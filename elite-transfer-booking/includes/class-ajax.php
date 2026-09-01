<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Ajax {
    public function __construct() {
        add_action( 'wp_ajax_etb_submit_booking', array( $this, 'handle_submit_booking' ) );
        add_action( 'wp_ajax_nopriv_etb_submit_booking', array( $this, 'handle_submit_booking' ) );

        // Écouteurs pour la validation du code promo
        add_action( 'wp_ajax_etb_validate_promo', array( $this, 'handle_validate_promo' ) );
        add_action( 'wp_ajax_nopriv_etb_validate_promo', array( $this, 'handle_validate_promo' ) );
    }


    /**
     * Validation AJAX du code promo
     */
    public function handle_validate_promo() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        $raw_code   = sanitize_text_field( $_POST['promo_code'] ?? '' );
        $promo_code = strtoupper( trim( $raw_code ) );

        if ( empty( $promo_code ) ) {
            wp_send_json_error( array( 'message' => 'Veuillez saisir un code promo.' ) );
        }

        // 1. Recherche par titre du CPT tour_promo
        $args = array(
            'post_type'      => 'tour_promo',
            'post_status'    => 'publish',
            'title'          => $promo_code,
            'posts_per_page' => 1,
        );

        $query = new WP_Query( $args );

        // 2. Recherche alternative via méta-clé _etb_promo_code
        if ( ! $query->have_posts() ) {
            $args_meta = array(
                'post_type'      => 'tour_promo',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array(
                        'key'     => '_etb_promo_code',
                        'value'   => $promo_code,
                        'compare' => '=',
                    ),
                ),
            );
            $query = new WP_Query( $args_meta );
        }

        if ( $query->have_posts() ) {
            $query->the_post();
            $promo_id = get_the_ID();

            // Récupération du type de réduction (compatible avec plusieurs nommages de clés)
            $discount_type = get_post_meta( $promo_id, '_etb_discount_type', true );
            if ( empty( $discount_type ) ) {
                $discount_type = get_post_meta( $promo_id, '_etb_promo_type', true ) ?: 'fixed';
            }

            // Récupération de la valeur de réduction (fallback sur différentes clés méta possibles)
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
            wp_send_json_error( array( 'message' => 'Code promo invalide ou expiré.' ) );
        }
    }


    public function handle_submit_booking() {
        check_ajax_referer( 'etb_booking_nonce', 'nonce' );

        // --- Réception et sanitization brute ---
        $vehicles = array();
        if ( ! empty( $_POST['etb_car_qty'] ) && is_array( $_POST['etb_car_qty'] ) ) {
            foreach ( $_POST['etb_car_qty'] as $vehicle_id => $qty ) {
                $vehicles[ absint( $vehicle_id ) ] = absint( $qty );
            }
        }

        $extras = array();
        foreach ( $_POST as $key => $value ) {
            if ( strpos( $key, 'etb_extra_' ) === 0 ) {
                $extra_id = absint( str_replace( 'etb_extra_', '', $key ) );
                $extras[ $extra_id ] = absint( $value );
            }
        }

        $data = array(
            'vehicles'   => $vehicles,
            'adults'     => absint( $_POST['etb_adults'] ?? 0 ),
            'children'   => absint( $_POST['etb_children'] ?? 0 ),
            'pickup_id'  => absint( $_POST['etb_pickup_id'] ?? 0 ),
            'extras'     => $extras,
            'name'       => sanitize_text_field( $_POST['etb_name'] ?? '' ),
            'email'      => sanitize_email( $_POST['etb_email'] ?? '' ),
            'date'       => sanitize_text_field( $_POST['etb_date'] ?? '' ),
            'time'       => sanitize_text_field( $_POST['etb_time'] ?? '' ),
            'luggage'    => absint( $_POST['etb_total_luggage'] ?? 0 ),
            'promo'      => sanitize_text_field( $_POST['etb_promo'] ?? '' ),
            'note'       => sanitize_textarea_field( $_POST['etb_note'] ?? '' ),
            
            // --- NOUVEAUTÉ ETB V2 ---
            'pickup_address' => sanitize_text_field( $_POST['etb_pickup_address'] ?? '' ),
            'dropoff_info' => sanitize_textarea_field( $_POST['etb_dropoff_info'] ?? '' ),
            'option_id'    => sanitize_text_field( $_POST['etb_option_id'] ?? '' ),
            'circuit_id'   => absint( $_POST['etb_circuit_id'] ?? 0 ),
            
        );

        // --- ÉTAPE 2 : REVALIDATION SERVEUR ---
        // 1. Champs obligatoires
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
        // 2. Vérification de l'existence réelle du pickup (si ID utilisé)
        if ( ! empty( $data['pickup_id'] ) && ( get_post_type( $data['pickup_id'] ) !== 'tour_pickup' || get_post_status( $data['pickup_id'] ) !== 'publish' ) ) {
            wp_send_json_error( array( 'message' => 'Le point de départ sélectionné est invalide.' ) );
        }
        
        if ( empty( $data['vehicles'] ) || array_sum( $data['vehicles'] ) === 0 ) {
            wp_send_json_error( array( 'message' => 'Veuillez sélectionner au moins un véhicule.' ) );
        }
        if ( $data['adults'] < 1 ) {
            wp_send_json_error( array( 'message' => 'Au moins 1 adulte est requis.' ) );
        }

        // 2. Vérification de l'existence réelle du pickup
        /*
        if ( get_post_type( $data['pickup_id'] ) !== 'tour_pickup' || get_post_status( $data['pickup_id'] ) !== 'publish' ) {
            wp_send_json_error( array( 'message' => 'Le point de départ sélectionné est invalide.' ) );
        }*/

        /*//V2 2. Vérification de l'existence réelle du pickup (uniquement si un ID est utilisé)
        if ( ! empty( $data['pickup_id'] ) && ( get_post_type( $data['pickup_id'] ) !== 'tour_pickup' || get_post_status( $data['pickup_id'] ) !== 'publish' ) ) {
            wp_send_json_error( array( 'message' => 'Le point de départ sélectionné est invalide.' ) );
        }*/

        // 3. Vérification des véhicules + calcul des capacités réelles
        $total_capacity_pax     = 0;
        $total_capacity_baggage = 0;

        foreach ( $data['vehicles'] as $vehicle_id => $qty ) {
            if ( $qty <= 0 ) {
                continue;
            }
            if ( get_post_type( $vehicle_id ) !== 'tour_vehicle' || get_post_status( $vehicle_id ) !== 'publish' ) {
                wp_send_json_error( array( 'message' => 'Un véhicule sélectionné est invalide.' ) );
            }
            $max_pax     = absint( get_post_meta( $vehicle_id, '_etb_max_pax', true ) );
            $max_baggage = absint( get_post_meta( $vehicle_id, '_etb_max_baggage', true ) );

            $total_capacity_pax     += $max_pax * $qty;
            $total_capacity_baggage += $max_baggage * $qty;
        }

        // 4. Vérification des extras (existence réelle)
        foreach ( $data['extras'] as $extra_id => $qty ) {
            if ( $qty <= 0 ) {
                continue;
            }
            if ( get_post_type( $extra_id ) !== 'tour_extra' || get_post_status( $extra_id ) !== 'publish' ) {
                wp_send_json_error( array( 'message' => 'Une option sélectionnée est invalide.' ) );
            }
        }

        // 5. Validation de la capacité passagers
        $total_passengers = $data['adults'] + $data['children'];
        if ( $total_passengers > $total_capacity_pax ) {
            wp_send_json_error( array( 'message' => 'Le nombre de passagers dépasse la capacité des véhicules sélectionnés.' ) );
        }

        // 6. Validation de la capacité bagages
        if ( $data['luggage'] > $total_capacity_baggage ) {
            wp_send_json_error( array( 'message' => 'Le nombre de bagages dépasse la capacité des véhicules sélectionnés.' ) );
        }

        // --- Fin étape 2 : toutes les validations sont passées ---
        $pricing_engine  = new ETB_Pricing_Engine();
        $pricing_details = $pricing_engine->calculate_total( $data );
        $data['pricing'] = $pricing_details;

        // 1. Création de la réservation (CPT tour_booking)
        $post_title = sprintf(
            'Réservation #%s - %s',
            $data['name'],
            $data['date']
        );

        $booking_id = wp_insert_post( array(
            'post_title'   => $post_title,
            'post_type'    => 'tour_booking',
            'post_status'  => 'pending',
        ) );

        if ( is_wp_error( $booking_id ) || ! $booking_id ) {
            wp_send_json_error( array( 'message' => 'Impossible de sauvegarder la réservation.' ) );
        }

        // 2. Sauvegarde des méta-données de la commande
        update_post_meta( $booking_id, '_etb_customer_name', $data['name'] );
        update_post_meta( $booking_id, '_etb_customer_email', $data['email'] );
        update_post_meta( $booking_id, '_etb_booking_date', $data['date'] );
        update_post_meta( $booking_id, '_etb_booking_time', $data['time'] );
        update_post_meta( $booking_id, '_etb_pickup_id', $data['pickup_id'] );
        update_post_meta( $booking_id, '_etb_pickup_address', $data['pickup_address'] ); //v2
        update_post_meta( $booking_id, '_etb_adults', $data['adults'] );
        update_post_meta( $booking_id, '_etb_children', $data['children'] );
        update_post_meta( $booking_id, '_etb_luggage', $data['luggage'] );
        update_post_meta( $booking_id, '_etb_vehicles', $data['vehicles'] );
        update_post_meta( $booking_id, '_etb_extras', $data['extras'] );
        update_post_meta( $booking_id, '_etb_note', $data['note'] );
        // --- NOUVEAUTÉ ETB V2 ---
        update_post_meta( $booking_id, '_etb_dropoff_info', $data['dropoff_info'] );
        update_post_meta( $booking_id, '_etb_circuit_option_id', $data['option_id'] );
        update_post_meta( $booking_id, '_etb_circuit_id', $data['circuit_id'] );
        update_post_meta( $booking_id, '_etb_duration_hours', $pricing_details['duration_hours'] ?? 1 );

        //
        update_post_meta( $booking_id, '_etb_total_price', $pricing_details['grand_total'] );
        update_post_meta( $booking_id, '_etb_pricing_details', $pricing_details );
        update_post_meta( $booking_id, '_etb_promo_code', $pricing_details['promo_code'] );
        update_post_meta( $booking_id, '_etb_discount_amount', $pricing_details['discount_amount'] );

        // --- ENVOI DES NOTIFICATIONS E-MAIL ---

        // Récupération des réglages du plugin
        $general_settings = get_option( 'etb_general_settings', array() );
        $currency_symbol  = ! empty( $general_settings['currency'] ) ? sanitize_text_field( $general_settings['currency'] ) : '€';
        
        // Récupération sécurisée du destinataire admin avec fallback
        $admin_email = ! empty( $general_settings['admin_email'] ) && is_email( $general_settings['admin_email'] )
            ? sanitize_email( $general_settings['admin_email'] )
            : get_option( 'admin_email' );

        $formatted_total = number_format_i18n( $pricing_details['grand_total'], 2 ) . ' ' . $currency_symbol;
        $pickup_name = ! empty( $data['pickup_address'] ) ? $data['pickup_address'] : ( $data['pickup_id'] ? get_the_title( $data['pickup_id'] ) : 'Non spécifié' );


        // NOUVEAUTÉ V2 : Récupération du nom clair du circuit et de la ville pour les e-mails
        $prestation_label = 'Transfert standard';
        if ( ! empty( $data['option_id'] ) ) {
            $fallback_label   = sprintf( 'Option Circuit : %s (%sh)', $data['option_id'], $pricing_details['duration_hours'] ?? 1 );
            $prestation_label = apply_filters( 'etb_circuit_option_label', $fallback_label, $data['option_id'], $data['circuit_id'] ?? 0 );
        }

        // Construction HTML de la liste des véhicules sélectionnés
        $vehicles_list = '';
        foreach ( $data['vehicles'] as $v_id => $qty ) {
            if ( $qty > 0 ) {
                $v_title = get_the_title( $v_id );
                $vehicles_list .= '<li>' . esc_html( $v_title ) . ' &times; ' . absint( $qty ) . '</li>';
            }
        }

        // Construction HTML de la liste des extras
        $extras_list = '';
        foreach ( $data['extras'] as $e_id => $qty ) {
            if ( $qty > 0 ) {
                $e_title = get_the_title( $e_id );
                $extras_list .= '<li>' . esc_html( $e_title ) . ' &times; ' . absint( $qty ) . '</li>';
            }
        }

        // Éléments conditionnels
        $promo_html = '';
        if ( ! empty( $pricing_details['discount_amount'] ) && $pricing_details['discount_amount'] > 0 ) {
            $formatted_discount = number_format_i18n( $pricing_details['discount_amount'], 2 ) . ' ' . $currency_symbol;
            $promo_html = '<p style="color:#22c55e;"><strong>Code promo (' . esc_html( $pricing_details['promo_code'] ) . ') :</strong> - ' . $formatted_discount . '</p>';
        } elseif ( ! empty( $pricing_details['promo_code'] ) ) {
            $promo_html = '<p><strong>Code promo :</strong> ' . esc_html( $pricing_details['promo_code'] ) . '</p>';
        }
        $note_html  = ! empty( $data['note'] )  ? '<h3>Demande spéciale :</h3><p>' . nl2br( esc_html( $data['note'] ) ) . '</p>' : '';

        // Headers
        $headers_client = array( 'Content-Type: text/html; charset=UTF-8' );
        
        // Reply-To construit uniquement à partir des données déjà nettoyées et validées (sans esc_html)
        $headers_admin  = array(
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>',
        );

        // 1. Contenu E-mail Client
        $to_client      = $data['email'];
        $subject_client = sprintf( 'Confirmation de votre demande de réservation #%d', $booking_id );
        

        // 1. Contenu E-mail Client
        // 1. Contenu E-mail Client
        $to_client      = $data['email'];
        $subject_client = sprintf( 'Confirmation de votre demande de réservation #%d', $booking_id );
        
        $message_client = sprintf(
            '<h2>Bonjour %s,</h2>
            <p>Votre demande de réservation a bien été enregistrée sous le dossier <strong>#%d</strong>.</p>
            <hr>
            <h3>Détails de la réservation :</h3>
            <ul>
                <li><strong>Prestation :</strong> %s</li>
                <li><strong>Point de départ :</strong> %s</li>
                <li><strong>Point d\'arrivée :</strong> %s</li>
                <li><strong>Date et Heure :</strong> %s à %s</li>
                <li><strong>Passagers :</strong> %d adulte(s), %d enfant(s) (Total : %d)</li>
                <li><strong>Bagages :</strong> %d</li>
            </ul>
            <h3>Véhicule(s) réservé(s) :</h3>
            <ul>%s</ul>' .
            ( ! empty( $extras_list ) ? '<h3>Option(s) / Extra(s) :</h3><ul>' . $extras_list . '</ul>' : '' ) .
            '%s%s
            <hr>
            <p><strong>Montant estimé :</strong> %s</p>
            <p>Notre équipe traite votre demande et vous recontactera dans les plus brefs délais.</p>',
            esc_html( $data['name'] ),
            $booking_id,
            esc_html( $prestation_label ), // Prestation / Circuit
            esc_html( $pickup_name ),
            esc_html( empty( $data['dropoff_info'] ) ? 'Identique au point de départ' : $data['dropoff_info'] ),
            esc_html( $data['date'] ),
            esc_html( $data['time'] ),
            $data['adults'],
            $data['children'],
            ( $data['adults'] + $data['children'] ),
            $data['luggage'],
            $vehicles_list,
            $promo_html,
            $note_html,
            $formatted_total
        );
        
        // 2. Contenu E-mail Admin
        // 2. Contenu E-mail Admin
        $subject_admin  = sprintf( '[Nouvelle Réservation] Dossier #%d - %s', $booking_id, $data['name'] );
        $admin_edit_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );

        $message_admin  = sprintf(
            '<h2>Nouvelle demande de réservation (Dossier #%d)</h2>
            <p><strong>Client :</strong> %s (%s)</p>
            <hr>
            <h3>Détails de la réservation :</h3>
            <ul>
                <li><strong>Prestation :</strong> %s</li>
                <li><strong>Point de départ :</strong> %s</li>
                <li><strong>Point d\'arrivée :</strong> %s</li>
                <li><strong>Date et Heure :</strong> %s à %s</li>
                <li><strong>Passagers :</strong> %d adulte(s), %d enfant(s) (Total : %d)</li>
                <li><strong>Bagages :</strong> %d</li>
            </ul>
            <h3>Véhicule(s) :</h3>
            <ul>%s</ul>' .
            ( ! empty( $extras_list ) ? '<h3>Option(s) / Extra(s) :</h3><ul>' . $extras_list . '</ul>' : '' ) .
            '%s%s
            <hr>
            <p><strong>Montant Total :</strong> %s</p>
            <hr>
            <p><a href="%s" style="display:inline-block; padding:10px 15px; background:#0073aa; color:#fff; text-decoration:none; border-radius:3px;">Consulter le dossier dans WordPress</a></p>',
            $booking_id,
            esc_html( $data['name'] ),
            esc_html( $data['email'] ),
            esc_html( $prestation_label ), // Prestation / Circuit
            esc_html( $pickup_name ),
            esc_html( empty( $data['dropoff_info'] ) ? 'Identique au point de départ' : $data['dropoff_info'] ),
            esc_html( $data['date'] ),
            esc_html( $data['time'] ),
            $data['adults'],
            $data['children'],
            ( $data['adults'] + $data['children'] ),
            $data['luggage'],
            $vehicles_list,
            $promo_html,
            $note_html,
            $formatted_total,
            esc_url( $admin_edit_url )
        );

        // Expédition & Logging d'échec
        $sent_client = wp_mail( $to_client, $subject_client, $message_client, $headers_client );
        if ( ! $sent_client ) {
            error_log( sprintf( 'ETB Mail Error: Échec d\'envoi du mail client (#%d à %s)', $booking_id, $to_client ) );
        }

        $sent_admin = wp_mail( $admin_email, $subject_admin, $message_admin, $headers_admin );
        if ( ! $sent_admin ) {
            error_log( sprintf( 'ETB Mail Error: Échec d\'envoi du mail admin (#%d à %s)', $booking_id, $admin_email ) );
        }

        $data['booking_id'] = $booking_id;

        wp_send_json_success( $data );
    }
}