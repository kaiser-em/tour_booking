<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$gen_settings = get_option( 'etb_general_settings', array() );
$currency     = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';

$vehicles = get_posts( array(
    'post_type'   => 'tour_vehicle',
    'post_status' => 'publish',
    'numberposts' => -1,
    'orderby'     => 'menu_order',
    'order'       => 'ASC',
) );

$today_str   = current_time( 'Y-m-d' );
$tomorrow_ts = strtotime( '+1 day', current_time( 'timestamp' ) );
$tomorrow_str= date( 'Y-m-d', $tomorrow_ts );

// Formatage textuel de la date de demain en anglais (ex: 18 Sep 2026)
$months = array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );
$d_day  = date( 'd', $tomorrow_ts );
$d_m_idx= (int) date( 'm', $tomorrow_ts ) - 1;
$d_year = date( 'Y', $tomorrow_ts );
$tomorrow_display = sprintf( '%s %s %s', $d_day, $months[ $d_m_idx ] ?? 'Sep', $d_year );

// L'heure n'est PAS pré-remplie par défaut (doit être choisie par le client)
$now_time = '';
?>

<div class="etb-quick-widget" id="etb-quick-widget-app">


    <!-- TITRE ANIMÉ STYLE AI STUDIO (THINKING SHIMMER) -->
    <div class="etb-quick-title-wrapper">
        
        <h2 class="etb-quick-title-thinking">Fare Calculator</h2>
    </div>

    <!-- 1. TOGGLE CAPSULE NOIRE (One way / By the hour) -->
    <div class="etb-quick-mode-capsule">
        <button type="button" class="etb-quick-mode-btn active" data-mode="transfer">
            <span>One way</span>
        </button>
        <button type="button" class="etb-quick-mode-btn" data-mode="hourly">
            <span>By the hour</span>
        </button>
    </div>

    <!-- 2. BARRE HORIZONTALE FLOTTANTE (GLASSMORPHISM BLACKLANE) -->
    <div class="etb-quick-glass-bar etb-initial-border">
        
        <!-- Colonne 1 : Lieu de départ -->
        <div class="etb-quick-col" id="etb-quick-pickup-col">
            <span class="etb-quick-label">Pickup location</span>
            <div class="etb-quick-input-wrap">
                <input type="text" name="etb_quick_pickup" id="etb-quick-pickup" placeholder="Address, airport, hotel..." autocomplete="off" class="etb-quick-input">
                <button type="button" class="etb-quick-clear-btn" id="etb-quick-clear-pickup" aria-label="Clear" style="display: none;">
                    <svg viewBox="0 0 24 24" width="11" height="11" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <input type="hidden" name="etb_quick_pickup_lat" id="etb-quick-pickup-lat" value="">
            <input type="hidden" name="etb_quick_pickup_lng" id="etb-quick-pickup-lng" value="">
            <div class="etb-quick-suggestions-box" id="etb-quick-pickup-suggestions" style="display: none;"></div>
        </div>

        <!-- Colonne 2 : Lieu de dépose (Mode Trajet simple) -->
        <div class="etb-quick-col" id="etb-quick-dropoff-col">
            <span class="etb-quick-label">Drop-off location</span>
            <div class="etb-quick-input-wrap">
                <input type="text" name="etb_quick_dropoff" id="etb-quick-dropoff" placeholder="Address, airport, hotel..." autocomplete="off" class="etb-quick-input">
                <button type="button" class="etb-quick-clear-btn" id="etb-quick-clear-dropoff" aria-label="Clear" style="display: none;">
                    <svg viewBox="0 0 24 24" width="11" height="11" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <input type="hidden" name="etb_quick_dropoff_lat" id="etb-quick-dropoff-lat" value="">
            <input type="hidden" name="etb_quick_dropoff_lng" id="etb-quick-dropoff-lng" value="">
            <div class="etb-quick-suggestions-box" id="etb-quick-dropoff-suggestions" style="display: none;"></div>
        </div>

        <!-- Colonne 2bis : Durée (Choix dynamique de 3h à 24h) -->
        <div class="etb-quick-col" id="etb-quick-duration-col" style="display: none; position: relative;">
            <span class="etb-quick-label">Duration</span>
            <input type="hidden" name="etb_quick_duration" id="etb-quick-duration" value="4">
            
            <div class="etb-custom-select" id="etb-duration-custom-select">
                <div class="etb-custom-select-trigger">
                    <span>4 Hours</span>
                    <i class="dashicons dashicons-arrow-down-alt2"></i>
                </div>
                <div class="etb-custom-select-options">
                    <?php for ( $dur = 3; $dur <= 24; $dur++ ) : ?>
                        <div class="etb-custom-option <?php echo ( 4 === $dur ) ? 'selected' : ''; ?>" data-val="<?php echo esc_attr( $dur ); ?>">
                            <?php echo esc_html( $dur ); ?> Hours
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>


        <div class="etb-quick-divider"></div>

        <!-- Colonne 3 : Date par défaut sur DEMAIN -->
        <div class="etb-quick-col etb-col-date" id="etb-quick-date-col" style="cursor: pointer;">
            <span class="etb-quick-label">Date</span>
            <div class="etb-quick-date-wrapper">
                <span class="etb-quick-date-display" id="etb-quick-date-text"><?php echo esc_html( $tomorrow_display ); ?></span>
                <input type="date" name="etb_quick_date" id="etb-quick-date" value="<?php echo esc_attr( $tomorrow_str ); ?>" min="<?php echo esc_attr( $today_str ); ?>">
            </div>
        </div>

        <!-- Colonne 4 : Heure format 12h (AM / PM) avec crans de 5 min -->
        <div class="etb-quick-col etb-col-time" id="etb-quick-time-col" style="cursor: pointer; position: relative;">
            <span class="etb-quick-label">Pickup time</span>
            <input type="text" name="etb_quick_time" id="etb-quick-time" value="" placeholder="-- : --" readonly style="cursor: pointer;">
            
            <!-- Menu déroulant 12h complet avec capsule AM/PM et deux colonnes distinctes -->
            <div class="etb-quick-time-picker-popup" id="etb-quick-time-popup" style="display: none;">
                <!-- Capsule AM / PM -->
                <div class="etb-quick-ampm-capsule">
                    <button type="button" class="etb-ampm-btn" data-val="AM">AM</button>
                    <button type="button" class="etb-ampm-btn active" data-val="PM">PM</button>
                </div>

                <div class="etb-time-columns-row">
                    <div class="etb-time-col etb-time-hours">
                        <span class="etb-time-head">Hour</span>
                        <div class="etb-time-scroll">
                            <?php for ( $h = 1; $h <= 12; $h++ ) : $h_str = sprintf( '%02d', $h ); ?>
                                <div class="etb-time-opt etb-hour-opt" data-val="<?php echo esc_attr( $h_str ); ?>"><?php echo esc_html( $h_str ); ?></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="etb-time-col etb-time-minutes">
                        <span class="etb-time-head">Minute</span>
                        <div class="etb-time-scroll">
                            <?php for ( $m = 0; $m < 60; $m += 5 ) : $m_str = sprintf( '%02d', $m ); ?>
                                <div class="etb-time-opt etb-minute-opt" data-val="<?php echo esc_attr( $m_str ); ?>"><?php echo esc_html( $m_str ); ?></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne 5 : Bouton Voir les options -->
        <div class="etb-quick-btn-col">
            <button type="button" class="etb-quick-submit-btn" id="etb-quick-get-price-btn">
                <span>Show Prices</span>
            </button>
        </div>

    </div>

    <!-- Message de feedback / erreur si champs vides -->
    <div class="etb-quick-error-notice" id="etb-quick-error" style="display: none;"></div>

