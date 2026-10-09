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
                        
                        <!-- Ligne Capacités avec nouveaux SVGs dorés et majuscules -->
                        <div class="etb-quick-specs">
                            <span class="etb-spec-item">
                                <svg viewBox="0 0 100 95" width="20" height="20" fill="currentColor" style="flex-shrink: 0;">
                                    <path d="m88.484 31.117c0 8.0312-6.5117 14.539-14.539 14.539-8.0312 0-14.543-6.5078-14.543-14.539s6.5117-14.539 14.543-14.543c8.0273 0 14.539 6.5117 14.539 14.543z"/>
                                    <path d="m0.90234 73.273c-3.0547 6.2812 2.0391 15.633 9.6914 17.562 16.133 3.6016 32.57 3.6016 48.703 0 7.6562-1.9336 12.75-11.281 9.6914-17.562-5.7109-11.867-18.844-22.293-34.047-22.391-15.203 0.10156-28.336 10.523-34.047 22.391z"/>
                                    <path d="m54.445 25.965c0 10.77-8.7305 19.504-19.5 19.504-10.77 0-19.504-8.7344-19.504-19.504 0-10.77 8.7344-19.5 19.504-19.5 10.77-0.003906 19.5 8.7305 19.5 19.5z"/>
                                    <path d="m99.328 66.391c-4.2578-8.8516-14.051-16.625-25.383-16.695-5.1719 0.03125-10.023 1.6719-14.176 4.2812 6.0273 4.4648 11.02 10.426 14.141 16.91 1.5391 3.1641 1.8438 6.8906 0.93359 10.605 5.7734-0.0625 11.539-0.73047 17.258-2.0078 5.707-1.4414 9.5078-8.4141 7.2266-13.094z"/>
                                </svg>
                                <?php echo esc_html( $max_pax ); ?> 
                                 <span class="etb-lbl-desktop">Passengers</span><span class="etb-lbl-mobile">Pax</span>
                            </span>
                            <span class="etb-spec-divider">|</span>
                            <span class="etb-spec-item">
                                <svg viewBox="20 15 60 88" width="23" height="23" fill="currentColor" style="flex-shrink: 0;">
                                    <path d="M70.75,26.75H60.028l1.056,5.476c2.108-0.315,4.109,1.073,4.517,3.185l0.868,4.5c0.418,2.169-1.001,4.267-3.171,4.685 l-1.227,0.237c-2.169,0.418-4.268-1.001-4.686-3.17l-0.867-4.5c-0.408-2.113,0.936-4.146,3.01-4.637l-1.113-5.775H54.75v-14 c0-0.019-0.01-0.034-0.011-0.052c0.002-0.032,0.01-0.062,0.01-0.095c0-0.773-0.626-1.397-1.397-1.397h-6.705 c-0.771,0-1.397,0.624-1.397,1.397c0,0.034,0.008,0.065,0.01,0.098c0,0.017-0.01,0.031-0.01,0.049v14h-16c-2.209,0-4,1.791-4,4v59 c0,2.209,1.791,4,4,4h6V94c0,0.69,0.559,1.25,1.25,1.25c0.689,0,1.25-0.56,1.25-1.25v-0.25h24.5V94c0,0.69,0.559,1.25,1.25,1.25 c0.689,0,1.25-0.56,1.25-1.25v-0.25h6c2.209,0,4-1.791,4-4v-59C74.75,28.541,72.959,26.75,70.75,26.75z M47.75,14h4.5v12.75h-4.5V14 z M63.39,84.078h-26.78c-1.027,0-1.86-0.834-1.86-1.859c0-1.028,0.833-1.859,1.86-1.859h26.78c1.026,0,1.859,0.831,1.859,1.859 C65.249,83.244,64.416,84.078,63.39,84.078z"/>
                                </svg>
                                <span><?php echo esc_html( $max_bag ); ?> Luggage</span>
                            </span>
                        </div>

                        <!-- Capsule kilométrique (Mode À l'heure) -->
                        <div class="etb-quick-km-info etb-quick-km-pill" style="display: none;"></div>

                        <!-- Rangée des 6 Équipements VIP : Track | M & G | Seat | Water | Candy | WiFi -->
                        <div class="etb-quick-amenities">
                            
                            <!-- 1. Track (Zoom léger & proportion idéale) -->
                            <div class="etb-amenity-col" title="Real-time flight and ride tracking">
                                <svg class="etb-amenity-svg" viewBox="7 9 88 80" fill="currentColor">
                                    <path d="m44.75 53.047c3.4141-0.70312 10.168-2.1289 13.609-2.8281l-7.0938-9.7344h-0.003906c-0.29297-0.40234-0.76562-0.64062-1.2656-0.64062h-7.8086c-0.50781 0.007812-0.98047 0.25391-1.2734 0.67188-0.29297 0.41406-0.36328 0.94531-0.19531 1.4219z"/>
                                    <path d="m88.836 55.469c-0.44531-0.007812-0.87109-0.1875-1.1875-0.5l-2.1875-2.1875c-2.3398-2.3438-5.6133-3.4961-8.9023-3.1406 0 0-44.855 9.3281-54.512 11.297-1.2656 0.15625-2.5-0.46484-3.125-1.5781l-2.6094-4.625c-0.82422-1.4805-2.3828-2.3945-4.0742-2.3906h-4.4219c-0.52734 0.003906-1.0117 0.27344-1.3008 0.71094s-0.33984 0.99219-0.13672 1.4766l4.7969 10.875v-0.003906c2.0156 5.2695 7.3242 8.5234 12.934 7.9375l21.637-2.2188-5.0312 13.484h0.003906c-0.17188 0.48047-0.10156 1.0117 0.19141 1.4297 0.28906 0.41797 0.76562 0.67188 1.2773 0.67969h7.8125-0.003906c0.5 0 0.97266-0.23828 1.2656-0.64062 0.17188-0.042969 12.141-16.992 12.438-16.793l25.605-4.2031v-0.003906c1.5273-0.13281 2.9062-0.97656 3.7188-2.2773 0.91016-1.5 0.9375-3.375 0.066406-4.8984-0.87109-1.5234-2.5-2.4531-4.2539-2.4297z"/>
                                    <path d="m74.996 44.531c4.1406 0 8.1172-1.6445 11.047-4.5742 2.9297-2.9297 4.5742-6.9062 4.5742-11.047 0-4.1445-1.6445-8.1172-4.5742-11.047-2.9297-2.9336-6.9062-4.5781-11.047-4.5781-4.1445 0-8.1172 1.6445-11.051 4.5781-2.9297 2.9297-4.5742 6.9023-4.5742 11.047 0.007812 4.1406 1.6562 8.1094 4.582 11.039 2.9297 2.9258 6.8984 4.5742 11.043 4.582zm-1.5625-23.918c-0.003906-0.41406 0.16016-0.8125 0.45312-1.1094 0.29297-0.29297 0.69141-0.45703 1.1094-0.45703 0.41406 0 0.8125 0.16406 1.1055 0.45703 0.29297 0.29688 0.45703 0.69531 0.45703 1.1094v6.7344h4.6875-0.003906c0.41797-0.003906 0.81641 0.16016 1.1094 0.45312 0.29297 0.29297 0.46094 0.69141 0.46094 1.1094 0 0.41406-0.16797 0.8125-0.46094 1.1055-0.29297 0.29297-0.69141 0.45703-1.1094 0.45703h-6.25 0.003906c-0.86328-0.003906-1.5625-0.70312-1.5625-1.5625z"/>
                                </svg>
                                <small>Track</small>
                            </div>

                            <!-- 2. M & G (Meet & Greet - Référence) -->
                            <div class="etb-amenity-col" title="Chauffeur meet and greet service">
                                <svg class="etb-amenity-svg" viewBox="0 0 128.072 230" fill="currentColor">
                                    <ellipse cx="64.024" cy="22.5" rx="22.495" ry="22.495"/>
                                    <path d="M119.076,125.347c-0.018,0-0.035-0.004-0.052-0.004v28.167H91.069H66.341h-4.635H36.978H9.023v-28.166 c-0.009,0-0.018,0.002-0.026,0.002c-1.389,0-2.741-0.285-4.019-0.849c-0.315-0.138-0.635-0.284-0.955-0.429v34.442h32.954v58.31 c0,6.829,5.537,12.363,12.363,12.363c6.828,0,12.365-5.534,12.365-12.363v-58.31h4.635v58.31c0,6.829,5.537,12.363,12.363,12.363 c6.828,0,12.365-5.534,12.365-12.363v-58.31h32.954v-34.43c-0.312,0.142-0.625,0.284-0.933,0.419 C121.815,125.061,120.463,125.347,119.076,125.347z"/>
                                    <path d="M4.023,121.855c0.585,0.272,1.163,0.55,1.762,0.813c1.046,0.46,2.138,0.677,3.212,0.677c0.009,0,0.018-0.002,0.026-0.002 v-0.001c3.068-0.01,5.99-1.794,7.303-4.785c1.245-2.833,0.71-5.993-1.109-8.245c-0.778-0.963-1.788-1.764-3.001-2.296 c-1.087-0.477-2.15-0.971-3.193-1.479v0c-0.005-0.002-0.011-0.005-0.016-0.008c-0.326-0.158-0.637-0.326-0.957-0.488 c-0.562-0.282-1.121-0.565-1.67-0.856c-0.789-0.422-1.566-0.851-2.332-1.287c-0.009-0.005-0.018-0.009-0.026-0.014v0v-2.293 c1.632,0.957,3.305,1.852,5,2.702v-9.472V70.343h20.661h7.293h10.835h32.442h10.814h7.315h20.639v24.461v9.5 c1.695-0.85,3.368-1.744,5-2.7v2.294v0c-0.014,0.008-0.028,0.015-0.041,0.022c-0.761,0.433-1.532,0.858-2.316,1.277 c-0.446,0.236-0.901,0.465-1.355,0.695c-0.425,0.217-0.843,0.439-1.277,0.651c-0.004,0.002-0.007,0.003-0.011,0.005v0 c-1.035,0.503-2.09,0.994-3.168,1.467c-1.213,0.532-2.224,1.334-3.002,2.298c-1.818,2.251-2.352,5.411-1.107,8.243 c1.309,2.981,4.218,4.763,7.277,4.783v0.001c0.018,0,0.034,0.004,0.052,0.004c1.074,0,2.166-0.218,3.212-0.677 c0.59-0.259,1.16-0.533,1.736-0.801v0c1.376-0.641,2.729-1.298,4.049-1.977v-18.421c-0.001,0.001-0.002,0.002-0.004,0.003v-0.003 c-1.396-0.896-2.744-1.818-4.045-2.76V65.343H95.632c-2.326-4.56-3.41-7.647-3.523-7.99l-0.004-0.01h-0.002 c-1.075-3.301-4.093-5.417-7.376-5.513c-0.45-0.082-0.913-0.131-1.387-0.131H44.706c-0.477,0-0.941,0.049-1.394,0.132 c-3.272,0.107-6.276,2.221-7.348,5.512h-0.002l-0.004,0.01c-0.113,0.343-1.197,3.43-3.523,7.99H4.023v33.38 C2.729,99.66,1.389,100.577,0,101.468v18.421v0C1.312,120.565,2.656,121.217,4.023,121.855L4.023,121.855z"/>
                                </svg>
                                <small>M & G</small>
                            </div>

                            <!-- 3. Seat (Cadrage recalé sur M&G) -->
                            <div class="etb-amenity-col" title="Child & baby seats available upon request">
                                <svg class="etb-amenity-svg" viewBox="10 5 70 78" fill="currentColor">
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
                                <small>Seat</small>
                            </div>

                            <!-- 4. Water (Cadrage recalé sur M&G) -->
                            <div class="etb-amenity-col" title="Complimentary bottled water">
                                <svg class="etb-amenity-svg" viewBox="20 4 60 92" fill="currentColor">
                                    <path d="m69 89c0 3.3125-2.6875 6-6 6h-26c-3.3125 0-6-2.6875-6-6v-19h38z"/>
                                    <path d="m69 65h-38v-17h38z"/>
                                    <path d="m69 43h-38l8-18h22z"/>
                                    <path d="m55 5c3.3125 0 6 2.6875 6 6v9h-22v-9c0-3.3125 2.6875-6 6-6z"/>
                                </svg>
                                <small>Water</small>
                            </div>

                            <!-- 5. Candy (Calé pour toucher la ligne haute et basse) -->
                            <div class="etb-amenity-col" title="Complimentary sweets & refreshments">
                                <svg class="etb-amenity-svg" viewBox="7 11 88 78" fill="currentColor">
                                    <path d="m29.719 74.594c-0.87891 1.8555-2.1719 4.0117-3.2266 5.2656-0.36328 0.43359-1.0078 0.48438-1.4375 0.125-0.42969-0.36328-0.48828-1.0078-0.125-1.4375 1.0938-1.3047 2.4102-3.6094 3.1602-5.3008-0.48047-0.40625-0.92578-0.85156-1.332-1.332-1.6914 0.75391-3.9961 2.0664-5.3047 3.1641-0.19141 0.16016-0.42578 0.23828-0.65625 0.23828-0.91406 0.035156-1.3945-1.2305-0.65625-1.8008 1.2578-1.0547 3.4141-2.3516 5.2656-3.2266-0.92969-1.2461-1.7031-2.5859-2.332-3.9922-11.77-0.48047-16.84 5.2617-17.062 5.5234-3.0703 3.6172 2.2422 6.1992 4.625 8.4219 1.9453 2.1484 1.0352 2.6328 3.8008 5.3242 2.6875 2.7656 3.168 1.8516 5.3242 3.8008 2.1992 2.3672 4.8281 7.7031 8.4219 4.625 0.26172-0.22266 6.0039-5.2852 5.5234-17.055-1.4023-0.62891-2.7461-1.4062-3.9922-2.3359z"/>
                                    <path d="m91.316 21.371c-1.2891-0.92969-2.7383-2.1328-3.1992-3.4141-0.375-0.80469-0.84375-1.8047-2.5547-3.5156-2.6875-2.7656-3.168-1.8516-5.3242-3.8008-2.207-2.3672-4.8164-7.7031-8.4219-4.625-0.26172 0.22266-6.0039 5.2891-5.5234 17.059 1.375 0.61719 2.6914 1.3711 3.9141 2.2773 0.875-1.8906 2.2188-4.1367 3.3047-5.4258 0.36328-0.42969 1.0078-0.48828 1.4375-0.125 0.42969 0.36328 0.48828 1.0078 0.125 1.4375-1.1289 1.3438-2.4922 3.7539-3.2305 5.4609 0.44531 0.38281 0.87891 0.78516 1.2578 1.2383 1.7031-0.73828 4.1055-2.0977 5.4453-3.2227 0.43359-0.36328 1.0742-0.30469 1.4375 0.125 0.36328 0.42969 0.30469 1.0742-0.125 1.4375-1.2812 1.0781-3.5078 2.4062-5.3867 3.2852 0.98828 1.3008 1.7812 2.6953 2.4258 4.1406 0.48047 0.019531 0.95703 0.039063 1.4141 0.039063 10.781 0 15.457-5.3008 15.672-5.5508 2.3125-2.6797-0.1875-5.0742-2.668-6.8164z"/>
                                    <path d="m71.168 28.832c-0.21094-0.21094-0.43359-0.39062-0.65234-0.58984-0.42578 1.9141-1.1484 2.9883-1.8008 3.9453-1.7617 2.0938-2.1328 6.1094-0.76953 8.4961 0.52344 1.1836 1.1172 2.5273 1.0664 4.9609 0.007812 2.4062-0.60938 3.7383-1.1523 4.9102-0.54297 1.1719-1.0117 2.1836-0.87891 4.3555 0.16406 1.8281 0.63281 2.7773 1.2031 3.6914l2.9844-2.9844c7.3828-7.3828 7.3828-19.402 0-26.785z"/>
                                    <path d="m67.027 31.039c0.69922-0.95312 1.4609-2.3477 1.6758-4.2578-2.707-1.918-5.793-3.0508-8.9609-3.3789 0.3125 2.0781 0.97656 3.0469 0.41406 5.9727-0.37891 2.1992-1.1562 3.3164-1.8438 4.3008-0.67188 0.96094-1.25 1.793-1.4727 3.7539-0.3125 4.0859 1.8125 4.2578 1.6211 8.5586 0.015624 4.2891-2.1133 4.3828-1.957 8.4922 0.14844 1.9492 0.69141 2.8008 1.3242 3.7852 0.64453 1.0078 1.3789 2.1523 1.6758 4.3945 0.37891 2.4766-0.070312 3.5938-0.46484 5.0898l7.668-7.668c-2.0547-2.6133-2.3477-7.5977-0.69531-10.383 0.5-1.0781 0.97266-2.0938 0.96484-4.0703 0.042969-2.0078-0.41016-3.0312-0.89062-4.1172-1.6484-2.9141-1.1836-7.9102 0.94922-10.469z"/>
                                    <path d="m33.781 72.844c-0.42578-2.6562 0.074219-4.1016 0.51953-5.3789 0.38281-1.1055 0.75-2.1523 0.55078-4.1016-0.14844-1.9688-0.69141-2.9297-1.2695-3.9453-1.9062-2.7188-1.9297-7.6562-0.078125-10.391 0.5625-1.0312 1.0938-2.0039 1.2148-3.9609 0.22266-2.6953-0.51172-3.5078-1.0586-5.5117l-4.8242 4.8242c-9.3438 8.9727-6.2969 25.543 5.5664 30.617-0.24219-0.59766-0.45703-1.293-0.61719-2.1562z"/>
                                    <path d="m56.75 68.797c0.26172-2.3008 1.1875-3.1211 0.73438-5.8398-0.24219-1.8203-0.76562-2.6406-1.375-3.5898-0.68359-1.0664-1.457-2.2734-1.6445-4.7617-0.25781-4.5391 1.8672-4.6055 1.9531-8.6602 0.082031-4.0703-2.043-4.2148-1.6133-8.7266 0.28516-2.4961 1.1055-3.6758 1.832-4.7148 0.62109-0.89062 1.2031-1.7266 1.5117-3.5078 0.54297-2.6523-0.26563-3.4922-0.48047-5.7031-3.1602 0.015625-6.3086 0.82812-9.1406 2.4141-1.0156 4.1367 1.1211 4.8125 0.32031 9.1602-0.60938 4.3242-2.7617 4.0938-3.2031 8.2539-0.23438 4.1484 1.9141 4.2812 1.8047 8.6523 0.10547 4.3516-2.0586 4.4805-1.8125 8.6445 0.1875 1.9844 0.76172 2.8438 1.4258 3.8398 0.67188 1.0117 1.4375 2.1562 1.7812 4.4141 0.40234 2.2344 0.050781 3.5664-0.26172 4.7422-0.19531 0.74219-0.37109 1.4062-0.35156 2.293 3.3594-1.0273 6.4375-3.3672 8.8828-6.0469-0.25-0.21094-0.40625-0.51953-0.37109-0.86719z"/>
                                    <path d="m46.617 72.898c0.28125-1.0664 0.54687-2.0742 0.22266-3.8906-0.28125-1.8359-0.85547-2.6992-1.4648-3.6172-0.71094-1.0703-1.5195-2.2852-1.7578-4.8047-0.34766-4.6094 1.7891-4.6602 1.8047-8.8125-0.007813-4.168-2.1406-4.2266-1.8008-8.8203 0.41016-4.6094 2.5312-4.3164 3.2227-8.418 0.61328-3.3945-0.85547-4.0742-0.60938-7.3086-1.2734 0.80469-9.5156 9.3477-10.797 10.551 0.0625 0.97266 0.30469 1.6758 0.59375 2.4727 0.4375 1.2031 0.92969 2.5703 0.72656 4.9688-0.14844 2.3867-0.84375 3.6602-1.457 4.7852-0.60938 1.1172-1.1328 2.0781-1.1445 4.2188 0.13672 4.418 2.4375 4.3438 2.7344 8.957 0.24219 2.3711-0.23438 3.7422-0.65234 4.9531-0.41797 1.1992-0.77734 2.2383-0.4375 4.3594 0.34375 1.8281 0.91016 2.7109 1.582 3.5664 2.8945 0.77734 5.9258 0.85547 8.8477 0.21094-0.11328-1.4453 0.15234-2.4609 0.39453-3.3711z"/>
                                </svg>
                                <small>Candy</small>
                            </div>

                            <!-- 6. WiFi (Cadrage recalé sur M&G) -->
                            <div class="etb-amenity-col" title="Complimentary on-board WiFi">
                                <svg class="etb-amenity-svg" viewBox="20 4 60 92" fill="currentColor">
                                    <path d="m65.582 4.6875h-31.164c-5.6055 0.007812-10.148 4.5508-10.156 10.156v70.312c0.007812 5.6055 4.5508 10.148 10.156 10.156h31.164c5.6055-0.007812 10.148-4.5508 10.156-10.156v-70.312c-0.007812-5.6055-4.5508-10.148-10.156-10.156zm5.4688 80.469c-0.003906 3.0195-2.4492 5.4648-5.4688 5.4688h-31.164c-3.0195-0.003906-5.4648-2.4492-5.4688-5.4688v-70.312c0.003906-3.0195 2.4492-5.4648 5.4688-5.4688h0.57422c-0.015626 0.089844-0.023438 0.18359-0.027344 0.27734 0 2.2422 1.8164 4.0586 4.0586 4.0625h21.953c2.2422-0.003906 4.0586-1.8203 4.0586-4.0625-0.003906-0.09375-0.011718-0.1875-0.027344-0.27734h0.57422c3.0195 0.003906 5.4648 2.4492 5.4688 5.4688zm-3.9023-43.301c0.91406 0.91797 0.91406 2.3984 0 3.3164-0.91406 0.91406-2.3984 0.91406-3.3125 0-3.6719-3.668-8.6484-5.7305-13.836-5.7305s-10.164 2.0625-13.836 5.7305c-0.91406 0.91406-2.3984 0.91406-3.3125 0-0.91406-0.91797-0.91406-2.3984 0-3.3164 4.5469-4.5469 10.719-7.1016 17.148-7.1016s12.602 2.5547 17.148 7.1016zm-5.1562 5.1523h0.003906c0.44141 0.44141 0.69141 1.0352 0.69531 1.6602 0 0.625-0.24609 1.2227-0.6875 1.6641-0.4375 0.44141-1.0391 0.6875-1.6602 0.6875-0.625 0-1.2227-0.25-1.6602-0.69141-4.7969-4.7969-12.57-4.7969-17.367 0-0.4375 0.44141-1.0352 0.69141-1.6602 0.69141-0.62109 0-1.2227-0.24609-1.6602-0.6875-0.44141-0.44141-0.6875-1.0391-0.6875-1.6641 0.003906-0.625 0.25391-1.2188 0.69531-1.6602 3.1836-3.1797 7.4961-4.9648 11.996-4.9648s8.8125 1.7852 11.996 4.9648zm-9.2461 12.422h0.003906c0 1.1133-0.67188 2.1133-1.6992 2.5391-1.0273 0.42578-2.207 0.19141-2.9961-0.59375-0.78516-0.78906-1.0195-1.9688-0.59375-2.9961 0.42578-1.0273 1.4258-1.6992 2.5391-1.6992 0.73047 0 1.4297 0.28906 1.9453 0.80469 0.51563 0.51562 0.80469 1.2148 0.80469 1.9453zm4.0703-7.2461h0.003906c0.44141 0.4375 0.69141 1.0312 0.69141 1.6562 0 0.62109-0.24609 1.2188-0.68359 1.6562-0.4375 0.44141-1.0352 0.69141-1.6602 0.69141s-1.2188-0.24609-1.6602-0.68359c-1.9414-1.9414-5.082-1.9414-7.0234 0-0.44141 0.4375-1.0391 0.68359-1.6602 0.68359s-1.2188-0.25-1.6562-0.69141c-0.4375-0.4375-0.68359-1.0352-0.68359-1.6562 0-0.625 0.25-1.2188 0.69141-1.6562 3.7695-3.7617 9.8711-3.7617 13.641 0z"/>
                                </svg>
                                <small>WiFi</small>
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
        <div class='etb-notice-wrapper'>
        <!-- Bandeau de réservation urgente (< 24h) identique au Checkout -->
        <div id="etb-quick-urgent-notice" style="display: block;background: rgb(145 11 11 / 84%);border: 1px solid rgb(255 24 79 / 91%);border-radius: 10px;padding: 12px 16px;margin: 18px 0px;font-size: 12px;color:#ffffff;line-height: 1.45;text-align: center; width: 60%;">
            <strong style="color: #ffffff; display: block; margin-bottom: 2px;"><img draggable="false" role="img" class="emoji" alt="⏱️" src="https://s.w.org/images/core/emoji/17.0.2/svg/23f1.svg"> Short-Notice Pickup (&lt; 24h)</strong>
            For bookings scheduled within 24 hours, our dispatch team will confirm chauffeur availability before any payment is collected. You will receive a response within 1 hour during business hours (8:00 AM – 9:00 PM).
        </div>
        </div>


       
        <!-- 4. BARRE DE CONFIRMATION / RÉSERVATION EN 3 COLONNES -->
        <div class="etb-quick-confirm-bar" id="etb-quick-booking-bar" style="display: none;">
            
            <!-- Injection JS dynamique des Colonnes 1 (Véhicule) et 2 (Prix) -->
            <div id="etb-quick-dynamic-bar-content" class="etb-bar-dynamic-wrapper"></div>
            
            <!-- Colonne 3 : Boutons d'action -->
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