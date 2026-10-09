<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_menu' ) );
        add_action( 'admin_init', array( $this, 'register_etb_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_uploader' ) ); // <-- AJOUT DE CETTE LIGNE
    }

    public function enqueue_media_uploader( $hook ) {
        if ( isset( $_GET['page'] ) && 'etb-settings' === $_GET['page'] ) {
            wp_enqueue_media();
        }
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

        // Nom de l'expéditeur officiel (From Name)
        $new_input['sender_name'] = ! empty( $input['sender_name'] ) 
            ? sanitize_text_field( trim( $input['sender_name'] ) ) 
            : get_bloginfo( 'name' );

        // E-mail d'expédition officiel (From Email)
        if ( ! empty( $input['sender_email'] ) && is_email( $input['sender_email'] ) ) {
            $new_input['sender_email'] = sanitize_email( trim( $input['sender_email'] ) );
        } else {
            $new_input['sender_email'] = isset( $existing['sender_email'] ) ? $existing['sender_email'] : $new_input['admin_email'];
        }

        // Numéro WhatsApp de l'entreprise
        $new_input['company_whatsapp'] = ! empty( $input['company_whatsapp'] ) ? sanitize_text_field( trim( $input['company_whatsapp'] ) ) : '';

        // Fournisseur d'adresses modulaire (Google Maps ou Mapbox)
        $new_input['address_provider']    = ! empty( $input['address_provider'] ) ? sanitize_text_field( $input['address_provider'] ) : 'google';
        $new_input['google_maps_api_key'] = ! empty( $input['google_maps_api_key'] ) ? sanitize_text_field( trim( $input['google_maps_api_key'] ) ) : '';
        $new_input['mapbox_token']         = ! empty( $input['mapbox_token'] ) ? sanitize_text_field( trim( $input['mapbox_token'] ) ) : '';
        $new_input['checkout_page_url']    = ! empty( $input['checkout_page_url'] ) ? esc_url_raw( trim( $input['checkout_page_url'] ) ) : '';
        $new_input['payment_page_url']     = ! empty( $input['payment_page_url'] ) ? esc_url_raw( trim( $input['payment_page_url'] ) ) : '';

        // Coordonnées & Mentions Légales de l'Entreprise (Zéro Hardcode)
        $new_input['company_legal_name']    = ! empty( $input['company_legal_name'] ) ? sanitize_text_field( trim( $input['company_legal_name'] ) ) : '"EDEN CAB" Ltd';
        $new_input['company_subtitle']      = ! empty( $input['company_subtitle'] ) ? sanitize_text_field( trim( $input['company_subtitle'] ) ) : 'superior drive';
        $new_input['company_address_line1'] = ! empty( $input['company_address_line1'] ) ? sanitize_text_field( trim( $input['company_address_line1'] ) ) : '250 avenue de Grasse';
        $new_input['company_address_line2'] = ! empty( $input['company_address_line2'] ) ? sanitize_text_field( trim( $input['company_address_line2'] ) ) : 'Cannes 06400, France';
        $new_input['company_logo_url']      = ! empty( $input['company_logo_url'] ) ? esc_url_raw( trim( $input['company_logo_url'] ) ) : '';

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
        <style>
            .etb-admin-wrap { margin: 20px 20px 0 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
            .etb-admin-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
            .etb-admin-header h1 { margin: 0; font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
            .etb-admin-header .etb-badge { background: #fbac18; color: #0f172a; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }

            .etb-dashboard { display: flex; gap: 30px; align-items: flex-start; margin-top: 15px; }
            .etb-sidebar { width: 260px; flex-shrink: 0; position: sticky; top: 40px; }
            .etb-content { flex: 1; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 35px; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03); }

            /* Boutons latéraux (Onglets) */
            .etb-nav-pill { display: flex; align-items: center; padding: 14px 18px; margin-bottom: 8px; background: #ffffff; border-radius: 8px; color: #475569; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.2s ease; border: 1px solid #e2e8f0; cursor: pointer; }
            .etb-nav-pill:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; transform: translateX(2px); }
            .etb-nav-pill.active { background: #0f172a; color: #ffffff; border-color: #0f172a; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15); transform: translateX(4px); }
            .etb-nav-pill .dashicons { margin-right: 12px; font-size: 18px; width: 18px; height: 18px; color: #94a3b8; transition: color 0.2s; }
            .etb-nav-pill:hover .dashicons { color: #64748b; }
            .etb-nav-pill.active .dashicons { color: #fbac18; }

            /* Sections de contenu */
            .etb-settings-section { display: none; }
            .etb-settings-section.active { display: block; animation: etbFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1); }
            @keyframes etbFadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

            .etb-section-title { font-size: 20px; font-weight: 800; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-top: 0; margin-bottom: 25px; letter-spacing: -0.3px; }

            /* Styles WordPress unifiés */
            .etb-content .form-table th { font-weight: 600; color: #334155; font-size: 14px; padding-left: 0; }
            .etb-content .form-table td { padding-left: 0; }
            .etb-content .form-table input[type="text"], 
            .etb-content .form-table input[type="email"], 
            .etb-content .form-table input[type="password"], 
            .etb-content .form-table input[type="url"], 
            .etb-content .form-table select { border-radius: 6px; border: 1.5px solid #cbd5e1; padding: 6px 12px; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02); transition: all 0.2s ease; width: 100%; max-width: 400px; color: #0f172a; font-weight: 500; }
            .etb-content .form-table input:focus, 
            .etb-content .form-table select:focus { border-color: #fbac18; box-shadow: 0 0 0 3px rgba(251, 172, 24, 0.15); outline: none; }
            .etb-content .description { color: #64748b; font-size: 12px; font-style: normal; margin-top: 6px; display: block; line-height: 1.5; max-width: 500px; }
            
            /* Bouton d'enregistrement */
            .etb-save-bar { margin-top: 35px; padding-top: 25px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-start; }
            .etb-save-bar .button-primary { background: #fbac18 !important; border-color: #fbac18 !important; color: #0f172a !important; font-weight: 800; font-size: 14px; padding: 6px 28px; border-radius: 50px; box-shadow: 0 4px 10px rgba(251, 172, 24, 0.2); transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 0.05em; }
            .etb-save-bar .button-primary:hover { background: #e39402 !important; border-color: #e39402 !important; color: #000 !important; transform: translateY(-1px); box-shadow: 0 6px 15px rgba(251, 172, 24, 0.3); }

            @media (max-width: 900px) {
                .etb-dashboard { flex-direction: column; }
                .etb-sidebar { width: 100%; position: static; display: flex; overflow-x: auto; gap: 10px; padding-bottom: 10px; }
                .etb-nav-pill { flex: 0 0 auto; margin-bottom: 0; padding: 10px 16px; white-space: nowrap; }
            }
        </style>

        <div class="etb-admin-wrap">
            <div class="etb-admin-header">
                <h1>Elite Transfer Booking</h1>
                <span class="etb-badge">Premium</span>
            </div>

            <!-- NATIVE WP TABS FOR TOP LEVEL -->
            <h2 class="nav-tab-wrapper" style="margin-bottom: 20px; border-bottom: 1px solid #cbd5e1;">
                <a href="?post_type=tour_booking&page=etb-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>" style="font-weight: 600;">Réglages Généraux</a>
                <a href="?post_type=tour_booking&page=etb-settings&tab=form" class="nav-tab <?php echo $active_tab === 'form' ? 'nav-tab-active' : ''; ?>" style="font-weight: 600;">Configuration Formulaire</a>
            </h2>

            <form method="post" action="options.php">
                <?php
                if ( $active_tab === 'general' ) {
                    settings_fields( 'etb_general_settings_group' );
                    $options = get_option( 'etb_general_settings', array() );
                    ?>
                    <div class="etb-dashboard">
                        <!-- SIDEBAR NAVIGATION (ONGLETS JS) -->
                        <div class="etb-sidebar">
                            <a class="etb-nav-pill active" data-target="sec-general"><span class="dashicons dashicons-admin-generic"></span> Généralités</a>
                            <a class="etb-nav-pill" data-target="sec-company"><span class="dashicons dashicons-store"></span> Identité & Marque</a>
                            <a class="etb-nav-pill" data-target="sec-geo"><span class="dashicons dashicons-location"></span> Géolocalisation & URLs</a>
                            <a class="etb-nav-pill" data-target="sec-stripe"><span class="dashicons dashicons-cart"></span> Paiement Stripe</a>
                            <a class="etb-nav-pill" data-target="sec-dispatch"><span class="dashicons dashicons-randomize"></span> Système de Dispatch</a>
                        </div>

                        <!-- CONTENT PANELS -->
                        <div class="etb-content">
                            <!-- SECTION: GENERAL -->
                            <div id="sec-general" class="etb-settings-section active">
                                <h2 class="etb-section-title">Paramètres Généraux</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Devise d'affichage</th>
                                        <td>
                                            <input type="text" name="etb_general_settings[currency]" value="<?php echo esc_attr( $options['currency'] ?? 'EUR' ); ?>" class="regular-text" style="width: 100px;">
                                            <p class="description">Symbole ou code devise (ex: €, $, GBP).</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Délai minimum avant réservation</th>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <input type="number" name="etb_general_settings[min_delay]" value="<?php echo esc_attr( $options['min_delay'] ?? 24 ); ?>" class="small-text" style="width: 80px; padding: 6px 12px; border-radius: 6px;">
                                                <span style="font-weight: 600; color: #475569;">heures</span>
                                            </div>
                                            <p class="description">En dessous de ce délai, la réservation est considérée comme "Urgente" et requiert une vérification manuelle du dispatch avant encaissement.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Email de notification (Admin)</th>
                                        <td>
                                            <input type="email" name="etb_general_settings[admin_email]" value="<?php echo esc_attr( $options['admin_email'] ?? get_option('admin_email') ); ?>" class="regular-text">
                                            <p class="description">Adresse où vous recevrez les alertes de nouveaux devis et paiements.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Numéro WhatsApp de la régulation</th>
                                        <td>
                                            <input type="text" name="etb_general_settings[company_whatsapp]" value="<?php echo esc_attr( $options['company_whatsapp'] ?? '' ); ?>" class="regular-text" placeholder="+33 6 12 34 56 78">
                                            <p class="description">Format international (ex: <code>+33612345678</code>). Les demandes de devis directes lui seront automatiquement adressées par les clients.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- SECTION: IDENTITE -->
                            <div id="sec-company" class="etb-settings-section">
                                <h2 class="etb-section-title">Identité & Mentions Légales</h2>
                                <p class="description" style="margin-bottom: 25px;">Ces informations habillent officiellement la page de paiement Stripe, les emails envoyés aux clients, et les factures imprimables.</p>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Nom de l'expéditeur (From Name)</th>
                                        <td>
                                            <input type="text" name="etb_general_settings[sender_name]" value="<?php echo esc_attr( $options['sender_name'] ?? get_bloginfo('name') ); ?>" class="regular-text" placeholder="EDEN CAB Reservations">
                                            <p class="description">Le nom qui apparaît comme expéditeur dans la boîte de réception du client.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Email d'expédition (From Email)</th>
                                        <td>
                                            <input type="email" name="etb_general_settings[sender_email]" value="<?php echo esc_attr( $options['sender_email'] ?? 'office@eden-cab.com' ); ?>" class="regular-text" placeholder="office@eden-cab.com">
                                            <p class="description">Adresse e-mail professionnelle liée à votre domaine (conseillé pour éviter le dossier SPAM).</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Logo de l'entreprise</th>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 8px;">
                                                <?php $logo_val = $options['company_logo_url'] ?? ''; ?>
                                                <div id="etb_logo_preview" style="width: 130px; height: 60px; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f8fafc; overflow: hidden;">
                                                    <?php if ( ! empty( $logo_val ) ) : ?>
                                                        <img src="<?php echo esc_url( $logo_val ); ?>" style="max-height: 50px; max-width: 120px; object-fit: contain;">
                                                    <?php else : ?>
                                                        <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">Aucun logo</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="etb_general_settings[company_logo_url]" id="etb_company_logo_url" value="<?php echo esc_url( $logo_val ); ?>">
                                                    <button type="button" class="button" id="etb_upload_logo_btn" style="border-radius: 6px;">📁 Parcourir</button>
                                                    <button type="button" class="button" id="etb_remove_logo_btn" style="<?php echo empty( $logo_val ) ? 'display:none;' : ''; ?> color: #dc2626; border-color: #fca5a5; background: #fef2f2; border-radius: 6px;">✕ Retirer</button>
                                                </div>
                                            </div>
                                            <p class="description">Format recommandé : PNG transparent ou SVG.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nom légal de l'entreprise</th>
                                        <td>
                                            <input type="text" name="etb_general_settings[company_legal_name]" value="<?php echo esc_attr( $options['company_legal_name'] ?? '"EDEN CAB" Ltd' ); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Slogan / Sous-titre</th>
                                        <td>
                                            <input type="text" name="etb_general_settings[company_subtitle]" value="<?php echo esc_attr( $options['company_subtitle'] ?? 'superior drive' ); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Adresse (Ligne 1)</th>
                                        <td>
                                            <input type="text" name="etb_general_settings[company_address_line1]" value="<?php echo esc_attr( $options['company_address_line1'] ?? '250 avenue de Grasse' ); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Ville & Code Postal (Ligne 2)</th>
                                        <td>
                                            <input type="text" name="etb_general_settings[company_address_line2]" value="<?php echo esc_attr( $options['company_address_line2'] ?? 'Cannes 06400, France' ); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- SECTION: GEO & URLS -->
                            <div id="sec-geo" class="etb-settings-section">
                                <h2 class="etb-section-title">Géolocalisation & Pages WordPress</h2>
                                <table class="form-table">
                                    <?php $provider = $options['address_provider'] ?? 'google'; ?>
                                    <tr>
                                        <th scope="row">Moteur d'autocomplétion</th>
                                        <td>
                                            <select name="etb_general_settings[address_provider]" id="etb_address_provider_select" class="regular-text" style="font-weight: 700; color: #0f172a;">
                                                <option value="google" <?php selected( $provider, 'google' ); ?>>🟢 Google Places API (Recommandé)</option>
                                                <option value="mapbox" <?php selected( $provider, 'mapbox' ); ?>>🔵 Mapbox (Search Box)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="etb_row_google_key" style="<?php echo ( 'google' === $provider ) ? '' : 'display: none;'; ?>">
                                        <th scope="row">Clé API Google Maps</th>
                                        <td>
                                            <input type="password" name="etb_general_settings[google_maps_api_key]" value="<?php echo esc_attr( $options['google_maps_api_key'] ?? '' ); ?>" class="regular-text" placeholder="AIzaSy...">
                                            <p class="description">Activez les bibliothèques <strong>Places API</strong> et <strong>Maps JavaScript API</strong> sur Google Cloud Console.</p>
                                        </td>
                                    </tr>
                                    <tr id="etb_row_mapbox_key" style="<?php echo ( 'mapbox' === $provider ) ? '' : 'display: none;'; ?>">
                                        <th scope="row">Jeton public Mapbox</th>
                                        <td>
                                            <input type="text" name="etb_general_settings[mapbox_token]" value="<?php echo esc_attr( $options['mapbox_token'] ?? '' ); ?>" class="regular-text" placeholder="pk.eyJ1...">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Page de Checkout</th>
                                        <td>
                                            <input type="url" name="etb_general_settings[checkout_page_url]" value="<?php echo esc_url( $options['checkout_page_url'] ?? '' ); ?>" class="regular-text">
                                            <p class="description">URL de la page contenant le shortcode <code>[etb_checkout]</code>.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Page de Paiement</th>
                                        <td>
                                            <input type="url" name="etb_general_settings[payment_page_url]" value="<?php echo esc_url( $options['payment_page_url'] ?? home_url( '/payment/' ) ); ?>" class="regular-text">
                                            <p class="description">URL de la page contenant le shortcode <code>[etb_payment]</code>.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- SECTION: STRIPE -->
                            <div id="sec-stripe" class="etb-settings-section">
                                <h2 class="etb-section-title">Passerelle Bancaire Stripe</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Activer Stripe</th>
                                        <td>
                                            <label style="display: flex; align-items: center; gap: 10px;">
                                                <input type="checkbox" name="etb_general_settings[stripe_enabled]" value="1" <?php checked( $options['stripe_enabled'] ?? '0', '1' ); ?> style="width: 18px; height: 18px;">
                                                <span style="font-weight: 600; color: #1e293b;">Activer les paiements en ligne et Apple/Google Pay</span>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Mode d'Environnement</th>
                                        <td>
                                            <select name="etb_general_settings[stripe_mode]" class="regular-text" style="font-weight: 600;">
                                                <option value="test" <?php selected( $options['stripe_mode'] ?? 'test', 'test' ); ?>>🟡 Test / Sandbox (pk_test_...)</option>
                                                <option value="live" <?php selected( $options['stripe_mode'] ?? 'test', 'live' ); ?>>🟢 Live / Production (pk_live_...)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Clé Publique (Publishable Key)</th>
                                        <td>
                                            <input type="text" name="etb_general_settings[stripe_publishable_key]" value="<?php echo esc_attr( $options['stripe_publishable_key'] ?? '' ); ?>" class="regular-text" placeholder="pk_test_... ou pk_live_...">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Clé Secrète (Secret Key)</th>
                                        <td>
                                            <input type="password" name="etb_general_settings[stripe_secret_key]" value="<?php echo esc_attr( $options['stripe_secret_key'] ?? '' ); ?>" class="regular-text" placeholder="sk_test_... ou sk_live_...">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Méthode de Prélèvement</th>
                                        <td>
                                            <select name="etb_general_settings[stripe_capture_method]" class="regular-text">
                                                <option value="manual" <?php selected( $options['stripe_capture_method'] ?? 'manual', 'manual' ); ?>>🔒 Empreinte Bancaire (Pré-autorisation, capture manuelle)</option>
                                                <option value="immediate" <?php selected( $options['stripe_capture_method'] ?? 'manual', 'immediate' ); ?>>⚡ Débit Immédiat</option>
                                            </select>
                                            <p class="description">Le mode Empreinte bloque les fonds sans débiter la carte, vous permettant d'ajuster le tarif à la hausse (péages, attente) en fin de mission.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- SECTION: DISPATCH -->
                            <div id="sec-dispatch" class="etb-settings-section">
                                <h2 class="etb-section-title">Système de Dispatch</h2>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Application Connectée</th>
                                        <td>
                                            <?php $active_dispatcher = $options['active_dispatcher'] ?? 'none'; ?>
                                            <select name="etb_general_settings[active_dispatcher]" class="regular-text" style="font-weight: 700; color: #0f172a; border-color: #cbd5e1;">
                                                <option value="none" <?php selected( $active_dispatcher, 'none' ); ?>>⚪ Aucune (Mode 100% Autonome)</option>
                                                <option value="limoexpress" <?php selected( $active_dispatcher, 'limoexpress' ); ?>>🔴 LimoExpress</option>
                                            </select>
                                            <p class="description">Choisissez le logiciel qui recevra vos réservations en temps réel.</p>
                                        </td>
                                    </tr>
                                    <?php 
                                    // Le module LimoExpress va s'accrocher ici avec ses propres `<tr>` 
                                    // sans casser notre layout car nous sommes dans une `table.form-table` !
                                    do_action( 'etb_render_dispatcher_settings', $options ); 
                                    ?>
                                </table>
                            </div>

                            <!-- Save Button (Présent en bas de n'importe quel onglet) -->
                            <div class="etb-save-bar">
                                <button type="submit" class="button button-primary">Enregistrer la configuration</button>
                            </div>

                        </div> <!-- End Content -->
                    </div> <!-- End Dashboard -->

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Logique des Onglets JS (Sidebar)
                        const pills = document.querySelectorAll('.etb-nav-pill');
                        const sections = document.querySelectorAll('.etb-settings-section');

                        pills.forEach(pill => {
                            pill.addEventListener('click', function(e) {
                                e.preventDefault();
                                pills.forEach(p => p.classList.remove('active'));
                                this.classList.add('active');

                                sections.forEach(sec => sec.classList.remove('active'));
                                const target = document.getElementById(this.dataset.target);
                                if (target) target.classList.add('active');
                            });
                        });

                        // Toggle Maps Provider
                        const selectEl = document.getElementById('etb_address_provider_select');
                        const rowGoogle = document.getElementById('etb_row_google_key');
                        const rowMapbox = document.getElementById('etb_row_mapbox_key');
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

                        // Upload du logo via le Media Manager WordPress
                        const uploadBtn = document.getElementById('etb_upload_logo_btn');
                        const removeBtn = document.getElementById('etb_remove_logo_btn');
                        const inputField = document.getElementById('etb_company_logo_url');
                        const previewDiv = document.getElementById('etb_logo_preview');

                        if (uploadBtn) {
                            uploadBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                var customUploader = wp.media({
                                    title: 'Sélectionner le logo EDEN CAB',
                                    button: { text: 'Utiliser ce logo' },
                                    multiple: false
                                });
                                customUploader.on('select', function() {
                                    var attachment = customUploader.state().get('selection').first().toJSON();
                                    inputField.value = attachment.url;
                                    previewDiv.innerHTML = '<img src="' + attachment.url + '" style="max-height: 44px; max-width: 110px; object-fit: contain;">';
                                    if (removeBtn) removeBtn.style.display = 'inline-block';
                                });
                                customUploader.open();
                            });
                        }

                        if (removeBtn) {
                            removeBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                inputField.value = '';
                                previewDiv.innerHTML = '<span style="font-size: 11px; color: #94a3b8; font-weight: 600;">Aucun logo</span>';
                                removeBtn.style.display = 'none';
                            });
                        }
                    });
                    </script>
                    <?php
                } else { 
                    // Onglet natif "Configuration Formulaire"
                    settings_fields( 'etb_form_settings_group' );
                    $form_opts = get_option( 'etb_form_settings', array() );
                    $fields = $this->get_form_fields_list();
                    ?>
                    <div class="etb-admin-wrap" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 35px; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03); max-width: 800px;">
                        <h2 class="etb-section-title">Personnalisation du Formulaire Client</h2>
                        <p class="description" style="margin-bottom: 25px; font-size: 13px;">Cochez les champs que vous souhaitez afficher dans le widget frontend (Sidebar Circuit).</p>
                        
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <table class="form-table" style="margin: 0;">
                                <?php foreach ( $fields as $id => $label ) : ?>
                                <tr>
                                    <th scope="row" style="padding: 12px 0; color: #0f172a; font-weight: 600; font-size: 14px; width: 250px;">
                                        <?php echo esc_html( $label ); ?>
                                    </th>
                                    <td style="padding: 12px 0;">
                                        <label style="display:flex; align-items:center; gap:10px; cursor: pointer;">
                                            <input type="checkbox" name="etb_form_settings[<?php echo $id; ?>]" value="1" <?php checked( $form_opts[$id] ?? '1', '1' ); ?> style="width: 18px; height: 18px; cursor: pointer;">
                                            <span style="color: #64748b; font-size: 13px;">Activer ce champ</span>
                                        </label>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        
                        <div class="etb-save-bar" style="margin-top: 35px; padding-top: 25px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-start;">
                            <button type="submit" class="button button-primary" style="background: #fbac18 !important; border-color: #fbac18 !important; color: #0f172a !important; font-weight: 800; font-size: 14px; padding: 6px 28px; border-radius: 50px; box-shadow: 0 4px 10px rgba(251, 172, 24, 0.2); transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 0.05em;">Enregistrer la configuration</button>
                        </div>
                    </div>
                <?php }
                submit_button( '', 'hidden', 'submit', false ); // Bouton caché requis par le form WordPress natif si on place le notre ailleurs
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Génère les en-têtes d'e-mail standardisés avec From officiel et Reply-To
     *
     * @param string $reply_email Adresse optionnelle pour Reply-To
     * @param string $reply_name  Nom optionnel pour Reply-To
     * @return array Tableau des en-têtes formatés pour wp_mail()
     */
    public static function get_mail_headers( $reply_email = '', $reply_name = '' ) {
        $options      = get_option( 'etb_general_settings', array() );
        $sender_name  = ! empty( $options['sender_name'] ) ? sanitize_text_field( $options['sender_name'] ) : get_bloginfo( 'name' );
        $sender_email = ! empty( $options['sender_email'] ) && is_email( $options['sender_email'] ) 
            ? sanitize_email( $options['sender_email'] ) 
            : get_option( 'admin_email' );

        // Nettoyage strict des caractères spéciaux pour éviter toute injection SMTP
        $clean_sender_name = preg_replace( '/[^\p{L}\p{N}\s\-\.]/u', '', $sender_name );
        $clean_sender_name = trim( preg_replace( '/\s+/', ' ', $clean_sender_name ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $clean_sender_name . ' <' . $sender_email . '>',
        );

        if ( ! empty( $reply_email ) && is_email( $reply_email ) ) {
            $clean_reply_name = ! empty( $reply_name ) ? preg_replace( '/[^\p{L}\p{N}\s\-\.]/u', '', $reply_name ) : '';
            $clean_reply_name = trim( preg_replace( '/\s+/', ' ', $clean_reply_name ) );
            $headers[]        = 'Reply-To: ' . ( $clean_reply_name ? $clean_reply_name . ' ' : '' ) . '<' . sanitize_email( $reply_email ) . '>';
        }

        return $headers;
    }
}

