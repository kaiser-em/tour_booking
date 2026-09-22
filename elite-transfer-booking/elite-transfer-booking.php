<?php
/**
 * Plugin Name: Elite Transfer Booking
 * Plugin URI: 
 * Description: Système de réservation d'excursions, circuits touristiques et transferts privés. avec lien API ou autonome
 * Version:     2.0.3
 * Author:      Reich C
 * Text Domain: elite-transfer-booking
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ETB_VERSION', '2.0.3' );
define( 'ETB_PATH', plugin_dir_path( __FILE__ ) );
define( 'ETB_URL', plugin_dir_url( __FILE__ ) );

class Elite_Transfer_Booking {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    

    public function enqueue_public_assets() {
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'etb-booking-style', ETB_URL . 'public/css/booking-widget.css', array( 'dashicons' ), ETB_VERSION );
        
        $gen_settings    = get_option( 'etb_general_settings', array() );
        $currency_symbol = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';
        $provider        = ! empty( $gen_settings['address_provider'] ) ? sanitize_text_field( $gen_settings['address_provider'] ) : 'google';
        $google_key      = ! empty( $gen_settings['google_maps_api_key'] ) ? sanitize_text_field( trim( $gen_settings['google_maps_api_key'] ) ) : '';
        $mapbox_token    = ! empty( $gen_settings['mapbox_token'] ) ? sanitize_text_field( trim( $gen_settings['mapbox_token'] ) ) : '';

        // Chargement du script officiel Google Maps Places uniquement si Google est sélectionné (Langue : Anglais)
        if ( 'google' === $provider && ! empty( $google_key ) ) {
            wp_enqueue_script( 'google-maps-places', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $google_key ) . '&libraries=places&language=en', array(), null, true );
        }
        
        wp_enqueue_script( 'etb-booking-script', ETB_URL . 'public/js/booking-widget.js', array(), ETB_VERSION, true );

        wp_localize_script( 'etb-booking-script', 'etbAjax', array(
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'etb_booking_nonce' ),
            'currency'         => $currency_symbol,
            'address_provider' => $provider,
            'google_key'       => $google_key,
            'mapbox_token'     => $mapbox_token,
            'checkout_url'     => ! empty( $gen_settings['checkout_page_url'] ) ? esc_url( $gen_settings['checkout_page_url'] ) : '',
            'home_url'              => esc_url( home_url( '/' ) ),
            'admin_email'       => ! empty( $gen_settings['admin_email'] ) && is_email( $gen_settings['admin_email'] ) ? sanitize_email( $gen_settings['admin_email'] ) : get_option( 'admin_email' ),
            'company_whatsapp'  => ! empty( $gen_settings['company_whatsapp'] ) ? preg_replace( '/[^0-9]/', '', $gen_settings['company_whatsapp'] ) : '',
            'limo_form_url'     => ! empty( $gen_settings['limo_form_url'] ) ? esc_url( $gen_settings['limo_form_url'] ) : 'https://app.limoexpress.me/public/reservation-form',
            'limo_param'        => ! empty( $gen_settings['limo_form_param'] ) ? sanitize_text_field( $gen_settings['limo_form_param'] ) : '479812783e34cb527161c28bee8748d95cee8c85dd26fcdb51f1086d31b3108be443d2',
            'limo_oneway_type'  => ! empty( $gen_settings['limo_booking_type_id'] ) ? sanitize_text_field( $gen_settings['limo_booking_type_id'] ) : 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf',
            'limo_hourly_type'  => ! empty( $gen_settings['limo_hourly_type_id'] ) ? sanitize_text_field( $gen_settings['limo_hourly_type_id'] ) : '89bc0301-9af8-4bd0-858a-998e21f0bf13',
        ) );
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->instantiate_modules();
        $this->init_hooks();
    }

    private function load_dependencies() {
         require_once ETB_PATH . 'includes/class-etb-limoexpress.php'; // <-- AJOUT DE CETTE LIGNE
         require_once ETB_PATH . 'includes/class-etb-dispatcher-manager.php'; // <-- AJOUT DE CETTE LIGNE
        require_once ETB_PATH . 'includes/class-etb-security.php';
        require_once ETB_PATH . 'includes/class-etb-cpt-manager.php';
        require_once ETB_PATH . 'includes/class-etb-settings.php';
        
        require_once ETB_PATH . 'includes/class-etb-pricing-engine.php';
        require_once ETB_PATH . 'includes/class-etb-meta-manager.php';
        require_once ETB_PATH . 'includes/class-etb-ajax.php';
        require_once ETB_PATH . 'includes/class-etb-shortcode.php';
    }

    private function init_hooks() {
        add_action( 'init', array( $this, 'register_cpts' ), 0 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function register_cpts() {
        $cpt_manager = new ETB_CPT_Manager();
        $cpt_manager->register_all_cpts();
    }

    public function instantiate_modules() {
        new ETB_Settings();
        new ETB_Meta_Manager();
        new ETB_Pricing_Engine();
        new ETB_Ajax();
        new ETB_Shortcode();
        
        // Branchement du module LimoExpress sur la Prise Universelle ETB
        if ( class_exists( 'ETB_LimoExpress' ) ) {
            ETB_LimoExpress::init_hooks();
        }
    }

    public function enqueue_admin_assets( $hook ) {
        global $post_type;
        if ( 'circuit' === $post_type ) {
            if ( file_exists( ETB_PATH . 'admin/css/etb-admin.css' ) ) {
                wp_enqueue_style( 'etb-admin-style', ETB_URL . 'admin/css/etb-admin.css', array(), ETB_VERSION );
            }
            if ( file_exists( ETB_PATH . 'admin/js/etb-admin.js' ) ) {
                wp_enqueue_script( 'etb-admin-script', ETB_URL . 'admin/js/etb-admin.js', array( 'jquery' ), ETB_VERSION, true );
            }
        }
    }
}

// Activation / Désactivation avec flush des permaliens
function etb_activate_plugin() {
    $cpt_manager = new ETB_CPT_Manager();
    $cpt_manager->register_all_cpts();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'etb_activate_plugin' );

function etb_deactivate_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'etb_deactivate_plugin' );

// Lancement
function etb_init_app() {
    return Elite_Transfer_Booking::get_instance();
}
etb_init_app();