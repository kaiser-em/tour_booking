<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Shortcode {

    public function __construct() {
        add_shortcode( 'tour_booking', array( $this, 'render_booking_form' ) );
    }

    /**
     * Rendu du formulaire de réservation
     */
    public function render_booking_form() {
        // Enqueue des assets (CSS enregistré en Phase 5A)
        wp_enqueue_style( 'etb-widget-css' );
        wp_enqueue_script( 'etb-widget-js' );

        


        // 1. Récupération des réglages généraux (Devise)
        $gen_settings = get_option( 'etb_general_settings', array() );
        $currency     = $gen_settings['currency'] ?? '€';

        // 2. Récupération des réglages du formulaire (Lecture directe de l'option)
        $saved_form_settings = get_option( 'etb_form_settings', array() );

        // Définition des valeurs par défaut locales (tous les champs à '1' si non définis)
        $form_defaults = array(
            'show_vehicle'   => '1', 'show_adults'    => '1', 'show_children'  => '1',
            'show_pickup'    => '1', 'show_extras'    => '1', 'show_name'      => '1',
            'show_email'     => '1', 'show_date'      => '1', 'show_time'      => '1',
            'show_total_bag' => '1', 'show_promo'     => '1', 'show_note'      => '1'
        );

        // Fusion pour éviter les "Undefined array key" sans toucher à class-settings.php
        $form_settings = wp_parse_args( $saved_form_settings, $form_defaults );

        // 3. Récupération des données dynamiques (CPT)
        $vehicles = get_posts( array( 'post_type' => 'tour_vehicle', 'numberposts' => -1, 'post_status' => 'publish' ) );
        $pickups  = get_posts( array( 'post_type' => 'tour_pickup', 'numberposts' => -1, 'post_status' => 'publish' ) );
        $extras   = get_posts( array( 'post_type' => 'tour_extra', 'numberposts' => -1, 'post_status' => 'publish' ) );

        // 4. Chargement du template
        ob_start();
        $template_path = ETB_PATH . 'templates/booking-form.php';
        
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo "<!-- ETB Error: Template booking-form.php not found -->";
        }
        
        return ob_get_clean();
    }
}

