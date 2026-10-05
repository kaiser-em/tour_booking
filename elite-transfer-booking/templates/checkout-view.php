<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Récupération des options générales
$gen_settings    = get_option( 'etb_general_settings', array() );
$currency_symbol = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';

// Jetons de sécurité anti-spam
$sec_token = class_exists( 'ETB_Security' ) ? ETB_Security::generate_timestamp_token() : array( 'time' => time(), 'token' => '' );

// ── LECTURE SÉCURISÉE DU QUOTE TOKEN SERVEUR (?ref=q_XXXX) ──
$ref_token  = ! empty( $_GET['ref'] ) ? sanitize_key( $_GET['ref'] ) : '';
$quote_data = ! empty( $ref_token ) ? get_transient( 'etb_quote_' . $ref_token ) : false;

$has_quote = ( is_array( $quote_data ) && ! empty( $quote_data['vehicle_id'] ) );

// Données du trajet scellées par le serveur
$trip_mode     = $has_quote ? ( $quote_data['mode'] ?? 'transfer' ) : 'transfer';
$pickup_addr   = $has_quote ? ( $quote_data['pickup'] ?? '' ) : '';
$dropoff_addr  = $has_quote ? ( $quote_data['dropoff'] ?? '' ) : '';
$duration_val  = $has_quote ? ( $quote_data['duration'] ?? 4 ) : 4;
$date_val      = $has_quote ? ( $quote_data['date'] ?? '' ) : '';
$time_val      = $has_quote ? ( $quote_data['time'] ?? '' ) : '';
$vehicle_id    = $has_quote ? ( $quote_data['vehicle_id'] ?? 0 ) : 0;
$vehicle_name  = $has_quote ? ( $quote_data['vehicle_name'] ?? '' ) : 'Mercedes-Benz VIP Fleet';
$vehicle_img   = $has_quote ? ( $quote_data['vehicle_img'] ?? '' ) : '';
$pax_count     = $has_quote ? ( $quote_data['pax'] ?? 3 ) : 3;
$bag_count     = $has_quote ? ( $quote_data['bag'] ?? 2 ) : 2;
$price_val     = $has_quote ? ( $quote_data['price'] ?? 0 ) : 0;

$formatted_price = ( 'Custom Quote' === $price_val || floatval( $price_val ) <= 0 )
    ? 'Custom Quote'
    : ( number_format_i18n( floatval( $price_val ), 0 ) . ' ' . $currency_symbol );


// Formatage de la date en anglais (ex: 03 Oct. 2026 at 09:00 AM)
$formatted_datetime_display = '—';
if ( ! empty( $date_val ) ) {
    $dt_timestamp = strtotime( $date_val );
    $formatted_datetime_display = $dt_timestamp ? date( 'd M. Y', $dt_timestamp ) : $date_val;
    if ( ! empty( $time_val ) ) {
        $formatted_datetime_display .= ' at ' . $time_val;
    }
}

// Détection d'une réservation urgente (< délai minimum configuré, par défaut 24h)
$min_delay_hours   = ! empty( $gen_settings['min_delay'] ) ? absint( $gen_settings['min_delay'] ) : 24;
$is_urgent_booking = false;

if ( ! empty( $date_val ) ) {
    $time_raw = ! empty( $time_val ) ? trim( $time_val ) : '09:00';
    $pickup_timestamp = strtotime( $date_val . ' ' . $time_raw );
    
    // Fallback si le format est standard HH:MM
    if ( ! $pickup_timestamp ) {
        $pickup_timestamp = strtotime( $date_val . ' ' . substr( $time_raw, 0, 5 ) . ':00' );
    }

    // Comparaison temporelle absolue (UTC vs UTC)
    $now_timestamp = time();

    if ( $pickup_timestamp && ( $pickup_timestamp - $now_timestamp ) >= 0 && ( $pickup_timestamp - $now_timestamp ) < ( $min_delay_hours * HOUR_IN_SECONDS ) ) {
        $is_urgent_booking = true;
    }
}
?>

