<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CO_Meta_Box_Manager {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_circuit_meta_boxes' ) );
        add_action( 'save_post_circuit', array( $this, 'save_circuit_options_data' ) );
    }

    public function add_circuit_meta_boxes() {
        add_meta_box(
            'co_circuit_options_meta',
            '📍 Options de Départ & Programme du Circuit',
            array( $this, 'render_circuit_options_box' ),
            'circuit',
            'normal',
            'high'
        );
    }

    public function render_circuit_options_box( $post ) {
        wp_nonce_field( 'co_save_options', 'co_nonce' );

        $options = get_post_meta( $post->ID, '_circuit_options_data', true );
        if ( empty( $options ) || ! is_array( $options ) ) {
            // Option par défaut initiale si nouveau circuit
            $options = array(
                'opt_default' => array(
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
            <!-- Onglets des options -->
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

            <!-- Contenu des onglets -->
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

        <!-- Template HTML caché pour le clonage JS lors de l'ajout d'une nouvelle option -->
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

            <!-- Bloc 1 : Liaison ETB & Nom -->
            <div class="co-section-card">
                <h4><span class="dashicons dashicons-admin-settings"></span> 1. Paramètres & Liaison ETB</h4>
                <div class="co-grid-4">
                    <div class="co-field">
                        <label>Ville / Titre de départ * :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][city_name]" value="<?php echo esc_attr( $city ); ?>" class="widefat co-city-input" placeholder="Ex: Cannes, Antsirabe...">
                    </div>
                    <div class="co-field">
                        <label>Durée (en heures) * :</label>
                        <input type="number" step="0.25" min="0.5" name="co_options[<?php echo esc_attr( $opt_id ); ?>][duration_hours]" value="<?php echo esc_attr( $duration ); ?>" class="widefat">
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

            <!-- Bloc 2 : Les 4 Badges du haut -->
            <div class="co-section-card">
                <h4><span class="dashicons dashicons-tag"></span> 2. Les 4 Badges récapitulatifs (Highlights)</h4>
                <div class="co-grid-4">
                    <div class="co-field">
                        <label>Badge 1 (ex: Durée) :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][badge_1]" value="<?php echo esc_attr( $badge_1 ); ?>" class="widefat">
                    </div>
                    <div class="co-field">
                        <label>Badge 2 (ex: Lieu) :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][badge_2]" value="<?php echo esc_attr( $badge_2 ); ?>" class="widefat">
                    </div>
                    <div class="co-field">
                        <label>Badge 3 (ex: Type) :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][badge_3]" value="<?php echo esc_attr( $badge_3 ); ?>" class="widefat">
                    </div>
                    <div class="co-field">
                        <label>Badge 4 (ex: Langue) :</label>
                        <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][badge_4]" value="<?php echo esc_attr( $badge_4 ); ?>" class="widefat">
                    </div>
                </div>
            </div>

            <!-- Bloc 3 : Programme / Timeline -->
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

            <!-- Bloc 4 : Inclus / Non inclus -->
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
        if ( ! isset( $_POST['co_nonce'] ) || ! wp_verify_nonce( $_POST['co_nonce'], 'co_save_options' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( ! isset( $_POST['co_options'] ) || ! is_array( $_POST['co_options'] ) ) {
            delete_post_meta( $post_id, '_circuit_options_data' );
            return;
        }

        $clean_options = array();

        foreach ( $_POST['co_options'] as $opt_id => $raw_data ) {
            // Nettoyage de l'ID
            $clean_id = sanitize_key( $opt_id );
            if ( empty( $clean_id ) ) continue;

            // Timeline reconstruction
            $timeline = array();
            if ( ! empty( $raw_data['timeline_time'] ) && is_array( $raw_data['timeline_time'] ) ) {
                $count = count( $raw_data['timeline_time'] );
                for ( $i = 0; $i < $count; $i++ ) {
                    $t_time  = sanitize_text_field( $raw_data['timeline_time'][ $i ] ?? '' );
                    $t_title = sanitize_text_field( $raw_data['timeline_title'][ $i ] ?? '' );
                    $t_desc  = sanitize_textarea_field( $raw_data['timeline_desc'][ $i ] ?? '' );

                    if ( ! empty( $t_title ) || ! empty( $t_time ) ) {
                        $timeline[] = array(
                            'time'  => $t_time,
                            'title' => $t_title,
                            'desc'  => $t_desc,
                        );
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
}

new CO_Meta_Box_Manager();