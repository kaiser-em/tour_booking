<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Pricing_Engine {

    public function __construct() {}

    /**
     * Calcule le tarif total à partir des données validées du formulaire.
     *
     * @param array $data Données sanitizées du formulaire
     * @return array Détail des coûts et montant total
     */
    public function calculate_total( array $data ) {
        $vehicles_total   = 0;
        $pickup_surcharge = 0;
        $extras_total     = 0;

        // --- NOUVEAUTÉ ETB V2 : Préparation pour le contexte "Circuit Options" ---
        $option_id = ! empty( $data['option_id'] ) ? sanitize_text_field( $data['option_id'] ) : '';
        
        // Hooks WordPress : Le futur plugin Circuit Options viendra modifier ces valeurs.
        // En attendant (mode autonome), la durée par défaut est de 1 heure, et le prix additionnel 0.
        $duration_hours = apply_filters( 'etb_circuit_duration_hours', 1.0, $option_id );
        $circuit_additional_price = apply_filters( 'etb_circuit_additional_price', 0.0, $option_id );

        // 1. Calcul du sous-total des véhicules (Nouveau calcul : Taux horaire × Durée × Quantité)
        if ( ! empty( $data['vehicles'] ) && is_array( $data['vehicles'] ) ) {
            foreach ( $data['vehicles'] as $vehicle_id => $qty ) {
                $qty = (int) $qty;
                if ( $qty > 0 ) {
                    $hourly_rate = (float) get_post_meta( $vehicle_id, '_etb_hourly_rate', true );
                    
                    // Fallback (Migration) : Si le taux horaire n'est pas encore renseigné, on utilise l'ancien prix
                    if ( $hourly_rate <= 0 ) {
                        $hourly_rate = (float) get_post_meta( $vehicle_id, '_etb_base_price', true );
                    }

                    $vehicles_total += ( $hourly_rate * $duration_hours * $qty );
                }
            }
        }
        // 2. Supplément lié au point de départ (pickup)
        if ( ! empty( $data['pickup_id'] ) ) {
            $pickup_surcharge = (float) get_post_meta( $data['pickup_id'], '_etb_surcharge', true );
        }

        // 3. Calcul des extras
        if ( ! empty( $data['extras'] ) && is_array( $data['extras'] ) ) {
            foreach ( $data['extras'] as $extra_id => $qty ) {
                $qty = (int) $qty;
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

        if ( ! empty( $promo_code ) ) {
            // 1. Recherche par titre du CPT tour_promo
            $promo_query = new WP_Query( array(
                'post_type'      => 'tour_promo',
                'post_status'    => 'publish',
                'title'          => $promo_code,
                'posts_per_page' => 1,
            ) );

            // 2. Recherche alternative par méta-clé _etb_promo_code
            if ( ! $promo_query->have_posts() ) {
                $promo_query = new WP_Query( array(
                    'post_type'      => 'tour_promo',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'meta_query'     => array(
                        array(
                            'key'     => '_etb_promo_code',
                            'value'   => $promo_code,
                            'compare' => '=',
                        ),
                    ),
                ) );
            }

            if ( $promo_query->have_posts() ) {
                $promo_query->the_post();
                $promo_id = get_the_ID();

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
                wp_reset_postdata();
            }
        }

        $grand_total = max( 0, $subtotal - $discount_amount );

        return array(
                'vehicles_total'           => $vehicles_total,
                'pickup_surcharge'         => $pickup_surcharge,
                'extras_total'             => $extras_total,
                'circuit_additional_price' => $circuit_additional_price, // Ajout V2
                'duration_hours'           => $duration_hours,           // Ajout V2
                'discount_amount'          => $discount_amount,
                'promo_code'               => $promo_code,
                'grand_total'              => $grand_total,
            );
    }
}