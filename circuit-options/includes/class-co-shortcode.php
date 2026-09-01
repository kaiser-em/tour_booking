<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CO_Shortcode_Manager {

    public function __construct() {
        add_shortcode( 'circuit_view', array( $this, 'render_circuit_view' ) );
        add_filter( 'the_content', array( $this, 'auto_append_to_circuit_single' ), 20 );
    }

    /**
     * Rend le shortcode [circuit_view] ou [circuit_view id="123"]
     */
    public function render_circuit_view( $atts ) {
        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'circuit_view' );

        $circuit_id = absint( $atts['id'] );

        // 1. Détection de l'ID du Circuit
        if ( ! $circuit_id ) {
            if ( get_post_type() === 'circuit' ) {
                $circuit_id = get_the_ID();
            } else {
                // Recherche du circuit le plus récent (tous statuts : publié, brouillon pour aperçu)
                $circuits = get_posts( array(
                    'post_type'      => 'circuit',
                    'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
                    'numberposts'    => 1,
                    'fields'         => 'ids',
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                ) );
                if ( ! empty( $circuits ) ) {
                    $circuit_id = $circuits[0];
                }
            }
        }

        if ( ! $circuit_id ) {
            return '<p style="padding: 15px; background: #fee2e2; border: 1px solid #f87171; border-radius: 6px; color: #991b1b;">' .
                   '<strong>[circuit_view] :</strong> Aucun circuit trouvé. Veuillez créer un circuit dans <em>Circuits & Tours</em>.' .
                   '</p>';
        }

        $options = get_post_meta( $circuit_id, '_circuit_options_data', true );
        if ( empty( $options ) || ! is_array( $options ) ) {
            return '<p style="padding: 15px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; color: #92400e;">' .
                   '<strong>[circuit_view] :</strong> Aucune option de départ configurée pour le circuit #' . esc_html( $circuit_id ) . '. Ouvrez ce circuit et ajoutez au moins une option de départ.' .
                   '</p>';
        }

        ob_start();
        include CO_PLUGIN_DIR . 'templates/circuit-view.php';
        return ob_get_clean();
    }

    /**
     * Affiche automatiquement le layout si on visite directement un Circuit
     */
    public function auto_append_to_circuit_single( $content ) {
        if ( is_singular( 'circuit' ) && in_the_loop() && is_main_query() ) {
            if ( has_shortcode( $content, 'circuit_view' ) ) {
                return $content;
            }
            $circuit_view = $this->render_circuit_view( array( 'id' => get_the_ID() ) );
            return $content . $circuit_view;
        }
        return $content;
    }
}

new CO_Shortcode_Manager();