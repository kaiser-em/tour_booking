<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Meta_Manager {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_etb_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_etb_meta_data' ) );
        add_action( 'save_post_circuit', array( $this, 'save_circuit_options_data' ) );
        add_filter( 'wp_insert_post_data', array( $this, 'normalize_promo_code_title' ), 10, 2 );
    }

    public function add_etb_meta_boxes() {
        // Metaboxes Circuit Options
        add_meta_box( 'co_circuit_options_meta', '📍 Options de Départ & Programme du Circuit', array( $this, 'render_circuit_options_box' ), 'circuit', 'normal', 'high' );
        
        // Metaboxes ETB
        add_meta_box( 'etb_vehicle_details', 'Détails du Véhicule', array( $this, 'render_vehicle_box' ), 'tour_vehicle', 'normal', 'high' );
        add_meta_box( 'etb_extra_details', 'Configuration de l\'option', array( $this, 'render_extra_box' ), 'tour_extra', 'normal', 'high' );
        add_meta_box( 'etb_pickup_details', 'Configuration du point de départ', array( $this, 'render_pickup_box' ), 'tour_pickup', 'normal', 'high' );
        add_meta_box( 'etb_promo_details', 'Configuration du Code Promo', array( $this, 'render_promo_box' ), 'tour_promo', 'normal', 'high' );
        add_meta_box( 'etb_booking_details', 'Détails de la Réservation & Statut', array( $this, 'render_booking_box' ), 'tour_booking', 'normal', 'high' );
    }

    /* ------------------------------------------------------------------------
       METABOX CIRCUIT OPTIONS (_circuit_options_data)
    ------------------------------------------------------------------------ */
    public function render_circuit_options_box( $post ) {
        wp_nonce_field( 'co_save_options', 'co_nonce' );

        $options = get_post_meta( $post->ID, '_circuit_options_data', true );
        if ( empty( $options ) || ! is_array( $options ) ) {
            $default_id = 'opt_' . substr( md5( uniqid( rand(), true ) ), 0, 8 );
            $options = array(
                $default_id => array(
                    'city_name'        => 'Départ Principal',
                    'duration_hours'   => 4.0,
                    'departure_time'   => '09:00',
                    'additional_price' => 0.0,
                    'badge_1'          => '⏱ 4h d\'excursion',
                    'badge_2'          => '📍 Prise en charge hôtel',
                    'badge_3'          => '👥 Visite privée',
                    'badge_4'          => '🗣 Guide en français',
                    'timeline'         => array(
                        array( 'time' => '09:00', 'title' => 'Prise en charge', 'desc' => 'Départ de votre hôtel.' ),
                    ),
                    'inclusions'       => "Véhicule privé et chauffeur\nGuide\nEau à bord",
                    'exclusions'       => "Repas\nBoissons supplémentaires",
                ),
            );
        }
        ?>
        <div class="co-admin-wrapper">
            <div class="co-tabs-nav">
                <?php 
                $first = true;
                foreach ( $options as $opt_id => $opt_data ) : 
                    $city = ! empty( $opt_data['city_name'] ) ? esc_html( $opt_data['city_name'] ) : 'Option sans nom';
                ?>
                    <button type="button" class="co-tab-btn <?php echo $first ? 'active' : ''; ?>" data-tab="<?php echo esc_attr( $opt_id ); ?>">
                        <span class="dashicons dashicons-location"></span>
                        <span class="co-tab-title"><?php echo $city; ?></span>
                    </button>
                <?php 
                    $first = false;
                endforeach; 
                ?>
                <button type="button" class="co-add-tab-btn">+ Ajouter une option de départ</button>
            </div>

            <div class="co-tab-panes">
                <?php 
                $first = true;
                foreach ( $options as $opt_id => $opt_data ) : 
                    $this->render_pane_html( $opt_id, $opt_data, $first );
                    $first = false;
                endforeach; 
                ?>
            </div>
        </div>

        <template id="co-pane-template">
            <?php 
            $this->render_pane_html( '{{INDEX}}', array(
                'city_name'        => '{{DEFAULT_TITLE}}',
                'duration_hours'   => 4.0,
                'departure_time'   => '09:00',
                'additional_price' => 0.0,
                'badge_1'          => '⏱ 4h d\'excursion',
                'badge_2'          => '📍 Prise en charge hôtel',
                'badge_3'          => '👥 Visite privée',
                'badge_4'          => '🗣 Guide en français',
                'timeline'         => array(),
                'inclusions'       => "Véhicule privé et chauffeur\nGuide\nEau à bord",
                'exclusions'       => "Repas",
            ), false ); 
            ?>
        </template>
        <?php
    }

    private function render_pane_html( $opt_id, $data, $is_active = false ) {
        $city             = $data['city_name'] ?? '';
        $duration         = $data['duration_hours'] ?? 1.0;
        $departure_time   = $data['departure_time'] ?? '09:00';
        $additional_price = $data['additional_price'] ?? 0.0;
        $badge_1          = $data['badge_1'] ?? '';
        $badge_2          = $data['badge_2'] ?? '';
        $badge_3          = $data['badge_3'] ?? '';
        $badge_4          = $data['badge_4'] ?? '';
        $timeline         = $data['timeline'] ?? array();
        $inclusions       = $data['inclusions'] ?? '';
        $exclusions       = $data['exclusions'] ?? '';
        ?>
        <div class="co-pane <?php echo $is_active ? 'active' : ''; ?>" id="<?php echo esc_attr( $opt_id ); ?>">
            <div class="co-pane-header-actions">
                <span style="font-weight: bold; color: #64748b;">ID Option : <code><?php echo esc_html( $opt_id ); ?></code></span>
                <button type="button" class="co-btn-danger co-delete-option-btn">🗑 Supprimer cette option de départ</button>
            </div>

            <div class="co-section-card">
                <h4><span class="dashicons dashicons-admin-settings"></span> 1. Paramètres & Liaison</h4>
                <div class="co-grid-4">
                    <div class="co-field">
                        <label>Ville / Titre de départ * :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][city_name]" value="<?php echo esc_attr( $city ); ?>" class="widefat co-city-input" placeholder="Ex: Cannes, Antsirabe...">
                    </div>
                    <div class="co-field">
                        <label>Durée (en heures) * :</label>
                        <input type="number" step="0.25" min="0.25" name="co_options[<?php echo esc_attr( $opt_id ); ?>][duration_hours]" value="<?php echo esc_attr( $duration ); ?>" class="widefat">
                    </div>
                    <div class="co-field">
                        <label>Heure départ par défaut :</label>
                        <input type="time" name="co_options[<?php echo esc_attr( $opt_id ); ?>][departure_time]" value="<?php echo esc_attr( $departure_time ); ?>" class="widefat">
                    </div>
                    <div class="co-field">
                        <label>Prix additionnel (€) :</label>
                        <input type="number" step="0.01" min="0" name="co_options[<?php echo esc_attr( $opt_id ); ?>][additional_price]" value="<?php echo esc_attr( $additional_price ); ?>" class="widefat" placeholder="0">
                    </div>
                </div>
            </div>

            <div class="co-section-card">
                <h4><span class="dashicons dashicons-tag"></span> 2. Les 4 Badges récapitulatifs</h4>
                <div class="co-grid-4">
                    <div class="co-field">
                        <label>Badge 1 :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][badge_1]" value="<?php echo esc_attr( $badge_1 ); ?>" class="widefat">
                    </div>
                    <div class="co-field">
                        <label>Badge 2 :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][badge_2]" value="<?php echo esc_attr( $badge_2 ); ?>" class="widefat">
                    </div>
                    <div class="co-field">
                        <label>Badge 3 :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][badge_3]" value="<?php echo esc_attr( $badge_3 ); ?>" class="widefat">
                    </div>
                    <div class="co-field">
                        <label>Badge 4 :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][badge_4]" value="<?php echo esc_attr( $badge_4 ); ?>" class="widefat">
                    </div>
                </div>
            </div>

            <div class="co-section-card">
                <h4><span class="dashicons dashicons-calendar-alt"></span> 3. Programme & Timeline des étapes</h4>
                <table class="co-timeline-table">
                    <thead>
                        <tr>
                            <th>Horaire</th>
                            <th>Titre de l'étape</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $timeline ) ) : foreach ( $timeline as $step ) : ?>
                            <tr>
                                <td style="width: 100px;">
                                    <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][timeline_time][]" value="<?php echo esc_attr( $step['time'] ?? '' ); ?>" placeholder="09:00" class="widefat">
                                </td>
                                <td style="width: 200px;">
                                    <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][timeline_title][]" value="<?php echo esc_attr( $step['title'] ?? '' ); ?>" placeholder="Titre étape" class="widefat">
                                </td>
                                <td>
                                    <textarea name="co_options[<?php echo esc_attr( $opt_id ); ?>][timeline_desc][]" rows="2" placeholder="Description..." class="widefat"><?php echo esc_textarea( $step['desc'] ?? '' ); ?></textarea>
                                </td>
                                <td style="width: 50px; text-align: center;">
                                    <button type="button" class="co-btn-danger co-remove-step-btn">&times;</button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <button type="button" class="co-btn-add-step" data-pane="<?php echo esc_attr( $opt_id ); ?>">+ Ajouter une étape au programme</button>
            </div>

            <div class="co-section-card">
                <h4><span class="dashicons dashicons-yes-alt"></span> 4. Ce qui est inclus / Non inclus</h4>
                <div class="co-grid-2">
                    <div class="co-field">
                        <label>✅ Inclus (1 élément par ligne) :</label>
                        <textarea name="co_options[<?php echo esc_attr( $opt_id ); ?>][inclusions]" rows="4" class="widefat"><?php echo esc_textarea( $inclusions ); ?></textarea>
                    </div>
                    <div class="co-field">
                        <label>❌ Non Inclus (1 élément par ligne) :</label>
                        <textarea name="co_options[<?php echo esc_attr( $opt_id ); ?>][exclusions]" rows="4" class="widefat"><?php echo esc_textarea( $exclusions ); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_circuit_options_data( $post_id ) {
        if ( ! isset( $_POST['co_nonce'] ) || ! wp_verify_nonce( $_POST['co_nonce'], 'co_save_options' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( ! isset( $_POST['co_options'] ) || ! is_array( $_POST['co_options'] ) ) {
            delete_post_meta( $post_id, '_circuit_options_data' );
            return;
        }

        $clean_options = array();
        foreach ( $_POST['co_options'] as $opt_id => $raw_data ) {
            $clean_id = sanitize_key( $opt_id );
            if ( empty( $clean_id ) ) continue;

            $timeline = array();
            if ( ! empty( $raw_data['timeline_time'] ) && is_array( $raw_data['timeline_time'] ) ) {
                $count = count( $raw_data['timeline_time'] );
                for ( $i = 0; $i < $count; $i++ ) {
                    $t_time  = sanitize_text_field( $raw_data['timeline_time'][ $i ] ?? '' );
                    $t_title = sanitize_text_field( $raw_data['timeline_title'][ $i ] ?? '' );
                    $t_desc  = sanitize_textarea_field( $raw_data['timeline_desc'][ $i ] ?? '' );

                    if ( ! empty( $t_title ) || ! empty( $t_time ) ) {
                        $timeline[] = array( 'time' => $t_time, 'title' => $t_title, 'desc' => $t_desc );
                    }
                }
            }

            $clean_options[ $clean_id ] = array(
                'city_name'        => sanitize_text_field( $raw_data['city_name'] ?? '' ),
                'duration_hours'   => max( 0.25, floatval( $raw_data['duration_hours'] ?? 1.0 ) ),
                'departure_time'   => sanitize_text_field( $raw_data['departure_time'] ?? '09:00' ),
                'additional_price' => max( 0.0, floatval( $raw_data['additional_price'] ?? 0.0 ) ),
                'badge_1'          => sanitize_text_field( $raw_data['badge_1'] ?? '' ),
                'badge_2'          => sanitize_text_field( $raw_data['badge_2'] ?? '' ),
                'badge_3'          => sanitize_text_field( $raw_data['badge_3'] ?? '' ),
                'badge_4'          => sanitize_text_field( $raw_data['badge_4'] ?? '' ),
                'timeline'         => $timeline,
                'inclusions'       => sanitize_textarea_field( $raw_data['inclusions'] ?? '' ),
                'exclusions'       => sanitize_textarea_field( $raw_data['exclusions'] ?? '' ),
            );
        }

        update_post_meta( $post_id, '_circuit_options_data', $clean_options );
    }

    /* ------------------------------------------------------------------------
       METABOXES VÉHICULES, EXTRAS, PROMO & RÉSERVATIONS
    ------------------------------------------------------------------------ */
    public function render_vehicle_box( $post ) {
        wp_nonce_field( 'etb_save_meta', 'etb_nonce' );
        $base_price  = get_post_meta( $post->ID, '_etb_base_price', true );
        $hourly_rate = get_post_meta( $post->ID, '_etb_hourly_rate', true );
        $max_pax     = get_post_meta( $post->ID, '_etb_max_pax', true );
        $max_bag     = get_post_meta( $post->ID, '_etb_max_baggage', true );
        $selected_extras = get_post_meta( $post->ID, '_etb_allowed_extras', true ) ?: array();

        $extras = get_posts( array( 'post_type' => 'tour_extra', 'numberposts' => -1, 'post_status' => 'publish' ) );
        ?>
        <p>
            <label>Prix de base (€) :</label><br>
            <input type="number" step="0.01" min="0" name="etb_base_price" value="<?php echo esc_attr( $base_price ); ?>" class="widefat">
        </p>
        <p>
            <label>Taux horaire (€/h) :</label><br>
            <input type="number" step="0.01" min="0" name="etb_hourly_rate" value="<?php echo esc_attr( $hourly_rate ); ?>" class="widefat">
        </p>
        <p>
            <label>Nombre max passagers :</label><br>
            <input type="number" min="1" name="etb_max_pax" value="<?php echo esc_attr( $max_pax ); ?>" class="widefat">
        </p>
        <p>
            <label>Nombre max bagages :</label><br>
            <input type="number" min="0" name="etb_max_bag" value="<?php echo esc_attr( $max_bag ); ?>" class="widefat">
        </p>
        <p>
            <label>Options autorisées pour ce véhicule :</label><br>
            <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                <?php if ( $extras ) : foreach ( $extras as $extra ) : ?>
                    <label style="display: block; margin-bottom: 5px;">
                        <input type="checkbox" name="etb_allowed_extras[]" value="<?php echo $extra->ID; ?>" <?php checked( in_array( $extra->ID, $selected_extras ) ); ?>>
                        <?php echo esc_html( $extra->post_title ); ?>
                    </label>
                <?php endforeach; else : ?>
                    <span class="description">Aucune option créée.</span>
                <?php endif; ?>
            </div>
        </p>
        <?php
    }

    public function render_extra_box( $post ) {
        wp_nonce_field( 'etb_save_meta', 'etb_nonce' );
        $price   = get_post_meta( $post->ID, '_etb_price', true );
        $type    = get_post_meta( $post->ID, '_etb_price_type', true );
        $max_qty = get_post_meta( $post->ID, '_etb_max_qty', true );
        $icon    = get_post_meta( $post->ID, '_etb_icon', true );
        ?>
        <p>
            <label>Prix (€) :</label><br>
            <input type="number" step="0.01" min="0" name="etb_price" value="<?php echo esc_attr( $price ); ?>" class="widefat">
        </p>
        <p>
            <label>Type de tarification :</label><br>
            <select name="etb_price_type" class="widefat">
                <option value="fixed" <?php selected( $type, 'fixed' ); ?>>Prix fixe (par réservation)</option>
                <option value="per_day" <?php selected( $type, 'per_day' ); ?>>Par jour</option>
                <option value="per_quantity" <?php selected( $type, 'per_quantity' ); ?>>Par quantité</option>
            </select>
        </p>
        <p>
            <label>Quantité max par réservation :</label><br>
            <input type="number" min="1" name="etb_max_qty" value="<?php echo esc_attr( $max_qty ); ?>" class="widefat">
        </p>
        <p>
            <label>Icône Dashicons (ex: dashicons-admin-generic) :</label><br>
            <input type="text" name="etb_icon" value="<?php echo esc_attr( $icon ); ?>" class="widefat" placeholder="dashicons-...">
        </p>
        <?php
    }

    public function render_pickup_box( $post ) {
        wp_nonce_field( 'etb_save_meta', 'etb_nonce' );
        $surcharge = get_post_meta( $post->ID, '_etb_surcharge', true );
        $address   = get_post_meta( $post->ID, '_etb_address', true );
        $active    = get_post_meta( $post->ID, '_etb_active', true );
        if ( $active === '' ) $active = '1';
        ?>
        <p>
            <label>Supplément de prix (€) :</label><br>
            <input type="number" step="0.01" min="0" name="etb_surcharge" value="<?php echo esc_attr( $surcharge ); ?>" class="widefat">
        </p>
        <p>
            <label>Adresse / Infos complémentaires :</label><br>
            <textarea name="etb_address" class="widefat" rows="3"><?php echo esc_textarea( $address ); ?></textarea>
        </p>
        <p>
            <label>Statut :</label><br>
            <select name="etb_active" class="widefat">
                <option value="1" <?php selected( $active, '1' ); ?>>Actif</option>
                <option value="0" <?php selected( $active, '0' ); ?>>Inactif</option>
            </select>
        </p>
        <?php
    }

    public function render_promo_box( $post ) {
        wp_nonce_field( 'etb_save_meta', 'etb_nonce' );
        $type   = get_post_meta( $post->ID, '_etb_promo_type', true ) ?: 'percentage';
        $value  = get_post_meta( $post->ID, '_etb_promo_value', true );
        $active = get_post_meta( $post->ID, '_etb_promo_active', true );
        if ( $active === '' ) $active = '1';
        ?>
        <p>
            <label>Type de réduction :</label><br>
            <select name="etb_promo_type" class="widefat">
                <option value="percentage" <?php selected( $type, 'percentage' ); ?>>Pourcentage (%)</option>
                <option value="fixed" <?php selected( $type, 'fixed' ); ?>>Montant fixe (€)</option>
            </select>
        </p>
        <p>
            <label>Valeur de la réduction :</label><br>
            <input type="number" step="0.01" min="0" name="etb_promo_value" value="<?php echo esc_attr( $value ); ?>" class="widefat">
        </p>
        <p>
            <label>Statut :</label><br>
            <select name="etb_promo_active" class="widefat">
                <option value="1" <?php selected( $active, '1' ); ?>>Actif</option>
                <option value="0" <?php selected( $active, '0' ); ?>>Inactif</option>
            </select>
        </p>
        <?php
    }

    public function render_booking_box( $post ) {
        wp_nonce_field( 'etb_save_meta', 'etb_nonce' );

        $name           = get_post_meta( $post->ID, '_etb_customer_name', true );
        $email          = get_post_meta( $post->ID, '_etb_customer_email', true );
        $date           = get_post_meta( $post->ID, '_etb_booking_date', true );
        $time           = get_post_meta( $post->ID, '_etb_booking_time', true );
        $pickup_address = get_post_meta( $post->ID, '_etb_pickup_address', true );
        $pickup_id      = get_post_meta( $post->ID, '_etb_pickup_id', true );
        $pickup         = $pickup_address ?: ( $pickup_id ? get_the_title( $pickup_id ) : 'Non spécifié' );
        $dropoff_info   = get_post_meta( $post->ID, '_etb_dropoff_info', true );
        $adults         = get_post_meta( $post->ID, '_etb_adults', true ) ?: 0;
        $children       = get_post_meta( $post->ID, '_etb_children', true ) ?: 0;
        $luggage        = get_post_meta( $post->ID, '_etb_luggage', true ) ?: 0;

        $circuit_id        = get_post_meta( $post->ID, '_etb_circuit_id', true );
        $circuit_option_id = get_post_meta( $post->ID, '_etb_circuit_option_id', true );
        $duration_hours    = get_post_meta( $post->ID, '_etb_duration_hours', true ) ?: 1;

        $option_label = 'Transfert standard (1.0h)';
        if ( ! empty( $circuit_option_id ) ) {
            $fallback_label = sprintf( 'Option : %s (%sh)', $circuit_option_id, $duration_hours );
            $option_label   = ETB_Pricing_Engine::get_circuit_option_label( $circuit_option_id, $circuit_id, $fallback_label );
        }

        $vehicles  = get_post_meta( $post->ID, '_etb_vehicles', true ) ?: array();
        $extras    = get_post_meta( $post->ID, '_etb_extras', true ) ?: array();
        $note      = get_post_meta( $post->ID, '_etb_note', true );
        $total     = get_post_meta( $post->ID, '_etb_total_price', true ) ?: 0;
        $promo     = get_post_meta( $post->ID, '_etb_promo_code', true );
        $discount  = get_post_meta( $post->ID, '_etb_discount_amount', true );
        $status    = get_post_meta( $post->ID, '_etb_status', true ) ?: 'pending';
        ?>
        <style>
            .etb-admin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
            .etb-admin-card { background: #f8f9fa; border: 1px solid #c3c4c7; padding: 12px 15px; border-radius: 4px; }
            .etb-admin-card h4 { margin: 0 0 10px 0; border-bottom: 1px solid #dcdcde; padding-bottom: 5px; font-size: 14px; }
            .etb-status-box { background: #e7f5ff; border: 1px solid #74c0fc; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
        </style>

        <div class="etb-status-box">
            <label for="etb_status"><strong>Statut de la réservation :</strong></label>
            <select name="etb_status" id="etb_status" style="margin-left: 10px; font-weight: bold;">
                <option value="pending" <?php selected( $status, 'pending' ); ?>>⏳ En attente</option>
                <option value="confirmed" <?php selected( $status, 'confirmed' ); ?>>✅ Confirmée</option>
                <option value="completed" <?php selected( $status, 'completed' ); ?>>🏁 Terminée</option>
                <option value="cancelled" <?php selected( $status, 'cancelled' ); ?>>❌ Annulée</option>
            </select>
        </div>

        <div class="etb-admin-grid">
            <div class="etb-admin-card">
                <h4>👤 Client</h4>
                <p><strong>Nom :</strong> <?php echo esc_html( $name ?: 'Non renseigné' ); ?></p>
                <p><strong>E-mail :</strong> <?php echo esc_html( $email ?: 'Non renseigné' ); ?></p>
            </div>

            <div class="etb-admin-card">
                <h4>📍 Trajet & Passagers</h4>
                <p><strong>Prestation :</strong> <?php echo $circuit_option_id ? '<span style="background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px;">' . esc_html( $option_label ) . '</span>' : '<span style="color: #64748b;"><em>Transfert standard (1.0h)</em></span>'; ?></p>
                <p><strong>Lieu de départ :</strong> <?php echo esc_html( $pickup ); ?></p>
                <p><strong>Lieu de dépose :</strong> <?php echo $dropoff_info ? nl2br( esc_html( $dropoff_info ) ) : '<em>Identique au lieu de départ</em>'; ?></p>
                <p><strong>Date & Heure :</strong> <?php echo esc_html( $date ); ?> à <?php echo esc_html( $time ); ?></p>
                <p><strong>Passagers :</strong> <?php echo esc_html( $adults ); ?> adulte(s), <?php echo esc_html( $children ); ?> enfant(s)</p>
                <p><strong>Bagages :</strong> <?php echo esc_html( $luggage ); ?></p>
            </div>
        </div>

        <div class="etb-admin-grid">
            <div class="etb-admin-card">
                <h4>🚘 Véhicules & Options</h4>
                <p><strong>Véhicule(s) :</strong></p>
                <ul>
                    <?php
                    $has_vehicles = false;
                    foreach ( $vehicles as $v_id => $qty ) {
                        if ( $qty > 0 ) {
                            $has_vehicles = true;
                            echo '<li>• ' . esc_html( get_the_title( $v_id ) ) . ' &times; ' . absint( $qty ) . '</li>';
                        }
                    }
                    if ( ! $has_vehicles ) echo '<li><em>Aucun véhicule sélectionné</em></li>';
                    ?>
                </ul>

                <p><strong>Option(s) :</strong></p>
                <ul>
                    <?php
                    $has_extras = false;
                    foreach ( $extras as $e_id => $qty ) {
                        if ( $qty > 0 ) {
                            $has_extras = true;
                            echo '<li>• ' . esc_html( get_the_title( $e_id ) ) . ' &times; ' . absint( $qty ) . '</li>';
                        }
                    }
                    if ( ! $has_extras ) echo '<li><em>Aucune option sélectionnée</em></li>';
                    ?>
                </ul>
            </div>

            <div class="etb-admin-card">
                <h4>💳 Tarification & Note</h4>
                <?php if ( $promo ) : ?>
                    <p><strong>Code Promo :</strong> <?php echo esc_html( $promo ); ?> (-<?php echo number_format( (float) $discount, 2, ',', ' ' ); ?> €)</p>
                <?php endif; ?>
                <p style="font-size: 16px; color: #1d2327;"><strong>Montant Total :</strong> <span style="color: #22c55e; font-weight: bold;"><?php echo number_format( (float) $total, 2, ',', ' ' ); ?> €</span></p>
                
                <?php if ( $note ) : ?>
                    <hr>
                    <p><strong>Demande spéciale :</strong></p>
                    <p style="font-style: italic; background: #fff; padding: 8px; border: 1px solid #e2e8f0; border-radius: 3px;"><?php echo nl2br( esc_html( $note ) ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function save_etb_meta_data( $post_id ) {
        if ( ! isset( $_POST['etb_nonce'] ) || ! wp_verify_nonce( $_POST['etb_nonce'], 'etb_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $post_type = get_post_type( $post_id );

        switch ( $post_type ) {
            case 'tour_vehicle':
                $base_price  = isset( $_POST['etb_base_price'] ) ? max( 0, floatval( $_POST['etb_base_price'] ) ) : 0;
                $hourly_rate = isset( $_POST['etb_hourly_rate'] ) ? max( 0, floatval( $_POST['etb_hourly_rate'] ) ) : 0;
                update_post_meta( $post_id, '_etb_base_price', $base_price );
                update_post_meta( $post_id, '_etb_hourly_rate', $hourly_rate );
                update_post_meta( $post_id, '_etb_max_pax', absint( $_POST['etb_max_pax'] ) );
                update_post_meta( $post_id, '_etb_max_baggage', absint( $_POST['etb_max_bag'] ) );

                $allowed_extras = array();
                if ( isset( $_POST['etb_allowed_extras'] ) && is_array( $_POST['etb_allowed_extras'] ) ) {
                    foreach ( $_POST['etb_allowed_extras'] as $extra_id ) {
                        $id = absint( $extra_id );
                        if ( $id > 0 && get_post_type( $id ) === 'tour_extra' ) {
                            $allowed_extras[] = $id;
                        }
                    }
                }
                update_post_meta( $post_id, '_etb_allowed_extras', $allowed_extras );
                break;

            case 'tour_extra':
                $extra_price = isset( $_POST['etb_price'] ) ? max( 0, floatval( $_POST['etb_price'] ) ) : 0;
                update_post_meta( $post_id, '_etb_price', $extra_price );

                $valid_types = array( 'fixed', 'per_day', 'per_quantity' );
                $type = ( isset( $_POST['etb_price_type'] ) && in_array( $_POST['etb_price_type'], $valid_types ) ) ? $_POST['etb_price_type'] : 'fixed';
                update_post_meta( $post_id, '_etb_price_type', $type );
                update_post_meta( $post_id, '_etb_max_qty', max( 1, absint( $_POST['etb_max_qty'] ) ) );
                update_post_meta( $post_id, '_etb_icon', sanitize_text_field( $_POST['etb_icon'] ) );
                break;

            case 'tour_pickup':
                $surcharge = isset( $_POST['etb_surcharge'] ) ? max( 0, floatval( $_POST['etb_surcharge'] ) ) : 0;
                update_post_meta( $post_id, '_etb_surcharge', $surcharge );
                update_post_meta( $post_id, '_etb_address', sanitize_textarea_field( $_POST['etb_address'] ) );
                $status = ( isset( $_POST['etb_active'] ) && $_POST['etb_active'] === '0' ) ? '0' : '1';
                update_post_meta( $post_id, '_etb_active', $status );
                break;

            case 'tour_promo':
                $valid_promo_types = array( 'fixed', 'percentage' );
                $p_type = ( isset( $_POST['etb_promo_type'] ) && in_array( $_POST['etb_promo_type'], $valid_promo_types ) ) ? $_POST['etb_promo_type'] : 'percentage';
                $p_value = floatval( $_POST['etb_promo_value'] );
                if ( $p_type === 'percentage' ) {
                    $p_value = max( 0, min( 100, $p_value ) );
                } else {
                    $p_value = max( 0, $p_value );
                }
                update_post_meta( $post_id, '_etb_promo_type', $p_type );
                update_post_meta( $post_id, '_etb_promo_value', $p_value );
                update_post_meta( $post_id, '_etb_promo_active', ( $_POST['etb_promo_active'] === '0' ) ? '0' : '1' );
                break;

            case 'tour_booking':
                if ( isset( $_POST['etb_status'] ) ) {
                    $old_status = get_post_meta( $post_id, '_etb_status', true ) ?: 'pending';
                    $valid_statuses = array( 'pending', 'confirmed', 'completed', 'cancelled' );
                    $new_status = in_array( $_POST['etb_status'], $valid_statuses, true ) ? $_POST['etb_status'] : 'pending';

                    if ( $old_status !== $new_status ) {
                        update_post_meta( $post_id, '_etb_status', $new_status );
                        $this->send_status_change_email( $post_id, $new_status );
                    }
                }
                break;
        }
    }

    public function normalize_promo_code_title( $data, $postarr ) {
        if ( $data['post_type'] === 'tour_promo' && ! empty( $data['post_title'] ) ) {
            $data['post_title'] = strtoupper( sanitize_text_field( $data['post_title'] ) );
        }
        return $data;
    }

    private function send_status_change_email( $booking_id, $new_status ) {
        $email = get_post_meta( $booking_id, '_etb_customer_email', true );
        $name  = get_post_meta( $booking_id, '_etb_customer_name', true );
        $date  = get_post_meta( $booking_id, '_etb_booking_date', true );
        $time  = get_post_meta( $booking_id, '_etb_booking_time', true );

        if ( empty( $email ) || ! is_email( $email ) ) return;

        $general_settings = get_option( 'etb_general_settings', array() );
        $currency_symbol  = ! empty( $general_settings['currency'] ) ? sanitize_text_field( $general_settings['currency'] ) : '€';
        $total            = get_post_meta( $booking_id, '_etb_total_price', true );
        $formatted_total  = number_format_i18n( (float) $total, 2 ) . ' ' . $currency_symbol;

        $subject = ''; $status_title = ''; $status_message = '';
        switch ( $new_status ) {
            case 'confirmed':
                $subject = sprintf( '✅ Votre réservation #%d est confirmée', $booking_id );
                $status_title = 'Réservation Confirmée';
                $status_message = 'Votre demande de réservation a été validée par notre équipe.';
                break;
            case 'cancelled':
                $subject = sprintf( '❌ Annulation de votre réservation #%d', $booking_id );
                $status_title = 'Réservation Annulée';
                $status_message = 'Votre demande de réservation a été annulée.';
                break;
            case 'completed':
                $subject = sprintf( '🏁 Votre réservation #%d est terminée', $booking_id );
                $status_title = 'Prestation Effectuée';
                $status_message = 'Votre prestation est désormais terminée. Merci pour votre confiance !';
                break;
            default:
                return;
        }

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $message = sprintf(
            '<h2>Bonjour %s,</h2><p><strong>Mise à jour concernant votre dossier #%d :</strong></p><div style="background-color: #f8f9fa; border-left: 4px solid #0073aa; padding: 12px; margin: 15px 0;"><h3 style="margin-top:0;">%s</h3><p style="margin-bottom:0;">%s</p></div><hr><h3>Rappel de vos informations :</h3><ul><li><strong>Date et Heure :</strong> %s à %s</li><li><strong>Montant Total :</strong> %s</li></ul>',
            esc_html( $name ), $booking_id, esc_html( $status_title ), esc_html( $status_message ), esc_html( $date ), esc_html( $time ), $formatted_total
        );
        wp_mail( $email, $subject, $message, $headers );
    }
}