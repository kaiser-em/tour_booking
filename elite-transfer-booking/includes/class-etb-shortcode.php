<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Shortcode {

    public function __construct() {
        add_shortcode( 'tour_booking', array( $this, 'render_booking_form' ) );
        add_shortcode( 'circuit_view', array( $this, 'render_circuit_view' ) );
        add_filter( 'the_content', array( $this, 'auto_append_to_circuit_single' ), 20 );
    }

    /**
     * Shortcode [circuit_view id="..."]
     */
    public function render_circuit_view( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'circuit_view' );
        $circuit_id = absint( $atts['id'] );

        if ( ! $circuit_id ) {
            if ( get_post_type() === 'circuit' ) {
                $circuit_id = get_the_ID();
            } else {
                $circuits = get_posts( array(
                    'post_type'   => 'circuit',
                    'post_status' => array( 'publish', 'draft', 'private', 'pending' ),
                    'numberposts' => 1,
                    'fields'      => 'ids',
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                ) );
                if ( ! empty( $circuits ) ) {
                    $circuit_id = $circuits[0];
                }
            }
        }

        if ( ! $circuit_id ) {
            return '<p style="padding: 15px; background: #fee2e2; border: 1px solid #f87171; border-radius: 6px; color: #991b1b;"><strong>[circuit_view] :</strong> Aucun circuit trouvé.</p>';
        }

        $options = get_post_meta( $circuit_id, '_circuit_options_data', true );
        if ( empty( $options ) || ! is_array( $options ) ) {
            return '<p style="padding: 15px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; color: #92400e;"><strong>[circuit_view] :</strong> Aucune option de départ configurée pour ce circuit #' . esc_html( $circuit_id ) . '.</p>';
        }

        ob_start();
        include ETB_PATH . 'templates/circuit-view.php';
        return ob_get_clean();
    }

    /**
     * Affichage automatique sur les pages de circuit
     */
    public function auto_append_to_circuit_single( $content ) {
        if ( is_singular( 'circuit' ) && in_the_loop() && is_main_query() ) {
            if ( has_shortcode( $content, 'circuit_view' ) ) {
                return $content;
            }
            return $content . $this->render_circuit_view( array( 'id' => get_the_ID() ) );
        }
        return $content;
    }

    /**
     * Shortcode [tour_booking]
     */
    public function render_booking_form( $atts = array() ) {
        $gen_settings  = get_option( 'etb_general_settings', array() );
        $currency      = $gen_settings['currency'] ?? '€';
        $form_settings = array(
            'show_vehicle'   => '0', // Masqué par défaut car affiché en haut dans le layout circuit
            'show_adults'    => '1',
            'show_children'  => '1',
            'show_pickup'    => '1',
            'show_extras'    => '1',
            'show_name'      => '1',
            'show_email'     => '1',
            'show_date'      => '1',
            'show_time'      => '1',
            'show_total_bag' => '1',
            'show_promo'     => '1',
            'show_note'      => '1',
        );

        $vehicles = get_posts( array( 'post_type' => 'tour_vehicle', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'menu_order', 'order' => 'ASC' ) );
        $extras   = get_posts( array( 'post_type' => 'tour_extra', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'menu_order', 'order' => 'ASC' ) );
        $pickups  = array();

        ob_start();
        include ETB_PATH . 'templates/booking-form.php';
        return ob_get_clean();
    }
}