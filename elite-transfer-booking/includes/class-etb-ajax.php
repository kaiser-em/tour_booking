<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Ajax {
    public function __construct() {
        add_action( 'wp_ajax_etb_submit_booking', array( $this, 'handle_submit_booking' ) );
        add_action( 'wp_ajax_nopriv_etb_submit_booking', array( $this, 'handle_submit_booking' ) );
        add_action( 'wp_ajax_etb_validate_promo', array( $this, 'handle_validate_promo' ) );
        add_action( 'wp_ajax_nopriv_etb_validate_promo', array( $this, 'handle_validate_promo' ) );

        add_action( 'wp_ajax_etb_resync_booking', array( $this, 'handle_resync_booking' ) );


        // Nouvelle route AJAX pour le calcul de prix en direct (Point A -> Point B)
        add_action( 'wp_ajax_etb_quick_pricing', array( $this, 'handle_quick_pricing' ) );
        add_action( 'wp_ajax_nopriv_etb_quick_pricing', array( $this, 'handle_quick_pricing' ) );

       // Route AJAX pour le Checkout Blacklane ([etb_checkout])
        add_action( 'wp_ajax_etb_submit_checkout', array( $this,'handle_submit_checkout' ) );
        add_action( 'wp_ajax_nopriv_etb_submit_checkout', array( $this, 'handle_submit_checkout' ) );

        /// Route AJAX pour générer un jeton de devis scellé (Quote Token Handoff)
        add_action( 'wp_ajax_etb_create_quote', array( $this, 'handle_create_quote' ) );
        add_action( 'wp_ajax_nopriv_etb_create_quote', array( $this, 'handle_create_quote' ) );

        // Route AJAX pour initialiser le paiement ou l'empreinte bancaire Stripe
        add_action( 'wp_ajax_etb_create_payment_intent', array( $this, 'handle_create_payment_intent' ) );
        add_action( 'wp_ajax_nopriv_etb_create_payment_intent', array( $this, 'handle_create_payment_intent' ) );

        // Route AJAX pour le règlement d'un devis validé ([etb_payment])
        add_action( 'wp_ajax_etb_settle_quote_payment', array( $this, 'handle_settle_quote_payment' ) );
        add_action( 'wp_ajax_nopriv_etb_settle_quote_payment', array( $this, 'handle_settle_quote_payment' ) );

        // Événement automatique de relance en arrière-plan (Panier abandonné +1h)
        add_action( 'etb_check_abandoned_payment', array( $this, 'handle_abandoned_payment_reminder' ), 10, 1 );
    }
    
    

    public function handle_validate_promo() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        // 1. Anti Brute-Force Rate Limiting (Max 15 échecs / 10 min)
        if ( ETB_Security::is_promo_bruteforce_blocked( 15 ) ) {
            wp_send_json_error( array( 'message' => 'Trop de tentatives de code promo. Veuillez patienter 10 minutes.' ) );
        }

        $raw_code   = sanitize_text_field( $_POST['promo_code'] ?? '' );
        $promo_code = strtoupper( trim( $raw_code ) );

        if ( empty( $promo_code ) ) {
            wp_send_json_error( array( 'message' => 'Veuillez saisir un code promo.' ) );
        }

        $query = new WP_Query( array(
            'post_type'      => 'tour_promo',
            'post_status'    => 'publish',
            'title'          => $promo_code,
            'posts_per_page' => 1,
        ) );

        if ( ! $query->have_posts() ) {
            $query = new WP_Query( array(
                'post_type'      => 'tour_promo',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array( 'key' => '_etb_promo_code', 'value' => $promo_code, 'compare' => '=' ),
                ),
            ) );
        }

        if ( $query->have_posts() ) {
            $query->the_post();
            $promo_id = get_the_ID();

            // 2. Vérification de l'état actif du code promo
            $is_active = get_post_meta( $promo_id, '_etb_promo_active', true );
            if ( '0' === $is_active ) {
                wp_reset_postdata();
                ETB_Security::record_failed_promo_attempt();
                wp_send_json_error( array( 'message' => 'Code promo invalide ou désactivé.' ) );
            }

            // 2bis. Contrôle des dates de validité
            $today      = current_time( 'Y-m-d' );
            $valid_from = get_post_meta( $promo_id, '_etb_promo_valid_from', true );
            $valid_to   = get_post_meta( $promo_id, '_etb_promo_valid_to', true );

            if ( ! empty( $valid_from ) && $today < $valid_from ) {
                wp_reset_postdata();
                ETB_Security::record_failed_promo_attempt();
                wp_send_json_error( array( 'message' => 'Ce code promo n\'est pas encore actif.' ) );
            }

            if ( ! empty( $valid_to ) && $today > $valid_to ) {
                wp_reset_postdata();
                ETB_Security::record_failed_promo_attempt();
                wp_send_json_error( array( 'message' => 'Ce code promo a expiré.' ) );
            }

            // 2ter. Contrôle du quota d'utilisations restantes
            $remaining = get_post_meta( $promo_id, '_etb_promo_remaining', true );
            if ( '' !== $remaining && is_numeric( $remaining ) && (int) $remaining <= 0 ) {
                wp_reset_postdata();
                ETB_Security::record_failed_promo_attempt();
                wp_send_json_error( array( 'message' => 'Ce code promo a atteint sa limite maximale d\'utilisations.' ) );
            }

            $discount_type = get_post_meta( $promo_id, '_etb_discount_type', true );

            if ( empty( $discount_type ) ) {
                $discount_type = get_post_meta( $promo_id, '_etb_promo_type', true ) ?: 'fixed';
            }

            $raw_value = get_post_meta( $promo_id, '_etb_discount_value', true );
            if ( '' === $raw_value || false === $raw_value ) {
                $raw_value = get_post_meta( $promo_id, '_etb_promo_value', true );
            }
            if ( '' === $raw_value || false === $raw_value ) {
                $raw_value = get_post_meta( $promo_id, '_etb_value', true );
            }
            $discount_value = floatval( $raw_value );

            wp_reset_postdata();

            wp_send_json_success( array(
                'code'           => $promo_code,
                'discount_type'  => $discount_type,
                'discount_value' => $discount_value,
                'message'        => 'Code promo appliqué avec succès !',
            ) );
        } else {
            wp_reset_postdata();
            ETB_Security::record_failed_promo_attempt();
            wp_send_json_error( array( 'message' => 'Code promo invalide ou expiré.' ) );
        }
    }

    public function handle_submit_booking() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        // 1. Contrôle Anti-Spam Honeypot
        if ( ! ETB_Security::verify_honeypot( 'etb_hp_email' ) ) {
            wp_send_json_error( array( 'message' => 'Validation de sécurité échouée.' ) );
        }

        // 2. Contrôle de vélocité (Minimum 3 secondes)
        $sec_time  = absint( $_POST['etb_sec_time'] ?? 0 );
        $sec_token = sanitize_text_field( $_POST['etb_sec_token'] ?? '' );
        if ( ! ETB_Security::verify_timestamp_token( $sec_time, $sec_token, 3, 86400 ) ) {
            wp_send_json_error( array( 'message' => 'Soumission trop rapide ou session expirée. Veuillez patienter quelques secondes et réessayer.' ) );
        }

        // 3. Rate Limiting Réservation (10 réservations / 10 minutes par IP)
        if ( ! ETB_Security::check_rate_limit( 'booking', 10, 600 ) ) {
            wp_send_json_error( array( 'message' => 'Trop de demandes de réservation effectuées récemment. Veuillez patienter quelques minutes.' ) );
        }

        // --- Réception et sanitization avec plafonnement ---
        $vehicles = array();
        if ( ! empty( $_POST['etb_car_qty'] ) && is_array( $_POST['etb_car_qty'] ) ) {
            foreach ( $_POST['etb_car_qty'] as $vehicle_id => $qty ) {
                $clean_qty = min( 50, max( 0, absint( $qty ) ) ); // Plafonné à 50
                if ( $clean_qty > 0 ) {
                    $vehicles[ absint( $vehicle_id ) ] = $clean_qty;
                }
            }
        }

        $extras = array();
        foreach ( $_POST as $key => $value ) {
            if ( strpos( $key, 'etb_extra_' ) === 0 ) {
                $extra_id = absint( str_replace( 'etb_extra_', '', $key ) );
                $extras[ $extra_id ] = min( 20, max( 0, absint( $value ) ) ); // Plafonné à 20
            }
        }

        $data = array(
            'vehicles'       => $vehicles,
            'adults'         => min( 200, absint( $_POST['etb_adults'] ?? 0 ) ),
            'children'       => min( 200, absint( $_POST['etb_children'] ?? 0 ) ),
            'pickup_id'      => absint( $_POST['etb_pickup_id'] ?? 0 ),
            'pickup_address' => sanitize_text_field( $_POST['etb_pickup_address'] ?? '' ),
            'dropoff_info'   => sanitize_textarea_field( $_POST['etb_dropoff_info'] ?? '' ),
            'option_id'      => sanitize_text_field( $_POST['etb_option_id'] ?? '' ),
            'circuit_id'     => absint( $_POST['etb_circuit_id'] ?? 0 ),
            'extras'         => $extras,
            'name'           => sanitize_text_field( $_POST['etb_name'] ?? '' ),
            'email'          => sanitize_email( $_POST['etb_email'] ?? '' ),
            'phone'          => sanitize_text_field( $_POST['etb_phone'] ?? '' ), // <-- AJOUT DE CETTE LIGNE phone 
            'date'           => sanitize_text_field( $_POST['etb_date'] ?? '' ),
            'time'           => sanitize_text_field( $_POST['etb_time'] ?? '' ),
            'luggage'        => min( 500, absint( $_POST['etb_total_luggage'] ?? 0 ) ),
            'promo'          => sanitize_text_field( $_POST['etb_promo'] ?? '' ),
            'note'           => sanitize_textarea_field( $_POST['etb_note'] ?? '' ),
        );

        // Validation métier
        if ( empty( $data['name'] ) ) {
            wp_send_json_error( array( 'message' => 'Le nom complet est obligatoire.' ) );
        }
        if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
            wp_send_json_error( array( 'message' => 'Une adresse email valide est obligatoire.' ) );
        }
        if ( empty( $data['date'] ) ) {
            wp_send_json_error( array( 'message' => 'La date est obligatoire.' ) );
        }
        if ( strtotime( $data['date'] ) < strtotime( current_time( 'Y-m-d' ) ) ) {
            wp_send_json_error( array( 'message' => 'La date de réservation ne peut pas être dans le passé.' ) );
        }
        if ( empty( $data['time'] ) ) {
            wp_send_json_error( array( 'message' => 'L\'heure de départ est obligatoire.' ) );
        }
        if ( empty( $data['pickup_address'] ) && empty( $data['pickup_id'] ) ) {
            wp_send_json_error( array( 'message' => 'L\'adresse de prise en charge est obligatoire.' ) );
        }
        if ( empty( $data['vehicles'] ) || array_sum( $data['vehicles'] ) === 0 ) {
            wp_send_json_error( array( 'message' => 'Veuillez sélectionner au moins un véhicule.' ) );
        }
        if ( $data['adults'] < 1 ) {
            wp_send_json_error( array( 'message' => 'Au moins 1 adulte est requis.' ) );
        }

        // Capacités réelles
        $total_capacity_pax     = 0;
        $total_capacity_baggage = 0;

        foreach ( $data['vehicles'] as $vehicle_id => $qty ) {
            if ( $qty <= 0 ) continue;
            if ( get_post_type( $vehicle_id ) !== 'tour_vehicle' || get_post_status( $vehicle_id ) !== 'publish' ) {
                wp_send_json_error( array( 'message' => 'Un véhicule sélectionné est invalide.' ) );
            }
            $total_capacity_pax     += absint( get_post_meta( $vehicle_id, '_etb_max_pax', true ) ) * $qty;
            $total_capacity_baggage += absint( get_post_meta( $vehicle_id, '_etb_max_baggage', true ) ) * $qty;
        }

        if ( ( $data['adults'] + $data['children'] ) > $total_capacity_pax ) {
            wp_send_json_error( array( 'message' => 'Le nombre de passagers dépasse la capacité des véhicules sélectionnés.' ) );
        }


        // Calcul tarifaire
        $pricing_engine  = new ETB_Pricing_Engine();
        $pricing_details = $pricing_engine->calculate_total( $data );
        $data['pricing'] = $pricing_details;

        // Création du CPT tour_booking
        $post_title = sprintf( 'Réservation #%s - %s', $data['name'], $data['date'] );
        $booking_id = wp_insert_post( array(
            'post_title'   => $post_title,
            'post_type'    => 'tour_booking',
            'post_status'  => 'pending',
        ) );

        if ( is_wp_error( $booking_id ) || ! $booking_id ) {
            wp_send_json_error( array( 'message' => 'Impossible de sauvegarder la réservation.' ) );
        }

        // Sauvegarde des métadonnées
        update_post_meta( $booking_id, '_etb_customer_name', $data['name'] );
        update_post_meta( $booking_id, '_etb_customer_email', $data['email'] );
        update_post_meta( $booking_id, '_etb_customer_phone', $data['phone'] ); // <-- AJOUT DE CETTE LIGNE
        update_post_meta( $booking_id, '_etb_booking_date', $data['date'] );
        update_post_meta( $booking_id, '_etb_booking_time', $data['time'] );
        update_post_meta( $booking_id, '_etb_pickup_id', $data['pickup_id'] );
        update_post_meta( $booking_id, '_etb_pickup_address', $data['pickup_address'] );
        update_post_meta( $booking_id, '_etb_dropoff_info', $data['dropoff_info'] );
        update_post_meta( $booking_id, '_etb_circuit_id', $data['circuit_id'] );
        update_post_meta( $booking_id, '_etb_circuit_option_id', $data['option_id'] );
        update_post_meta( $booking_id, '_etb_duration_hours', $pricing_details['duration_hours'] ?? 1 );
        update_post_meta( $booking_id, '_etb_adults', $data['adults'] );
        update_post_meta( $booking_id, '_etb_children', $data['children'] );
        update_post_meta( $booking_id, '_etb_luggage', $data['luggage'] );
        update_post_meta( $booking_id, '_etb_vehicles', $data['vehicles'] );
        update_post_meta( $booking_id, '_etb_extras', $data['extras'] );

        update_post_meta( $booking_id, '_etb_note', $data['note'] );
        update_post_meta( $booking_id, '_etb_total_price', $pricing_details['grand_total'] );
        update_post_meta( $booking_id, '_etb_pricing_details', $pricing_details );
        
        update_post_meta( $booking_id, '_etb_promo_code', $pricing_details['promo_code'] );
        update_post_meta( $booking_id, '_etb_discount_amount', $pricing_details['discount_amount'] );

        // Décrémentation automatique du quota d'utilisations restantes
        if ( ! empty( $pricing_details['promo_code'] ) && ! empty( $pricing_details['discount_amount'] ) && $pricing_details['discount_amount'] > 0 ) {
            $applied_code = strtoupper( trim( $pricing_details['promo_code'] ) );
            
            // Recherche du coupon utilisé
            $p_query = new WP_Query( array(
                'post_type'      => 'tour_promo',
                'post_status'    => 'publish',
                'title'          => $applied_code,
                'posts_per_page' => 1,
            ) );
            if ( ! $p_query->have_posts() ) {
                $p_query = new WP_Query( array(
                    'post_type'      => 'tour_promo',
                    'post_status'    => 'publish',
                    'meta_key'       => '_etb_promo_code',
                    'meta_value'     => $applied_code,
                    'posts_per_page' => 1,
                ) );
            }

            if ( $p_query->have_posts() ) {
                $used_promo_id = $p_query->posts[0]->ID;
                $rem = get_post_meta( $used_promo_id, '_etb_promo_remaining', true );
                if ( '' !== $rem && is_numeric( $rem ) && (int) $rem > 0 ) {
                    update_post_meta( $used_promo_id, '_etb_promo_remaining', max( 0, (int) $rem - 1 ) );
                }
            }
        }


        $data['booking_id'] = $booking_id;
        wp_send_json_success( $data );
    }

    /**
     * Traitement AJAX du bouton "Transférer vers LimoExpress"
     */
    public function handle_resync_booking() {
        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        check_ajax_referer( 'etb_resync_nonce_' . $booking_id, 'nonce' );

        if ( ! current_user_can( 'edit_post', $booking_id ) ) {
            wp_send_json_error( array( 'message' => 'Autorisation refusée.' ) );
        }

        // Reconstitution des données de la réservation depuis la base WordPress
        $pricing = get_post_meta( $booking_id, '_etb_pricing_details', true );
        if ( ! is_array( $pricing ) ) {
            $pricing = array(
                'grand_total'     => floatval( get_post_meta( $booking_id, '_etb_total_price', true ) ),
                'duration_hours'  => floatval( get_post_meta( $booking_id, '_etb_duration_hours', true ) ?: 1 ),
                'discount_amount' => floatval( get_post_meta( $booking_id, '_etb_discount_amount', true ) ?: 0 ),
                'promo_code'      => get_post_meta( $booking_id, '_etb_promo_code', true ) ?: '',
            );
        }

        $data = array(
            'vehicles'       => get_post_meta( $booking_id, '_etb_vehicles', true ) ?: array(),
            'adults'         => absint( get_post_meta( $booking_id, '_etb_adults', true ) ),
            'children'       => absint( get_post_meta( $booking_id, '_etb_children', true ) ),
            'pickup_id'      => absint( get_post_meta( $booking_id, '_etb_pickup_id', true ) ),
            'pickup_address' => get_post_meta( $booking_id, '_etb_pickup_address', true ) ?: '',
            'dropoff_info'   => get_post_meta( $booking_id, '_etb_dropoff_info', true ) ?: '',
            'option_id'      => get_post_meta( $booking_id, '_etb_circuit_option_id', true ) ?: '',
            'circuit_id'     => absint( get_post_meta( $booking_id, '_etb_circuit_id', true ) ),
            'extras'         => get_post_meta( $booking_id, '_etb_extras', true ) ?: array(),
            'name'           => get_post_meta( $booking_id, '_etb_customer_name', true ) ?: '',
            'email'          => get_post_meta( $booking_id, '_etb_customer_email', true ) ?: '',
            'phone'          => get_post_meta( $booking_id, '_etb_customer_phone', true ) ?: '',
            'date'           => get_post_meta( $booking_id, '_etb_booking_date', true ) ?: '',
            'time'           => get_post_meta( $booking_id, '_etb_booking_time', true ) ?: '',
            'luggage'        => absint( get_post_meta( $booking_id, '_etb_luggage', true ) ),
            'note'           => get_post_meta( $booking_id, '_etb_note', true ) ?: '',
            'pricing'        => $pricing,
        );

        if ( ! class_exists( 'ETB_Dispatcher_Manager' ) ) {
            wp_send_json_error( array( 'message' => 'Gestionnaire de dispatch introuvable.' ) );
        }

        $dispatch_result = ETB_Dispatcher_Manager::dispatch_booking( $booking_id, $data );

        if ( $dispatch_result['success'] ) {
            $limo_id      = get_post_meta( $booking_id, '_etb_limo_booking_id', true );
            $client_debug = get_post_meta( $booking_id, '_etb_limo_client_debug', true );
            wp_send_json_success( array( 
                'limo_id'      => $limo_id,
                'client_debug' => $client_debug,
            ) );
        } else {
            wp_send_json_error( array( 'message' => $dispatch_result['message'] ) );
        }
    }

    /**
     * Traitement AJAX : Calcul du prix de transfert via l'API LimoExpress
     */
    public function handle_quick_pricing() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        // Protection Anti-Abus : Max 30 estimations de prix par tranche de 10 minutes par IP
        if ( ! ETB_Security::check_rate_limit( 'quick_pricing', 30, 600 ) ) {
            wp_send_json_error( array( 'message' => 'Trop de demandes de calcul de tarif. Veuillez patienter quelques instants avant de réessayer.' ) );
        }
        
        $from_lat = floatval( $_POST['from_lat'] ?? 0 );
        $from_lng = floatval( $_POST['from_lng'] ?? 0 );
        $to_lat   = floatval( $_POST['to_lat'] ?? 0 );
        $to_lng   = floatval( $_POST['to_lng'] ?? 0 );

        if ( empty( $from_lat ) || empty( $from_lng ) || empty( $to_lat ) || empty( $to_lng ) ) {
            wp_send_json_error( array( 'message' => 'Coordonnées GPS incomplètes pour calculer le trajet.' ) );
        }

        $settings = get_option( 'etb_general_settings', array() );
        $token    = $settings['limo_api_token'] ?? '';
        
        if ( empty( $token ) ) {
            wp_send_json_error( array( 'message' => 'Jeton API LimoExpress non configuré.' ) );
        }

        // On interroge LimoExpress pour récupérer la matrice de prix A -> B
        $api_url = sprintf(
            'https://api.limoexpress.me/api/integration/pricing?from_latitude=%s&from_longitude=%s&to_latitude=%s&to_longitude=%s',
            $from_lat, $from_lng, $to_lat, $to_lng
        );

        $response = wp_remote_get( $api_url, array(
            'headers' => array(
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Erreur de connexion au serveur de tarification.' ) );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code >= 200 && $status_code < 300 && isset( $body['data'] ) ) {
            wp_send_json_success( array(
                'pricing_data' => $body['data']
            ) );
        } else {
            $error_msg = isset( $body['message'] ) ? $body['message'] : 'Impossible d\'obtenir un tarif pour cet itinéraire.';
            wp_send_json_error( array( 'message' => $error_msg ) );
        }
    }


    /**
     * Traitement AJAX : Soumission du Checkout Blacklane ([etb_checkout])
     */
    public function handle_submit_checkout() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        // Initialisation globale des réglages et de la devise
        $gen_settings    = get_option( 'etb_general_settings', array() );
        $currency_symbol = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';

        // 1. Contrôle Anti-Spam (Honeypot + Rate Limiting)
        if ( ! ETB_Security::verify_honeypot( 'etb_hp_email' ) ) {
            wp_send_json_error( array( 'message' => 'Security validation failed.' ) );
        }

        // 2. Récupération et assainissement des données du formulaire
        $mode            = sanitize_text_field( $_POST['etb_trip_mode'] ?? 'transfer' );
        $pickup          = sanitize_text_field( $_POST['etb_pickup_address'] ?? '' );
        $dropoff         = sanitize_text_field( $_POST['etb_dropoff_address'] ?? '' );
        $duration        = max( 1, floatval( $_POST['etb_duration'] ?? 4 ) );
        $date            = sanitize_text_field( $_POST['etb_date'] ?? '' );
        $time            = sanitize_text_field( $_POST['etb_time'] ?? '' );
        $vehicle_id      = absint( $_POST['etb_vehicle_id'] ?? 0 );
        $raw_price       = sanitize_text_field( $_POST['etb_calculated_price'] ?? '0' );
        $quote_ref       = sanitize_key( $_POST['etb_quote_ref'] ?? '' );

        $flight_number   = sanitize_text_field( $_POST['etb_flight_number'] ?? '' );

        $flight_number   = sanitize_text_field( $_POST['etb_flight_number'] ?? '' );
        $pickup_sign     = sanitize_text_field( $_POST['etb_pickup_sign'] ?? '' );
        $booker_type     = sanitize_text_field( $_POST['etb_booker_type'] ?? 'myself' );
        $first_name      = sanitize_text_field( $_POST['etb_first_name'] ?? '' );
        $last_name       = sanitize_text_field( $_POST['etb_last_name'] ?? '' );
        $email           = sanitize_email( $_POST['etb_email'] ?? '' );
        $phone           = sanitize_text_field( $_POST['etb_phone'] ?? '' );
        $booker_name        = sanitize_text_field( $_POST['etb_booker_name'] ?? '' );
        $booker_email       = sanitize_email( $_POST['etb_booker_email'] ?? '' );
        $baby_seat_count    = min( 10, absint( $_POST['etb_baby_seat_count'] ?? 0 ) );
        $booster_seat_count = min( 10, absint( $_POST['etb_booster_seat_count'] ?? 0 ) );
        $total_child_seats  = $baby_seat_count + $booster_seat_count;

        // Règle tarifaire officielle : Baby > 1 = 50€/u, Booster > 2 = 50€/u
        $paid_baby_seats    = max( 0, $baby_seat_count - 1 );
        $paid_booster_seats = max( 0, $booster_seat_count - 2 );
        $child_seat_fee     = (float) ( ( $paid_baby_seats + $paid_booster_seats ) * 50.0 );
        $notes              = sanitize_textarea_field( $_POST['etb_notes'] ?? '' );
        $cost_center        = sanitize_text_field( $_POST['etb_cost_center'] ?? '' );

        // Calcul d'urgence serveur infaillible (< min_delay_hours)
        $gen_settings    = get_option( 'etb_general_settings', array() );
        $min_delay_hours = ! empty( $gen_settings['min_delay'] ) ? absint( $gen_settings['min_delay'] ) : 24;
        
        $clean_time = trim( (string) $time );
        $pickup_ts  = strtotime( $date . ' ' . $clean_time );
        if ( ! $pickup_ts ) {
            $pickup_ts = strtotime( $date . ' ' . substr( $clean_time, 0, 5 ) . ':00' );
        }
        
        // Comparaison temporelle absolue (UTC vs UTC)
        $now_ts            = time();
        $is_urgent_by_time = ( $pickup_ts && ( $pickup_ts - $now_ts ) >= 0 && ( $pickup_ts - $now_ts ) < ( $min_delay_hours * HOUR_IN_SECONDS ) );
        
        // Prise en compte du flag envoyé par le formulaire validé par le navigateur
        $is_urgent_by_post = ( isset( $_POST['etb_is_urgent'] ) && '1' === (string) $_POST['etb_is_urgent'] );

        $is_urgent = ( $is_urgent_by_time || $is_urgent_by_post );

        // Nouveautés : Passagers, Bagages (Checked + Cabin) et Pourboire chauffeur
        $passengers_count   = max( 1, absint( $_POST['etb_passengers_count'] ?? 1 ) );
        $checked_luggage    = max( 0, absint( $_POST['etb_luggage_count'] ?? 0 ) );
        $cabin_bags         = max( 0, absint( $_POST['etb_cabin_bag_count'] ?? 0 ) );
        $total_luggage_sum  = $checked_luggage + $cabin_bags;
        $tip_percentage     = max( 0, absint( $_POST['etb_driver_tip'] ?? 0 ) );
        $tip_amount         = max( 0.0, floatval( $_POST['etb_tip_amount'] ?? 0.0 ) );

        $full_name = trim( $first_name . ' ' . $last_name );

        // 3. Validations obligatoires
        if ( empty( $full_name ) ) {
            wp_send_json_error( array( 'message' => 'Please enter the passenger first and last name.' ) );
        }
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'A valid email address is required.' ) );
        }
        if ( empty( $phone ) ) {
            wp_send_json_error( array( 'message' => 'A mobile phone number is required.' ) );
        }
        if ( empty( $pickup ) ) {
            wp_send_json_error( array( 'message' => 'Pickup location is missing.' ) );
        }
        if ( 'transfer' === $mode && empty( $dropoff ) ) {
            wp_send_json_error( array( 'message' => 'Drop-off location is missing.' ) );
        }
        if ( empty( $date ) || empty( $time ) ) {
            wp_send_json_error( array( 'message' => 'Date and pickup time are required.' ) );
        }
        if ( ! $vehicle_id || get_post_type( $vehicle_id ) !== 'tour_vehicle' ) {
            wp_send_json_error( array( 'message' => 'Invalid vehicle selected.' ) );
        }

        // 4. Détection blindée du mode devis (Custom Quote) et verrouillage serveur anti-fraude
        $is_quote_ride = false;
        $final_price   = 0.0;

        // Lecture prioritaire du devis scellé en mémoire serveur (Transient)
        $quote_data = ! empty( $quote_ref ) ? get_transient( 'etb_quote_' . $quote_ref ) : false;

        if ( is_array( $quote_data ) && isset( $quote_data['price'] ) ) {
            if ( 'Custom Quote' === $quote_data['price'] || floatval( $quote_data['price'] ) <= 0 ) {
                $is_quote_ride = true;
                $final_price   = 0.0;
            } else {
                $final_price = (float) round( floatval( $quote_data['price'] ), 2 );
            }
        } else {
            // Repli de sécurité si le devis a expiré (+30 min)
            $raw_price_clean = trim( (string) $raw_price );
            $is_quote_ride   = ( empty( $raw_price_clean ) 
                || '0' === $raw_price_clean 
                || floatval( $raw_price_clean ) <= 0 
                || false !== stripos( $raw_price_clean, 'quote' ) );

            if ( ! $is_quote_ride ) {
                $final_price = (float) round( floatval( $raw_price ), 2 );
            }
        }

        // Recalcul de sécurité infaillible pour le mode horaire (Mise à disposition)
        if ( 'hourly' === $mode && class_exists( 'ETB_Pricing_Engine' ) ) {
            $final_price = (float) round( ETB_Pricing_Engine::calculate_vehicle_price( $vehicle_id, $duration ), 2 );
        }

        // Ajout du supplément sièges enfants et du pourboire au total final (précision centimes)
        $base_fare_with_seats = $is_quote_ride ? 0.0 : ( $final_price + $child_seat_fee );
        $grand_total_with_tip = $is_quote_ride ? 0.0 : (float) round( $base_fare_with_seats + $tip_amount, 2 );


        $vehicle_title = get_the_title( $vehicle_id );
        $max_pax       = absint( get_post_meta( $vehicle_id, '_etb_max_pax', true ) ?: 1 );
        $max_bag       = absint( get_post_meta( $vehicle_id, '_etb_max_baggage', true ) ?: 0 );

        // 5. Création du dossier de réservation dans WordPress (tour_booking)
        $post_title = sprintf( 'Réservation #%s - %s', $full_name, $date );
        $booking_id = wp_insert_post( array(
            'post_title'  => $post_title,
            'post_type'   => 'tour_booking',
            'post_status' => 'pending',
        ) );

        if ( is_wp_error( $booking_id ) || ! $booking_id ) {
            wp_send_json_error( array( 'message' => 'Could not save reservation in database.' ) );
        }

        // Enregistrement des métadonnées
        update_post_meta( $booking_id, '_etb_customer_name', $full_name );
        update_post_meta( $booking_id, '_etb_customer_email', $email );
        update_post_meta( $booking_id, '_etb_customer_phone', $phone );
        update_post_meta( $booking_id, '_etb_booking_date', $date );
        update_post_meta( $booking_id, '_etb_booking_time', $time );
        update_post_meta( $booking_id, '_etb_pickup_address', $pickup );
        update_post_meta( $booking_id, '_etb_dropoff_info', ( 'hourly' === $mode ) ? sprintf( 'By the hour (%sh)', $duration ) : $dropoff );
        update_post_meta( $booking_id, '_etb_duration_hours', ( 'hourly' === $mode ) ? $duration : 1.0 );
        update_post_meta( $booking_id, '_etb_adults', $passengers_count );
        update_post_meta( $booking_id, '_etb_luggage', $total_luggage_sum ); // Total cumulé
        update_post_meta( $booking_id, '_etb_checked_luggage', $checked_luggage );
        update_post_meta( $booking_id, '_etb_cabin_bags', $cabin_bags );
        update_post_meta( $booking_id, '_etb_child_seat_fee', $child_seat_fee );
        update_post_meta( $booking_id, '_etb_vehicles', array( $vehicle_id => 1 ) );
        update_post_meta( $booking_id, '_etb_base_price', $final_price );
        update_post_meta( $booking_id, '_etb_tip_amount', $tip_amount );
        update_post_meta( $booking_id, '_etb_tip_percentage', $tip_percentage );
        update_post_meta( $booking_id, '_etb_total_price', $grand_total_with_tip );
        update_post_meta( $booking_id, '_etb_is_quote', $is_quote_ride ? '1' : '0' );
        update_post_meta( $booking_id, '_etb_is_urgent', $is_urgent ? '1' : '0' );

        // Mention du pourboire dans la note chauffeur LimoExpress
        $tip_driver_note = '';
        if ( $tip_amount > 0 ) {
            $tip_driver_note = sprintf( "\n💸 POURBOIRE CHAUFFEUR INCLUS : %s € (%d%%)", number_format_i18n( $tip_amount, 2 ), $tip_percentage );
        }


        // Métadonnées exclusives Blacklane (Enregistre la pancarte UNIQUEMENT si saisie)
        update_post_meta( $booking_id, '_etb_flight_number', $flight_number );
        update_post_meta( $booking_id, '_etb_waiting_board_text', $pickup_sign );
        update_post_meta( $booking_id, '_etb_booker_type', $booker_type );
        update_post_meta( $booking_id, '_etb_booker_name', $booker_name );
        update_post_meta( $booking_id, '_etb_booker_email', $booker_email );
        update_post_meta( $booking_id, '_etb_baby_seat_count', $baby_seat_count );
        update_post_meta( $booking_id, '_etb_booster_seat_count', $booster_seat_count );
        update_post_meta( $booking_id, '_etb_total_child_seats', $total_child_seats );
        update_post_meta( $booking_id, '_etb_cost_center', $cost_center );

        // Mention détaillée des sièges pour le chauffeur et le dispatch (2-10 ans)
        $seats_detail_note = '';
        if ( $baby_seat_count > 0 || $booster_seat_count > 0 ) {
            $seats_list = array();
            if ( $baby_seat_count > 0 ) {
                $seats_list[] = sprintf( '%d Siège(s) Bébé (0-2 ans)', $baby_seat_count );
            }
            if ( $booster_seat_count > 0 ) {
                $seats_list[] = sprintf( '%d Rehausseur(s) / Booster (2-10 ans)', $booster_seat_count );
            }
            $seats_detail_note = "\n👶 SIÈGES ENFANTS REQUIS : " . implode( ' + ', $seats_list );
        }

        update_post_meta( $booking_id, '_etb_note', $notes . $tip_driver_note . $seats_detail_note );

        // Construction des logs de paiement conformes au Swagger LimoExpress avec lien Stripe
        $payment_logs = array();
        $is_paid_status = false;

        $card_last4 = sanitize_text_field( $_POST['etb_card_last4'] ?? '' );
        $card_brand = sanitize_text_field( $_POST['etb_card_brand'] ?? 'card' );
        $card_exp   = sanitize_text_field( $_POST['etb_card_exp'] ?? '' );
        $intent_id  = sanitize_text_field( $_POST['etb_payment_intent_id'] ?? '' );

        // Construction du lien direct vers votre transaction Stripe
        $stripe_mode = ( isset( $gen_settings['stripe_mode'] ) && 'live' === $gen_settings['stripe_mode'] ) ? 'live' : 'test';
        $stripe_url  = ! empty( $intent_id )
            ? ( 'live' === $stripe_mode 
                ? 'https://dashboard.stripe.com/payments/' . $intent_id 
                : 'https://dashboard.stripe.com/test/payments/' . $intent_id )
            : '';

        if ( ! empty( $card_last4 ) || ! empty( $intent_id ) ) {
            $payment_logs[] = array(
                'amount'         => (float) round( $grand_total_with_tip, 2 ),
                'method'         => 'card',
                'last_4_digits'  => substr( $card_last4 ?: '4242', -4 ),
                'brand'          => strtolower( $card_brand ),
                'expire_date'    => $card_exp,
                'receipt_number' => $intent_id,
                'remark'         => $stripe_url,
                'paid_at'        => wp_date( 'Y-m-d H:i:s' ),
            );
            $is_paid_status = true;

            update_post_meta( $booking_id, '_etb_stripe_payment_intent_id', $intent_id );
        }

        // Injection du lien Stripe direct dans la note répartiteur LimoExpress
        $stripe_note_info = ! empty( $stripe_url ) ? "\n💳 PAIEMENT STRIPE : " . $stripe_url . "\n" : "";

        // 6. Préparation des données et génération systématique du jeton de paiement scellé
        $pay_token = 'q_pay_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 );
        $pay_transient_data = array(
            'booking_id'   => $booking_id,
            'client_name'  => $full_name,
            'client_email' => $email,
            'client_phone' => $phone,
            'pickup'       => $pickup,
            'dropoff'      => ( 'hourly' === $mode ) ? sprintf( 'By the hour (%sh)', $duration ) : $dropoff,
            'date'         => $date,
            'time'         => $time,
            'amount'       => $is_quote_ride ? 0.0 : floatval( $grand_total_with_tip ),
        );
        set_transient( 'etb_pay_' . $pay_token, $pay_transient_data, 14 * DAY_IN_SECONDS );
        update_post_meta( $booking_id, '_etb_pay_token', $pay_token );

        $gen_settings     = get_option( 'etb_general_settings', array() );
        $payment_base_url = ! empty( $gen_settings['payment_page_url'] ) ? esc_url_raw( $gen_settings['payment_page_url'] ) : home_url( '/payment/' );
        $payment_link_url = add_query_arg( 'ref', $pay_token, $payment_base_url );

        // Détection de l'option Pay Later
        $is_pay_later = ! empty( $_POST['etb_pay_later'] );

        // Si le client va payer en ligne, on programme un contrôle automatique à +1h en cas d'abandon
        if ( ! $is_pay_later && ! $is_quote_ride && ! $is_urgent ) {
            wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'etb_check_abandoned_payment', array( $booking_id ) );
        }

        $quote_alert_note = '';
        if ( $is_urgent && ! $is_quote_ride ) {
            $formatted_total_due = number_format_i18n( $grand_total_with_tip, 2 ) . ' ' . $currency_symbol;
            $quote_alert_note = "\n═🚨 COURSE URGENTE (< 24H) — VÉRIFICATION DISPONIBILITÉ═\n"
                . "Prise en charge prévue le : " . $date . " à " . $time . "\n"
                . "Montant fixé : " . $formatted_total_due . "\n"
                . "Action : Valider la disponibilité chauffeur avant d'encaisser.\n"
                . "🔗 Lien de paiement prêt à transmettre dès validation :\n"
                . "👉 " . $payment_link_url . "\n"
                . "════════════\n\n";
        } elseif ( $is_quote_ride ) {
            $quote_alert_note = "\n═ 🚨 DEVIS SUR MESURE REÇU DEPUIS LE SITE ═\n"
                . "1. Fixez votre tarif dans cette fiche LimoExpress (ex: 2 400 €).\n"
                . "2. Transmettez ce lien unique au client (WhatsApp ou E-mail) :\n"
                . "👉 " . $payment_link_url . "\n"
                . "(Le lien s'activera automatiquement dès que vous aurez saisi le prix ci-dessus !)\n"
                . "════════════\n\n";
        } elseif ( $is_pay_later || ! $is_paid_status ) {
            $formatted_total_due = number_format_i18n( $grand_total_with_tip, 2 ) . ' ' . $currency_symbol;
            $tip_info_text       = ( $tip_amount > 0 ) ? ' (incluant ' . number_format_i18n( $tip_amount, 2 ) . ' ' . $currency_symbol . ' de pourboire)' : '';

            $quote_alert_note = "\n═⏳ RÈGLEMENT EN ATTENTE ═\n"
                . "Statut : en attente de paiement en ligne.\n"
                . "Montant à régler : " . $formatted_total_due . $tip_info_text . "\n"
                . "🔗 LIEN DE PAIEMENT SÉCURISÉ PRÊT À TRANSMETTRE :\n"
                . "👉 " . $payment_link_url . "\n"
                . "══════════\n\n";
        }


        // Construction de la note chauffeur opérationnelle pure (SANS lien de paiement ni consigne financière)
        $clean_driver_note = $notes; // Contient uniquement les remarques du voyageur et la mention du pourboire
        // On isole les alertes financières pour le répartiteur, et on garde la note brute pour le chauffeur
        $dispatcher_alert  = $quote_alert_note . $tip_driver_note;
        $clean_driver_note = trim( $notes );

        $extra_fees_checkout = array();
        if ( ! $is_quote_ride && $child_seat_fee > 0 ) {
            $extra_fees_checkout[] = array(
                'category' => 'child_seat_fee',
                'amount'   => (float) round( $child_seat_fee, 2 ),
                'value'    => (float) round( $child_seat_fee, 2 ),
                'active'   => true,
            );
        }
        if ( ! $is_quote_ride && $tip_amount > 0 ) {
            $extra_fees_checkout[] = array(
                'category' => 'gratuity_amount',
                'amount'   => (float) round( $tip_amount, 2 ),
                'value'    => (float) round( $tip_amount, 2 ),
                'active'   => true,
            );
        }

        $dispatch_data = array(
            'name'               => $full_name,
            'email'              => $email,
            'phone'              => $phone,
            'date'               => $date,
            'time'               => $time,
            'pickup_address'     => $pickup,
            'dropoff_info'       => ( 'hourly' === $mode ) ? sprintf( 'By the hour (%sh - As Directed)', $duration ) : $dropoff,
            'trip_mode'          => $mode,
            'vehicles'           => array( $vehicle_id => 1 ),
            'adults'             => $passengers_count,
            'children'           => 0,
            'luggage'            => $total_luggage_sum, // Total envoyé au champ suitcase_count de LimoExpress
            'checked_luggage'    => $checked_luggage,
            'cabin_bags'         => $cabin_bags,
            'baby_seat_count'    => $baby_seat_count,
            'booster_seat_count' => $booster_seat_count,
            'total_child_seats'  => $total_child_seats,
            'tip_amount'         => $is_quote_ride ? 0.0 : $tip_amount,
            'extra_fees'         => $extra_fees_checkout,
            'paid'               => $is_quote_ride ? false : $is_paid_status,
            'payment_logs'       => $is_quote_ride ? array() : $payment_logs,
            'payment_intent_id'  => $intent_id,
            'is_quote'           => $is_quote_ride,
            'client_note'        => $clean_driver_note, // La note brute du client
            'dispatcher_alert'   => $dispatcher_alert,  // Les infos de paiement
            'note'               => $dispatcher_alert . "\n" . $clean_driver_note,
            'note_for_driver'    => $clean_driver_note, // N'envoie QUE la note client au chauffeur
            'flight_number'      => $flight_number,
            'waiting_board_text' => $pickup_sign, // PAS de forçage si le champ est vide
            'cost_center'        => $cost_center,
            'pricing'            => array(
                'grand_total'    => $is_quote_ride ? 0.0 : $grand_total_with_tip,
                'base_fare'      => $is_quote_ride ? 0.0 : $final_price,
                'duration_hours' => ( 'hourly' === $mode ) ? $duration : 1.0,
            ),
        );

        if ( ! class_exists( 'ETB_Dispatcher_Manager' ) ) {
            require_once ETB_PATH . 'includes/class-etb-dispatcher-manager.php';
        }

        $dispatch_result = ETB_Dispatcher_Manager::dispatch_booking( $booking_id, $dispatch_data );

        if ( $dispatch_result['success'] ) {
            update_post_meta( $booking_id, '_etb_limo_status', 'synced' );
        } else {
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', $dispatch_result['message'] );
        }

        // 7. Envoi des e-mails (Client + Administrateur)
        $gen_settings    = get_option( 'etb_general_settings', array() );
        $currency_symbol = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';
        
        // Récupération sécurisée de l'e-mail administrateur
        $admin_email = ! empty( $gen_settings['admin_email'] ) && is_email( $gen_settings['admin_email'] ) 
            ? sanitize_email( $gen_settings['admin_email'] ) 
            : get_option( 'admin_email' );
            
        $company_name = get_bloginfo( 'name' );

        // Nettoyage sécurisé du nom pour les sujets d'e-mails
        $clean_name = preg_replace( '/[^\p{L}\p{N}\s\-\.]/u', '', $full_name );
        $clean_name = trim( preg_replace( '/\s+/', ' ', $clean_name ) );

        // En-têtes officiels stricts pour tous les envois (Client et Admin)
        $headers_client = ETB_Settings::get_mail_headers( $admin_email, $company_name );
        $headers_admin  = ETB_Settings::get_mail_headers( $email, $full_name );

        // Formatage de la date en "03 Oct 2026"
        $date_ts       = strtotime( $date );
        $formatted_dt  = $date_ts ? date( 'd M Y', $date_ts ) : $date;
        $dropoff_label = ( 'hourly' === $mode ) ? sprintf( 'By the hour (%sh)', $duration ) : $dropoff;
        $total_display = number_format_i18n( $grand_total_with_tip, 2 ) . ' ' . $currency_symbol;

        if ( ! empty( $is_urgent ) && ! $is_quote_ride ) {
            // ─────────────────────────────────────────────────────────────
            // EMAIL 1: URGENT BOOKING (< 24H) - AVAILABILITY CHECK
            // ─────────────────────────────────────────────────────────────
            $subject_client = 'Urgent Booking Request Received #' . $booking_id . ' — ' . $company_name;
            $message_client = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">'
                . '<div style="background: #0f172a; padding: 25px 30px;">'
                . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">' . esc_html( $company_name ) . '</h1>'
                . '<p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em;">VIP Urgent Reservation #' . $booking_id . '</p>'
                . '</div>'
                . '<div style="padding: 30px;">'
                . '<p style="font-size: 15px; margin-top: 0; margin-bottom: 16px;">Dear <strong>' . esc_html( $full_name ) . '</strong>,</p>'
                . '<p style="font-size: 14px; margin-bottom: 20px;">We have received your urgent transfer request for pickup within the next 24 hours.</p>'
                . '<div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 6px; margin-bottom: 22px;">'
                . '<strong style="color: #92400e; font-size: 13px; display: block; margin-bottom: 3px;">⏱️ Chauffeur Availability Check</strong>'
                . '<p style="margin: 0; font-size: 13px; color: #78350f;">Our dispatch team is immediately verifying chauffeur allocation for your pickup time (<strong>' . esc_html( $formatted_dt ) . ' at ' . esc_html( $time ) . '</strong>). You will receive final confirmation and your payment link within <strong>1 hour</strong>.</p>'
                . '</div>'
                . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Vehicle:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $vehicle_title ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Pickup:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Drop-off:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $dropoff_label ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $formatted_dt ) . ' at ' . esc_html( $time ) . '</td></tr>'
                . ( $flight_number ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Flight:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $flight_number ) . '</td></tr>' : '' )
                . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Total Amount:</td><td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 800; color: #e65a15;">' . $total_display . '</td></tr>'
                . '</table>'
                . '<p style="font-size: 12px; color: #64748b; margin: 0;">No charge will be made until chauffeur availability is formally secured.</p>'
                . '</div>'
                . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
                . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — VIP Chauffeur & Private Transfers</p>'
                . '</div>'
                . '</div>'
                . '</div>';

            // Preparation of pre-filled WhatsApp message for Admin
            $first_name_only = explode( ' ', trim( $full_name ) )[0];
            $wa_phone_clean  = preg_replace( '/[^0-9]/', '', $phone );
            $wa_message_raw  = "Hey " . $first_name_only . " 🙋‍♀️,\n\n"
                . "Great news! We are pleased to confirm that your chauffeur is available for your booking #" . $booking_id . " with " . $company_name . ".\n\n"
                . "📋 *Ride Details:*\n"
                . "• *Date & Time:* " . $formatted_dt . " at " . $time . "\n"
                . "• *Vehicle:* " . $vehicle_title . "\n"
                . "• *Pickup:* " . $pickup . "\n"
                . "• *Drop-off:* " . $dropoff_label . "\n"
                . "• *Total Fare:* " . $total_display . "\n\n"
                . "To finalize and secure your reservation, please complete your payment using our secure link below:\n"
                . "👉 " . $payment_link_url . "\n\n"
                . "Thank you, and we look forward to driving you!";

            $wa_link = ! empty( $wa_phone_clean )
                ? 'https://api.whatsapp.com/send?phone=' . $wa_phone_clean . '&text=' . rawurlencode( $wa_message_raw )
                : '';

            $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
            $subject_admin  = '🚨 [URGENT < 24H] Short-Notice Ride to Confirm #' . $booking_id . ' - ' . $clean_name;

            $message_admin = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
                . '<div style="background: #991b1b; padding: 22px 28px; text-align: left;">'
                . '<span style="background: rgba(255,255,255,0.2); color: #ffffff; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.08em; display: inline-block; margin-bottom: 6px;">Action Required</span>'
                . '<h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 800;">🚨 Urgent Ride Scheduled in &lt; 24h</h1>'
                . '<p style="margin: 4px 0 0 0; color: #fecaca; font-size: 12px;">Dossier #' . $booking_id . ' — Short-notice pickup verification</p>'
                . '</div>'
                . '<div style="padding: 28px;">'
                . '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 22px;">'
                . '<strong style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">👤 Passenger Contact</strong>'
                . '<p style="margin: 2px 0; font-size: 15px; font-weight: 700; color: #0f172a;">' . esc_html( $full_name ) . '</p>'
                . '<p style="margin: 2px 0; font-size: 13px; color: #475569;">✉️ <a href="mailto:' . esc_attr( $email ) . '" style="color: #0284c7; text-decoration: none;">' . esc_html( $email ) . '</a></p>'
                . '<p style="margin: 2px 0; font-size: 13px; color: #475569;">📞 <a href="tel:' . esc_attr( $phone ) . '" style="color: #0284c7; text-decoration: none;">' . esc_html( $phone ) . '</a></p>'
                . ( ! empty( $wa_link ) ? '<div style="margin-top: 10px;"><a href="' . esc_attr( $wa_link ) . '" target="_blank" style="background: #22c55e; color: #ffffff; padding: 7px 16px; border-radius: 50px; text-decoration: none; font-size: 12px; font-weight: 700; display: inline-block;">💬 Contact on WhatsApp</a></div>' : '' )
                . '</div>'
                . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Pickup Location:</td><td style="padding: 9px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Drop-off:</td><td style="padding: 9px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $dropoff_label ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Date & Time:</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #991b1b;">' . esc_html( $formatted_dt ) . ' at ' . esc_html( $time ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Vehicle:</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $vehicle_title ) . '</td></tr>'
                . ( $flight_number ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Flight:</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $flight_number ) . '</td></tr>' : '' )
                . ( $pickup_sign ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Greeting Sign:</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $pickup_sign ) . '</td></tr>' : '' )
                . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Total Fare:</td><td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 800; color: #ea580c;">' . $total_display . '</td></tr>'
                . '</table>'
                . '<div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 16px; margin-bottom: 22px;">'
                . '<strong style="font-size: 13px; color: #92400e; display: block; margin-bottom: 6px;">👉 Dispatch Procedure:</strong>'
                . '<p style="margin: 0 0 10px 0; font-size: 12.5px; color: #78350f;">Check chauffeur availability in LimoExpress. Once assigned, send this secure settlement link to the passenger:</p>'
                . '<a href="' . esc_url( $payment_link_url ) . '" style="background: #fbac18; color: #0f172a; padding: 10px 20px; border-radius: 50px; text-decoration: none; font-size: 13px; font-weight: 800; display: inline-block;">🔗 Open Payment Link ➔</a>'
                . '</div>'
                . '<div style="text-align: center;">'
                . '<a href="' . esc_url( $admin_edit_url ) . '" style="color: #64748b; font-size: 12px; text-decoration: underline; display:none">View Dossier #' . $booking_id . ' in WordPress</a>'
                . '</div>'
                . '</div>'
                . '</div>'
                . '</div>';

            wp_mail( $email, $subject_client, $message_client, $headers_client );
            wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );

        } elseif ( $is_quote_ride ) {
            // ─────────────────────────────────────────────────────────────
            // EMAIL 2: CUSTOM QUOTE REQUEST (TAILORED ROUTE)
            // ─────────────────────────────────────────────────────────────
            $subject_client = 'Custom Quote Request Received #' . $booking_id . ' — ' . $company_name;
            $message_client = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">'
                . '<div style="background: #0f172a; padding: 25px 30px;">'
                . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">' . esc_html( $company_name ) . '</h1>'
                . '<p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em;">VIP Custom Quote Request #' . $booking_id . '</p>'
                . '</div>'
                . '<div style="padding: 30px;">'
                . '<p style="font-size: 15px; margin-top: 0; margin-bottom: 16px;">Dear <strong>' . esc_html( $full_name ) . '</strong>,</p>'
                . '<p style="font-size: 14px; margin-bottom: 20px;">Thank you for your inquiry. Our dispatch regulation is currently calculating your tailored quotation (itinerary, tolls, and chauffeur schedule).</p>'
                . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Vehicle:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $vehicle_title ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Pickup:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Drop-off:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $dropoff_label ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $formatted_dt ) . ' at ' . esc_html( $time ) . '</td></tr>'
                . ( $flight_number ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Flight:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $flight_number ) . '</td></tr>' : '' )
                . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Estimated Rate:</td><td style="padding: 12px 0; text-align: right; font-size: 16px; font-weight: 800; color: #d97706;">Custom Quote (Being calculated)</td></tr>'
                . '</table>'
                . '<div style="background: #f8fafc; border-left: 4px solid #fbac18; padding: 14px 16px; border-radius: 6px; margin-bottom: 20px;">'
                . '<p style="margin: 0; font-size: 13px; color: #334155;">Our team will transmit your final quotation with a direct settlement link within <strong>1 hour</strong>.</p>'
                . '</div>'
                . '</div>'
                . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
                . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — VIP Chauffeur & Private Transfers</p>'
                . '</div>'
                . '</div>'
                . '</div>';

            // Admin Email for Custom Quote (English)
            $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
            $subject_admin  = '📋 [NEW QUOTE REQUEST] Dossier #' . $booking_id . ' - ' . $clean_name;
            $message_admin  = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
                . '<div style="background: #9a3412; padding: 22px 28px;">'
                . '<h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 800;">📋 New Custom Quote Request</h1>'
                . '<p style="margin: 4px 0 0 0; color: #fed7aa; font-size: 12px;">Dossier #' . $booking_id . ' — Transmitted to dispatch</p>'
                . '</div>'
                . '<div style="padding: 28px;">'
                . '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">'
                . '<p style="margin: 2px 0;"><strong>Passenger:</strong> ' . esc_html( $full_name ) . ' (<a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a> | ' . esc_html( $email ) . ')</p>'
                . '<p style="margin: 2px 0;"><strong>Vehicle:</strong> ' . esc_html( $vehicle_title ) . '</p>'
                . '<p style="margin: 2px 0;"><strong>Route:</strong> ' . esc_html( $pickup ) . ' ➔ ' . esc_html( $dropoff_label ) . '</p>'
                . '<p style="margin: 2px 0;"><strong>Date & Time:</strong> ' . esc_html( $formatted_dt ) . ' at ' . esc_html( $time ) . '</p>'
                . '</div>'
                . '<div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 14px; margin-bottom: 20px; font-size: 12.5px; color: #78350f;">'
                . '<strong>👉 Action:</strong> Price the route in LimoExpress and send the payment link to the client.'
                . '</div>'
                . '<div style="text-align: center;">'
                . '<a href="' . esc_url( $admin_edit_url ) . '" style="background: #0f172a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 50px; font-weight: 700; font-size: 13px; display: none">View Dossier in WordPress</a>'
                . '</div>'
                . '</div>'
                . '</div>'
                . '</div>';

            wp_mail( $email, $subject_client, $message_client, $headers_client );
            wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );

        } else {
            // ─────────────────────────────────────────────────────────────
            // EMAIL 3: PAY LATER OPTION (PENDING PAYMENT)
            // ─────────────────────────────────────────────────────────────
            $is_pay_later = ! empty( $_POST['etb_pay_later'] );

            if ( $is_pay_later ) {
                $subject_client = 'Complete Your VIP Reservation #' . $booking_id . ' — ' . $company_name;
                $message_client = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                    . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">'
                    . '<div style="background: #0f172a; padding: 25px 30px;">'
                    . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">' . esc_html( $company_name ) . '</h1>'
                    . '<p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em;">Superior Drive — Order #' . $booking_id . ' (Payment Pending)</p>'
                    . '</div>'
                    . '<div style="padding: 30px;">'
                    . '<p style="font-size: 15px; margin-top: 0; margin-bottom: 16px;">Dear <strong>' . esc_html( $full_name ) . '</strong>,</p>'
                    . '<p style="font-size: 14px; margin-bottom: 20px;">Thank you for booking with us. Your transfer dossier has been registered and is currently awaiting online settlement.</p>'
                    . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
                    . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Vehicle:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $vehicle_title ) . '</td></tr>'
                    . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Pickup:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup ) . '</td></tr>'
                    . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Drop-off:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $dropoff_label ) . '</td></tr>'
                    . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $formatted_dt ) . ' at ' . esc_html( $time ) . '</td></tr>'
                    . ( $flight_number ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Flight:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $flight_number ) . '</td></tr>' : '' )
                    . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Total to Pay:</td><td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 800; color: #e65a15;">' . $total_display . '</td></tr>'
                    . '</table>'
                    . '<div style="text-align: center; margin: 26px 0 16px 0;">'
                    . '<a href="' . esc_url( $payment_link_url ) . '" style="background: #fbac18; color: #0f172a; padding: 14px 32px; text-decoration: none; border-radius: 50px; font-weight: 800; font-size: 14px; display: inline-block;">Proceed to Payment to Confirm ➔</a>'
                    . '</div>'
                    . '<p style="font-size: 12px; color: #64748b; text-align: center; margin-bottom: 0;">You can complete your payment online anytime prior to pickup.</p>'
                    . '</div>'
                    . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
                    . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — VIP Chauffeur & Private Transfers</p>'
                    . '</div>'
                    . '</div>'
                    . '</div>';

                // Admin Email for Pay Later (English)
                $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
                $subject_admin  = '⏳ [PAY LATER] New Reservation Pending Payment #' . $booking_id . ' - ' . $clean_name;
                $message_admin  = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                    . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
                    . '<div style="background: #0f172a; padding: 22px 28px;">'
                    . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800;">⏳ Pay Later Reservation Registered</h1>'
                    . '<p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 12px;">Dossier #' . $booking_id . ' — Transmitted to dispatch</p>'
                    . '</div>'
                    . '<div style="padding: 28px;">'
                    . '<p style="margin: 4px 0;"><strong>Passenger:</strong> ' . esc_html( $full_name ) . ' (<a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a> | ' . esc_html( $email ) . ')</p>'
                    . '<p style="margin: 4px 0;"><strong>Vehicle:</strong> ' . esc_html( $vehicle_title ) . '</p>'
                    . '<p style="margin: 4px 0;"><strong>Route:</strong> ' . esc_html( $pickup ) . ' ➔ ' . esc_html( $dropoff_label ) . '</p>'
                    . '<p style="margin: 4px 0;"><strong>Date & Time:</strong> ' . esc_html( $formatted_dt ) . ' at ' . esc_html( $time ) . '</p>'
                    . '<p style="margin: 4px 0;"><strong>Amount Due:</strong> ' . $total_display . '</p>'
                    . '<div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px 14px; margin: 16px 0;">'
                    . '<p style="margin: 0; font-size: 12px; color: #92400e;">Payment link sent to client. You may also forward it manually:</p>'
                    . '<a href="' . esc_url( $payment_link_url ) . '" style="color: #ea580c; font-weight: bold; word-break: break-all; font-size: 12px;">' . esc_html( $payment_link_url ) . '</a>'
                    . '</div>'
                    . '<div style="margin-top: 15px; text-align: center;">'
                    . '<a href="' . esc_url( $admin_edit_url ) . '" style="background: #0f172a; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 50px; font-weight: 700; font-size: 13px; display:none;">View in WordPress</a>'
                    . '</div>'
                    . '</div>'
                    . '</div>'
                    . '</div>';

                wp_mail( $email, $subject_client, $message_client, $headers_client );
                wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );
            }
        }

        // 8. Réponse JSON de succès avec URL de paiement pour redirection
        $limo_id = get_post_meta( $booking_id, '_etb_limo_booking_id', true );
        wp_send_json_success( array(
            'booking_id'   => $booking_id,
            'limo_id'      => $limo_id ?: 'SYNCED',
            'is_quote'     => $is_quote_ride,
            'payment_url'  => $payment_link_url,
            'message'      => $is_quote_ride ? 'Quote request registered.' : 'Reservation created, redirecting to payment...',
        ) );
    }

    /**
     * Traitement AJAX : Génération d'un devis scellé serveur avec jeton temporaire (Quote Token)
     */
    public function handle_create_quote() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        // 1. Rate Limiting Anti-Abus (Max 20 devis / 10 minutes par IP)
        if ( ! ETB_Security::check_rate_limit( 'create_quote', 20, 600 ) ) {
            wp_send_json_error( array( 'message' => 'Too many requests. Please wait a moment.' ) );
        }

        // 2. Récupération et assainissement des critères de course (avec dé-échappement des apostrophes)
        $mode       = sanitize_text_field( wp_unslash( $_POST['mode'] ?? 'transfer' ) );
        $pickup     = sanitize_text_field( wp_unslash( $_POST['pickup'] ?? '' ) );
        $dropoff    = sanitize_text_field( wp_unslash( $_POST['dropoff'] ?? '' ) );
        $duration   = max( 1, floatval( $_POST['duration'] ?? 4 ) );
        $date       = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
        $time       = sanitize_text_field( wp_unslash( $_POST['time'] ?? '' ) );
        $vehicle_id = absint( $_POST['vehicle_id'] ?? 0 );
        $raw_price  = sanitize_text_field( wp_unslash( $_POST['price'] ?? '0' ) );

        if ( empty( $pickup ) ) {
            wp_send_json_error( array( 'message' => 'Pickup location is required.' ) );
        }
        
        
        if ( 'transfer' === $mode && empty( $dropoff ) ) {
            wp_send_json_error( array( 'message' => 'Drop-off location is required.' ) );
        }
        if ( empty( $date ) || empty( $time ) ) {
            wp_send_json_error( array( 'message' => 'Date and time are required.' ) );
        }
        if ( ! $vehicle_id || get_post_type( $vehicle_id ) !== 'tour_vehicle' ) {
            wp_send_json_error( array( 'message' => 'Invalid vehicle selected.' ) );
        }

        // 3. Calcul / Verrouillage du prix côté serveur
        $is_quote_ride = ( 'Custom Quote' === $raw_price || floatval( $raw_price ) <= 0 );
        $locked_price  = 0.0;

        if ( ! $is_quote_ride ) {
            if ( 'hourly' === $mode && class_exists( 'ETB_Pricing_Engine' ) ) {
                $locked_price = ETB_Pricing_Engine::calculate_vehicle_price( $vehicle_id, $duration );
            } else {
                $locked_price = floatval( $raw_price );
            }
        }

        // Récupération des informations officielles du véhicule
        $vehicle_name = get_the_title( $vehicle_id );
        $vehicle_img  = get_the_post_thumbnail_url( $vehicle_id, 'full' ) ?: '';
        $max_pax      = absint( get_post_meta( $vehicle_id, '_etb_max_pax', true ) ?: 1 );
        $max_bag      = absint( get_post_meta( $vehicle_id, '_etb_max_baggage', true ) ?: 0 );

        // 4. Génération d'un jeton aléatoire unique cryptographique (ex: q_8f94c2d1)
        $token = 'q_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 );

        // 5. Enregistrement du devis dans la mémoire sécurisée WordPress (Transient 30 minutes)
        $quote_data = array(
            'ref'          => $token,
            'mode'         => $mode,
            'pickup'       => $pickup,
            'dropoff'      => $dropoff,
            'duration'     => $duration,
            'date'         => $date,
            'time'         => $time,
            'vehicle_id'   => $vehicle_id,
            'vehicle_name' => $vehicle_name,
            'vehicle_img'  => $vehicle_img,
            'pax'          => $max_pax,
            'bag'          => $max_bag,
            'price'        => $is_quote_ride ? 'Custom Quote' : $locked_price,
            'created_at'   => current_time( 'timestamp' ),
        );

        set_transient( 'etb_quote_' . $token, $quote_data, 30 * MINUTE_IN_SECONDS );

        // 6. Construction de l'URL de redirection propre
        $gen_settings = get_option( 'etb_general_settings', array() );
        $checkout_url = ! empty( $gen_settings['checkout_page_url'] ) ? esc_url( $gen_settings['checkout_page_url'] ) : home_url( '/checkout/' );
        $redirect_url = add_query_arg( 'ref', $token, $checkout_url );

        wp_send_json_success( array(
            'ref'          => $token,
            'redirect_url' => $redirect_url,
        ) );
    }

    /**
     * Traitement AJAX : Initialisation du PaymentIntent Stripe (Modèle Blacklane avec capture différée)
     */
    public function handle_create_payment_intent() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        // 1. Contrôle Anti-Abus (Max 10 tentatives / 10 minutes par IP)
        if ( ! ETB_Security::check_rate_limit( 'stripe_intent', 10, 600 ) ) {
            wp_send_json_error( array( 'message' => 'Too many payment attempts. Please wait a few minutes.' ) );
        }

        // 2. Vérification que Stripe est activé et configuré
        $settings = get_option( 'etb_general_settings', array() );
        $stripe_enabled = ! empty( $settings['stripe_enabled'] ) && '1' === $settings['stripe_enabled'];
        $secret_key     = ! empty( $settings['stripe_secret_key'] ) ? trim( $settings['stripe_secret_key'] ) : '';

        if ( ! $stripe_enabled || empty( $secret_key ) ) {
            wp_send_json_error( array( 'message' => 'Stripe payment gateway is not active.' ) );
        }

        if ( ! class_exists( 'ETB_Stripe' ) ) {
            require_once ETB_PATH . 'includes/class-etb-stripe.php';
        }

        // 3. Récupération et assainissement des données de transaction
        $name       = sanitize_text_field( $_POST['name'] ?? 'VIP Customer' );
        $email      = sanitize_email( $_POST['email'] ?? '' );
        $phone      = sanitize_text_field( $_POST['phone'] ?? '' );
        $amount     = max( 0.0, floatval( $_POST['amount'] ?? 0.0 ) );
        $currency   = sanitize_text_field( $_POST['currency'] ?? 'eur' );
        $route_info = sanitize_text_field( $_POST['route'] ?? 'VIP Ride' );
        $car_name   = sanitize_text_field( $_POST['vehicle_name'] ?? 'Chauffeured Vehicle' );

        if ( $amount <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid payment amount.' ) );
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'A valid email address is required.' ) );
        }

        // 4. Création du client dans Stripe
        $customer_res = ETB_Stripe::create_customer( $name, $email, $phone );
        $customer_id  = '';
        if ( ! is_wp_error( $customer_res ) && ! empty( $customer_res['id'] ) ) {
            $customer_id = $customer_res['id'];
        }

        // 5. Méthode de prélèvement : 'manual' (Modèle Blacklane) ou 'automatic' (Débit direct)
        $capture_method = ( isset( $settings['stripe_capture_method'] ) && 'immediate' === $settings['stripe_capture_method'] ) ? 'automatic' : 'manual';

        // Métadonnées visibles dans votre Dashboard Stripe
        $metadata = array(
            'customer_name' => $name,
            'customer_email'=> $email,
            'customer_phone'=> $phone,
            'vehicle'       => $car_name,
            'route'         => substr( $route_info, 0, 200 ),
            'source'        => 'EDEN CAB - Checkout Funnel',
        );

        // 6. Création du PaymentIntent officiel auprès de Stripe
        $intent_res = ETB_Stripe::create_payment_intent( $amount, $currency, $customer_id, $metadata, $capture_method );

        if ( is_wp_error( $intent_res ) ) {
            wp_send_json_error( array( 'message' => $intent_res->get_error_message() ) );
        }

        if ( empty( $intent_res['client_secret'] ) ) {
            wp_send_json_error( array( 'message' => 'Could not initialize payment with Stripe.' ) );
        }

        // 7. Transmission du client_secret au navigateur pour sécurisation 3D Secure
        wp_send_json_success( array(
            'client_secret'     => $intent_res['client_secret'],
            'payment_intent_id' => $intent_res['id'],
            'customer_id'       => $customer_id,
            'capture_method'    => $capture_method,
        ) );
    }

    /**
     * Traitement AJAX : Règlement d'un devis sur la page de paiement dédiée ([etb_payment])
     * Met à jour la commande WordPress et appelle l'API LimoExpress pour passer la course en PAID
     */
    /**
     * Traitement AJAX : Règlement d'un devis sur la page de paiement dédiée ([etb_payment])
     * Met à jour la commande WordPress et appelle l'API LimoExpress pour passer la course en PAID
     */
    public function handle_settle_quote_payment() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        $booking_id     = absint( $_POST['booking_id'] ?? 0 );
        $amount         = floatval( $_POST['amount'] ?? 0.0 );
        $intent_id      = sanitize_text_field( $_POST['payment_intent_id'] ?? '' );
        $card_last4     = sanitize_text_field( $_POST['card_last4'] ?? '4242' );
        $card_brand     = sanitize_text_field( $_POST['card_brand'] ?? 'card' );
        $card_exp       = sanitize_text_field( $_POST['card_exp'] ?? '' );
        $tip_amount     = max( 0.0, floatval( $_POST['tip_amount'] ?? 0.0 ) );
        $tip_percentage = max( 0, absint( $_POST['tip_percentage'] ?? 0 ) );

        if ( ! $booking_id || get_post_type( $booking_id ) !== 'tour_booking' ) {
            wp_send_json_error( array( 'message' => 'Invalid booking reference.' ) );
        }

        if ( $amount <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid payment amount.' ) );
        }

        // 1. Définition des réglages généraux et devise
        $gen_settings   = get_option( 'etb_general_settings', array() );
        $stripe_enabled = ! empty( $gen_settings['stripe_enabled'] ) && '1' === $gen_settings['stripe_enabled'];
        $stripe_mode    = ( isset( $gen_settings['stripe_mode'] ) && 'live' === $gen_settings['stripe_mode'] ) ? 'live' : 'test';
        $currency_sym   = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';

        // 2. Contrôle d'intégrité Stripe serveur
        if ( $stripe_enabled ) {
            if ( empty( $intent_id ) ) {
                wp_send_json_error( array( 'message' => 'Identifiant de transaction Stripe manquant.' ) );
            }

            if ( ! class_exists( 'ETB_Stripe' ) ) {
                require_once ETB_PATH . 'includes/class-etb-stripe.php';
            }

            $intent_check = ETB_Stripe::retrieve_payment_intent( $intent_id );

            if ( is_wp_error( $intent_check ) ) {
                wp_send_json_error( array( 'message' => 'Échec de vérification bancaire : ' . $intent_check->get_error_message() ) );
            }

            $valid_statuses = array( 'succeeded', 'requires_capture' );
            $intent_status  = $intent_check['status'] ?? '';

            if ( ! in_array( $intent_status, $valid_statuses, true ) ) {
                wp_send_json_error( array( 'message' => 'La transaction Stripe n\'est pas autorisée (Statut : ' . esc_html( $intent_status ) . ').' ) );
            }

            $expected_cents = (int) round( $amount * 100 );
            $stripe_cents   = (int) ( $intent_check['amount'] ?? 0 );

            if ( abs( $expected_cents - $stripe_cents ) > 5 ) {
                wp_send_json_error( array( 'message' => 'Fraude détectée : le montant réglé ne correspond pas au montant de la réservation.' ) );
            }

            if ( ! empty( $intent_check['charges']['data'][0]['payment_method_details']['card'] ) ) {
                $stripe_card = $intent_check['charges']['data'][0]['payment_method_details']['card'];
                $card_last4  = ! empty( $stripe_card['last4'] ) ? sanitize_text_field( $stripe_card['last4'] ) : $card_last4;
                $card_brand  = ! empty( $stripe_card['brand'] ) ? sanitize_text_field( $stripe_card['brand'] ) : $card_brand;
                if ( ! empty( $stripe_card['exp_month'] ) && ! empty( $stripe_card['exp_year'] ) ) {
                    $card_exp = sprintf( '%02d/%02d', $stripe_card['exp_month'], substr( (string) $stripe_card['exp_year'], -2 ) );
                }
            }
        }

        $stripe_url = ! empty( $intent_id )
            ? ( 'live' === $stripe_mode 
                ? 'https://dashboard.stripe.com/payments/' . $intent_id 
                : 'https://dashboard.stripe.com/test/payments/' . $intent_id )
            : '';

        // 3. Mise à jour de la réservation WordPress
        update_post_meta( $booking_id, '_etb_status', 'confirmed' );
        update_post_meta( $booking_id, '_etb_total_price', (float) round( $amount, 2 ) );
        update_post_meta( $booking_id, '_etb_tip_amount', (float) round( $tip_amount, 2 ) );
        update_post_meta( $booking_id, '_etb_tip_percentage', $tip_percentage );
        update_post_meta( $booking_id, '_etb_stripe_payment_intent_id', $intent_id );
        update_post_meta( $booking_id, '_etb_paid_at', current_time( 'mysql' ) );

        $payment_log = array(
            'amount'         => (float) round( $amount, 2 ),
            'method'         => 'card',
            'last_4_digits'  => substr( $card_last4, -4 ),
            'brand'          => strtolower( $card_brand ),
            'expire_date'    => $card_exp,
            'receipt_number' => $intent_id,
            'remark'         => $stripe_url,
            'paid_at'        => current_time( 'mysql' ),
        );
        update_post_meta( $booking_id, '_etb_payment_logs', array( $payment_log ) );

        // 4. Construction du tampon de paiement et nettoyage de l'ancienne note
        $paid_date_formatted = wp_date( 'd M Y \a\t H:i' );
        $tip_stamp_line      = ( $tip_amount > 0 ) ? sprintf( "💸 Driver tip included: %s %s (%d%%)\n", number_format_i18n( $tip_amount, 2 ), $currency_sym, $tip_percentage ) : "";

        $paid_stamp = "\n═ ✅ ONLINE PAYMENT COLLECTED ═\n"
            . "💳 Amount Paid: " . number_format_i18n( $amount, 2 ) . " " . $currency_sym . "\n"
            . $tip_stamp_line
            . "💳 Method: " . ucfirst( $card_brand ) . " •••• " . substr( $card_last4, -4 ) . "\n"
            . "📅 Paid on: " . $paid_date_formatted . "\n";
        if ( ! empty( $stripe_url ) ) {
            $paid_stamp .= "🔗 DIRECT STRIPE LINK:\n" . $stripe_url . "\n";
        }
        $paid_stamp .= "═══════════════════════════════\n\n";

        // Nettoyage de l'ancienne note (suppression des anciens bandeaux d'attente/urgence)
        $raw_note   = get_post_meta( $booking_id, '_etb_note', true );
        $clean_note = preg_replace( '/[=═]{1,}\s*[⏳🚨✅].*?[=═]{1,}.*?[=═]{5,}[\r\n\s]*/us', '', (string) $raw_note );
        $clean_note = preg_replace( '/[\r\n]*💸\s*(POURBOIRE CHAUFFEUR INCLUS|Driver tip included)\s*:.*?(?=\r|\n|$)/ui', '', $clean_note );
        $clean_note = trim( $clean_note );

        $final_dispatcher_note = $paid_stamp . $clean_note;
        update_post_meta( $booking_id, '_etb_note', $final_dispatcher_note );

       // 5. Résolution de l'identifiant UUID officiel LimoExpress
        $limo_uuid  = get_post_meta( $booking_id, '_etb_limo_uuid', true );
        $limo_id    = get_post_meta( $booking_id, '_etb_limo_booking_id', true );
        $limo_token = $gen_settings['limo_api_token'] ?? '';
        $limo_synced = false;

        // Si l'UUID n'est pas encore stocké localement, on le retrouve via le numéro
        if ( empty( $limo_uuid ) && ! empty( $limo_id ) && class_exists( 'ETB_LimoExpress' ) ) {
            $limo_info = ETB_LimoExpress::get_booking_details( $limo_id, $booking_id );
            if ( ! empty( $limo_info['uuid'] ) ) {
                $limo_uuid = $limo_info['uuid'];
                update_post_meta( $booking_id, '_etb_limo_uuid', $limo_uuid );
            }
        }

        $target_uuid = ! empty( $limo_uuid ) ? $limo_uuid : $limo_id;

        // 6. Mise à jour officielle de la course dans LimoExpress (POST /api/integration/bookings)
        if ( ! empty( $limo_token ) && ! empty( $target_uuid ) ) {
            $headers = array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $limo_token,
            );

            $pickup_addr  = get_post_meta( $booking_id, '_etb_pickup_address', true ) ?: 'Pickup Location';
            $dropoff_addr = get_post_meta( $booking_id, '_etb_dropoff_info', true ) ?: $pickup_addr;
            $booking_dt   = get_post_meta( $booking_id, '_etb_booking_date', true );
            $booking_tm   = get_post_meta( $booking_id, '_etb_booking_time', true ) ?: '09:00';
            $pickup_iso   = sprintf( '%s %s:00', $booking_dt, substr( $booking_tm, 0, 5 ) );

            // Type de réservation (Standard vs Hourly)
            $type_id = $gen_settings['limo_booking_type_id'] ?? '';
            $duration_h = floatval( get_post_meta( $booking_id, '_etb_duration_hours', true ) ?: 1.0 );
            if ( $duration_h > 1.0 && ! empty( $gen_settings['limo_hourly_type_id'] ) ) {
                $type_id = $gen_settings['limo_hourly_type_id'];
            }
            if ( empty( $type_id ) && class_exists( 'ETB_LimoExpress' ) ) {
                $available_types = ETB_LimoExpress::get_booking_types();
                if ( ! empty( $available_types[0]['id'] ) ) {
                    $type_id = (string) $available_types[0]['id'];
                }
            }

            // Récupération des suppléments enregistrés
            $child_seat_fee = floatval( get_post_meta( $booking_id, '_etb_child_seat_fee', true ) );
            $stored_base    = floatval( get_post_meta( $booking_id, '_etb_base_price', true ) );

            // Construction de la liste des extra_fees (Sièges enfants + Pourboire)
            $extra_fees       = array();
            $total_extra_fees = 0.0;

            if ( $child_seat_fee > 0 ) {
                $extra_fees[] = array(
                    'category' => 'child_seat_fee',
                    'value'    => (float) round( $child_seat_fee, 2 ),
                    'amount'   => (float) round( $child_seat_fee, 2 ),
                    'active'   => true,
                );
                $total_extra_fees += $child_seat_fee;
            }

            if ( $tip_amount > 0 ) {
                $clean_tip    = (float) round( $tip_amount, 2 );
                $extra_fees[] = array(
                    'category' => 'gratuity_amount',
                    'value'    => $clean_tip,
                    'amount'   => $clean_tip,
                    'active'   => true,
                );
                $total_extra_fees += $clean_tip;
            }

            // Calcul du tarif transport pur (hors sièges et hors pourboire)
            if ( $stored_base > 0 ) {
                $net_ride_price = $stored_base;
            } else {
                $net_ride_price = max( 0.0, $amount - $total_extra_fees );
            }
            if ( $tip_amount > 0 ) {
                $clean_tip = (float) round( $tip_amount, 2 );
                $extra_fees[] = array(
                    'category' => 'gratuity_amount',
                    'value'    => $clean_tip,
                    'amount'   => $clean_tip,
                    'active'   => true,
                );
            }

            // Moyen de paiement Carte dans LimoExpress
            $card_method_id = get_transient( 'etb_limo_card_method_id' );
            if ( false === $card_method_id ) {
                $pm_res = wp_remote_get( 'https://api.limoexpress.me/api/integration/payment-methods', array(
                    'headers' => array(
                        'Accept'        => 'application/json',
                        'Authorization' => 'Bearer ' . $limo_token,
                    ),
                    'timeout' => 10,
                ) );
                if ( ! is_wp_error( $pm_res ) && 200 === wp_remote_retrieve_response_code( $pm_res ) ) {
                    $pm_data = json_decode( wp_remote_retrieve_body( $pm_res ), true );
                    if ( ! empty( $pm_data['data'] ) && is_array( $pm_data['data'] ) ) {
                        foreach ( $pm_data['data'] as $method ) {
                            $m_name = strtolower( $method['name'] ?? '' );
                            if ( strpos( $m_name, 'card' ) !== false || strpos( $m_name, 'carte' ) !== false || strpos( $m_name, 'stripe' ) !== false || strpos( $m_name, 'online' ) !== false ) {
                                $card_method_id = (string) $method['id'];
                                set_transient( 'etb_limo_card_method_id', $card_method_id, 12 * HOUR_IN_SECONDS );
                                break;
                            }
                        }
                    }
                }
            }

            // Classe de véhicule LimoExpress
            $vehicle_class_id = '';
            $booked_vehicles  = get_post_meta( $booking_id, '_etb_vehicles', true );
            if ( ! empty( $booked_vehicles ) && is_array( $booked_vehicles ) ) {
                foreach ( $booked_vehicles as $v_id => $qty ) {
                    if ( $qty > 0 ) {
                        $v_class = get_post_meta( $v_id, '_etb_limo_class_id', true );
                        if ( ! empty( $v_class ) ) {
                            $vehicle_class_id = trim( $v_class );
                            break;
                        }
                    }
                }
            }

            // Devise Euro UUID LimoExpress
            $currency_uuid = '3cf48cef-5da3-4fa9-bc05-66b96350ecec';

            $update_payload = array(
                'id'                 => (string) $target_uuid,
                'booking_type_id'    => (string) $type_id,
                'from_location'      => array( 'name' => $pickup_addr ),
                'to_location'        => array( 'name' => $dropoff_addr ),
                'pickup_time'        => $pickup_iso,
                'price'              => (float) round( $net_ride_price, 2 ),
                'price_type'         => 'NET',
                'currency_id'        => $currency_uuid,
                'note'               => $final_dispatcher_note,
                'paid'               => true,
                'confirmed'          => true,
                'extra_fees'         => $extra_fees,
                'driving_extra_fees' => $extra_fees,
            );

            if ( ! empty( $vehicle_class_id ) ) {
                $update_payload['vehicle_class_id'] = $vehicle_class_id;
            }
            if ( ! empty( $card_method_id ) ) {
                $update_payload['payment_method_id'] = (string) $card_method_id;
            }

            // ÉTAPE 1 : Mise à jour des données (POST /api/integration/bookings)
            $update_res = wp_remote_post( 'https://api.limoexpress.me/api/integration/bookings', array(
                'headers' => $headers,
                'body'    => wp_json_encode( $update_payload ),
                'timeout' => 15,
            ) );

            $up_code = ! is_wp_error( $update_res ) ? wp_remote_retrieve_response_code( $update_res ) : 500;
            $up_body = ! is_wp_error( $update_res ) ? wp_remote_retrieve_body( $update_res ) : $update_res->get_error_message();
            
            // Log diagnostic précis dans debug.log et post_meta
            error_log( "[ETB LIMO UPDATE] HTTP: " . $up_code . " | Réponse: " . $up_body );
            update_post_meta( $booking_id, '_etb_limo_debug_response', 'HTTP ' . $up_code . ' : ' . $up_body );

            // ÉTAPE 2 : Déclenchement formel des statuts Payé et Confirmé
            $paid_res = wp_remote_post( 'https://api.limoexpress.me/api/integration/mark-booking-as-paid', array(
                'headers' => $headers,
                'body'    => wp_json_encode( array( 'id' => (string) $target_uuid ) ),
                'timeout' => 15,
            ) );

            $conf_res = wp_remote_post( 'https://api.limoexpress.me/api/integration/mark-booking-as-confirmed', array(
                'headers' => $headers,
                'body'    => wp_json_encode( array( 'id' => (string) $target_uuid ) ),
                'timeout' => 15,
            ) );

            $paid_code = ! is_wp_error( $paid_res ) ? wp_remote_retrieve_response_code( $paid_res ) : 500;
            if ( $up_code >= 200 && $up_code < 300 && $paid_code >= 200 && $paid_code < 300 ) {
                $limo_synced = true;
                update_post_meta( $booking_id, '_etb_limo_status', 'synced' );
                update_post_meta( $booking_id, '_etb_limo_paid_synced', '1' );
                delete_post_meta( $booking_id, '_etb_limo_error' );
            } else {
                $err_msg = ! is_wp_error( $update_res ) ? wp_remote_retrieve_body( $update_res ) : $update_res->get_error_message();
                update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
                update_post_meta( $booking_id, '_etb_limo_error', 'LimoExpress update error: ' . $err_msg );
            }
        }

        // 9. Envoi du reçu officiel au client (English)
        $customer_email = get_post_meta( $booking_id, '_etb_customer_email', true );
        $customer_name  = get_post_meta( $booking_id, '_etb_customer_name', true ) ?: 'Client';
        $company_name   = get_bloginfo( 'name' );

        if ( ! empty( $customer_email ) && is_email( $customer_email ) ) {
            $subject = 'Official Payment Receipt #' . $booking_id . ' — ' . $company_name;
            $message = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">'
                . '<div style="background: #0f172a; padding: 25px 30px;">'
                . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">' . esc_html( $company_name ) . '</h1>'
                . '<p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em;">Official Paid Receipt #' . $booking_id . '</p>'
                . '</div>'
                . '<div style="padding: 30px;">'
                . '<p style="font-size: 15px; margin-top: 0; margin-bottom: 16px;">Dear <strong>' . esc_html( $customer_name ) . '</strong>,</p>'
                . '<p style="font-size: 14px; margin-bottom: 20px;">Your payment has been successfully authorized and confirmed. Your mission is locked in dispatch.</p>'
                . '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 18px; margin-bottom: 24px;">'
                . '<table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">'
                . '<tr style="border-bottom: 1px solid #dcfce7;"><td style="padding: 6px 0; color: #166534;">Total Paid:</td><td style="padding: 6px 0; text-align: right; font-weight: 800; font-size: 17px; color: #15803d;">' . number_format_i18n( $amount, 2 ) . ' ' . esc_html( $currency_sym ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #dcfce7;"><td style="padding: 6px 0; color: #166534;">Payment Method:</td><td style="padding: 6px 0; text-align: right; font-weight: 600; color: #15803d;">' . ucfirst( esc_html( $card_brand ) ) . ' •••• ' . esc_html( substr( $card_last4, -4 ) ) . '</td></tr>'
                . '<tr><td style="padding: 6px 0; color: #166534;">Transaction ID:</td><td style="padding: 6px 0; text-align: right; font-family: monospace; font-size: 11px; color: #15803d;">' . esc_html( $intent_id ) . '</td></tr>'
                . '</table>'
                . '</div>'
                . '<p style="font-size: 13px; color: #475569; margin: 0;">Your chauffeur will send an SMS update prior to pickup. Thank you for your trust.</p>'
                . '</div>'
                . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
                . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — VIP Chauffeur & Private Transfers</p>'
                . '</div>'
                . '</div>'
                . '</div>';

            $admin_email    = ! empty( $gen_settings['admin_email'] ) && is_email( $gen_settings['admin_email'] ) ? sanitize_email( $gen_settings['admin_email'] ) : get_option( 'admin_email' );
            $headers_client = ETB_Settings::get_mail_headers( $admin_email, $company_name );
            wp_mail( $customer_email, $subject, $message, $headers_client );
        }

        // 10. Alerte d'encaissement instantanée pour l'administrateur (English)
        $admin_email = ! empty( $gen_settings['admin_email'] ) && is_email( $gen_settings['admin_email'] ) ? sanitize_email( $gen_settings['admin_email'] ) : get_option( 'admin_email' );
        if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
            $subject_admin  = sprintf( '💰 [PAYMENT RECEIVED] %s %s collected for Dossier #%d — %s', number_format_i18n( $amount, 2 ), $currency_sym, $booking_id, $customer_name );
            $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
            $headers_admin  = ETB_Settings::get_mail_headers( $customer_email, $customer_name );

            $message_admin  = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
                . '<div style="background: #15803d; padding: 22px 28px;">'
                . '<span style="background: rgba(255,255,255,0.2); color: #ffffff; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.08em; display: inline-block; margin-bottom: 6px;">Payment Authorized</span>'
                . '<h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 800;">💰 Payment Successfully Collected Online</h1>'
                . '<p style="margin: 4px 0 0 0; color: #bbf7d0; font-size: 12px;">Dossier #' . $booking_id . ' — Online Settlement Confirmation</p>'
                . '</div>'
                . '<div style="padding: 28px;">'
                . '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 22px;">'
                . '<p style="margin: 0; font-size: 13px; color: #166534;">Total Amount Paid:</p>'
                . '<p style="margin: 2px 0 0 0; font-size: 26px; font-weight: 900; color: #15803d;">' . number_format_i18n( $amount, 2 ) . ' ' . esc_html( $currency_sym ) . '</p>'
                . '<p style="margin: 6px 0 0 0; font-size: 12px; color: #166534;">Card: ' . ucfirst( esc_html( $card_brand ) ) . ' •••• ' . esc_html( substr( $card_last4, -4 ) ) . ' (Exp: ' . esc_html( $card_exp ) . ')</p>'
                . '</div>'
                . '<p style="margin: 4px 0;"><strong>Passenger:</strong> ' . esc_html( $customer_name ) . ' (<a href="mailto:' . esc_attr( $customer_email ) . '">' . esc_html( $customer_email ) . '</a>)</p>'
                . '<p style="margin: 4px 0;"><strong>Stripe Transaction ID:</strong> <code style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">' . esc_html( $intent_id ) . '</code></p>'
                . ( ! empty( $stripe_url ) ? '<p style="margin: 12px 0;"><a href="' . esc_url( $stripe_url ) . '" target="_blank" style="background: #635bff; color: #ffffff; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 12px; display: inline-block;">🔗 View Transaction on Stripe Dashboard</a></p>' : '' )
                . '<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">'
                . '<div style="text-align: center;">'
                . '<a href="' . esc_url( $admin_edit_url ) . '" style="color: #64748b; font-size: 12px; text-decoration: underline;">View Reservation #' . $booking_id . ' in WordPress</a>'
                . '</div>'
                . '</div>'
                . '</div>'
                . '</div>';

            wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );
        }

        wp_send_json_success( array(
            'booking_id'  => $booking_id,
            'limo_synced' => $limo_synced,
            'message'     => 'Payment successfully recorded.',
        ) );
    }

    /**
     * Tâche automatique : vérifie si le paiement a été abandonné et relance le client (+1h)
     *
     * @param int $booking_id
     */
    public function handle_abandoned_payment_reminder( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id || 'tour_booking' !== get_post_type( $booking_id ) ) {
            return;
        }

        // 1. Vérifications : est-ce déjà payé, confirmé ou déjà relancé ?
        $status       = get_post_meta( $booking_id, '_etb_status', true );
        $intent_id    = get_post_meta( $booking_id, '_etb_stripe_payment_intent_id', true );
        $already_sent = get_post_meta( $booking_id, '_etb_abandoned_reminder_sent', true );

        if ( 'confirmed' === $status || ! empty( $intent_id ) || '1' === $already_sent ) {
            return; // Le paiement a été fait ! Fin silencieuse, aucun e-mail envoyé.
        }

        // 2. Vérification LimoExpress (au cas où le client a payé hors ligne)
        if ( class_exists( 'ETB_LimoExpress' ) ) {
            $limo_id   = get_post_meta( $booking_id, '_etb_limo_booking_id', true );
            $limo_uuid = get_post_meta( $booking_id, '_etb_limo_uuid', true );
            $search_id = ! empty( $limo_uuid ) ? $limo_uuid : $limo_id;
            if ( $search_id ) {
                $limo_check = ETB_LimoExpress::get_booking_details( $search_id, $booking_id );
                if ( ! empty( $limo_check['paid'] ) ) {
                    update_post_meta( $booking_id, '_etb_status', 'confirmed' );
                    return; // Déjà payé dans LimoExpress ! Fin silencieuse.
                }
            }
        }

        // 3. Préparation de l'e-mail de relance avec le lien de paiement
        $customer_email = get_post_meta( $booking_id, '_etb_customer_email', true );
        $customer_name  = get_post_meta( $booking_id, '_etb_customer_name', true ) ?: 'Client';
        $pay_token      = get_post_meta( $booking_id, '_etb_pay_token', true );

        if ( empty( $customer_email ) || ! is_email( $customer_email ) || empty( $pay_token ) ) {
            return;
        }

        $gen_settings     = get_option( 'etb_general_settings', array() );
        $payment_base_url = ! empty( $gen_settings['payment_page_url'] ) ? esc_url_raw( $gen_settings['payment_page_url'] ) : home_url( '/payment/' );
        $payment_link_url = add_query_arg( 'ref', $pay_token, $payment_base_url );

        $company_name    = get_bloginfo( 'name' );
        $currency_symbol = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';
        $total_price     = floatval( get_post_meta( $booking_id, '_etb_total_price', true ) );
        $pickup          = get_post_meta( $booking_id, '_etb_pickup_address', true ) ?: '—';
        $dropoff         = get_post_meta( $booking_id, '_etb_dropoff_info', true ) ?: '—';
        $date            = get_post_meta( $booking_id, '_etb_booking_date', true );
        $time            = get_post_meta( $booking_id, '_etb_booking_time', true );

        $date_ts         = strtotime( $date );
        $formatted_dt    = $date_ts ? date( 'd M Y', $date_ts ) : $date;

        $admin_email = ! empty( $gen_settings['admin_email'] ) && is_email( $gen_settings['admin_email'] )
            ? sanitize_email( $gen_settings['admin_email'] )
            : get_option( 'admin_email' );

        $headers_client = ETB_Settings::get_mail_headers( $admin_email, $company_name );
        $subject_client = 'Complete Your VIP Reservation #' . $booking_id . ' — ' . $company_name;

        $message_client = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
            . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">'
            . '<div style="background: #0f172a; padding: 25px 30px;">'
            . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">' . esc_html( $company_name ) . '</h1>'
            . '<p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em;">Pending Reservation #' . $booking_id . '</p>'
            . '</div>'
            . '<div style="padding: 30px;">'
            . '<p style="font-size: 15px; margin-top: 0; margin-bottom: 16px;">Dear <strong>' . esc_html( $customer_name ) . '</strong>,</p>'
            . '<p style="font-size: 14px; margin-bottom: 20px;">We noticed you started reserving your VIP transfer but didn\'t finish the payment step. We have kept your vehicle reserved for you.</p>'
            . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Pickup:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Drop-off:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $dropoff ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $formatted_dt ) . ' at ' . esc_html( $time ) . '</td></tr>'
            . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Total to Pay:</td><td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 800; color: #e65a15;">' . number_format_i18n( $total_price, 2 ) . ' ' . esc_html( $currency_symbol ) . '</td></tr>'
            . '</table>'
            . '<div style="text-align: center; margin: 26px 0 16px 0;">'
            . '<a href="' . esc_url( $payment_link_url ) . '" style="background: #fbac18; color: #0f172a; padding: 14px 32px; text-decoration: none; border-radius: 50px; font-weight: 800; font-size: 14px; display: inline-block;">Complete Your Reservation Now ➔</a>'
            . '</div>'
            . '<p style="font-size: 12px; color: #64748b; text-align: center; margin-bottom: 0;">You can complete your payment online anytime prior to pickup.</p>'
            . '</div>'
            . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
            . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — Cannes, Côte d\'Azur, France</p>'
            . '</div>'
            . '</div>'
            . '</div>';

        wp_mail( $customer_email, $subject_client, $message_client, $headers_client );

        // Marqueur anti-doublon (pour ne jamais relancer deux fois)
        update_post_meta( $booking_id, '_etb_abandoned_reminder_sent', '1' );
    }

}