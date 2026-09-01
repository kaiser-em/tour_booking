<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CO_CPT_Manager {

    public function __construct() {
        add_action( 'init', array( $this, 'register_circuit_cpt' ) );
    }

    /**
     * Enregistre le Custom Post Type "circuit"
     */
    public function register_circuit_cpt() {
        $labels = array(
            'name'                  => 'Circuits',
            'singular_name'         => 'Circuit',
            'menu_name'             => 'Circuits & Tours',
            'name_admin_bar'        => 'Circuit',
            'add_new'               => 'Ajouter un Circuit',
            'add_new_item'          => 'Ajouter un nouveau Circuit',
            'new_item'              => 'Nouveau Circuit',
            'edit_item'             => 'Modifier le Circuit',
            'view_item'             => 'Voir le Circuit',
            'all_items'             => 'Tous les Circuits',
            'search_items'          => 'Rechercher un Circuit',
            'not_found'             => 'Aucun circuit trouvé',
            'not_found_in_trash'    => 'Aucun circuit trouvé dans la corbeille',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'circuits' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-palmtree',
            'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'circuit', $args );
    }
}

new CO_CPT_Manager();