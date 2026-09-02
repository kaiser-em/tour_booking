<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Assets {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_etb_assets' ) );
    }

    public function register_etb_assets() {
         error_log('ETB DEBUG: register_etb_assets() appelé, ETB_URL = ' . (defined('ETB_URL') ? ETB_URL : 'NON DEFINIE'));
        // On enregistre les styles et scripts
        wp_register_style( 'etb-widget-css', ETB_URL . 'public/css/booking-widget.css', array('dashicons'), ETB_VERSION );
        wp_register_script( 'etb-widget-js', ETB_URL . 'public/js/booking-widget.js', array(), ETB_VERSION, true );

         wp_localize_script( 'etb-widget-js', 'etbAjax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'etb_booking_nonce' ),
             ) );
    }
}