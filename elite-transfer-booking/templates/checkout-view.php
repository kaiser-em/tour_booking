<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Récupération des options générales
$gen_settings    = get_option( 'etb_general_settings', array() );
$currency_symbol = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';

// Jetons de sécurité anti-spam
$sec_token = class_exists( 'ETB_Security' ) ? ETB_Security::generate_timestamp_token() : array( 'time' => time(), 'token' => '' );

// Liste des véhicules disponibles
$vehicles = get_posts( array(
    'post_type'   => 'tour_vehicle',
    'post_status' => 'publish',
    'numberposts' => -1,
    'orderby'     => 'menu_order',
    'order'       => 'ASC',
) );
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
    <form id="etb-checkout-form" onsubmit="return false;">
        <!-- Layout 2 Colonnes Blacklane (Gauche: Formulaire / Droite: Summary Sticky) -->
        <div class="etb-checkout-layout">
            
            <!-- ================================================================== -->
            <!-- COLONNE GAUCHE : FORMULAIRE 4 SECTIONS BLACKLANE                  -->
            <!-- ================================================================== -->
            <div class="etb-checkout-left-col">
               
                    
                    <!-- Champs de sécurité invisibles -->
                    <div style="position: absolute !important; left: -9999px !important; opacity: 0 !important; width: 0 !important; height: 0 !important; overflow: hidden !important;" aria-hidden="true">
                        <input type="text" name="etb_hp_email" value="" tabindex="-1" autocomplete="off">
                        <input type="hidden" name="etb_sec_time" value="<?php echo esc_attr( $sec_token['time'] ); ?>">
                        <input type="hidden" name="etb_sec_token" value="<?php echo esc_attr( $sec_token['token'] ); ?>">
                    </div>

                    <!-- Données de trajet cachées (Reçues du widget ou de l'URL) -->
                    <input type="hidden" name="etb_trip_mode" id="etb-chk-mode" value="transfer">
                    <input type="hidden" name="etb_pickup_address" id="etb-chk-pickup" value="">
                    <input type="hidden" name="etb_dropoff_address" id="etb-chk-dropoff" value="">
                    <input type="hidden" name="etb_duration" id="etb-chk-duration" value="4">
                    <input type="hidden" name="etb_date" id="etb-chk-date" value="">
                    <input type="hidden" name="etb_time" id="etb-chk-time" value="">
                    <input type="hidden" name="etb_vehicle_id" id="etb-chk-vehicle-id" value="">
                    <input type="hidden" name="etb_calculated_price" id="etb-chk-price" value="">

                    <!-- ─────────────────────────────────────────────────────────── -->
                    <!-- SECTION 1 : ACCUEIL AÉROPORT & PANCARTE (FLIGHT TRACKING)   -->
                    <!-- ─────────────────────────────────────────────────────────── -->
                    <!-- SECTION 1 : ACCUEIL AÉROPORT INTELLIGENT (Contextuel Arrivée vs Dépose) -->
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

                    <!-- ─────────────────────────────────────────────────────────── -->
                    <!-- SECTION 2 : WHO IS RIDING? (PASSAGER VS CORPORATE ASSISTANT)-->
                    <!-- ─────────────────────────────────────────────────────────── -->
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

                        <!-- Bascule Blacklane : Myself vs Someone else -->
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

                        <!-- Sélecteur Passagers & Bagages interactif -->
                        <div class="etb-chk-capacity-box" style="margin-top: 15px;">
                            <div class="etb-chk-cap-item">
                                <div class="etb-chk-cap-label">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <div>
                                        <strong>Passengers</strong>
                                        <small id="etb-chk-max-pax-hint">Max: 3</small>
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
                                        <small id="etb-chk-max-bag-hint">Max: 2</small>
                                    </div>
                                </div>
                                <div class="etb-chk-qty-control">
                                    <button type="button" class="etb-chk-qty-btn etb-bag-minus">-</button>
                                    <input type="number" name="etb_luggage_count" id="etb-chk-bag-input" value="1" min="0" readonly>
                                    <button type="button" class="etb-chk-qty-btn etb-bag-plus">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- Champs additionnels si "Booking for someone else" -->
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
                    </div>

                    <!-- ─────────────────────────────────────────────────────────── -->
                    <!-- SECTION 3 : SPECIAL REQUESTS & CHILD SEATS                  -->
                    <!-- ─────────────────────────────────────────────────────────── -->
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

                        <!-- Sièges bébé / Enfants (Mappé sur baby_seat_count LimoExpress) -->
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

                        <!-- Référence interne / Centre de coût (Corporate) -->
                        <div class="etb-chk-field">
                            <label for="etb-cost-center">Billing Reference / Cost Center (Optional)</label>
                            <input type="text" name="etb_cost_center" id="etb-cost-center" placeholder="e.g. PO-84920, Project Alpha...">
                            <small class="etb-chk-hint">Will appear on your official printable PDF invoice.</small>
                        </div>
                    </div>

               
            </div>

            <!-- ================================================================== -->
            <!-- COLONNE DROITE : STICKY RIDE SUMMARY CARD (BLACKLANE STYLE)        -->
            <!-- ================================================================== -->
            <div class="etb-checkout-right-col">
                <div class="etb-chk-sticky-card">
                    
                    <h3 class="etb-chk-summary-title">Ride Summary</h3>

                    <!-- Aperçu du Véhicule (Finition Vectorielle VIP) -->
                <div class="etb-chk-summary-hero">
                    <img src="" id="etb-chk-summary-img" alt="Vehicle" style="display: none;">
                    <div class="etb-chk-summary-vehicle-meta">
                        <h4 id="etb-chk-summary-vehicle-name">Mercedes-Benz VIP Fleet</h4>
                        <div class="etb-chk-summary-specs" id="etb-chk-summary-specs">
                            <span class="etb-summary-spec-item">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fbac18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span><strong id="etb-chk-pax-count">3</strong> passengers</span>
                            </span>
                            <span class="etb-spec-dot">•</span>
                            <span class="etb-summary-spec-item">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fbac18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                                <span><strong id="etb-chk-bag-count">2</strong> luggage</span>
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
                                <strong id="etb-chk-summary-pickup">—</strong>
                            </div>
                        </div>

                        <div class="etb-chk-timeline-item" id="etb-chk-timeline-dropoff-row">
                            <span class="etb-chk-bullet etb-bullet-dropoff"></span>
                            <div class="etb-chk-timeline-text">
                                <small>DROP-OFF</small>
                                <strong id="etb-chk-summary-dropoff">—</strong>
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
                                <strong id="etb-chk-summary-datetime">—</strong>
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
                            <span id="etb-chk-wait-time-text">60 min complimentary wait time at airports (15 min standard)</span>
                        </div>
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-shield"></span>
                            <span>Taxes & tolls included</span>
                        </div>
                    </div>

                    <!-- Pourboire chauffeur en pourcentage (Driver Tip) -->
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
                            <span id="etb-chk-breakdown-base">—</span>
                        </div>
                        <div class="etb-chk-price-row" id="etb-chk-tip-row" style="display: none; color: #4ade80;">
                            <span>Driver Tip (<strong id="etb-chk-tip-percent">10%</strong>)</span>
                            <span id="etb-chk-breakdown-tip">+ 0 €</span>
                        </div>
                        <div class="etb-chk-price-row total-row">
                            <span>Total (All Inclusive)</span>
                            <strong id="etb-chk-total-price">0 <?php echo esc_html( $currency_symbol ); ?></strong>
                        </div>
                    </div>

                   
                    <!-- Message d'erreur / Feedback avant envoi -->
                    <div class="etb-chk-feedback" id="etb-chk-feedback" style="display: none;"></div>

                    <!-- Bouton d'action ultime Blacklane -->
                    <button type="button" class="etb-chk-submit-btn" id="etb-chk-submit-btn">
                        <span id="etb-chk-submit-text">Confirm & Book Now</span>
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
            </div>

        </div>

    </form>

</div>