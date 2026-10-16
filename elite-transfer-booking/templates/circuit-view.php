<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables d'environnement & Réglages
$gen_settings  = get_option( 'etb_general_settings', array() );
$form_settings = get_option( 'etb_form_settings', array() );
$currency      = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';
$sec_token     = class_exists( 'ETB_Security' ) ? ETB_Security::generate_timestamp_token() : array( 'time' => time(), 'token' => '' );
$extras        = get_posts( array( 'post_type' => 'tour_extra', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
?>

<div class="co-circuit-wrapper" id="co-circuit-app" data-circuit-id="<?php echo esc_attr( $circuit_id ); ?>" style="position: relative; width: 100%; clear: both; display: block;">

   <?php
    // 0. EXTRACTION DES DONNÉES DE L'OPTION ACTIVE
    $active_option = ! empty( $options ) && is_array( $options ) ? reset( $options ) : array();
    
    // Titre de base pour les balises alt des images
    $hero_title = get_the_title( $circuit_id );

    // Rétrocompatibilité et récupération des sections dynamiques de présentation
    $overview_sections = ! empty( $active_option['overview_sections'] ) ? $active_option['overview_sections'] : array();
    if ( empty( $overview_sections ) ) {
        // Fallback si la page n'a pas encore été sauvegardée depuis la mise à jour
        if ( ! empty( $active_option['hero_desc'] ) ) {
            $overview_sections[] = array( 'title' => $active_option['hero_title'] ?? '', 'desc' => $active_option['hero_desc'] );
        }
        if ( ! empty( $active_option['overview_desc'] ) ) {
            $overview_sections[] = array( 'title' => $active_option['overview_title'] ?? 'Overview', 'desc' => $active_option['overview_desc'] );
        }
    }
    
    // Variables Trip Info
    $trip_info_title = ! empty( $active_option['trip_info_title'] ) ? $active_option['trip_info_title'] : 'Trip Info';
    $trip_facts      = ! empty( $active_option['trip_facts'] ) && is_array( $active_option['trip_facts'] ) ? $active_option['trip_facts'] : array();
    
    // Titre des points forts
    $highlights_title = ! empty( $active_option['highlights_title'] ) ? $active_option['highlights_title'] : ( ! empty( $active_option['hero_subtitle'] ) ? $active_option['hero_subtitle'] : 'Points forts :' );
    $highlights_items = ! empty( $active_option['highlights'] ) ? $active_option['highlights'] : ( ! empty( $active_option['hero_bullets'] ) ? array_filter( array_map( 'trim', explode( "\n", $active_option['hero_bullets'] ) ) ) : array() );

    // Récupération des URLs de la galerie photo
    $gallery_enabled = ! empty( $active_option['gallery_enabled'] ) && '1' === (string) $active_option['gallery_enabled'];
    $gallery_raw_ids = $active_option['gallery_image_ids'] ?? '';
    $gallery_ids     = array_filter( array_map( 'absint', explode( ',', (string) $gallery_raw_ids ) ) );
    $gallery_urls    = array();
    if ( $gallery_enabled && ! empty( $gallery_ids ) ) {
        foreach ( $gallery_ids as $gid ) {
            $url = wp_get_attachment_image_url( $gid, 'large' );
            if ( $url ) $gallery_urls[] = $url;
        }
    }
    ?>

    <!-- 0. MOSAÏQUE PHOTO VIP (BENTO GRID CALIBRÉ 360PX) -->
    <?php if ( ! empty( $gallery_urls ) ) : 
        $count = count( $gallery_urls );
        $alt_label = $hero_title ?: 'Circuit';
    ?>
        <div class="co-vip-bento-gallery" data-gallery="<?php echo esc_attr( wp_json_encode( $gallery_urls ) ); ?>" style="width: 100%; display: block; clear: both; margin-bottom: 35px;">
            <?php if ( $count === 1 ) : ?>
                <div class="co-bento-item co-bento-single" data-index="0">
                    <img src="<?php echo esc_url( $gallery_urls[0] ); ?>" alt="<?php echo esc_attr( $alt_label ); ?>">
                </div>
            <?php elseif ( $count === 2 ) : ?>
                <div class="co-bento-duo">
                    <div class="co-bento-item" data-index="0">
                        <img src="<?php echo esc_url( $gallery_urls[0] ); ?>" alt="<?php echo esc_attr( $alt_label ); ?>">
                    </div>
                    <div class="co-bento-item" data-index="1">
                        <img src="<?php echo esc_url( $gallery_urls[1] ); ?>" alt="<?php echo esc_attr( $alt_label ); ?>">
                    </div>
                </div>
            <?php else : ?>
                <div class="co-bento-grid">
                    <div class="co-bento-item co-bento-main" data-index="0">
                        <img src="<?php echo esc_url( $gallery_urls[0] ); ?>" alt="<?php echo esc_attr( $alt_label ); ?>">
                    </div>
                    <div class="co-bento-side">
                        <div class="co-bento-item co-bento-sub-top" data-index="1">
                            <img src="<?php echo esc_url( $gallery_urls[1] ); ?>" alt="<?php echo esc_attr( $alt_label ); ?>">
                        </div>
                        <div class="co-bento-sub-bottom">
                            <div class="co-bento-item co-bento-thumb" data-index="2">
                                <img src="<?php echo esc_url( $gallery_urls[2] ); ?>" alt="<?php echo esc_attr( $alt_label ); ?>">
                            </div>
                            <div class="co-bento-item co-bento-thumb co-bento-last" data-index="3">
                                <img src="<?php echo esc_url( $gallery_urls[3] ?? $gallery_urls[0] ); ?>" alt="<?php echo esc_attr( $alt_label ); ?>">
                                <span class="co-bento-badge" data-index="3">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    Gallery
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- 1. GRAND TITRE DU CIRCUIT (S'affiche uniquement si vous le tapez manuellement dans la metabox) -->
    <?php if ( $hero_title ) : ?>
        <h1 style="font-family: var(--etb-theme-font-head, 'Sunsive-Medium', sans-serif); color: var(--etb-theme-secondary, #5b3516); font-size: 32px; font-weight: 700; margin: 15px 0 20px 0; line-height: 1.25; clear: both; display: none;">
            <?php echo esc_html( $hero_title ); ?>
        </h1>
    <?php endif; ?>

    <!-- 2. BANDEAU TRIP INFO -->
    <?php if ( ! empty( $trip_facts ) ) : ?>
        <div class="co-vip-tripinfo-section" style="margin-bottom: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 22px; width: 100%; clear: both;">
            
            <!-- CORRECTION : RESTAURATION DU VRAI TITRE "TRIP INFO" -->
            <?php if ( $trip_info_title ) : ?>
                <h3 class="co-vip-title" style="margin: 0 0 18px 0; border: none; padding: 0; font-size: 24px !important; font-weight: 600 !important; line-height: 1.25 !important; font-family: var(--etb-theme-font-head, 'Sunsive-Medium', sans-serif); color: var(--etb-theme-secondary, #5b3516); text-transform: capitalize;">
                    <?php echo esc_html( $trip_info_title ); ?>
                </h3>
            <?php endif; ?>

            <div class="co-vip-tripinfo-row" style="display: flex; flex-wrap: wrap; gap: 24px 40px; align-items: center;">
           
                <?php foreach ( $trip_facts as $fact ) : 
                    $f_icon  = ! empty( $fact['icon'] ) ? $fact['icon'] : 'fas fa-info-circle';
                    $f_label = ! empty( $fact['label'] ) ? $fact['label'] : '';
                    $f_value = ! empty( $fact['value'] ) ? $fact['value'] : '';
                    if ( empty( $f_label ) && empty( $f_value ) ) continue;
                ?>
                    <div class="co-vip-tripinfo-item" style="display: flex; align-items: center; gap: 14px;">
                        <span class="co-vip-tripinfo-icon" style="color: var(--etb-theme-primary, #ba6827); font-size: 26px; line-height: 1; display: inline-flex; align-items: center; justify-content: center; width: 32px; flex-shrink: 0;">
                            <i class="<?php echo esc_attr( $f_icon ); ?>"></i>
                        </span>
                        <div class="co-vip-tripinfo-text" style="display: flex; flex-direction: column;">
                            <?php if ( $f_label ) : ?>
                                <span class="co-vip-tripinfo-label" style="font-size: 12.5px; color: var(--etb-theme-text, #797979); line-height: 1.2; margin-bottom: 3px;">
                                    <?php echo esc_html( $f_label ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( $f_value ) : ?>
                                <span class="co-vip-tripinfo-value" style="font-family: var(--etb-theme-font-head, 'Sunsive-Medium', sans-serif); font-size: 15px; font-weight: 600; color: var(--etb-theme-secondary, #5b3516); line-height: 1.3;">
                                    <?php echo esc_html( $f_value ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. SECTION 2 COLONNES : OVERVIEW (GAUCHE) & CARTE POINTS FORTS SANS BADGE (DROITE) -->
    <div class="co-hero-overview-grid" style="display: grid; grid-template-columns: 1.35fr 1fr; gap: 40px; align-items: start; margin-bottom: 40px; width: 100%; clear: both;">
        
       <!-- COLONNE GAUCHE : SECTIONS DE PRÉSENTATION DYNAMIQUES -->
        <div class="co-hero-text-col">
            <?php foreach ( $overview_sections as $sec ) : ?>
                <div class="co-vip-overview-section" style="margin-bottom: 30px;">
                    <?php if ( ! empty( $sec['title'] ) ) : ?>
                        <h2 style="font-family: var(--etb-theme-font-head, 'Sunsive-Medium', sans-serif); color: var(--etb-theme-secondary, #5b3516); font-size: 26px; font-weight: 700; margin: 0 0 14px 0; line-height: 1.25;">
                            <?php echo esc_html( $sec['title'] ); ?>
                        </h2>
                    <?php endif; ?>

                    <?php if ( ! empty( $sec['desc'] ) ) : ?>
                        <div class="co-hero-desc" style="color: var(--etb-theme-text, #797979); font-size: 15.5px; line-height: 1.75; font-weight: 500;">
                            <?php echo wp_kses_post( wpautop( $sec['desc'] ) ); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- COLONNE DROITE : CARTE POINTS FORTS ÉPURÉE SANS BADGE DE DURÉE -->
        <?php if ( ! empty( $highlights_items ) ) : ?>
            <div class="co-hero-highlights-col">
                <div class="co-vip-highlights-card" style="background: #ffffff; border: 1.5px solid #ebd9cc; border-radius: 12px; padding: 22px 24px; box-shadow: 0 4px 20px rgba(91, 53, 22, 0.04);">
                    
                    <h3 style="display: flex; align-items: center; gap: 10px; font-family: var(--etb-theme-font-head, 'Sunsive-Medium', sans-serif); color: var(--etb-theme-secondary, #5b3516); font-size: 20px; font-weight: 700; margin: 0 0 18px 0; padding-bottom: 12px; border-bottom: 1px solid #f8fafc;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--etb-theme-primary, #ba6827)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        <span><?php echo esc_html( $highlights_title ); ?></span>
                    </h3>

                    <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px;">
                        <?php foreach ( $highlights_items as $hl ) : ?>
                            <li style="display: flex; align-items: flex-start; gap: 12px; color: var(--etb-theme-text, #797979); font-size: 14.5px; line-height: 1.5;">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="var(--etb-theme-primary, #ba6827)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="16 9 10 15 7 12"></polyline>
                                </svg>
                                <span><?php echo esc_html( $hl ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div> <!-- Fin de co-hero-overview-grid -->

    <?php
    // Récupération dynamique de tous les véhicules publiés
    $vehicles = get_posts( array(
        'post_type'      => 'tour_vehicle',
        'post_status'    => 'publish',
        'numberposts'    => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ) );
    ?>



    <!-- 1. SECTION HAUTE : CHOISISSEZ VOTRE VÉHICULE (GRILLE PLEINE LARGEUR DYNAMIQUE) -->
    <?php if ( ! empty( $vehicles ) ) : ?>
    <div class="co-top-vehicles-section">
        <h2 class="co-section-title"><span class="dashicons dashicons-car"></span> Choose your vehicle</h2>
        
        <div class="co-vehicles-grid" id="co-vehicles-grid">
            
            <?php foreach ( $vehicles as $vehicle ) : 
                $base_price    = get_post_meta( $vehicle->ID, '_etb_base_price', true );
                $hourly_rate   = get_post_meta( $vehicle->ID, '_etb_hourly_rate', true );
                $pack_10h      = get_post_meta( $vehicle->ID, '_etb_pack_10h', true );
                $sup_hour_rate = get_post_meta( $vehicle->ID, '_etb_sup_hour_rate', true );
                $display_price = ( $hourly_rate > 0 ) ? $hourly_rate : $base_price;
                $max_pax       = get_post_meta( $vehicle->ID, '_etb_max_pax', true ) ?: 1;
                $max_bag       = get_post_meta( $vehicle->ID, '_etb_max_baggage', true ) ?: 0;
                $img_url       = get_the_post_thumbnail_url( $vehicle->ID, 'full' ) ?: ( defined('ETB_URL') ? ETB_URL . 'public/images/default-car.png' : '' );
                $hover_img     = get_post_meta( $vehicle->ID, '_etb_hover_image', true );
            ?>
                <div class="etb-vehicle-card" 
                     data-id="<?php echo $vehicle->ID; ?>" 
                     data-hourly-rate="<?php echo esc_attr( $hourly_rate ); ?>"
                     data-pack-10h="<?php echo esc_attr( $pack_10h ); ?>"
                     data-sup-hour-rate="<?php echo esc_attr( $sup_hour_rate ); ?>"
                     data-base-price="<?php echo esc_attr( $base_price ); ?>"
                     data-max-pax="<?php echo esc_attr( $max_pax ); ?>" 
                     data-max-baggage="<?php echo esc_attr( $max_bag ); ?>">
                    <span class="etb-selection-check"><i class="dashicons dashicons-yes"></i></span>
                    <?php if ( $img_url ) : ?>
                        <div class="etb-vehicle-image-wrapper">
                            <img src="<?php echo esc_url( $img_url ); ?>" class="etb-vehicle-image etb-img-static" alt="<?php echo esc_attr( $vehicle->post_title ); ?>">
                            <?php if ( ! empty( $hover_img ) ) : ?>
                                <img src="<?php echo esc_url( $hover_img ); ?>" class="etb-vehicle-image etb-img-hover" alt="<?php echo esc_attr( $vehicle->post_title ); ?> (animated)">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <h3 class="etb-vehicle-name"><?php echo esc_html( $vehicle->post_title ); ?></h3>
                    
                    <p class="etb-vehicle-price"><?php echo esc_html( $display_price ); ?> <?php echo esc_html( $currency ); ?> /h</p>
                    <div class="etb-vehicle-specs">
                        <span>👤 <?php echo esc_html( $max_pax ); ?> Max pax</span>
                        <span>🧳 <?php echo esc_html( $max_bag ); ?> Luggage</span>
                    </div>
                     <input type="hidden" name="etb_car_qty[<?php echo $vehicle->ID; ?>]" value="0">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- LAYOUT PRINCIPAL 2 COLONNES -->
    <div class="co-main-layout">
        
       <!-- COLONNE GAUCHE : DÉTAILS DE L'OPTION SÉLECTIONNÉE -->
        <div class="co-left-details">
            
            <!-- 1. ONGLETS DES VILLES DE DÉPART (DANS LA COLONNE GAUCHE) -->
            <div class="co-departure-nav">
                <span class="co-nav-label">Departure city:</span>
                <div class="co-nav-buttons">
                    <?php 
                    $is_first = true;
                    foreach ( $options as $opt_id => $opt_data ) : 
                        $city = ! empty( $opt_data['city_name'] ) ? esc_html( $opt_data['city_name'] ) : 'Departure';
                        $add_price = floatval( $opt_data['additional_price'] ?? 0 );
                        $price_suffix = ( $add_price > 0 ) ? ' (+' . $add_price . ' ' . $currency . ')' : '';
                    ?>
                    <button type="button" 
                            class="co-pub-tab-btn <?php echo $is_first ? 'active' : ''; ?>" 
                            data-target="<?php echo esc_attr( $opt_id ); ?>"
                            data-duration="<?php echo esc_attr( $opt_data['duration_hours'] ?? 1 ); ?>"
                            data-price="<?php echo esc_attr( $add_price ); ?>"
                            data-time="<?php echo esc_attr( $opt_data['departure_time'] ?? '09:00' ); ?>">
                        <span class="dashicons dashicons-location"></span>
                        <?php echo $city . esc_html( $price_suffix ); ?>
                    </button>
                    <?php 
                        $is_first = false;
                    endforeach; 
                    ?>
                </div>
            </div>

           <?php 
            $is_first = true;
            foreach ( $options as $opt_id => $opt_data ) : 
                $timeline        = $opt_data['timeline'] ?? array();
                $inclusions      = ! empty( $opt_data['inclusions'] ) ? explode( "\n", trim( $opt_data['inclusions'] ) ) : array();
                $exclusions      = ! empty( $opt_data['exclusions'] ) ? explode( "\n", trim( $opt_data['exclusions'] ) ) : array();
                $overview_title  = $opt_data['overview_title'] ?? 'Circuit Overview';
                $overview_desc   = $opt_data['overview_desc'] ?? '';
                $trip_info_title = ! empty( $opt_data['trip_info_title'] ) ? $opt_data['trip_info_title'] : 'trip info';
                $trip_facts      = ! empty( $opt_data['trip_facts'] ) && is_array( $opt_data['trip_facts'] ) ? $opt_data['trip_facts'] : array();
                
                // Rétrocompatibilité : on récupère les highlights, sinon on utilise les anciens badges
                $highlights = $opt_data['highlights'] ?? array();
                if ( empty( $highlights ) ) {
                    $highlights = array_filter( array(
                        $opt_data['badge_1'] ?? '',
                        $opt_data['badge_2'] ?? '',
                        $opt_data['badge_3'] ?? '',
                        $opt_data['badge_4'] ?? '',
                    ) );
                }
            ?>
                <div class="co-option-pane <?php echo $is_first ? 'active' : ''; ?>" 
                     id="co-pane-<?php echo esc_attr( $opt_id ); ?>" 
                     data-base-departure="<?php echo esc_attr( $opt_data['departure_time'] ?? '09:00' ); ?>">

                   <!-- PROGRAMME / TIMELINE (ACCORDÉON AVEC COMMANDE GLOBALE) -->
                    <?php if ( ! empty( $timeline ) ) : ?>
                        <div class="co-timeline-block">
                            
                            <!-- En-tête Itinéraire avec switch Expand all / Collapse all -->
                            <div class="co-timeline-head-bar" style="display: flex; justify-content: space-between; align-items: center; margin-top: 40px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                                <h3 class="co-vip-title" style="margin: 0; padding: 0; border: none;">Itinerary</h3>
                                
                                <div class="co-expand-all-wrap" style="display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;">
                                    <span class="co-expand-all-label" style="font-size: 13.5px; font-weight: 600; color: #5b3516;">Expand all</span>
                                    <button type="button" class="co-expand-toggle-switch" aria-label="Toggle all steps" style="position: relative; width: 40px; height: 22px; background: #cbd5e1; border-radius: 50px; border: none; cursor: pointer; padding: 2px; transition: background 0.25s ease;">
                                        <span class="co-expand-switch-thumb" style="display: block; width: 18px; height: 18px; background: #ffffff; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: transform 0.25s ease;"></span>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="co-vip-timeline">
                                <?php foreach ( $timeline as $index => $step ) : 
                                    $is_first_step = ($index === 0);
                                    $time_label = ! empty( $step['time'] ) ? $step['time'] . ' : ' : '';
                                ?>
                                    <div class="co-vip-step <?php echo $is_first_step ? 'is-open' : ''; ?>">
                                        <div class="co-vip-step-marker <?php echo $is_first_step ? 'filled' : ''; ?>"></div>
                                        
                                        <div class="co-vip-step-header">
                                            <h4 class="co-vip-step-title" data-base-time="<?php echo esc_attr( $step['time'] ?? '' ); ?>">
                                                <span class="co-timeline-time-display"><?php echo esc_html( $time_label ); ?></span><?php echo esc_html( $step['title'] ?? '' ); ?>
                                            </h4>
                                            <svg class="co-vip-step-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </div>
                                        
                                        <div class="co-vip-step-body">
                                            <!-- Paragraphe d'introduction de l'étape -->
                                            <?php if ( ! empty( $step['desc'] ) ) : ?>
                                                <div style="margin: 0 0 16px 0; color: var(--etb-theme-text, #797979); font-size: 15px; line-height: 1.7;">
                                                    <?php echo wp_kses_post( wpautop( $step['desc'] ) ); ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Sous-sections détaillées (Sous-titre + Paragraphe) -->
                                            <?php if ( ! empty( $step['subblocks'] ) && is_array( $step['subblocks'] ) ) : ?>
                                                <div class="co-vip-step-subblocks" style="display: flex; flex-direction: column; gap: 16px; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                                                    <?php foreach ( $step['subblocks'] as $sub ) : 
                                                        $sub_title = ! empty( $sub['title'] ) ? $sub['title'] : '';
                                                        $sub_desc  = ! empty( $sub['desc'] ) ? $sub['desc'] : '';
                                                    ?>
                                                        <div class="co-vip-subblock-item">
                                                            <?php if ( $sub_title ) : ?>
                                                                <h5 style="font-family: var(--etb-theme-font-head, 'Sunsive-Medium', sans-serif); color: var(--etb-theme-secondary, #5b3516); font-size: 16.5px; font-weight: 600; margin: 0 0 6px 0; line-height: 1.35;">
                                                                    <?php echo esc_html( $sub_title ); ?>
                                                                </h5>
                                                            <?php endif; ?>

                                                            <?php if ( $sub_desc ) : ?>
                                                                <div style="margin: 0; color: var(--etb-theme-text, #797979); font-size: 14.5px; line-height: 1.7;">
                                                                    <?php echo wp_kses_post( wpautop( $sub_desc ) ); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ( empty( $step['desc'] ) && empty( $step['subblocks'] ) ) : ?>
                                                <em>No description for this step.</em>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- INCLUS / NON INCLUS (STYLE PHOTO 2) -->
                    <?php if ( ! empty( $inclusions ) || ! empty( $exclusions ) ) : ?>
                        <div class="co-vip-inclusions-section" style="display: block; width: 100%; border-top: 1px solid #f1f5f9; padding-top: 35px; margin-top: 35px;">
                            
                            
                            <div class="co-vip-inclusions-wrap">
                                
                                <!-- BLOC INCLUS (CERCLE VERT) -->
                                <?php if ( ! empty( $inclusions ) ) : ?>
                                    <div>
                                        <h4 style="font-family: 'Sunsive-Medium', Sans-serif; color: #5b3516; font-size: 18px; font-weight: 600; margin: 0 0 16px 0;">Included:</h4>
                                        <ul class="co-vip-inc-list" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px;">
                                            <?php foreach ( $inclusions as $item ) : 
                                                if ( empty( trim( $item ) ) ) continue;
                                            ?>
                                                <li style="display: flex; align-items: flex-start; gap: 12px; color: #797979; font-size: 15px; line-height: 1.5;">
                                                    <!-- Icône Cercle Coche Verte (Photo 2) -->
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <polyline points="16 9 10 15 7 12"></polyline>
                                                    </svg>
                                                    <span><?php echo esc_html( trim( $item ) ); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <!-- BLOC NON INCLUS (CERCLE ROUGE) -->
                                <?php if ( ! empty( $exclusions ) ) : ?>
                                    <div>
                                        <h4 style="font-family: 'Sunsive-Medium', Sans-serif; color: #5b3516; font-size: 18px; font-weight: 600; margin: 0 0 16px 0;">Not included:</h4>
                                        <ul class="co-vip-inc-list" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px;">
                                            <?php foreach ( $exclusions as $item ) : 
                                                if ( empty( trim( $item ) ) ) continue;
                                            ?>
                                                <li style="display: flex; align-items: flex-start; gap: 12px; color: #797979; font-size: 15px; line-height: 1.5;">
                                                    <!-- Icône Cercle Croix Rouge (Photo 2) -->
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                                    </svg>
                                                    <span><?php echo esc_html( trim( $item ) ); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            <?php 
                $is_first = false;
            endforeach; 
            ?>
        </div> <!-- CORRECTION ICI : Fermeture de la div .co-left-details -->


        <!-- COLONNE DROITE : WIDGET DE RÉSERVATION ETB (DIRECT & SANS SHORTCODE) -->
        <div class="co-right-sidebar">
            <div class="co-sticky-box">
                
                <form class="etb-booking-widget" id="etb-booking-app" onsubmit="return false;">
                    
                    <!-- CHAMPS DE SÉCURITÉ & ANTI-SPAM (Honeypot + Timestamp signé) -->
                    <div style="position: absolute !important; left: -9999px !important; top: -9999px !important; opacity: 0 !important; width: 0 !important; height: 0 !important; overflow: hidden !important;" aria-hidden="true">
                        <input type="text" name="etb_hp_email" value="" tabindex="-1" autocomplete="off">
                        <input type="hidden" name="etb_sec_time" value="<?php echo esc_attr( $sec_token['time'] ); ?>">
                        <input type="hidden" name="etb_sec_token" value="<?php echo esc_attr( $sec_token['token'] ); ?>">
                    </div>

                    <!-- EN-TÊTE PRIX ET CAPACITÉ DU WIDGET -->
                    <div class="etb-sidebar-price-card" id="etb-sidebar-price-header">
                        <span class="etb-sidebar-label"></span>
                        <div class="etb-sidebar-amount-row">
                            <span class="etb-sidebar-currency"><?php echo esc_html( $currency ); ?></span>
                            <span class="etb-sidebar-val" id="etb-sidebar-val">0</span>
                        </div>
                        <p class="etb-sidebar-pax-notice" id="etb-sidebar-pax-notice">
                            Maximum passengers: <strong id="etb-sidebar-max-pax">0</strong>
                        </p>
                    </div>

                    <!-- SECTION : PASSAGERS -->
                    <section class="etb-section">
                        <h2 class="etb-section-title"><span class="dashicons dashicons-groups"></span> Number of passengers</h2>
                        <!-- Message d'alerte si clic sur + sans véhicule -->
                        <p class="etb-error-message" id="etb-vehicle-selection-error" style="display:none; margin-top: 0; margin-bottom: 16px;">
                            ⚠ Please select at least one vehicle before adding passengers.
                        </p>
                        <div class="etb-pax-grid">
                            <?php if ( ( $form_settings['show_adults'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-pax-card">
                                    <div class="etb-pax-info">
                                        <strong>Adults</strong>
                                        <span>12+ years</span>
                                    </div>
                                    <div class="etb-qty-control">
                                        <button type="button" class="etb-qty-btn etb-minus" aria-label="Diminuer">-</button>
                                        <input type="number" name="etb_adults" value="1" min="1" readonly>
                                        <button type="button" class="etb-qty-btn etb-plus" aria-label="Augmenter">+</button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ( ( $form_settings['show_children'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-pax-card">
                                    <div class="etb-pax-info">
                                        <strong>Children</strong>
                                        <span>2-11 years</span>
                                    </div>
                                    <div class="etb-qty-control">
                                        <button type="button" class="etb-qty-btn etb-minus" aria-label="Diminuer">-</button>
                                        <input type="number" name="etb_children" value="0" min="0" readonly>
                                        <button type="button" class="etb-qty-btn etb-plus" aria-label="Augmenter">+</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="etb-error-message" id="etb-pax-capacity-error" style="display:none;">
                            ⚠ The number of passengers exceeds the capacity of the selected vehicles.
                        </p>
                    </section>

                    <!-- SECTION : ADRESSE DE PRISE EN CHARGE -->
                    <?php if ( ( $form_settings['show_pickup'] ?? '1' ) !== '0' ) : ?>
                    <section class="etb-section">
                        <h2 class="etb-section-title"><span class="dashicons dashicons-location"></span> Pickup address</h2>
                        <div class="etb-field etb-field-full">
                            <div class="etb-field-icon-wrapper">
                                <span class="dashicons dashicons-location"></span>
                                <input type="text" name="etb_pickup_address" id="etb-pickup-address" placeholder="Ex: Hotel des Thermes, Antsirabe..." autocomplete="off">
                            </div>
                        </div>
                        <p class="etb-error-message" id="etb-pickup-error" style="display:none;">
                            ⚠ Please provide the pickup address.
                        </p>
                        
                        <!-- Lieu de dépose différent -->
                        <div class="etb-field etb-field-full etb-dropoff-toggle-wrapper">
                            <label id="etb-dropoff-label" class="etb-toggle-dropoff-label" for="etb-diff-dropoff-cb">
                                <input type="checkbox" id="etb-diff-dropoff-cb" class="hidden-input">
                                <span class="etb-custom-box">
                                    <span class="dashicons dashicons-yes"></span>
                                </span>
                                <span class="etb-dropoff-label-text">My drop-off location is different from the pickup location</span>
                            </label>
                        </div>
                        
                        <div class="etb-field etb-field-full" id="etb-dropoff-container" style="display: none;">
                            <label>Precise drop-off location (Address or Hotel name) *</label>
                            <div id="etb-dropoff-div" class="etb-field-icon-wrapper">
                                <textarea name="etb_dropoff_info" id="etb-dropoff-info" rows="2" placeholder="Ex: Hotel Colbert, Antananarivo..."></textarea>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- SECTION : OPTIONS SUPPLÉMENTAIRES (EXTRAS) -->
                    <?php if ( ( $form_settings['show_extras'] ?? '1' ) !== '0' && ! empty( $extras ) ) : ?>
                    <section class="etb-section">
                        <h2 class="etb-section-title"><span class="dashicons dashicons-star-filled"></span> Additional options</h2>
                        <div class="etb-grid-2">
                            <?php foreach ( $extras as $extra ) : 
                                $e_price = get_post_meta( $extra->ID, '_etb_price', true );
                                $e_type  = get_post_meta( $extra->ID, '_etb_price_type', true );
                                $e_icon  = get_post_meta( $extra->ID, '_etb_icon', true ) ?: 'dashicons-tag';
                                $price_display = ( $e_price <= 0 ) ? 'Free' : '+ ' . $e_price . ' ' . $currency;
                            ?>
                                <div class="etb-pax-card etb-extra-item <?php echo ( $e_type === 'per_quantity' ) ? 'etb-type-qty' : 'etb-type-toggle'; ?>" 
                                     data-id="<?php echo esc_attr( $extra->ID ); ?>" 
                                     data-price="<?php echo esc_attr( $e_price ); ?>">

                                    <div class="etb-option-row-top">
                                        <span class="etb-icon-badge">
                                            <span class="dashicons <?php echo esc_attr( $e_icon ); ?>"></span>
                                        </span>
                                        <strong><?php echo esc_html( $extra->post_title ); ?></strong>
                                    </div>

                                    <div class="etb-option-row-bottom">
                                        <span class="etb-price-tag"><?php echo esc_html( $price_display ); ?></span>

                                        <?php if ( $e_type === 'per_quantity' ) : ?>
                                            <div class="etb-qty-control mini">
                                                <button type="button" class="etb-qty-btn etb-minus">-</button>
                                                <input type="number" name="etb_extra_<?php echo esc_attr( $extra->ID ); ?>" value="0" min="0" readonly>
                                                <button type="button" class="etb-qty-btn etb-plus">+</button>
                                            </div>
                                        <?php else : ?>
                                            <div class="etb-toggle-check">
                                                <i class="dashicons dashicons-yes"></i>
                                                <input type="hidden" name="etb_extra_<?php echo esc_attr( $extra->ID ); ?>" value="0">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- SECTION : INFORMATIONS CLIENT -->
                    <section class="etb-section">
                        <h2 class="etb-section-title"><span class="dashicons dashicons-admin-users"></span> Your information</h2>
                        <div class="etb-grid-2">
                            <?php if ( ( $form_settings['show_name'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-field">
                                    <label>Full name *</label>
                                    <div class="etb-field-icon-wrapper">
                                        <span class="dashicons dashicons-admin-users"></span>
                                        <input type="text" name="etb_name" placeholder="Ex: John Smith">
                                    </div>
                                    <p class="etb-error-message" id="etb-name-error" style="display:none;">
                                        ⚠ Please enter your full name.
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ( ( $form_settings['show_email'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-field">
                                    <label>Email *</label>
                                    <div class="etb-field-icon-wrapper">
                                        <span class="dashicons dashicons-email"></span>
                                        <input type="email" name="etb_email" placeholder="Ex: john.smith@email.com">
                                    </div>
                                    <p class="etb-error-message" id="etb-email-error" style="display:none;">
                                        ⚠ Please enter a valid email address.
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ( ( $form_settings['show_phone'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-field">
                                    <label>Phone *</label>
                                    <div class="etb-field-icon-wrapper">
                                        <span class="dashicons dashicons-phone"></span>
                                        <input type="tel" name="etb_phone" placeholder="Ex: +33 6 12 34 56 78">
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ( ( $form_settings['show_date'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-field">
                                    <label>Preferred date *</label>
                                    <div class="etb-field-icon-wrapper">
                                        <input type="date" name="etb_date">
                                    </div>
                                    <p class="etb-error-message" id="etb-date-error" style="display:none;">
                                        ⚠ Please select a date.
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ( ( $form_settings['show_time'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-field">
                                    <label>Departure time *</label>
                                    <div class="etb-field-icon-wrapper">
                                        <input type="time" name="etb_time">
                                    </div>
                                    <p class="etb-error-message" id="etb-time-error" style="display:none;">
                                        ⚠ Please select a departure time.
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ( ( $form_settings['show_total_bag'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-field">
                                    <label>Total luggage *</label>
                                    <div class="etb-field-icon-wrapper">
                                        <span class="dashicons dashicons-portfolio"></span>
                                        <input type="number" name="etb_total_luggage" value="0" min="0">
                                    </div>
                                    <p class="etb-error-message" id="etb-baggage-capacity-error" style="display:none;">
                                        ⚠ The number of luggage exceeds the capacity of the selected vehicles.
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ( ( $form_settings['show_promo'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-field etb-field-full">
                                    <label>Promo code</label>
                                    <div class="etb-field-icon-wrapper etb-promo-wrapper">
                                        <span class="dashicons dashicons-tag"></span>
                                        <input type="text" name="etb_promo" id="etb-promo-input" placeholder="Ex: WELCOME10">
                                        <button type="button" id="etb-apply-promo-btn" class="etb-btn-secondary">Apply</button>
                                    </div>
                                    <div id="etb-promo-message" class="etb-field-feedback" style="display:none;"></div>
                                </div>
                            <?php endif; ?>

                            <?php if ( ( $form_settings['show_note'] ?? '1' ) !== '0' ) : ?>
                                <div class="etb-field etb-field-full">
                                    <label>Special request or note</label>
                                    <textarea name="etb_note" rows="3" placeholder="Notes for your trip..."></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- MESSAGE D'ALERTE GLOBAL AVANT RÉCAPITULATIF -->
                    <div class="etb-error-message etb-global-error" id="etb-global-submit-error" style="display:none; margin-bottom: 18px; font-size: 13px; line-height: 1.4;">
                        ⚠ Please complete or fix the required information above before booking.
                    </div>

                    <!-- RÉCAPITULATIF SOMBRE -->
                    <div class="etb-summary-card">
                        <h2 id="etb-summary-title" class="etb-section-title" style="color: white; border: none;">Summary</h2>
                        <div id="etb-summary-text">
                            <p style="opacity: 0.7;">Select your options to see the details.</p>
                        </div>
                        
                        <div class="etb-summary-total" id="etb-total">
                            <span>ESTIMATED TOTAL</span>
                            <div class="etb-total-amount"><span id="etb-total-val">0</span> <?php echo esc_html( $currency ); ?></div>
                        </div>

                        <button type="button" class="etb-submit-button">
                            Book now
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>

     <!-- CONTENEUR HTML DE LA LIGHTBOX (GALERIE PHOTO VIP) -->
    <div id="etb-vip-lightbox" class="etb-lightbox-overlay">
        <div class="etb-lightbox-close" title="Close (Esc)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </div>
        <div class="etb-lightbox-nav etb-lightbox-prev" title="Previous">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </div>
        <div class="etb-lightbox-nav etb-lightbox-next" title="Next">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>
        <img src="" alt="Gallery Zoom" class="etb-lightbox-img">
        <div class="etb-lightbox-counter"><span id="etb-lb-current">1</span> / <span id="etb-lb-total">1</span></div>
    </div>

</div>




