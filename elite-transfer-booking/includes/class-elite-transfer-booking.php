<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Elite_Transfer_Booking {
    public function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies() {
        require_once ETB_INC . 'class-cpt-manager.php';
        require_once ETB_INC . 'class-meta-manager.php';
        require_once ETB_INC . 'class-settings.php';
        require_once ETB_INC . 'class-assets.php';
        require_once ETB_INC . 'class-pricing-engine.php';
        require_once ETB_INC . 'class-shortcode.php'; // Nouveau
        require_once ETB_INC . 'class-ajax.php';
    }

    public function run() {
        $cpt_manager = new ETB_CPT_Manager();
        add_action( 'init', array( $cpt_manager, 'register_all_cpts' ) );
        
        new ETB_Meta_Manager();
        new ETB_Settings();
        new ETB_Assets();
        new ETB_Shortcode(); // Initialise le shortcode [tour_booking]
        new ETB_Ajax();
    }
}