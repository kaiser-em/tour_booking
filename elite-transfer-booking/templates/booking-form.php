<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Variables préparées par le shortcode
$currency = $gen_settings['currency'] ?? '€';
$sec_token = class_exists( 'ETB_Security' ) ? ETB_Security::generate_timestamp_token() : array( 'time' => time(), 'token' => '' );
?>



<form class="etb-booking-widget" id="etb-booking-app" onsubmit="return false;">
    
    <!-- CHAMPS DE SÉCURITÉ & ANTI-SPAM (Honeypot + Timestamp signé) -->
    <div style="position: absolute !important; left: -9999px !important; top: -9999px !important; opacity: 0 !important; width: 0 !important; height: 0 !important; overflow: hidden !important;" aria-hidden="true">
        <input type="text" name="etb_hp_email" value="" tabindex="-1" autocomplete="off">
        <input type="hidden" name="etb_sec_time" value="<?php echo esc_attr( $sec_token['time'] ); ?>">
        <input type="hidden" name="etb_sec_token" value="<?php echo esc_attr( $sec_token['token'] ); ?>">
    </div>

    <!-- EN-TÊTE PRIX ET CAPACITÉ DU WIDGET (NOUVEAUTÉ V2.2) -->
    <div class="etb-sidebar-price-card" id="etb-sidebar-price-header">
        <span class="etb-sidebar-label">  </span>
        <div class="etb-sidebar-amount-row">
            <span class="etb-sidebar-currency"><?php echo esc_html( $currency ); ?></span>
            <span class="etb-sidebar-val" id="etb-sidebar-val">0</span>
        </div>
        <p class="etb-sidebar-pax-notice" id="etb-sidebar-pax-notice">
            passager maximum : <strong id="etb-sidebar-max-pax">0</strong>
        </p>
    </div>



    <!-- SECTION 2 : PASSAGERS -->
        <section class="etb-section">
        <h2 class="etb-section-title"><span class="dashicons dashicons-groups"></span> Nombre de passagers</h2>
         <!-- Message d'alerte si clic sur + sans véhicule -->
        <p class="etb-error-message" id="etb-vehicle-selection-error" style="display:none; margin-top: 0; margin-bottom: 16px;">
            ⚠ Veuillez sélectionner au moins un véhicule avant d'ajouter des passagers.
        </p>
        <div class="etb-pax-grid">
            <?php if ( $form_settings['show_adults'] !== '0' ) : ?>
                <div class="etb-pax-card">
                    <div class="etb-pax-info">
                        <strong>Adultes</strong>
                        <span>12+ ans</span>
                    </div>
                    <div class="etb-qty-control">
                        <button type="button" class="etb-qty-btn etb-minus" aria-label="Diminuer">-</button>
                        <input type="number" name="etb_adults" value="1" min="1" readonly>
                        <button type="button" class="etb-qty-btn etb-plus" aria-label="Augmenter">+</button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $form_settings['show_children'] !== '0' ) : ?>
                <div class="etb-pax-card">
                    <div class="etb-pax-info">
                        <strong>Enfants</strong>
                        <span>2-11 ans</span>
                    </div>
                    <div class="etb-qty-control">
                        <button type="button" class="etb-qty-btn etb-minus" aria-label="Diminuer">-</button>
                        <input type="number" name="etb_children" value="0" min="0" readonly>
                        <button type="button" class="etb-qty-btn etb-plus" aria-label="Augmenter">+</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <p class="etb-error-message" id="etb-pax-capacity-error" style="display:none;">
            ⚠ Le nombre de passagers dépasse la capacité des véhicules sélectionnés.
        </p>
    </section>

    <!-- SECTION 3 : ADRESSE DE PRISE EN CHARGE -->
    <?php if ( $form_settings['show_pickup'] !== '0' ) : ?>
    <section class="etb-section">
        <h2 class="etb-section-title"><span class="dashicons dashicons-location"></span> Adresse de prise en charge</h2>
        <div class="etb-field etb-field-full">
            <div class="etb-field-icon-wrapper">
                <span class="dashicons dashicons-location"></span>
                <input type="text" name="etb_pickup_address" id="etb-pickup-address" placeholder="Ex: Hôtel des Thermes, Antsirabe..." autocomplete="off">
            </div>
        </div>
        <p class="etb-error-message" id="etb-pickup-error" style="display:none;">
                ⚠ Veuillez renseigner l'adresse de prise en charge.
        </p>
     
        
        <!-- NOUVEAUTÉ ETB V2 : Drop-off (Lieu de dépose) -->
        <div class="etb-field etb-field-full etb-dropoff-toggle-wrapper">
            <label id='etb-dropoff-label' class="etb-toggle-dropoff-label" for="etb-diff-dropoff-cb">
                <input type="checkbox" id="etb-diff-dropoff-cb" class='hidden-input'>
                <span class="etb-custom-box">
                    <span class="dashicons dashicons-yes"></span>
                </span>
                <span class="etb-dropoff-label-text">Mon lieu de dépose est différent du lieu de prise en charge</span>
            </label>
        </div>
        
        <div class="etb-field etb-field-full" id="etb-dropoff-container" style="display: none;">
            <label>Lieu de dépose précis (Adresse ou Nom de l'hôtel) *</label>
            <div id="etb-dropoff-div" class="etb-field-icon-wrapper">
                
                <textarea name="etb_dropoff_info" id="etb-dropoff-info" rows="2" placeholder="Ex: Hôtel Colbert, Antananarivo..."></textarea>
            </div>
        </div>

    </section>
    <?php endif; ?>


   <!-- SECTION 4 : OPTIONS SUPPLÉMENTAIRES (D1 - Hybride Toggle/Qty) -->
    <?php if ( $form_settings['show_extras'] !== '0' ) : ?>
    <section class="etb-section">
        <h2 class="etb-section-title"><span class="dashicons dashicons-star-filled"></span> Options supplémentaires</h2>
        <div class="etb-grid-2">
            <?php foreach ( $extras as $extra ) : 
                $e_price = get_post_meta( $extra->ID, '_etb_price', true );
                $e_type  = get_post_meta( $extra->ID, '_etb_price_type', true ); // fixed ou per_quantity
                $e_icon  = get_post_meta( $extra->ID, '_etb_icon', true ) ?: 'dashicons-tag';
                
                // Affichage du prix "Offert" ou montant
                $price_display = ($e_price <= 0) ? 'Offert' : '+ ' . $e_price . ' ' . $currency;
            ?>
                <!-- On ajoute etb-extra-item et une classe de type pour le JS -->
               
                                <div class="etb-pax-card etb-extra-item <?php echo ($e_type === 'per_quantity') ? 'etb-type-qty' : 'etb-type-toggle'; ?>" 
                     data-id="<?php echo $extra->ID; ?>" 
                     data-price="<?php echo $e_price; ?>">

                    <div class="etb-option-row-top">
                        <span class="etb-icon-badge">
                            <span class="dashicons <?php echo esc_attr($e_icon); ?>"></span>
                        </span>
                        <strong><?php echo esc_html( $extra->post_title ); ?></strong>
                    </div>

                    <div class="etb-option-row-bottom">
                        <span class="etb-price-tag"><?php echo esc_html( $price_display ); ?></span>

                        <?php if ($e_type === 'per_quantity') : ?>
                            <!-- AFFICHAGE QUANTITÉ -->
                            <div class="etb-qty-control mini">
                                <button type="button" class="etb-qty-btn etb-minus">-</button>
                                <input type="number" name="etb_extra_<?php echo $extra->ID; ?>" value="0" min="0" readonly>
                                <button type="button" class="etb-qty-btn etb-plus">+</button>
                            </div>
                        <?php else : ?>
                            <!-- AFFICHAGE CLIC SIMPLE (CHECK) -->
                            <div class="etb-toggle-check">
                                <i class="dashicons dashicons-yes"></i>
                                <input type="hidden" name="etb_extra_<?php echo $extra->ID; ?>" value="0">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- SECTION 5 : INFORMATIONS CLIENT -->
       <section class="etb-section">
        <h2 class="etb-section-title"><span class="dashicons dashicons-admin-users"></span> Vos informations</h2>
        <div class="etb-grid-2">
            <?php if ( $form_settings['show_name'] !== '0' ) : ?>
                <div class="etb-field">
                    <label>Nom complet *</label>
                    <div class="etb-field-icon-wrapper">
                        <span class="dashicons dashicons-admin-users"></span>
                        <input type="text" name="etb_name" placeholder="Ex: Jean Dupont">
                    </div>
                    <p class="etb-error-message" id="etb-name-error" style="display:none;">
                        ⚠ Veuillez indiquer votre nom complet.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( $form_settings['show_email'] !== '0' ) : ?>
                <div class="etb-field">
                    <label>Email *</label>
                    <div class="etb-field-icon-wrapper">
                        <span class="dashicons dashicons-email"></span>
                        <input type="email" name="etb_email" placeholder="Ex: jean.dupont@email.com">
                    </div>
                    <p class="etb-error-message" id="etb-email-error" style="display:none;">
                        ⚠ Veuillez indiquer une adresse email valide.
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ( $form_settings['show_date'] !== '0' ) : ?>
                <div id="etb-time-field" class="etb-field">
                    <label>Date souhaitée *</label>
                    <div class="etb-field-icon-wrapper">
                        
                        <input id="etb-time-field" type="date" name="etb_date">
                    </div>
                    <p class="etb-error-message" id="etb-date-error" style="display:none;">
                        ⚠ Veuillez sélectionner une date.
                    </p>
                </div>
            <?php endif; ?>
            <?php if ( $form_settings['show_time'] !== '0' ) : ?>
                <div  class="etb-field">
                    <label>Heure de départ *</label>
                        <div class="etb-field-icon-wrapper">
                        
                            <input id="etb-time-field" type="time" name="etb_time">
                        </div>
                        <p class="etb-error-message" id="etb-time-error" style="display:none;">
                            ⚠ Veuillez sélectionner une heure de départ.
                        </p>
                </div>
            <?php endif; ?>

            <?php if ( $form_settings['show_total_bag'] !== '0' ) : ?>
                <div class="etb-field">
                    <label>Bagages total *</label>
                    <div class="etb-field-icon-wrapper">
                        <span class="dashicons dashicons-portfolio"></span>
                        <input type="number" name="etb_total_luggage" value="0" min='0'>
                    </div>
                    <p class="etb-error-message" id="etb-baggage-capacity-error" style="display:none;">
                        ⚠ Le nombre de bagages dépasse la capacité des véhicules sélectionnés.
                    </p>
                </div>
            <?php endif; ?>
            <?php if ( $form_settings['show_promo'] !== '0' ) : ?>
            <div class="etb-field etb-field-full">
                <label>Code promo</label>
                <div class="etb-field-icon-wrapper etb-promo-wrapper">
                    <span class="dashicons dashicons-tag"></span>
                    <input type="text" name="etb_promo" id="etb-promo-input" placeholder="Ex: WELCOME10">
                    <button type="button" id="etb-apply-promo-btn" class="etb-btn-secondary">Appliquer</button>
                </div>
                <div id="etb-promo-message" class="etb-field-feedback" style="display:none;"></div>
            </div>
        <?php endif; ?>
            <?php if ( $form_settings['show_note'] !== '0' ) : ?>
                <div class="etb-field etb-field-full">
                    <label>Demande spéciale ou note</label>
                    <textarea name="etb_note" rows="3" placeholder="Notes pour votre trajet..."></textarea>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- MESSAGE D'ALERTE GLOBAL AVANT RÉCAPITULATIF (NOUVEAUTÉ V2) -->
    <div class="etb-error-message etb-global-error" id="etb-global-submit-error" style="display:none; margin-bottom: 18px; font-size: 13px; line-height: 1.4;">
        ⚠ Veuillez compléter ou corriger les informations requises ci-dessus avant de pouvoir réserver.
    </div>

    

    <!-- SECTION 6 : RÉCAPITULATIF SOMBRE -->
    <div class="etb-summary-card">
        <h2 id="etb-summary-title" class="etb-section-title" style="color: white; border: none;">Récapitulatif</h2>
        <div id="etb-summary-text">
            <!-- Rempli dynamiquement par JS -->
            <p style="opacity: 0.7;">Sélectionnez vos options pour voir le détail.</p>
        </div>
        
        <div class="etb-summary-total" id='etb-total'>
            <span>TOTAL ESTIMÉ</span>
            <div class="etb-total-amount"><span id="etb-total-val">0</span> <?php echo esc_html( $currency ); ?></div>
        </div>

        <button type="button" class="etb-submit-button">
            Réserver maintenant
        </button>
    </div>

</form>