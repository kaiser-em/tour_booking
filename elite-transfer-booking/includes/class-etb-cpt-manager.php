<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_CPT_Manager {

    public function __construct() {
        add_filter( 'manage_tour_booking_posts_columns', array( $this, 'set_booking_columns' ) );
        add_action( 'manage_tour_booking_posts_custom_column', array( $this, 'render_booking_columns' ), 10, 2 );
        add_action( 'admin_menu', array( $this, 'cleanup_admin_submenus' ), 999 );
    }

    public function register_all_cpts() {
        $this->register_circuit_cpt(); // <-- Intégration unifiée Circuit Options
        $this->register_vehicle_cpt();
        $this->register_extra_cpt();
        $this->register_pickup_cpt();
        $this->register_booking_cpt();
        $this->register_promo_cpt();
    }

    private function register_circuit_cpt() {
        $labels = array(
            'name'               => 'Circuits & Tours',
            'singular_name'      => 'Circuit',
            'menu_name'          => 'Circuits & Tours',
            'name_admin_bar'     => 'Circuit',
            'add_new'            => 'Ajouter un Circuit',
            'add_new_item'       => 'Ajouter un nouveau Circuit',
            'new_item'           => 'Nouveau Circuit',
            'edit_item'          => 'Modifier le Circuit',
            'view_item'          => 'Voir le Circuit',
            'all_items'          => 'Circuits & Tours',
            'search_items'       => 'Rechercher un Circuit',
            'not_found'          => 'Aucun circuit trouvé',
            'not_found_in_trash' => 'Aucun circuit trouvé dans la corbeille',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=tour_booking', // Rattaché au menu Tour Booking
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'circuits', 'with_front' => false ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_icon'          => 'dashicons-palmtree',
            'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'circuit', $args );
    }

    private function register_vehicle_cpt() {
        register_post_type( 'tour_vehicle', array(
            'labels'       => array( 'name' => 'Véhicules', 'singular_name' => 'Véhicule' ),
            'public'       => true,
            'menu_icon'    => 'dashicons-car',
            'supports'     => array( 'title', 'thumbnail' ),
            'has_archive'  => false,
            'show_in_menu' => 'edit.php?post_type=tour_booking',
        ));
    }

    private function register_extra_cpt() {
        register_post_type( 'tour_extra', array(
            'labels'       => array( 'name' => 'Options', 'singular_name' => 'Option' ),
            'public'       => false,
            'show_ui'      => true,
            'menu_icon'    => 'dashicons-plus-alt',
            'supports'     => array( 'title' ),
            'show_in_menu' => 'edit.php?post_type=tour_booking',
        ));
    }

    private function register_pickup_cpt() {
        register_post_type( 'tour_pickup', array(
            'labels'       => array( 'name' => 'Points de départ', 'singular_name' => 'Point de départ' ),
            'public'       => false,
            'show_ui'      => true,
            'menu_icon'    => 'dashicons-location',
            'supports'     => array( 'title' ),
            'show_in_menu' => false, // Masqué
        ));
    }

    private function register_booking_cpt() {
        register_post_type( 'tour_booking', array(
            'labels'    => array( 
                'name'          => 'Réservations', 
                'singular_name' => 'Réservation',
                'menu_name'     => 'Tour Booking',
                'all_items'     => 'Toutes les Réservations',
            ),
            'public'    => false,
            'show_ui'   => true,
            'menu_icon' => 'dashicons-car',
            'supports'  => array( 'title' ),
        ));
    }

    private function register_promo_cpt() {
        register_post_type( 'tour_promo', array(
            'labels'       => array( 'name' => 'Codes Promo', 'singular_name' => 'Code Promo' ),
            'public'       => false,
            'show_ui'      => true,
            'menu_icon'    => 'dashicons-tag',
            'supports'     => array( 'title' ),
            'show_in_menu' => 'edit.php?post_type=tour_booking',
        ));
    }

    public function set_booking_columns( $columns ) {
        return array(
            'cb'       => $columns['cb'],
            'title'    => 'Référence',
            'customer' => 'Client',
            'trip'     => 'Trajet & Date',
            'total'    => 'Montant Total',
            'status'   => 'Statut',
            'date'     => 'Reçue le',
        );
    }

    public function render_booking_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'customer':
                $name  = get_post_meta( $post_id, '_etb_customer_name', true );
                $phone = get_post_meta( $post_id, '_etb_customer_phone', true );
                echo '<strong>' . esc_html( $name ?: 'Non renseigné' ) . '</strong>';
                if ( $phone ) {
                    echo '<br><small>📞 ' . esc_html( $phone ) . '</small>';
                }
                break;

            case 'trip':
                $date           = get_post_meta( $post_id, '_etb_booking_date', true );
                $time           = get_post_meta( $post_id, '_etb_booking_time', true );
                $pickup_address = get_post_meta( $post_id, '_etb_pickup_address', true );
                $pickup_id      = get_post_meta( $post_id, '_etb_pickup_id', true );
                $pickup         = $pickup_address ?: ( $pickup_id ? get_the_title( $pickup_id ) : '' );
                
                if ( $date ) {
                    echo '📅 <strong>' . esc_html( $date ) . ( $time ? ' à ' . esc_html( $time ) : '' ) . '</strong><br>';
                }
                if ( $pickup ) {
                    echo '<small>📍 ' . esc_html( $pickup ) . '</small>';
                }
                break;

            case 'total':
                $price = get_post_meta( $post_id, '_etb_total_price', true );
                echo '<strong>' . number_format( (float) $price, 2, ',', ' ' ) . ' €</strong>';
                break;

            case 'status':
                $status = get_post_meta( $post_id, '_etb_status', true ) ?: 'pending';
                $badges = array(
                    'pending'   => array( 'label' => 'En attente', 'bg' => '#e67e22' ),
                    'confirmed' => array( 'label' => 'Confirmée',  'bg' => '#27ae60' ),
                    'completed' => array( 'label' => 'Terminée',   'bg' => '#2980b9' ),
                    'cancelled' => array( 'label' => 'Annulée',    'bg' => '#c0392b' ),
                );
                $badge = isset( $badges[ $status ] ) ? $badges[ $status ] : $badges['pending'];
                echo '<span style="background:' . $badge['bg'] . '; color:#fff; padding:4px 8px; border-radius:3px; font-weight:600; font-size:11px;">' . esc_html( $badge['label'] ) . '</span>';
                break;
        }
    }

    public function cleanup_admin_submenus() {
        remove_submenu_page( 'edit.php?post_type=tour_booking', 'post-new.php?post_type=tour_booking' );
    }
}