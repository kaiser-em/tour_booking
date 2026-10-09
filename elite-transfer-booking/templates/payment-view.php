<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$gen_settings    = get_option( 'etb_general_settings', array() );
$currency_symbol = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';

// Lecture dynamique des paramètres d'entreprise (Zéro Hardcode)
$legal_name   = ! empty( $gen_settings['company_legal_name'] ) ? sanitize_text_field( $gen_settings['company_legal_name'] ) : '"EDEN CAB" Ltd';
$sub_brand    = ! empty( $gen_settings['company_subtitle'] ) ? sanitize_text_field( $gen_settings['company_subtitle'] ) : 'superior drive';
$addr_line1   = ! empty( $gen_settings['company_address_line1'] ) ? sanitize_text_field( $gen_settings['company_address_line1'] ) : '250 avenue de Grasse';
$addr_line2   = ! empty( $gen_settings['company_address_line2'] ) ? sanitize_text_field( $gen_settings['company_address_line2'] ) : 'Cannes 06400, France';
$uploaded_logo= ! empty( $gen_settings['company_logo_url'] ) ? esc_url( $gen_settings['company_logo_url'] ) : '';
$company_name = get_bloginfo( 'name' );

// 1. Détection du dossier via Quote/Payment Token ou paramètres signés
$ref_token  = ! empty( $_GET['ref'] ) ? sanitize_key( $_GET['ref'] ) : '';
$pay_data   = false;
$booking_id = 0;

if ( ! empty( $ref_token ) ) {
    $pay_data = get_transient( 'etb_pay_' . $ref_token );
    if ( is_array( $pay_data ) && ! empty( $pay_data['booking_id'] ) ) {
        $booking_id = absint( $pay_data['booking_id'] );
    }
}

if ( ! $booking_id && ! empty( $_GET['booking_id'] ) ) {
    $booking_id = absint( $_GET['booking_id'] );
}




$has_valid_booking = ( $booking_id > 0 && 'tour_booking' === get_post_type( $booking_id ) );

if ( isset( $_GET['debug'] ) && current_user_can( 'manage_options' ) ) {
    echo '<div style="background:#000;color:#0f0;padding:20px;font-family:monospace;font-size:12px;z-index:99999;position:relative;">';
    echo '=== DIAGNOSTIC WORDPRESS & LIMOEXPRESS ===<br>';
    echo 'Booking ID WP : ' . esc_html( $booking_id ) . '<br>';
    echo '_etb_total_price : ' . var_export( get_post_meta( $booking_id, '_etb_total_price', true ), true ) . '<br>';
    echo '_etb_limo_booking_id : ' . var_export( get_post_meta( $booking_id, '_etb_limo_booking_id', true ), true ) . '<br>';
    echo '_etb_limo_uuid : ' . var_export( get_post_meta( $booking_id, '_etb_limo_uuid', true ), true ) . '<br>';
    echo '_etb_limo_raw_create_response : ' . esc_html( get_post_meta( $booking_id, '_etb_limo_raw_create_response', true ) ) . '<br>';
    echo '</div>';
}



// 2. Contrôle de sécurité cryptographique anti-falsification
//$url_amount      = isset( $_GET['amount'] ) ? floatval( $_GET['amount'] ) : 0.0;
//$url_signature   = sanitize_text_field( $_GET['sig'] ?? '' );
//$is_tampered     = false;
//$settled_amount  = 0.0;

// 2. Initialisation des états de base
$is_already_paid = false;
if ( $has_valid_booking ) {
    $current_status  = get_post_meta( $booking_id, '_etb_status', true );
    $stripe_intent   = get_post_meta( $booking_id, '_etb_stripe_payment_intent_id', true );
    $is_already_paid = ( 'confirmed' === $current_status || ! empty( $stripe_intent ) );
}

// 3. Contrôle du montant et interrogation de LimoExpress
$url_amount      = isset( $_GET['amount'] ) ? floatval( $_GET['amount'] ) : 0.0;
$url_signature   = sanitize_text_field( $_GET['sig'] ?? '' );
$is_tampered     = false;
$settled_amount  = 0.0;

if ( $url_amount > 0 ) {
    // Si un montant est passé dans l'URL, la signature HMAC est obligatoire
    if ( class_exists( 'ETB_Security' ) && ETB_Security::verify_payment_signature( $booking_id, $url_amount, $url_signature ) ) {
        $settled_amount = $url_amount;
    } else {
        $is_tampered = true;
    }
} elseif ( is_array( $pay_data ) && ! empty( $pay_data['amount'] ) ) {
    $settled_amount = floatval( $pay_data['amount'] );
} elseif ( $has_valid_booking ) {
    $settled_amount = floatval( get_post_meta( $booking_id, '_etb_total_price', true ) );

    // ── LIMOEXPRESS-FIRST : SYNCHRONISATION MIROIR EN TEMPS RÉEL ──
    if ( ! $is_already_paid && class_exists( 'ETB_LimoExpress' ) ) {
        $limo_id   = get_post_meta( $booking_id, '_etb_limo_booking_id', true );
        $limo_uuid = get_post_meta( $booking_id, '_etb_limo_uuid', true );
        $search_id = ! empty( $limo_uuid ) ? $limo_uuid : $limo_id;

        $limo_check = ETB_LimoExpress::get_booking_details( $search_id, $booking_id );

        // Si la course a été retrouvée dans LimoExpress
        if ( isset( $limo_check['price'] ) ) {
            $live_limo_price = floatval( $limo_check['price'] );
            $child_seats_fee = floatval( get_post_meta( $booking_id, '_etb_child_seat_fee', true ) );

            // Le total LimoExpress = Tarif transport + Sièges enfants
            $settled_amount = $live_limo_price + $child_seats_fee;
            update_post_meta( $booking_id, '_etb_base_price', $live_limo_price );
            update_post_meta( $booking_id, '_etb_total_price', $settled_amount );

            // Sauvegarde de l'UUID réel de la course pour le paiement final
            if ( ! empty( $limo_check['uuid'] ) ) {
                update_post_meta( $booking_id, '_etb_limo_uuid', $limo_check['uuid'] );
            }
        }

        // Détection si la course a été marquée payée directement dans LimoExpress
        if ( ! empty( $limo_check['paid'] ) ) {
            $is_already_paid = true;
            update_post_meta( $booking_id, '_etb_status', 'confirmed' );
        }
    }
}

// 4. Récupération des détails complets de la réservation
if ( $has_valid_booking && ! $is_tampered ) {
    $customer_name   = get_post_meta( $booking_id, '_etb_customer_name', true ) ?: ( $pay_data['client_name'] ?? 'VIP Client' );
    $customer_email  = get_post_meta( $booking_id, '_etb_customer_email', true ) ?: ( $pay_data['client_email'] ?? '' );
    $customer_phone  = get_post_meta( $booking_id, '_etb_customer_phone', true ) ?: ( $pay_data['client_phone'] ?? '' );
    $pickup_address  = get_post_meta( $booking_id, '_etb_pickup_address', true ) ?: ( $pay_data['pickup'] ?? '—' );
    $dropoff_info    = get_post_meta( $booking_id, '_etb_dropoff_info', true ) ?: ( $pay_data['dropoff'] ?? '—' );
    $booking_date    = get_post_meta( $booking_id, '_etb_booking_date', true ) ?: ( $pay_data['date'] ?? '' );
    $booking_time    = get_post_meta( $booking_id, '_etb_booking_time', true ) ?: ( $pay_data['time'] ?? '' );
    $flight_number   = get_post_meta( $booking_id, '_etb_flight_number', true );
    $waiting_sign    = get_post_meta( $booking_id, '_etb_waiting_board_text', true );
    $limo_id         = get_post_meta( $booking_id, '_etb_limo_booking_id', true ) ?: ( $pay_data['limo_id'] ?? '' );

    // Passagers et Bagages détaillés
    $pax_count       = absint( get_post_meta( $booking_id, '_etb_adults', true ) ?: 1 );
    $checked_luggage = absint( get_post_meta( $booking_id, '_etb_checked_luggage', true ) );
    $cabin_bags      = absint( get_post_meta( $booking_id, '_etb_cabin_bags', true ) );
    $total_luggage   = absint( get_post_meta( $booking_id, '_etb_luggage', true ) ?: ($checked_luggage + $cabin_bags) );

    // Sièges enfants et calcul des suppléments payants
    $baby_seats      = absint( get_post_meta( $booking_id, '_etb_baby_seat_count', true ) );
    $booster_seats   = absint( get_post_meta( $booking_id, '_etb_booster_seat_count', true ) );
    $child_seat_fee  = floatval( get_post_meta( $booking_id, '_etb_child_seat_fee', true ) );
    $paid_baby       = max( 0, $baby_seats - 1 );
    $paid_booster    = max( 0, $booster_seats - 2 );
    $fee_baby        = (float) ( $paid_baby * 50.0 );
    $fee_booster     = (float) ( $paid_booster * 50.0 );

    // Tarification décomposée & Pourboire chauffeur
    $saved_tip_percent = absint( get_post_meta( $booking_id, '_etb_tip_percentage', true ) ?: 0 );
    $saved_tip_amount  = floatval( get_post_meta( $booking_id, '_etb_tip_amount', true ) ?: 0.0 );
    $saved_base_price  = floatval( get_post_meta( $booking_id, '_etb_base_price', true ) );

    // Définition anticipée de l'émoji persistant de pourboire (pour Apple Pay, Google Pay et Carte)
    $noto_initial_codes = array( 10 => '1f64f', 15 => '1f929', 20 => '1f60d' );
    $initial_code       = $noto_initial_codes[ $saved_tip_percent ] ?? '';

    if ( $saved_base_price <= 0 ) {
        $saved_base_price = ( $settled_amount > ( $saved_tip_amount + $child_seat_fee ) ) 
            ? ( $settled_amount - $saved_tip_amount - $child_seat_fee ) 
            : $settled_amount;
    }
    
    $vehicles = get_post_meta( $booking_id, '_etb_vehicles', true ) ?: array();
    $vehicle_title = 'VIP Chauffeured Vehicle';
    $vehicle_img   = '';
    if ( ! empty( $vehicles ) && is_array( $vehicles ) ) {
        foreach ( $vehicles as $v_id => $q ) {
            if ( $q > 0 ) {
                $vehicle_title = get_the_title( $v_id );
                $vehicle_img   = get_the_post_thumbnail_url( $v_id, 'full' ) ?: '';
                break;
            }
        }
    }
} else {
    $customer_name   = 'VIP Client';
    $customer_email  = '';
    $customer_phone  = '';
    $pickup_address  = '—';
    $dropoff_info    = '—';
    $booking_date    = '';
    $booking_time    = '';
    $flight_number   = '';
    $waiting_sign    = '';
    $limo_id         = '';
    $pax_count       = 1;
    $checked_luggage = 0;
    $cabin_bags      = 0;
    $total_luggage   = 0;
    $baby_seats      = 0;
    $booster_seats   = 0;
    $vehicle_title   = 'VIP Chauffeured Vehicle';
    $vehicle_img     = '';
}

