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

        // Réglages LimoExpress
        $new_input['limo_enabled']           = isset( $input['limo_enabled'] ) && $input['limo_enabled'] === '1' ? '1' : '0';
        $new_input['limo_api_token']         = ! empty( $input['limo_api_token'] ) ? sanitize_text_field( trim( $input['limo_api_token'] ) ) : '';
        $new_input['limo_client_id']         = ! empty( $input['limo_client_id'] ) ? sanitize_text_field( trim( $input['limo_client_id'] ) ) : '';
        $new_input['limo_booking_type_id']   = ! empty( $input['limo_booking_type_id'] ) ? sanitize_text_field( trim( $input['limo_booking_type_id'] ) ) : '';
        $new_input['limo_booking_status_id'] = ! empty( $input['limo_booking_status_id'] ) ? sanitize_text_field( trim( $input['limo_booking_status_id'] ) ) : '';

        // Purge automatique des caches à chaque enregistrement des réglages
        delete_transient( 'etb_limo_clients_cache' );
        delete_transient( 'etb_limo_classes_cache' );
        delete_transient( 'etb_limo_types_cache' );
        delete_transient( 'etb_limo_statuses_cache' );

        return $new_input;

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
                            <th scope="row" colspan="2"><hr style="margin: 20px 0; border: 0; border-top: 1px solid #dcdcde;"><h3>🚙 Intégration LimoExpress</h3></th>
                        </tr>
                        <tr>
                            <th scope="row">Activer la synchronisation</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="etb_general_settings[limo_enabled]" value="1" <?php checked( $options['limo_enabled'] ?? '0', '1' ); ?>>
                                    Transmettre automatiquement chaque réservation validée vers LimoExpress
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Jeton API (Bearer Token)</th>
                            <td>
                                <input type="password" name="etb_general_settings[limo_api_token]" value="<?php echo esc_attr( $options['limo_api_token'] ?? '' ); ?>" class="regular-text" placeholder="Collez votre Token ici...">
                                <p class="description">Généré dans LimoExpress (Administration &gt; Organization &gt; Advanced Settings &gt; API Integration).</p>
                            </td>
                        </tr>

                        <?php 
                        $limo_clients = class_exists( 'ETB_LimoExpress' ) ? ETB_LimoExpress::get_clients() : array();
                        $current_client_id = $options['limo_client_id'] ?? ( defined('ETB_LimoExpress::DEFAULT_REGULAR_CLIENT_ID') ? ETB_LimoExpress::DEFAULT_REGULAR_CLIENT_ID : 'e56ea49f-8533-41b9-97c9-17343ee35a4e' );
                        ?>
                        <tr>
                            <th scope="row">Client par défaut LimoExpress</th>
                            <td>
                                <?php if ( ! empty( $limo_clients ) ) : ?>
                                    <select name="etb_general_settings[limo_client_id]" class="regular-text">
                                        <?php foreach ( $limo_clients as $client ) : ?>
                                            <option value="<?php echo esc_attr( $client['id'] ); ?>" <?php selected( $current_client_id, $client['id'] ); ?>>
                                                👤 <?php echo esc_html( $client['name'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">✅ Chargé automatiquement depuis votre compte LimoExpress.</p>
                                <?php else : ?>
                                    <input type="text" name="etb_general_settings[limo_client_id]" value="<?php echo esc_attr( $current_client_id ); ?>" class="regular-text">
                                    <p class="description">Enregistrez votre Jeton API ci-dessus pour charger automatiquement vos clients.</p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        
                        <?php 
                        $limo_types = ( class_exists( 'ETB_LimoExpress' ) && method_exists( 'ETB_LimoExpress', 'get_booking_types' ) ) 
                            ? ETB_LimoExpress::get_booking_types() 
                            : array();
                        $current_type_id = $options['limo_booking_type_id'] ?? '';

                        $limo_statuses = ( class_exists( 'ETB_LimoExpress' ) && method_exists( 'ETB_LimoExpress', 'get_booking_statuses' ) ) 
                            ? ETB_LimoExpress::get_booking_statuses() 
                            : array();
                        $current_status_id = $options['limo_booking_status_id'] ?? '';
                        ?>
                        <tr>
                            <th scope="row">Type de réservation LimoExpress</th>
                            <td>
                                <?php if ( ! empty( $limo_types ) ) : ?>
                                    <select name="etb_general_settings[limo_booking_type_id]" class="regular-text">
                                        <?php foreach ( $limo_types as $type ) : ?>
                                            <option value="<?php echo esc_attr( $type['id'] ); ?>" <?php selected( $current_type_id, $type['id'] ); ?>>
                                                📋 <?php echo esc_html( $type['name'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Type de prestation appliqué dans LimoExpress (ex: Transfert).</p>
                                <?php else : ?>
                                    <input type="text" name="etb_general_settings[limo_booking_type_id]" value="<?php echo esc_attr( $current_type_id ); ?>" class="regular-text">
                                    <p class="description">Enregistrez votre jeton API pour charger automatiquement les types de réservation.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Statut initial LimoExpress</th>
                            <td>
                                <?php if ( ! empty( $limo_statuses ) ) : ?>
                                    <select name="etb_general_settings[limo_booking_status_id]" class="regular-text">
                                        <?php foreach ( $limo_statuses as $st ) : ?>
                                            <option value="<?php echo esc_attr( $st['id'] ); ?>" <?php selected( $current_status_id, $st['id'] ); ?>>
                                                ⏳ <?php echo esc_html( $st['name'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Statut sous lequel la course est créée (ex: Pending / En attente).</p>
                                <?php else : ?>
                                    <input type="text" name="etb_general_settings[limo_booking_status_id]" value="<?php echo esc_attr( $current_status_id ); ?>" class="regular-text">
                                    <p class="description">Enregistrez votre jeton API pour charger automatiquement les statuts.</p>
                                <?php endif; ?>
                            </td>
                        </tr>

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