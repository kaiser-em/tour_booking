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
            'show_pickup'    => 'Point de départ',
            'show_extras'    => 'Options supplémentaires',
            'show_name'      => 'Nom complet',
            'show_email'     => 'Email',
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