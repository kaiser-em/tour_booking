<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables disponibles : $circuit_id, $options
?>

<div class="co-circuit-wrapper" id="co-circuit-app" data-circuit-id="<?php echo esc_attr( $circuit_id ); ?>">

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
        <h2 class="co-section-title"><span class="dashicons dashicons-car"></span> Choisissez votre véhicule</h2>
        
        <div class="co-vehicles-grid" id="co-vehicles-grid">
            <?php foreach ( $vehicles as $vehicle ) : 
                $base_price    = get_post_meta( $vehicle->ID, '_etb_base_price', true );
                $hourly_rate   = get_post_meta( $vehicle->ID, '_etb_hourly_rate', true );
                $display_price = ( $hourly_rate > 0 ) ? $hourly_rate : $base_price;
                $max_pax       = get_post_meta( $vehicle->ID, '_etb_max_pax', true ) ?: 1;
                $max_bag       = get_post_meta( $vehicle->ID, '_etb_max_baggage', true ) ?: 0;
                $img_url       = get_the_post_thumbnail_url( $vehicle->ID, 'medium' ) ?: ( defined('ETB_URL') ? ETB_URL . 'public/images/default-car.png' : '' );
            ?>
                <div class="etb-vehicle-card" data-id="<?php echo $vehicle->ID; ?>" data-max-pax="<?php echo esc_attr( $max_pax ); ?>" data-max-baggage="<?php echo esc_attr( $max_bag ); ?>">
                    <span class="etb-selection-check"><i class="dashicons dashicons-yes"></i></span>
                    <?php if ( $img_url ) : ?>
                        <img src="<?php echo esc_url( $img_url ); ?>" class="etb-vehicle-image" alt="<?php echo esc_attr( $vehicle->post_title ); ?>">
                    <?php endif; ?>
                    <h3 class="etb-vehicle-name"><?php echo esc_html( $vehicle->post_title ); ?></h3>
                    <p class="etb-vehicle-price"><?php echo esc_html( $display_price ); ?> € /h</p>
                    <div class="etb-vehicle-specs">
                        <span>👤 <?php echo esc_html( $max_pax ); ?> Pers. max</span>
                        <span>🧳 <?php echo esc_html( $max_bag ); ?> Bagages</span>
                    </div>
                    <div class="etb-vehicle-qty">
                        <span>Quantité</span>
                        <div class="etb-qty-control mini">
                            <button type="button" class="etb-qty-btn etb-minus" aria-label="Diminuer">-</button>
                            <input type="number" name="etb_car_qty[<?php echo $vehicle->ID; ?>]" value="0" min="0" readonly>
                            <button type="button" class="etb-qty-btn etb-plus" aria-label="Augmenter">+</button>
                        </div>
                    </div>
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
                <span class="co-nav-label">Choisissez votre ville de départ :</span>
                <div class="co-nav-buttons">
                    <?php 
                    $is_first = true;
                    foreach ( $options as $opt_id => $opt_data ) : 
                        $city = ! empty( $opt_data['city_name'] ) ? esc_html( $opt_data['city_name'] ) : 'Départ';
                    ?>
                        <button type="button" 
                                class="co-pub-tab-btn <?php echo $is_first ? 'active' : ''; ?>" 
                                data-target="<?php echo esc_attr( $opt_id ); ?>"
                                data-duration="<?php echo esc_attr( $opt_data['duration_hours'] ?? 1 ); ?>"
                                data-price="<?php echo esc_attr( $opt_data['additional_price'] ?? 0 ); ?>"
                        data-time="<?php echo esc_attr( $opt_data['departure_time'] ?? '09:00' ); ?>">
                            <span class="dashicons dashicons-location"></span>
                            <?php echo $city; ?>
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
                $timeline   = $opt_data['timeline'] ?? array();
                $inclusions = ! empty( $opt_data['inclusions'] ) ? explode( "\n", trim( $opt_data['inclusions'] ) ) : array();
                $exclusions = ! empty( $opt_data['exclusions'] ) ? explode( "\n", trim( $opt_data['exclusions'] ) ) : array();
            ?>
                <div class="co-option-pane <?php echo $is_first ? 'active' : ''; ?>" id="co-pane-<?php echo esc_attr( $opt_id ); ?>">
                    
                    <!-- LES 4 BADGES (HIGHLIGHTS) -->
                    <div class="co-highlights-grid">
                        <?php if ( ! empty( $opt_data['badge_1'] ) ) : ?>
                            <div class="co-highlight-card">
                                <span class="dashicons dashicons-clock"></span>
                                <span><?php echo esc_html( $opt_data['badge_1'] ); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $opt_data['badge_2'] ) ) : ?>
                            <div class="co-highlight-card">
                                <span class="dashicons dashicons-location"></span>
                                <span><?php echo esc_html( $opt_data['badge_2'] ); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $opt_data['badge_3'] ) ) : ?>
                            <div class="co-highlight-card">
                                <span class="dashicons dashicons-groups"></span>
                                <span><?php echo esc_html( $opt_data['badge_3'] ); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $opt_data['badge_4'] ) ) : ?>
                            <div class="co-highlight-card">
                                <span class="dashicons dashicons-translation"></span>
                                <span><?php echo esc_html( $opt_data['badge_4'] ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- PROGRAMME / TIMELINE -->
                    <?php if ( ! empty( $timeline ) ) : ?>
                        <div class="co-timeline-block">
                            <h3 class="co-block-title">Programme</h3>
                            <div class="co-timeline-track">
                                <?php foreach ( $timeline as $step ) : ?>
                                    <div class="co-timeline-step">
                                        <div class="co-timeline-bullet"></div>
                                        <div class="co-timeline-time"><?php echo esc_html( $step['time'] ?? '' ); ?></div>
                                        <div class="co-timeline-content">
                                            <h4><?php echo esc_html( $step['title'] ?? '' ); ?></h4>
                                            <?php if ( ! empty( $step['desc'] ) ) : ?>
                                                <p><?php echo nl2br( esc_html( $step['desc'] ) ); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- INCLUS / NON INCLUS -->
                    <?php if ( ! empty( $inclusions ) || ! empty( $exclusions ) ) : ?>
                        <div class="co-inclusions-grid">
                            <?php if ( ! empty( $inclusions ) ) : ?>
                                <div class="co-inc-card co-included">
                                    <h4>Inclus</h4>
                                    <ul>
                                        <?php foreach ( $inclusions as $item ) : 
                                            if ( empty( trim( $item ) ) ) continue;
                                        ?>
                                            <li><span class="co-icon-check">✓</span> <?php echo esc_html( trim( $item ) ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if ( ! empty( $exclusions ) ) : ?>
                                <div class="co-inc-card co-excluded">
                                    <h4>Non inclus</h4>
                                    <ul>
                                        <?php foreach ( $exclusions as $item ) : 
                                            if ( empty( trim( $item ) ) ) continue;
                                        ?>
                                            <li><span class="co-icon-cross">✕</span> <?php echo esc_html( trim( $item ) ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php 
                $is_first = false;
            endforeach; 
            ?>
        </div>

        <!-- COLONNE DROITE : WIDGET DE RÉSERVATION ETB (UNIQUE & FIXE) -->
        <div class="co-right-sidebar">
            <div class="co-sticky-box">
                <?php echo do_shortcode( '[tour_booking]' ); ?>
            </div>
        </div>

    </div>

</div>