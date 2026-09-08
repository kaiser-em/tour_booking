<?php
/**
 * Plugin Name: Elite Transfer Booking (Unified)
 * Plugin URI:  https://github.com/kaiser-em/tour_booking
 * Description: Système unifié de réservation d'excursions, circuits touristiques et transferts privés. avec lien API Octopuspro
 * Version:     1.0.4
 * Author:      Reich C
 * Text Domain: elite-transfer-booking
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ETB_VERSION', '2.0.0' );
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
        
        wp_enqueue_script( 'etb-booking-script', ETB_URL . 'public/js/booking-widget.js', array(), ETB_VERSION, true );
        $gen_settings    = get_option( 'etb_general_settings', array() );
        $currency_symbol = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '$';

        wp_localize_script( 'etb-booking-script', 'etbAjax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'etb_booking_nonce' ),
            'currency' => $currency_symbol,
        ) );
    }

    private function __construct() {
        $this->load_dependencies();
        $this->instantiate_modules();
        $this->init_hooks();
    }

    private function load_dependencies() {
         require_once ETB_PATH . 'includes/class-etb-limoexpress.php'; // <-- AJOUT DE CETTE LIGNE
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