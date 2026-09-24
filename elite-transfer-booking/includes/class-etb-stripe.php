<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Stripe {

    const API_BASE = 'https://api.stripe.com/v1/';

    /**
     * Récupère la clé secrète Stripe configurée
     */
    public static function get_secret_key() {
        $settings = get_option( 'etb_general_settings', array() );
        return ! empty( $settings['stripe_secret_key'] ) ? trim( $settings['stripe_secret_key'] ) : '';
    }

    /**
     * Exécute une requête HTTP sécurisée vers l'API REST Stripe
     */
    private static function request( $endpoint, $method = 'POST', $data = array() ) {
        $secret_key = self::get_secret_key();
        if ( empty( $secret_key ) ) {
            return new WP_Error( 'stripe_no_key', 'Clé secrète Stripe non configurée.' );
        }

        $url  = self::API_BASE . ltrim( $endpoint, '/' );
        $args = array(
            'method'    => $method,
            'headers'   => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'timeout'   => 20,
            'sslverify' => true,
        );

        if ( 'POST' === $method && ! empty( $data ) ) {
            $args['body'] = http_build_query( $data );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            return $body;
        }

        $error_msg = $body['error']['message'] ?? 'Erreur Stripe inconnue.';
        return new WP_Error( 'stripe_error', $error_msg, array( 'status' => $code ) );
    }

    /**
     * Crée un profil client dans Stripe
     */
    public static function create_customer( $name, $email, $phone = '' ) {
        $data = array(
            'name'  => $name,
            'email' => $email,
        );
        if ( ! empty( $phone ) ) {
            $data['phone'] = $phone;
        }

        return self::request( 'customers', 'POST', $data );
    }

    /**
     * Crée une intention de paiement (PaymentIntent) avec capture différée (Modèle Blacklane)
     *
     * @param float  $amount Montant en euros (ex: 197.00)
     * @param string $currency Code devise (ex: 'eur')
     * @param string $customer_id ID client Stripe (ex: 'cus_XXXX')
     * @param array  $metadata Données de traçabilité (Dossier WP, trajet...)
     * @param string $capture_method 'manual' pour pré-autorisation Blacklane, 'automatic' pour débit direct
     */
    public static function create_payment_intent( $amount, $currency = 'eur', $customer_id = '', $metadata = array(), $capture_method = 'manual' ) {
        // Stripe attend les montants en centimes d'euro entiers (197.00 € = 19700 centimes)
        $amount_in_cents = round( floatval( $amount ) * 100 );

        $data = array(
            'amount'               => $amount_in_cents,
            'currency'             => strtolower( $currency ),
            'capture_method'       => $capture_method, // 'manual' bloque les fonds sans débiter
            'payment_method_types' => array( 'card' ),
        );

        if ( ! empty( $customer_id ) ) {
            $data['customer'] = $customer_id;
        }

        if ( ! empty( $metadata ) && is_array( $metadata ) ) {
            $data['metadata'] = $metadata;
        }

        return self::request( 'payment_intents', 'POST', $data );
    }

    /**
     * Capture / Débite définitivement une empreinte pré-autorisée
     */
    public static function capture_payment_intent( $payment_intent_id, $amount = null ) {
        $data = array();
        if ( null !== $amount ) {
            $data['amount_to_capture'] = round( floatval( $amount ) * 100 );
        }

        return self::request( 'payment_intents/' . urlencode( $payment_intent_id ) . '/capture', 'POST', $data );
    }
}