<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_LimoExpress {

    /**
     * Endpoint officiel LimoExpress
     */
    const API_ENDPOINT = 'https://api.limoexpress.me/api/integration/booking-with-fees/';

    /**
     * Transmet une réservation WordPress vers LimoExpress
     *
     * @param int   $booking_id ID de la réservation WordPress
     * @param array $data       Données assainies du formulaire
     * @return bool
     */
    public static function send_booking( $booking_id, $data ) {
        $settings = get_option( 'etb_general_settings', array() );

        // 1. Contrôle d'activation
        if ( empty( $settings['limo_enabled'] ) || '1' !== $settings['limo_enabled'] ) {
            return false;
        }

        $api_token = $settings['limo_api_token'] ?? '';
        if ( empty( $api_token ) ) {
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', 'Jeton API LimoExpress manquant dans les réglages.' );
            return false;
        }

        // 2. Identification de la Classe de Véhicule LimoExpress
        $vehicle_class_id = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) {
                    $class_id = get_post_meta( $v_id, '_etb_limo_class_id', true );
                    if ( ! empty( $class_id ) ) {
                        $vehicle_class_id = trim( $class_id );
                        break;
                    }
                }
            }
        }

        if ( empty( $vehicle_class_id ) ) {
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', 'Aucun ID de classe LimoExpress défini pour le véhicule réservé.' );
            return false;
        }

        // 3. Gestion des dates, heures et durée (Format : YYYY-MM-DD HH:MM:SS)
        $time_val           = ! empty( $data['time'] ) ? $data['time'] : '09:00';
        $start_datetime_sec = sprintf( '%s %s:00', $data['date'], substr( $time_val, 0, 5 ) );

        $duration_hours = floatval( $data['pricing']['duration_hours'] ?? 1 );
        $duration_sec   = round( $duration_hours * 3600 );
        $start_ts       = strtotime( $start_datetime_sec ) ?: time();
        $dropoff_time   = date( 'Y-m-d H:i:00', $start_ts + $duration_sec );

        $dur_h              = floor( $duration_hours );
        $dur_m              = round( ( $duration_hours - $dur_h ) * 60 );
        $duration_formatted = sprintf( '%02d:%02d', $dur_h, $dur_m );

        // 4. Extraction du nom client
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

       // ====================================================================
        // 5. POINTS DE PASSAGE (CHECKPOINTS / TIMELINE DU CIRCUIT)
        // ====================================================================
        $checkpoints = array();
        if ( ! empty( $data['option_id'] ) ) {
            $circuit_data = ETB_Pricing_Engine::find_circuit_option_data( $data['option_id'], $data['circuit_id'] );
            if ( ! empty( $circuit_data['timeline'] ) && is_array( $circuit_data['timeline'] ) ) {
                $base_time   = $circuit_data['departure_time'] ?? '09:00';
                $booked_time = ! empty( $data['time'] ) ? $data['time'] : $base_time;
                $delta_sec   = strtotime( $booked_time ) - strtotime( $base_time );
                $order_index = 1;

                foreach ( $circuit_data['timeline'] as $step ) {
                    $step_title = ! empty( $step['title'] ) ? trim( $step['title'] ) : '';
                    if ( empty( $step_title ) ) continue;

                    // Calcul de l'heure ajustée selon le départ réel (Format HH:MM)
                    $step_raw_time = ! empty( $step['time'] ) ? $step['time'] : '09:00';
                    $step_ts       = strtotime( $step_raw_time );
                    $adjusted_hm   = $step_ts ? date( 'H:i', $step_ts + $delta_sec ) : substr( $step_raw_time, 0, 5 );

                    // TITRE PUR SEUL + CLEF OFFICIELLE arrival_time + NUMÉRO D'ORDRE
                    $checkpoints[] = array(
                        'location'     => array(
                            'name' => mb_substr( $step_title, 0, 100 ), // Titre pur sans description
                        ),
                        'arrival_time' => $adjusted_hm,              // Vraie colonne LimoExpress (07:08)
                        'time'         => $adjusted_hm,              // Doublon de secours Swagger
                        'order_number' => $order_index++,            // 1, 2, 3, 4...
                    );
                }
            }
        }
        

        // 6. Traitement des Extras & Siège Bébé
        $baby_seat_count = 0;
        $extras_summary  = '';
        $extra_fees      = array();

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name        = get_the_title( $e_id );
                    $e_price       = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total    = $e_price * $qty;
                    $limo_extra_id = trim( get_post_meta( $e_id, '_etb_limo_extra_id', true ) );

                    $extras_summary .= sprintf( '%s x %d, ', $e_name, $qty );

                    // Siège bébé (compteur natif LimoExpress)
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    if ( $line_total > 0 ) {
                        $category_slug = ! empty( $limo_extra_id ) ? $limo_extra_id : str_replace( '-', '_', sanitize_title( $e_name ) );
                        $extra_fees[]  = array(
                            'category' => $category_slug,
                            'amount'   => (float) round( $line_total, 2 ),
                        );
                    }
                }
            }
        }
        $extras_summary = rtrim( $extras_summary, ', ' );

        // 7. Gestion du Code Promo (Traçabilité texte avec devise)
        $grand_total     = floatval( $data['pricing']['grand_total'] ?? 0 );
        $discount_amount = floatval( $data['pricing']['discount_amount'] ?? 0 );
        $promo_code      = ! empty( $data['pricing']['promo_code'] ) ? $data['pricing']['promo_code'] : '';
        $currency_symbol = ! empty( $settings['currency'] ) ? sanitize_text_field( $settings['currency'] ) : '€';
        $promo_text      = '';

        if ( $discount_amount > 0 && ! empty( $promo_code ) ) {
            $promo_text = sprintf( "\n🏷️ Remise appliquée : %s (-%s %s)", $promo_code, $discount_amount, $currency_symbol );
        }

        // Préparation des résumés textuels
        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) {
                    $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
                }
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address   = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info     = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );
        $client_note      = ! empty( $data['note'] ) ? trim( $data['note'] ) : 'Aucune';

        // Notes professionnelles
        $note_for_driver = sprintf(
            "📋 DOSSIER WP #%d\n" .
            "⭐ Extras à bord : %s\n" .
            "📝 Note client : %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            $client_note
        );

        $dispatcher_note = sprintf(
            "══════ DÉTAILS RÉSERVATION #%d ══════\n" .
            "📍 Prestation : %s\n" .
            "🚘 Véhicule(s) : %s\n" .
            "👥 Passagers : %d Adulte(s), %d Enfant(s) (Total : %d)\n" .
            "🧳 Bagages : %d\n" .
            "⭐ Extras : %s" .
            "%s\n" .
            "💬 Demande spéciale : %s",
            $booking_id,
            $prestation_label,
            $vehicles_summary ?: 'Non spécifié',
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $total_passengers,
            intval( $data['luggage'] ),
            $extras_summary ?: 'Aucun',
            $promo_text,
            $client_note
        );

        // 8. Passagers : client principal uniquement
        $passengers_array = array(
            array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $data['email'],
                'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : '',
            ),
        );

        // 9. Construction du Payload conforme au Swagger officiel
        $payload = array(
            'booking_type_id'        => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'      => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'       => $vehicle_class_id,
            'pickup_time'            => $start_datetime_sec,
            'expected_drop_off_time' => $dropoff_time,
            'duration'               => $duration_formatted,
            'from_location'          => array( 'name' => $pickup_address ),
            'to_location'            => array( 'name' => $dropoff_info ),
            'price'                  => (int) round( $grand_total ),
            'price_type'             => 'NET',
            'passenger_count'        => (int) $total_passengers,
            'suitcase_count'         => (int) $data['luggage'],
            'baby_seat_count'        => (int) $baby_seat_count,
            'round_trip'             => ! empty( $data['option_id'] ),
            'note'                   => $dispatcher_note,
            'note_for_driver'        => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text'     => substr( $data['name'], 0, 50 ),
            'passengers'             => $passengers_array,
            'checkpoints'            => $checkpoints, // <-- Injection automatique des étapes du circuit !
            'extra_fees'             => $extra_fees,
        );

        // 10. Requête HTTP PUT vers LimoExpress
        $response = wp_remote_request( self::API_ENDPOINT, array(
            'method'    => 'PUT',
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $api_token,
            ),
            'body'      => wp_json_encode( $payload ),
            'timeout'   => 15,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', $response->get_error_message() );
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $body        = json_decode( $raw_body, true );

        if ( $status_code >= 200 && $status_code < 300 ) {
            $limo_id = $body['data']['number'] ?? $body['data']['internal_number'] ?? $body['data']['id'] ?? 'OK';
            update_post_meta( $booking_id, '_etb_limo_status', 'synced' );
            update_post_meta( $booking_id, '_etb_limo_booking_id', $limo_id );
            delete_post_meta( $booking_id, '_etb_limo_error' );
            return true;
        } else {
            $error_details = '';
            if ( isset( $body['errors'] ) && is_array( $body['errors'] ) ) {
                $err_parts = array();
                foreach ( $body['errors'] as $f => $m ) {
                    $err_parts[] = sprintf( '[%s: %s]', $f, is_array( $m ) ? implode( ', ', $m ) : $m );
                }
                $error_details = implode( ' ', $err_parts );
            } elseif ( isset( $body['message'] ) ) {
                $error_details = $body['message'];
            } else {
                $error_details = substr( strip_tags( $raw_body ), 0, 200 );
            }

            $error_log_msg = sprintf( 'HTTP %d: %s', $status_code, $error_details );
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', $error_log_msg );
            return false;
        }
    }

    /**
     * Récupère la liste des clients depuis LimoExpress (avec cache WordPress de 1 heure)
     */
    public static function get_clients( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( 'etb_limo_clients_cache' );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        $settings = get_option( 'etb_general_settings', array() );
        $token    = $settings['limo_api_token'] ?? '';
        if ( empty( $token ) ) {
            return array();
        }

        $response = wp_remote_get( 'https://api.limoexpress.me/api/integration/clients', array(
            'headers' => array(
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return array();
        }

        $body    = json_decode( wp_remote_retrieve_body( $response ), true );
        $clients = array();

        if ( ! empty( $body['data'] ) && is_array( $body['data'] ) ) {
            foreach ( $body['data'] as $c ) {
                if ( ! empty( $c['id'] ) && ! empty( $c['name'] ) ) {
                    $clients[] = array(
                        'id'   => sanitize_text_field( $c['id'] ),
                        'name' => sanitize_text_field( $c['name'] ),
                    );
                }
            }
        }

        set_transient( 'etb_limo_clients_cache', $clients, HOUR_IN_SECONDS );
        return $clients;
    }

    /**
     * Récupère la liste des classes de véhicules depuis LimoExpress (avec cache WordPress de 1 heure)
     */
    public static function get_vehicle_classes( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( 'etb_limo_classes_cache' );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        $settings = get_option( 'etb_general_settings', array() );
        $token    = $settings['limo_api_token'] ?? '';
        if ( empty( $token ) ) {
            return array();
        }

        $response = wp_remote_get( 'https://api.limoexpress.me/api/integration/vehicle-classes', array(
            'headers' => array(
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return array();
        }

        $body    = json_decode( wp_remote_retrieve_body( $response ), true );
        $classes = array();

        if ( ! empty( $body['data'] ) && is_array( $body['data'] ) ) {
            foreach ( $body['data'] as $cls ) {
                if ( ! empty( $cls['id'] ) && ! empty( $cls['name'] ) ) {
                    $classes[] = array(
                        'id'   => sanitize_text_field( $cls['id'] ),
                        'name' => sanitize_text_field( $cls['name'] ),
                    );
                }
            }
        }

        set_transient( 'etb_limo_classes_cache', $classes, HOUR_IN_SECONDS );
        return $classes;
    }
}