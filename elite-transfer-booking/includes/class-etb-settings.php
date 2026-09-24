<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_menu' ) );
        add_action( 'admin_init', array( $this, 'register_etb_settings' ) );
    }

    public function add_settings_menu() {
        add_submenu_page(
            'edit.php?post_type=tour_booking',
            'Réglages Elite Transfer',
            'Réglages',
            'manage_options',
            'etb-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_etb_settings() {
        // Séparation explicite des groupes pour éviter le croisement de données entre onglets
        register_setting( 'etb_general_settings_group', 'etb_general_settings', array( $this, 'sanitize_general' ) );
        register_setting( 'etb_form_settings_group', 'etb_form_settings', array( $this, 'sanitize_form' ) );
    }

    public function sanitize_general( $input ) {
        $existing = get_option( 'etb_general_settings', array() );
        if ( ! is_array( $input ) ) {
            return $existing;
        }

        $new_input = array();
        
        // Conservation de la devise saisie (y compris €)
        $new_input['currency'] = ! empty( $input['currency'] ) ? sanitize_text_field( $input['currency'] ) : 'EUR';
        
        // Délai minimum
        $new_input['min_delay'] = isset( $input['min_delay'] ) ? absint( $input['min_delay'] ) : 24;
        
        // Email administrateur
        if ( ! empty( $input['admin_email'] ) && is_email( $input['admin_email'] ) ) {
            $new_input['admin_email'] = sanitize_email( $input['admin_email'] );
        } else {
            $new_input['admin_email'] = isset( $existing['admin_email'] ) ? $existing['admin_email'] : get_option( 'admin_email' );
        }

        // Numéro WhatsApp de l'entreprise
        $new_input['company_whatsapp'] = ! empty( $input['company_whatsapp'] ) ? sanitize_text_field( trim( $input['company_whatsapp'] ) ) : '';

        // Fournisseur d'adresses modulaire (Google Maps ou Mapbox)
        $new_input['address_provider']    = ! empty( $input['address_provider'] ) ? sanitize_text_field( $input['address_provider'] ) : 'google';
        $new_input['google_maps_api_key'] = ! empty( $input['google_maps_api_key'] ) ? sanitize_text_field( trim( $input['google_maps_api_key'] ) ) : '';
        $new_input['mapbox_token']         = ! empty( $input['mapbox_token'] ) ? sanitize_text_field( trim( $input['mapbox_token'] ) ) : '';
        $new_input['checkout_page_url']    = ! empty( $input['checkout_page_url'] ) ? esc_url_raw( trim( $input['checkout_page_url'] ) ) : '';

        // Configuration de la passerelle de paiement Stripe
        $new_input['stripe_enabled']         = isset( $input['stripe_enabled'] ) ? '1' : '0';
        $new_input['stripe_mode']            = ( isset( $input['stripe_mode'] ) && 'live' === $input['stripe_mode'] ) ? 'live' : 'test';
        $new_input['stripe_publishable_key'] = ! empty( $input['stripe_publishable_key'] ) ? sanitize_text_field( trim( $input['stripe_publishable_key'] ) ) : '';
        $new_input['stripe_secret_key']      = ! empty( $input['stripe_secret_key'] ) ? sanitize_text_field( trim( $input['stripe_secret_key'] ) ) : '';
        $new_input['stripe_capture_method']  = ( isset( $input['stripe_capture_method'] ) && 'immediate' === $input['stripe_capture_method'] ) ? 'immediate' : 'manual';

        // Application de dispatch choisie (Autonome, LimoExpress...)
        $new_input['active_dispatcher'] = isset( $input['active_dispatcher'] ) ? sanitize_text_field( $input['active_dispatcher'] ) : 'none';

        // Crochet WordPress (Hook) : on laisse l'application sélectionnée sauvegarder ses propres champs
        // Si LimoExpress est branché, c'est lui qui interceptera ce hook pour sauvegarder son Token.
        $new_input = apply_filters( 'etb_sanitize_dispatcher_settings', $new_input, $input );

        return $new_input;
    }

    public function sanitize_form( $input ) {
        $existing = get_option( 'etb_form_settings', array() );
        if ( ! is_array( $input ) ) {
            return $existing;
        }

        $fields = $this->get_form_fields_list();
        $new_input = array();
        foreach ( $fields as $id => $label ) {
            $new_input[$id] = isset( $input[$id] ) ? '1' : '0';
        }
        return $new_input;
    }

    public function get_form_fields_list() {
        return array(
            'show_vehicle'   => 'Véhicule',
            'show_adults'    => 'Adultes',
            'show_children'  => 'Enfants',
            'show_pickup'    => 'Point de Pickup',
            'show_extras'    => 'Options supplémentaires',
            'show_name'      => 'Nom complet',
            'show_email'     => 'Email',
            'show_phone'     => 'Téléphone', // <-- AJOUT DE CETTE LIGNE
            'show_date'      => 'Date souhaitée',
            'show_time'      => 'Heure de départ',
            'show_total_bag' => 'Bagages total (véhicule)',
            'show_promo'     => 'Code promo',
            'show_note'      => 'Demande spéciale / note',
        );
    }

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap">
            <h1>Réglages Elite Transfer Booking</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=tour_booking&page=etb-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Général</a>
                <a href="?post_type=tour_booking&page=etb-settings&tab=form" class="nav-tab <?php echo $active_tab === 'form' ? 'nav-tab-active' : ''; ?>">Configuration du Formulaire</a>
            </h2>

            <form method="post" action="options.php">
                <?php
                if ( $active_tab === 'general' ) {
                    settings_fields( 'etb_general_settings_group' );
                    $options = get_option( 'etb_general_settings', array() );
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Devise</th>
                            <td><input type="text" name="etb_general_settings[currency]" value="<?php echo esc_attr( $options['currency'] ?? 'EUR' ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Délai minimum avant réservation (heures)</th>
                            <td><input type="number" name="etb_general_settings[min_delay]" value="<?php echo esc_attr( $options['min_delay'] ?? 24 ); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Email de notification admin</th>
                            <td><input type="email" name="etb_general_settings[admin_email]" value="<?php echo esc_attr( $options['admin_email'] ?? get_option('admin_email') ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">Numéro WhatsApp de l'entreprise</th>
                            <td>
                                <input type="text" name="etb_general_settings[company_whatsapp]" value="<?php echo esc_attr( $options['company_whatsapp'] ?? '' ); ?>" class="regular-text" placeholder="+33 6 12 34 56 78">
                                <p class="description">Format international avec indicatif (ex: <code>+33612345678</code> ou <code>+261...</code>). Les demandes de devis directes lui seront automatiquement adressées.</p>
                            </td>
                        </tr>
                        
                        <?php 
                        $provider = $options['address_provider'] ?? 'google'; 
                        ?>
                        <tr>
                            <th scope="row">Fournisseur d'adresses & Géocodage</th>
                            <td>
                                <select name="etb_general_settings[address_provider]" id="etb_address_provider_select" class="regular-text" style="font-weight: 600;">
                                    <option value="google" <?php selected( $provider, 'google' ); ?>>🟢 Google Places API (Recommandé)</option>
                                    <option value="mapbox" <?php selected( $provider, 'mapbox' ); ?>>🔵 Mapbox (Search Box)</option>
                                </select>
                                <p class="description">Choisissez le service qui gère la recherche d'adresses et le calcul des coordonnées GPS.</p>
                            </td>
                        </tr>
                        <tr id="etb_row_google_key" style="<?php echo ( 'google' === $provider ) ? '' : 'display: none;'; ?>">
                            <th scope="row">Clé API Google Maps (Places)</th>
                            <td>
                                <input type="password" name="etb_general_settings[google_maps_api_key]" value="<?php echo esc_attr( $options['google_maps_api_key'] ?? '' ); ?>" class="regular-text" placeholder="AIzaSy...">
                                <p class="description">Activez <strong>Places API</strong> et <strong>Maps JavaScript API</strong> sur Google Cloud Console.</p>
                            </td>
                        </tr>
                        <tr id="etb_row_mapbox_key" style="<?php echo ( 'mapbox' === $provider ) ? '' : 'display: none;'; ?>">
                            <th scope="row">Jeton public Mapbox</th>
                            <td>
                                <input type="text" name="etb_general_settings[mapbox_token]" value="<?php echo esc_attr( $options['mapbox_token'] ?? '' ); ?>" class="regular-text" placeholder="pk.eyJ1...">
                                <p class="description">Jeton public Mapbox (100 000 requêtes gratuites/mois).</p>
                            </td>
                        </tr>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var selectEl = document.getElementById('etb_address_provider_select');
                            var rowGoogle = document.getElementById('etb_row_google_key');
                            var rowMapbox = document.getElementById('etb_row_mapbox_key');
                            if (selectEl && rowGoogle && rowMapbox) {
                                selectEl.addEventListener('change', function() {
                                    if (this.value === 'google') {
                                        rowGoogle.style.display = '';
                                        rowMapbox.style.display = 'none';
                                    } else {
                                        rowGoogle.style.display = 'none';
                                        rowMapbox.style.display = '';
                                    }
                                });
                            }
                        });
                        </script>


                        <tr>
                            <th scope="row">Page de réservation finale (Checkout)</th>
                            <td>
                                <input type="url" name="etb_general_settings[checkout_page_url]" value="<?php echo esc_url( $options['checkout_page_url'] ?? '' ); ?>" class="regular-text" placeholder="https://monsite.com/test-checkout/">
                                <p class="description">URL de la page WordPress contenant le shortcode <code>[etb_checkout]</code> où le client finalise sa commande.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" colspan="2">
                                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #dcdcde;">
                                <h3>💳 Passerelle de Paiement Stripe (Standard Blacklane)</h3>
                                <p class="description">Gère la sécurisation bancaire 3D Secure, les empreintes de cartes et la transmission des logs de paiement vers LimoExpress.</p>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row">Activer Stripe</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="etb_general_settings[stripe_enabled]" value="1" <?php checked( $options['stripe_enabled'] ?? '0', '1' ); ?>>
                                    <span>Activer les paiements et empreintes bancaires par carte</span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Mode Stripe</th>
                            <td>
                                <select name="etb_general_settings[stripe_mode]" class="regular-text">
                                    <option value="test" <?php selected( $options['stripe_mode'] ?? 'test', 'test' ); ?>>🟡 Test / Sandbox (pk_test_...)</option>
                                    <option value="live" <?php selected( $options['stripe_mode'] ?? 'test', 'live' ); ?>>🟢 Live / Production (pk_live_...)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Clé Publique Stripe (Publishable Key)</th>
                            <td>
                                <input type="text" name="etb_general_settings[stripe_publishable_key]" value="<?php echo esc_attr( $options['stripe_publishable_key'] ?? '' ); ?>" class="regular-text" placeholder="pk_test_... ou pk_live_...">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Clé Secrète Stripe (Secret Key)</th>
                            <td>
                                <input type="password" name="etb_general_settings[stripe_secret_key]" value="<?php echo esc_attr( $options['stripe_secret_key'] ?? '' ); ?>" class="regular-text" placeholder="sk_test_... ou sk_live_...">
                                <p class="description">Disponible dans votre Dashboard Stripe (Développeurs &gt; Clés API).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Méthode de Prélèvement</th>
                            <td>
                                <select name="etb_general_settings[stripe_capture_method]" class="regular-text">
                                    <option value="manual" <?php selected( $options['stripe_capture_method'] ?? 'manual', 'manual' ); ?>>🔒 Empreinte Bancaire / Pré-autorisation (Modèle Blacklane - Capture à la fin de course)</option>
                                    <option value="immediate" <?php selected( $options['stripe_capture_method'] ?? 'manual', 'immediate' ); ?>>⚡ Débit Immédiat (Paiement direct à la réservation)</option>
                                </select>
                                <p class="description">Le mode Empreinte bloque les fonds sans débiter la carte, vous permettant d'ajuster d'éventuels suppléments lors de la mission.</p>
                            </td>
                        </tr>

                        
                        <tr>
                            <th scope="row" colspan="2">
                                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #dcdcde;">
                                <h3>🔌 Système de Dispatch (Application externe)</h3>
                            </th>
                        </tr>
                        
                        <tr>
                            <th scope="row">Application sélectionnée</th>
                            <td>
                                <?php $active_dispatcher = $options['active_dispatcher'] ?? 'none'; ?>
                                <select name="etb_general_settings[active_dispatcher]" class="regular-text" style="font-weight: bold; border-color: #0f172a;">
                                    <option value="none" <?php selected( $active_dispatcher, 'none' ); ?>>⚪ Aucune (Mode 100% Autonome)</option>
                                    <option value="limoexpress" <?php selected( $active_dispatcher, 'limoexpress' ); ?>>🔴 LimoExpress</option>
                                </select>
                                <p class="description">Choisissez le logiciel qui recevra vos réservations. En mode Autonome, ETB gère tout en interne sans appel externe.</p>
                            </td>
                        </tr>

                        <?php 
                        // C'est ici que l'application sélectionnée branchera et affichera ses propres réglages !
                        do_action( 'etb_render_dispatcher_settings', $options ); 
                        ?>
                    </table>
                <?php } else { 
                    settings_fields( 'etb_form_settings_group' );
                    $form_opts = get_option( 'etb_form_settings', array() );
                    $fields = $this->get_form_fields_list();
                    ?>
                    <p>Cochez les champs que vous souhaitez afficher dans le widget frontend.</p>
                    <table class="form-table">
                        <?php foreach ( $fields as $id => $label ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $label ); ?></th>
                            <td>
                                <input type="checkbox" name="etb_form_settings[<?php echo $id; ?>]" value="1" <?php checked( $form_opts[$id] ?? '1', '1' ); ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php }
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}