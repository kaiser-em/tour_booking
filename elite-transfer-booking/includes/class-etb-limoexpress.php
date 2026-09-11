<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_LimoExpress {

    /**
     * Endpoint officiel LimoExpress
     */
    const API_ENDPOINT = 'https://api.limoexpress.me/api/integration/booking-with-fees/';

    /**
     * Branche le module LimoExpress sur le socle ETB (Hooks WordPress)
     */
    public static function init_hooks() {
        add_filter( 'etb_sanitize_dispatcher_settings', array( __CLASS__, 'sanitize_settings' ), 10, 2 );
        add_action( 'etb_render_dispatcher_settings', array( __CLASS__, 'render_settings' ) );
    }

    /**
     * Sauvegarde des réglages propres à LimoExpress
     */
    public static function sanitize_settings( $new_input, $raw_input ) {
        if ( isset( $new_input['active_dispatcher'] ) && 'limoexpress' === $new_input['active_dispatcher'] ) {
            $new_input['limo_api_token']         = ! empty( $raw_input['limo_api_token'] ) ? sanitize_text_field( trim( $raw_input['limo_api_token'] ) ) : '';
            $new_input['limo_client_id']         = ! empty( $raw_input['limo_client_id'] ) ? sanitize_text_field( trim( $raw_input['limo_client_id'] ) ) : '';
            $new_input['limo_booking_type_id']   = ! empty( $raw_input['limo_booking_type_id'] ) ? sanitize_text_field( trim( $raw_input['limo_booking_type_id'] ) ) : '';
            $new_input['limo_booking_status_id'] = ! empty( $raw_input['limo_booking_status_id'] ) ? sanitize_text_field( trim( $raw_input['limo_booking_status_id'] ) ) : '';

            // Purge automatique des caches
            delete_transient( 'etb_limo_clients_cache' );
            delete_transient( 'etb_limo_classes_cache' );
            delete_transient( 'etb_limo_types_cache' );
            delete_transient( 'etb_limo_statuses_cache' );
        }
        return $new_input;
    }

    /**
     * Affiche l'interface de réglages LimoExpress dans WordPress
     */
    public static function render_settings( $options ) {
        if ( empty( $options['active_dispatcher'] ) || 'limoexpress' !== $options['active_dispatcher'] ) {
            return;
        }
        ?>
        <tr class="etb-dispatcher-row limoexpress-row">
            <th scope="row" colspan="2">
                <div style="background: #fff0f0; border-left: 4px solid #dc2626; padding: 12px; margin-top: 10px;">
                    <h4 style="margin: 0 0 5px 0; color: #991b1b;">🔴 Configuration LimoExpress</h4>
                    <p style="margin: 0; font-size: 13px;">Module connecté. Remplissez votre jeton pour charger automatiquement vos données.</p>
                </div>
            </th>
        </tr>
        <tr class="etb-dispatcher-row limoexpress-row">
            <th scope="row">Jeton API (Bearer Token)</th>
            <td>
                <input type="password" name="etb_general_settings[limo_api_token]" value="<?php echo esc_attr( $options['limo_api_token'] ?? '' ); ?>" class="regular-text" placeholder="Collez votre Token ici...">
                <p class="description">Généré dans LimoExpress (Administration &gt; Organization &gt; Advanced Settings &gt; API Integration).</p>
            </td>
        </tr>

        <?php 
        $limo_clients = self::get_clients();
        $current_client_id = $options['limo_client_id'] ?? '';
        ?>
        <tr class="etb-dispatcher-row limoexpress-row">
            <th scope="row">Client par défaut LimoExpress</th>
            <td>
                <?php if ( ! empty( $limo_clients ) ) : ?>
                    <select name="etb_general_settings[limo_client_id]" class="regular-text">
                        <option value="">-- Automatique (Détecté par LimoExpress) --</option>
                        <?php foreach ( $limo_clients as $client ) : ?>
                            <option value="<?php echo esc_attr( $client['id'] ); ?>" <?php selected( $current_client_id, $client['id'] ); ?>>
                                👤 <?php echo esc_html( $client['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="text" name="etb_general_settings[limo_client_id]" value="<?php echo esc_attr( $current_client_id ); ?>" class="regular-text">
                <?php endif; ?>
            </td>
        </tr>

        <?php 
        $limo_types        = self::get_booking_types();
        $current_type_id   = $options['limo_booking_type_id'] ?? '';
        $limo_statuses     = self::get_booking_statuses();
        $current_status_id = $options['limo_booking_status_id'] ?? '';
        ?>
        <tr class="etb-dispatcher-row limoexpress-row">
            <th scope="row">Type de réservation LimoExpress</th>
            <td>
                <?php if ( ! empty( $limo_types ) ) : ?>
                    <select name="etb_general_settings[limo_booking_type_id]" class="regular-text">
                        <?php foreach ( $limo_types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type['id'] ); ?>" <?php selected( $current_type_id, $type['id'] ); ?>>
                                📋 <?php echo esc_html( $type['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="text" name="etb_general_settings[limo_booking_type_id]" value="<?php echo esc_attr( $current_type_id ); ?>" class="regular-text">
                <?php endif; ?>
            </td>
        </tr>
        <tr class="etb-dispatcher-row limoexpress-row">
            <th scope="row">Statut initial LimoExpress</th>
            <td>
                <?php if ( ! empty( $limo_statuses ) ) : ?>
                    <select name="etb_general_settings[limo_booking_status_id]" class="regular-text">
                        <?php foreach ( $limo_statuses as $st ) : ?>
                            <option value="<?php echo esc_attr( $st['id'] ); ?>" <?php selected( $current_status_id, $st['id'] ); ?>>
                                ⏳ <?php echo esc_html( $st['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="text" name="etb_general_settings[limo_booking_status_id]" value="<?php echo esc_attr( $current_status_id ); ?>" class="regular-text">
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Transmet une réservation WordPress vers LimoExpress
     */
    public static function send_booking( $booking_id, $data ) {
        $settings = get_option( 'etb_general_settings', array() );

        // 1. Contrôle d'activation (compatible Dispatcher Manager)
        $is_active = ( ! empty( $settings['active_dispatcher'] ) && 'limoexpress' === $settings['active_dispatcher'] )
                  || ( ! empty( $settings['limo_enabled'] ) && '1' === $settings['limo_enabled'] );

        if ( ! $is_active ) {
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', 'LimoExpress n\'est pas sélectionné comme application active dans les réglages.' );
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

        // 2bis. Création ou détection automatique du client voyageur dans LimoExpress
        $client_id = self::get_or_create_client( $data, $settings, $booking_id );

        // 3. Gestion des dates, heures et durée
        $time_val           = ! empty( $data['time'] ) ? $data['time'] : '09:00';
        $start_datetime_sec = sprintf( '%s %s:00', $data['date'], substr( $time_val, 0, 5 ) );

        $duration_hours = floatval( $data['pricing']['duration_hours'] ?? 1 );
        $duration_sec   = round( $duration_hours * 3600 );
        $start_ts       = strtotime( $start_datetime_sec ) ?: time();
        $dropoff_time   = date( 'Y-m-d H:i:00', $start_ts + $duration_sec );

        $dur_h              = floor( $duration_hours );
        $dur_m              = round( ( $duration_hours - $dur_h ) * 60 );
        $duration_formatted = sprintf( '%02d:%02d', $dur_h, $dur_m );

        // 4. Extraction du nom client (Sans forcer aucun texte par défaut)
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : '--';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        // 5. Points de passage (Checkpoints / Timeline du circuit)
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

                    $step_raw_time = ! empty( $step['time'] ) ? $step['time'] : '09:00';
                    $step_ts       = strtotime( $step_raw_time );
                    $adjusted_hm   = $step_ts ? date( 'H:i', $step_ts + $delta_sec ) : substr( $step_raw_time, 0, 5 );

                    $checkpoints[] = array(
                        'location'     => array(
                            'name' => mb_substr( $step_title, 0, 100 ),
                        ),
                        'arrival_time' => $adjusted_hm,
                        'time'         => $adjusted_hm,
                        'order_number' => $order_index++,
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
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= sprintf( '%s x %d, ', $e_name, $qty );

                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    if ( $line_total > 0 ) {
                        $category_slug = str_replace( '-', '_', sanitize_title( $e_name ) );
                        $extra_fees[]  = array(
                            'category' => $category_slug,
                            'amount'   => (float) round( $line_total, 2 ),
                        );
                    }
                }
            }
        }
        $extras_summary = rtrim( $extras_summary, ', ' );

        // 7. Gestion du Code Promo
        $grand_total     = floatval( $data['pricing']['grand_total'] ?? 0 );
        $discount_amount = floatval( $data['pricing']['discount_amount'] ?? 0 );
        $promo_code      = ! empty( $data['pricing']['promo_code'] ) ? $data['pricing']['promo_code'] : '';
        $currency_symbol = ! empty( $settings['currency'] ) ? sanitize_text_field( $settings['currency'] ) : '€';
        $promo_text      = '';

        if ( $discount_amount > 0 && ! empty( $promo_code ) ) {
            $promo_text = sprintf( "\n🏷️ Remise appliquée : %s (-%s %s)", $promo_code, $discount_amount, $currency_symbol );
        }

        // Préparation des résumés
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

        // Notes pour le chauffeur et le répartiteur
        $note_for_driver = sprintf(
            "📋 DOSSIER WP #%d\n" .
            "⭐ Extras : %s\n" .
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

        // Résolution dynamique du Type de réservation (Zéro-Hardcode)
        $booking_type_id = ! empty( $settings['limo_booking_type_id'] ) ? trim( $settings['limo_booking_type_id'] ) : '';
        if ( empty( $booking_type_id ) ) {
            $available_types = self::get_booking_types();
            $booking_type_id = ! empty( $available_types[0]['id'] ) ? $available_types[0]['id'] : '';
        }

        // Résolution dynamique du Statut initial (Zéro-Hardcode)
        $booking_status_id = ! empty( $settings['limo_booking_status_id'] ) ? trim( $settings['limo_booking_status_id'] ) : '';
        if ( empty( $booking_status_id ) ) {
            $available_statuses = self::get_booking_statuses();
            foreach ( $available_statuses as $st ) {
                if ( stripos( $st['name'], 'pend' ) !== false || stripos( $st['name'], 'attent' ) !== false ) {
                    $booking_status_id = $st['id'];
                    break;
                }
            }
            if ( empty( $booking_status_id ) && ! empty( $available_statuses[0]['id'] ) ) {
                $booking_status_id = $available_statuses[0]['id'];
            }
        }

        if ( empty( $booking_type_id ) || empty( $booking_status_id ) ) {
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', 'Impossible de déterminer le type ou statut de réservation LimoExpress.' );
            return false;
        }

        // 9. Construction du Payload conforme au Swagger officiel
        $payload = array(
            'booking_type_id'        => (string) $booking_type_id,
            'booking_status_id'      => (string) $booking_status_id,
            'vehicle_class_id'       => $vehicle_class_id,
            'client_id'              => (string) $client_id,
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
            'checkpoints'            => $checkpoints,
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
            update_post_meta( $booking_id, '_etb_limo_error', 'Erreur WP HTTP: ' . $response->get_error_message() );
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
            $err_details = '';
            if ( isset( $body['errors'] ) && is_array( $body['errors'] ) ) {
                $err_parts = array();
                foreach ( $body['errors'] as $f => $m ) {
                    $err_parts[] = sprintf( '[%s: %s]', $f, is_array( $m ) ? implode( ', ', $m ) : $m );
                }
                $err_details = implode( ' ', $err_parts );
            } elseif ( isset( $body['message'] ) ) {
                $err_details = $body['message'];
            } else {
                $err_details = substr( strip_tags( $raw_body ), 0, 200 );
            }
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', sprintf( 'HTTP %s: %s', $status_code, $err_details ) );
            return false;
        }
    }

    /**
     * Récupère la liste des clients depuis LimoExpress (Simple GET propre)
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
                        'id'    => sanitize_text_field( $c['id'] ),
                        'name'  => sanitize_text_field( $c['name'] ),
                        'email' => ! empty( $c['email'] ) ? sanitize_email( $c['email'] ) : '',
                    );
                }
            }
        }

        set_transient( 'etb_limo_clients_cache', $clients, HOUR_IN_SECONDS );
        return $clients;
    }

    /**
     * Récupère la liste des classes de véhicules depuis LimoExpress (Simple GET propre)
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

    /**
     * Récupère la liste des types de réservation depuis LimoExpress (Simple GET propre)
     */
    public static function get_booking_types( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( 'etb_limo_types_cache' );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        $settings = get_option( 'etb_general_settings', array() );
        $token    = $settings['limo_api_token'] ?? '';
        if ( empty( $token ) ) {
            return array();
        }

        $response = wp_remote_get( 'https://api.limoexpress.me/api/integration/booking-types', array(
            'headers' => array(
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return array();
        }

        $body  = json_decode( wp_remote_retrieve_body( $response ), true );
        $types = array();

        if ( ! empty( $body['data'] ) && is_array( $body['data'] ) ) {
            foreach ( $body['data'] as $t ) {
                if ( ! empty( $t['id'] ) ) {
                    $types[] = array(
                        'id'   => sanitize_text_field( $t['id'] ),
                        'name' => sanitize_text_field( $t['name'] ?? $t['title'] ?? 'Standard' ),
                    );
                }
            }
        }

        set_transient( 'etb_limo_types_cache', $types, HOUR_IN_SECONDS );
        return $types;
    }

    /**
     * Récupère la liste des statuts de réservation depuis LimoExpress (Simple GET propre)
     */
    public static function get_booking_statuses( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( 'etb_limo_statuses_cache' );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        $settings = get_option( 'etb_general_settings', array() );
        $token    = $settings['limo_api_token'] ?? '';
        if ( empty( $token ) ) {
            return array();
        }

        $response = wp_remote_get( 'https://api.limoexpress.me/api/integration/booking-statuses', array(
            'headers' => array(
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return array();
        }

        $body     = json_decode( wp_remote_retrieve_body( $response ), true );
        $statuses = array();

        if ( ! empty( $body['data'] ) && is_array( $body['data'] ) ) {
            foreach ( $body['data'] as $s ) {
                if ( ! empty( $s['id'] ) ) {
                    $statuses[] = array(
                        'id'   => sanitize_text_field( $s['id'] ),
                        'name' => sanitize_text_field( $s['name'] ?? $s['title'] ?? 'Statut' ),
                    );
                }
            }
        }

        set_transient( 'etb_limo_statuses_cache', $statuses, HOUR_IN_SECONDS );
        return $statuses;
    }

    /**
     * Recherche ou crée automatiquement le client voyageur dans LimoExpress (Méthode PUT officielle isolée)
     *
     * @param array $data       Données du formulaire
     * @param array $settings   Réglages généraux
     * @param int   $booking_id ID de la réservation WordPress
     * @return string UUID du client
     */
    public static function get_or_create_client( $data, $settings, $booking_id = 0 ) {
        $token = $settings['limo_api_token'] ?? '';
        
        // Client de secours par défaut
        $default_fallback_id = ! empty( $settings['limo_client_id'] ) 
            ? trim( $settings['limo_client_id'] ) 
            : 'e56ea49f-8533-41b9-97c9-17343ee35a4e';

        if ( empty( $token ) || empty( $data['name'] ) ) {
            return $default_fallback_id;
        }

        // 1. Mémoire locale WordPress (Anti-doublon)
        if ( $booking_id ) {
            $saved_client_uuid = get_post_meta( $booking_id, '_etb_limo_client_id', true );
            if ( ! empty( $saved_client_uuid ) ) {
                return trim( $saved_client_uuid );
            }
        }

        $customer_email = ! empty( $data['email'] ) ? strtolower( trim( $data['email'] ) ) : '';

        // 2. Recherche par e-mail dans la liste existante
        if ( ! empty( $customer_email ) ) {
            $existing_clients = self::get_clients();
            if ( ! empty( $existing_clients ) && is_array( $existing_clients ) ) {
                foreach ( $existing_clients as $client ) {
                    if ( ! empty( $client['email'] ) && strtolower( $client['email'] ) === $customer_email ) {
                        $found_uuid = trim( $client['id'] );
                        if ( $booking_id ) {
                            update_post_meta( $booking_id, '_etb_limo_client_id', $found_uuid );
                        }
                        return $found_uuid;
                    }
                }
            }
        }

        // 3. Création du nouveau client via PUT /api/integration/clients
        $client_payload = array(
            'name'   => sanitize_text_field( $data['name'] ),
            'type'   => 'natural_person',
            'active' => true,
        );

        if ( ! empty( $data['email'] ) ) {
            $client_payload['email'] = sanitize_email( $data['email'] );
        }
        if ( ! empty( $data['phone'] ) ) {
            $client_payload['phone'] = sanitize_text_field( $data['phone'] );
        }
        if ( ! empty( $data['pickup_address'] ) ) {
            $client_payload['address'] = sanitize_text_field( $data['pickup_address'] );
        }

        $response = wp_remote_request( 'https://api.limoexpress.me/api/integration/clients', array(
            'method'  => 'PUT',
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body'    => wp_json_encode( $client_payload ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return $default_fallback_id;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $body        = json_decode( $raw_body, true );

        if ( $status_code >= 200 && $status_code < 300 && ! empty( $body['data']['id'] ) ) {
            $new_client_uuid = sanitize_text_field( $body['data']['id'] );
            delete_transient( 'etb_limo_clients_cache' );
            if ( $booking_id ) {
                update_post_meta( $booking_id, '_etb_limo_client_id', $new_client_uuid );
            }
            return $new_client_uuid;
        }

        return $default_fallback_id;
    }
}