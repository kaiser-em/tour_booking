<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Pricing_Engine {

    public function __construct() {}

    /**
     * Recherche native et autonome des données de l'option de circuit
     */
    public static function find_circuit_option_data( $option_id, $circuit_id = 0 ) {
        if ( empty( $option_id ) ) {
            return null;
        }

        if ( ! empty( $circuit_id ) ) {
            $options = get_post_meta( $circuit_id, '_circuit_options_data', true );
            if ( is_array( $options ) && isset( $options[ $option_id ] ) ) {
                $data = $options[ $option_id ];
                $data['circuit_id']    = $circuit_id;
                $data['circuit_title'] = get_the_title( $circuit_id );
                return $data;
            }
        }

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
     * Formate le libellé clair de la prestation
     */
    public static function get_circuit_option_label( $option_id, $circuit_id = 0, $default_fallback = '' ) {
        $option = self::find_circuit_option_data( $option_id, $circuit_id );
        if ( $option ) {
            $circuit_name = ! empty( $option['circuit_title'] ) ? $option['circuit_title'] : 'Circuit';
            $city_name    = ! empty( $option['city_name'] ) ? $option['city_name'] : 'Départ';
            $duration     = ! empty( $option['duration_hours'] ) ? $option['duration_hours'] : 1;

            $label = sprintf( '%s — Départ : %s (%sh)', $circuit_name, $city_name, $duration );
            return apply_filters( 'etb_circuit_option_label', $label, $option_id, $circuit_id );
        }

        return $default_fallback ?: apply_filters( 'etb_circuit_option_label', 'Transfert standard', $option_id, $circuit_id );
    }

    /**
     * Calcule le tarif total à partir des données validées du formulaire.
     */
    public function calculate_total( array $data ) {
        $vehicles_total   = 0;
        $pickup_surcharge = 0;
        $extras_total     = 0;

        $option_id  = ! empty( $data['option_id'] ) ? sanitize_text_field( $data['option_id'] ) : '';
        $circuit_id = ! empty( $data['circuit_id'] ) ? absint( $data['circuit_id'] ) : 0;

        $circuit_data      = self::find_circuit_option_data( $option_id, $circuit_id );
        $default_duration  = ( $circuit_data && isset( $circuit_data['duration_hours'] ) ) ? floatval( $circuit_data['duration_hours'] ) : 1.0;
        $default_add_price = ( $circuit_data && isset( $circuit_data['additional_price'] ) ) ? floatval( $circuit_data['additional_price'] ) : 0.0;

        $duration_hours           = apply_filters( 'etb_circuit_duration_hours', $default_duration, $option_id, $circuit_id );
        $circuit_additional_price = apply_filters( 'etb_circuit_additional_price', $default_add_price, $option_id, $circuit_id );

        // 1. Calcul des véhicules (plafonné à 50)
        if ( ! empty( $data['vehicles'] ) && is_array( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $vehicle_id => $qty ) {
                $qty = min( 50, max( 0, (int) $qty ) );
                if ( $qty > 0 ) {
                    $hourly_rate = (float) get_post_meta( $vehicle_id, '_etb_hourly_rate', true );
                    if ( $hourly_rate <= 0 ) {
                        $hourly_rate = (float) get_post_meta( $vehicle_id, '_etb_base_price', true );
                    }
                    $vehicles_total += ( $hourly_rate * $duration_hours * $qty );
                }
            }
        }

        // 2. Supplément pickup
        if ( ! empty( $data['pickup_id'] ) ) {
            $pickup_surcharge = (float) get_post_meta( $data['pickup_id'], '_etb_surcharge', true );
        }

        // 3. Calcul des extras (plafonné à 20)
        if ( ! empty( $data['extras'] ) && is_array( $data['extras'] ) ) {
            foreach ( $data['extras'] as $extra_id => $qty ) {
                $qty = min( 20, max( 0, (int) $qty ) );
                if ( $qty > 0 ) {
                    $extra_price = (float) get_post_meta( $extra_id, '_etb_price', true );
                    $price_type  = get_post_meta( $extra_id, '_etb_price_type', true );

                    if ( 'fixed' === $price_type ) {
                        $extras_total += $extra_price;
                    } else {
                        $extras_total += ( $extra_price * $qty );
                    }
                }
            }
        }

        $subtotal = $vehicles_total + $pickup_surcharge + $extras_total + $circuit_additional_price;
        $discount_amount = 0;
        $promo_code = ! empty( $data['promo'] ) ? strtoupper( trim( sanitize_text_field( $data['promo'] ) ) ) : '';

        // 4. Application du code promo avec contrôle d'activation
        if ( ! empty( $promo_code ) ) {
            $promo_query = new WP_Query( array(
                'post_type'      => 'tour_promo',
                'post_status'    => 'publish',
                'title'          => $promo_code,
                'posts_per_page' => 1,
            ) );

            if ( ! $promo_query->have_posts() ) {
                $promo_query = new WP_Query( array(
                    'post_type'      => 'tour_promo',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'meta_query'     => array(
                        array( 'key' => '_etb_promo_code', 'value' => $promo_code, 'compare' => '=' ),
                    ),
                ) );
            }

            if ( $promo_query->have_posts() ) {
                $promo_query->the_post();
                $promo_id = get_the_ID();

                // Contrôle strict de l'état actif du code promo en BDD
                $is_active = get_post_meta( $promo_id, '_etb_promo_active', true );
                if ( '0' !== $is_active ) {
                    $discount_type = get_post_meta( $promo_id, '_etb_discount_type', true );
                    if ( empty( $discount_type ) ) {
                        $discount_type = get_post_meta( $promo_id, '_etb_promo_type', true ) ?: 'fixed';
                    }

                    $raw_value = get_post_meta( $promo_id, '_etb_discount_value', true );
                    if ( '' === $raw_value || false === $raw_value ) {
                        $raw_value = get_post_meta( $promo_id, '_etb_promo_value', true );
                    }
                    if ( '' === $raw_value || false === $raw_value ) {
                        $raw_value = get_post_meta( $promo_id, '_etb_value', true );
                    }
                    $discount_val = floatval( $raw_value );

                    if ( in_array( strtolower( $discount_type ), array( 'percentage', 'percent', 'pourcentage', '%' ), true ) ) {
                        $discount_amount = $subtotal * ( $discount_val / 100 );
                    } else {
                        $discount_amount = $discount_val;
                    }
                }
                wp_reset_postdata();
            }
        }

        $grand_total = max( 0, $subtotal - $discount_amount );

        return array(
            'vehicles_total'           => $vehicles_total,
            'pickup_surcharge'         => $pickup_surcharge,
            'extras_total'             => $extras_total,
            'circuit_additional_price' => $circuit_additional_price,
            'duration_hours'           => $duration_hours,
            'discount_amount'          => $discount_amount,
            'promo_code'               => $promo_code,
            'grand_total'              => $grand_total,
        );
    }
}