<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Octopus {

    const API_BASE = 'https://api.octopuspro.com/api/v1';

    /**
     * Recherche automatique du premier chauffeur/employé actif
     */
    private static function get_active_fieldworker_id( $api_key ) {
        $response = wp_remote_get( self::API_BASE . '/fieldworkers', array(
            'headers'   => array(
                'Authorization' => 'Bearer ' . $api_key,
                'X-API-KEY'     => $api_key,
            ),
            'timeout'   => 15,
            'sslverify' => true,
        ) );

        if ( ! is_wp_error( $response ) ) {
            $body    = json_decode( wp_remote_retrieve_body( $response ), true );
            $workers = $body['data'] ?? $body['results'] ?? ( is_array( $body ) ? $body : array() );

            if ( ! empty( $workers ) && is_array( $workers ) ) {
                $first_id = $workers[0]['id'] ?? $workers[0]['fieldworker_id'] ?? $workers[0]['user_id'] ?? 0;
                if ( ! empty( $first_id ) ) {
                    return absint( $first_id );
                }
            }
        }

        return 0;
    }

    /**
     * Recherche automatique de l'ID du service par son nom via l'API
     */
    private static function get_service_id_by_name( $api_key, $service_name = 'Private Tours' ) {
        $response = wp_remote_get( self::API_BASE . '/services', array(
            'headers'   => array(
                'Authorization' => 'Bearer ' . $api_key,
                'X-API-KEY'     => $api_key,
            ),
            'timeout'   => 15,
            'sslverify' => true,
        ) );

        if ( ! is_wp_error( $response ) ) {
            $body     = json_decode( wp_remote_retrieve_body( $response ), true );
            $services = $body['data'] ?? $body['results'] ?? ( is_array( $body ) ? $body : array() );

            if ( ! empty( $services ) && is_array( $services ) ) {
                foreach ( $services as $srv ) {
                    $name = $srv['name'] ?? $srv['service_name'] ?? '';
                    $id   = $srv['id'] ?? $srv['service_id'] ?? 0;
                    if ( strcasecmp( trim( $name ), trim( $service_name ) ) === 0 ) {
                        return absint( $id );
                    }
                }
                $first_id = $services[0]['id'] ?? $services[0]['service_id'] ?? 95405;
                return absint( $first_id );
            }
        }

        return 95405;
    }

    /**
     * Crée ou retrouve le client dans le CRM OctopusPro et extrait son customer_id
     */
    private static function get_or_create_customer( $api_key, $name, $email, $phone = '', &$error_out = '' ) {
        $name_parts = explode( ' ', trim( $name ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $customer_payload = array(
            'customer_type_id' => 1,
            'first_name'       => $first_name,
            'last_name'        => $last_name,
            'contacts'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'phone'      => ! empty( $phone ) ? $phone : '',
                    'is_primary' => 1,
                )
            ),
        );

        $response = wp_remote_post( self::API_BASE . '/customers', array(
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'X-API-KEY'     => $api_key,
            ),
            'body'      => wp_json_encode( $customer_payload ),
            'timeout'   => 15,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            $error_out = 'Erreur réseau Customer: ' . $response->get_error_message();
            return 0;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $body        = json_decode( $raw_body, true );

        if ( $status_code >= 200 && $status_code < 300 && is_array( $body ) ) {
            $customer_id = $body['id'] 
                ?? $body['data']['id'] 
                ?? $body['customer_id'] 
                ?? $body['data']['customer_id'] 
                ?? $body['customer']['id'] 
                ?? $body['data']['customer']['id'] 
                ?? $body['contacts'][0]['customer_id'] 
                ?? $body['data']['contacts'][0]['customer_id'] 
                ?? 0;

            if ( ! empty( $customer_id ) ) {
                return absint( $customer_id );
            }
        }

        // Recherche par email
        $search_url = add_query_arg( array( 'email' => $email ), self::API_BASE . '/customers' );
        $get_res    = wp_remote_get( $search_url, array(
            'headers'   => array(
                'Authorization' => 'Bearer ' . $api_key,
                'X-API-KEY'     => $api_key,
            ),
            'timeout'   => 15,
            'sslverify' => true,
        ) );

        if ( ! is_wp_error( $get_res ) ) {
            $get_body = json_decode( wp_remote_retrieve_body( $get_res ), true );
            if ( is_array( $get_body ) ) {
                $customer_id = $get_body['data'][0]['id'] 
                    ?? $get_body['results'][0]['id'] 
                    ?? $get_body['customers'][0]['id'] 
                    ?? $get_body['data']['customers'][0]['id'] 
                    ?? $get_body[0]['id'] 
                    ?? 0;

                if ( ! empty( $customer_id ) ) {
                    return absint( $customer_id );
                }
            }
        }

        $field_errors = '';
        if ( is_array( $body ) && ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
            $err_parts = array();
            foreach ( $body['errors'] as $f => $m ) {
                $err_parts[] = sprintf( '[%s: %s]', $f, is_array( $m ) ? implode( ', ', $m ) : (string) $m );
            }
            $field_errors = implode( ' ', $err_parts );
        } elseif ( is_array( $body ) && ! empty( $body['message'] ) ) {
            $field_errors = $body['message'];
        } else {
            $field_errors = ! empty( $raw_body ) ? substr( trim( strip_tags( $raw_body ) ), 0, 250 ) : 'Réponse vide';
        }

        $error_out = sprintf( 'Client HTTP %d: %s', $status_code, $field_errors );
        return 0;
    }

    /**
     * Transmet une réservation WordPress vers OctopusPro
     */
    public static function send_booking_to_octopus( $booking_id, $data ) {
        $settings = get_option( 'etb_general_settings', array() );

        if ( empty( $settings['octopus_enabled'] ) || $settings['octopus_enabled'] !== '1' ) {
            return false;
        }

        $api_key = $settings['octopus_api_key'] ?? '';
        if ( empty( $api_key ) ) {
            error_log( 'ETB Octopus Error: Clé API manquante dans les réglages.' );
            return false;
        }

        // 1. Service ID
        $configured_id = absint( $settings['octopus_service_id'] ?? 0 );
        $service_id    = $configured_id > 0 ? $configured_id : self::get_service_id_by_name( $api_key, 'Private Tours' );

        // 2. Chauffeur actif
        $fieldworker_id = self::get_active_fieldworker_id( $api_key );

        // 3. Source ID (111636 = Website)
        $configured_source = absint( $settings['octopus_source_id'] ?? 0 );
        $source_id         = ( $configured_source > 100 ) ? $configured_source : 111636;

        // 4. Client ID
        $customer_error = '';
        $customer_id    = self::get_or_create_customer( $api_key, $data['name'], $data['email'], $data['phone'] ?? '', $customer_error );
        if ( ! $customer_id ) {
            update_post_meta( $booking_id, '_etb_octopus_status', 'failed' );
            update_post_meta( $booking_id, '_etb_octopus_error', $customer_error );
            return false;
        }

        // 5. Dates et Heures exactes
        $time_val = ! empty( $data['time'] ) ? $data['time'] : '09:00';
        if ( strlen( $time_val ) === 5 ) {
            $time_val .= ':00';
        }
        $booking_start = sprintf( '%s %s', $data['date'], $time_val );
        
        $duration_hours = floatval( $data['pricing']['duration_hours'] ?? 1 );
        $duration_sec   = round( $duration_hours * 3600 );
        $start_ts       = strtotime( $booking_start ) ?: time();
        $booking_end    = date( 'Y-m-d H:i:s', $start_ts + $duration_sec );

        // 6. Ordre de mission complet formaté
        $prestation_label = ! empty( $data['option_id'] ) 
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] ) 
            : 'Transfert standard';

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $extras_summary = '';
        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) $extras_summary .= get_the_title( $e_id ) . ' x ' . $qty . ', ';
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $grand_total = floatval( $data['pricing']['grand_total'] ?? 0 );

        $job_notes = sprintf(
            "<strong>--- ORDRE DE MISSION WORDPRESS #%d ---</strong><br><strong>Prestation :</strong> %s<br><strong>Véhicule(s) :</strong> %s<br><strong>Passagers :</strong> %d Adulte(s), %d Enfant(s)<br><strong>Bagages :</strong> %d<br><strong>Prise en charge :</strong> %s<br><strong>Dépose :</strong> %s<br><strong>Options/Extras :</strong> %s<br><strong>Demande spéciale :</strong> %s<br><strong>Total Facturé :</strong> %s $",
            $booking_id,
            $prestation_label,
            $vehicles_summary ?: 'Non spécifié',
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $data['luggage'] ?? 0,
            ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié',
            ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : 'Identique au lieu de départ',
            $extras_summary ?: 'Aucune',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune',
            number_format( $grand_total, 2, '.', '' )
        );

        // 7. Ligne de service avec tarif officiel et description
        $service_item = array(
            'service_id'           => $service_id,
            'service_clone'        => 0,
            'service_rate'         => $grand_total,
            'rate'                 => $grand_total,
            'price'                => $grand_total,
            'quantity'             => 1.0,
            'description'          => $job_notes,
            'service_instructions' => $job_notes,
        );
        if ( $fieldworker_id > 0 ) {
            $service_item['fieldworker_id'] = $fieldworker_id;
        }

        // 8. Tableau des adresses
        $addresses = array();
        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : ( $data['pickup_id'] ? get_the_title( $data['pickup_id'] ) : '' );
        if ( ! empty( $pickup_address ) ) {
            $addresses[] = $pickup_address;
        }
        if ( ! empty( $data['dropoff_info'] ) ) {
            $addresses[] = $data['dropoff_info'];
        }

        // Préparation des valeurs pour vos Custom Fields OctopusPro
        $custom_dropoff  = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : 'Identique au lieu de départ';
        $custom_vehicles = ! empty( $vehicles_summary ) ? $vehicles_summary : 'Non spécifié';

        // Format tableau d'objets normalisé
        $formatted_custom_fields = array(
            array( 'id' => 18492, 'value' => $custom_vehicles ),
            array( 'id' => 18493, 'value' => $custom_dropoff ),
        );

        // Clés par nom et par ID pour compatibilité maximale
        $keyed_custom_fields = array(
            'vehicules_18492'         => $custom_vehicles,
            'drop_off_location_18493' => $custom_dropoff,
            18492                     => $custom_vehicles,
            18493                     => $custom_dropoff,
        );

        // Injection dans la ligne de service
        $service_item['custom_fields'] = $formatted_custom_fields;

        // Payload complet
        $payload = array(
            'customer_id'          => $customer_id,
            'booking_status_id'    => 1,
            'source_id'            => $source_id, // 111636 = Website
            'booking_start'        => $booking_start,
            'booking_end'          => $booking_end,
            'services'             => array( $service_item ),
            'custom_fields'        => $formatted_custom_fields,
            'custom_field_values'  => $keyed_custom_fields,
            'special_instructions' => $job_notes,
            'service_instructions' => $job_notes,
            'instructions'         => $job_notes,
            'notes'                => $job_notes,
            'reference_id'         => (string) $booking_id,
        );

        
        if ( ! empty( $addresses ) ) {
            $payload['addresses'] = $addresses;
        }
        if ( $fieldworker_id > 0 ) {
            $payload['fieldworker_id'] = $fieldworker_id;
        }

        // 10. Expédition POST vers l'API Bookings
        $response = wp_remote_post( self::API_BASE . '/bookings', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'X-API-KEY'     => $api_key,
            ),
            'body'      => wp_json_encode( $payload ),
            'timeout'   => 20,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            update_post_meta( $booking_id, '_etb_octopus_status', 'failed' );
            update_post_meta( $booking_id, '_etb_octopus_error', $error_message );
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $body        = json_decode( $raw_body, true );

        if ( $status_code >= 200 && $status_code < 300 ) {
            // Extraction robuste et infaillible du vrai numéro BOK-X
            $octopus_id = '';
            if ( is_array( $body ) ) {
                $octopus_id = ! empty( $body['booking_number'] ) ? $body['booking_number'] : '';
                if ( empty( $octopus_id ) && ! empty( $body['data']['booking_number'] ) ) {
                    $octopus_id = $body['data']['booking_number'];
                }
                if ( empty( $octopus_id ) && ! empty( $body['booking_code'] ) ) {
                    $octopus_id = $body['booking_code'];
                }
                if ( empty( $octopus_id ) && ! empty( $body['data']['booking_code'] ) ) {
                    $octopus_id = $body['data']['booking_code'];
                }
                if ( empty( $octopus_id ) && ! empty( $body['data']['id'] ) ) {
                    $octopus_id = 'BOK-' . $body['data']['id'];
                }
                if ( empty( $octopus_id ) && ! empty( $body['id'] ) ) {
                    $octopus_id = 'BOK-' . $body['id'];
                }
            }
            if ( empty( $octopus_id ) ) {
                $octopus_id = 'BOK-' . $booking_id;
            }

            update_post_meta( $booking_id, '_etb_octopus_status', 'synced' );
            update_post_meta( $booking_id, '_etb_octopus_booking_id', $octopus_id );
            return true;
        } else {
            $detailed_error = '';
            if ( is_array( $body ) ) {
                if ( ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
                    $field_errors = array();
                    foreach ( $body['errors'] as $field => $messages ) {
                        $msg_str = is_array( $messages ) ? implode( ', ', $messages ) : (string) $messages;
                        $field_errors[] = sprintf( '[%s: %s]', $field, $msg_str );
                    }
                    $detailed_error = implode( ' ', $field_errors );
                } elseif ( ! empty( $body['message'] ) ) {
                    $detailed_error = $body['message'];
                }
            }
            if ( empty( $detailed_error ) ) {
                $detailed_error = ! empty( $raw_body ) ? substr( trim( strip_tags( $raw_body ) ), 0, 250 ) : wp_remote_retrieve_response_message( $response );
            }

            $error_log_msg = sprintf( 'HTTP %d: %s', $status_code, $detailed_error );
            update_post_meta( $booking_id, '_etb_octopus_status', 'failed' );
            update_post_meta( $booking_id, '_etb_octopus_error', $error_log_msg );
            return false;
        }
    }
}