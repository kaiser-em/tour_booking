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
        add_action( 'wp_ajax_etb_submit_checkout', array( $this, 'handle_submit_checkout' ) );
        add_action( 'wp_ajax_nopriv_etb_submit_checkout', array( $this, 'handle_submit_checkout' ) );

        /// Route AJAX pour générer un jeton de devis scellé (Quote Token Handoff)
        add_action( 'wp_ajax_etb_create_quote', array( $this, 'handle_create_quote' ) );
        add_action( 'wp_ajax_nopriv_etb_create_quote', array( $this, 'handle_create_quote' ) );

        // Route AJAX pour initialiser le paiement ou l'empreinte bancaire Stripe
        add_action( 'wp_ajax_etb_create_payment_intent', array( $this, 'handle_create_payment_intent' ) );
        add_action( 'wp_ajax_nopriv_etb_create_payment_intent', array( $this, 'handle_create_payment_intent' ) );
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
        if ( $data['luggage'] > $total_capacity_baggage ) {
            wp_send_json_error( array( 'message' => 'Le nombre de bagages dépasse la capacité des véhicules sélectionnés.' ) );
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
        $note_html = ! empty( $data['note'] ) ? '<h3>Demande spéciale :</h3><p>' . nl2br( esc_html( $data['note'] ) ) . '</p>' : '';

        // Préparation du numéro de téléphone pour l'affichage
        $phone_display = ! empty( $data['phone'] ) ? esc_html( $data['phone'] ) : 'Non renseigné';

        // Durcissement de l'en-tête Reply-To (filtrage des caractères de contrôle SMTP)
        $clean_name = preg_replace( '/[^\p{L}\p{N}\s\-\.]/u', '', $data['name'] );
        $clean_name = trim( preg_replace( '/\s+/', ' ', $clean_name ) );

        $headers_client = array( 'Content-Type: text/html; charset=UTF-8' );
        $headers_admin  = array(
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $clean_name . ' <' . $data['email'] . '>',
        );

        $subject_client = 'Confirmation de votre demande de réservation #' . $booking_id;
        $message_client = '<h2>Bonjour ' . esc_html( $data['name'] ) . ',</h2>'
            . '<p>Votre demande de réservation a bien été enregistrée sous le dossier <strong>#' . $booking_id . '</strong>.</p>'
            . '<hr>'
            . '<h3>Détails de la réservation :</h3>'
            . '<ul>'
            . '<li><strong>Prestation :</strong> ' . esc_html( $prestation_label ) . '</li>'
            . '<li><strong>Point de départ :</strong> ' . esc_html( $pickup_name ) . '</li>'
            . '<li><strong>Point d\'arrivée :</strong> ' . esc_html( empty( $data['dropoff_info'] ) ? 'Identique au point de départ' : $data['dropoff_info'] ) . '</li>'
            . '<li><strong>Date et Heure :</strong> ' . esc_html( $data['date'] ) . ' à ' . esc_html( $data['time'] ) . '</li>'
            . '<li><strong>Téléphone :</strong> ' . $phone_display . '</li>'
            . '<li><strong>Passagers :</strong> ' . $data['adults'] . ' adulte(s), ' . $data['children'] . ' enfant(s) (Total : ' . ( $data['adults'] + $data['children'] ) . ')</li>'
            . '<li><strong>Bagages :</strong> ' . $data['luggage'] . '</li>'
            . '</ul>'
            . '<h3>Véhicule(s) réservé(s) :</h3><ul>' . $vehicles_list . '</ul>'
            . ( $extras_list ? '<h3>Option(s) / Extra(s) :</h3><ul>' . $extras_list . '</ul>' : '' )
            . $promo_html . $note_html
            . '<hr><p><strong>Montant estimé :</strong> ' . $formatted_total . '</p>';

        $subject_admin  = '[Nouvelle Réservation] Dossier #' . $booking_id . ' - ' . $data['name'];
        $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
        $message_admin  = '<h2>Nouvelle demande de réservation (Dossier #' . $booking_id . ')</h2>'
            . '<p><strong>Client :</strong> ' . esc_html( $data['name'] ) . ' (' . esc_html( $data['email'] ) . ')</p>'
            . '<hr>'
            . '<h3>Détails de la réservation :</h3>'
            . '<ul>'
            . '<li><strong>Prestation :</strong> ' . esc_html( $prestation_label ) . '</li>'
            . '<li><strong>Point de départ :</strong> ' . esc_html( $pickup_name ) . '</li>'
            . '<li><strong>Point d\'arrivée :</strong> ' . esc_html( empty( $data['dropoff_info'] ) ? 'Identique au point de départ' : $data['dropoff_info'] ) . '</li>'
            . '<li><strong>Date et Heure :</strong> ' . esc_html( $data['date'] ) . ' à ' . esc_html( $data['time'] ) . '</li>'
            . '<li><strong>Téléphone :</strong> ' . $phone_display . '</li>'
            . '<li><strong>Passagers :</strong> ' . $data['adults'] . ' adulte(s), ' . $data['children'] . ' enfant(s) (Total : ' . ( $data['adults'] + $data['children'] ) . ')</li>'
            . '<li><strong>Bagages :</strong> ' . $data['luggage'] . '</li>'
            . '</ul>'
            . '<h3>Véhicule(s) :</h3><ul>' . $vehicles_list . '</ul>'
            . ( $extras_list ? '<h3>Option(s) / Extra(s) :</h3><ul>' . $extras_list . '</ul>' : '' )
            . $promo_html . $note_html
            . '<hr><p><strong>Montant Total :</strong> ' . $formatted_total . '</p>'
            . '<hr><p><a href="' . esc_url( $admin_edit_url ) . '" style="display:inline-block; padding:10px 15px; background:#0073aa; color:#fff; text-decoration:none; border-radius:3px;">Consulter le dossier dans WordPress</a></p>';

            
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

        // Durcissement Reply-To contre les injections SMTP
        $clean_name = preg_replace( '/[^\p{L}\p{N}\s\-\.]/u', '', $name );
        $clean_name = trim( preg_replace( '/\s+/', ' ', $clean_name ) );

        $headers_admin = array(
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $clean_name . ' <' . $email . '>',
        );

        $headers_client = array(
            'Content-Type: text/html; charset=UTF-8',
        );

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
            . '<p>Our dispatch team is currently reviewing your request and will provide you with a tailored confirmation within <strong>15 minutes</strong>.</p>'
            . '<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">'
            . '<p style="font-size: 12px; color: #64748b;">Best regards,<br><strong>' . esc_html( $company_name ) . ' Reservations Team</strong></p>'
            . '</div>'
            . '</div>';

        // 6. Expédition des e-mails
        wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );
        wp_mail( $email, $subject_client, $message_client, $headers_client );

        wp_send_json_success( array(
            'message' => 'Thank you! Your inquiry has been submitted. Our dispatch team will get back to you within 15 minutes.'
        ) );
    }

    /**
     * Traitement AJAX : Soumission du Checkout Blacklane ([etb_checkout])
     */
    public function handle_submit_checkout() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        // 1. Contrôle Anti-Spam (Honeypot + Rate Limiting)
        if ( ! ETB_Security::verify_honeypot( 'etb_hp_email' ) ) {
            wp_send_json_error( array( 'message' => 'Security validation failed.' ) );
        }

        if ( ! ETB_Security::check_rate_limit( 'booking', 10, 600 ) ) {
            wp_send_json_error( array( 'message' => 'Too many booking requests. Please wait a few minutes.' ) );
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
        $booker_name     = sanitize_text_field( $_POST['etb_booker_name'] ?? '' );
        $booker_email    = sanitize_email( $_POST['etb_booker_email'] ?? '' );
        $baby_seat_count = min( 4, absint( $_POST['etb_baby_seat_count'] ?? 0 ) );
        $notes           = sanitize_textarea_field( $_POST['etb_notes'] ?? '' );
        $cost_center     = sanitize_text_field( $_POST['etb_cost_center'] ?? '' );

        // Nouveautés : Passagers, Bagages et Pourboire chauffeur
        $passengers_count = max( 1, absint( $_POST['etb_passengers_count'] ?? 1 ) );
        $luggage_count    = max( 0, absint( $_POST['etb_luggage_count'] ?? 0 ) );
        $tip_percentage   = max( 0, absint( $_POST['etb_driver_tip'] ?? 0 ) );
        $tip_amount       = max( 0.0, floatval( $_POST['etb_tip_amount'] ?? 0.0 ) );

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

        // 4. Calcul / Validation du prix net
        $is_quote_ride = ( 'Custom Quote' === $raw_price || floatval( $raw_price ) <= 0 );
        $final_price   = 0.0;

        if ( ! $is_quote_ride ) {
            $final_price = floatval( $raw_price );
            // Recalcul de sécurité pour le mode horaire
            if ( 'hourly' === $mode && class_exists( 'ETB_Pricing_Engine' ) ) {
                $final_price = ETB_Pricing_Engine::calculate_vehicle_price( $vehicle_id, $duration );
            }
        }

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
        update_post_meta( $booking_id, '_etb_luggage', $luggage_count );
        update_post_meta( $booking_id, '_etb_vehicles', array( $vehicle_id => 1 ) );
        update_post_meta( $booking_id, '_etb_base_price', $final_price );
        update_post_meta( $booking_id, '_etb_tip_amount', $tip_amount );
        update_post_meta( $booking_id, '_etb_tip_percentage', $tip_percentage );
        update_post_meta( $booking_id, '_etb_total_price', $grand_total_with_tip );

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
        update_post_meta( $booking_id, '_etb_cost_center', $cost_center );
        update_post_meta( $booking_id, '_etb_note', $notes . $tip_driver_note );

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

        // 6. Préparation des données et transmission en direct à LimoExpress
        $dispatch_data = array(
            'name'               => $full_name,
            'email'              => $email,
            'phone'              => $phone,
            'date'               => $date,
            'time'               => $time,
            'pickup_address'     => $pickup,
            'dropoff_info'       => ( 'hourly' === $mode ) ? $pickup : $dropoff,
            'vehicles'           => array( $vehicle_id => 1 ),
            'adults'             => $passengers_count,
            'children'           => 0,
            'luggage'            => $luggage_count,
            'baby_seat_count'    => $baby_seat_count,
            'tip_amount'         => $tip_amount,
            'paid'               => $is_paid_status,
            'payment_logs'       => $payment_logs,
            'payment_intent_id'  => $intent_id,
            'note'               => $notes . $tip_driver_note,
            'flight_number'      => $flight_number,
            'waiting_board_text' => $pickup_sign ?: $full_name,
            'cost_center'        => $cost_center,
            'pricing'            => array(
                'grand_total'    => $grand_total_with_tip,
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
        $admin_email     = ! empty( $gen_settings['admin_email'] ) && is_email( $gen_settings['admin_email'] ) 
            ? sanitize_email( $gen_settings['admin_email'] ) 
            : get_option( 'admin_email' );
        $company_name    = get_bloginfo( 'name' );

        $formatted_price = $is_quote_ride ? 'Custom Quote (Pending Dispatch)' : number_format_i18n( $final_price, 2 ) . ' ' . $currency_symbol;

        $subject_client = 'Booking Confirmation #' . $booking_id . ' — ' . $company_name;
        $message_client = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #1e293b; line-height: 1.6;">'
            . '<div style="background: #0f172a; padding: 20px; border-radius: 8px 8px 0 0; color: #ffffff;">'
            . '<h2 style="margin: 0; color: #fbac18;">' . esc_html( $company_name ) . '</h2>'
            . '<p style="margin: 4px 0 0 0; font-size: 13px; color: #94a3b8;">VIP Chauffeur Reservation #' . $booking_id . '</p>'
            . '</div>'
            . '<div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px; border-radius: 0 0 8px 8px; background: #ffffff;">'
            . '<p>Dear <strong>' . esc_html( $full_name ) . '</strong>,</p>'
            . '<p>Your VIP reservation has been received successfully.</p>'
            . '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin: 16px 0;">'
            . '<p style="margin: 4px 0;">🚘 <strong>Vehicle:</strong> ' . esc_html( $vehicle_title ) . '</p>'
            . '<p style="margin: 4px 0;">📍 <strong>Pickup:</strong> ' . esc_html( $pickup ) . '</p>'
            . '<p style="margin: 4px 0;">🏁 <strong>Drop-off / Service:</strong> ' . esc_html( ( 'hourly' === $mode ) ? sprintf( 'By the hour (%sh)', $duration ) : $dropoff ) . '</p>'
            . '<p style="margin: 4px 0;">📅 <strong>Date & Time:</strong> ' . esc_html( $date ) . ' at ' . esc_html( $time ) . '</p>'
            . ( $flight_number ? '<p style="margin: 4px 0;">✈️ <strong>Flight:</strong> ' . esc_html( $flight_number ) . '</p>' : '' )
            . '<p style="margin: 4px 0;">💰 <strong>Total:</strong> <strong>' . esc_html( $formatted_price ) . '</strong></p>'
            . '</div>'
            . '<p>Our dispatch team is assigning your professional chauffeur. You will receive SMS updates prior to pickup.</p>'
            . '</div>'
            . '</div>';

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $email, $subject_client, $message_client, $headers );

        // 8. Réponse JSON de succès
        $limo_id = get_post_meta( $booking_id, '_etb_limo_booking_id', true );
        wp_send_json_success( array(
            'booking_id' => $booking_id,
            'limo_id'    => $limo_id ?: 'SYNCED',
            'message'    => 'Your VIP reservation has been confirmed!',
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

        // 2. Récupération et assainissement des critères de course
        $mode       = sanitize_text_field( $_POST['mode'] ?? 'transfer' );
        $pickup     = sanitize_text_field( $_POST['pickup'] ?? '' );
        $dropoff    = sanitize_text_field( $_POST['dropoff'] ?? '' );
        $duration   = max( 1, floatval( $_POST['duration'] ?? 4 ) );
        $date       = sanitize_text_field( $_POST['date'] ?? '' );
        $time       = sanitize_text_field( $_POST['time'] ?? '' );
        $vehicle_id = absint( $_POST['vehicle_id'] ?? 0 );
        $raw_price  = sanitize_text_field( $_POST['price'] ?? '0' );

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

}