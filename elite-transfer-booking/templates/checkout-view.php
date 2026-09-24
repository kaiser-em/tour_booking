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
?>

<div class="etb-checkout-wrapper" id="etb-checkout-app">
    
    <!-- En-tête de retour / Titre de page -->
    <div class="etb-checkout-top-nav">
        <a href="javascript:history.back();" class="etb-checkout-back-link">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <span>Edit Ride Details</span>
        </a>
        <h1 class="etb-checkout-page-title">Finalize Your VIP Reservation</h1>
    </div>

    <!-- Formulaire englobant les 2 colonnes -->
    <form id="etb-checkout-form" onsubmit="return false;">
        
        <!-- Champs de sécurité invisibles -->
        <div style="position: absolute !important; left: -9999px !important; opacity: 0 !important; width: 0 !important; height: 0 !important; overflow: hidden !important;" aria-hidden="true">
            <input type="text" name="etb_hp_email" value="" tabindex="-1" autocomplete="off">
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
                        
                        <div class="etb-chk-grid-2" id="etb-chk-airport-grid">
                            <div class="etb-chk-field" id="etb-chk-flight-field">
                                <label for="etb-flight-number" id="etb-chk-flight-label">Airline & Flight Number (e.g. AF 7704)</label>
                                <input type="text" name="etb_flight_number" id="etb-flight-number" placeholder="e.g. BA 342, DL 401..." autocomplete="off">
                                <small class="etb-chk-hint" id="etb-chk-flight-hint">60 minutes complimentary wait time included after landing.</small>
                            </div>
                            <div class="etb-chk-field" id="etb-chk-sign-field">
                                <label for="etb-pickup-sign">Name on Chauffeur Greeting Sign</label>
                                <input type="text" name="etb_pickup_sign" id="etb-pickup-sign" placeholder="e.g. Mr. Bruce Wayne, Acme Corp..." autocomplete="off">
                                <small class="etb-chk-hint">Displayed on the tablet held by your chauffeur at the terminal gate.</small>
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
                                <label for="etb-passenger-phone">Mobile Phone (with country code) *</label>
                                <input type="tel" name="etb_phone" id="etb-passenger-phone" placeholder="+33 6 12 34 56 78" required>
                                <small class="etb-chk-hint">Chauffeur will send SMS when arriving on location.</small>
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

                        <!-- Sélecteur Passagers & Bagages interactif -->
                        <div class="etb-chk-capacity-box" style="margin-top: 15px;">
                            <div class="etb-chk-cap-item">
                                <div class="etb-chk-cap-label">
                                    <span class="dashicons dashicons-admin-users"></span>
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

                            <div class="etb-chk-cap-item">
                                <div class="etb-chk-cap-label">
                                    <span class="dashicons dashicons-portfolio"></span>
                                    <div>
                                        <strong>Luggage</strong>
                                        <small id="etb-chk-max-bag-hint">Max: <?php echo esc_html( $bag_count ); ?></small>
                                    </div>
                                </div>
                                <div class="etb-chk-qty-control">
                                    <button type="button" class="etb-chk-qty-btn etb-bag-minus">-</button>
                                    <input type="number" name="etb_luggage_count" id="etb-chk-bag-input" value="<?php echo min( 1, $bag_count ); ?>" min="0" readonly>
                                    <button type="button" class="etb-chk-qty-btn etb-bag-plus">+</button>
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

                        <!-- Sièges enfants -->
                        <div class="etb-chk-child-seats-box">
                            <div class="etb-chk-seat-info">
                                <span class="dashicons dashicons-heart"></span>
                                <div>
                                    <strong>Child / Baby Seats Required?</strong>
                                    <p>Infant seat (0-1 yr), Toddler seat (1-4 yrs) or Booster (4-10 yrs).</p>
                                </div>
                            </div>
                            <div class="etb-chk-qty-control">
                                <button type="button" class="etb-chk-qty-btn etb-seat-minus">-</button>
                                <input type="number" name="etb_baby_seat_count" id="etb-chk-baby-seats" value="0" min="0" max="4" readonly>
                                <button type="button" class="etb-chk-qty-btn etb-seat-plus">+</button>
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
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fbac18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <span><strong id="etb-chk-pax-count"><?php echo esc_html( $pax_count ); ?></strong> passengers</span>
                                </span>
                                <span class="etb-spec-dot">•</span>
                                <span class="etb-summary-spec-item">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fbac18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                    </svg>
                                    <span><strong id="etb-chk-bag-count"><?php echo esc_html( $bag_count ); ?></strong> luggage</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Détails Itinéraire & Horaires (Tracé Blacklane) -->
                    <div class="etb-chk-summary-timeline">
                        <div class="etb-chk-timeline-item" id="etb-chk-timeline-pickup-row">
                            <span class="etb-chk-bullet etb-bullet-pickup"></span>
                            <div class="etb-chk-timeline-text">
                                <small>PICKUP</small>
                                <strong id="etb-chk-summary-pickup"><?php echo esc_html( $pickup_addr ?: '—' ); ?></strong>
                            </div>
                        </div>

                        <div class="etb-chk-timeline-item" id="etb-chk-timeline-dropoff-row">
                            <span class="etb-chk-bullet etb-bullet-dropoff"></span>
                            <div class="etb-chk-timeline-text">
                                <small>DROP-OFF</small>
                                <strong id="etb-chk-summary-dropoff"><?php echo esc_html( $dropoff_addr ?: '—' ); ?></strong>
                            </div>
                        </div>

                        <div class="etb-chk-timeline-item" id="etb-chk-timeline-duration-row" style="display: none;">
                            <span class="etb-chk-bullet etb-bullet-hourly"></span>
                            <div class="etb-chk-timeline-text">
                                <small>DURATION</small>
                                <strong id="etb-chk-summary-duration">—</strong>
                            </div>
                        </div>

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
                                <strong id="etb-chk-summary-datetime"><?php echo esc_html( ( $date_val && $time_val ) ? sprintf( '%s at %s', $date_val, $time_val ) : '—' ); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Engagements de service Blacklane -->
                    <div class="etb-chk-guarantees">
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-yes"></span>
                            <span>Free cancellation up to 8 hours before pickup</span>
                        </div>
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-clock"></span>
                            <span id="etb-chk-wait-time-text">15 min complimentary wait time included</span>
                        </div>
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-shield"></span>
                            <span>Taxes, tolls & chauffeur gratuity included</span>
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

                    <!-- Bouton d'action dynamique (Proceed to Payment -> Pay Now) -->
                    <button type="button" class="etb-chk-submit-btn" id="etb-chk-submit-btn">
                        <span id="etb-chk-submit-text">Continue to Payment</span>
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