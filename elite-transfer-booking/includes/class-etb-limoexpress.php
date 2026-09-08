<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_LimoExpress {

    // L'endpoint officiel validé dans votre test Swagger
    const API_ENDPOINT = 'https://api.limoexpress.me/api/integration/bookings/';

    /**
     * Transmet une réservation WordPress vers LimoExpress
     */
    public static function send_booking( $booking_id, $data ) {
        $settings = get_option( 'etb_general_settings', array() );

        // 1. Vérification de l'activation
        if ( empty( $settings['limo_enabled'] ) || $settings['limo_enabled'] !== '1' ) {
            return false;
        }

        $api_token = $settings['limo_api_token'] ?? '';
        if ( empty( $api_token ) ) {
            return false;
        }

        // 2. Identification de la Classe de Véhicule LimoExpress
        $vehicle_class_id = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) {
                    $class_id = get_post_meta( $v_id, '_etb_limo_class_id', true );
                    if ( ! empty( $class_id ) ) {
                        $vehicle_class_id = $class_id;
                        break; // On prend la classe du premier véhicule principal de la commande
                    }
                }
            }
        }

        if ( empty( $vehicle_class_id ) ) {
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', 'Aucun ID de classe LimoExpress défini pour ce véhicule.' );
            return false;
        }

        // 3. Gestion des dates, heures et durée (Format : YYYY-MM-DD HH:MM)
        $time_val = ! empty( $data['time'] ) ? $data['time'] : '09:00';
        $start_datetime = sprintf( '%s %s', $data['date'], substr( $time_val, 0, 5 ) );

        $duration_hours = floatval( $data['pricing']['duration_hours'] ?? 1 );
        $duration_sec   = round( $duration_hours * 3600 );
        $start_ts       = strtotime( $data['date'] . ' ' . $time_val ) ?: time();
        $end_datetime   = date( 'Y-m-d H:i', $start_ts + $duration_sec );

        // Formatage de la durée pour LimoExpress (HH:MM)
        $dur_h = floor( $duration_hours );
        $dur_m = round( ($duration_hours - $dur_h) * 60 );
        $duration_formatted = sprintf( '%02d:%02d', $dur_h, $dur_m );

        // 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees,
            
            // Forcer le type de client "Regular" (Individuel)
            'client'             => array(
                'name'  => $data['name'],
                'email' => $data['email'],
                'phone' => ! empty( $data['phone'] ) ? $data['phone'] : '',
                'type'  => 'regular' // ou 'individual' selon les réglages LimoExpress
            ),
            
            // Génération dynamique des passagers pour que LimoExpress compte juste
            'passengers'         => array_merge(
                array(
                    // Passager principal (Le client qui réserve)
                    array(
                        'first_name' => $first_name,
                        'last_name'  => $last_name,
                        'email'      => $data['email'],
                        'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                    )
                ),
                // Passagers supplémentaires générés automatiquement
                array_map( function($i) {
                    return array(
                        'first_name' => 'Passager',
                        'last_name'  => (string) ($i + 1), // "Passager 2", "Passager 3"...
                        'email'      => '',
                        'phone'      => ''
                    );
                }, range( 1, max( 1, $total_passengers ) - 1 ) )
            )
        );

        // Nettoyage si $total_passengers = 1
        if ( $total_passengers <= 1 ) {
            $payload['passengers'] = array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            );
        }

        // 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';



        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );// 4. Extraction du nom, des extras et préparation des notes
        $name_parts = explode( ' ', trim( $data['name'] ), 2 );
        $first_name = ! empty( $name_parts[0] ) ? $name_parts[0] : 'Client';
        $last_name  = ! empty( $name_parts[1] ) ? $name_parts[1] : 'ETB';

        $prestation_label = ! empty( $data['option_id'] )
            ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'] )
            : 'Transfert standard';

        $baby_seat_count    = 0;
        $extras_summary     = '';
        $driving_extra_fees = array(); // NOUVEAU : Tableau des frais supplémentaires pour LimoExpress

        if ( ! empty( $data['extras'] ) ) {
            foreach ( $data['extras'] as $e_id => $qty ) {
                if ( $qty > 0 ) {
                    $e_name     = get_the_title( $e_id );
                    $e_price    = floatval( get_post_meta( $e_id, '_etb_price', true ) );
                    $line_total = $e_price * $qty;

                    $extras_summary .= $e_name . ' x ' . $qty . ', ';
                    
                    if ( stripos( $e_name, 'bébé' ) !== false || stripos( $e_name, 'bebe' ) !== false || stripos( $e_name, 'baby' ) !== false ) {
                        $baby_seat_count += $qty;
                    }

                    // Ajout formaté pour la section "Extra fees" de LimoExpress
                    if ( $line_total > 0 ) {
                        $driving_extra_fees[] = array(
                            'name'   => sprintf( '%s (x%d)', $e_name, $qty ),
                            'amount' => $line_total,
                            'price'  => $line_total
                        );
                    }
                }
            }
            $extras_summary = rtrim( $extras_summary, ', ' );
        }

        $vehicles_summary = '';
        if ( ! empty( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $v_id => $qty ) {
                if ( $qty > 0 ) $vehicles_summary .= get_the_title( $v_id ) . ' x ' . $qty . ', ';
            }
            $vehicles_summary = rtrim( $vehicles_summary, ', ' );
        }

        $pickup_address = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : 'Non spécifié';
        $dropoff_info   = ! empty( $data['dropoff_info'] ) ? $data['dropoff_info'] : $pickup_address;
        $total_passengers = intval( $data['adults'] ) + intval( $data['children'] );

        // Note pour le chauffeur (courte et opérationnelle)
        $note_for_driver = sprintf(
            "WP Booking #%d | Extras: %s | Notes client: %s",
            $booking_id,
            $extras_summary ?: 'Aucun',
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // NOUVEAU : Note pour le Dispatcher (Admin LimoExpress) claire et détaillée
        $dispatcher_note = sprintf(
            "--- DÉTAILS RÉSERVATION ---\nPassagers : %d Adulte(s), %d Enfant(s)\nVéhicules : %s\nCircuit : %s\nDemande spéciale : %s",
            $data['adults'] ?? 1,
            $data['children'] ?? 0,
            $vehicles_summary ?: 'Non spécifié',
            $prestation_label,
            ! empty( $data['note'] ) ? $data['note'] : 'Aucune'
        );

        // 5. Construction du Payload LimoExpress
        $payload = array(
            'booking_type_id'    => 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'booking_status_id'  => '7366f352-928e-43e9-8df0-217913b7177b',
            'vehicle_class_id'   => $vehicle_class_id,
            'pickup_time'        => $start_datetime . ':00',
            'start'              => $start_datetime,
            'end'                => $end_datetime,
            'duration'           => $duration_formatted,
            'from_location'      => array( 'name' => $pickup_address ),
            'to_location'        => array( 'name' => $dropoff_info ),
            'price'              => floatval( $data['pricing']['grand_total'] ?? 0 ),
            'price_type'         => 'NET',
            'passenger_count'    => $total_passengers,
            'suitcase_count'     => intval( $data['luggage'] ),
            'baby_seat_count'    => intval( $baby_seat_count ),
            'round_trip'         => ( ! empty( $data['option_id'] ) ) ? 1 : 0,
            'note'               => $dispatcher_note, // <-- Affiché dans "Additional info"
            'note_for_driver'    => substr( $note_for_driver, 0, 500 ),
            'waiting_board_text' => substr( $data['name'], 0, 50 ),
            'drivingExtraFees'   => $driving_extra_fees, // <-- Injecte les extras dans LimoExpress
            'passengers'         => array(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $data['email'],
                    'phone'      => ! empty( $data['phone'] ) ? $data['phone'] : ''
                )
            )
        );

        // 6. Requête HTTP PUT vers l'API
        $response = wp_remote_request( self::API_ENDPOINT, array(
            'method'    => 'PUT', // Méthode imposée par Swagger pour cet endpoint
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
            // Succès (201 Created ou 200 OK)
            $limo_id = $body['data']['number'] ?? $body['data']['internal_number'] ?? $body['data']['id'] ?? 'OK';
            update_post_meta( $booking_id, '_etb_limo_status', 'synced' );
            update_post_meta( $booking_id, '_etb_limo_booking_id', $limo_id );
            return true;
        } else {
            // Extraction des erreurs 422 Unprocessable Entity
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
}