<div class="etb-checkout-wrapper" id="etb-checkout-app">
    
    <!-- En-tête de retour & Switcher Thème -->
    <div class="etb-checkout-top-nav" style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <a href="javascript:history.back();" class="etb-checkout-back-link">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <span>Edit Ride Details</span>
            </a>
            <h1 class="etb-checkout-page-title">Finalize Your VIP Reservation</h1>
        </div>

        <!-- BOUTON SWITCHER THÈME (PILULE COULISSANTE VIP) -->
        <button type="button" class="etb-theme-toggle-btn etb-theme-switch" aria-label="Basculer le thème">
            <span class="etb-switch-track">
                <svg class="etb-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg class="etb-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
                <span class="etb-switch-thumb"></span>
            </span>
        </button>
    </div>

    <!-- Formulaire englobant les 2 colonnes -->
    <form id="etb-checkout-form" onsubmit="return false;">
        
        <!-- Champs de sécurité invisibles immunisés contre l'Autofill -->
        <div style="position: absolute !important; left: -9999px !important; opacity: 0 !important; width: 0 !important; height: 0 !important; overflow: hidden !important;" aria-hidden="true">
            <input type="text" name="etb_antibot_check" value="" tabindex="-1" autocomplete="new-password" aria-hidden="true">
            <input type="hidden" name="etb_sec_time" value="<?php echo esc_attr( $sec_token['time'] ); ?>">
            <input type="hidden" name="etb_sec_token" value="<?php echo esc_attr( $sec_token['token'] ); ?>">
        </div>

        <!-- Données de trajet scellées côté serveur -->
        <input type="hidden" name="etb_quote_ref" id="etb-chk-quote-ref" value="<?php echo esc_attr( $ref_token ); ?>">
        <input type="hidden" name="etb_trip_mode" id="etb-chk-mode" value="<?php echo esc_attr( $trip_mode ); ?>">
        <input type="hidden" name="etb_pickup_address" id="etb-chk-pickup" value="<?php echo esc_attr( $pickup_addr ); ?>">
        <input type="hidden" name="etb_dropoff_address" id="etb-chk-dropoff" value="<?php echo esc_attr( $dropoff_addr ); ?>">
        <input type="hidden" name="etb_duration" id="etb-chk-duration" value="<?php echo esc_attr( $duration_val ); ?>">
        <input type="hidden" name="etb_date" id="etb-chk-date" value="<?php echo esc_attr( $date_val ); ?>">
        <input type="hidden" name="etb_time" id="etb-chk-time" value="<?php echo esc_attr( $time_val ); ?>">
        <input type="hidden" name="etb_vehicle_id" id="etb-chk-vehicle-id" value="<?php echo esc_attr( $vehicle_id ); ?>">
        <input type="hidden" name="etb_calculated_price" id="etb-chk-price" value="<?php echo esc_attr( $price_val ); ?>">
        <input type="hidden" name="etb_is_urgent" id="etb-chk-is-urgent" value="<?php echo $is_urgent_booking ? '1' : '0'; ?>">

        <!-- ============================================================== -->
        <!-- LE CONTENEUR 2 COLONNES (CSS GRID)                             -->
        <!-- ============================================================== -->
        <div class="etb-checkout-layout">
            
            <!-- ────────────────────────────────────────────────────────── -->
            <!-- COLONNE 1 : GAUCHE (Formulaire en 2 Étapes in-place)       -->
            <!-- ────────────────────────────────────────────────────────── -->
            <div class="etb-checkout-left-col">
                
                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- ÉTAPE 1 : COORDONNÉES, VOL ET DEMANDES DU PASSAGER         -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div class="etb-chk-step-panel" id="etb-chk-step-1">
                    
                    <!-- SECTION 1 : ACCUEIL AÉROPORT INTELLIGENT -->
                    <div class="etb-checkout-card" id="etb-chk-airport-card" style="display: none;">
                        <div class="etb-checkout-card-header">
                            <span class="etb-chk-badge-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fbac18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>
                                </svg>
                            </span>
                            <div>
                                <h3 id="etb-chk-airport-title">Airport Arrival & Greeting</h3>
                                <p id="etb-chk-airport-desc">Real-time flight tracking included. Your chauffeur adjusts pickup time automatically in case of flight delays.</p>
                            </div>
                        </div>
                        
                        <div id="etb-chk-airport-grid" class="etb-chk-grid-2">
                            <div class="etb-chk-field" id="etb-chk-flight-field">
                                <label for="etb-flight-number" id="etb-chk-flight-label"> Flight Number</label>
                                <input type="text" name="etb_flight_number" id="etb-flight-number" placeholder="e.g. BA 342, DL 401..." autocomplete="off">
                                <small class="etb-chk-hint" id="etb-chk-flight-hint">1 hour free waiting time included after landing.</small>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2 : WHO IS RIDING? -->
                    <div class="etb-checkout-card">
                        <div class="etb-checkout-card-header">
                            <span class="etb-chk-badge-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fbac18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </span>
                            <div>
                                <h3>Passenger Information</h3>
                                <p>Select whether you are riding yourself or booking on behalf of someone else.</p>
                            </div>
                        </div>

                        <!-- Bascule Myself vs Guest -->
                        <div class="etb-chk-booker-toggle">
                            <label class="etb-chk-radio-pill active" id="etb-toggle-myself">
                                <input type="radio" name="etb_booker_type" value="myself" checked>
                                <span class="dashicons dashicons-admin-users"></span>
                                <span>I am the passenger</span>
                            </label>
                            <label class="etb-chk-radio-pill" id="etb-toggle-guest">
                                <input type="radio" name="etb_booker_type" value="guest">
                                <span class="dashicons dashicons-businessman"></span>
                                <span>Booking for someone else (Guest)</span>
                            </label>
                        </div>

                        <!-- Coordonnées passager principal -->
                        <div class="etb-chk-grid-2" style="margin-top: 18px;">
                            <div class="etb-chk-field">
                                <label for="etb-passenger-first-name">First Name *</label>
                                <input type="text" name="etb_first_name" id="etb-passenger-first-name" placeholder="First Name" required>
                            </div>
                            <div class="etb-chk-field">
                                <label for="etb-passenger-last-name">Last Name *</label>
                                <input type="text" name="etb_last_name" id="etb-passenger-last-name" placeholder="Last Name" required>
                            </div>
                        </div>

                        <div class="etb-chk-grid-2">
                            <div class="etb-chk-field">
                                <label for="etb-passenger-email">Email Address *</label>
                                <input type="email" name="etb_email" id="etb-passenger-email" placeholder="email@domain.com" required>
                                <small class="etb-chk-hint">Ride confirmation and official invoice will be sent here.</small>
                            </div>
                            <div class="etb-chk-field">
                                <label for="etb-passenger-phone">Mobile Phone *</label>
                                <input type="tel" name="etb_phone" id="etb-passenger-phone" class="etb-phone-field" placeholder="6 12 34 56 78" required>
                                <small class="etb-chk-hint">Chauffeur will send SMS or call when arriving on location.</small>
                            </div>
                            
                        </div>

                        <!-- Champ Pancarte Chauffeur (Greeting Sign) calé à 50% sous la colonne Email -->
                        <div class="etb-chk-grid-2">
                            <div class="etb-chk-field" id="etb-chk-sign-field" style="display: none; margin-top: 6px;">
                                <label for="etb-pickup-sign">Name on Chauffeur Greeting Sign</label>
                                <input type="text" name="etb_pickup_sign" id="etb-pickup-sign" placeholder="e.g. Mr. Bruce Wayne, Acme Corp..." autocomplete="off">
                                <small class="etb-chk-hint">Name displayed on the welcome sign at arrival.</small>
                            </div>
                        </div>

                        <!-- Champs additionnels Booker / Assistant -->
                        <div id="etb-guest-booker-fields" style="display: none; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 16px; margin-top: 10px;">
                            <h4 style="color: #fbac18; font-size: 13px; text-transform: uppercase; margin: 0 0 10px 0;">Booker / Assistant Information</h4>
                            <div class="etb-chk-grid-2">
                                <div class="etb-chk-field">
                                    <label for="etb-booker-name">Booker Full Name</label>
                                    <input type="text" name="etb_booker_name" id="etb-booker-name" placeholder="Assistant / Company Name">
                                </div>
                                <div class="etb-chk-field">
                                    <label for="etb-booker-email">Booker Email (for invoice copy)</label>
                                    <input type="email" name="etb_booker_email" id="etb-booker-email" placeholder="assistant@corp.com">
                                </div>
                            </div>
                        </div>

                        <!-- Sélecteur 3 colonnes : Passengers | Checked Luggage | Cabin Bags -->
                        <div class="etb-chk-capacity-box etb-chk-capacity-3col" style="margin-top: 15px;">
                            
                            <!-- 1. Passengers -->
                            <div class="etb-chk-cap-item">
                                <div class="etb-chk-cap-label">
                                    <span class="etb-cap-svg-wrap" style="color: #fbac18; display: flex; align-items: center; justify-content: center; height: 30px; width: 100%; margin-bottom: 6px; flex-shrink: 0;">
                                        <svg viewBox="0 5 100 88" style="height: 24px; width: auto; max-width: 32px; display: block;" fill="currentColor">
                                            <path d="m88.484 31.117c0 8.0312-6.5117 14.539-14.539 14.539-8.0312 0-14.543-6.5078-14.543-14.539s6.5117-14.539 14.543-14.543c8.0273 0 14.539 6.5117 14.539 14.543z"/>
                                            <path d="m0.90234 73.273c-3.0547 6.2812 2.0391 15.633 9.6914 17.562 16.133 3.6016 32.57 3.6016 48.703 0 7.6562-1.9336 12.75-11.281 9.6914-17.562-5.7109-11.867-18.844-22.293-34.047-22.391-15.203 0.10156-28.336 10.523-34.047 22.391z"/>
                                            <path d="m54.445 25.965c0 10.77-8.7305 19.504-19.5 19.504-10.77 0-19.504-8.7344-19.504-19.504 0-10.77 8.7344-19.5 19.504-19.5 10.77-0.003906 19.5 8.7305 19.5 19.5z"/>
                                            <path d="m99.328 66.391c-4.2578-8.8516-14.051-16.625-25.383-16.695-5.1719 0.03125-10.023 1.6719-14.176 4.2812 6.0273 4.4648 11.02 10.426 14.141 16.91 1.5391 3.1641 1.8438 6.8906 0.93359 10.605 5.7734-0.0625 11.539-0.73047 17.258-2.0078 5.707-1.4414 9.5078-8.4141 7.2266-13.094z"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <strong>Passengers</strong>
                                        <small id="etb-chk-max-pax-hint">Max: <?php echo esc_html( $pax_count ); ?></small>
                                    </div>
                                </div>
                                <div class="etb-chk-qty-control">
                                    <button type="button" class="etb-chk-qty-btn etb-pax-minus">-</button>
                                    <input type="number" name="etb_passengers_count" id="etb-chk-pax-input" value="1" min="1" readonly>
                                    <button type="button" class="etb-chk-qty-btn etb-pax-plus">+</button>
                                </div>
                            </div>

                            <!-- 2. Checked Luggage (Big Bags) -->
                            <div class="etb-chk-cap-item">
                                <div class="etb-chk-cap-label">
                                    <span class="etb-cap-svg-wrap" style="color: #fbac18; display: flex; align-items: center; justify-content: center; height: 30px; width: 100%; margin-bottom: 6px; flex-shrink: 0;">
                                        <svg viewBox="20 10 60 86" style="height: 24px; width: auto; max-width: 32px; display: block;" fill="currentColor">
                                            <path d="M70.75,26.75H60.028l1.056,5.476c2.108-0.315,4.109,1.073,4.517,3.185l0.868,4.5c0.418,2.169-1.001,4.267-3.171,4.685 l-1.227,0.237c-2.169,0.418-4.268-1.001-4.686-3.17l-0.867-4.5c-0.408-2.113,0.936-4.146,3.01-4.637l-1.113-5.775H54.75v-14 c0-0.019-0.01-0.034-0.011-0.052c0.002-0.032,0.01-0.062,0.01-0.095c0-0.773-0.626-1.397-1.397-1.397h-6.705 c-0.771,0-1.397,0.624-1.397,1.397c0,0.034,0.008,0.065,0.01,0.098c0,0.017-0.01,0.031-0.01,0.049v14h-16c-2.209,0-4,1.791-4,4v59 c0,2.209,1.791,4,4,4h6V94c0,0.69,0.559,1.25,1.25,1.25c0.689,0,1.25-0.56,1.25-1.25v-0.25h24.5V94c0,0.69,0.559,1.25,1.25,1.25 c0.689,0,1.25-0.56,1.25-1.25v-0.25h6c2.209,0,4-1.791,4-4v-59C74.75,28.541,72.959,26.75,70.75,26.75z M47.75,14h4.5v12.75h-4.5V14 z M63.39,84.078h-26.78c-1.027,0-1.86-0.834-1.86-1.859c0-1.028,0.833-1.859,1.86-1.859h26.78c1.026,0,1.859,0.831,1.859,1.859 C65.249,83.244,64.416,84.078,63.39,84.078z"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <strong>Checked Luggage</strong>
                                        <small>Large suitcase</small>
                                    </div>
                                </div>
                                <div class="etb-chk-qty-control">
                                    <button type="button" class="etb-chk-qty-btn etb-bag-minus">-</button>
                                    <input type="number" name="etb_luggage_count" id="etb-chk-bag-input" value="<?php echo min( 1, $bag_count ); ?>" min="0" readonly>
                                    <button type="button" class="etb-chk-qty-btn etb-bag-plus">+</button>
                                </div>
                            </div>

                            <!-- 3. Cabin Bags (Hand luggage) -->
                            <div class="etb-chk-cap-item">
                                <div class="etb-chk-cap-label">
                                    <span class="etb-cap-svg-wrap" style="color: #fbac18; display: flex; align-items: center; justify-content: center; height: 30px; width: 100%; margin-bottom: 6px; flex-shrink: 0;">
                                        <svg viewBox="0 42 401.438 318" style="height: 24px; width: auto; max-width: 32px; display: block;" fill="currentColor">
                                            <path d="M272.25,71.625c0-15.816-12.871-28.688-28.688-28.688H157.5c-15.816,0-28.688,12.871-28.688,28.688V90.75H76.5V358.5 h248.625V90.75H272.25V71.625z M253.125,90.75H147.938V71.625c0-5.279,4.284-9.562,9.562-9.562h86.062 c5.278,0,9.562,4.284,9.562,9.562L253.125,90.75L253.125,90.75z"/>
                                            <path d="M0,129v191.25c0,21.123,17.126,38.25,38.25,38.25h28.688V90.75H38.25C17.126,90.75,0,107.876,0,129z"/>
                                            <path d="M363.188,90.75H334.5V358.5h28.688c21.125,0,38.25-17.127,38.25-38.25V129C401.438,107.876,384.311,90.75,363.188,90.75z"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <strong>Cabin Bags</strong>
                                        <small>Hand luggage</small>
                                    </div>
                                </div>
                                <div class="etb-chk-qty-control">
                                    <button type="button" class="etb-chk-qty-btn etb-cabin-bag-minus">-</button>
                                    <input type="number" name="etb_cabin_bag_count" id="etb-chk-cabin-bag-input" value="0" min="0" readonly>
                                    <button type="button" class="etb-chk-qty-btn etb-cabin-bag-plus">+</button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- SECTION 3 : SPECIAL REQUESTS & CHILD SEATS -->
                    <div class="etb-checkout-card">
                        <div class="etb-checkout-card-header">
                            <span class="etb-chk-badge-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fbac18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                            </span>
                            <div>
                                <h3>Special Requests & Chauffeur Notes</h3>
                                <p>Provide specific access codes, child seating needs, or luggage preferences.</p>
                            </div>
                        </div>

                        <!-- Sièges enfants : 1. Baby Seat & 2. Booster Seat en 2 colonnes (Structure 2 Niveaux) -->
                        <div class="etb-chk-grid-2 etb-chk-seats-wrapper" style="margin-bottom: 16px;">
                            
                            <!-- 1. Baby Seat (0-2 yrs) : 1er gratuit, puis 50€ -->
                            <div class="etb-chk-child-seats-box">
                                <div class="etb-chk-seat-top-row">
                                    <div class="etb-chk-seat-info">
                                        <span class="etb-seat-svg-icon" aria-hidden="true" style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; color: #fbac18; flex-shrink: 0;">
                                            <svg viewBox="0 0 100 100" width="22" height="22" fill="currentColor">
                                                <path d="M 72.317,5.001 A 9.686,9.686 0 0 0 62.784,15.594 c 0.514,0.178 1.013,0.368 1.513,0.605 7.244,3.44 10.383,12.198 6.961,19.445 -1.041,2.187 -2.619,3.946 -4.464,5.296 l 13.052,-6.999 2.27,4.237 -15.624,8.398 c 1.599,2.867 1.884,6.389 0.378,9.571 -1.102,2.344 -6.844,14.419 -8.058,16.948 -0.833,1.747 -1.931,3.298 -3.216,4.615 l 2.459,5.372 -4.388,2.005 -2.005,-4.388 c -4.748,2.666 -10.657,3.089 -15.927,0.605 l -2.856,-1.379 c -2.856,-1.379 -4.97,-3.408 -6.62,-5.485 -1.611,-2.034 -2.391,-4.336 -3.026,-6.469 -2.387,1.562 -4.899,3.184 -6.166,4.086 -2.445,1.706 -5.524,1.91 -8.134,0.832 a 9.695,9.695 0 0 0 2.005,8.474 l 7.301,8.739 a 9.686,9.686 0 0 0 7.453,3.48 l 27.276,0 a 9.686,9.686 0 0 0 8.739,-5.561 l 24.552,-51.904 a 9.686,9.686 0 0 0 0.189,-7.869 L 81.434,11.054 A 9.686,9.686 0 0 0 72.317,5.001 z M 57.45,18.431 c -3.886,0.216 -7.558,2.522 -9.344,6.28 -2.598,5.489 -0.233,12.017 5.258,14.603 5.471,2.603 11.2,0.246 14.603,-5.221 2.588,-5.481 0.261,-11.2 -5.221,-14.603 -1.71,-0.81 -3.53,-1.157 -5.296,-1.059 z M 34.486,28.683 c -0.612,0.05 -1.2,0.225 -1.778,0.53 -2.326,1.203 -3.216,4.04 -2.005,6.356 l 5.561,10.706 c 0.604,1.149 1.628,1.989 2.875,2.345 l 5.675,1.665 -1.665,3.367 16.494,-8.852 c -0.132,-0.057 -0.275,-0.107 -0.416,-0.151 0,0 -12.451,-3.643 -15.511,-4.54 -1.289,-2.48 -4.615,-8.928 -4.615,-8.928 -0.912,-1.728 -2.779,-2.646 -4.615,-2.497 z M 63.389,48.279 46.214,57.51 53.894,74.155 c 0.623,-0.792 1.177,-1.66 1.627,-2.61 1.174,-2.446 6.977,-14.65 8.058,-16.948 0.994,-2.102 0.839,-4.424 -0.189,-6.318 z M 28.812,52.403 c -1.244,0.029 -2.496,0.402 -3.594,1.173 0,0 -9.617,6.54 -13.089,8.966 -2.181,1.55 -2.73,4.579 -1.173,6.772 1.545,2.191 4.579,2.703 6.772,1.173 2.412,-1.717 8.236,-5.39 10.517,-6.999 0,0 1.461,7.322 3.632,10.063 1.432,1.803 3.149,3.429 5.372,4.502 4.288,2.021 9.075,1.668 12.862,-0.567 L 41.031,57.926 38.307,63.449 34.222,55.127 C 32.935,53.289 30.885,52.355 28.812,52.403 z"/>
                                            </svg>
                                        </span>
                                        <div class="etb-chk-seat-text">
                                            <strong>Baby Seat</strong>
                                            <span class="etb-chk-seat-age">0–2 years</span>
                                        </div>
                                    </div>
                                    <div class="etb-chk-qty-control">
                                        <button type="button" class="etb-chk-qty-btn etb-seat-minus" aria-label="Decrease baby seats">-</button>
                                        <input type="number" name="etb_baby_seat_count" id="etb-chk-baby-seats" value="0" min="0" max="10" readonly>
                                        <button type="button" class="etb-chk-qty-btn etb-seat-plus" aria-label="Increase baby seats">+</button>
                                    </div>
                                </div>
                                <div class="etb-chk-seat-pricing-row">
                                    <span class="etb-chk-seat-badge-free">1st Free included</span>
                                    <span class="etb-chk-seat-badge-extra">+50 € per extra</span>
                                </div>
                            </div>

                            <!-- 2. Booster / Child Seat (2-10 yrs) : 2 gratuits, puis 50€ -->
                            <div class="etb-chk-child-seats-box">
                                <div class="etb-chk-seat-top-row">
                                    <div class="etb-chk-seat-info">
                                        <span class="etb-seat-svg-icon" aria-hidden="true" style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; color: #fbac18; flex-shrink: 0;">
                                            <svg viewBox="0 0 100 100" width="22" height="22" fill="currentColor">
                                                <path d="M50.673,29.234c7.425-0.008,13.452-6.03,13.452-13.451c0-7.443-6.025-13.468-13.452-13.451 C43.236,2.316,37.214,8.34,37.219,15.784C37.214,23.205,43.236,29.226,50.673,29.234z"/>
                                                <path d="M50.673,57.383H36.41V47.062L25.55,58.029c-4.921,4.921-11.581-1.379-6.645-6.32l17.989-18.043 c1.47-1.438,3.068-2.359,5.62-2.38h8.159l0,0h7.995c2.521,0.021,4.229,0.916,5.726,2.38L82.6,51.924 c4.684,4.665-2.157,11.024-6.644,6.267L64.771,47.009v10.373L50.673,57.383z M69,21.086L20.952,65.308 l3.946,4.287l48.05-44.221L69,21.086z"/>
                                                <path d="M75.764,69.482c-16.016-8.113-34.926-8.059-50.867,0.108c-0.788-1.539-1.577-3.076-2.366-4.613 c17.418-8.924,38.077-8.983,55.574-0.122C77.325,66.398,76.545,67.94,75.764,69.482z"/>
                                                <path d="M39.219,65.945l7.887,7.886l-7.831,7.83l8.048,8.053c4.731,4.739-1.815,11.53-6.701,6.645l-12.75-13.234 c-2.721-2.801-3.476-7.944-0.539-10.858c0-0.011,1.96-1.982,4.06-4.092C31.393,68.174,35.177,66.692,39.219,65.945z"/>
                                                <path d="M69.791,68.174c2.099,2.109,4.06,4.081,4.06,4.092c2.935,2.914,2.183,8.058-0.539,10.858L60.561,96.358 c-4.885,4.886-11.432-1.905-6.7-6.645l8.048-8.053l-7.831-7.83l7.886-7.886C66.006,66.692,69.791,68.174,69.791,68.174z"/>
                                                <path d="M21.251,72.781c-0.491,0.429-1.236,0.378-1.664-0.115l-2.284-2.619c-0.428-0.49-0.377-1.235,0.114-1.664 l2.241-1.953c0.491-0.429,0.649-0.419,1.078,0.074l2.83,3.246c0.428,0.491,0.417,0.649-0.073,1.077L21.251,72.781z M22.132,70.187 c0.259-0.229,0.286-0.62,0.06-0.878l-1.204-1.383c-0.226-0.259-0.62-0.286-0.879-0.061l-1.016,0.887 c-0.259,0.225-0.287,0.619-0.06,0.878l1.204,1.382c0.226,0.26,0.62,0.286,0.878,0.061L22.132,70.187z"/>
                                            </svg>
                                        </span>
                                        <div class="etb-chk-seat-text">
                                            <strong>Booster Seat</strong>
                                            <span class="etb-chk-seat-age">2–10 years</span>
                                        </div>
                                    </div>
                                    <div class="etb-chk-qty-control">
                                        <button type="button" class="etb-chk-qty-btn etb-booster-minus" aria-label="Decrease booster seats">-</button>
                                        <input type="number" name="etb_booster_seat_count" id="etb-chk-booster-seats" value="0" min="0" max="10" readonly>
                                        <button type="button" class="etb-chk-qty-btn etb-booster-plus">+</button>
                                    </div>
                                </div>
                                <div class="etb-chk-seat-pricing-row">
                                    <span class="etb-chk-seat-badge-free">2 Free included</span>
                                    <span class="etb-chk-seat-badge-extra">+50 € per extra</span>
                                </div>
                            </div>

                        </div>

                        <div class="etb-chk-field" style="margin-top: 15px;">
                            <label for="etb-chauffeur-notes">Notes for the Chauffeur (Optional)</label>
                            <textarea name="etb_notes" id="etb-chauffeur-notes" rows="3" placeholder="Hotel room number, gate entry code, assistance with heavy luggage..."></textarea>
                        </div>

                        <div class="etb-chk-field">
                            <label for="etb-cost-center">Billing Reference / Cost Center (Optional)</label>
                            <input type="text" name="etb_cost_center" id="etb-cost-center" placeholder="e.g. PO-84920, Project Alpha...">
                            <small class="etb-chk-hint">Will appear on your official printable PDF invoice.</small>
                        </div>
                    </div>

                </div> <!-- Fin #etb-chk-step-1 -->

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- ÉTAPE 2 : MODULE DE PAIEMENT STRIPE (SANS SUMMARY GAUCHE)  -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div class="etb-chk-step-panel" id="etb-chk-step-2" style="display: none;">
                    
                    <a href="#" class="etb-chk-step-back-link" id="etb-chk-back-to-step1">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <span>Back to Passenger Details</span>
                    </a>

                    <div class="etb-checkout-card">
                        
                        <!-- En-tête EDEN CAB -->
                        <div class="etb-payment-company-header">
                            <div class="etb-payment-logo-wrap">
                                <span class="etb-payment-brand-title">EDEN CAB</span>
                                <span class="etb-payment-brand-sub">superior drive</span>
                            </div>
                            <div class="etb-payment-company-address">
                                <p>250 avenue de Grasse</p>
                                <p>Cannes, 06400 France</p>
                            </div>
                        </div>

                        <!-- Formulaire Carte Bancaire -->
                        <div class="etb-payment-card-fields" style="margin-top: 15px;">
                            <h3 class="etb-payment-section-title">Credit Card Information</h3>
                            
                            <!-- Nom sur la carte (Name on Card) -->
                            <div class="etb-chk-field">
                                <label for="etb-cardholder-name">Name on Card *</label>
                                <input type="text" id="etb-cardholder-name" name="etb_cardholder_name" placeholder="e.g. Bruce Wayne" autocomplete="cc-name" required>
                            </div>

                            <!-- Boîtier sécurisé Stripe Elements -->
                            <div class="etb-chk-field">
                                <div class="etb-card-label-row">
                                    <label>Credit or Debit Card *</label>
                                    <div class="etb-card-brand-icons">
                                        <span class="etb-brand-badge visa">VISA</span>
                                        <span class="etb-brand-badge mc">MC</span>
                                        <span class="etb-brand-badge amex">AMEX</span>
                                    </div>
                                </div>
                                <div id="etb-stripe-card-mount" class="etb-stripe-mount-box"></div>
                            </div>

                            <!-- Sélecteur Pays Custom (100% Dark Mode) -->
                            <div class="etb-chk-field">
                                <label>Billing Country *</label>
                                <input type="hidden" name="etb_card_country" id="etb-card-country" value="FR">
                                
                                <div class="etb-custom-select" id="etb-country-custom-select">
                                    <div class="etb-custom-select-trigger">
                                        <span id="etb-country-selected-label">France</span>
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
                                        <div class="etb-custom-option" data-val="LU">Luxembourg</div>
                                        <div class="etb-custom-option" data-val="AE">United Arab Emirates</div>
                                        <div class="etb-custom-option" data-val="CA">Canada</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mandat d'autorisation différée EDEN CAB -->
                            <div class="etb-payment-mandate-box">
                                <label class="etb-mandate-label">
                                    <input type="checkbox" name="etb_save_card" value="1" checked>
                                    <span><strong>Setup payments for future usage & incidentals</strong></span>
                                </label>
                                <p class="etb-mandate-legal-text">
                                    By providing your payment card information, you authorize <strong>EDEN CAB SASU</strong> to charge your card for this service as well as any approved additional charges (extra hours, wait time, unforeseen tolls) in accordance with its terms and conditions.
                                </p>
                            </div>
                        </div>

                    </div>
                </div> <!-- Fin #etb-chk-step-2 -->

            </div> <!-- FIN .etb-checkout-left-col (COLONNE GAUCHE) -->

            <!-- ────────────────────────────────────────────────────────── -->
            <!-- COLONNE 2 : DROITE (STICKY RIDE SUMMARY CARD)              -->
            <!-- ────────────────────────────────────────────────────────── -->
            <div class="etb-checkout-right-col">
                <div class="etb-chk-sticky-card">
                    
                    <h3 class="etb-chk-summary-title">Ride Summary</h3>

                    <!-- Aperçu du Véhicule (Synchronisé) -->
                    <div class="etb-chk-summary-hero">
                        <img src="<?php echo esc_url( $vehicle_img ); ?>" id="etb-chk-summary-img" alt="<?php echo esc_attr( $vehicle_name ); ?>" style="<?php echo empty( $vehicle_img ) ? 'display: none;' : ''; ?>">
                        <div class="etb-chk-summary-vehicle-meta">
                            <h4 id="etb-chk-summary-vehicle-name"><?php echo esc_html( $vehicle_name ); ?></h4>
                            <div class="etb-chk-summary-specs" id="etb-chk-summary-specs">
                                <span class="etb-summary-spec-item">
                                    <svg viewBox="0 0 100 95" width="20" height="20" fill="#fbac18">
                                        <path d="m88.484 31.117c0 8.0312-6.5117 14.539-14.539 14.539-8.0312 0-14.543-6.5078-14.543-14.539s6.5117-14.539 14.543-14.543c8.0273 0 14.539 6.5117 14.539 14.543z"/>
                                        <path d="m0.90234 73.273c-3.0547 6.2812 2.0391 15.633 9.6914 17.562 16.133 3.6016 32.57 3.6016 48.703 0 7.6562-1.9336 12.75-11.281 9.6914-17.562-5.7109-11.867-18.844-22.293-34.047-22.391-15.203 0.10156-28.336 10.523-34.047 22.391z"/>
                                        <path d="m54.445 25.965c0 10.77-8.7305 19.504-19.5 19.504-10.77 0-19.504-8.7344-19.504-19.504 0-10.77 8.7344-19.5 19.504-19.5 10.77-0.003906 19.5 8.7305 19.5 19.5z"/>
                                        <path d="m99.328 66.391c-4.2578-8.8516-14.051-16.625-25.383-16.695-5.1719 0.03125-10.023 1.6719-14.176 4.2812 6.0273 4.4648 11.02 10.426 14.141 16.91 1.5391 3.1641 1.8438 6.8906 0.93359 10.605 5.7734-0.0625 11.539-0.73047 17.258-2.0078 5.707-1.4414 9.5078-8.4141 7.2266-13.094z"/>
                                    </svg>
                                    <span><strong id="etb-chk-pax-count"><?php echo esc_html( $pax_count ); ?></strong> Passengers</span>
                                </span>
                                <span class="etb-spec-dot">•</span>
                                <span class="etb-summary-spec-item">
                                    <svg viewBox="20 8 60 88" width="20" height="20" fill="#fbac18">
                                        <path d="M70.75,26.75H60.028l1.056,5.476c2.108-0.315,4.109,1.073,4.517,3.185l0.868,4.5c0.418,2.169-1.001,4.267-3.171,4.685 l-1.227,0.237c-2.169,0.418-4.268-1.001-4.686-3.17l-0.867-4.5c-0.408-2.113,0.936-4.146,3.01-4.637l-1.113-5.775H54.75v-14 c0-0.019-0.01-0.034-0.011-0.052c0.002-0.032,0.01-0.062,0.01-0.095c0-0.773-0.626-1.397-1.397-1.397h-6.705 c-0.771,0-1.397,0.624-1.397,1.397c0,0.034,0.008,0.065,0.01,0.098c0,0.017-0.01,0.031-0.01,0.049v14h-16c-2.209,0-4,1.791-4,4v59 c0,2.209,1.791,4,4,4h6V94c0,0.69,0.559,1.25,1.25,1.25c0.689,0,1.25-0.56,1.25-1.25v-0.25h24.5V94c0,0.69,0.559,1.25,1.25,1.25 c0.689,0,1.25-0.56,1.25-1.25v-0.25h6c2.209,0,4-1.791,4-4v-59C74.75,28.541,72.959,26.75,70.75,26.75z M47.75,14h4.5v12.75h-4.5V14 z M63.39,84.078h-26.78c-1.027,0-1.86-0.834-1.86-1.859c0-1.028,0.833-1.859,1.86-1.859h26.78c1.026,0,1.859,0.831,1.859,1.859 C65.249,83.244,64.416,84.078,63.39,84.078z"/>
                                    </svg>
                                    <span><strong id="etb-chk-bag-count"><?php echo esc_html( $bag_count ); ?></strong> Luggage</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Détails Itinéraire & Horaires (Date en 1er puis Trajet) -->
                    <div class="etb-chk-summary-timeline">
                        
                        <!-- 1. DATE & TIME (En tête) -->
                        <div class="etb-chk-timeline-item" id="etb-chk-timeline-date-row">
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
                                <strong id="etb-chk-summary-datetime"><?php echo esc_html( $formatted_datetime_display ); ?></strong>
                            </div>
                        </div>

                        <!-- 2. PICKUP (Départ) -->
                        <div class="etb-chk-timeline-item" id="etb-chk-timeline-pickup-row">
                            <span class="etb-chk-bullet etb-bullet-pickup"></span>
                            <div class="etb-chk-timeline-text">
                                <small>PICKUP</small>
                                <strong id="etb-chk-summary-pickup"><?php echo esc_html( $pickup_addr ?: '—' ); ?></strong>
                            </div>
                        </div>

                        <!-- 3. DROP-OFF (Arrivée) -->
                        <div class="etb-chk-timeline-item" id="etb-chk-timeline-dropoff-row">
                            <span class="etb-chk-bullet etb-bullet-dropoff"></span>
                            <div class="etb-chk-timeline-text">
                                <small>DROP-OFF</small>
                                <strong id="etb-chk-summary-dropoff"><?php echo esc_html( $dropoff_addr ?: '—' ); ?></strong>
                            </div>
                        </div>

                        <!-- 3bis. DURATION (Mode À l'heure) -->
                        <div class="etb-chk-timeline-item" id="etb-chk-timeline-duration-row" style="display: none;">
                            <span class="etb-chk-bullet etb-bullet-hourly"></span>
                            <div class="etb-chk-timeline-text">
                                <small>DURATION</small>
                                <strong id="etb-chk-summary-duration">—</strong>
                            </div>
                        </div>

                    </div>

                    <!-- Engagements de service Blacklane -->
                    <div class="etb-chk-guarantees">
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-yes"></span>
                            <span>Free cancellation up to 2 hours before pickup</span>
                        </div>
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-clock"></span>
                            <span id="etb-chk-wait-time-text">15 min complimentary wait time included</span>
                        </div>
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-shield"></span>
                            <span>Taxes, tolls, fuel & insurance included</span>
                        </div>
                    </div>

                    <!-- Sélecteur de Pourboire chauffeur -->
                    <div class="etb-chk-tip-section">
                        <label class="etb-chk-tip-title">Add Driver Tip (Optional)</label>
                        <div class="etb-chk-tip-pills">
                            <label class="etb-tip-pill active" data-tip="0">
                                <input type="radio" name="etb_driver_tip" value="0" checked>
                                <span>None</span>
                            </label>
                            <label class="etb-tip-pill" data-tip="10">
                                <input type="radio" name="etb_driver_tip" value="10">
                                <span>10%</span>
                            </label>
                            <label class="etb-tip-pill" data-tip="15">
                                <input type="radio" name="etb_driver_tip" value="15">
                                <span>15%</span>
                            </label>
                            <label class="etb-tip-pill" data-tip="20">
                                <input type="radio" name="etb_driver_tip" value="20">
                                <span>20%</span>
                            </label>
                        </div>
                        <input type="hidden" name="etb_tip_amount" id="etb-chk-tip-amount" value="0">
                    </div>

                    <!-- Total Prix Net Garanti -->
                    <div class="etb-chk-price-breakdown">
                        <div class="etb-chk-price-row">
                            <span>Base Fare</span>
                            <span id="etb-chk-breakdown-base"><?php echo esc_html( $formatted_price ); ?></span>
                        </div>
                        <!-- Lignes dynamiques distinctes : Baby Seat et Booster Seat -->
                        <div class="etb-chk-price-row" id="etb-chk-baby-seats-row" style="display: none; color: #fbac18;">
                            <span id="etb-chk-baby-seats-label">Extra Baby Seat</span>
                            <span id="etb-chk-breakdown-baby-seats">+ 0 €</span>
                        </div>
                        <div class="etb-chk-price-row" id="etb-chk-booster-seats-row" style="display: none; color: #fbac18;">
                            <span id="etb-chk-booster-seats-label">Extra Booster Seat</span>
                            <span id="etb-chk-breakdown-booster-seats">+ 0 €</span>
                        </div>

                        <div class="etb-chk-price-row" id="etb-chk-tip-row" style="display: none; color: #4ade80;">
                            <span>Driver Tip (<strong id="etb-chk-tip-percent">10%</strong>)</span>
                            <span id="etb-chk-breakdown-tip">+ 0 €</span>
                        </div>
                        <div class="etb-chk-price-row total-row">
                            <span>Total (All Inclusive)</span>
                            <strong id="etb-chk-total-price"><?php echo esc_html( $formatted_price ); ?></strong>
                        </div>
                    </div>

                    <!-- Message d'erreur / Feedback avant envoi -->
                    <div class="etb-chk-feedback" id="etb-chk-feedback" style="display: none;"></div>

                    <!-- Bandeau de réservation urgente (< 24h) pilotable en PHP & JS -->
                    <div id="etb-chk-urgent-notice-box" style="<?php echo $is_urgent_booking ? 'display: block;' : 'display: none;'; ?> background: rgba(251, 172, 24, 0.1); border: 1px solid rgba(251, 172, 24, 0.35); border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; font-size: 12px; color: var(--etb-text-primary, #ffffff); line-height: 1.45;">
                        <strong style="color: #fbac18; display: block; margin-bottom: 2px;">⏱️ Short-Notice Pickup (&lt; 24h)</strong>
                        For bookings scheduled within 24 hours, 
                        our dispatch team will confirm chauffeur 
                        availability before any payment is collected.
                        You will receive a response within 
                        1 hour during business hours (8:00 AM – 9:00 PM).
                    </div>
                    
                    <!-- Option "Pay Later" (masquée si devis ou réservation urgente) -->
                    <div id="etb-chk-pay-later-wrap" style="text-align: center; margin-bottom: 12px; <?php echo ( $is_urgent_booking || 'Custom Quote' === $price_val || floatval( $price_val ) <= 0 ) ? 'display: none;' : ''; ?>">
                        <a href="#" id="etb-chk-pay-later-link" style="font-size: 13px; font-weight: 700; color: var(--etb-text-secondary, #94a3b8); text-decoration: underline; transition: color 0.2s ease;">
                            Or book now and Pay Later ➔
                        </a>
                    </div>

                    <!-- Bouton d'action principal -->
                    <button type="button" class="etb-chk-submit-btn" id="etb-chk-submit-btn">
                        <span id="etb-chk-submit-text"><?php echo $is_urgent_booking ? 'Request Urgent Booking' : 'Continue to Payment'; ?></span>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>

                    <p class="etb-chk-ssl-notice">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        256-Bit SSL Encrypted Dispatch to LimoExpress
                    </p>

                </div>
            </div> <!-- FIN .etb-checkout-right-col (COLONNE 2) -->

        </div> <!-- FIN .etb-checkout-layout (FIN DU GRID 2 COLONNES) -->
    </form>

</div>