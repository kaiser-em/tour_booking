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
        return empty( $_POST[ $field_name ] );
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
}