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

<div class="etb-quick-widget" id="etb-quick-widget-app" data-etb-theme="dark" style="position: relative;">
    
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
                <span class="etb-quick-input-loader" style="display: none;"></span>
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
                <span class="etb-quick-input-loader" style="display: none;"></span>
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
                    <button type="button" class="etb-ampm-btn active" data-val="AM">AM</button>
                    <button type="button" class="etb-ampm-btn " data-val="PM">PM</button>
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

                        <!-- Rangée des 6 Équipements VIP (SVGs Vectoriels Nets) -->
                        <div class="etb-quick-amenities">
                            <!-- 1. A/C (Air Conditioning) -->
                            <div class="etb-amenity-col" title="Air conditioning">
                                <svg class="etb-amenity-svg" viewBox="0 0 100 100" fill="currentColor">
                                    <path d="M24,82A14,14,0,0,0,38,68a4,4,0,0,0-8,0,6,6,0,0,1-6,6,4,4,0,0,0,0,8Z"/>
                                    <path d="M70,68a4,4,0,0,0-8,0A14,14,0,0,0,76,82a4,4,0,0,0,0-8A6,6,0,0,1,70,68Z"/>
                                    <path d="M46,68V78a4,4,0,0,0,8,0V68a4,4,0,0,0-8,0Z"/>
                                    <path d="M61,18H39a19,19,0,0,0,0,38H61a19,19,0,0,0,0-38Zm0,30H39a11,11,0,0,1,0-22H61a11,11,0,0,1,0,22Z"/>
                                    <path d="M60,33H40a4,4,0,0,0,0,8H60a4,4,0,0,0,0-8Z"/>
                                </svg>
                                <small>A/C</small>
                            </div>

                            <!-- 2. Water -->
                            <div class="etb-amenity-col" title="Complimentary bottled water">
                                <svg class="etb-amenity-svg" viewBox="0 0 100 100" fill="currentColor">
                                    <path d="m69 89c0 3.3125-2.6875 6-6 6h-26c-3.3125 0-6-2.6875-6-6v-19h38z"/>
                                    <path d="m69 65h-38v-17h38z"/>
                                    <path d="m69 43h-38l8-18h22z"/>
                                    <path d="m55 5c3.3125 0 6 2.6875 6 6v9h-22v-9c0-3.3125 2.6875-6 6-6z"/>
                                </svg>
                                <small>Water</small>
                            </div>

                            <!-- 3. Wifi -->
                            <div class="etb-amenity-col" title="Wifi on-board">
                                <svg class="etb-amenity-svg" viewBox="0 0 100 100" fill="currentColor">
                                    <path d="m65.582 4.6875h-31.164c-5.6055 0.007812-10.148 4.5508-10.156 10.156v70.312c0.007812 5.6055 4.5508 10.148 10.156 10.156h31.164c5.6055-0.007812 10.148-4.5508 10.156-10.156v-70.312c-0.007812-5.6055-4.5508-10.148-10.156-10.156zm5.4688 80.469c-0.003906 3.0195-2.4492 5.4648-5.4688 5.4688h-31.164c-3.0195-0.003906-5.4648-2.4492-5.4688-5.4688v-70.312c0.003906-3.0195 2.4492-5.4648 5.4688-5.4688h0.57422c-0.015626 0.089844-0.023438 0.18359-0.027344 0.27734 0 2.2422 1.8164 4.0586 4.0586 4.0625h21.953c2.2422-0.003906 4.0586-1.8203 4.0586-4.0625-0.003906-0.09375-0.011718-0.1875-0.027344-0.27734h0.57422c3.0195 0.003906 5.4648 2.4492 5.4688 5.4688zm-3.9023-43.301c0.91406 0.91797 0.91406 2.3984 0 3.3164-0.91406 0.91406-2.3984 0.91406-3.3125 0-3.6719-3.668-8.6484-5.7305-13.836-5.7305s-10.164 2.0625-13.836 5.7305c-0.91406 0.91406-2.3984 0.91406-3.3125 0-0.91406-0.91797-0.91406-2.3984 0-3.3164 4.5469-4.5469 10.719-7.1016 17.148-7.1016s12.602 2.5547 17.148 7.1016zm-5.1562 5.1523h0.003906c0.44141 0.44141 0.69141 1.0352 0.69531 1.6602 0 0.625-0.24609 1.2227-0.6875 1.6641-0.4375 0.44141-1.0391 0.6875-1.6602 0.6875-0.625 0-1.2227-0.25-1.6602-0.69141-4.7969-4.7969-12.57-4.7969-17.367 0-0.4375 0.44141-1.0352 0.69141-1.6602 0.69141-0.62109 0-1.2227-0.24609-1.6602-0.6875-0.44141-0.44141-0.6875-1.0391-0.6875-1.6641 0.003906-0.625 0.25391-1.2188 0.69531-1.6602 3.1836-3.1797 7.4961-4.9648 11.996-4.9648s8.8125 1.7852 11.996 4.9648zm-9.2461 12.422h0.003906c0 1.1133-0.67188 2.1133-1.6992 2.5391-1.0273 0.42578-2.207 0.19141-2.9961-0.59375-0.78516-0.78906-1.0195-1.9688-0.59375-2.9961 0.42578-1.0273 1.4258-1.6992 2.5391-1.6992 0.73047 0 1.4297 0.28906 1.9453 0.80469 0.51563 0.51562 0.80469 1.2148 0.80469 1.9453zm4.0703-7.2461h0.003906c0.44141 0.4375 0.69141 1.0312 0.69141 1.6562 0 0.62109-0.24609 1.2188-0.68359 1.6562-0.4375 0.44141-1.0352 0.69141-1.6562 0.69141s-1.2188-0.24609-1.6602-0.68359c-1.9414-1.9414-5.082-1.9414-7.0234 0-0.44141 0.4375-1.0391 0.68359-1.6602 0.68359s-1.2188-0.25-1.6562-0.69141c-0.4375-0.4375-0.68359-1.0352-0.68359-1.6562 0-0.625 0.25-1.2188 0.69141-1.6562 3.7695-3.7617 9.8711-3.7617 13.641 0z"/>
                                </svg>
                                <small>Wifi</small>
                            </div>

                            <!-- 4. Baby Seat -->
                            <div class="etb-amenity-col" title="Child & baby seats available">
                                <svg class="etb-amenity-svg" viewBox="0 0 90 90" fill="currentColor">
                                    <path d="M30.85,32.809c3.984,5.24,6.058,9.468,6.058,18.052c-1.131,0.328-2.235,0.735-3.319,1.197l-1.244-8.582l-8.928,6.483 c-4.389,2.813-8.588-1.88-5.239-5.073L30.85,32.809z"/>
                                    <path d="M59.073,32.976c-3.988,5.239-6.063,9.467-6.063,18.051c1.131,0.328,2.234,0.735,3.318,1.197l1.244-8.582l8.934,6.484 c4.391,2.817,8.582-1.875,5.238-5.074L59.073,32.976z"/>
                                    <path d="M53.448,29.824c-2.516,1.76-5.51,2.703-8.578,2.708c-3.067,0-6.058-0.938-8.572-2.688c-0.693,0.234-1.355,0.516-1.995,0.813 c1.817,2.213,3.291,4.682,4.391,7.312h12.672c1.082-2.588,2.525-5.021,4.301-7.208C54.954,30.417,54.22,30.095,53.448,29.824z"/>
                                    <path d="M44.871,53.876c-4.427,0-8.734,1.375-12.495,3.921l-5.167,7.131c-1.495,2.063-2.537,4.708-1.339,6.989l5.052,9.615 c2.224,5.62,8.24,2.967,7.824-2.385L35.5,70.954l7.104-6.224l4.662,0.12l6.969,6.104l-3.244,8.193 c-0.412,5.353,5.598,8.005,7.822,2.385l5.053-9.615c1.197-2.281,0.156-4.926-1.34-6.989l-5.166-7.131 C53.601,55.251,49.298,53.876,44.871,53.876z"/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M57.095,17.517c0,3.24-1.287,6.35-3.584,8.641c-2.291,2.296-5.4,3.583-8.64,3.583 c-6.75,0-12.225-5.473-12.225-12.224c0-6.75,5.475-12.224,12.225-12.224c3.24,0,6.349,1.287,8.64,3.579 C55.808,11.162,57.095,14.272,57.095,17.517z"/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M29.531,12.281c-6.869,0-11.869,6.511-10.4,13.495l2.115,11.183 c0.547,2.631,4.385-0.916,3.938-2.573l-2.115-9.443c-1.01-4.795,2.058-8.64,6.463-8.64C31.49,16.199,31.255,11.917,29.531,12.281z"/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M24.361,53.257v7.479c-0.101,2.776,4.118,0.479,4.02-2.296v-6.6 C28.381,48.917,24.361,51.095,24.361,53.257z"/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M60.204,12.281c6.875,0,11.869,6.511,10.406,13.495l-2.115,11.183 c-0.551,2.631-4.385-0.916-3.941-2.573l2.119-9.443c1.01-4.795-2.057-8.64-6.469-8.64C58.251,16.199,58.485,11.917,60.204,12.281z"/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M65.382,53.257v7.479c0.098,2.776-4.121,0.479-4.021-2.296v-6.6 C61.36,48.917,65.382,51.095,65.382,53.257z"/>
                                </svg>
                                <small>Baby seat</small>
                            </div>

                            <!-- 5. Comfort -->
                            <div class="etb-amenity-col" title="Comfort leather seating">
                                <svg class="etb-amenity-svg" viewBox="0 0 32 32" fill="currentColor">
                                    <path d="M15.0001 5.41424V12C15.0001 12.5523 14.5524 13 14.0001 13C13.4478 13 13.0001 12.5523 13.0001 12V5.41424L10.4143 8.00003C10.0238 8.39055 9.39063 8.39055 9.0001 8.00003C8.60958 7.6095 8.60958 6.97634 9.0001 6.58582L14.0001 1.58582L19.0001 6.58582C19.3906 6.97634 19.3906 7.6095 19.0001 8.00003C18.6096 8.39055 17.9764 8.39055 17.5859 8.00003L15.0001 5.41424Z"/>
                                    <path d="M23.6389 11.7529C20.8475 12.5905 20.0001 14.9996 19.0613 17.6948C18.1226 20.3901 16.8877 22.7593 16.8877 22.7593C16.7966 23.1524 16.4304 23.4192 16.0278 23.3857L7.43044 23.6698C7.16958 23.6481 6.90707 23.6588 6.64878 23.7016C4.9235 23.9878 3.75695 24.6171 4.04327 26.341L4.08683 26.6023C4.4125 28.5625 6.10944 29.9996 8.09813 29.9996H20.9867C22.046 29.9996 22.9843 29.3167 23.3091 28.3095L27.4088 15.5946C27.5978 15.0084 27.6041 14.3787 27.4268 13.7887C26.9437 12.1815 25.2478 11.2703 23.6389 11.7529Z"/>
                                    <path d="M22.5017 7.56723C22.2384 8.42523 22.7213 9.33407 23.5801 9.59704L23.6099 9.60593L26.9637 10.5631C27.7512 10.7878 28.583 10.3928 28.9056 9.64075L29.7362 7.70454C31.9848 2.46273 24.0549 2.50427 22.5017 7.56723Z"/>
                                    <path d="M10.7071 14.707H4.12127L6.70706 12.1212C7.09758 11.7307 7.09758 11.0975 6.70706 10.707C6.31654 10.3165 5.68337 10.3165 5.29285 10.707L0.292847 15.707L5.29285 20.707C5.68337 21.0975 6.31654 21.0975 6.70706 20.707C7.09758 20.3165 7.09758 19.6833 6.70706 19.2928L4.12127 16.707H10.7071C11.2593 16.707 11.7071 16.2593 11.7071 15.707C11.7071 15.1547 11.2593 14.707 10.7071 14.707Z"/>
                                </svg>
                                <small>Comfort</small>
                            </div>

                            <!-- 6. Safety -->
                            <div class="etb-amenity-col" title="Certified chauffeur & safety">
                                <svg class="etb-amenity-svg" viewBox="0 0 100 100" fill="currentColor">
                                    <path d="m50 53.539c-4.4102 0-8.3594 1.9414-11.059 5.0117 0.98828 0.64062 1.7891 1.5391 2.3281 2.5898 2.0586-2.5391 5.2109-4.1602 8.7305-4.1602s6.6719 1.6211 8.7305 4.1602c0.53906-1.0508 1.3398-1.9492 2.3281-2.5898-2.6992-3.0703-6.6484-5.0117-11.059-5.0117zm0-36.488c6.6211 0 11.988 5.3594 11.988 11.988 0 6.6211-5.3711 11.98-11.988 11.98-6.6211 0-11.988-5.3594-11.988-11.98 0-6.6289 5.3711-11.988 11.988-11.988zm14.211 42.09c-2.5586 0.26172-4.5508 2.4102-4.5508 5.0391 0 1.7383 0.87891 3.2812 2.2188 4.1914h0.011719c2.9609 2.2891 6.3984 4.0508 10.16 4.9609 3.2188 0.78906 8.1289-1.9219 7.3008-6.9297-1.0781-6.5391-4.4805-12.289-9.3281-16.398-1.5117-1.2891-3.3516-2.4609-5.5195-3.4219-1.5 2.7109-3.3398 5.1992-5.4688 7.4102 2.0781 1.3203 3.8516 3.0781 5.1797 5.1484zm-3.6914-13.98c-3.0312-0.83984-6.5312-1.3203-10.52-1.3203-9.2383 0-15.859 2.6094-20.02 6.1602-4.8516 4.1094-8.25 9.8594-9.3281 16.398-0.82812 5.0117 4.0781 7.7188 7.3008 6.9297 3.7617-0.91016 7.1992-2.6719 10.16-4.9609h0.011719c1.3398-0.91016 2.2188-2.4492 2.2188-4.1914 0-2.6289-1.9883-4.7812-4.5508-5.0391 3-4.6719 8.25-7.7695 14.211-7.7695 1.7383 0 3.4219 0.26953 4.9883 0.75 0.21094-0.19141 0.41016-0.37891 0.60156-0.57813 1.8984-1.8984 3.5586-4.0391 4.9297-6.3789zm-24.762 26.781c1.6406 6.3281 7.3906 11.012 14.238 11.012 6.8516 0 12.602-4.6797 14.238-11.012-1.1484-0.67188-2.2617-1.4102-3.3086-2.2188-1.0117-0.69141-1.7891-1.6289-2.2891-2.6992-2.3281 0.98828-5.3398 1.5898-8.6406 1.5898-3.3008 0-6.3086-0.60156-8.6406-1.5898-0.5 1.0703-1.2812 2.0117-2.2891 2.6992-1.0508 0.80859-2.1602 1.5508-3.3086 2.2188zm24.801 0.21875c-0.87891-0.64844-2.0117-1.0508-3.2383-1.0508-2.8398 0-5.1406 2.1211-5.1406 4.7305 0 1.1992 0.48047 2.2891 1.2812 3.1211 3.2812-1.0586 5.9102-3.5781 7.1016-6.8008zm-14.02 6.8008c0.80078-0.82813 1.2812-1.9219 1.2812-3.1211 0-2.6094-2.3008-4.7305-5.1406-4.7305-1.2305 0-2.3594 0.39844-3.2383 1.0586 1.1992 3.2109 3.8203 5.7305 7.1016 6.7891z" fill-rule="evenodd"/>
                                </svg>
                                <small>Safety</small>
                            </div>
                        </div>


                        <!-- 3. PIED DE CARTE (Prix & All inclusive à droite du montant, Bouton Select à droite) -->
                        <div class="etb-quick-card-footer">
                            <div class="etb-quick-price-box">
                                <div class="etb-quick-price-display">
                                    <span class="etb-quick-amount">0</span>
                                    <span class="etb-quick-currency"><?php echo esc_html( $currency ); ?></span>
                                    <span class="etb-quick-all-inclusive">All inclusive</span>
                                </div>
                                <div class="etb-quick-price-detail" style="display: none;"></div>
                            </div>

                            <button type="button" class="etb-quick-select-btn">
                                <span class="etb-btn-text">Select</span>
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

                <!-- Bouton Email Inquiry direct (mailto) -->
                <a href="#" class="etb-quick-email-btn" id="etb-quick-email-btn">
                    <span class="dashicons dashicons-email-alt"></span>
                    <span>Email Inquiry</span>
                </a>

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

   

</div>