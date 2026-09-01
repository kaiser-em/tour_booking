<?php
/**
 * Plugin Name: Elite Transfer Booking
 * Description: Système de réservation premium personnalisé pour tours et transferts.
 * Version: 1.2.0
 * Author: Reich C
 * Text Domain: elite-transfer-booking
 * Update URI: false 
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Protection contre les mises à jour non désirées
add_filter('site_transient_update_plugins', function($value) {
    $plugin_file = plugin_basename(__FILE__);
    if (isset($value->response[$plugin_file])) {
        unset($value->response[$plugin_file]);
    }
    return $value;
});

// 2. Définition des constantes
define( 'ETB_VERSION', '1.0.0' );
define( 'ETB_PATH', plugin_dir_path( __FILE__ ) );
define( 'ETB_URL', plugin_dir_url( __FILE__ ) ); // Ligne ajoutée pour corriger l'erreur fatale
define( 'ETB_INC', ETB_PATH . 'includes/' );

// 3. Chargement sécurisé de l'orchestrateur
$orchestrator_file = ETB_INC . 'class-elite-transfer-booking.php';

if ( file_exists( $orchestrator_file ) ) {
    require_once $orchestrator_file;
    
    function run_elite_transfer_booking() {
        if ( class_exists( 'Elite_Transfer_Booking' ) ) {
            $plugin = new Elite_Transfer_Booking();
            $plugin->run();
        }
    }
    run_elite_transfer_booking();
} else {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p>Elite Transfer Booking : Fichier orchestrateur introuvable dans /includes/.</p></div>';
    });
}