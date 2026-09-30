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
    echo '=== DIAGNOSTIC WORDPRESS ===<br>';
    echo 'Booking ID WP : ' . esc_html( $booking_id ) . '<br>';
    echo 'Post Type : ' . esc_html( get_post_type( $booking_id ) ) . '<br>';
    echo '_etb_total_price en base : ' . var_export( get_post_meta( $booking_id, '_etb_total_price', true ), true ) . '<br>';
    echo '_etb_limo_status en base : ' . var_export( get_post_meta( $booking_id, '_etb_limo_status', true ), true ) . '<br>';
    echo '_etb_limo_booking_id en base : ' . var_export( get_post_meta( $booking_id, '_etb_limo_booking_id', true ), true ) . '<br>';
    echo '_etb_limo_uuid en base : ' . var_export( get_post_meta( $booking_id, '_etb_limo_uuid', true ), true ) . '<br>';
    echo '</div>';
}



// 2. Contrôle de sécurité cryptographique anti-falsification
$url_amount      = isset( $_GET['amount'] ) ? floatval( $_GET['amount'] ) : 0.0;
$url_signature   = sanitize_text_field( $_GET['sig'] ?? '' );
$is_tampered     = false;
$settled_amount  = 0.0;

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

            // LimoExpress fait foi absolue : on adopte le montant exact de Limo (qu'il soit 2400 € ou 0 €)
            $settled_amount = $live_limo_price;
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

// 4. Récupération des détails de la réservation
if ( $has_valid_booking && ! $is_tampered ) {
    $customer_name  = get_post_meta( $booking_id, '_etb_customer_name', true ) ?: ( $pay_data['client_name'] ?? 'VIP Client' );
    $customer_email = get_post_meta( $booking_id, '_etb_customer_email', true ) ?: ( $pay_data['client_email'] ?? '' );
    $customer_phone = get_post_meta( $booking_id, '_etb_customer_phone', true ) ?: ( $pay_data['client_phone'] ?? '' );
    $pickup_address = get_post_meta( $booking_id, '_etb_pickup_address', true ) ?: ( $pay_data['pickup'] ?? '—' );
    $dropoff_info   = get_post_meta( $booking_id, '_etb_dropoff_info', true ) ?: ( $pay_data['dropoff'] ?? '—' );
    $booking_date   = get_post_meta( $booking_id, '_etb_booking_date', true ) ?: ( $pay_data['date'] ?? '—' );
    $booking_time   = get_post_meta( $booking_id, '_etb_booking_time', true ) ?: ( $pay_data['time'] ?? '—' );
    $flight_number  = get_post_meta( $booking_id, '_etb_flight_number', true );
    $limo_id        = get_post_meta( $booking_id, '_etb_limo_booking_id', true ) ?: ( $pay_data['limo_id'] ?? '' );
    
    $vehicles = get_post_meta( $booking_id, '_etb_vehicles', true ) ?: array();
    $vehicle_title = 'VIP Chauffeured Vehicle';
    if ( ! empty( $vehicles ) && is_array( $vehicles ) ) {
        foreach ( $vehicles as $v_id => $q ) {
            if ( $q > 0 ) {
                $vehicle_title = get_the_title( $v_id );
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
    $booking_date    = '—';
    $booking_time    = '—';
    $flight_number   = '';
    $limo_id         = '';
    $vehicle_title   = 'VIP Chauffeured Vehicle';
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
                                <span class="etb-payment-brand-sub" style="font-size: 11px; color: #fbac18; text-transform: uppercase; letter-spacing: 0.14em; font-weight: 800; margin-top: 3px;"><?php echo esc_html( $sub_brand ); ?></span>
                            </div>
                        </div>

                        <!-- Adresse physique de l'entreprise (Point 2) -->
                        <div class="etb-payment-company-address" style="text-align: right;">
                            <p style="margin: 0; font-size: 12px; color: var(--etb-text-secondary, #94a3b8); font-weight: 500; line-height: 1.4;"><?php echo esc_html( $addr_line1 ); ?></p>
                            <p style="margin: 0; font-size: 12px; color: var(--etb-text-secondary, #94a3b8); font-weight: 500; line-height: 1.4;"><?php echo esc_html( $addr_line2 ); ?></p>
                        </div>
                    </div>

                    <!-- Montant principal en très gros -->
                    <div style="margin-bottom: 24px;">
                        <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--etb-text-secondary, #94a3b8); letter-spacing: 0.05em;">Total Amount</span>
                        <div style="font-size: 38px; font-weight: 900; color: var(--etb-accent-gold, #fbac18); letter-spacing: -1px; margin-top: 2px;">
                            <?php echo esc_html( $formatted_amount ); ?>
                        </div>
                    </div>

                    <!-- Détails de la mission -->
                    <div style="border-top: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); padding-top: 20px; margin-bottom: 20px;">
                        <h4 style="font-size: 16px; font-weight: 800; margin: 0 0 6px 0; color: var(--etb-text-primary, #ffffff);"><?php echo esc_html( $vehicle_title ); ?></h4>
                        <p style="font-size: 13px; color: var(--etb-text-secondary, #94a3b8); margin: 0 0 16px 0;">
                            Passenger: <strong style="color: var(--etb-text-primary, #ffffff);"><?php echo esc_html( $customer_name ); ?></strong>
                        </p>

                        <!-- Timeline trajet -->
                        <div class="etb-chk-summary-timeline">
                            <div class="etb-chk-timeline-item">
                                <span class="etb-chk-bullet etb-bullet-pickup"></span>
                                <div class="etb-chk-timeline-text">
                                    <small>PICKUP</small>
                                    <strong><?php echo esc_html( $pickup_address ); ?></strong>
                                </div>
                            </div>
                            <div class="etb-chk-timeline-item">
                                <span class="etb-chk-bullet etb-bullet-dropoff"></span>
                                <div class="etb-chk-timeline-text">
                                    <small>DROP-OFF / SERVICE</small>
                                    <strong><?php echo esc_html( $dropoff_info ); ?></strong>
                                </div>
                            </div>
                            <div class="etb-chk-timeline-item">
                                <span class="etb-chk-calendar-icon">
                                    <span class="dashicons dashicons-calendar-alt" style="color: #fbac18; font-size: 15px;"></span>
                                </span>
                                <div class="etb-chk-timeline-text">
                                    <small>DATE & TIME</small>
                                    <strong><?php echo esc_html( $booking_date . ' at ' . $booking_time ); ?></strong>
                                </div>
                            </div>
                            <?php if ( ! empty( $flight_number ) ) : ?>
                                <div class="etb-chk-timeline-item">
                                    <span class="dashicons dashicons-airplane" style="color: #fbac18; font-size: 15px; margin-left: 2px;"></span>
                                    <div class="etb-chk-timeline-text">
                                        <small>FLIGHT</small>
                                        <strong><?php echo esc_html( $flight_number ); ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Référence du dossier -->
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

                        <!-- Nom du titulaire (Name on Card) en anglais -->
                        <div class="etb-chk-field" style="margin-top: 16px;">
                            <label for="etb-pay-cardholder" style="font-size: 13px; font-weight: 600;">Name on Card *</label>
                            <input type="text" id="etb-pay-cardholder" name="cardholder_name" value="<?php echo esc_attr( $customer_name ); ?>" placeholder="Full Name" required>
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

                        <!-- Mandat légal en anglais avec nom légal dynamique (Point 3) -->
                        <div class="etb-payment-disclaimer-text" style="margin-top: 18px; margin-bottom: 22px;">
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