<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Security {

    const TIME_SALT = 'etb_security_timestamp_salt_v1';

    /**
     * Récupération et normalisation sécurisée de l'adresse IP du client
     */
    public static function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $clean_ip = filter_var( $ip, FILTER_VALIDATE_IP );
        return $clean_ip ? $clean_ip : '127.0.0.1';
    }

    /**
     * Contrôle de fréquence (Rate Limiting) basé sur les Transients WordPress
     *
     * @param string $action Nom de l'action (ex: 'booking', 'promo')
     * @param int $max_attempts Seuil d'autorisations tolérées
     * @param int $window_seconds Fenêtre temporelle en secondes
     * @return bool True si autorisé, False si limite atteinte
     */
    public static function check_rate_limit( $action, $max_attempts, $window_seconds ) {
        $ip = self::get_client_ip();
        $transient_key = 'etb_rl_' . sanitize_key( $action ) . '_' . md5( $ip );

        $attempts = (int) get_transient( $transient_key );

        if ( $attempts >= $max_attempts ) {
            return false; // Limite atteinte
        }

        if ( false === $attempts || $attempts === 0 ) {
            set_transient( $transient_key, 1, $window_seconds );
        } else {
            // Incrémentation en conservant la validité du transient
            set_transient( $transient_key, $attempts + 1, $window_seconds );
        }

        return true;
    }

    /**
     * Incrémente le compteur d'échecs uniquement lors d'un code promo erroné
     */
    public static function record_failed_promo_attempt() {
        $ip = self::get_client_ip();
        $transient_key = 'etb_rl_promo_fail_' . md5( $ip );

        $fails = (int) get_transient( $transient_key );
        set_transient( $transient_key, $fails + 1, 600 ); // Fenêtre de 10 minutes
    }

    /**
     * Vérifie si le quota d'échecs de codes promo a été dépassé
     */
    public static function is_promo_bruteforce_blocked( $max_failures = 15 ) {
        $ip = self::get_client_ip();
        $transient_key = 'etb_rl_promo_fail_' . md5( $ip );

        $fails = (int) get_transient( $transient_key );
        return ( $fails >= $max_failures );
    }

    /**
     * Vérifie si le champ Honeypot est vide (non piégé)
     */
    public static function verify_honeypot( $field_name = 'etb_hp_email' ) {
        // Tolérance : vérifie le champ principal ou le champ secondaire anti-autofill
        if ( ! empty( $_POST['etb_hp_email'] ) ) {
            return false;
        }
        if ( ! empty( $_POST['etb_antibot_check'] ) ) {
            return false;
        }
        return true;
    }
    /**
     * Génère un jeton d'horodatage signé pour valider le temps de remplissage
     */
    public static function generate_timestamp_token() {
        $timestamp = time();
        $token     = wp_hash( $timestamp . '|' . self::TIME_SALT, 'nonce' );

        return array(
            'time'  => $timestamp,
            'token' => $token,
        );
    }

    /**
     * Vérifie la validité du jeton d'horodatage et la vélocité de soumission
     *
     * @param int $submitted_time Timestamp envoyé par le formulaire
     * @param string $submitted_token Signature envoyée
     * @param int $min_seconds Temps minimum humain requis (ex: 3 secondes)
     * @param int $max_seconds Validité maximale du formulaire (ex: 86400 = 24h)
     * @return bool
     */
     public static function verify_timestamp_token( $submitted_time, $submitted_token, $min_seconds = 2, $max_seconds = 86400 ) {
        // 1. Si les champs de sécurité ne sont pas reçus (ex: cache navigateur non rafraîchi), on ne bloque pas
        if ( empty( $submitted_time ) || empty( $submitted_token ) ) {
            return true;
        }

        // 2. Vérification de la signature cryptographique du serveur
        $expected_token = wp_hash( $submitted_time . '|' . self::TIME_SALT, 'nonce' );
        if ( ! hash_equals( $expected_token, $submitted_token ) ) {
            return false; // Signature falsifiée
        }

        $elapsed = time() - (int) $submitted_time;

        // 3. Rejet si soumis en moins de 2 secondes (Bot script ultra-rapide)
        if ( $elapsed < $min_seconds ) {
            return false;
        }

        // 4. Rejet si le formulaire a été ouvert il y a plus de 24 heures (Formulaire périmé)
        if ( $elapsed > $max_seconds ) {
            return false;
        }

        return true;
    }

    /**
     * Calcule la signature cryptographique HMAC pour sceller un montant et une réservation
     *
     * @param int   $booking_id ID de la réservation WordPress
     * @param float $amount     Montant fixé par le régulateur
     * @return string Empreinte SHA256 inviolable
     */
    public static function create_payment_signature( $booking_id, $amount ) {
        $clean_id     = absint( $booking_id );
        $clean_amount = number_format( (float) $amount, 2, '.', '' );
        $salt         = wp_salt( 'nonce' );
        
        return hash_hmac( 'sha256', $clean_id . '|' . $clean_amount, $salt );
    }

    /**
     * Vérifie si la signature fournie dans l'URL correspond au montant et au dossier
     *
     * @param int    $booking_id ID de la réservation WordPress
     * @param float  $amount     Montant reçu dans l'URL
     * @param string $signature  Signature reçue dans l'URL
     * @return bool True si valide, False si lien falsifié
     */
    public static function verify_payment_signature( $booking_id, $amount, $signature ) {
        if ( empty( $booking_id ) || empty( $amount ) || empty( $signature ) ) {
            return false;
        }

        $expected_sig = self::create_payment_signature( $booking_id, $amount );
        return hash_equals( $expected_sig, (string) $signature );
    }

    /**
     * Génère l'URL complète et sécurisée vers la page de paiement
     *
     * @param int   $booking_id ID de la réservation WordPress
     * @param float $amount     Montant fixé par le régulateur (facultatif si devis initial)
     * @return string URL prête à être envoyée par WhatsApp ou e-mail
     */
    public static function get_secure_payment_url( $booking_id, $amount = 0.0 ) {
        $gen_settings = get_option( 'etb_general_settings', array() );
        
        // Recherche de la page contenant le shortcode [etb_payment]
        $payment_page_url = ! empty( $gen_settings['payment_page_url'] ) 
            ? esc_url_raw( $gen_settings['payment_page_url'] ) 
            : home_url( '/payment/' );

        $clean_amount = floatval( $amount );
        
        if ( $clean_amount > 0 ) {
            $sig = self::create_payment_signature( $booking_id, $clean_amount );
            return add_query_arg( array(
                'booking_id' => absint( $booking_id ),
                'amount'     => number_format( $clean_amount, 2, '.', '' ),
                'sig'        => $sig,
            ), $payment_page_url );
        }

        // Si montant non encore arrêté, lien d'attente sécurisé
        $sig = self::create_payment_signature( $booking_id, 0.0 );
        return add_query_arg( array(
            'booking_id' => absint( $booking_id ),
            'sig'        => $sig,
        ), $payment_page_url );
    }

}