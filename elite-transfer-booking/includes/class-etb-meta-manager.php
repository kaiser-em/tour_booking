<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ETB_Meta_Manager {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_etb_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_etb_meta_data' ) );
        add_action( 'save_post_circuit', array( $this, 'save_circuit_options_data' ) );
        add_filter( 'wp_insert_post_data', array( $this, 'normalize_promo_code_title' ), 10, 2 );
        add_action( 'admin_post_etb_print_invoice', array( $this, 'handle_print_invoice' ) ); // <-- AJOUT DE CETTE LIGNE
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
                    'overview_title'   => 'Aperçu du Circuit',
                    'overview_desc'    => '',
                    'highlights'       => array(
                        '⏱ 4h d\'excursion',
                        '📍 Prise en charge hôtel',
                        '👥 Visite privée',
                        '🗣 Guide accompagnateur'
                    ),
                    'timeline'         => array(
                        array( 'time' => '09:00', 'title' => 'Prise en charge', 'desc' => 'Départ de votre hôtel.' ),
                    ),
                    'inclusions'       => "Véhicule privé et chauffeur\nGuide\nEau à bord",
                    'exclusions'       => "Repas",
                ),
            );
        }

        // Champs « Autres circuits » (niveau circuit, pas par option de         // Champs « Autres circuits » (niveau circuit, pas par option de départ)
        $related_title    = get_post_meta( $post->ID, '_circuit_related_title', true );
        $related_subtitle = get_post_meta( $post->ID, '_circuit_related_subtitle', true );

        // Interrupteur d'affichage : absence = '1' (rétrocompatibilité).
        $related_enabled  = get_post_meta( $post->ID, '_circuit_related_enabled', true );
        if ( '' === $related_enabled ) {
            $related_enabled = '1';
        }
        ?>
        <div class="co-admin-wrapper">
                        <!-- Section « Autres circuits » (champs au niveau circuit) -->
            <div style="background: #ffffff; border: 1.5px solid #ebd9cc; border-radius: 8px; padding: 18px; margin-bottom: 18px;">
                <h3 style="margin: 0 0 6px 0; color: #5b3516; font-size: 15px; font-weight: 700;">
                    🔗 Section « Autres circuits » (bas de page)
                </h3>
                <p style="font-size: 12px; color: #64748b; margin: 0 0 14px 0;">
                    Personnalisez le titre et le sous-titre affichés sous la vue circuit. Laissez vide pour utiliser les valeurs par défaut.
                </p>

                                <!-- Interrupteur : afficher / masquer la section au front -->
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                        <input type="checkbox" name="etb_related_enabled" value="1" <?php checked( $related_enabled, '1' ); ?>>
                        <span style="font-size: 13px; font-weight: 600; color: #334155;">Afficher la section sur la page</span>
                    </label>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div>
                        <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Titre :</label>
                        <input type="text" name="etb_related_title" value="<?php echo esc_attr( $related_title ); ?>" class="widefat" placeholder="Découvrez nos autres circuits">
                    </div>
                    <div>
                        <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Sous-titre :</label>
                        <input type="text" name="etb_related_subtitle" value="<?php echo esc_attr( $related_subtitle ); ?>" class="widefat" placeholder="Poursuivez l'aventure avec une autre expérience sur mesure.">
                    </div>
                </div>
            </div>
            <div class="co-tabs-nav" style="margin-bottom: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
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
                'overview_title'   => 'Aperçu du Circuit',
                'overview_desc'    => '',
                'highlights'       => array( '⏱ Excursion privée', '📍 Prise en charge hôtel' ),
                'timeline'         => array(),
                'inclusions'       => "Véhicule privé\nChauffeur\nEau à bord",
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
        
        // Rétrocompatibilité : migration des anciens badges vers "highlights" si vide
        $highlights = $data['highlights'] ?? array();
        if ( empty( $highlights ) ) {
            $old_badges = array_filter( array(
                $data['badge_1'] ?? '',
                $data['badge_2'] ?? '',
                $data['badge_3'] ?? '',
                $data['badge_4'] ?? '',
            ) );
            if ( ! empty( $old_badges ) ) {
                $highlights = array_values( $old_badges );
            }
        }

        $overview_title = $data['overview_title'] ?? 'Aperçu du Circuit';
        $overview_desc  = $data['overview_desc'] ?? '';
        $timeline       = $data['timeline'] ?? array();
        $inclusions     = $data['inclusions'] ?? '';
        $exclusions     = $data['exclusions'] ?? '';
        ?>
        <div class="co-pane <?php echo $is_active ? 'active' : ''; ?>" id="<?php echo esc_attr( $opt_id ); ?>">
            <div class="co-pane-header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <span style="font-weight: 600; color: #475569;">ID Option de départ : <code><?php echo esc_html( $opt_id ); ?></code></span>
                <button type="button" class="co-btn-danger co-delete-option-btn" style="background: #ef4444; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">🗑 Supprimer cette option</button>
            </div>

            <!-- CONTENEUR WP TRAVEL ENGINE AVEC ONGLETS VERTICAUX -->
            <div class="etb-engine-container">
                
                <!-- NAVIGATION VERTICALE GAUCHE -->
                <div class="etb-engine-sidebar">
                    <button type="button" class="etb-engine-tab-btn active" data-target="engine-overview-<?php echo esc_attr( $opt_id ); ?>">
                        <span class="dashicons dashicons-welcome-write-blog"></span> Présentation
                    </button>
                    <button type="button" class="etb-engine-tab-btn" data-target="engine-general-<?php echo esc_attr( $opt_id ); ?>">
                        <span class="dashicons dashicons-admin-settings"></span> Général & Tarifs
                    </button>
                    <button type="button" class="etb-engine-tab-btn" data-target="engine-tripinfo-<?php echo esc_attr( $opt_id ); ?>">
                        <span class="dashicons dashicons-info"></span> Trip Info
                    </button>
                    <button type="button" class="etb-engine-tab-btn" data-target="engine-itinerary-<?php echo esc_attr( $opt_id ); ?>">
                        <span class="dashicons dashicons-location-alt"></span> Itinéraire
                    </button>
                    <button type="button" class="etb-engine-tab-btn" data-target="engine-mapgallery-<?php echo esc_attr( $opt_id ); ?>">
                        <span class="dashicons dashicons-format-gallery"></span> Map & Gallery
                    </button>
                    <button type="button" class="etb-engine-tab-btn" data-target="engine-includes-<?php echo esc_attr( $opt_id ); ?>">
                        <span class="dashicons dashicons-yes-alt"></span> Inclus / Exclus
                    </button>
                </div>
                <!-- CONTENU DROIT -->
                <div class="etb-engine-content">
                    
                    <!-- ONGLET UNIQUE : PRÉSENTATION & POINTS FORTS -->
                    <div class="etb-engine-pane active" id="engine-overview-<?php echo esc_attr( $opt_id ); ?>">
                        <h3 class="etb-engine-pane-title">Présentation du Circuit</h3>
                        
                        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 30px; align-items: start;">
                            
                            <!-- COLONNE GAUCHE (ÉDITEUR) : Sections de texte -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px;">
                                <h4 style="margin: 0 0 10px 0; color: #0f172a; font-size: 14px;">📝 Texte (Colonne gauche)</h4>
                                <p style="font-size: 12px; color: #64748b; margin-bottom: 15px;">Ajoutez des blocs (Titre + Paragraphe) pour présenter le circuit.</p>
                                
                                <div class="etb-overview-sections-list" id="etb-overview-sections-<?php echo esc_attr( $opt_id ); ?>" style="display: flex; flex-direction: column; gap: 14px;">
                                    <?php 
                                    $sections = $data['overview_sections'] ?? array();
                                    
                                    // Rétrocompatibilité : fusionne l'ancien hero_desc et overview_desc s'ils existent
                                    if ( empty( $sections ) ) {
                                        if ( ! empty( $data['hero_desc'] ) ) {
                                            $sections[] = array( 'title' => $data['hero_title'] ?? '', 'desc' => $data['hero_desc'] );
                                        }
                                        if ( ! empty( $data['overview_desc'] ) ) {
                                            $sections[] = array( 'title' => $data['overview_title'] ?? 'Overview', 'desc' => $data['overview_desc'] );
                                        }
                                        if ( empty( $sections ) ) {
                                            $sections[] = array( 'title' => 'Overview', 'desc' => '' ); // Par défaut 1 bloc vide
                                        }
                                    }

                                    foreach ( $sections as $s_idx => $sec ) : 
                                    ?>
                                        <div class="etb-overview-section-item" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px; position: relative;">
                                            <div style="display: flex; justify-content: space-between; gap: 10px; margin-bottom: 10px;">
                                                <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][overview_sec_title][]" value="<?php echo esc_attr( $sec['title'] ); ?>" class="widefat" placeholder="Titre (ex: Overview)" style="font-weight: 600; font-size: 14px;">
                                                <button type="button" class="etb-btn-danger-icon etb-remove-overview-sec-btn" title="Supprimer ce bloc" style="flex-shrink: 0;"><span class="dashicons dashicons-trash"></span></button>
                                            </div>
                                            <textarea name="co_options[<?php echo esc_attr( $opt_id ); ?>][overview_sec_desc][]" rows="4" class="widefat etb-rich-text" placeholder="Rédigez votre paragraphe..."><?php echo esc_textarea( $sec['desc'] ); ?></textarea>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button button-small etb-add-overview-sec-btn" data-optid="<?php echo esc_attr( $opt_id ); ?>" style="margin-top: 14px;">
                                    + Ajouter une section (Titre & Texte)
                                </button>
                            </div>

                            <!-- COLONNE DROITE : Points forts -->
                            <div style="background: #ffffff; border: 1.5px solid #ebd9cc; border-radius: 8px; padding: 18px; box-shadow: 0 2px 10px rgba(91, 53, 22, 0.04);">
                                <h4 style="margin: 0 0 10px 0; color: #5b3516; font-size: 14px;">⭐ Highlights (Carte de droite)</h4>
                                
                                <label style="font-size: 12px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Titre de la carte :</label>
                                <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][highlights_title]" value="<?php echo esc_attr( $data['highlights_title'] ?? 'Highlights' ); ?>" class="widefat" style="margin-bottom: 15px;">

                                <div class="etb-highlights-list" id="etb-highlights-list-<?php echo esc_attr( $opt_id ); ?>" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px;">
                                    <?php 
                                    // Rétrocompatibilité
                                    $highlights = $data['highlights'] ?? array();
                                    if ( empty( $highlights ) && ! empty( $data['hero_bullets'] ) ) {
                                        $highlights = array_filter( array_map( 'trim', explode( "\n", $data['hero_bullets'] ) ) );
                                    }
                                    if ( ! empty( $highlights ) ) : foreach ( $highlights as $hl ) : ?>
                                        <div class="etb-highlight-item" style="display: flex; gap: 6px;">
                                            <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][highlights][]" value="<?php echo esc_attr( $hl ); ?>" class="widefat" style="font-size: 13px;">
                                            <button type="button" class="etb-btn-danger-icon etb-remove-highlight-btn" title="Supprimer" style="width: 26px; height: 26px;"><span class="dashicons dashicons-trash" style="font-size: 14px;"></span></button>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                                <button type="button" class="etb-btn-secondary etb-add-highlight-btn" data-list="etb-highlights-list-<?php echo esc_attr( $opt_id ); ?>" data-optid="<?php echo esc_attr( $opt_id ); ?>">+ Add Highlight</button>
                            </div>
                        </div>
                    </div>

                    <!-- ONGLET : GÉNÉRAL & TARIFS -->
                    <div class="etb-engine-pane" id="engine-general-<?php echo esc_attr( $opt_id ); ?>">
                        <h3 class="etb-engine-pane-title">Général & Tarifs</h3>
                        <div class="etb-engine-grid-2">
                            <div class="etb-engine-row">
                                <label>Ville / Titre de départ * :</label>
                                <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][city_name]" value="<?php echo esc_attr( $city ); ?>" class="widefat co-city-input" placeholder="Ex: Cannes, Antsirabe...">
                            </div>
                            <div class="etb-engine-row">
                                <label>Durée (en heures) * :</label>
                                <input type="number" step="0.25" min="0.25" name="co_options[<?php echo esc_attr( $opt_id ); ?>][duration_hours]" value="<?php echo esc_attr( $duration ); ?>" class="widefat">
                            </div>
                            <div class="etb-engine-row">
                                <label>Heure départ par défaut :</label>
                                <input type="time" name="co_options[<?php echo esc_attr( $opt_id ); ?>][departure_time]" value="<?php echo esc_attr( $departure_time ); ?>" class="widefat">
                            </div>
                            <div class="etb-engine-row">
                                <label>Prix additionnel de départ (€) :</label>
                                <input type="number" step="0.01" min="0" name="co_options[<?php echo esc_attr( $opt_id ); ?>][additional_price]" value="<?php echo esc_attr( $additional_price ); ?>" class="widefat" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <!-- ONGLET : TRIP INFO (MODULE PROFESSIONNEL STYLE WP TRAVEL ENGINE) -->
                    <div class="etb-engine-pane" id="engine-tripinfo-<?php echo esc_attr( $opt_id ); ?>">
                        <h3 class="etb-engine-pane-title">Trip Info & Caractéristiques Clés</h3>
                        
                        <div class="etb-engine-row">
                            <label>Section Title :</label>
                            <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][trip_info_title]" value="<?php echo esc_attr( $data['trip_info_title'] ?? 'trip info' ); ?>" class="widefat" placeholder="trip info">
                        </div>

                        <!-- Barre d'action supérieure (Sélecteur rapide + Bouton Add Facts + Bouton Add Custom Info) -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0;">
                            <label style="font-weight: 700; color: #1e293b; display: block; margin-bottom: 8px;">Trip Info Selection :</label>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <select class="etb-tripinfo-type-select" style="min-width: 250px; height: 38px;">
                                    <option value="transportation" data-icon="fas fa-bus" data-label="Transportation" data-default="Vtc">🚍 Transportation (VTC / Chauffeur)</option>
                                    <option value="wifi" data-icon="fas fa-wifi" data-label="Wifi" data-default="Available">📶 Wifi</option>
                                    <option value="cancellation" data-icon="far fa-calendar-xmark" data-label="Free Cancellation" data-default="Yes">📅 Free Cancellation</option>
                                    <option value="language" data-icon="fas fa-language" data-label="Language" data-default="English, French">🗣 Language</option>
                                    <option value="hotel_transfer" data-icon="fas fa-hotel" data-label="Hotel Transfer" data-default="Available">🏨 Hotel Transfer</option>
                                    <option value="mineral_water" data-icon="fas fa-bottle-droplet" data-label="Mineral Water" data-default="Available">💧 Mineral Water</option>
                                    <option value="guide" data-icon="fas fa-hands-praying" data-label="Guide" data-default="Guided">🙏 Guide</option>
                                    <option value="meals" data-icon="fas fa-bowl-food" data-label="Meals" data-default="Breakfast and Dinner">🍽 Meals</option>
                                    <option value="meeting_point" data-icon="fas fa-handshake-simple" data-label="Meeting Point" data-default="Hotel">🤝 Meeting Point</option>
                                    <option value="payment_method" data-icon="fab fa-cc-mastercard" data-label="Payment Method" data-default="VISA, Master Card">💳 Payment Method</option>
                                    <option value="group_size" data-icon="fas fa-user-group" data-label="Group Size" data-default="Private (1-7 pax)">👥 Group Size</option>
                                    <option value="walking_hours" data-icon="far fa-clock" data-label="Walking Hours" data-default="5-6 Hours">⏱ Walking Hours / Duration</option>
                                    <option value="admission_fee" data-icon="fas fa-tag" data-label="Admission Fee" data-default="Included">🏷 Admission Fee</option>
                                    <option value="insurance" data-icon="fas fa-hospital-user" data-label="Insurance Coverage" data-default="Covered 80%">🏥 Insurance Coverage</option>
                                    <option value="best_season" data-icon="fas fa-cloud-sun" data-label="Best Season" data-default="All year">⛅ Best Season</option>
                                </select>
                                
                                <button type="button" class="button button-primary etb-add-fact-btn" data-optid="<?php echo esc_attr( $opt_id ); ?>" style="background: #2563eb; border-color: #2563eb; height: 38px; font-weight: 600;">
                                    Add Facts
                                </button>

                                <button type="button" class="button etb-add-custom-info-btn" data-optid="<?php echo esc_attr( $opt_id ); ?>" style="height: 38px;">
                                    + Add Custom Info
                                </button>
                            </div>
                            <span class="description" style="font-size: 11.5px; color: #64748b; margin-top: 6px; display: block;">
                                Sélectionnez une caractéristique dans la liste ou cliquez sur "+ Add Custom Info" pour en créer une sur-mesure.
                            </span>
                        </div>

                       <!-- En-tête des colonnes de la liste -->
                        <div style="display: grid; grid-template-columns: 28px 160px 220px 1fr 34px; gap: 10px; padding: 0 14px 6px 14px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                            <span></span>
                            <span>Nom / Libellé</span>
                            <span>Icône Font Awesome</span>
                            <span>Valeur</span>
                            <span></span>
                        </div>

                        <!-- Liste dynamique des faits enregistrés -->
                        <div class="etb-tripinfo-list" id="etb-tripinfo-list-<?php echo esc_attr( $opt_id ); ?>" style="display: flex; flex-direction: column; gap: 10px;">
                            <?php 
                            $saved_facts = $data['trip_facts'] ?? array();
                            if ( ! empty( $saved_facts ) && is_array( $saved_facts ) ) : 
                                foreach ( $saved_facts as $f_idx => $fact ) : 
                                    $f_type  = $fact['type'] ?? 'custom';
                                    $f_icon  = $fact['icon'] ?? 'fas fa-info-circle';
                                    $f_label = $fact['label'] ?? 'Info';
                                    $f_val   = $fact['value'] ?? '';
                            ?>
                                <div class="etb-tripinfo-item" style="display: grid; grid-template-columns: 28px 160px 220px 1fr 34px; gap: 10px; align-items: center; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px;">
                                    <span class="dashicons dashicons-menu" style="color: #94a3b8; cursor: grab;"></span>
                                    <input type="hidden" name="co_options[<?php echo esc_attr( $opt_id ); ?>][trip_facts][<?php echo esc_attr( $f_idx ); ?>][type]" value="<?php echo esc_attr( $f_type ); ?>">
                                    
                                    <!-- Nom du champ -->
                                    <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][trip_facts][<?php echo esc_attr( $f_idx ); ?>][label]" value="<?php echo esc_attr( $f_label ); ?>" class="widefat" style="font-weight: 600;" placeholder="Name">
                                    
                                    <!-- Colonne Icône : Carré d'aperçu dynamique + Menu déroulant -->
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="etb-fa-preview" style="width: 36px; height: 36px; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; color: #ba6827; flex-shrink: 0;">
                                            <i class="<?php echo esc_attr( $f_icon ); ?>"></i>
                                        </span>
                                        
                                        <select name="co_options[<?php echo esc_attr( $opt_id ); ?>][trip_facts][<?php echo esc_attr( $f_idx ); ?>][icon]" class="widefat etb-fa-icon-select" style="font-size: 12px; height: 36px; font-family: monospace;">
                                            <option value="fas fa-hotel" <?php selected( $f_icon, 'fas fa-hotel' ); ?>>fas fa-hotel</option>
                                            <option value="fas fa-bus" <?php selected( $f_icon, 'fas fa-bus' ); ?>>fas fa-bus</option>
                                            <option value="fas fa-car-side" <?php selected( $f_icon, 'fas fa-car-side' ); ?>>fas fa-car-side</option>
                                            <option value="fas fa-van-shuttle" <?php selected( $f_icon, 'fas fa-van-shuttle' ); ?>>fas fa-van-shuttle</option>
                                            <option value="fas fa-wifi" <?php selected( $f_icon, 'fas fa-wifi' ); ?>>fas fa-wifi</option>
                                            <option value="far fa-calendar-xmark" <?php selected( $f_icon, 'far fa-calendar-xmark' ); ?>>far fa-calendar-xmark</option>
                                            <option value="fas fa-language" <?php selected( $f_icon, 'fas fa-language' ); ?>>fas fa-language</option>
                                            <option value="fas fa-bottle-droplet" <?php selected( $f_icon, 'fas fa-bottle-droplet' ); ?>>fas fa-bottle-droplet</option>
                                            <option value="fas fa-hands-praying" <?php selected( $f_icon, 'fas fa-hands-praying' ); ?>>fas fa-hands-praying</option>
                                            <option value="fas fa-user-tie" <?php selected( $f_icon, 'fas fa-user-tie' ); ?>>fas fa-user-tie</option>
                                            <option value="fas fa-bowl-food" <?php selected( $f_icon, 'fas fa-bowl-food' ); ?>>fas fa-bowl-food</option>
                                            <option value="fas fa-handshake-simple" <?php selected( $f_icon, 'fas fa-handshake-simple' ); ?>>fas fa-handshake-simple</option>
                                            <option value="fab fa-cc-mastercard" <?php selected( $f_icon, 'fab fa-cc-mastercard' ); ?>>fab fa-cc-mastercard</option>
                                            <option value="fas fa-tag" <?php selected( $f_icon, 'fas fa-tag' ); ?>>fas fa-tag</option>
                                            <option value="fas fa-user-group" <?php selected( $f_icon, 'fas fa-user-group' ); ?>>fas fa-user-group</option>
                                            <option value="far fa-clock" <?php selected( $f_icon, 'far fa-clock' ); ?>>far fa-clock</option>
                                            <option value="fas fa-cloud-sun" <?php selected( $f_icon, 'fas fa-cloud-sun' ); ?>>fas fa-cloud-sun</option>
                                            <option value="fas fa-hospital-user" <?php selected( $f_icon, 'fas fa-hospital-user' ); ?>>fas fa-hospital-user</option>
                                            <option value="fas fa-plane-arrival" <?php selected( $f_icon, 'fas fa-plane-arrival' ); ?>>fas fa-plane-arrival</option>
                                            <option value="fas fa-suitcase-rolling" <?php selected( $f_icon, 'fas fa-suitcase-rolling' ); ?>>fas fa-suitcase-rolling</option>
                                            <option value="fas fa-shield-halved" <?php selected( $f_icon, 'fas fa-shield-halved' ); ?>>fas fa-shield-halved</option>
                                            <option value="fas fa-child" <?php selected( $f_icon, 'fas fa-child' ); ?>>fas fa-child</option>
                                            <option value="fas fa-star" <?php selected( $f_icon, 'fas fa-star' ); ?>>fas fa-star</option>
                                        </select>
                                    </div>

                                    <!-- Valeur -->
                                    <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][trip_facts][<?php echo esc_attr( $f_idx ); ?>][value]" value="<?php echo esc_attr( $f_val ); ?>" class="widefat" placeholder="Valeur (ex: Available, Yes...)">
                                    
                                    <!-- Bouton Supprimer -->
                                    <button type="button" class="etb-btn-danger-icon etb-remove-fact-btn" title="Supprimer">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- ONGLET 3 : ITINÉRAIRE (AVEC SOUS-SECTIONS DÉTAILLÉES) -->
                    <div class="etb-engine-pane" id="engine-itinerary-<?php echo esc_attr( $opt_id ); ?>">
                        <h3 class="etb-engine-pane-title">Itinéraire & Programme</h3>
                        <div class="etb-itinerary-list" id="etb-itinerary-list-<?php echo esc_attr( $opt_id ); ?>">
                            <?php if ( ! empty( $timeline ) ) : foreach ( $timeline as $index => $step ) : 
                                $step_key = 'step_' . $index;
                                $subblocks = $step['subblocks'] ?? array();
                            ?>
                                <div class="etb-itinerary-step" data-stepkey="<?php echo esc_attr( $step_key ); ?>">
                                    <div class="etb-itinerary-header">
                                        <div class="etb-itinerary-title-wrap">
                                            <span class="dashicons dashicons-menu" style="color: #94a3b8; cursor: grab;"></span>
                                            <span class="etb-step-header-text"><?php echo esc_html( $step['title'] ?: 'Étape ' . ($index + 1) ); ?></span>
                                        </div>
                                        <div class="etb-itinerary-actions">
                                            <button type="button" class="etb-btn-danger-icon etb-remove-step-btn" title="Supprimer cette étape">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                            <span class="dashicons dashicons-arrow-down-alt2 etb-toggle-icon" style="color: #64748b;"></span>
                                        </div>
                                    </div>
                                    <div class="etb-itinerary-body" style="display: none;">
                                        <div class="etb-engine-grid-2" style="margin-bottom: 12px;">
                                            <div>
                                                <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Horaire ou Repère :</label>
                                                <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][timeline_time][<?php echo esc_attr( $step_key ); ?>]" value="<?php echo esc_attr( $step['time'] ?? '' ); ?>" placeholder="Ex: 09:00 ou Day 1" class="widefat">
                                            </div>
                                            <div>
                                                <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Titre de l'étape * :</label>
                                                <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][timeline_title][<?php echo esc_attr( $step_key ); ?>]" value="<?php echo esc_attr( $step['title'] ?? '' ); ?>" placeholder="Ex: Prise en charge à l'hôtel" class="widefat etb-step-title-input">
                                            </div>
                                        </div>
                                        <div style="margin-bottom: 16px;">
                                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Paragraphe d'introduction de l'étape :</label>
                                            <textarea name="co_options[<?php echo esc_attr( $opt_id ); ?>][timeline_desc][<?php echo esc_attr( $step_key ); ?>]" rows="3" class="widefat" placeholder="Détaillez le début de cette étape..."><?php echo esc_textarea( $step['desc'] ?? '' ); ?></textarea>
                                        </div>

                                        <!-- SOUS-BLOCS : SOUS-TITRE + PARAGRAPHE -->
                                        <div class="etb-subblocks-wrapper" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-top: 14px;">
                                            <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 10px;">
                                                Sous-sections détaillées (Sous-titre + Texte) :
                                            </label>
                                            <div class="etb-subblocks-list" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px;">
                                                <?php if ( ! empty( $subblocks ) ) : foreach ( $subblocks as $sub ) : ?>
                                                    <div class="etb-subblock-item" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; position: relative;">
                                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                                            <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][timeline_sub_title][<?php echo esc_attr( $step_key ); ?>][]" value="<?php echo esc_attr( $sub['title'] ?? '' ); ?>" placeholder="Sous-titre (ex: La Rue Saint-Antoine)" class="widefat" style="font-weight: 600;">
                                                            <button type="button" class="etb-btn-danger-icon etb-remove-subblock-btn" style="margin-left: 8px; flex-shrink: 0;" title="Supprimer ce sous-titre">
                                                                <span class="dashicons dashicons-trash"></span>
                                                            </button>
                                                        </div>
                                                        <textarea name="co_options[<?php echo esc_attr( $opt_id ); ?>][timeline_sub_desc][<?php echo esc_attr( $step_key ); ?>][]" rows="2" class="widefat" placeholder="Paragraphe correspondant..."><?php echo esc_textarea( $sub['desc'] ?? '' ); ?></textarea>
                                                    </div>
                                                <?php endforeach; endif; ?>
                                            </div>
                                            <button type="button" class="button button-small etb-add-subblock-btn" data-optid="<?php echo esc_attr( $opt_id ); ?>" data-stepkey="<?php echo esc_attr( $step_key ); ?>">
                                                + Ajouter un sous-titre & paragraphe
                                            </button>
                                        </div>

                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <button type="button" class="etb-btn-secondary etb-add-itinerary-step-btn" data-list="etb-itinerary-list-<?php echo esc_attr( $opt_id ); ?>" data-optid="<?php echo esc_attr( $opt_id ); ?>">+ Add Itinerary Step</button>
                    </div>

                    <!-- ONGLET : MAP & GALLERY (DESIGN ÉPURÉ WP TRAVEL ENGINE) -->
                    <div class="etb-engine-pane" id="engine-mapgallery-<?php echo esc_attr( $opt_id ); ?>">
                        <h3 class="etb-engine-pane-title">Map & Gallery</h3>

                        <!-- Sous-onglets horizontaux (Gallery | Map) -->
                        <div class="etb-subtabs-bar">
                            <button type="button" class="etb-subtab-btn active" data-subpane="subpane-gallery-<?php echo esc_attr( $opt_id ); ?>">Gallery</button>
                            <button type="button" class="etb-subtab-btn" data-subpane="subpane-map-<?php echo esc_attr( $opt_id ); ?>">Map</button>
                        </div>

                        <!-- SOUS-PANNEAU 1 : GALLERY (CAPTURE 2) -->
                        <div class="etb-subpane active" id="subpane-gallery-<?php echo esc_attr( $opt_id ); ?>">
                            
                            <!-- Toggle switch iOS "Enable Image Gallery" -->
                            <div class="etb-ios-toggle-row">
                                <span class="etb-ios-toggle-title">Enable Image Gallery</span>
                                <label class="etb-ios-switch">
                                    <input type="checkbox" name="co_options[<?php echo esc_attr( $opt_id ); ?>][gallery_enabled]" value="1" <?php checked( $data['gallery_enabled'] ?? '1', '1' ); ?>>
                                    <span class="etb-ios-slider"></span>
                                </label>
                            </div>

                            <!-- Champ caché stockant les IDs -->
                            <?php 
                            $raw_gallery_ids = $data['gallery_image_ids'] ?? '';
                            $gallery_ids = array_filter( array_map( 'absint', explode( ',', (string) $raw_gallery_ids ) ) );
                            ?>
                            <input type="hidden" name="co_options[<?php echo esc_attr( $opt_id ); ?>][gallery_image_ids]" id="etb-gallery-ids-<?php echo esc_attr( $opt_id ); ?>" value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>">
                            <input type="hidden" name="co_options[<?php echo esc_attr( $opt_id ); ?>][gallery_layout]" value="<?php echo esc_attr( $data['gallery_layout'] ?? 'mosaic_4' ); ?>">

                            <!-- Zone de dépôt pointillée bleue avec bouton contour bleu (Capture 2) -->
                            <div class="etb-gallery-dropzone">
                                <button type="button" class="etb-btn-outline-blue etb-add-gallery-images-btn" data-optid="<?php echo esc_attr( $opt_id ); ?>">
                                    <span class="dashicons dashicons-plus-alt2" style="font-size: 15px; width: 15px; height: 15px;"></span> Add Image
                                </button>
                                <div class="etb-dropzone-hint">
                                    Max. file size 5MB Supports: JPG,PNG,WebP images
                                </div>
                            </div>

                            <!-- Rangée des miniatures épurées (Capture 2) -->
                            <div class="etb-gallery-thumbs-row etb-gallery-thumbs-grid" id="etb-gallery-thumbs-<?php echo esc_attr( $opt_id ); ?>">
                                <?php foreach ( $gallery_ids as $img_id ) : 
                                    $thumb_url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
                                    if ( ! $thumb_url ) continue;
                                ?>
                                    <div class="etb-gallery-thumb-card etb-gallery-thumb-item" data-id="<?php echo esc_attr( $img_id ); ?>">
                                        <span class="etb-thumb-handle dashicons dashicons-menu" title="Déplacer"></span>
                                        <img src="<?php echo esc_url( $thumb_url ); ?>">
                                        <button type="button" class="etb-remove-gallery-img-btn" title="Supprimer">&times;</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- SOUS-PANNEAU 2 : MAP (CAPTURE 3) -->
                        <div class="etb-subpane" id="subpane-map-<?php echo esc_attr( $opt_id ); ?>" style="display: none;">
                            <div class="etb-engine-row">
                                <label>Section Title :</label>
                                <input type="text" name="co_options[<?php echo esc_attr( $opt_id ); ?>][map_title]" value="<?php echo esc_attr( $data['map_title'] ?? 'Map' ); ?>" class="widefat" placeholder="Map">
                            </div>

                            <div class="etb-engine-row" style="margin-bottom: 0;">
                                <label>Map iframe Code :</label>
                                <textarea name="co_options[<?php echo esc_attr( $opt_id ); ?>][map_iframe]" rows="5" class="widefat" style="font-family: monospace; font-size: 12px;" placeholder='<iframe src="https://www.google.com/maps?output=embed&saddr=...&daddr=..." width="100%"></iframe>'><?php echo esc_textarea( $data['map_iframe'] ?? '' ); ?></textarea>
                            </div>
                        </div>

                    </div>

                    <!-- ONGLET 4 : INCLUS / EXCLUS -->
                    <div class="etb-engine-pane" id="engine-includes-<?php echo esc_attr( $opt_id ); ?>">
                        <h3 class="etb-engine-pane-title">Ce qui est inclus / Non inclus</h3>
                        <div class="etb-engine-grid-2">
                            <div class="etb-engine-row">
                                <label style="color: #16a34a;">✅ Inclus (1 élément par ligne) :</label>
                                <textarea name="co_options[<?php echo esc_attr( $opt_id ); ?>][inclusions]" rows="6" class="widefat" placeholder="Véhicule privé..."><?php echo esc_textarea( $inclusions ); ?></textarea>
                            </div>
                            <div class="etb-engine-row">
                                <label style="color: #dc2626;">❌ Non Inclus (1 élément par ligne) :</label>
                                <textarea name="co_options[<?php echo esc_attr( $opt_id ); ?>][exclusions]" rows="6" class="widefat" placeholder="Repas..."><?php echo esc_textarea( $exclusions ); ?></textarea>
                            </div>
                        </div>
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

                // Sauvegarde des champs « Autres circuits » (toujours effectuée, indépendamment des options)
        if ( isset( $_POST['etb_related_title'] ) ) {
            update_post_meta( $post_id, '_circuit_related_title', sanitize_text_field( $_POST['etb_related_title'] ) );
        }

        // Interrupteur d'affichage (checkbox non postée si décochée).
        update_post_meta(
            $post_id,
            '_circuit_related_enabled',
            isset( $_POST['etb_related_enabled'] ) ? '1' : '0'
        );
        if ( isset( $_POST['etb_related_subtitle'] ) ) {
            update_post_meta( $post_id, '_circuit_related_subtitle', sanitize_text_field( $_POST['etb_related_subtitle'] ) );
        }

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
                foreach ( $raw_data['timeline_time'] as $step_key => $time_val ) {
                    $t_time  = sanitize_text_field( $time_val );
                    $t_title = sanitize_text_field( $raw_data['timeline_title'][ $step_key ] ?? '' );
                    // Autorise la mise en forme riche sécurisée (Gras, liens, etc.)
                    $t_desc  = wp_kses_post( $raw_data['timeline_desc'][ $step_key ] ?? '' );

                    // Traitement des sous-sections (Sous-titre + Texte)
                    $subblocks = array();
                    if ( ! empty( $raw_data['timeline_sub_title'][ $step_key ] ) && is_array( $raw_data['timeline_sub_title'][ $step_key ] ) ) {
                        foreach ( $raw_data['timeline_sub_title'][ $step_key ] as $sub_idx => $sub_title_val ) {
                            $st_title = sanitize_text_field( $sub_title_val );
                            // Autorise la mise en forme riche sécurisée
                            $st_desc  = wp_kses_post( $raw_data['timeline_sub_desc'][ $step_key ][ $sub_idx ] ?? '' );
                            if ( ! empty( $st_title ) || ! empty( $st_desc ) ) {
                                $subblocks[] = array(
                                    'title' => $st_title,
                                    'desc'  => $st_desc,
                                );
                            }
                        }
                    }

                    if ( ! empty( $t_title ) || ! empty( $t_time ) ) {
                        $timeline[] = array(
                            'time'      => $t_time,
                            'title'     => $t_title,
                            'desc'      => $t_desc,
                            'subblocks' => $subblocks,
                        );
                    }
                }
            }

            // Nettoyage des Highlights
            $clean_highlights = array();
            if ( ! empty( $raw_data['highlights'] ) && is_array( $raw_data['highlights'] ) ) {
                foreach ( $raw_data['highlights'] as $hl ) {
                    if ( ! empty( trim( $hl ) ) ) {
                        $clean_highlights[] = sanitize_text_field( $hl );
                    }
                }
            }

            $overview_sections = array();
            if ( ! empty( $raw_data['overview_sec_title'] ) && is_array( $raw_data['overview_sec_title'] ) ) {
                foreach ( $raw_data['overview_sec_title'] as $idx => $sec_title ) {
                    $s_title = sanitize_text_field( $sec_title );
                    $s_desc  = wp_kses_post( $raw_data['overview_sec_desc'][ $idx ] ?? '' );
                    if ( ! empty( $s_title ) || ! empty( $s_desc ) ) {
                        $overview_sections[] = array( 'title' => $s_title, 'desc' => $s_desc );
                    }
                }
            }

            $clean_options[ $clean_id ] = array(
                'city_name'        => sanitize_text_field( $raw_data['city_name'] ?? '' ),
                'duration_hours'   => max( 0.25, floatval( $raw_data['duration_hours'] ?? 1.0 ) ),
                'departure_time'   => sanitize_text_field( $raw_data['departure_time'] ?? '09:00' ),
                'additional_price' => max( 0.0, floatval( $raw_data['additional_price'] ?? 0.0 ) ),
                
                // Nouveaux blocs de texte dynamiques (Colonne gauche)
                'overview_sections'=> $overview_sections,
                'highlights_title' => sanitize_text_field( $raw_data['highlights_title'] ?? 'Highlights' ),
                'highlights'       => $clean_highlights,

                // Trip Info (Caractéristiques clés)
                'trip_info_title'  => sanitize_text_field( $raw_data['trip_info_title'] ?? 'trip info' ),
                'trip_facts'       => (function() use ($raw_data) {
                    $facts = array();
                    if ( ! empty( $raw_data['trip_facts'] ) && is_array( $raw_data['trip_facts'] ) ) {
                        foreach ( $raw_data['trip_facts'] as $f ) {
                            $label = sanitize_text_field( $f['label'] ?? '' );
                            $value = sanitize_text_field( $f['value'] ?? '' );
                            $icon  = sanitize_text_field( $f['icon'] ?? 'fas fa-info-circle' );
                            $type  = sanitize_key( $f['type'] ?? 'custom' );
                            if ( ! empty( $label ) || ! empty( $value ) ) {
                                $facts[] = array(
                                    'type'  => $type,
                                    'icon'  => $icon,
                                    'label' => $label,
                                    'value' => $value,
                                );
                            }
                        }
                    }
                    return $facts;
                })(),

                // Overview - Mise en forme autorisée sur la description
                'overview_title'   => sanitize_text_field( $raw_data['overview_title'] ?? 'Aperçu du Circuit' ),
                'overview_desc'    => wp_kses_post( $raw_data['overview_desc'] ?? '' ),
                'highlights_title' => sanitize_text_field( $raw_data['highlights_title'] ?? 'Highlights' ),
                'highlights'       => $clean_highlights,

                // Map & Gallery
                'gallery_enabled'   => ! empty( $raw_data['gallery_enabled'] ) ? '1' : '0',
                'gallery_image_ids' => sanitize_text_field( $raw_data['gallery_image_ids'] ?? '' ),
                'gallery_layout'    => sanitize_key( $raw_data['gallery_layout'] ?? 'mosaic_4' ),
                'map_title'         => sanitize_text_field( $raw_data['map_title'] ?? 'Map' ),
                'map_iframe'        => (function() use ($raw_data) {
                    $raw_iframe = $raw_data['map_iframe'] ?? '';
                    // Autorise l'iframe de carte en toute sécurité
                    return wp_kses( $raw_iframe, array(
                        'iframe' => array(
                            'src'             => true,
                            'width'           => true,
                            'height'          => true,
                            'frameborder'     => true,
                            'style'           => true,
                            'allowfullscreen' => true,
                            'loading'         => true,
                            'referrerpolicy'  => true,
                        ),
                    ) );
                })(),
                
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
        $base_price    = get_post_meta( $post->ID, '_etb_base_price', true );
        $max_pax       = get_post_meta( $post->ID, '_etb_max_pax', true );
        $max_bag       = get_post_meta( $post->ID, '_etb_max_baggage', true );
        $selected_extras = get_post_meta( $post->ID, '_etb_allowed_extras', true ) ?: array();

        // Nouveaux champs pour la grille tarifaire avancée (Mise à disposition)
        $min_hours     = get_post_meta( $post->ID, '_etb_min_hours', true ) ?: '4';
        $hourly_rate   = get_post_meta( $post->ID, '_etb_hourly_rate', true );
        $pack_10h      = get_post_meta( $post->ID, '_etb_pack_10h', true );
        $sup_hour_rate = get_post_meta( $post->ID, '_etb_sup_hour_rate', true );
        $km_included   = get_post_meta( $post->ID, '_etb_km_included_ph', true ) ?: '35';
        $km_sup_rate   = get_post_meta( $post->ID, '_etb_km_sup_rate', true );

        $limo_class_id = get_post_meta( $post->ID, '_etb_limo_class_id', true );
        $extras = get_posts( array( 'post_type' => 'tour_extra', 'numberposts' => -1, 'post_status' => 'publish' ) );
        ?>
        <p>
            <label><strong>Tarif forfaitaire de base (€) (Transfert simple / Fallback) :</strong></label><br>
            <input type="number" step="0.01" min="0" name="etb_base_price" value="<?php echo esc_attr( $base_price ); ?>" class="widefat">
        </p>

        <!-- GRILLE DE TARIFICATION PAR PALIERS (MISE À DISPOSITION / À L'HEURE) -->
        <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 14px; margin: 15px 0;">
            <h4 style="margin: 0 0 10px 0; color: #0f172a; font-size: 13px; text-transform: uppercase;">
                ⏱️ Tarification Mise à disposition (À l'heure)
            </h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label>Durée minimale requise (h) :</label><br>
                    <input type="number" min="1" name="etb_min_hours" value="<?php echo esc_attr( $min_hours ); ?>" class="widefat" placeholder="Ex: 4 pour berlines, 10 pour Sprinter">
                </div>
                <div>
                    <label>Tarif horaire 1h (€/h) :</label><br>
                    <input type="number" step="0.01" min="0" name="etb_hourly_rate" value="<?php echo esc_attr( $hourly_rate ); ?>" class="widefat" placeholder="Ex: 127 (Laisser vide si pas de tarif court)">
                </div>
                <div>
                    <label>Forfait 10 Heures (€) :</label><br>
                    <input type="number" step="0.01" min="0" name="etb_pack_10h" value="<?php echo esc_attr( $pack_10h ); ?>" class="widefat" placeholder="Ex: 1250">
                </div>
                <div>
                    <label>Heure sup. au-delà de 10h (€/h) :</label><br>
                    <input type="number" step="0.01" min="0" name="etb_sup_hour_rate" value="<?php echo esc_attr( $sup_hour_rate ); ?>" class="widefat" placeholder="Ex: 125">
                </div>
                <div>
                    <label>Km inclus par heure :</label><br>
                    <input type="number" min="0" name="etb_km_included_ph" value="<?php echo esc_attr( $km_included ); ?>" class="widefat" placeholder="Ex: 35 ou 30">
                </div>
                <div>
                    <label>Tarif du Km supplémentaire (€/km) :</label><br>
                    <input type="number" step="0.01" min="0" name="etb_km_sup_rate" value="<?php echo esc_attr( $km_sup_rate ); ?>" class="widefat" placeholder="Ex: 3.50">
                </div>
            </div>
        </div>
        <p>
            <label>Nombre max passagers :</label><br>
            <input type="number" min="1" name="etb_max_pax" value="<?php echo esc_attr( $max_pax ); ?>" class="widefat">
        </p>
        
        <p>
                <label>Nombre max bagages :</label><br>
                <input type="number" min="0" name="etb_max_bag" value="<?php echo esc_attr( $max_bag ); ?>" class="widefat">
            </p>
            <p>
            <label><strong>🎬 GIF / Image animée au survol (Optionnel) :</strong></label><br>
            <input type="url" name="etb_hover_image" value="<?php echo esc_url( get_post_meta( $post->ID, '_etb_hover_image', true ) ); ?>" class="widefat" placeholder="https://.../mon-vehicule-anime.gif">
            <span class="description">Collez l'URL de votre fichier GIF animé (téléversé dans Médias WP).</span>
        </p>

        
            <?php 
            // Vérification de l'application de dispatch active
            $gen_settings      = get_option( 'etb_general_settings', array() );
            $active_dispatcher = $gen_settings['active_dispatcher'] ?? 'none';

            // Affichage UNIQUEMENT si LimoExpress est sélectionné dans les Réglages
            if ( 'limoexpress' === $active_dispatcher ) :
                $force_refresh = ! empty( $_GET['refresh_limo'] );
                $limo_classes  = class_exists( 'ETB_LimoExpress' ) ? ETB_LimoExpress::get_vehicle_classes( $force_refresh ) : array();
                $refresh_url   = add_query_arg( 'refresh_limo', '1' );
            ?>
                <hr style="margin: 15px 0;">
                <p>
                    <label><strong>🚙 Classe de Véhicule LimoExpress :</strong></label><br>
                    <?php if ( ! empty( $limo_classes ) ) : ?>
                        <select name="etb_limo_class_id" class="widefat" style="margin-top: 5px;">
                            <option value="">-- Aucune liaison LimoExpress --</option>
                            <?php foreach ( $limo_classes as $cls ) : ?>
                                <option value="<?php echo esc_attr( $cls['id'] ); ?>" <?php selected( $limo_class_id, $cls['id'] ); ?>>
                                    🚙 <?php echo esc_html( $cls['name'] ); ?> (<?php echo esc_html( substr( $cls['id'], 0, 8 ) ); ?>...)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span style="display: block; margin-top: 6px;">
                            <a href="<?php echo esc_url( $refresh_url ); ?>" class="button button-small" style="vertical-align: middle;">
                                🔄 Rafraîchir la liste LimoExpress
                            </a>
                            <span class="description" style="color: #166534; margin-left: 6px;">Classes synchronisées en temps réel.</span>
                        </span>
                    <?php else : ?>
                        <input type="text" name="etb_limo_class_id" value="<?php echo esc_attr( $limo_class_id ); ?>" class="widefat" placeholder="Ex: 9c50c90c-ffaf-4523-b573-0177fea64541">
                        <span class="description">Saisissez le jeton API dans les Réglages pour charger automatiquement vos classes de véhicules.</span>
                    <?php endif; ?>
                </p>
                <hr style="margin: 15px 0;">
            <?php endif; ?>
            
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
        $type        = get_post_meta( $post->ID, '_etb_promo_type', true ) ?: 'percentage';
        $value       = get_post_meta( $post->ID, '_etb_promo_value', true );
        $active      = get_post_meta( $post->ID, '_etb_promo_active', true );
        if ( $active === '' ) $active = '1';

        // Nouveautés : Dates et Quotas
        $valid_from  = get_post_meta( $post->ID, '_etb_promo_valid_from', true );
        $valid_to    = get_post_meta( $post->ID, '_etb_promo_valid_to', true );
        $usage_limit = get_post_meta( $post->ID, '_etb_promo_usage_limit', true );
        $remaining   = get_post_meta( $post->ID, '_etb_promo_remaining', true );
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
        <hr style="margin: 15px 0;">
        
        <!-- Dates de validité -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px;">
            <div>
                <label><strong>📅 Valide à partir du :</strong></label><br>
                <input type="date" name="etb_promo_valid_from" value="<?php echo esc_attr( $valid_from ); ?>" class="widefat">
                <span class="description" style="font-size: 11px;">Laisser vide si actif immédiatement.</span>
            </div>
            <div>
                <label><strong>📅 Expire le (inclus) :</strong></label><br>
                <input type="date" name="etb_promo_valid_to" value="<?php echo esc_attr( $valid_to ); ?>" class="widefat">
                <span class="description" style="font-size: 11px;">Laisser vide pour aucune date de fin.</span>
            </div>
        </div>

        <!-- Quotas d'utilisations -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px;">
            <div>
                <label><strong>🔢 Nombre total d'utilisations :</strong></label><br>
                <input type="number" min="0" name="etb_promo_usage_limit" value="<?php echo esc_attr( $usage_limit ); ?>" class="widefat" placeholder="Illimité si vide">
                <span class="description" style="font-size: 11px;">Nombre total autorisé (ex: 30).</span>
            </div>
            <div>
                <label><strong>⏳ Utilisations restantes :</strong></label><br>
                <input type="number" min="0" name="etb_promo_remaining" value="<?php echo esc_attr( $remaining ); ?>" class="widefat" placeholder="Calculé auto">
                <span class="description" style="font-size: 11px;">Se décrémente à chaque commande.</span>
            </div>
        </div>

    
        
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

       // Récupération de l'état LimoExpress
        $limo_status = get_post_meta( $post->ID, '_etb_limo_status', true );
        $limo_id     = get_post_meta( $post->ID, '_etb_limo_booking_id', true );
        $limo_error  = get_post_meta( $post->ID, '_etb_limo_error', true );

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

        <?php 
        $invoice_url = wp_nonce_url( 
            admin_url( 'admin-post.php?action=etb_print_invoice&booking_id=' . $post->ID ), 
            'etb_print_invoice_' . $post->ID 
        );
        ?>
        <div class="etb-status-box" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <label for="etb_status"><strong>Statut de la réservation :</strong></label>
                <select name="etb_status" id="etb_status" style="margin-left: 10px; font-weight: bold;">
                    <option value="pending" <?php selected( $status, 'pending' ); ?>>⏳ En attente</option>
                    <option value="confirmed" <?php selected( $status, 'confirmed' ); ?>>✅ Confirmée</option>
                    <option value="completed" <?php selected( $status, 'completed' ); ?>>🏁 Terminée</option>
                    <option value="cancelled" <?php selected( $status, 'cancelled' ); ?>>❌ Annulée</option>
                </select>
            </div>
            <div>
                <a href="<?php echo esc_url( $invoice_url ); ?>" target="_blank" class="button button-primary" style="background: #0f172a; border-color: #0f172a; font-weight: 600;">
                    📄 Voir / Imprimer la Facture
                </a>
            </div>
        </div>

        <div class="etb-admin-grid">
            <div class="etb-admin-card">
                <h4>👤 Client</h4>
                <p><strong>Nom :</strong> <?php echo esc_html( $name ?: 'Non renseigné' ); ?></p>
                <p><strong>E-mail :</strong> <?php echo esc_html( $email ?: 'Non renseigné' ); ?></p>
                 <p><strong>Téléphone :</strong> <?php echo esc_html( get_post_meta( $post->ID, '_etb_customer_phone', true ) ?: 'Non renseigné' ); ?></p>
            </div>

            <div class="etb-admin-card">
                <h4>📍 Trajet & Passagers</h4>
                <p><strong>Prestation :</strong> <?php echo $circuit_option_id ? '<span style="background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px;">' . esc_html( $option_label ) . '</span>' : '<span style="color: #64748b;"><em>Transfert standard (1.0h)</em></span>'; ?></p>


                <div style="margin-bottom: 12px; padding: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px;">
                    <p style="margin: 0 0 8px 0;"><strong>LimoExpress :</strong> 
                        <span id="etb-limo-status-badge">
                            <?php if ( $limo_status === 'synced' ) : ?>
                                <span style="background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px;">✅ Synchronisé (Course #<?php echo esc_html( $limo_id ); ?>)</span>
                            <?php elseif ( $limo_status === 'failed' ) : ?>
                                <span style="background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px;">❌ Échec : <?php echo esc_html( $limo_error ?: 'Erreur de connexion' ); ?></span>
                            <?php else : ?>
                                <span style="color: #94a3b8;"><em>Non synchronisé / En attente</em></span>
                            <?php endif; ?>
                        </span>
                    </p>

                    <?php 
                    // Affichage du paiement Stripe sécurisé si existant
                    $stripe_pi = get_post_meta( $post->ID, '_etb_stripe_payment_intent_id', true );
                    if ( ! empty( $stripe_pi ) ) :
                        $gen_settings = get_option( 'etb_general_settings', array() );
                        $stripe_mode  = ( ! empty( $gen_settings['stripe_mode'] ) && 'live' === $gen_settings['stripe_mode'] ) ? 'live' : 'test';
                        $stripe_url   = ( 'live' === $stripe_mode ) 
                            ? 'https://dashboard.stripe.com/payments/' . esc_attr( $stripe_pi )
                            : 'https://dashboard.stripe.com/test/payments/' . esc_attr( $stripe_pi );
                    ?>
                        <p style="margin: 8px 0 8px 0; padding: 8px; background: #f8fafc; border-left: 3px solid #635bff; border-radius: 3px;">
                            <strong>💳 Transaction Stripe :</strong> <code style="font-size: 11px;"><?php echo esc_html( $stripe_pi ); ?></code><br>
                            <a href="<?php echo esc_url( $stripe_url ); ?>" target="_blank" class="button button-small" style="margin-top: 5px; background: #635bff; color: #fff; border-color: #635bff; font-weight: 600;">
                                🔗 Ouvrir dans le Dashboard Stripe
                            </a>
                        </p>
                    <?php endif; ?>

                    <!-- Bouton de transfert manuel avec bouclier anti-doublon -->
                    <div>
                        <?php 
                        $is_already_synced = ( 'synced' === $limo_status && ! empty( $limo_id ) );
                        $btn_label         = $is_already_synced ? '⚠️ Forcer un re-transfert LimoExpress' : '🔄 Transférer vers LimoExpress';
                        ?>
                        <button type="button" 
                                class="button button-secondary" 
                                id="etb-btn-resync-limo" 
                                data-post-id="<?php echo esc_attr( $post->ID ); ?>" 
                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'etb_resync_nonce_' . $post->ID ) ); ?>"
                                data-is-synced="<?php echo $is_already_synced ? '1' : '0'; ?>"
                                data-limo-id="<?php echo esc_attr( $limo_id ); ?>">
                            <?php echo esc_html( $btn_label ); ?>
                        </button>
                        <span id="etb-resync-spinner" class="spinner" style="float: none; margin: 0 5px; vertical-align: middle;"></span>
                        <span id="etb-resync-feedback" style="font-weight: 600; font-size: 12px;"></span>
                    </div>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    $('#etb-btn-resync-limo').on('click', function(e) {
                        e.preventDefault();
                        var $btn = $(this);
                        var isSynced = $btn.data('is-synced');
                        var limoId = $btn.data('limo-id');

                        // Bouclier Anti-Doublon : confirmation obligatoire si déjà synchronisé
                        if (isSynced == '1' || isSynced === 1) {
                            var confirmMsg = "⚠️ ATTENTION ANTI-DOUBLON :\n\nCette commande est DÉJÀ synchronisée dans LimoExpress (Course #" + limoId + ").\n\nVoulez-vous vraiment générer une DEUXIÈME course en doublon ?";
                            if (!confirm(confirmMsg)) {
                                return; // Annule immédiatement sans envoyer de requête
                            }
                        }

                        var postId = $btn.data('post-id');
                        var nonce = $btn.data('nonce');
                        var $spinner = $('#etb-resync-spinner');
                        var $feedback = $('#etb-resync-feedback');

                        $btn.prop('disabled', true);
                        $spinner.addClass('is-active');
                        $feedback.text('').css('color', '');

                        $.post(ajaxurl, {
                            action: 'etb_resync_booking',
                            booking_id: postId,
                            nonce: nonce
                        }, function(res) {
                            $btn.prop('disabled', false);
                            $spinner.removeClass('is-active');
                            if (res.success) {
                                $feedback.text('✓ Synchronisé avec succès (Course #' + res.data.limo_id + ')').css('color', '#15803d');
                                $('#etb-limo-status-badge').html('<span style="background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px;">✅ Synchronisé (Course #' + res.data.limo_id + ')</span>');
                                
                                // Mise à jour de l'état du bouton pour protéger les clics suivants
                                $btn.text('⚠️ Forcer un re-transfert LimoExpress').data('is-synced', '1').data('limo-id', res.data.limo_id);
                            } else {
                                $feedback.text('⚠ ' + (res.data.message || 'Erreur')).css('color', '#dc2626');
                                $('#etb-limo-status-badge').html('<span style="background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px;">❌ Échec : ' + (res.data.message || 'Erreur') + '</span>');
                            }
                        }).fail(function() {
                            $btn.prop('disabled', false);
                            $spinner.removeClass('is-active');
                            $feedback.text('⚠ Erreur réseau.').css('color', '#dc2626');
                        });
                    });
                });
                </script>
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
                <?php 
                $gen_settings = get_option( 'etb_general_settings', array() );
                $currency     = ! empty( $gen_settings['currency'] ) ? sanitize_text_field( $gen_settings['currency'] ) : '€';
                ?>
                <?php if ( $promo ) : ?>
                    <p><strong>Code Promo :</strong> <?php echo esc_html( $promo ); ?> (-<?php echo number_format_i18n( (float) $discount, 2 ); ?> <?php echo esc_html( $currency ); ?>)</p>
                <?php endif; ?>
                <p style="font-size: 16px; color: #1d2327;"><strong>Montant Total :</strong> <span style="color: #22c55e; font-weight: bold;"><?php echo number_format_i18n( (float) $total, 2 ); ?> <?php echo esc_html( $currency ); ?></span></p>
                
                <?php if ( $note ) : ?>
                    <hr>
                    <p><strong>Demande spéciale :</strong></p>
                    <p style="font-style: italic; background: #fff; padding: 8px; border: 1px solid #e2e8f0; border-radius: 3px;"><?php echo nl2br( esc_html( $note ) ); ?></p>
                <?php endif; ?>
            </div>
        </div>


         <?php 
        // ==========================================================================
        // NOUVEAUTÉ : AFFICHAGE DU DÉTAIL DU CIRCUIT (TIMELINE & INCLUSIONS)
        // ==========================================================================
        if ( ! empty( $circuit_option_id ) ) {
            $circuit_data = ETB_Pricing_Engine::find_circuit_option_data( $circuit_option_id, $circuit_id );
            
            if ( $circuit_data ) {
                $timeline   = $circuit_data['timeline'] ?? array();
                $inclusions = ! empty( $circuit_data['inclusions'] ) ? explode( "\n", trim( $circuit_data['inclusions'] ) ) : array();
                $exclusions = ! empty( $circuit_data['exclusions'] ) ? explode( "\n", trim( $circuit_data['exclusions'] ) ) : array();
                $badges     = array_filter( array( $circuit_data['badge_1'] ?? '', $circuit_data['badge_2'] ?? '', $circuit_data['badge_3'] ?? '', $circuit_data['badge_4'] ?? '' ) );

                // Calcul du décalage horaire pour la timeline
                $base_time   = $circuit_data['departure_time'] ?? '09:00';
                $booked_time = $time ?: $base_time;
                $delta_sec   = strtotime( $booked_time ) - strtotime( $base_time );
                ?>
                
                <div class="etb-admin-card" style="margin-top: 20px; border-top: 3px solid #0284c7;">
                    <h4 style="color: #0284c7; font-size: 16px;">🗺️ Programme & Détails du Circuit : <?php echo esc_html( $circuit_data['city_name'] ?? '' ); ?></h4>
                    
                    <!-- Badges -->
                    <?php if ( ! empty( $badges ) ) : ?>
                        <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php foreach ( $badges as $badge ) : ?>
                                <span style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 50px; font-size: 12px; color: #334155;">
                                    <?php echo esc_html( $badge ); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
                        <!-- Timeline -->
                        <div>
                            <h5 style="margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; color: #475569;">📍 Itinéraire (Heures adaptées)</h5>
                            <?php if ( ! empty( $timeline ) ) : ?>
                                <ul style="margin: 0; padding: 0; list-style: none; border-left: 2px dashed #cbd5e1; padding-left: 15px;">
                                    <?php foreach ( $timeline as $step ) : 
                                        $step_base_ts  = strtotime( $step['time'] ?? '09:00' );
                                        $adjusted_time = date( 'H:i', $step_base_ts + $delta_sec );
                                    ?>
                                        <li style="margin-bottom: 15px; position: relative;">
                                            <span style="position: absolute; left: -22px; top: 2px; width: 10px; height: 10px; background: #e65a15; border-radius: 50%; border: 2px solid #fff;"></span>
                                            <strong style="color: #e65a15; font-size: 13px;"><?php echo esc_html( $adjusted_time ); ?></strong> - 
                                            <strong style="color: #0f172a; font-size: 14px;"><?php echo esc_html( $step['title'] ?? '' ); ?></strong>
                                            <?php if ( ! empty( $step['desc'] ) ) : ?>
                                                <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;"><?php echo nl2br( esc_html( $step['desc'] ) ); ?></p>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p style="color: #64748b; font-style: italic;">Aucune étape définie.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Inclus / Non Inclus -->
                        <div>
                            <div style="margin-bottom: 20px;">
                                <h5 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #16a34a;">✅ Inclus</h5>
                                <?php if ( ! empty( $inclusions ) ) : ?>
                                    <ul style="margin: 0; padding: 0; list-style: none; font-size: 13px; color: #334155;">
                                        <?php foreach ( $inclusions as $item ) : if ( trim( $item ) ) : ?>
                                            <li style="margin-bottom: 5px;">✓ <?php echo esc_html( trim( $item ) ); ?></li>
                                        <?php endif; endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <span style="color: #94a3b8; font-style: italic;">Non spécifié</span>
                                <?php endif; ?>
                            </div>

                            <div>
                                <h5 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #dc2626;">❌ Non inclus</h5>
                                <?php if ( ! empty( $exclusions ) ) : ?>
                                    <ul style="margin: 0; padding: 0; list-style: none; font-size: 13px; color: #334155;">
                                        <?php foreach ( $exclusions as $item ) : if ( trim( $item ) ) : ?>
                                            <li style="margin-bottom: 5px;">✕ <?php echo esc_html( trim( $item ) ); ?></li>
                                        <?php endif; endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <span style="color: #94a3b8; font-style: italic;">Non spécifié</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        ?>


        <?php
    }

    public function save_etb_meta_data( $post_id ) {
        if ( ! isset( $_POST['etb_nonce'] ) || ! wp_verify_nonce( $_POST['etb_nonce'], 'etb_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $post_type = get_post_type( $post_id );

        switch ( $post_type ) {
            case 'tour_vehicle':
                $base_price    = isset( $_POST['etb_base_price'] ) ? max( 0, floatval( $_POST['etb_base_price'] ) ) : 0;
                $min_hours     = isset( $_POST['etb_min_hours'] ) ? max( 1, absint( $_POST['etb_min_hours'] ) ) : 4;
                $hourly_rate   = ( isset( $_POST['etb_hourly_rate'] ) && '' !== trim( $_POST['etb_hourly_rate'] ) ) ? max( 0, floatval( $_POST['etb_hourly_rate'] ) ) : '';
                $pack_10h      = ( isset( $_POST['etb_pack_10h'] ) && '' !== trim( $_POST['etb_pack_10h'] ) ) ? max( 0, floatval( $_POST['etb_pack_10h'] ) ) : '';
                $sup_hour_rate = ( isset( $_POST['etb_sup_hour_rate'] ) && '' !== trim( $_POST['etb_sup_hour_rate'] ) ) ? max( 0, floatval( $_POST['etb_sup_hour_rate'] ) ) : '';
                $km_included   = isset( $_POST['etb_km_included_ph'] ) ? max( 0, absint( $_POST['etb_km_included_ph'] ) ) : 35;
                $km_sup_rate   = ( isset( $_POST['etb_km_sup_rate'] ) && '' !== trim( $_POST['etb_km_sup_rate'] ) ) ? max( 0, floatval( $_POST['etb_km_sup_rate'] ) ) : '';

                update_post_meta( $post_id, '_etb_base_price', $base_price );
                update_post_meta( $post_id, '_etb_min_hours', $min_hours );
                update_post_meta( $post_id, '_etb_hourly_rate', $hourly_rate );
                update_post_meta( $post_id, '_etb_pack_10h', $pack_10h );
                update_post_meta( $post_id, '_etb_sup_hour_rate', $sup_hour_rate );
                update_post_meta( $post_id, '_etb_km_included_ph', $km_included );
                update_post_meta( $post_id, '_etb_km_sup_rate', $km_sup_rate );

                update_post_meta( $post_id, '_etb_max_pax', absint( $_POST['etb_max_pax'] ?? 0 ) );
                update_post_meta( $post_id, '_etb_max_baggage', absint( $_POST['etb_max_bag'] ?? 0 ) );
                update_post_meta( $post_id, '_etb_hover_image', esc_url_raw( $_POST['etb_hover_image'] ?? '' ) );

                // Sauvegarde de l'ID LimoExpress
                $limo_class_id = isset( $_POST['etb_limo_class_id'] ) ? sanitize_text_field( trim( $_POST['etb_limo_class_id'] ) ) : '';
                update_post_meta( $post_id, '_etb_limo_class_id', $limo_class_id );
                
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
                $p_value = floatval( $_POST['etb_promo_value'] ?? 0 );
                if ( $p_type === 'percentage' ) {
                    $p_value = max( 0, min( 100, $p_value ) );
                } else {
                    $p_value = max( 0, $p_value );
                }
                update_post_meta( $post_id, '_etb_promo_type', $p_type );
                update_post_meta( $post_id, '_etb_promo_value', $p_value );
                update_post_meta( $post_id, '_etb_promo_active', ( isset( $_POST['etb_promo_active'] ) && $_POST['etb_promo_active'] === '0' ) ? '0' : '1' );
               

                // Sauvegarde des dates de validité
                $valid_from = ! empty( $_POST['etb_promo_valid_from'] ) ? sanitize_text_field( $_POST['etb_promo_valid_from'] ) : '';
                $valid_to   = ! empty( $_POST['etb_promo_valid_to'] ) ? sanitize_text_field( $_POST['etb_promo_valid_to'] ) : '';
                update_post_meta( $post_id, '_etb_promo_valid_from', $valid_from );
                update_post_meta( $post_id, '_etb_promo_valid_to', $valid_to );

                // Sauvegarde et contrôle strict de cohérence des quotas
                $usage_limit = isset( $_POST['etb_promo_usage_limit'] ) && '' !== trim( $_POST['etb_promo_usage_limit'] ) ? absint( $_POST['etb_promo_usage_limit'] ) : '';
                $remaining   = isset( $_POST['etb_promo_remaining'] ) && '' !== trim( $_POST['etb_promo_remaining'] ) ? absint( $_POST['etb_promo_remaining'] ) : '';

                // Règle 1 : Si le total est 0, le restant est obligatoirement 0
                if ( 0 === $usage_limit ) {
                    $remaining = 0;
                }
                // Règle 2 : Si un total est défini (ex: 10) et que le restant est vide, initialisation au total
                elseif ( '' !== $usage_limit && '' === $remaining ) {
                    $remaining = $usage_limit;
                }
                // Règle 3 : Le restant ne peut JAMAIS être supérieur au total autorisé
                elseif ( '' !== $usage_limit && '' !== $remaining && $remaining > $usage_limit ) {
                    $remaining = $usage_limit;
                }

                update_post_meta( $post_id, '_etb_promo_usage_limit', $usage_limit );
                update_post_meta( $post_id, '_etb_promo_remaining', $remaining );
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
        $name  = get_post_meta( $booking_id, '_etb_customer_name', true ) ?: 'Client';
        $date  = get_post_meta( $booking_id, '_etb_booking_date', true );
        $time  = get_post_meta( $booking_id, '_etb_booking_time', true );

        if ( empty( $email ) || ! is_email( $email ) ) return;

        $general_settings = get_option( 'etb_general_settings', array() );
        $currency_symbol  = ! empty( $general_settings['currency'] ) ? sanitize_text_field( $general_settings['currency'] ) : '€';
        $company_name     = get_bloginfo( 'name' );
        $admin_email      = ! empty( $general_settings['admin_email'] ) && is_email( $general_settings['admin_email'] ) ? sanitize_email( $general_settings['admin_email'] ) : get_option( 'admin_email' );

        $total            = get_post_meta( $booking_id, '_etb_total_price', true );
        $formatted_total  = number_format_i18n( (float) $total, 2 ) . ' ' . $currency_symbol;

        // Formatage de la date en "03 Oct 2026"
        $date_ts          = strtotime( $date );
        $formatted_dt     = $date_ts ? date( 'd M Y', $date_ts ) : $date;
        $datetime_display = $formatted_dt . ( $time ? ' at ' . $time : '' );

        $subject = ''; 
        $status_title = ''; 
        $status_message = '';
        $badge_color = '#15803d';

        switch ( $new_status ) {
            case 'confirmed':
                $subject        = sprintf( '✅ Your Booking #%d is Confirmed — %s', $booking_id, $company_name );
                $status_title   = 'Reservation Confirmed';
                $status_message = 'Your booking has been approved and locked in dispatch. Your chauffeur is allocated for your mission.';
                $badge_color    = '#15803d';
                break;
            case 'cancelled':
                $subject        = sprintf( '❌ Your Booking #%d has been Cancelled — %s', $booking_id, $company_name );
                $status_title   = 'Reservation Cancelled';
                $status_message = 'Your booking request has been cancelled. If you believe this is an error, please contact our dispatch team.';
                $badge_color    = '#dc2626';
                break;
            case 'completed':
                $subject        = sprintf( '🏁 Your Ride #%d is Completed — %s', $booking_id, $company_name );
                $status_title   = 'Service Completed';
                $status_message = 'Your chauffeur mission is now complete. Thank you for choosing us for your private transportation.';
                $badge_color    = '#0284c7';
                break;
            default:
                return;
        }

        $headers = class_exists( 'ETB_Settings' ) 
            ? ETB_Settings::get_mail_headers( $admin_email, $company_name )
            : array( 'Content-Type: text/html; charset=UTF-8' );

        $message = '<div style="background-color: #f1f5f9; padding: 30px 15px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; line-height: 1.6; color: #1e293b;">'
            . '<div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">'
            . '<div style="background: #0f172a; padding: 25px 30px;">'
            . '<h1 style="margin: 0; color: #fbac18; font-size: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">' . esc_html( $company_name ) . '</h1>'
            . '<p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em;">Status Update — Dossier #' . $booking_id . '</p>'
            . '</div>'
            . '<div style="padding: 30px;">'
            . '<p style="font-size: 15px; margin-top: 0; margin-bottom: 16px;">Dear <strong>' . esc_html( $name ) . '</strong>,</p>'
            . '<div style="background: #f8fafc; border-left: 4px solid ' . $badge_color . '; padding: 14px 18px; border-radius: 6px; margin-bottom: 24px;">'
            . '<strong style="color: ' . $badge_color . '; font-size: 14px; display: block; margin-bottom: 4px;">' . esc_html( $status_title ) . '</strong>'
            . '<p style="margin: 0; font-size: 13.5px; color: #334155;">' . esc_html( $status_message ) . '</p>'
            . '</div>'
            . '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13.5px;">'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Booking Reference:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">#' . $booking_id . '</td></tr>'
            . '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 0; color: #64748b;">Date & Time:</td><td style="padding: 10px 0; text-align: right; font-weight: 700; color: #0f172a;">' . esc_html( $datetime_display ) . '</td></tr>'
            . '<tr style="border-bottom: 2px solid #0f172a;"><td style="padding: 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Total Fare:</td><td style="padding: 12px 0; text-align: right; font-size: 18px; font-weight: 800; color: #e65a15;">' . $formatted_total . '</td></tr>'
            . '</table>'
            . '<div style="margin-top: 25px; padding: 14px 16px; background: #f8fafc; border-radius: 6px; font-size: 12.5px; color: #64748b; text-align: center;">'
            . 'If you have any questions, feel free to reply directly to this email.'
            . '</div>'
            . '</div>'
            . '<div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 11.5px; color: #64748b;">'
            . '<p style="margin: 0;">' . esc_html( $company_name ) . ' — VIP Chauffeur & Private Transfers</p>'
            . '</div>'
            . '</div>'
            . '</div>';

        wp_mail( $email, $subject, $message, $headers );
    }

    /**
     * Affiche la vue imprimable de la facture
     */
    public function handle_print_invoice() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( 'Accès refusé.' );
        }
        $booking_id = absint( $_GET['booking_id'] ?? 0 );
        check_admin_referer( 'etb_print_invoice_' . $booking_id );

        $invoice_file = ETB_PATH . 'templates/invoice-print.php';
        if ( file_exists( $invoice_file ) ) {
            include $invoice_file;
            exit;
        } else {
            wp_die( 'Fichier de facture introuvable.' );
        }
    }
}