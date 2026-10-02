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

        // Route AJAX pour le Micro-Modal de devis rapide (Email Inquiry)
        add_action( 'wp_ajax_etb_send_email_inquiry', array( $this, 'handle_send_email_inquiry' ) );
        add_action( 'wp_ajax_nopriv_etb_send_email_inquiry', array( $this, 'handle_send_email_inquiry' ) );

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

     
        // Notifications E-mail
        $general_settings = get_option( 'etb_general_settings', array() );
        $currency_symbol  = ! empty( $general_settings['currency'] ) ? sanitize_text_field( $general_settings['currency'] ) : '€';
        $admin_email      = ! empty( $general_settings['admin_email'] ) && is_email( $general_settings['admin_email'] ) ? sanitize_email( $general_settings['admin_email'] ) : get_option( 'admin_email' );

        $formatted_total  = number_format_i18n( $pricing_details['grand_total'], 2 ) . ' ' . $currency_symbol;
        $pickup_name      = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : ( $data['pickup_id'] ? get_the_title( $data['pickup_id'] ) : 'Non spécifié' );
        
        $fallback_label   = sprintf( 'Option Circuit : %s (%sh)', $data['option_id'], $pricing_details['duration_hours'] ?? 1 );
        $prestation_label = ! empty( $data['option_id'] ) ? ETB_Pricing_Engine::get_circuit_option_label( $data['option_id'], $data['circuit_id'], $fallback_label ) : 'Transfert standard';

        $vehicles_list = '';
        foreach ( $data['vehicles'] as $v_id => $qty ) {
            if ( $qty > 0 ) $vehicles_list .= '<li>' . esc_html( get_the_title( $v_id ) ) . ' &times; ' . absint( $qty ) . '</li>';
        }

        $extras_list = '';
        foreach ( $data['extras'] as $e_id => $qty ) {
            if ( $qty > 0 ) $extras_list .= '<li>' . esc_html( get_the_title( $e_id ) ) . ' &times; ' . absint( $qty ) . '</li>';
        }

        $promo_html = '';
        if ( ! empty( $pricing_details['discount_amount'] ) && $pricing_details['discount_amount'] > 0 ) {
            $formatted_discount = number_format_i18n( $pricing_details['discount_amount'], 2 ) . ' ' . $currency_symbol;
            $promo_html = '<p style="color:#22c55e;"><strong>Code promo (' . esc_html( $pricing_details['promo_code'] ) . ') :</strong> - ' . $formatted_discount . '</p>';
        }
        $note_html = ! empty( $data['note'] ) ? '<h3>Demande spéciale :</h3> <p> </br>' . nl2br( esc_html( $data['note'] ) ) . '</p>' : '';

        // Préparation du numéro de téléphone pour l'affichage
        $phone_display = ! empty( $data['phone'] ) ? esc_html( $data['phone'] ) : 'Non renseigné';

        // En-têtes officiels avec From officiel (office@eden-cab.com) et Reply-To
        $company_name   = get_bloginfo( 'name' );
        $headers_client = ETB_Settings::get_mail_headers( $admin_email, $company_name );
        $headers_admin  = ETB_Settings::get_mail_headers( $data['email'], $data['name'] );

        // Préparation des notes et réductions pour le client en anglais
        $promo_html_en = '';
        if ( ! empty( $pricing_details['discount_amount'] ) && $pricing_details['discount_amount'] > 0 ) {
            $formatted_discount = number_format_i18n( $pricing_details['discount_amount'], 2 ) . ' ' . $currency_symbol;
            $promo_html_en      = '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #16a34a;">Promo Code (' . esc_html( $pricing_details['promo_code'] ) . '):</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #16a34a;">- ' . $formatted_discount . '</td></tr>';
        }

        $note_html_en = ! empty( $data['note'] )
            ? '<div style="margin-top: 20px; padding: 14px 16px; background: #f8fafc; border-left: 3px solid #fbac18; border-radius: 6px;"><strong style="font-size: 11px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; display: block; margin-bottom: 4px;">Special Request / Note:</strong><p style="margin: 0; font-size: 13px; color: #334155;">' . nl2br( esc_html( $data['note'] ) ) . '</p></div>'
            : '';

        $dropoff_display_en = empty( $data['dropoff_info'] ) ? 'Same as pickup location' : $data['dropoff_info'];

        $subject_client = 'Booking Request Confirmation #' . $booking_id . ' — ' . $company_name;
        $message_client = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
            . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">'
            . '<div style="background: #0f172a; padding: 25px 30px;">'
            . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">' . esc_html( $company_name ) . '</h1>'
            . '<p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em;">VIP Booking Request #' . $booking_id . '</p>'
            . '</div>'
            . '<div style="padding: 30px;">'
            . '<p style="font-size: 15px; margin-top: 0; margin-bottom: 16px;">Dear <strong>' . esc_html( $data['name'] ) . '</strong>,</p>'
            . '<p style="font-size: 14px; margin-bottom: 22px;">Thank you for your reservation. Your request has been registered under dossier <strong>#' . $booking_id . '</strong>. Our dispatch team is currently reviewing your mission.</p>'
            . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Tour / Service:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $prestation_label ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Pickup:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup_name ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Drop-off:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $dropoff_display_en ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $data['date'] ) . ' at ' . esc_html( $data['time'] ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Passengers:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . ( $data['adults'] + $data['children'] ) . ' (' . $data['adults'] . ' adult(s), ' . $data['children'] . ' child(ren))</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Luggage:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . $data['luggage'] . ' piece(s)</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b; vertical-align: top;">Vehicle(s):</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;"><ul style="margin: 0; padding: 0; list-style: none;">' . $vehicles_list . '</ul></td></tr>'
            . ( $extras_list ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b; vertical-align: top;">Extra(s):</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;"><ul style="margin: 0; padding: 0; list-style: none;">' . $extras_list . '</ul></td></tr>' : '' )
            . $promo_html_en
            . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 14px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Estimated Total:</td><td style="padding: 14px 0; text-align: right; font-size: 18px; font-weight: 800; color: #e65a15;">' . $formatted_total . '</td></tr>'
            . '</table>'
            . $note_html_en
            . '<div style="margin-top: 25px; padding: 14px 16px; background: #f8fafc; border-radius: 6px; font-size: 12.5px; color: #64748b; text-align: center;">'
            . 'Our dispatch team will contact you shortly with final itinerary confirmation.'
            . '</div>'
            . '</div>'
            . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
            . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — VIP Chauffeur & Private Tours</p>'
            . '</div>'
            . '</div>'
            . '</div>';

        

        $subject_admin  = '[Nouvelle Réservation] Dossier #' . $booking_id . ' — ' . $data['name'];
        $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
        $wa_clean_phone = ! empty( $data['phone'] ) ? preg_replace( '/[^0-9]/', '', $data['phone'] ) : '';
        $wa_admin_link  = ! empty( $wa_clean_phone ) ? 'https://api.whatsapp.com/send?phone=' . $wa_clean_phone : '';

        $promo_row_admin = '';
        if ( ! empty( $pricing_details['discount_amount'] ) && $pricing_details['discount_amount'] > 0 ) {
            $formatted_discount = number_format_i18n( $pricing_details['discount_amount'], 2 ) . ' ' . $currency_symbol;
            $promo_row_admin    = '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #16a34a;">Remise promo (' . esc_html( $pricing_details['promo_code'] ) . ') :</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #16a34a;">- ' . $formatted_discount . '</td></tr>';
        }

        $note_box_admin = ! empty( $data['note'] )
            ? '<div style="margin-top: 18px; padding: 12px 14px; background: #fffbeb; border-left: 3px solid #f59e0b; border-radius: 4px;"><strong style="font-size: 11px; text-transform: uppercase; color: #92400e; display: block; margin-bottom: 4px;">Demande spéciale client :</strong><p style="margin: 0; font-size: 13px; color: #78350f;">' . nl2br( esc_html( $data['note'] ) ) . '</p></div>'
            : '';

        $message_admin = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
            . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
            . '<div style="background: #0f172a; padding: 22px 28px;">'
            . '<span style="background: rgba(251, 172, 24, 0.2); color: #fbac18; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.08em; display: inline-block; margin-bottom: 6px;">Nouvelle Réservation</span>'
            . '<h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 800;">Dossier #' . $booking_id . ' — ' . esc_html( $data['name'] ) . '</h1>'
            . '<p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 12px;">Reçue depuis la page circuit / réservation</p>'
            . '</div>'
            . '<div style="padding: 28px;">'
            
            // Fiche contact rapide client
            . '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;">'
            . '<strong style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 6px;">👤 Contact Client</strong>'
            . '<p style="margin: 2px 0; font-size: 15px; font-weight: 700; color: #0f172a;">' . esc_html( $data['name'] ) . '</p>'
            . '<p style="margin: 2px 0; font-size: 13px; color: #475569;">✉️ <a href="mailto:' . esc_attr( $data['email'] ) . '" style="color: #0284c7; text-decoration: none;">' . esc_html( $data['email'] ) . '</a></p>'
            . '<p style="margin: 2px 0; font-size: 13px; color: #475569;">📞 <a href="tel:' . esc_attr( $data['phone'] ) . '" style="color: #0284c7; text-decoration: none;">' . $phone_display . '</a></p>'
            . ( ! empty( $wa_admin_link ) ? '<div style="margin-top: 8px;"><a href="' . esc_url( $wa_admin_link ) . '" target="_blank" style="background: #22c55e; color: #ffffff; padding: 5px 12px; border-radius: 50px; text-decoration: none; font-size: 11.5px; font-weight: 700; display: inline-block;">💬 Contacter sur WhatsApp</a></div>' : '' )
            . '</div>'

            // Tableau des détails de la mission
            . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13.5px;">'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Prestation :</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $prestation_label ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Départ :</td><td style="padding: 9px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup_name ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Arrivée :</td><td style="padding: 9px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( empty( $data['dropoff_info'] ) ? 'Identique au départ' : $data['dropoff_info'] ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Date & Heure :</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $data['date'] ) . ' à ' . esc_html( $data['time'] ) . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Passagers :</td><td style="padding: 9px 0; text-align: right; font-weight: 600; color: #0f172a;">' . ( $data['adults'] + $data['children'] ) . ' (' . $data['adults'] . ' ad., ' . $data['children'] . ' enf.)</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Bagages :</td><td style="padding: 9px 0; text-align: right; font-weight: 600; color: #0f172a;">' . $data['luggage'] . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b; vertical-align: top;">Véhicule(s) :</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #0f172a;"><ul style="margin: 0; padding: 0; list-style: none;">' . $vehicles_list . '</ul></td></tr>'
            . ( $extras_list ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b; vertical-align: top;">Option(s) :</td><td style="padding: 9px 0; text-align: right; font-weight: 600; color: #0f172a;"><ul style="margin: 0; padding: 0; list-style: none;">' . $extras_list . '</ul></td></tr>' : '' )
            . $promo_row_admin
            . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Montant Total :</td><td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 800; color: #e65a15;">' . $formatted_total . '</td></tr>'
            . '</table>'
            . $note_box_admin
            . '<div style="text-align: center; margin-top: 25px;">'
            . '<a href="' . esc_url( $admin_edit_url ) . '" style="background: #0f172a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 50px; font-weight: 800; font-size: 13px; display: inline-block;">Consulter le dossier dans WordPress ➔</a>'
            . '</div>'
            . '</div>'
            . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 28px; text-align: center; font-size: 11.5px; color: #64748b;">'
            . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — Administration & Dispatch</p>'
            . '</div>'
            . '</div>'
            . '</div>';

            
        // 1. Transmission à l'Application de Dispatch sélectionnée
        if ( ! class_exists( 'ETB_Dispatcher_Manager' ) ) {
            require_once ETB_PATH . 'includes/class-etb-dispatcher-manager.php';
        }
        $dispatch_result = ETB_Dispatcher_Manager::dispatch_booking( $booking_id, $data );

        // Sauvegarde immédiate du statut pour qu'il s'affiche dans WordPress !
        if ( $dispatch_result['success'] ) {
            update_post_meta( $booking_id, '_etb_limo_status', 'synced' );
        } else {
            update_post_meta( $booking_id, '_etb_limo_status', 'failed' );
            update_post_meta( $booking_id, '_etb_limo_error', $dispatch_result['message'] );
        }

        // 2. Alerte e-mail administrateur si le dispatch externe a échoué
        if ( ! $dispatch_result['success'] ) {
            $subject_admin = '[Action Requise - Échec Dispatch] Dossier #' . $booking_id . ' - ' . $data['name'];
            $alert_box     = '<div style="background:#fee2e2; border-left:4px solid #dc2626; padding:12px; margin-bottom:15px; color:#991b1b;">'
                . '<strong>⚠️ ATTENTION : La synchronisation vers votre application externe a échoué.</strong><br>'
                . 'Motif : ' . esc_html( $dispatch_result['message'] ) . '<br>'
                . '👉 <em>Vous pouvez relancer le transfert depuis la commande WordPress.</em>'
                . '</div>';
            $message_admin = $alert_box . $message_admin;
        } else {
            $subject_admin = '[Nouvelle Réservation] Dossier #' . $booking_id . ' - ' . $data['name'];
        }

        // 3. Expédition des e-mails
        wp_mail( $data['email'], $subject_client, $message_client, $headers_client );
        wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );
        
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
     * Traitement AJAX : Envoi d'une demande de devis rapide par e-mail (Quick Email Inquiry)
     */
    public function handle_send_email_inquiry() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        // 1. Bouclier Anti-Spam (Max 5 demandes / 10 minutes par IP)
        if ( ! ETB_Security::check_rate_limit( 'email_inquiry', 5, 600 ) ) {
            wp_send_json_error( array( 'message' => 'Too many requests sent. Please wait a few minutes before trying again.' ) );
        }

        // 2. Récupération et assainissement strict des données
        $name            = sanitize_text_field( $_POST['inquiry_name'] ?? '' );
        $email           = sanitize_email( $_POST['inquiry_email'] ?? '' );
        $phone           = sanitize_text_field( $_POST['inquiry_phone'] ?? '' );
        $notes           = sanitize_textarea_field( $_POST['inquiry_notes'] ?? '' );
        $vehicle_name    = sanitize_text_field( $_POST['vehicle_name'] ?? 'Not specified' );
        $trip_route      = sanitize_text_field( $_POST['trip_route'] ?? 'Not specified' );
        $trip_datetime   = sanitize_text_field( $_POST['trip_datetime'] ?? 'Not specified' );
        $estimated_price = sanitize_text_field( $_POST['estimated_price'] ?? 'Custom Quote' );

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'Please enter your full name.' ) );
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
        }

        // 3. Préparation des destinataires
        $gen_settings = get_option( 'etb_general_settings', array() );
        $admin_email  = ! empty( $gen_settings['admin_email'] ) && is_email( $gen_settings['admin_email'] ) 
            ? sanitize_email( $gen_settings['admin_email'] ) 
            : get_option( 'admin_email' );
        $company_name = get_bloginfo( 'name' );

        // En-têtes officiels avec From officiel (office@eden-cab.com) et Reply-To
        $headers_client = ETB_Settings::get_mail_headers( $admin_email, $company_name );
        $headers_admin  = ETB_Settings::get_mail_headers( $email, $name );

        // 4. E-mail Administrateur (Lead complet prêt à être traité)
        $subject_admin = '[New Inquiry] ' . $vehicle_name . ' - ' . $clean_name;
        $message_admin = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #1e293b; line-height: 1.6;">'
            . '<div style="background: #0f172a; padding: 20px; border-radius: 8px 8px 0 0; color: #ffffff;">'
            . '<h2 style="margin: 0; color: #fbac18; font-size: 20px;">✉️ Nouvelle Demande de Devis Rapide</h2>'
            . '<p style="margin: 5px 0 0 0; font-size: 13px; color: #94a3b8;">Reçue depuis le widget de réservation ' . esc_html( $company_name ) . '</p>'
            . '</div>'
            . '<div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px; border-radius: 0 0 8px 8px; background: #ffffff;">'
            . '<h3 style="margin-top: 0; color: #0f172a; font-size: 16px; border-bottom: 2px solid #fbac18; padding-bottom: 6px;">👤 Coordonnées Prospect</h3>'
            . '<p style="margin: 6px 0;"><strong>Nom :</strong> ' . esc_html( $name ) . '</p>'
            . '<p style="margin: 6px 0;"><strong>E-mail :</strong> <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>'
            . '<p style="margin: 6px 0;"><strong>Téléphone :</strong> ' . esc_html( $phone ?: 'Non renseigné' ) . '</p>'
            . '<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 18px 0;">'
            . '<h3 style="color: #0f172a; font-size: 16px; border-bottom: 2px solid #fbac18; padding-bottom: 6px;">🚘 Détails de la Mission Souhaitée</h3>'
            . '<p style="margin: 6px 0;"><strong>Véhicule :</strong> ' . esc_html( $vehicle_name ) . '</p>'
            . '<p style="margin: 6px 0;"><strong>Trajet / Prestation :</strong> ' . esc_html( $trip_route ) . '</p>'
            . '<p style="margin: 6px 0;"><strong>Date & Heure :</strong> ' . esc_html( $trip_datetime ) . '</p>'
            . '<p style="margin: 6px 0;"><strong>Tarif indicatif en ligne :</strong> <span style="font-weight: bold; color: #d97706;">' . esc_html( $estimated_price ) . '</span></p>'
            . ( $notes ? '<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 18px 0;"><h3 style="color: #0f172a; font-size: 16px;">💬 Message / Précisions du client :</h3><p style="background: #f8fafc; padding: 12px; border-left: 4px solid #3b82f6; border-radius: 4px;">' . nl2br( esc_html( $notes ) ) . '</p>' : '' )
            . '<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">'
            . '<p style="font-size: 12px; color: #64748b;">👉 Pour répondre directement à ce prospect, cliquez simplement sur « Répondre » dans votre messagerie.</p>'
            . '</div>'
            . '</div>';

        // 5. E-mail Accusé de Réception Client (Rassurance VIP)
        $subject_client = 'Quote Inquiry Confirmation — ' . $company_name;
        $message_client = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #1e293b; line-height: 1.6;">'
            . '<div style="background: #0f172a; padding: 20px; border-radius: 8px 8px 0 0; color: #ffffff;">'
            . '<h2 style="margin: 0; color: #fbac18; font-size: 20px;">' . esc_html( $company_name ) . '</h2>'
            . '<p style="margin: 5px 0 0 0; font-size: 13px; color: #94a3b8;">VIP Chauffeur & Private Transfer Service</p>'
            . '</div>'
            . '<div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px; border-radius: 0 0 8px 8px; background: #ffffff;">'
            . '<h3 style="margin-top: 0; color: #0f172a;">Dear ' . esc_html( $name ) . ',</h3>'
            . '<p>Thank you for reaching out to us. We have successfully received your inquiry for your upcoming transfer with <strong>' . esc_html( $vehicle_name ) . '</strong>.</p>'
            . '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin: 16px 0;">'
            . '<p style="margin: 4px 0;">📍 <strong>Route:</strong> ' . esc_html( $trip_route ) . '</p>'
            . '<p style="margin: 4px 0;">📅 <strong>Date & Time:</strong> ' . esc_html( $trip_datetime ) . '</p>'
            . '<p style="margin: 4px 0;">💰 <strong>Estimated Rate:</strong> ' . esc_html( $estimated_price ) . '</p>'
            . '</div>'
            . '<p>Our dispatch team is currently reviewing your request and will provide you with a tailored confirmation within <strong>1 hour</strong>.</p>'
            . '<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">'
            . '<p style="font-size: 12px; color: #64748b;">Best regards,<br><strong>' . esc_html( $company_name ) . ' Reservations Team</strong></p>'
            . '</div>'
            . '</div>';

        // 6. Expédition des e-mails
        wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );
        wp_mail( $email, $subject_client, $message_client, $headers_client );

        wp_send_json_success( array(
            'message' => 'Thank you! Your inquiry has been submitted. Our dispatch team will get back to you within 1 hour.'
        ) );
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

        $flight_number   = sanitize_text_field( $_POST['etb_flight_number'] ?? '' );
        $pickup_sign     = sanitize_text_field( $_POST['etb_pickup_sign'] ?? '' );
        $booker_type     = sanitize_text_field( $_POST['etb_booker_type'] ?? 'myself' );
        $first_name      = sanitize_text_field( $_POST['etb_first_name'] ?? '' );
        $last_name       = sanitize_text_field( $_POST['etb_last_name'] ?? '' );
        $email           = sanitize_email( $_POST['etb_email'] ?? '' );
        $phone           = sanitize_text_field( $_POST['etb_phone'] ?? '' );
        $booker_name        = sanitize_text_field( $_POST['etb_booker_name'] ?? '' );
        $booker_email       = sanitize_email( $_POST['etb_booker_email'] ?? '' );
        $baby_seat_count    = min( 4, absint( $_POST['etb_baby_seat_count'] ?? 0 ) );
        $booster_seat_count = min( 4, absint( $_POST['etb_booster_seat_count'] ?? 0 ) );
        $total_child_seats  = $baby_seat_count + $booster_seat_count;
        $notes              = sanitize_textarea_field( $_POST['etb_notes'] ?? '' );
        $cost_center        = sanitize_text_field( $_POST['etb_cost_center'] ?? '' );

        // Calcul d'urgence serveur infaillible (< min_delay_hours)
        $gen_settings    = get_option( 'etb_general_settings', array() );
        $min_delay_hours = isset( $gen_settings['min_delay'] ) ? absint( $gen_settings['min_delay'] ) : 24;
        
        $pickup_ts = strtotime( $date . ' ' . trim( $time ) );
        if ( ! $pickup_ts ) {
            $pickup_ts = strtotime( $date . ' ' . substr( trim( $time ), 0, 5 ) . ':00' );
        }
        $now_ts    = current_time( 'timestamp' );
        $is_urgent = ( $pickup_ts && ( $pickup_ts - $now_ts ) < ( $min_delay_hours * HOUR_IN_SECONDS ) );

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

        // 4. Détection blindée du mode devis (Custom Quote)
        $raw_price_clean = trim( (string) $raw_price );
        $is_quote_ride   = ( empty( $raw_price_clean ) 
            || '0' === $raw_price_clean 
            || floatval( $raw_price_clean ) <= 0 
            || false !== stripos( $raw_price_clean, 'quote' ) );
        $final_price     = 0.0;

        if ( ! $is_quote_ride ) {
            $final_price = floatval( $raw_price );
            // Recalcul de sécurité pour le mode horaire
            if ( 'hourly' === $mode && class_exists( 'ETB_Pricing_Engine' ) ) {
                $final_price = ETB_Pricing_Engine::calculate_vehicle_price( $vehicle_id, $duration );
            }
        }

      

        // Ajout du pourboire au total final
        $grand_total_with_tip = $final_price + $tip_amount;

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
        update_post_meta( $booking_id, '_etb_vehicles', array( $vehicle_id => 1 ) );
        update_post_meta( $booking_id, '_etb_base_price', $final_price );
        update_post_meta( $booking_id, '_etb_tip_amount', $tip_amount );
        update_post_meta( $booking_id, '_etb_tip_percentage', $tip_percentage );
        update_post_meta( $booking_id, '_etb_total_price', $grand_total_with_tip );
        update_post_meta( $booking_id, '_etb_is_quote', $is_quote_ride ? '1' : '0' );

        // Mention du pourboire dans la note chauffeur LimoExpress
        $tip_driver_note = '';
        if ( $tip_amount > 0 ) {
            $tip_driver_note = sprintf( "\n💸 POURBOIRE CHAUFFEUR INCLUS : %s € (%d%%)", number_format_i18n( $tip_amount, 2 ), $tip_percentage );
        }


        // Métadonnées exclusives Blacklane
        update_post_meta( $booking_id, '_etb_flight_number', $flight_number );
        update_post_meta( $booking_id, '_etb_waiting_board_text', $pickup_sign ?: $full_name );
        update_post_meta( $booking_id, '_etb_booker_type', $booker_type );
        update_post_meta( $booking_id, '_etb_booker_name', $booker_name );
        update_post_meta( $booking_id, '_etb_booker_email', $booker_email );
        update_post_meta( $booking_id, '_etb_baby_seat_count', $baby_seat_count );
        update_post_meta( $booking_id, '_etb_booster_seat_count', $booster_seat_count );
        update_post_meta( $booking_id, '_etb_total_child_seats', $total_child_seats );
        update_post_meta( $booking_id, '_etb_cost_center', $cost_center );

        // Mention détaillée des sièges pour le chauffeur et le dispatch
        $seats_detail_note = '';
        if ( $baby_seat_count > 0 || $booster_seat_count > 0 ) {
            $seats_list = array();
            if ( $baby_seat_count > 0 ) {
                $seats_list[] = sprintf( '%d Siège(s) Bébé (0-2 ans)', $baby_seat_count );
            }
            if ( $booster_seat_count > 0 ) {
                $seats_list[] = sprintf( '%d Rehausseur(s) / Booster (3-10 ans)', $booster_seat_count );
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
                'receipt_number' => $intent_id,  // Remplira "Numéro du reçu"
                'remark'         => $stripe_url, // Remplira "Remarque" avec le lien direct
               'paid_at'        => wp_date( 'Y-m-d H:i:s' ),
            );
            $is_paid_status = true;

            // Sauvegarde de l'ID Stripe dans WordPress
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

        if ( ! empty( $is_urgent ) && ! $is_quote_ride ) {
            // ─────────────────────────────────────────────────────────────
            // E-MAIL 1 : RÉSERVATION URGENTE (< 24H) - EN ATTENTE DISPONIBILITÉ
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
                . '<p style="margin: 0; font-size: 13px; color: #78350f;">Our dispatch team is immediately verifying chauffeur allocation for your pickup time (<strong>' . esc_html( $date ) . ' at ' . esc_html( $time ) . '</strong>). You will receive final confirmation and your payment link within <strong>1 hour</strong>.</p>'
                . '</div>'
                . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Vehicle:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $vehicle_title ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Pickup:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Drop-off:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( ( 'hourly' === $mode ) ? sprintf( 'By the hour (%sh)', $duration ) : $dropoff ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $date ) . ' at ' . esc_html( $time ) . '</td></tr>'
                . ( $flight_number ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Flight:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $flight_number ) . '</td></tr>' : '' )
                . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Total Amount:</td><td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 800; color: #e65a15;">' . number_format_i18n( $grand_total_with_tip, 2 ) . ' ' . esc_html( $currency_symbol ) . '</td></tr>'
                . '</table>'
                . '<p style="font-size: 12px; color: #64748b; margin: 0;">No charge will be made until chauffeur availability is formally secured.</p>'
                . '</div>'
                . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
                . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — Cannes, Côte d\'Azur, France</p>'
                . '</div>'
                . '</div>'
                . '</div>';

            

            // Alerte e-mail administrateur pour l'urgence (Sujet propre sans entités HTML)
            $subject_admin  = '🚨 [URGENT < 24H] Nouvelle course à valider #' . $booking_id . ' - ' . $clean_name;
            $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
            $wa_link        = 'https://api.whatsapp.com/send?phone=' . preg_replace( '/[^0-9]/', '', $phone );

            $message_admin = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
                . '<div style="background: #991b1b; padding: 22px 28px; text-align: left;">'
                . '<span style="background: rgba(255,255,255,0.2); color: #ffffff; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.08em; display: inline-block; margin-bottom: 6px;">Action Requise</span>'
                . '<h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 800;">🚨 Course urgente planifiée sous 24h</h1>'
                . '<p style="margin: 4px 0 0 0; color: #fecaca; font-size: 12px;">Dossier #' . $booking_id . ' — Prise en charge dans moins de 24h</p>'
                . '</div>'
                . '<div style="padding: 28px;">'
                
                // Bloc Client avec actions rapides
                . '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 22px;">'
                . '<strong style="font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">👤 Contact Prospect</strong>'
                . '<p style="margin: 2px 0; font-size: 15px; font-weight: 700; color: #0f172a;">' . esc_html( $full_name ) . '</p>'
                . '<p style="margin: 2px 0; font-size: 13px; color: #475569;">✉️ <a href="mailto:' . esc_attr( $email ) . '" style="color: #0284c7; text-decoration: none;">' . esc_html( $email ) . '</a></p>'
                . '<p style="margin: 2px 0; font-size: 13px; color: #475569;">📞 <a href="tel:' . esc_attr( $phone ) . '" style="color: #0284c7; text-decoration: none;">' . esc_html( $phone ) . '</a></p>'
                . '<div style="margin-top: 10px; display: flex; gap: 8px;">'
                . '<a href="' . esc_url( $wa_link ) . '" target="_blank" style="background: #22c55e; color: #fff; padding: 6px 14px; border-radius: 50px; text-decoration: none; font-size: 12px; font-weight: 700; display: inline-block;">💬 Ouvrir WhatsApp</a>'
                . '</div>'
                . '</div>'

                // Tableau Mission
                . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Départ :</td><td style="padding: 9px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Arrivée :</td><td style="padding: 9px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( ( 'hourly' === $mode ) ? sprintf( 'À l\'heure (%sh)', $duration ) : $dropoff ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Date & Heure :</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #991b1b;">' . esc_html( $date ) . ' à ' . esc_html( $time ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 9px 0; color: #64748b;">Véhicule :</td><td style="padding: 9px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $vehicle_title ) . '</td></tr>'
                . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Montant total :</td><td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 800; color: #ea580c;">' . number_format_i18n( $grand_total_with_tip, 2 ) . ' ' . esc_html( $currency_symbol ) . '</td></tr>'
                . '</table>'

                // Boutons d'action pour le régulateur
                . '<div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 16px; margin-bottom: 22px;">'
                . '<strong style="font-size: 13px; color: #92400e; display: block; margin-bottom: 6px;">👉 Consigne régulation :</strong>'
                . '<p style="margin: 0 0 10px 0; font-size: 12.5px; color: #78350f;">Vérifiez la disponibilité dans LimoExpress. Si disponible, transmettez ce lien de règlement direct au client :</p>'
                . '<a href="' . esc_url( $payment_link_url ) . '" style="background: #fbac18; color: #0f172a; padding: 10px 20px; border-radius: 50px; text-decoration: none; font-size: 13px; font-weight: 800; display: inline-block;">🔗 Ouvrir le lien de paiement ➔</a>'
                . '</div>'

                . '<div style="text-align: center;">'
                . '<a href="' . esc_url( $admin_edit_url ) . '" style="color: #64748b; font-size: 12px; text-decoration: underline;">Consulter le dossier #' . $booking_id . ' dans WordPress</a>'
                . '</div>'

                . '</div>'
                . '</div>'
                . '</div>';

            wp_mail( $email, $subject_client, $message_client, $headers_client );
            wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );

        } elseif ( $is_quote_ride ) {
            // ─────────────────────────────────────────────────────────────
            // E-MAIL 2 : DEMANDE DE DEVIS SUR MESURE (DESIGN VIP)
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
                . '<p style="font-size: 14px; margin-bottom: 20px;">Thank you for your inquiry. Our dispatch regulation is currently calculating your tailored quotation (route, tolls, and chauffeur schedule).</p>'
                . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Vehicle:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $vehicle_title ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Pickup:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Drop-off:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( ( 'hourly' === $mode ) ? sprintf( 'By the hour (%sh)', $duration ) : $dropoff ) . '</td></tr>'
                . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $date ) . ' at ' . esc_html( $time ) . '</td></tr>'
                . ( $flight_number ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Flight:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $flight_number ) . '</td></tr>' : '' )
                . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Estimated Rate:</td><td style="padding: 12px 0; text-align: right; font-size: 16px; font-weight: 800; color: #d97706;">Custom Quote (Being calculated)</td></tr>'
                . '</table>'
                . '<div style="background: #f8fafc; border-left: 4px solid #fbac18; padding: 14px 16px; border-radius: 6px; margin-bottom: 20px;">'
                . '<p style="margin: 0; font-size: 13px; color: #334155;">Our team will transmit your final quotation with a direct settlement link within <strong>1 hour</strong>.</p>'
                . '</div>'
                . '</div>'
                . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
                . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — Cannes, Côte d\'Azur, France</p>'
                . '</div>'
                . '</div>'
                . '</div>';

            // Alerte Admin
            $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
            $subject_admin  = '🚨 [NOUVEAU DEVIS] Dossier #' . $booking_id . ' - ' . $clean_name;
            $message_admin  = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
                . '<div style="background: #9a3412; padding: 22px 28px;">'
                . '<h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 800;">🚨 Nouveau devis sur mesure</h1>'
                . '<p style="margin: 4px 0 0 0; color: #fed7aa; font-size: 12px;">Dossier #' . $booking_id . ' — Transmis à LimoExpress</p>'
                . '</div>'
                . '<div style="padding: 28px;">'
                . '<p style="margin: 4px 0;"><strong>Client :</strong> ' . esc_html( $full_name ) . ' (<a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a> | ' . esc_html( $email ) . ')</p>'
                . '<p style="margin: 4px 0;"><strong>Véhicule :</strong> ' . esc_html( $vehicle_title ) . '</p>'
                . '<p style="margin: 4px 0;"><strong>Trajet :</strong> ' . esc_html( $pickup ) . ' ➔ ' . esc_html( $dropoff ) . '</p>'
                . '<p style="margin: 4px 0;"><strong>Date & Heure :</strong> ' . esc_html( $date ) . ' à ' . esc_html( $time ) . '</p>'
                . '<div style="margin-top: 20px;">'
                . '<a href="' . esc_url( $admin_edit_url ) . '" style="background: #0f172a; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px; display: inline-block;">Voir dans WordPress</a>'
                . '</div>'
                . '</div>'
                . '</div>'
                . '</div>';

            wp_mail( $email, $subject_client, $message_client, $headers_client );
            wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );

        } else {
            // ─────────────────────────────────────────────────────────────
            // E-MAIL 3 : OPTION PAY LATER (DESIGN VIP AVEC BOUTON DORÉ)
            // ─────────────────────────────────────────────────────────────
            $is_pay_later = ! empty( $_POST['etb_pay_later'] );

            if ( $is_pay_later ) {
            $subject_client = 'Complete Your VIP Reservation #' . $booking_id . ' — ' . $company_name;
                $message_client = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                    . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">'
                    . '<div style="background: #0f172a; padding: 25px 30px;">'
                    . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">' . esc_html( $company_name ) . '</h1>'
                    . '<p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em;">Superior Drive -- Order #' . $booking_id . ' (Payment Pending)</p>'
                    . '</div>'
                    . '<div style="padding: 30px;">'
                    . '<p style="font-size: 15px; margin-top: 0; margin-bottom: 16px;">Dear <strong>' . esc_html( $full_name ) . '</strong>,</p>'
                    . '<p style="font-size: 14px; margin-bottom: 20px;">Thank you for booking with us. Your transfer dossier has been registered and is currently awaiting online settlement.</p>'
                    . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13.5px;">'
                    . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Vehicle:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $vehicle_title ) . '</td></tr>'
                    . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Pickup:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( $pickup ) . '</td></tr>'
                    . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Drop-off:</td><td style="padding: 10px 0; text-align: right; font-weight: 600; color: #0f172a;">' . esc_html( ( 'hourly' === $mode ) ? sprintf( 'By the hour (%sh)', $duration ) : $dropoff ) . '</td></tr>'
                    . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $date ) . ' at ' . esc_html( $time ) . '</td></tr>'
                    . ( $flight_number ? '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Flight:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $flight_number ) . '</td></tr>' : '' )
                    . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Total to Pay:</td><td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 800; color: #e65a15;">' . number_format_i18n( $grand_total_with_tip, 2 ) . ' ' . esc_html( $currency_symbol ) . '</td></tr>'
                    . '</table>'
                    . '<div style="text-align: center; margin: 26px 0 16px 0;">'
                    . '<a href="' . esc_url( $payment_link_url ) . '" style="background: #fbac18; color: #0f172a; padding: 14px 32px; text-decoration: none; border-radius: 50px; font-weight: 800; font-size: 14px; display: inline-block;">Proceed to Payment to Confirm ➔</a>'
                    . '</div>'
                    . '<p style="font-size: 12px; color: #64748b; text-align: center; margin-bottom: 0;">You can complete your payment online anytime prior to pickup.</p>'
                    . '</div>'
                    . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
                    . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — Cannes, Côte d\'Azur, France</p>'
                    . '</div>'
                    . '</div>'
                    . '</div>';

                // Alerte Administrateur pour Pay Later
                $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
                $subject_admin  = '⏳ [PAY LATER] Nouvelle réservation en attente de règlement #' . $booking_id . ' - ' . $clean_name;
                $message_admin  = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                    . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
                    . '<div style="background: #0f172a; padding: 22px 28px;">'
                    . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800;">⏳ Réservation Pay Later enregistrée</h1>'
                    . '<p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 12px;">Dossier #' . $booking_id . ' — Transmis à LimoExpress</p>'
                    . '</div>'
                    . '<div style="padding: 28px;">'
                    . '<p style="margin: 4px 0;"><strong>Client :</strong> ' . esc_html( $full_name ) . ' (<a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a> | ' . esc_html( $email ) . ')</p>'
                    . '<p style="margin: 4px 0;"><strong>Véhicule :</strong> ' . esc_html( $vehicle_title ) . '</p>'
                    . '<p style="margin: 4px 0;"><strong>Trajet :</strong> ' . esc_html( $pickup ) . ' ➔ ' . esc_html( ( 'hourly' === $mode ) ? sprintf( 'À l\'heure (%sh)', $duration ) : $dropoff ) . '</p>'
                    . '<p style="margin: 4px 0;"><strong>Date & Heure :</strong> ' . esc_html( $date ) . ' à ' . esc_html( $time ) . '</p>'
                    . '<p style="margin: 4px 0;"><strong>Montant à régler :</strong> ' . number_format_i18n( $grand_total_with_tip, 2 ) . ' ' . esc_html( $currency_symbol ) . '</p>'
                    . '<div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px 14px; margin: 16px 0;">'
                    . '<p style="margin: 0; font-size: 12px; color: #92400e;">Le client a reçu son lien de paiement. S\'il le demande sur WhatsApp, vous pouvez lui renvoyer :</p>'
                    . '<a href="' . esc_url( $payment_link_url ) . '" style="color: #ea580c; font-weight: bold; word-break: break-all; font-size: 12px;">' . esc_html( $payment_link_url ) . '</a>'
                    . '</div>'
                    . '<div style="margin-top: 15px;">'
                    . '<a href="' . esc_url( $admin_edit_url ) . '" style="background: #0f172a; color: #ffffff; padding: 10px 18px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 13px; display: inline-block;">Voir dans WordPress</a>'
                    . '</div>'
                    . '</div>'
                    . '</div>'
                    . '</div>';

                // En-têtes officiels stricts pour le Pay Later
                $headers_client = ETB_Settings::get_mail_headers( $admin_email, $company_name );
                $headers_admin  = ETB_Settings::get_mail_headers( $email, $full_name );

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
    public function handle_settle_quote_payment() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        $amount     = floatval( $_POST['amount'] ?? 0.0 );
        $intent_id  = sanitize_text_field( $_POST['payment_intent_id'] ?? '' );
        $card_last4 = sanitize_text_field( $_POST['card_last4'] ?? '4242' );
        $card_brand = sanitize_text_field( $_POST['card_brand'] ?? 'card' );
        $card_exp   = sanitize_text_field( $_POST['card_exp'] ?? '' );

        if ( ! $booking_id || get_post_type( $booking_id ) !== 'tour_booking' ) {
            wp_send_json_error( array( 'message' => 'Invalid booking reference.' ) );
        }

        if ( $amount <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid payment amount.' ) );
        }

        // 1. Récupération des réglages et configuration Stripe
        $gen_settings = get_option( 'etb_general_settings', array() );
        $stripe_mode  = ( isset( $gen_settings['stripe_mode'] ) && 'live' === $gen_settings['stripe_mode'] ) ? 'live' : 'test';
        $stripe_url   = ! empty( $intent_id )
            ? ( 'live' === $stripe_mode 
                ? 'https://dashboard.stripe.com/payments/' . $intent_id 
                : 'https://dashboard.stripe.com/test/payments/' . $intent_id )
            : '';

        // 2. Mise à jour de la réservation WordPress
        update_post_meta( $booking_id, '_etb_status', 'confirmed' );
        update_post_meta( $booking_id, '_etb_total_price', $amount );
        update_post_meta( $booking_id, '_etb_stripe_payment_intent_id', $intent_id );
        update_post_meta( $booking_id, '_etb_paid_at', current_time( 'mysql' ) );

        // Construction du log de paiement local
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

        // 3. Appel de l'API LimoExpress officielle (Mise à jour en PAID et CONFIRMED via UUID)
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

        // L'UUID officiel LimoExpress est obligatoire pour les actions de statut
        $target_uuid = ! empty( $limo_uuid ) ? $limo_uuid : $limo_id;

        if ( ! empty( $limo_token ) && ! empty( $target_uuid ) ) {
            $headers = array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $limo_token,
            );

            // A. Action 1 : Marquer la course comme PAYÉE (POST /mark-booking-as-paid)
            $paid_res = wp_remote_post( 'https://api.limoexpress.me/api/integration/mark-booking-as-paid', array(
                'headers' => $headers,
                'body'    => wp_json_encode( array( 'id' => (string) $target_uuid ) ),
                'timeout' => 15,
            ) );

            // B. Action 2 : Marquer la course comme CONFIRMÉE (POST /mark-booking-as-confirmed)
            $conf_res = wp_remote_post( 'https://api.limoexpress.me/api/integration/mark-booking-as-confirmed', array(
                'headers' => $headers,
                'body'    => wp_json_encode( array( 'id' => (string) $target_uuid ) ),
                'timeout' => 15,
            ) );

            $paid_code = ! is_wp_error( $paid_res ) ? wp_remote_retrieve_response_code( $paid_res ) : 500;
            $conf_code = ! is_wp_error( $conf_res ) ? wp_remote_retrieve_response_code( $conf_res ) : 500;

            if ( $paid_code >= 200 && $paid_code < 300 ) {
                $limo_synced = true;
                update_post_meta( $booking_id, '_etb_limo_paid_synced', '1' );

                // C. Action 3 : Mise à jour de la note répartiteur avec le tampon de paiement officiel Stripe
                $paid_stamp = "\n═ ✅ PAIEMENT ENCAISSÉ EN LIGNE ═\n"
                    . "💳 Montant réglé : " . number_format( $amount, 2 ) . " €\n"
                    . "💳 Moyen : " . ucfirst( $card_brand ) . " •••• " . substr( $card_last4, -4 ) . "\n"
                    . "📅 Encaissé le : " . current_time( 'd/m/Y à H:i' ) . "\n";
                if ( ! empty( $stripe_url ) ) {
                    $paid_stamp .= "🔗 LIEN STRIPE DIRECT :\n" . $stripe_url . "\n";
                }
                $paid_stamp .= "════════════\n\n";

                // Récupération des données minimales requises par POST /api/integration/bookings
                $pickup_addr  = get_post_meta( $booking_id, '_etb_pickup_address', true ) ?: 'Non renseigné';
                $dropoff_addr = get_post_meta( $booking_id, '_etb_dropoff_info', true ) ?: $pickup_addr;
                $booking_dt   = get_post_meta( $booking_id, '_etb_booking_date', true );
                $booking_tm   = get_post_meta( $booking_id, '_etb_booking_time', true ) ?: '09:00';
                $pickup_iso   = sprintf( '%s %s:00', $booking_dt, substr( $booking_tm, 0, 5 ) );
                $type_id      = $gen_settings['limo_booking_type_id'] ?? '';

                // On récupère la NOTE INTACTE depuis LimoExpress !
                $limo_check    = ETB_LimoExpress::get_booking_details( $target_uuid, $booking_id );
                $existing_note = ! empty( $limo_check['limo_note'] ) ? $limo_check['limo_note'] : ( get_post_meta( $booking_id, '_etb_note', true ) ?: '' );

                // On efface l'ancien bandeau temporaire (Pay Later, Devis ou Course urgente) pour ne pas polluer la note finale
                $existing_note = preg_replace( '/═\s*[⏳🚨].*?═.*?═{10,}[\r\n\s]*/us', '', $existing_note );

                $update_payload = array(
                    'id'              => (string) $target_uuid,
                    'booking_type_id' => ! empty( $type_id ) ? (string) $type_id : 'default',
                    'from_location'   => array( 'name' => $pickup_addr ),
                    'to_location'     => array( 'name' => $dropoff_addr ),
                    'pickup_time'     => $pickup_iso,
                    'note'            => $paid_stamp . trim( $existing_note ),
                    'paid'            => true,
                    'confirmed'       => true,
                );

                wp_remote_post( 'https://api.limoexpress.me/api/integration/bookings', array(
                    'headers' => $headers,
                    'body'    => wp_json_encode( $update_payload ),
                    'timeout' => 15,
                ) );
                    

            } else {
                $err_msg = ! is_wp_error( $paid_res ) ? wp_remote_retrieve_body( $paid_res ) : $paid_res->get_error_message();
                update_post_meta( $booking_id, '_etb_limo_paid_error', $err_msg );
            }
        }

        // 4. Envoi du reçu officiel par e-mail au client
        $customer_email = get_post_meta( $booking_id, '_etb_customer_email', true );
        $customer_name  = get_post_meta( $booking_id, '_etb_customer_name', true ) ?: 'Client';
        $company_name   = get_bloginfo( 'name' );
        $currency_sym   = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';

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
                . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — Cannes, Côte d\'Azur, France</p>'
                . '</div>'
                . '</div>'
                . '</div>';

            $admin_email    = ! empty( $gen_settings['admin_email'] ) && is_email( $gen_settings['admin_email'] ) ? sanitize_email( $gen_settings['admin_email'] ) : get_option( 'admin_email' );
            $headers_client = ETB_Settings::get_mail_headers( $admin_email, $company_name );

         wp_mail( $customer_email, $subject, $message, $headers_client );
        }

        // 5. Alerte d'encaissement instantanée pour l'administrateur
        if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
            $subject_admin  = sprintf( '💰 [ENCAISSEMENT RÉUSSI] %s € reçus pour le Dossier #%d — %s', number_format( $amount, 2 ), $booking_id, $customer_name );
            $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
            $headers_admin  = ETB_Settings::get_mail_headers( $customer_email, $customer_name );

            $message_admin  = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
                . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">'
                . '<div style="background: #15803d; padding: 22px 28px;">'
                . '<span style="background: rgba(255,255,255,0.2); color: #ffffff; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.08em; display: inline-block; margin-bottom: 6px;">Paiement Validé</span>'
                . '<h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 800;">💰 Règlement encaissé en ligne</h1>'
                . '<p style="margin: 4px 0 0 0; color: #bbf7d0; font-size: 12px;">Dossier #' . $booking_id . ' — Régularisation en ligne</p>'
                . '</div>'
                . '<div style="padding: 28px;">'
                . '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 22px;">'
                . '<p style="margin: 0; font-size: 13px; color: #166534;">Montant total réglé :</p>'
                . '<p style="margin: 2px 0 0 0; font-size: 26px; font-weight: 900; color: #15803d;">' . number_format( $amount, 2 ) . ' ' . esc_html( $currency_sym ) . '</p>'
                . '<p style="margin: 6px 0 0 0; font-size: 12px; color: #166534;">Carte : ' . ucfirst( esc_html( $card_brand ) ) . ' •••• ' . esc_html( substr( $card_last4, -4 ) ) . ' (Exp: ' . esc_html( $card_exp ) . ')</p>'
                . '</div>'
                . '<p style="margin: 4px 0;"><strong>Client :</strong> ' . esc_html( $customer_name ) . ' (<a href="mailto:' . esc_attr( $customer_email ) . '">' . esc_html( $customer_email ) . '</a>)</p>'
                . '<p style="margin: 4px 0;"><strong>ID Stripe :</strong> <code style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">' . esc_html( $intent_id ) . '</code></p>'
                . ( ! empty( $stripe_url ) ? '<p style="margin: 12px 0;"><a href="' . esc_url( $stripe_url ) . '" target="_blank" style="background: #635bff; color: #ffffff; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 12px; display: inline-block;">🔗 Voir la transaction sur Stripe Dashboard</a></p>' : '' )
                . '<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">'
                . '<div style="text-align: center;">'
                . '<a href="' . esc_url( $admin_edit_url ) . '" style="color: #64748b; font-size: 12px; text-decoration: underline;">Consulter la réservation #' . $booking_id . ' dans WordPress</a>'
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
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $date ) . ' at ' . esc_html( $time ) . '</td></tr>'
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