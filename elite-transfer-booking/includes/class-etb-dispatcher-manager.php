<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Dispatcher_Manager {

    /**
     * Lit les réglages et exécute l'envoi vers l'application active
     *
     * @param int   $booking_id
     * @param array $data
     * @return array { 'success' => bool, 'message' => string }
     */
    public static function dispatch_booking( $booking_id, $data ) {
        $options = get_option( 'etb_general_settings', array() );
        $active_dispatcher = $options['active_dispatcher'] ?? 'none';

        // Si Mode 100% Autonome, on valide silencieusement sans rien envoyer
        if ( 'none' === $active_dispatcher ) {
            return array( 'success' => true, 'message' => 'Mode autonome actif.' );
        }

        // Si LimoExpress est sélectionné
        if ( 'limoexpress' === $active_dispatcher && class_exists( 'ETB_LimoExpress' ) ) {
            $success = ETB_LimoExpress::send_booking( $booking_id, $data );
            if ( $success ) {
                return array( 'success' => true, 'message' => 'Synchronisé vers LimoExpress.' );
            } else {
                $error_msg = get_post_meta( $booking_id, '_etb_limo_error', true ) ?: 'Erreur LimoExpress inconnue.';
                return array( 'success' => false, 'message' => $error_msg );
            }
        }

        // Si Kymark ou autre (Prêt pour le futur)
        // if ( 'kymark' === $active_dispatcher ) { ... }

        return array( 'success' => false, 'message' => 'Application de dispatch non reconnue.' );
    }
}