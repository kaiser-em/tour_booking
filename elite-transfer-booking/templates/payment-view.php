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
            <h2 style="color: #4ade80; font-size: 22px; font-weight: 800; margin: 0 0 8px 0;">Mission Already Settled</h2>
            <p style="font-size: 14px; margin-bottom: 20px;">
                Dossier #<?php echo esc_html( $booking_id ); ?> has been paid in full and confirmed in dispatch.
            </p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="etb-chk-submit-btn" style="text-decoration: none; display: inline-flex; width: auto; padding: 12px 30px;">
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

                    <!-- Référence du dossier -->
                    <div style="font-size: 12px; color: var(--etb-text-secondary, #94a3b8); border-top: 1px dashed var(--etb-border-light, rgba(255,255,255,0.08)); padding-top: 12px;">
                        <span>Booking Reference: <strong style="color: var(--etb-text-primary, #ffffff);">#<?php echo esc_html( $booking_id ); ?></strong></span>
                        <?php if ( ! empty( $limo_id ) ) : ?>
                            <span style="display: block; margin-top: 2px;">LimoExpress Mission: <strong style="color: var(--etb-text-primary, #ffffff);">#<?php echo esc_html( $limo_id ); ?></strong></span>
                        <?php endif; ?>
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

                        

                        <!-- Pays ou région (Country or Region) en anglais -->
                        <div class="etb-chk-field" style="margin-top: 16px;">
                            <label style="font-size: 13px; font-weight: 600;">Country or Region *</label>
                            <input type="hidden" name="card_country" id="etb-pay-card-country" value="FR">
                            <div class="etb-custom-select" id="etb-pay-country-select">
                                <div class="etb-custom-select-trigger">
                                    <span id="etb-pay-country-label">France</span>
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </div>
                                <div class="etb-custom-select-options">
                                    <div class="etb-custom-option selected" data-val="FR">France</div>
                                    <div class="etb-custom-option" data-val="MC">Monaco</div>
                                    <div class="etb-custom-option" data-val="US">United States</div>
                                    <div class="etb-custom-option" data-val="GB">United Kingdom</div>
                                    <div class="etb-custom-option" data-val="CH">Switzerland</div>
                                    <div class="etb-custom-option" data-val="DE">Germany</div>
                                    <div class="etb-custom-option" data-val="IT">Italy</div>
                                    <div class="etb-custom-option" data-val="BE">Belgium</div>
                                    <div class="etb-custom-option" data-val="AE">United Arab Emirates</div>
                                    <div class="etb-custom-option" data-val="CA">Canada</div>
                                    <div class="etb-custom-option" data-val="MG">Madagascar</div>
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
                    </form>
                </div>
            </div>

        </div> <!-- Fin .etb-checkout-layout -->
    <?php endif; ?>

</div>