<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Ajax {
    public function __construct() {
        add_action( 'wp_ajax_etb_submit_booking', array( $this, 'handle_submit_booking' ) );
        add_action( 'wp_ajax_nopriv_etb_submit_booking', array( $this, 'handle_submit_booking' ) );
        add_action( 'wp_ajax_etb_validate_promo', array( $this, 'handle_validate_promo' ) );
        add_action( 'wp_ajax_nopriv_etb_validate_promo', array( $this, 'handle_validate_promo' ) );

        add_action( 'wp_ajax_etb_resync_booking', array( $this, 'handle_resync_booking' ) );
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

            
        // 1. Transmission préalable à LimoExpress pour évaluer le statut
        $limo_synced = false;
        $limo_err    = '';
        if ( class_exists( 'ETB_LimoExpress' ) ) {
            $limo_synced = ETB_LimoExpress::send_booking( $booking_id, $data );
            if ( ! $limo_synced ) {
                $limo_err = get_post_meta( $booking_id, '_etb_limo_error', true );
            }
        }

        // 2. Alerte e-mail administrateur si le transfert LimoExpress a échoué
        if ( ! $limo_synced && ! empty( $limo_err ) ) {
            $subject_admin = '[Action Requise - Échec LimoExpress] Dossier #' . $booking_id . ' - ' . $data['name'];
            $alert_box     = '<div style="background:#fee2e2; border-left:4px solid #dc2626; padding:12px; margin-bottom:15px; color:#991b1b;">'
                . '<strong>⚠️ ATTENTION : La synchronisation automatique vers LimoExpress a échoué.</strong><br>'
                . 'Motif : ' . esc_html( $limo_err ) . '<br>'
                . '👉 <em>Vous pouvez relancer le transfert en un clic depuis WordPress via le bouton "Transférer vers LimoExpress".</em>'
                . '</div>';
            $message_admin = $alert_box . $message_admin;
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

        if ( ! class_exists( 'ETB_LimoExpress' ) ) {
            wp_send_json_error( array( 'message' => 'Module LimoExpress introuvable.' ) );
        }

        $success = ETB_LimoExpress::send_booking( $booking_id, $data );

        if ( $success ) {
            $limo_id      = get_post_meta( $booking_id, '_etb_limo_booking_id', true );
            $client_debug = get_post_meta( $booking_id, '_etb_limo_client_debug', true );
            wp_send_json_success( array( 
                'limo_id'      => $limo_id,
                'client_debug' => $client_debug,
            ) );
        } else {
            $error_msg = get_post_meta( $booking_id, '_etb_limo_error', true ) ?: 'Erreur de communication API';
            wp_send_json_error( array( 'message' => $error_msg ) );
        }
    }
}