// Formatage de la date en anglais (ex: 03 Oct. 2026 at 09:00 AM)
$formatted_pay_datetime = '—';
if ( ! empty( $booking_date ) ) {
    $dt_ts = strtotime( $booking_date );
    $formatted_pay_datetime = $dt_ts ? date( 'd M. Y', $dt_ts ) : $booking_date;
    if ( ! empty( $booking_time ) ) {
        $formatted_pay_datetime .= ' at ' . $booking_time;
    }
}

$formatted_amount = number_format_i18n( $settled_amount, 2 ) . ' ' . $currency_symbol;
?>

<div class="etb-checkout-wrapper etb-standalone-payment-wrapper" id="etb-payment-page-app">
    
    <!-- Bouton bascule Dark / Light mode (flottant en haut à droite) -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
        <button type="button" class="etb-theme-toggle-btn etb-theme-switch" aria-label="Toggle theme">
            <span class="etb-switch-track">
                <svg class="etb-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line>
                </svg>
                <svg class="etb-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
                <span class="etb-switch-thumb"></span>
            </span>
        </button>
    </div>

    <?php if ( $is_tampered ) : ?>
        <!-- Alerte de sécurité si lien falsifié ou altéré -->
        <div class="etb-checkout-card" style="text-align: center; padding: 45px 30px; border-color: #dc2626; max-width: 600px; margin: 0 auto;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; background: rgba(220, 38, 38, 0.15); border: 2px solid #ef4444; border-radius: 50%; margin-bottom: 18px; color: #f87171;">
                <span class="dashicons dashicons-shield" style="font-size: 32px; width: 32px; height: 32px;"></span>
            </div>
            <h2 style="color: #ef4444; font-size: 22px; font-weight: 800; margin: 0 0 8px 0;">Security Verification Failed</h2>
            <p style="font-size: 14px; color: var(--etb-text-secondary, #94a3b8); margin-bottom: 20px; line-height: 1.5;">
                This payment link is invalid, expired, or has been altered. For security reasons, the transaction cannot proceed.
            </p>
            <p style="font-size: 13px; color: var(--etb-text-secondary, #94a3b8); margin-bottom: 25px;">
                Please contact our dispatch regulation via WhatsApp or email to receive a verified settlement link.
            </p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="etb-chk-submit-btn" style="text-decoration: none; display: inline-flex; width: auto; padding: 12px 30px;">
                Return to Home
            </a>
        </div>
    <?php elseif ( $is_already_paid ) : ?>
        <div class="etb-checkout-card" style="text-align: center; padding: 45px 30px; border-color: #16a34a; max-width: 600px; margin: 0 auto;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; background: rgba(34, 197, 94, 0.15); border: 2px solid #22c55e; border-radius: 50%; margin-bottom: 18px; color: #4ade80;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <?php 
            $settled_ref = get_post_meta( $booking_id, '_etb_limo_booking_id', true );
            $display_settled_ref = ( ! empty( $settled_ref ) && 'OK' !== $settled_ref ) ? $settled_ref : ( '#' . $booking_id );
            ?>
            <h2 style="color: #4ade80; font-size: 22px; font-weight: 800; margin: 0 0 8px 0;">Mission Already Settled</h2>
            <p style="font-size: 14px; margin-bottom: 20px;">
                Mission #<?php echo esc_html( $display_settled_ref ); ?> has been paid in full and confirmed in dispatch.
            </p>            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="etb-chk-submit-btn" style="text-decoration: none; display: inline-flex; width: auto; padding: 12px 30px;">
                Return to Home
            </a>
        </div>
    <?php elseif ( $settled_amount <= 0 ) : ?>
        <div class="etb-checkout-card" style="text-align: center; padding: 40px 20px; border-color: #eab308; max-width: 600px; margin: 0 auto;">
            <span class="dashicons dashicons-clock" style="font-size: 42px; width: 42px; height: 42px; color: #eab308; margin-bottom: 15px;"></span>
            <h2 style="font-size: 20px; font-weight: 800; margin: 0 0 8px 0;">Quotation in Progress</h2>
            <p style="font-size: 14px; max-width: 500px; margin: 0 auto 20px auto;">
                Our dispatch team is currently calculating your route pricing. You will receive an offer link as soon as the quotation is finalized.
            </p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="etb-chk-submit-btn" style="text-decoration: none; display: inline-flex; width: auto; padding: 12px 30px;">
                Return to Home
            </a>
        </div>
    <?php else : ?>
        
        <!-- LAYOUT STRIPE STYLE : DESCRIPTION À GAUCHE / PAIEMENT À DROITE -->
        <div class="etb-checkout-layout" style="align-items: start;">
            
            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- COLONNE 1 (GAUCHE) : DESCRIPTION ET RÉCAPITULATIF DE LA MISSION -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div class="etb-checkout-left-col">
                <div class="etb-chk-sticky-card">
                    
                    <?php
                    // Priorité 1 : Logo uploadé dans Tour Booking > Réglages
                    // Priorité 2 : Logo personnalisé du thème WP
                    $display_logo_url = $uploaded_logo;
                    if ( empty( $display_logo_url ) ) {
                        $custom_logo_id   = get_theme_mod( 'custom_logo' );
                        $display_logo_url = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'medium' ) : '';
                    }
                    ?>

                    <!-- En-tête officiel (Logo, Titre, Slogan & Adresse) -->
                    <div class="etb-payment-company-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); padding-bottom: 18px; margin-bottom: 22px;">
                        
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <?php if ( ! empty( $display_logo_url ) ) : ?>
                                <img src="<?php echo esc_url( $display_logo_url ); ?>" alt="EDEN CAB" style="max-height: 48px; max-width: 130px; object-fit: contain;">
                            <?php else : ?>
                                <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(251, 172, 24, 0.12); border: 1.5px solid #fbac18; display: flex; align-items: center; justify-content: center; color: #fbac18; flex-shrink: 0;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"/>
                                        <circle cx="6.5" cy="16.5" r="2.5"/>
                                        <circle cx="16.5" cy="16.5" r="2.5"/>
                                    </svg>
                                </div>
                            <?php endif; ?>

                            <!-- Nom de marque & Slogan -->
                            <div class="etb-payment-logo-wrap" style="display: flex; flex-direction: column;">
                                <span class="etb-payment-brand-title" style="font-size: 20px; font-weight: 900; letter-spacing: 0.08em; color: var(--etb-text-primary, #ffffff); line-height: 1.1;">EDEN CAB</span>
                                <span class="etb-payment-brand-sub" style="font-size: 11px; color: var(--etb-accent-gold, #fbac18); text-transform: uppercase; letter-spacing: 0.14em; font-weight: 800; margin-top: 3px;"><?php echo esc_html( $sub_brand ); ?></span>
                            </div>
                        </div>

                        <!-- Adresse physique de l'entreprise (Point 2) -->
                        <div class="etb-payment-company-address" style="text-align: right;">
                            <p style="margin: 0; font-size: 12px; color: var(--etb-text-secondary, #94a3b8); font-weight: 500; line-height: 1.4;"><?php echo esc_html( $addr_line1 ); ?></p>
                            <p style="margin: 0; font-size: 12px; color: var(--etb-text-secondary, #94a3b8); font-weight: 500; line-height: 1.4;"><?php echo esc_html( $addr_line2 ); ?></p>
                        </div>
                    </div>

                    <!-- Montant principal & Décomposition dynamique en anglais -->
                    <div style="margin-bottom: 24px;">
                        <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--etb-text-secondary, #94a3b8); letter-spacing: 0.05em;">Total Amount</span>
                        <div id="etb-pay-display-total" style="font-size: 38px; font-weight: 900; color: var(--etb-accent-gold, #fbac18); letter-spacing: -1px; margin-top: 2px;">
                            <?php echo esc_html( $formatted_amount ); ?>
                        </div>
                        
                        <!-- Détails des suppléments inclus sous le total -->
                        <div class="etb-pay-breakdown-details" style="font-size: 12.5px; margin-top: 6px; display: flex; flex-direction: column; gap: 3px;">
                            <?php if ( $fee_baby > 0 ) : ?>
                                <div style="display: flex; align-items: center; gap: 6px; color: var(--etb-text-primary, #fbac18);">
                                    <span>•</span>
                                    <span>Includes <strong>+<?php echo number_format_i18n( $fee_baby, 2 ); ?> <?php echo esc_html( $currency_symbol ); ?></strong> Extra Baby Seat (x<?php echo esc_html( $paid_baby ); ?>)</span>
                                </div>
                            <?php endif; ?>

                            <?php if ( $fee_booster > 0 ) : ?>
                                <div style="display: flex; align-items: center; gap: 6px; color: var(--etb-text-primary, #fbac18);">
                                    <span>•</span>
                                    <span>Includes <strong>+<?php echo number_format_i18n( $fee_booster, 2 ); ?> <?php echo esc_html( $currency_symbol ); ?></strong> Extra Booster Seat (x<?php echo esc_html( $paid_booster ); ?>)</span>
                                </div>
                            <?php endif; ?>

                            <div id="etb-pay-display-tip-line" style="display: <?php echo ( $saved_tip_amount > 0 ) ? 'flex' : 'none'; ?>; align-items: center; gap: 6px; color: #4ade80;">
                                <span>•</span>
                                <span>Includes <strong id="etb-pay-display-tip-text">+<?php echo number_format_i18n( $saved_tip_amount, 2 ); ?> <?php echo esc_html( $currency_symbol ); ?></strong> Driver Tip (<span id="etb-pay-display-tip-pct"><?php echo esc_html( $saved_tip_percent ); ?>%</span>)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Détails de la mission -->
                    <div style="border-top: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); padding-top: 20px; margin-bottom: 20px;">
                        
                        <!-- 1. NOM DU VÉHICULE -->
                        <h4 style="font-size: 17px; font-weight: 800; margin: 0 0 16px 0; color: var(--etb-text-primary, #ffffff); letter-spacing: 0.02em;">
                            <?php echo esc_html( $vehicle_title ); ?>
                        </h4>

                        <!-- 2. TIMELINE : DATE, PICKUP, DROPOFF, FLIGHT, GREETING SIGN -->
                        <div class="etb-chk-summary-timeline" style="margin-bottom: 18px;">
                            
                            <!-- Date & Time -->
                            <div class="etb-chk-timeline-item">
                                <span class="etb-chk-calendar-icon">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fbac18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                </span>
                                <div class="etb-chk-timeline-text">
                                    <small>DATE & TIME</small>
                                    <strong><?php echo esc_html( $formatted_pay_datetime ); ?></strong>
                                </div>
                            </div>

                            <!-- Pickup -->
                            <div class="etb-chk-timeline-item">
                                <span class="etb-chk-bullet etb-bullet-pickup"></span>
                                <div class="etb-chk-timeline-text">
                                    <small>PICKUP</small>
                                    <strong><?php echo esc_html( $pickup_address ); ?></strong>
                                </div>
                            </div>

                            <!-- Drop-off -->
                            <div class="etb-chk-timeline-item">
                                <span class="etb-chk-bullet etb-bullet-dropoff"></span>
                                <div class="etb-chk-timeline-text">
                                    <small>DROP-OFF / SERVICE</small>
                                    <strong><?php echo esc_html( $dropoff_info ); ?></strong>
                                </div>
                            </div>

                            <!-- Vol si présent -->
                            <?php if ( ! empty( $flight_number ) ) : ?>
                                <div class="etb-chk-timeline-item">
                                    <span class="dashicons dashicons-airplane" style="color: #fbac18; font-size: 15px; margin-left: 2px;"></span>
                                    <div class="etb-chk-timeline-text">
                                        <small>FLIGHT</small>
                                        <strong><?php echo esc_html( $flight_number ); ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Pancarte d'accueil si présente -->
                            <?php if ( ! empty( $waiting_sign ) ) : ?>
                                <div class="etb-chk-timeline-item">
                                    <span style="font-size: 13px; line-height: 1; margin-left: 2px;">🪧</span>
                                    <div class="etb-chk-timeline-text">
                                        <small>GREETING SIGN</small>
                                        <strong><?php echo esc_html( $waiting_sign ); ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 3. PASSAGER & CAPACITÉS (PAX, CHECKED, CABIN, SIÈGES BÉBÉ) -->
                        <div style="border-top: 1px dashed var(--etb-border-light, rgba(255,255,255,0.08)); padding-top: 16px;">
                            <p style="font-size: 13px; color: var(--etb-text-secondary, #94a3b8); margin: 0 0 10px 0;">
                                Passenger: <strong style="color: var(--etb-text-primary, #ffffff);"><?php echo esc_html( $customer_name ); ?></strong>
                            </p>

                            <!-- Spécifications Passagers & Bagages : Pax, Checked, Cabin -->
                            <div style="display: flex; align-items: center; gap: 12px; font-size: 12px; flex-wrap: wrap;">
                                <span style="display: inline-flex; align-items: center; gap: 5px;">
                                    <svg viewBox="0 0 100 95" width="14" height="14" fill=" var(--etb-accent-gold, #fbac18)">
                                        <path d="m88.484 31.117c0 8.0312-6.5117 14.539-14.539 14.539-8.0312 0-14.543-6.5078-14.543-14.539s6.5117-14.539 14.543-14.543c8.0273 0 14.539 6.5117 14.539 14.543z"/>
                                        <path d="m0.90234 73.273c-3.0547 6.2812 2.0391 15.633 9.6914 17.562 16.133 3.6016 32.57 3.6016 48.703 0 7.6562-1.9336 12.75-11.281 9.6914-17.562-5.7109-11.867-18.844-22.293-34.047-22.391-15.203 0.10156-28.336 10.523-34.047 22.391z"/>
                                        <path d="m54.445 25.965c0 10.77-8.7305 19.504-19.5 19.504-10.77 0-19.504-8.7344-19.504-19.504 0-10.77 8.7344-19.5 19.504-19.5 10.77-0.003906 19.5 8.7305 19.5 19.5z"/>
                                        <path d="m99.328 66.391c-4.2578-8.8516-14.051-16.625-25.383-16.695-5.1719 0.03125-10.023 1.6719-14.176 4.2812 6.0273 4.4648 11.02 10.426 14.141 16.91 1.5391 3.1641 1.8438 6.8906 0.93359 10.605 5.7734-0.0625 11.539-0.73047 17.258-2.0078 5.707-1.4414 9.5078-8.4141 7.2266-13.094z"/>
                                    </svg>
                                    <strong style="color: var(--etb-text-primary, #ffffff);"><?php echo esc_html( $pax_count ); ?></strong> Passengers
                                </span>
                                <span>•</span>
                                <span style="display: inline-flex; align-items: center; gap: 5px;">
                                    <svg viewBox="20 8 60 88" width="14" height="14" fill=" var(--etb-accent-gold, #fbac18)">
                                        <path d="M70.75,26.75H60.028l1.056,5.476c2.108-0.315,4.109,1.073,4.517,3.185l0.868,4.5c0.418,2.169-1.001,4.267-3.171,4.685 l-1.227,0.237c-2.169,0.418-4.268-1.001-4.686-3.17l-0.867-4.5c-0.408-2.113,0.936-4.146,3.01-4.637l-1.113-5.775H54.75v-14 c0-0.019-0.01-0.034-0.011-0.052c0.002-0.032,0.01-0.062,0.01-0.095c0-0.773-0.626-1.397-1.397-1.397h-6.705 c-0.771,0-1.397,0.624-1.397,1.397c0,0.034,0.008,0.065,0.01,0.098c0,0.017-0.01,0.031-0.01,0.049v14h-16c-2.209,0-4,1.791-4,4v59 c0,2.209,1.791,4,4,4h6V94c0,0.69,0.559,1.25,1.25,1.25c0.689,0,1.25-0.56,1.25-1.25v-0.25h24.5V94c0,0.69,0.559,1.25,1.25,1.25 c0.689,0,1.25-0.56,1.25-1.25v-0.25h6c2.209,0,4-1.791,4-4v-59C74.75,28.541,72.959,26.75,70.75,26.75z M47.75,14h4.5v12.75h-4.5V14 z M63.39,84.078h-26.78c-1.027,0-1.86-0.834-1.86-1.859c0-1.028,0.833-1.859,1.86-1.859h26.78c1.026,0,1.859,0.831,1.859,1.859 C65.249,83.244,64.416,84.078,63.39,84.078z"/>
                                    </svg>
                                    <strong style="color: var(--etb-text-primary, #ffffff);"><?php echo esc_html( $checked_luggage ); ?></strong> Checked
                                </span>
                                <span>•</span>
                                <span style="display: inline-flex; align-items: center; gap: 5px;">
                                    <svg viewBox="0 0 401.438 401.438" width="13" height="13" fill=" var(--etb-accent-gold, #fbac18)">
                                        <path d="M272.25,71.625c0-15.816-12.871-28.688-28.688-28.688H157.5c-15.816,0-28.688,12.871-28.688,28.688V90.75H76.5V358.5 h248.625V90.75H272.25V71.625z M253.125,90.75H147.938V71.625c0-5.279,4.284-9.562,9.562-9.562h86.062 c5.278,0,9.562,4.284,9.562,9.562L253.125,90.75L253.125,90.75z"/>
                                        <path d="M0,129v191.25c0,21.123,17.126,38.25,38.25,38.25h28.688V90.75H38.25C17.126,90.75,0,107.876,0,129z"/>
                                        <path d="M363.188,90.75H334.5V358.5h28.688c21.125,0,38.25-17.127,38.25-38.25V129C401.438,107.876,384.311,90.75,363.188,90.75z"/>
                                    </svg>
                                    <strong style="color: var(--etb-text-primary, #ffffff);"><?php echo esc_html( $cabin_bags ); ?></strong> Cabin
                                </span>
                            </div>

                            <!-- Sièges enfants si présents -->
                            <?php if ( $baby_seats > 0 || $booster_seats > 0 ) : ?>
                                <div class="baby-seat-list">
                                    👶 Child Seats: 
                                    <?php 
                                    $seats_recap = array();
                                    if ( $baby_seats > 0 ) $seats_recap[] = $baby_seats . ' Baby Seat (0-2y)';
                                    if ( $booster_seats > 0 ) $seats_recap[] = $booster_seats . ' Booster (2-10y)';
                                    echo esc_html( implode( ' + ', $seats_recap ) );
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Référence officielle unifiée de la mission -->
                    <?php 
                    $main_booking_ref = ( ! empty( $limo_id ) && 'OK' !== $limo_id ) ? $limo_id : ( '#' . $booking_id );
                    ?>
                    <div style="font-size: 12px; color: var(--etb-text-secondary, #94a3b8); border-top: 1px dashed var(--etb-border-light, rgba(255,255,255,0.08)); padding-top: 12px;">
                        <span>Booking Reference: <strong style="color: var(--etb-text-primary, #ffffff); font-size: 13.5px; letter-spacing: 0.02em;">#<?php echo esc_html( $main_booking_ref ); ?></strong></span>
                    </div>

                    <!-- Note informative : Paiements futurs (Point 1) -->
                    <div style="margin-top: 20px; padding: 14px 16px; background: var(--etb-bg-input, rgba(255,255,255,0.03)); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); border-radius: 10px;">
                        <strong style="display: block; font-size: 13px; color: var(--etb-text-primary, #0f172a); margin-bottom: 4px;">Setup payments for future usage</strong>
                        <p style="font-size: 11.5px; color: var(--etb-text-secondary, #64748b); line-height: 1.5; margin: 0;">
                            Allows <?php echo esc_html( $legal_name ); ?> to collect payments without your presence. Useful if you're a regular customer.
                        </p>
                    </div>

                </div>
            </div>
            
            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- COLONNE 2 (DROITE) : FORMULAIRE DE PAIEMENT STRIPE              -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div class="etb-checkout-right-col" style="position: static !important; width: 100% !important;">
                <div class="etb-checkout-card">
                    
                    <h3 class="etb-payment-section-title" style="margin-bottom: 20px;">Payment Details</h3>

                    <form id="etb-standalone-payment-form" onsubmit="return false;">
                        <input type="hidden" name="booking_id" id="etb-pay-booking-id" value="<?php echo esc_attr( $booking_id ); ?>">
                        <input type="hidden" name="base_fare" id="etb-pay-base-fare" value="<?php echo esc_attr( $saved_base_price ); ?>">
                        <input type="hidden" name="child_seat_fee" id="etb-pay-child-seat-fee" value="<?php echo esc_attr( $child_seat_fee ); ?>">
                        <input type="hidden" name="tip_percentage" id="etb-pay-tip-percent" value="<?php echo esc_attr( $saved_tip_percent ); ?>">
                        <input type="hidden" name="tip_amount" id="etb-pay-tip-amount" value="<?php echo esc_attr( $saved_tip_amount ); ?>">
                        <input type="hidden" name="amount" id="etb-pay-amount" value="<?php echo esc_attr( $settled_amount ); ?>">
                        <input type="hidden" name="client_name" id="etb-pay-client-name" value="<?php echo esc_attr( $customer_name ); ?>">
                        <input type="hidden" name="client_email" id="etb-pay-client-email" value="<?php echo esc_attr( $customer_email ); ?>">
                        <input type="hidden" name="client_phone" id="etb-pay-client-phone" value="<?php echo esc_attr( $customer_phone ); ?>">
                        <input type="hidden" name="limo_booking_id" id="etb-pay-limo-id" value="<?php echo esc_attr( $limo_id ); ?>">

                        <!-- 1. SÉLECTEUR D'ONGLETS : 3 MODES TOUJOURS VISIBLES -->
                        <div class="etb-pay-tabs-nav" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 22px;">
                            <!-- Onglet Carte -->
                            <button type="button" class="etb-pay-tab-btn active" data-tab="card" style="display: flex; align-items: center; justify-content: center; gap: 7px; padding: 12px 8px; border-radius: 8px; border: 1.5px solid var(--etb-accent-gold, #fbac18); background: rgba(251, 172, 24, 0.1); color: var(--etb-text-secondary, #ffffff); font-size: 18px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                <span>Card</span>
                            </button>

                            <!-- Onglet Apple Pay officiel -->
                            <button type="button" class="etb-pay-tab-btn" data-tab="apple-pay" style="display: flex; align-items: center; justify-content: center; padding: 12px 8px; border-radius: 8px; border: 1px solid var(--etb-border-light, rgba(255,255,255,0.12)); background: rgba(255,255,255,0.03); color: var(--etb-text-secondary, #94a3b8); cursor: pointer; transition: all 0.2s ease;">
                                <svg viewBox="0 0 512 215" style="height: 20px; width: auto; max-width: 58px; display: block;" fill="currentColor">
                                    <path d="M93.5520633,27.1031049 C87.5513758,34.2039183 77.9502759,39.8045599 68.349176,39.0044683 C67.1490386,29.4033684 71.849577,19.2021998 77.3502072,12.901478 C83.3508946,5.6006416 93.8520976,0.400045829 102.353071,0 C103.353186,10.0011457 99.4527392,19.8022685 93.5520633,27.1031049 Z M102.25306,40.904686 C88.3514675,40.1045943 76.4501041,48.8055911 69.8493479,48.8055911 C63.1485803,48.8055911 53.0474231,41.3047318 42.0461628,41.5047547 C27.7445244,41.7047776 14.4430006,49.8057057 7.14216427,62.7071836 C-7.8595543,88.5101396 3.24171744,126.714516 17.7433787,147.716922 C24.8441922,158.118114 33.345166,169.51942 44.5464492,169.119374 C55.1476637,168.719328 59.3481449,162.218584 72.1496114,162.218584 C85.0510894,162.218584 88.7515133,169.119374 99.9527965,168.919351 C111.554126,168.719328 118.854962,158.51816 125.955775,148.116968 C134.056703,136.315616 137.357081,124.814299 137.557104,124.21423 C137.357081,124.014207 115.154538,115.513233 114.954515,89.9103 C114.754492,68.5078482 132.45652,58.3066795 133.256612,57.7066108 C123.255466,42.9049151 107.653679,41.3047318 102.25306,40.904686 Z M182.56226,11.9013634 L182.56226,167.819225 L206.765033,167.819225 L206.765033,114.513118 L240.268871,114.513118 C270.872377,114.513118 292.37484,93.5107124 292.37484,63.1072295 C292.37484,32.7037465 271.272423,11.9013634 241.068963,11.9013634 L182.56226,11.9013634 Z M206.765033,32.3037007 L234.668229,32.3037007 C255.670635,32.3037007 267.67201,43.5049839 267.67201,63.2072409 C267.67201,82.909498 255.670635,94.2107926 234.568218,94.2107926 L206.765033,94.2107926 L206.765033,32.3037007 Z M336.579904,169.019363 C351.781646,169.019363 365.883261,161.31848 372.283994,149.117083 L372.784052,149.117083 L372.784052,167.819225 L395.186618,167.819225 L395.186618,90.2103344 C395.186618,67.7077565 377.184556,53.2060952 349.481382,53.2060952 C323.778438,53.2060952 304.776261,67.9077794 304.076181,88.1100938 L325.878678,88.1100938 C327.678884,78.5089939 336.579904,72.2082721 348.781302,72.2082721 C363.582998,72.2082721 371.883949,79.1090626 371.883949,91.8105177 L371.883949,100.411503 L341.680488,102.211709 C313.577269,103.911904 298.375528,115.413222 298.375528,135.415513 C298.375528,155.617827 314.077326,169.019363 336.579904,169.019363 Z M343.080649,150.517243 C330.179171,150.517243 321.978231,144.316533 321.978231,134.815444 C321.978231,125.014321 329.879137,119.313668 344.980867,118.413565 L371.883949,116.713371 L371.883949,125.514379 C371.883949,140.116051 359.482528,150.517243 343.080649,150.517243 Z M425.090044,210.224083 C448.692748,210.224083 459.794019,201.223052 469.495131,173.919924 L512,54.7062671 L487.397182,54.7062671 L458.893916,146.816819 L458.393859,146.816819 L429.890594,54.7062671 L404.587695,54.7062671 L445.592392,168.219271 L443.39214,175.120061 C439.691716,186.821402 433.691029,191.321918 422.989803,191.321918 C421.089585,191.321918 417.389162,191.121895 415.88899,190.921872 L415.88899,209.624014 C417.28915,210.02406 423.289838,210.224083 425.090044,210.224083 Z"/>
                                </svg>
                            </button>

                            <!-- Onglet Google Pay officiel -->
                            <button type="button" class="etb-pay-tab-btn" data-tab="google-pay" style="display: flex; align-items: center; justify-content: center; padding: 12px 8px; border-radius: 8px; border: 1px solid var(--etb-border-light, rgba(255,255,255,0.12)); background: rgba(255,255,255,0.03); color: var(--etb-text-secondary, #94a3b8); cursor: pointer; transition: all 0.2s ease;">
                                <svg viewBox="0 0 2387.3 948" style="height: 20px; width: auto; max-width: 58px; display: block;">
                                    <path fill="currentColor" d="M1129.1,463.2V741h-88.2V54.8h233.8c56.4-1.2,110.9,20.2,151.4,59.4c41,36.9,64.1,89.7,63.2,144.8 c1.2,55.5-21.9,108.7-63.2,145.7c-40.9,39-91.4,58.5-151.4,58.4L1129.1,463.2L1129.1,463.2z M1129.1,139.3v239.6h147.8 c32.8,1,64.4-11.9,87.2-35.5c46.3-45,47.4-119.1,2.3-165.4c-0.8-0.8-1.5-1.6-2.3-2.3c-22.5-24.1-54.3-37.3-87.2-36.4L1129.1,139.3 L1129.1,139.3z M1692.5,256.2c65.2,0,116.6,17.4,154.3,52.2c37.7,34.8,56.5,82.6,56.5,143.2V741H1819v-65.2h-3.8 c-36.5,53.7-85.1,80.5-145.7,80.5c-51.7,0-95-15.3-129.8-46c-33.8-28.5-53-70.7-52.2-115c0-48.6,18.4-87.2,55.1-115.9 c36.7-28.7,85.7-43.1,147.1-43.1c52.3,0,95.5,9.6,129.3,28.7v-20.2c0.2-30.2-13.2-58.8-36.4-78c-23.3-21-53.7-32.5-85.1-32.1 c-49.2,0-88.2,20.8-116.9,62.3l-77.6-48.9C1545.6,286.8,1608.8,256.2,1692.5,256.2L1692.5,256.2z M1578.4,597.3 c-0.1,22.8,10.8,44.2,29.2,57.5c19.5,15.3,43.7,23.5,68.5,23c37.2-0.1,72.9-14.9,99.2-41.2c29.2-27.5,43.8-59.7,43.8-96.8 c-27.5-21.9-65.8-32.9-115-32.9c-35.8,0-65.7,8.6-89.6,25.9C1590.4,550.4,1578.4,571.7,1578.4,597.3L1578.4,597.3z M2387.3,271.5 L2093,948h-91l109.2-236.7l-193.6-439.8h95.8l139.9,337.3h1.9l136.1-337.3L2387.3,271.5z"/>
                                    <path fill="#4285F4" d="M772.8,403.2c0-26.9-2.2-53.7-6.8-80.2H394.2v151.8h212.9c-8.8,49-37.2,92.3-78.7,119.8v98.6h127.1 C729.9,624.7,772.8,523.2,772.8,403.2L772.8,403.2z"/>
                                    <path fill="#34A853" d="M394.2,788.5c106.4,0,196-34.9,261.3-95.2l-127.1-98.6c-35.4,24-80.9,37.7-134.2,37.7 c-102.8,0-190.1-69.3-221.3-162.7H42v101.6C108.9,704.5,245.2,788.5,394.2,788.5z"/>
                                    <path fill="#FBBC04" d="M172.9,469.7c-16.5-48.9-16.5-102,0-150.9V217.2H42c-56,111.4-56,242.7,0,354.1L172.9,469.7z"/>
                                    <path fill="#EA4335" d="M394.2,156.1c56.2-0.9,110.5,20.3,151.2,59.1L658,102.7C586.6,35.7,492.1-1.1,394.2,0 C245.2,0,108.9,84.1,42,217.2l130.9,101.6C204.1,225.4,291.4,156.1,394.2,156.1z"/>
                                </svg>
                            </button>
                        </div>

                       <!-- 2. VOLET APPLE PAY -->
                        <div id="etb-panel-apple-pay" class="etb-pay-panel" style="display: none; margin-bottom: 20px;">
                            <!-- Bloc actif Apple Pay avec pourboire (affiché UNIQUEMENT si Apple Pay est supporté) -->
                            <div id="etb-apple-pay-active-wrap" style="display: none;">
                                <div class="etb-chk-tip-section" style="margin-bottom: 12px;">
                                    <label class="etb-chk-tip-title" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; margin-bottom: 8px; display: block;">Driver Tip (Optional)</label>
                                    <div class="etb-chk-tip-pills">
                                        <label class="etb-tip-pill <?php echo ( 0 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="0">
                                            <input type="radio" name="etb_apple_tip" value="0" <?php checked( $saved_tip_percent, 0 ); ?>>
                                            <span>None</span>
                                        </label>
                                        <label class="etb-tip-pill <?php echo ( 10 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="10">
                                            <input type="radio" name="etb_apple_tip" value="10" <?php checked( $saved_tip_percent, 10 ); ?>>
                                            <span>10%</span>
                                        </label>
                                        <label class="etb-tip-pill <?php echo ( 15 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="15">
                                            <input type="radio" name="etb_apple_tip" value="15" <?php checked( $saved_tip_percent, 15 ); ?>>
                                            <span>15%</span>
                                        </label>
                                        <label class="etb-tip-pill <?php echo ( 20 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="20">
                                            <input type="radio" name="etb_apple_tip" value="20" <?php checked( $saved_tip_percent, 20 ); ?>>
                                            <span>20%</span>
                                        </label>
                                    </div>

                                    <!-- Émoji persistant Apple Pay (s'anime puis se fige, réactif au survol) -->
                                    <div class="etb-tip-persistent-badge" style="display: <?php echo ( $saved_tip_percent > 0 && $initial_code ) ? 'flex' : 'none'; ?>; cursor: pointer;" title="Hover to animate">
                                        <img class="etb-tip-badge-img" src="<?php echo $initial_code ? esc_url( 'https://fonts.gstatic.com/s/e/notoemoji/latest/' . $initial_code . '/512.webp' ) : ''; ?>" alt="tip-emoji">
                                    </div>

                                </div>

                                <!-- Micro-récapitulatif dynamique sous les pourboires -->
                                <div class="etb-wallet-live-total" style="margin-bottom: 14px; padding: 10px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); border-radius: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                                        <span style="font-size: 11.5px; color: var(--etb-text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Total to authorize</span>
                                        <strong class="etb-wallet-display-amount" style="font-size: 18px; font-weight: 800; color: var(--etb-accent-gold, #fbac18);"><?php echo esc_html( $formatted_amount ); ?></strong>
                                    </div>
                                    <div class="etb-wallet-tip-detail" style="display: <?php echo ( $saved_tip_amount > 0 ) ? 'block' : 'none'; ?>; font-size: 11.5px; color: #4ade80; margin-top: 3px;">
                                        • Includes <span class="etb-wallet-tip-text">+<?php echo number_format_i18n( $saved_tip_amount, 2 ); ?> <?php echo esc_html( $currency_symbol ); ?></span> driver tip (<span class="etb-wallet-tip-pct"><?php echo esc_html( $saved_tip_percent ); ?>%</span>)
                                    </div>
                                </div>

                                <div id="etb-apple-pay-mount" style="min-height: 48px;"></div>

                                <!-- Mention légale & sécurité discrète -->
                                <p class="etb-wallet-legal-notice" style="margin: 12px 0 0 0; text-align: center; font-size: 11px; color: var(--etb-text-secondary, #94a3b8); line-height: 1.4;">
                                    <span class="dashicons dashicons-shield" style="font-size: 13px; width: 13px; height: 13px; vertical-align: middle;"></span>
                                    256-Bit SSL Encrypted · By paying, you agree to <?php echo esc_html( $legal_name ); ?>'s Terms & Conditions.
                                </p>
                            </div>

                            <!-- Repli si Apple Pay n'est pas disponible -->
                            <div id="etb-apple-pay-fallback" style="display: block; padding: 26px 20px; border-radius: 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); text-align: center;">
                                <div style="display: flex; justify-content: center; margin-bottom: 14px;">
                                    <svg fill="currentColor" viewBox="0 -6 36 36" style="height: 48px; width: auto; max-width: 80px; display: block;">
                                        <path d="m33.6 24h-31.2c-1.325 0-2.4-1.075-2.4-2.4v-19.2c0-1.325 1.075-2.4 2.4-2.4h31.2c1.325 0 2.4 1.075 2.4 2.4v19.2c0 1.325-1.075 2.4-2.4 2.4zm-5.807-7.11v1.11c.159.022.342.035.528.035h.016-.001c1.394 0 2.056-.542 2.626-2.147l2.514-7.05h-1.454l-1.686 5.446h-.03l-1.686-5.446h-1.496l2.425 6.713-.13.408c-.088.549-.559.963-1.125.963-.028 0-.056-.001-.084-.003h.004c-.112-.005-.332-.018-.42-.029zm-22.08-8.836h-.026c-.886.025-1.651.519-2.058 1.241l-.006.012c-.844 1.452-.307 3.674.627 5.027.438.64.918 1.266 1.551 1.266h.034c.265-.017.51-.086.731-.197l-.011.005c.267-.134.581-.213.913-.216h.001c.321.002.624.08.891.216l-.011-.005c.215.112.468.18.737.186h.002.027c.705-.013 1.147-.66 1.538-1.23.279-.404.51-.869.672-1.366l.011-.038v-.01l-.018-.008c-.78-.355-1.313-1.125-1.318-2.021v-.001c.008-.796.427-1.492 1.055-1.887l.009-.005.018-.012c-.409-.588-1.074-.974-1.83-.994h-.003c-.035 0-.071 0-.106 0-.446.024-.862.131-1.239.307l.021-.009c-.172.084-.372.145-.583.171l-.009.001c-.228-.025-.436-.087-.626-.181l.011.005c-.293-.142-.636-.235-.997-.259h-.008zm18.113 1.816c.88 0 1.366.412 1.366 1.159v.509l-1.786.106c-1.675.101-2.56.78-2.56 1.963.012 1.108.912 2.001 2.022 2.001.084 0 .166-.005.247-.015l-.01.001c.019.001.042.001.065.001.869 0 1.629-.468 2.041-1.167l.006-.011h.03v1.102h1.325v-4.586c0-1.33-1.061-2.188-2.703-2.188-1.514 0-2.64.868-2.685 2.063h1.29c.144-.549.635-.947 1.219-.947.047 0 .094.003.14.008l-.006-.001zm-9.826-3.566v9.216h1.431v-3.152h1.977c.046.003.101.004.155.004 1.618 0 2.929-1.311 2.929-2.929 0-.041-.001-.081-.002-.121v.006c.002-.038.003-.082.003-.127 0-1.602-1.298-2.9-2.9-2.9-.048 0-.096.001-.143.003h.007zm-4.747-.704c-.594.058-1.112.34-1.477.76l-.002.003c-.333.373-.536.868-.536 1.41 0 .047.002.094.005.14v-.006c.034 0 .07.004.11.004.56-.032 1.051-.3 1.378-.707l.003-.004c.327-.387.526-.891.526-1.443 0-.055-.002-.11-.006-.165v.007zm14.236 8.901c-.758 0-1.249-.365-1.249-.929 0-.582.47-.917 1.36-.97l1.59-.1v.521c-.035.828-.715 1.485-1.548 1.485-.054 0-.107-.003-.16-.008zm-6.418-3.33h-1.644v-3.661h1.65c.07-.01.151-.016.234-.016.951 0 1.722.771 1.722 1.722 0 .042-.002.085-.005.126v-.006c.003.036.004.079.004.122 0 .954-.774 1.728-1.728 1.728-.082 0-.164-.006-.243-.017l.009.001z"/>
                                    </svg>
                                </div>
                                <strong style="display: block; font-size: 14.5px; margin-bottom: 6px; color: var(--etb-text-primary, #ffffff);">Apple Pay is not available on this device</strong>
                                <p style="font-size: 12px; color: var(--etb-text-secondary, #94a3b8); margin: 0 0 18px 0; line-height: 1.55; max-width: 440px; margin-left: auto; margin-right: auto;">
                                    Apple Pay requires the Safari browser on an iPhone, iPad, or Mac with an active Wallet card.
                                </p>
                                <button type="button" class="etb-switch-to-card-btn" style="background: var(--etb-accent-gold, #fbac18); color: #000; border: none; padding: 10px 22px; border-radius: 6px; font-weight: 700; font-size: 12.5px; cursor: pointer; transition: transform 0.15s ease;">
                                    Pay with Credit Card instead ➔
                                </button>
                            </div>
                        </div>

                        <!-- 3. VOLET GOOGLE PAY -->
                        <div id="etb-panel-google-pay" class="etb-pay-panel" style="display: none; margin-bottom: 20px;">
                            <!-- Bloc actif Google Pay avec pourboire (affiché UNIQUEMENT si Google Pay est supporté) -->
                            <div id="etb-google-pay-active-wrap" style="display: none;">
                                <div class="etb-chk-tip-section" style="margin-bottom: 12px;">
                                    <label class="etb-chk-tip-title" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; margin-bottom: 8px; display: block;">Driver Tip (Optional)</label>
                                    <div class="etb-chk-tip-pills">
                                        <label class="etb-tip-pill <?php echo ( 0 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="0">
                                            <input type="radio" name="etb_google_tip" value="0" <?php checked( $saved_tip_percent, 0 ); ?>>
                                            <span>None</span>
                                        </label>
                                        <label class="etb-tip-pill <?php echo ( 10 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="10">
                                            <input type="radio" name="etb_google_tip" value="10" <?php checked( $saved_tip_percent, 10 ); ?>>
                                            <span>10%</span>
                                        </label>
                                        <label class="etb-tip-pill <?php echo ( 15 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="15">
                                            <input type="radio" name="etb_google_tip" value="15" <?php checked( $saved_tip_percent, 15 ); ?>>
                                            <span>15%</span>
                                        </label>
                                        <label class="etb-tip-pill <?php echo ( 20 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="20">
                                            <input type="radio" name="etb_google_tip" value="20" <?php checked( $saved_tip_percent, 20 ); ?>>
                                            <span>20%</span>
                                        </label>
                                    </div>

                                    <!-- Émoji persistant Google Pay (s'anime puis se fige, réactif au survol) -->
                                    <div class="etb-tip-persistent-badge" style="display: <?php echo ( $saved_tip_percent > 0 && $initial_code ) ? 'flex' : 'none'; ?>; cursor: pointer;" title="Hover to animate">
                                        <img class="etb-tip-badge-img" src="<?php echo $initial_code ? esc_url( 'https://fonts.gstatic.com/s/e/notoemoji/latest/' . $initial_code . '/512.webp' ) : ''; ?>" alt="tip-emoji">
                                    </div>
                                    
                                </div>

                                <!-- Micro-récapitulatif dynamique sous les pourboires -->
                                <div class="etb-wallet-live-total" style="margin-bottom: 14px; padding: 10px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); border-radius: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                                        <span style="font-size: 11.5px; color: var(--etb-text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Total to authorize</span>
                                        <strong class="etb-wallet-display-amount" style="font-size: 18px; font-weight: 800; color: var(--etb-accent-gold, #fbac18);"><?php echo esc_html( $formatted_amount ); ?></strong>
                                    </div>
                                    <div class="etb-wallet-tip-detail" style="display: <?php echo ( $saved_tip_amount > 0 ) ? 'block' : 'none'; ?>; font-size: 11.5px; color: #4ade80; margin-top: 3px;">
                                        • Includes <span class="etb-wallet-tip-text">+<?php echo number_format_i18n( $saved_tip_amount, 2 ); ?> <?php echo esc_html( $currency_symbol ); ?></span> driver tip (<span class="etb-wallet-tip-pct"><?php echo esc_html( $saved_tip_percent ); ?>%</span>)
                                    </div>
                                </div>

                                <div id="etb-google-pay-mount" style="min-height: 48px;"></div>

                                <!-- Mention légale & sécurité discrète -->
                                <p class="etb-wallet-legal-notice" style="margin: 12px 0 0 0; text-align: center; font-size: 11px; color: var(--etb-text-secondary, #94a3b8); line-height: 1.4;">
                                    <span class="dashicons dashicons-shield" style="font-size: 13px; width: 13px; height: 13px; vertical-align: middle;"></span>
                                    256-Bit SSL Encrypted · By paying, you agree to <?php echo esc_html( $legal_name ); ?>'s Terms & Conditions.
                                </p>
                            </div>


                            <!-- Repli si Google Pay n'est pas disponible -->
                            <div id="etb-google-pay-fallback" style="display: block; padding: 26px 20px; border-radius: 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); text-align: center;">
                                <div style="display: flex; justify-content: center; margin-bottom: 14px;">
                                    <svg viewBox="0 0 512 110" style="height: 38px; width: auto; max-width: 170px; display: block;" preserveAspectRatio="xMidYMid">
                                        <path d="M482.684036,37.1058143 L493.629766,63.5409278 L493.758919,63.5409278 L504.446342,37.1058143 L511.999495,37.1058143 L488.957189,90.1681566 L481.789189,90.1681566 L490.366342,71.606081 L475.195459,37.1058143 L482.684036,37.1058143 Z M457.84973,35.889699 C462.96973,35.889699 467.001153,37.2338143 469.946306,39.9861602 C472.891459,42.7385062 474.362883,46.4509675 474.362883,51.251544 L474.362883,73.9741963 L467.769153,73.9741963 L467.769153,68.8535044 L467.450883,68.8535044 C464.570306,73.0781963 460.792576,75.1903116 456.055423,75.1903116 C452.024,75.1903116 448.631423,73.9741963 445.88,71.6058503 C443.12627,69.1736197 441.781694,66.1652738 441.781694,62.5808125 C441.781694,58.8044666 443.190847,55.7320053 446.136,53.4916593 C449.016576,51.251544 452.856576,50.0993134 457.656,50.0993134 C461.752,50.0993134 465.144576,50.867544 467.83373,52.3396593 L467.83373,50.7393134 C467.83373,48.3070828 466.874306,46.3229675 464.953153,44.5946215 C463.034306,42.9303909 460.792576,42.0982756 458.232576,42.0982756 C454.392576,42.0982756 451.320576,43.6986215 449.078847,46.9629675 L442.999423,43.1225062 C446.327423,38.3219296 451.320576,35.889699 457.84973,35.889699 Z M422.899964,20.0799462 C427.57254,20.0799462 431.541694,21.6161307 434.805117,24.7525458 C438.133117,27.8888918 439.733694,31.6652377 439.733694,36.0818143 C439.733694,40.6263909 438.06854,44.4668521 434.805117,47.4751981 C431.603964,50.5476593 427.637117,52.08389 422.899964,52.08389 L411.506811,52.08389 L411.506811,73.9103116 L404.592504,73.9103116 L404.592504,20.0799462 L422.899964,20.0799462 Z M458.744576,55.53989 C455.928576,55.53989 453.624576,56.2440053 451.703423,57.5881206 C449.846847,58.9963512 448.887423,60.6605819 448.887423,62.6449278 C448.887423,64.4371584 449.655423,65.9731584 451.191423,67.1253891 C452.727423,68.3415044 454.519423,68.9176197 456.567423,68.9176197 C459.448,68.9176197 462.072576,67.8295044 464.376576,65.7171584 C466.680576,63.5409278 467.769153,61.0445819 467.769153,58.1003512 C465.594306,56.3720053 462.584576,55.53989 458.744576,55.53989 Z M423.091387,26.8007765 L411.506811,26.8007765 L411.506811,45.5549675 L423.091387,45.5549675 C425.845117,45.5549675 428.149117,44.6589675 429.941117,42.8026215 C431.797694,40.9465062 432.69254,38.7702756 432.69254,36.2098143 C432.69254,33.713699 431.797694,31.5372377 429.941117,29.6811224 C428.149117,27.7608918 425.845117,26.8007765 423.091387,26.8007765 Z" fill="#8f8f8f"/>
                                        <path d="M306.91582,35.9538143 C311.588396,35.9538143 315.301549,38.0020449 317.222703,40.3062756 L317.540973,40.3062756 L317.540973,37.1060449 L325.735279,37.1060449 L325.735279,72.3101963 C325.735279,86.7758107 317.222703,92.7286179 307.109549,92.7286179 C297.635243,92.7286179 291.938666,86.327926 289.761513,81.1433494 L297.252396,78.0067729 C298.594666,81.2072341 301.860396,84.9838107 307.109549,84.9838107 C313.574126,84.9838107 317.540973,80.9512341 317.540973,73.4624269 L317.540973,70.6459657 L317.222703,70.6459657 C315.301549,73.0143116 311.588396,75.1264269 306.91582,75.1264269 C297.123243,75.1264269 288.163243,66.6133891 288.163243,55.6042359 C288.163243,44.5309675 297.123243,35.9538143 306.91582,35.9538143 Z M363.245045,36.0179296 C373.422775,36.0179296 378.351351,44.0830828 380.014198,48.4994287 L380.911351,50.7397747 L354.732468,61.5570431 C356.715892,65.4616197 359.852468,67.5098503 364.204468,67.5098503 C368.558775,67.3818503 371.630775,65.2695044 373.870198,62.0051584 L380.526198,66.4856197 C378.351351,69.686081 373.166775,75.1907729 364.204468,75.1907729 C353.067315,75.1907729 344.810739,66.6136197 344.810739,55.6044666 C344.810739,43.9548521 353.196468,36.0179296 363.245045,36.0179296 Z M171.608519,14.3198078 C180.889557,14.3198078 187.482364,17.968246 192.41094,22.7047765 L186.522248,28.5934684 C182.937787,25.2652377 178.137211,22.6408918 171.544403,22.6408918 C159.318904,22.6408918 149.717751,32.4980449 149.717751,44.723544 C149.717751,56.9488125 159.25502,66.8061963 171.544403,66.8061963 C179.481326,66.8061963 184.025903,63.605735 186.906248,60.7253891 C189.274594,58.3570431 190.810825,54.9646972 191.450825,50.2921206 L171.352519,50.2921206 L171.352519,41.9711981 L199.515748,41.9711981 C199.835863,43.4433134 199.963863,45.235544 199.963863,47.1557747 C199.963863,53.3645819 198.235517,61.1093891 192.79494,66.614081 C187.418248,72.1826575 180.633557,75.1271188 171.608519,75.1271188 C154.838443,75.1271188 140.756829,61.4933891 140.756829,44.723544 C140.756829,27.9534684 154.838443,14.3198078 171.608519,14.3198078 Z M222.497167,35.9538143 C233.314666,35.9538143 242.14782,44.2108521 242.14782,55.5401206 C242.14782,66.8055044 233.314666,75.1264269 222.497167,75.1264269 C211.679899,75.1264269 202.846746,66.8055044 202.846746,55.5401206 C202.846746,44.2108521 211.679899,35.9538143 222.497167,35.9538143 Z M265.252396,35.9538143 C276.068973,35.9538143 284.902126,44.2108521 284.902126,55.5401206 C284.902126,66.8055044 276.068973,75.1264269 265.252396,75.1264269 C254.433513,75.1264269 245.60036,66.8055044 245.60036,55.5401206 C245.60036,44.2108521 254.433513,35.9538143 265.252396,35.9538143 Z M340.523315,16.368246 L340.523315,73.9112341 L331.946162,73.9112341 L331.946162,16.368246 L340.523315,16.368246 Z M222.561283,43.6988521 C216.672591,43.6988521 211.551899,48.4994287 211.551899,55.5401206 C211.551899,62.5169278 216.672591,67.3816197 222.561283,67.3816197 C228.449975,67.3816197 233.570666,62.5169278 233.570666,55.5401206 C233.570666,48.4994287 228.449975,43.6988521 222.561283,43.6988521 Z M265.252396,43.6988521 C259.36209,43.6988521 254.24209,48.4994287 254.24209,55.5401206 C254.24209,62.5169278 259.36209,67.3816197 265.252396,67.3816197 C271.140396,67.3816197 276.260396,62.5169278 276.260396,55.5401206 C276.260396,48.4994287 271.140396,43.6988521 265.252396,43.6988521 Z M307.748396,43.6347368 C301.79582,43.6347368 296.867243,48.6913134 296.867243,55.6042359 C296.867243,62.4530431 301.860396,67.3816197 307.748396,67.3816197 C313.574126,67.3816197 318.182126,62.4530431 318.182126,55.6042359 C318.182126,48.6913134 313.574126,43.6347368 307.748396,43.6347368 Z M363.501045,43.5069675 C359.149045,43.5069675 353.067315,47.4113134 353.323315,54.9642359 L370.798198,47.6673134 C369.838775,45.2350828 366.958198,43.5069675 363.501045,43.5069675 Z" fill="#8f8f8f"/>
                                        <path d="M95.3629548,17.2958655 C85.0064863,11.3175505 71.7634449,14.8699772 65.77858,25.2264918 L50.6921079,51.3606323 C46.3267313,58.9073278 51.9465079,61.5443584 58.2129728,65.3015621 L72.7297872,73.6801422 C77.6456791,76.5157458 83.9248286,74.8323729 86.7604322,69.9229386 L102.26942,43.0653098 C107.479596,34.040272 104.387993,22.5062035 95.3629548,17.2958655 Z" fill="#EA4335"/>
                                        <path d="M78.2923674,28.1128341 L63.775553,19.7343231 C55.7618304,15.2858042 51.2237115,14.9913581 47.9335349,20.2207923 L26.523171,57.3005242 C20.5447638,67.650535 24.103625,80.8873494 34.4536358,86.8528413 C43.4786737,92.0630179 55.0129728,88.9714143 60.2231494,79.9463765 L82.043344,42.1497062 C84.8916322,37.2340449 83.2082593,30.9484377 78.2923674,28.1128341 Z" fill="#FBBC04"/>
                                        <path d="M81.0864575,9.05160457 L70.8900467,3.16291268 C59.6119782,-3.34667377 45.1911061,0.512985186 38.6815566,11.7911275 L19.2679695,45.4142828 C16.4004236,50.374917 18.1030312,56.7244089 23.0637115,59.5856125 L34.4826953,66.1784197 C40.1216142,69.4365386 47.3288214,67.5033927 50.5869403,61.8642431 L72.7655349,23.4534035 C77.3613115,15.4971772 87.5321223,12.7704543 95.4881872,17.3662078 L81.0864575,9.05160457 Z" fill="#34A853"/>
                                        <path d="M41.4394376,21.4111923 L30.4173692,15.0616312 C25.5014773,12.2325084 19.22242,13.9094929 16.3868856,18.8124925 L3.16291728,41.6633062 C-3.34667839,52.909317 0.512987482,67.2983621 11.7911321,73.7887693 L20.1825353,78.6211729 L30.3597115,84.4842648 L34.7762881,87.025353 C26.9353079,81.7768918 24.4454196,71.2603657 29.2395385,62.9777278 L32.6639421,57.0634359 L45.2030989,35.3968413 C48.0322449,30.5067801 46.3488719,24.2403152 41.4394376,21.4111923 Z" fill="#4285F4"/>
                                    </svg>
                                </div>
                                <strong style="display: block; font-size: 14.5px; margin-bottom: 6px; color: var(--etb-text-primary, #ffffff);">Google Pay is not available on this device</strong>
                                <p style="font-size: 12px; color: var(--etb-text-secondary, #94a3b8); margin: 0 0 18px 0; line-height: 1.55; max-width: 440px; margin-left: auto; margin-right: auto;">
                                    Google Pay requires Google Chrome or an Android device linked with an active Google Wallet card.
                                </p>
                                <button type="button" class="etb-switch-to-card-btn" style="background: var(--etb-accent-gold, #fbac18); color: #000; border: none; padding: 10px 22px; border-radius: 6px; font-weight: 700; font-size: 12.5px; cursor: pointer; transition: transform 0.15s ease;">
                                    Pay with Credit Card instead ➔
                                </button>
                            </div>
                        </div>

                        <!-- 3. VOLET GOOGLE PAY -->
                        <div id="etb-panel-google-pay" class="etb-pay-panel" style="display: none; margin-bottom: 20px;">
                            <div id="etb-google-pay-mount" style="min-height: 48px; display: none;"></div>
                            <div id="etb-google-pay-fallback" style="display: block; padding: 22px 18px; border-radius: 10px; background: rgba(255,255,255,0.03); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); text-align: center;">
                                <div style="font-size: 22px; font-weight: 800; margin-bottom: 8px; color: #4285F4; line-height: 1;">G Pay</div>
                                <strong style="display: block; font-size: 14px; margin-bottom: 6px; color: var(--etb-text-primary, #ffffff);">Google Pay is not available on this device</strong>
                                <p style="font-size: 12px; color: var(--etb-text-secondary, #94a3b8); margin: 0 0 16px 0; line-height: 1.5;">
                                    Google Pay requires Google Chrome or an Android device linked with an active Google Wallet card.
                                </p>
                                <button type="button" class="etb-switch-to-card-btn" style="background: var(--etb-accent-gold, #fbac18); color: #000; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer;">
                                    Pay with Credit Card instead ➔
                                </button>
                            </div>
                        </div>
                        <!-- 4. DÉBUT DU VOLET CARTE BANCAIRE (Actif par défaut) -->
                        <div id="etb-panel-card" class="etb-pay-panel">
                        
                        <!-- E-mail de confirmation -->
                        <div class="etb-chk-field">
                            <label for="etb-pay-email">Email Receipt To *</label>
                            <input type="email" id="etb-pay-email" name="client_email_input" value="<?php echo esc_attr( $customer_email ); ?>" placeholder="email@domain.com" required>
                        </div>

                        <!-- Nom sur la carte -->
                        <div class="etb-chk-field">
                            <label for="etb-pay-cardholder">Name on Card *</label>
                            <input type="text" id="etb-pay-cardholder" name="cardholder_name" value="<?php echo esc_attr( $customer_name ); ?>" placeholder="Full Name" required>
                        </div>

                       <!-- Champ Carte Bancaire : Badges au-dessus à droite (Photo 1) & Détecteur à gauche (Photo 2) -->
                        <div class="etb-chk-field">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label style="font-size: 13px; font-weight: 600; margin: 0; color: var(--etb-text-primary, #0f172a);">Card Information *</label>
                                
                                <!-- Les 3 badges colorés placés au-dessus à droite (SANS Discover) -->
                                <div class="etb-stripe-official-badges">
                                    <span class="etb-badge-card etb-badge-visa">VISA</span>
                                    <span class="etb-badge-card etb-badge-mc">
                                        <span class="mc-red"></span><span class="mc-yellow"></span>
                                    </span>
                                    <span class="etb-badge-card etb-badge-amex">AMEX</span>
                                </div>
                            </div>
                            
                            <div class="etb-stripe-split-card-box">
                                <!-- Rangée haute : Numéro de carte avec logo Stripe auto à gauche -->
                                <div class="etb-stripe-row-top">
                                    <div id="etb-card-number-mount" class="etb-stripe-inner-mount"></div>
                                </div>

                                <!-- Ligne médiane fine -->
                                <div class="etb-stripe-card-divider"></div>

                                <!-- Rangée basse : Expiration MM / YY à gauche | CVC à droite -->
                                <div class="etb-stripe-row-bottom">
                                    <div id="etb-card-expiry-mount" class="etb-stripe-inner-mount etb-mount-expiry"></div>
                                    <div class="etb-stripe-vertical-divider"></div>
                                    <div class="etb-stripe-cvc-wrap">
                                        <div id="etb-card-cvc-mount" class="etb-stripe-inner-mount etb-mount-cvc"></div>
                                        <svg class="etb-cvc-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                                            <line x1="2" y1="10" x2="22" y2="10"></line>
                                            <text x="14" y="17" font-size="5" font-weight="900" fill="currentColor" stroke="none">123</text>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        
                        
                        <!-- Pays ou région (Country or Region) - Liste mondiale exhaustive ISO -->
                        <?php
                        // Liste exhaustive des pays pour la facturation Stripe
                        $world_countries = array(
                            // 1. Pays prioritaires fréquents (Top VIP)
                            'FR' => 'France',
                            'MC' => 'Monaco',
                            'GB' => 'United Kingdom',
                            'US' => 'United States',
                            'CH' => 'Switzerland',
                            'AE' => 'United Arab Emirates',
                            'SA' => 'Saudi Arabia',
                            'QA' => 'Qatar',
                            'KW' => 'Kuwait',
                            'DE' => 'Germany',
                            'IT' => 'Italy',
                            'ES' => 'Spain',
                            'BE' => 'Belgium',
                            'LU' => 'Luxembourg',
                            'NL' => 'Netherlands',
                            'AT' => 'Austria',
                            'CA' => 'Canada',
                            'AU' => 'Australia',
                            'SG' => 'Singapore',
                            'HK' => 'Hong Kong',
                            'JP' => 'Japan',
                            'IL' => 'Israel',
                            'MG' => 'Madagascar',

                            // 2. Reste du monde par ordre alphabétique
                            'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra',
                            'AO' => 'Angola', 'AG' => 'Antigua & Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia',
                            'AW' => 'Aruba', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain',
                            'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus', 'BZ' => 'Belize',
                            'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan', 'BO' => 'Bolivia',
                            'BA' => 'Bosnia & Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil', 'BN' => 'Brunei',
                            'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'KH' => 'Cambodia',
                            'CM' => 'Cameroon', 'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic',
                            'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia',
                            'KM' => 'Comoros', 'CG' => 'Congo - Brazzaville', 'CD' => 'Congo - Kinshasa', 'CR' => 'Costa Rica',
                            'CI' => 'Côte d’Ivoire', 'HR' => 'Croatia', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic',
                            'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic',
                            'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
                            'EE' => 'Estonia', 'SZ' => 'Eswatini', 'ET' => 'Ethiopia', 'FJ' => 'Fiji',
                            'FI' => 'Finland', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia',
                            'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland',
                            'GD' => 'Grenada', 'GT' => 'Guatemala', 'GN' => 'Guinea', 'GY' => 'Guyana',
                            'HT' => 'Haiti', 'HN' => 'Honduras', 'HU' => 'Hungary', 'IS' => 'Iceland',
                            'IN' => 'India', 'ID' => 'Indonesia', 'IQ' => 'Iraq', 'IE' => 'Ireland',
                            'JM' => 'Jamaica', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya',
                            'KR' => 'South Korea', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia',
                            'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya',
                            'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'MO' => 'Macao', 'MW' => 'Malawi',
                            'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta',
                            'MH' => 'Marshall Islands', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MX' => 'Mexico',
                            'MD' => 'Moldova', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MA' => 'Morocco',
                            'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia', 'NP' => 'Nepal',
                            'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria',
                            'MK' => 'North Macedonia', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan',
                            'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru',
                            'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania',
                            'RW' => 'Rwanda', 'KN' => 'Saint Kitts & Nevis', 'LC' => 'Saint Lucia', 'VC' => 'Saint Vincent',
                            'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'São Tomé & Príncipe', 'SN' => 'Senegal',
                            'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SK' => 'Slovakia',
                            'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa',
                            'LK' => 'Sri Lanka', 'SR' => 'Suriname', 'SE' => 'Sweden', 'TW' => 'Taiwan',
                            'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TG' => 'Togo',
                            'TO' => 'Tonga', 'TT' => 'Trinidad & Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
                            'TM' => 'Turkmenistan', 'UG' => 'Uganda', 'UA' => 'Ukraine', 'UY' => 'Uruguay',
                            'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VE' => 'Venezuela', 'VN' => 'Vietnam',
                            'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
                        );
                        ?>
                        <div class="etb-chk-field" style="margin-top: 16px;">
                            <label style="font-size: 13px; font-weight: 600;">Country or Region *</label>
                            <input type="hidden" name="card_country" id="etb-pay-card-country" value="FR">
                        
                            <div class="etb-custom-select" id="etb-pay-country-select">
                                <div class="etb-custom-select-trigger" tabindex="0" role="combobox" aria-haspopup="listbox">
                                    <span id="etb-pay-country-label">France</span>
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </div>
                                <div class="etb-custom-select-options">
                                    <?php foreach ( $world_countries as $code => $name ) : ?>
                                        <div class="etb-custom-option <?php echo ( 'FR' === $code ) ? 'selected' : ''; ?>" data-val="<?php echo esc_attr( $code ); ?>">
                                            <?php echo esc_html( $name ); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sélecteur de Pourboire Chauffeur (Positionné au trait rouge juste au-dessus du texte légal) -->
                        <div class="etb-chk-tip-section" style="margin-top: 20px; margin-bottom: 16px;">
                            <label class="etb-chk-tip-title" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; margin-bottom: 8px; display: block;">Driver Tip (Optional)</label>
                            <div class="etb-chk-tip-pills">
                                <label class="etb-tip-pill <?php echo ( 0 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="0">
                                    <input type="radio" name="etb_pay_driver_tip" value="0" <?php checked( $saved_tip_percent, 0 ); ?>>
                                    <span>None</span>
                                </label>
                                <label class="etb-tip-pill <?php echo ( 10 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="10">
                                    <input type="radio" name="etb_pay_driver_tip" value="10" <?php checked( $saved_tip_percent, 10 ); ?>>
                                    <span>10%</span>
                                </label>
                                <label class="etb-tip-pill <?php echo ( 15 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="15">
                                    <input type="radio" name="etb_pay_driver_tip" value="15" <?php checked( $saved_tip_percent, 15 ); ?>>
                                    <span>15%</span>
                                </label>
                                <label class="etb-tip-pill <?php echo ( 20 === $saved_tip_percent ) ? 'active' : ''; ?>" data-tip="20">
                                    <input type="radio" name="etb_pay_driver_tip" value="20" <?php checked( $saved_tip_percent, 20 ); ?>>
                                    <span>20%</span>
                                </label>
                            </div>


                            <!-- Émoji persistant sous les pilules (dynamique et interactif au survol) -->
                            <?php
                            $noto_initial_codes = array( 10 => '1f64f', 15 => '1f929', 20 => '1f60d' );
                            $initial_code       = $noto_initial_codes[ $saved_tip_percent ] ?? '';
                            ?>
                            <div class="etb-tip-persistent-badge" style="display: <?php echo ( $saved_tip_percent > 0 && $initial_code ) ? 'flex' : 'none'; ?>; cursor: pointer;" title="Hover to animate">
                                <img class="etb-tip-badge-img" src="<?php echo $initial_code ? esc_url( 'https://fonts.gstatic.com/s/e/notoemoji/latest/' . $initial_code . '/512.webp' ) : ''; ?>" alt="tip-emoji">
                            </div>

                        </div>

                        <!-- Mandat légal en anglais avec nom légal dynamique (Point 3) -->
                        <div class="etb-payment-disclaimer-text" style="margin-top: 14px; margin-bottom: 22px;">
                            <p style="font-size: 11.5px; color: var(--etb-text-secondary, #64748b); line-height: 1.5; margin: 0;">
                                By providing your payment card information, you authorize <strong><?php echo esc_html( $legal_name ); ?></strong> to charge your card for future payments in accordance with its terms and conditions.
                            </p>
                        </div>

                        

                        <!-- Feedback d'erreur -->
                        <div class="etb-chk-feedback" id="etb-standalone-pay-feedback" style="display: none; margin-top: 15px;"></div>

                        <!-- Bouton Payer plein format -->
                        <button type="button" class="etb-chk-submit-btn" id="etb-standalone-pay-btn" style="margin-top: 24px; padding: 16px 20px; font-size: 16px;">
                            <span id="etb-standalone-pay-text">Pay <?php echo esc_html( $formatted_amount ); ?></span>
                            <span class="dashicons dashicons-lock"></span>
                        </button>

                        <p class="etb-chk-ssl-notice" style="margin-top: 16px;">
                            <span class="dashicons dashicons-shield"></span>
                            Encrypted 256-Bit SSL Connection — Powered by Stripe
                        </p>
                        </div> <!-- Fin #etb-panel-card -->
                    </form>
                </div>
            </div>

        </div> <!-- Fin .etb-checkout-layout -->
    <?php endif; ?>

</div>