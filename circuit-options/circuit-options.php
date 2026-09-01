<?php
/**
 * Plugin Name: Circuit Options for ETB
 * Plugin URI:  https://github.com/kaiser-em/etb
 * Description: Gestion des options de départ, timeline et suppléments de circuits connectés à Elite Transfer Booking.
 * Version:     1.2.0
 * Author:      Reich C
 * Text Domain: circuit-options
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Constantes globales
define( 'CO_VERSION', '1.0.0' );
define( 'CO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// 2. Classe Principale du Plugin
class Circuit_Options_Plugin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Chargement des fichiers internes
     */
    private function load_dependencies() {
        require_once CO_PLUGIN_DIR . 'includes/class-co-cpt.php';
        require_once CO_PLUGIN_DIR . 'includes/class-co-meta-box.php';
        require_once CO_PLUGIN_DIR . 'includes/class-co-hooks.php';
        require_once CO_PLUGIN_DIR . 'includes/class-co-shortcode.php';
    }

    /**
     * Initialisation des hooks WordPress
     */
    private function init_hooks() {
        // Enregistrement des scripts & styles d'administration
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Enregistrement des scripts & styles frontend
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
    }

    /**
     * Assets du Back-Office
     */
    public function enqueue_admin_assets( $hook ) {
        global $post_type;
        if ( 'circuit' !== $post_type ) {
            return;
        }

        wp_enqueue_style( 'co-admin-style', CO_PLUGIN_URL . 'admin/css/co-admin.css', array(), CO_VERSION );
        wp_enqueue_script( 'co-admin-script', CO_PLUGIN_URL . 'admin/js/co-admin.js', array( 'jquery' ), CO_VERSION, true );
    }

    /**
     * Assets du Frontend
     */
    public function enqueue_public_assets() {
        wp_enqueue_style( 'co-public-style', CO_PLUGIN_URL . 'public/css/co-public.css', array(), CO_VERSION );
        wp_enqueue_script( 'co-public-script', CO_PLUGIN_URL . 'public/js/co-public.js', array(), CO_VERSION, true );
    }
}

// Initialisation globale
function circuit_options_init() {
    return Circuit_Options_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'circuit_options_init' );