<!-- 3. SECTION FLOTTE DE VÉHICULES (English UI & Custom Quote Ready) -->
    <div class="etb-quick-fleet-wrapper" id="etb-quick-fleet-section" style="display: none;">
        
        <h3 class="etb-quick-fleet-heading" id="etb-quick-fleet-heading">Please choose a Vehicle</h3>
        
        <!-- Bandeau d'information pour trajet longue distance / sur mesure -->
        <div class="etb-quick-info-notice" id="etb-quick-quote-notice" style="display: none;"></div>

        <div class="etb-quick-cars-grid" id="etb-quick-vehicles-grid">
            <?php if ( ! empty( $vehicles ) ) : foreach ( $vehicles as $v ) : 
                $base_price    = get_post_meta( $v->ID, '_etb_base_price', true );
                $min_hours     = get_post_meta( $v->ID, '_etb_min_hours', true ) ?: 4;
                $hourly_rate   = get_post_meta( $v->ID, '_etb_hourly_rate', true );
                $pack_10h      = get_post_meta( $v->ID, '_etb_pack_10h', true );
                $sup_hour_rate = get_post_meta( $v->ID, '_etb_sup_hour_rate', true );
                $km_included   = get_post_meta( $v->ID, '_etb_km_included_ph', true ) ?: 35;
                $km_sup_rate   = get_post_meta( $v->ID, '_etb_km_sup_rate', true );
                
                $max_pax       = get_post_meta( $v->ID, '_etb_max_pax', true ) ?: 1;
                $max_bag       = get_post_meta( $v->ID, '_etb_max_baggage', true ) ?: 0;
                $limo_class_id = trim( get_post_meta( $v->ID, '_etb_limo_class_id', true ) );
                $img_url       = get_the_post_thumbnail_url( $v->ID, 'full' ) ?: ( defined('ETB_URL') ? ETB_URL . 'public/images/default-car.png' : '' );
                $hover_img     = get_post_meta( $v->ID, '_etb_hover_image', true );
            ?>
                <div class="etb-quick-car-item" 
                     id="limo-car-<?php echo esc_attr( $limo_class_id ); ?>" 
                     data-id="<?php echo esc_attr( $v->ID ); ?>" 
                     data-min-hours="<?php echo esc_attr( $min_hours ); ?>"
                     data-hourly-rate="<?php echo esc_attr( $hourly_rate ); ?>" 
                     data-pack-10h="<?php echo esc_attr( $pack_10h ); ?>"
                     data-sup-hour-rate="<?php echo esc_attr( $sup_hour_rate ); ?>"
                     data-km-ph="<?php echo esc_attr( $km_included ); ?>"
                     data-km-sup-rate="<?php echo esc_attr( $km_sup_rate ); ?>"
                     data-base-price="<?php echo esc_attr( $base_price ); ?>"
                     data-max-pax="<?php echo esc_attr( $max_pax ); ?>" 
                     data-max-bag="<?php echo esc_attr( $max_bag ); ?>">
                    
                    <!-- 1. ZONE EN-TÊTE : Photo & GIF bord-à-bord + Badges flottants 👑 -->
                    <div class="etb-quick-card-hero">
                        <span class="etb-quick-badge-vip">Premium</span>
                        <span class="etb-quick-card-check"><i class="dashicons dashicons-yes"></i></span>

                        <?php if ( $img_url ) : ?>
                            <div class="etb-quick-img-box">
                                <img src="<?php echo esc_url( $img_url ); ?>" class="etb-img-static" alt="<?php echo esc_attr( $v->post_title ); ?>">
                                <?php if ( ! empty( $hover_img ) ) : ?>
                                    <img src="<?php echo esc_url( $hover_img ); ?>" class="etb-img-hover" alt="<?php echo esc_attr( $v->post_title ); ?> (animated)">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 2. CORPS DE LA CARTE -->
                    <div class="etb-quick-card-body">
                        <h4 class="etb-quick-car-title"><?php echo esc_html( $v->post_title ); ?></h4>
                        
                        <!-- Ligne Capacités avec séparateur | -->
                        <div class="etb-quick-specs">
                            <span class="etb-spec-item"><span class="dashicons dashicons-admin-users"></span> <?php echo esc_html( $max_pax ); ?> passengers</span>
                            <span class="etb-spec-divider">|</span>
                            <span class="etb-spec-item"><span class="dashicons dashicons-portfolio"></span> <?php echo esc_html( $max_bag ); ?> luggage</span>
                        </div>

                        <!-- Capsule kilométrique (Mode À l'heure) -->
                        <div class="etb-quick-km-info etb-quick-km-pill" style="display: none;"></div>

                        <!-- Rangée des 4 Équipements VIP -->
                        <div class="etb-quick-amenities">
                            <div class="etb-amenity-col" title="Air conditioning">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="etb-amenity-icon">
                                    <line x1="12" y1="2" x2="12" y2="22"></line>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <line x1="20" y1="16" x2="4" y2="8"></line>
                                    <line x1="4" y1="16" x2="20" y2="8"></line>
                                    <line x1="16" y1="20" x2="8" y2="4"></line>
                                    <line x1="8" y1="20" x2="16" y2="4"></line>
                                </svg>
                                <small>Climate</small>
                            </div>

                            <!-- Bouteille d'eau offerte à bord -->
                            <div class="etb-amenity-col" title="Complimentary bottled water">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="etb-amenity-icon">
                                    <path d="M10 2h4v2h-4z"></path>
                                    <path d="M10 4v2h4V4"></path>
                                    <path d="M8 8h8l1 4v9a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-9l1-4z"></path>
                                    <line x1="8" y1="14" x2="16" y2="14"></line>
                                </svg>
                                <small>Water</small>
                            </div>


                            <div class="etb-amenity-col" title="Comfort leather seats">
                                <span class="dashicons dashicons-nametag"></span>
                                <small>Comfort</small>
                            </div>
                            <div class="etb-amenity-col" title="Certified chauffeur & safety">
                                <span class="dashicons dashicons-shield"></span>
                                <small>Safety</small>
                            </div>
                            <div class="etb-amenity-col" title="Wifi & on-board amenities">
                                <span class="dashicons dashicons-rss"></span>
                                <small>Wifi</small>
                            </div>
                        </div>

                        <!-- 3. PIED DE CARTE (Split : Prix à gauche, Bouton à droite) -->
                        <div class="etb-quick-card-footer">
                            <div class="etb-quick-price-box">
                                <div class="etb-quick-price-display">
                                    <span class="etb-quick-amount">0</span>
                                    <span class="etb-quick-currency"><?php echo esc_html( $currency ); ?></span>
                                </div>
                                <div class="etb-quick-price-detail" style="display: none;"></div>
                            </div>

                            <button type="button" class="etb-quick-select-btn">
                                <span class="etb-select-label">Select</span>
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                        </div>
                    </div>

                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- 4. BARRE DE CONFIRMATION / RÉSERVATION HYBRIDE (Option C) -->
        <div class="etb-quick-confirm-bar" id="etb-quick-booking-bar" style="display: none;">
            <div class="etb-quick-summary-info">
                <span>Selected Vehicle: <strong id="etb-quick-selected-name">—</strong></span>
                <span>Estimated Rate: <strong id="etb-quick-selected-total" style="color: #fbac18;">0 <?php echo esc_html( $currency ); ?></strong></span>
            </div>
            
            <div class="etb-quick-actions-row" style="display: flex; align-items: center; gap: 12px;">

                <!-- Nouveau bouton : Demande par Email (Alerte temporaire) -->
                <button type="button" class="etb-quick-email-btn" id="etb-quick-email-btn">
                    <span class="dashicons dashicons-email-alt"></span>
                    <span>Email Inquiry</span>
                </button>

                <!-- Bouton secondaire Option C : Contact direct WhatsApp / Dispatch -->
                <a href="#" target="_blank" class="etb-quick-whatsapp-btn" id="etb-quick-whatsapp-btn" style="display: none;">
                    <span class="dashicons dashicons-whatsapp"></span>
                    <span>Quick Inquiry</span>
                </a>
                <!-- Bouton principal : Réservation / Demande LimoExpress -->
                <button type="button" class="etb-quick-book-trip-btn" id="etb-quick-book-now-btn">
                    <span id="etb-quick-book-btn-label">Book this Trip</span>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>

    </div>

   <!-- 5. MICRO-MODAL VIP DE DEVIS RAPIDE (EMAIL INQUIRY) -->
    <div class="etb-quick-modal-backdrop" id="etb-quick-inquiry-modal" style="display: none;">
        <div class="etb-quick-modal-card" role="dialog" aria-modal="true" aria-labelledby="etb-inquiry-title">
            
            <!-- En-tête du Modal -->
            <div class="etb-inquiry-header">
                <div class="etb-inquiry-title-wrap">
                    <span class="etb-inquiry-icon">✉️</span>
                    <div>
                        <h3 id="etb-inquiry-title">Quick Email Inquiry</h3>
                        <p>Receive a personal quote from our dispatch team within 15 minutes.</p>
                    </div>
                </div>
                <button type="button" class="etb-inquiry-close-btn" id="etb-inquiry-close" aria-label="Close modal">&times;</button>
            </div>

            <!-- Récapitulatif verrouillé du trajet sélectionné -->
            <div class="etb-inquiry-summary-card">
                <div class="etb-inquiry-summary-vehicle">
                    <strong id="etb-inquiry-car-name">—</strong>
                    <span class="etb-inquiry-summary-price" id="etb-inquiry-car-price">—</span>
                </div>
                <div class="etb-inquiry-summary-trip">
                    <div class="etb-inquiry-trip-line">
                        <span class="dashicons dashicons-location"></span>
                        <span id="etb-inquiry-route">—</span>
                    </div>
                    <div class="etb-inquiry-trip-line">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span id="etb-inquiry-datetime">—</span>
                    </div>
                </div>
            </div>

            <!-- Formulaire de contact rapide -->
            <form id="etb-inquiry-form" onsubmit="return false;">
                
                <div class="etb-inquiry-grid-2">
                    <div class="etb-inquiry-field">
                        <label for="etb_inq_name">Full Name *</label>
                        <input type="text" id="etb_inq_name" name="inquiry_name" placeholder="e.g. Bruce Wayne" autocomplete="name" required>
                    </div>
                    <div class="etb-inquiry-field">
                        <label for="etb_inq_email">Email Address *</label>
                        <input type="email" id="etb_inq_email" name="inquiry_email" placeholder="e.g. b.wayne@corp.com" autocomplete="email" required>
                    </div>
                </div>

                <div class="etb-inquiry-field">
                    <label for="etb_inq_phone">Mobile Phone (recommended for instant reply)</label>
                    <input type="tel" id="etb_inq_phone" name="inquiry_phone" placeholder="e.g. +33 6 12 34 56 78" autocomplete="tel">
                </div>

                <div class="etb-inquiry-field">
                    <label for="etb_inq_notes">Special Requests / Notes (optional)</label>
                    <textarea id="etb_inq_notes" name="inquiry_notes" rows="2" placeholder="Flight number, child seats, luggage details..."></textarea>
                </div>

                <!-- Zone de feedback (succès ou erreur) -->
                <div class="etb-inquiry-feedback" id="etb-inquiry-feedback" style="display: none;"></div>

                <!-- Bouton d'action -->
                <button type="button" class="etb-inquiry-submit-btn" id="etb-inquiry-submit">
                    <span id="etb-inquiry-btn-text">Send My Inquiry</span>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </form>

        </div>
    </div>

</div>