<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CO_Hooks_Bridge {

    public function __construct() {
        // Branchement sur les filtres déclarés dans ETB (3 arguments pour accepter le circuit_id)
        add_filter( 'etb_circuit_duration_hours', array( $this, 'provide_duration_hours' ), 10, 3 );
        add_filter( 'etb_circuit_additional_price', array( $this, 'provide_additional_price' ), 10, 3 );
        add_filter( 'etb_circuit_option_label', array( $this, 'provide_option_label' ), 10, 3 );
    }

    /**
     * Recherche ciblée sur le circuit précis (ou par BDD si non précisé)
     */
    private function find_option_data( $option_id, $circuit_id = 0 ) {
        if ( empty( $option_id ) ) {
            return null;
        }

        // 1. Si le circuit_id est précisé, lecture DIRECTE sur le bon circuit
        if ( ! empty( $circuit_id ) ) {
            $options = get_post_meta( $circuit_id, '_circuit_options_data', true );
            if ( is_array( $options ) && isset( $options[ $option_id ] ) ) {
                $data = $options[ $option_id ];
                $data['circuit_id']    = $circuit_id;
                $data['circuit_title'] = get_the_title( $circuit_id );
                return $data;
            }
        }

        // 2. Recherche de secours si circuit_id n'est pas fourni
        global $wpdb;
        $meta_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
            '_circuit_options_data'
        ) );

        if ( ! empty( $meta_rows ) ) {
            foreach ( $meta_rows as $row ) {
                $options = maybe_unserialize( $row->meta_value );
                if ( is_array( $options ) && isset( $options[ $option_id ] ) ) {
                    $data = $options[ $option_id ];
                    $data['circuit_id']    = $row->post_id;
                    $data['circuit_title'] = get_the_title( $row->post_id );
                    return $data;
                }
            }
        }

        return null;
    }

    /**
     * Fournit la durée exacte en heures à ETB
     */
    public function provide_duration_hours( $default_duration, $option_id, $circuit_id = 0 ) {
        if ( empty( $option_id ) ) {
            return $default_duration;
        }

        $option = $this->find_option_data( $option_id, $circuit_id );
        if ( $option && isset( $option['duration_hours'] ) ) {
            return floatval( $option['duration_hours'] );
        }

        return $default_duration;
    }

    /**
     * Fournit le supplément tarifaire de la ville à ETB
     */
    public function provide_additional_price( $default_price, $option_id, $circuit_id = 0 ) {
        if ( empty( $option_id ) ) {
            return $default_price;
        }

        $option = $this->find_option_data( $option_id, $circuit_id );
        if ( $option && isset( $option['additional_price'] ) ) {
            return floatval( $option['additional_price'] );
        }

        return $default_price;
    }

    /**
     * Fournit le libellé clair pour l'administration et les e-mails : "Nom du Circuit — [Ville] (Xh)"
     */
    public function provide_option_label( $default_label, $option_id, $circuit_id = 0 ) {
        if ( empty( $option_id ) ) {
            return $default_label;
        }

        $option = $this->find_option_data( $option_id, $circuit_id );
        if ( $option ) {
            $circuit_name = ! empty( $option['circuit_title'] ) ? $option['circuit_title'] : 'Circuit';
            $city_name    = ! empty( $option['city_name'] ) ? $option['city_name'] : 'Départ';
            $duration     = ! empty( $option['duration_hours'] ) ? $option['duration_hours'] : 1;

            return sprintf( '%s — Départ %s (%sh)', $circuit_name, $city_name, $duration );
        }

        return $default_label;
    }
}

new CO_Hooks_Bridge();