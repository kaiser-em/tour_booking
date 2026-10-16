jQuery(document).ready(function ($) {
    'use strict';

    // 1. Basculement d'onglets
    $(document).on('click', '.co-tab-btn', function (e) {
        e.preventDefault();
        const tabId = $(this).data('tab');

        $('.co-tab-btn').removeClass('active');
        $(this).addClass('active');

        $('.co-pane').removeClass('active');
        $('#' + tabId).addClass('active');
    });

    // 2. Synchronisation du titre de l'onglet avec la saisie de la ville
    $(document).on('input', '.co-city-input', function () {
        const paneId = $(this).closest('.co-pane').attr('id');
        const cityName = $(this).val().trim() || 'Option sans nom';
        $('.co-tab-btn[data-tab="' + paneId + '"] .co-tab-title').text(cityName);
    });

    // 3. Ajouter une nouvelle Option de Départ
    $('.co-add-tab-btn').on('click', function (e) {
        e.preventDefault();
        const uniqueId = 'opt_' + Math.random().toString(36).substr(2, 9);
        const tabCount = $('.co-tab-btn').length + 1;
        const defaultTitle = 'Option ' + tabCount;

        // Bouton onglet
        const tabBtnHtml = `
            <button type="button" class="co-tab-btn" data-tab="${uniqueId}">
                <span class="dashicons dashicons-location"></span>
                <span class="co-tab-title">${defaultTitle}</span>
            </button>
        `;
        $(this).before(tabBtnHtml);

        // Contenu du pane (clonage du template modèle)
        let paneTemplate = $('#co-pane-template').html();
        paneTemplate = paneTemplate.replace(/{{INDEX}}/g, uniqueId);
        paneTemplate = paneTemplate.replace(/{{DEFAULT_TITLE}}/g, defaultTitle);

        $('.co-tab-panes').append(paneTemplate);

        // Activer le nouvel onglet
        $('.co-tab-btn[data-tab="' + uniqueId + '"]').trigger('click');
    });

    // 4. Supprimer une Option de Départ
    $(document).on('click', '.co-delete-option-btn', function (e) {
        e.preventDefault();
        if ($('.co-tab-btn').length <= 1) {
            alert('Vous devez conserver au moins une option de départ pour ce circuit.');
            return;
        }

        if (confirm('Voulez-vous vraiment supprimer cette option de départ et tout son contenu ?')) {
            const pane = $(this).closest('.co-pane');
            const paneId = pane.attr('id');

            $('.co-tab-btn[data-tab="' + paneId + '"]').remove();
            pane.remove();

            // Activer le premier onglet restant
            $('.co-tab-btn').first().trigger('click');
        }
    });

    // ==========================================================================
    // NOUVELLE INTERFACE : METABOX WP TRAVEL ENGINE STYLE
    // ==========================================================================

    // 5. Navigation par onglets verticaux internes
    $(document).on('click', '.etb-engine-tab-btn', function (e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        var engineContainer = $(this).closest('.etb-engine-container');
        
        engineContainer.find('.etb-engine-tab-btn').removeClass('active');
        $(this).addClass('active');

        engineContainer.find('.etb-engine-pane').removeClass('active');
        $('#' + targetId).addClass('active');
    });

    // 6. Highlights (Points forts) - Ajouter
    $(document).on('click', '.etb-add-highlight-btn', function (e) {
        e.preventDefault();
        var listId = $(this).data('list');
        var optId  = $(this).data('optid');
        var html = `
            <div class="etb-highlight-item">
                <span class="dashicons dashicons-yes-alt" style="color: #22c55e;"></span>
                <input type="text" name="co_options[${optId}][highlights][]" value="" class="widefat" placeholder="Ex: Véhicule climatisé avec chauffeur privé">
                <button type="button" class="etb-btn-danger-icon etb-remove-highlight-btn" title="Supprimer">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        `;
        $('#' + listId).append(html);
    });

    // 7. Highlights - Supprimer
    $(document).on('click', '.etb-remove-highlight-btn', function (e) {
        e.preventDefault();
        $(this).closest('.etb-highlight-item').remove();
    });

    // 8. Itinéraire / Accordéon - Ouvrir / Fermer
    $(document).on('click', '.etb-itinerary-header', function (e) {
        if ($(e.target).closest('.etb-btn-danger-icon').length) return; // Ignore si on clique sur Supprimer
        
        var $body = $(this).next('.etb-itinerary-body');
        var $icon = $(this).find('.etb-toggle-icon');
        
        $body.slideToggle(200);
        $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    // 9. Itinéraire - Maj dynamique du titre de l'étape
    $(document).on('input', '.etb-step-title-input', function () {
        var val = $(this).val().trim();
        var $titleSpan = $(this).closest('.etb-itinerary-step').find('.etb-step-header-text');
        $titleSpan.text(val ? val : 'Nouvelle étape');
    });

    // 10. Itinéraire - Ajouter une étape avec conteneur de sous-blocs
    $(document).on('click', '.etb-add-itinerary-step-btn', function (e) {
        e.preventDefault();
        var listId    = $(this).data('list');
        var optId     = $(this).data('optid');
        var stepCount = $('#' + listId).children('.etb-itinerary-step').length + 1;
        var stepKey   = 'step_' + Date.now();
        var defaultTitle = 'Étape ' + stepCount;

        var html = `
            <div class="etb-itinerary-step" data-stepkey="${stepKey}">
                <div class="etb-itinerary-header">
                    <div class="etb-itinerary-title-wrap">
                        <span class="dashicons dashicons-menu" style="color: #94a3b8; cursor: grab;"></span>
                        <span class="etb-step-header-text">${defaultTitle}</span>
                    </div>
                    <div class="etb-itinerary-actions">
                        <button type="button" class="etb-btn-danger-icon etb-remove-step-btn" title="Supprimer cette étape">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                        <span class="dashicons dashicons-arrow-up-alt2 etb-toggle-icon" style="color: #64748b;"></span>
                    </div>
                </div>
                <div class="etb-itinerary-body">
                    <div class="etb-engine-grid-2" style="margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Horaire ou Repère :</label>
                            <input type="text" name="co_options[${optId}][timeline_time][${stepKey}]" value="" placeholder="Ex: 09:00 ou Jour 1" class="widefat">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Titre de l'étape * :</label>
                            <input type="text" name="co_options[${optId}][timeline_title][${stepKey}]" value="" placeholder="Ex: Prise en charge à l'hôtel" class="widefat etb-step-title-input">
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Paragraphe d'introduction de l'étape :</label>
                        <textarea name="co_options[${optId}][timeline_desc][${stepKey}]" rows="3" class="widefat" placeholder="Détaillez le début de cette étape..."></textarea>
                    </div>

                    <!-- SOUS-BLOCS : SOUS-TITRE + PARAGRAPHE -->
                    <div class="etb-subblocks-wrapper" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-top: 14px;">
                        <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 10px;">
                            Sous-sections détaillées (Sous-titre + Texte) :
                        </label>
                        <div class="etb-subblocks-list" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px;"></div>
                        <button type="button" class="button button-small etb-add-subblock-btn" data-optid="${optId}" data-stepkey="${stepKey}">
                            + Ajouter un sous-titre & paragraphe
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#' + listId).append(html);
    });

    // 11. Itinéraire - Supprimer une étape
    $(document).on('click', '.etb-remove-step-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (confirm('Voulez-vous vraiment supprimer cette étape de l\'itinéraire ?')) {
            $(this).closest('.etb-itinerary-step').remove();
        }
    });

   // 12. NOUVEAU : Ajouter un sous-bloc (Sous-titre + Paragraphe)
    $(document).on('click', '.etb-add-subblock-btn', function (e) {
        e.preventDefault();
        var optId       = $(this).attr('data-optid') || $(this).closest('.co-pane').attr('id');
        var stepKey     = $(this).attr('data-stepkey') || $(this).closest('.etb-itinerary-step').attr('data-stepkey') || 'step_0';
        var $targetList = $(this).closest('.etb-subblocks-wrapper').find('.etb-subblocks-list');

        var subHtml = `
            <div class="etb-subblock-item" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <input type="text" name="co_options[${optId}][timeline_sub_title][${stepKey}][]" value="" placeholder="Sous-titre (ex: La Rue Saint-Antoine)" class="widefat" style="font-weight: 600;">
                    <button type="button" class="etb-btn-danger-icon etb-remove-subblock-btn" style="margin-left: 8px; flex-shrink: 0;" title="Supprimer ce sous-titre">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
                <textarea name="co_options[${optId}][timeline_sub_desc][${stepKey}][]" rows="2" class="widefat" placeholder="Paragraphe correspondant..."></textarea>
            </div>
        `;
        $targetList.append(subHtml);
    });

    // 13. NOUVEAU : Supprimer un sous-bloc
    $(document).on('click', '.etb-remove-subblock-btn', function (e) {
        e.preventDefault();
        $(this).closest('.etb-subblock-item').remove();
    });

    // ==========================================================================
    // MODULE PROFESSIONNEL : TRIP INFO (WP TRAVEL ENGINE STYLE)
    // ==========================================================================

    // Fonction helper pour générer les options du select d'icônes (Nom de classe pur)
    function getFaIconOptionsHtml(selectedIcon) {
        var icons = [
            'fas fa-hotel', 'fas fa-bus', 'fas fa-car-side', 'fas fa-van-shuttle',
            'fas fa-wifi', 'far fa-calendar-xmark', 'fas fa-language', 'fas fa-bottle-droplet',
            'fas fa-hands-praying', 'fas fa-user-tie', 'fas fa-bowl-food', 'fas fa-handshake-simple',
            'fab fa-cc-mastercard', 'fas fa-tag', 'fas fa-user-group', 'far fa-clock',
            'fas fa-cloud-sun', 'fas fa-hospital-user', 'fas fa-plane-arrival',
            'fas fa-suitcase-rolling', 'fas fa-shield-halved', 'fas fa-child', 'fas fa-star'
        ];

        var html = '';
        icons.forEach(function(val) {
            var isSel = (val === selectedIcon) ? ' selected' : '';
            html += `<option value="${val}"${isSel}>${val}</option>`;
        });
        return html;
    }

    // 14. Ajouter un Trip Fact depuis le sélecteur rapide (Add Facts)
    $(document).on('click', '.etb-add-fact-btn', function (e) {
        e.preventDefault();
        var optId     = $(this).attr('data-optid') || $(this).closest('.co-pane').attr('id');
        var $select   = $(this).siblings('.etb-tripinfo-type-select');
        var $selected = $select.find(':selected');

        var factType    = $selected.val() || 'custom';
        var factIcon    = $selected.attr('data-icon') || 'fas fa-info-circle';
        var factLabel   = $selected.attr('data-label') || 'Info';
        var factDefault = $selected.attr('data-default') || '';

        var $targetList = $('#etb-tripinfo-list-' + optId);
        var factIdx     = Date.now();
        var iconOptions = getFaIconOptionsHtml(factIcon);

        var factHtml = `
            <div class="etb-tripinfo-item" style="display: grid; grid-template-columns: 28px 160px 220px 1fr 34px; gap: 10px; align-items: center; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px;">
                <span class="dashicons dashicons-menu" style="color: #94a3b8; cursor: grab;"></span>
                <input type="hidden" name="co_options[${optId}][trip_facts][${factIdx}][type]" value="${factType}">
                
                <!-- Nom -->
                <input type="text" name="co_options[${optId}][trip_facts][${factIdx}][label]" value="${factLabel}" class="widefat" style="font-weight: 600;" placeholder="Name">
                
                <!-- Aperçu dynamique + Menu déroulant -->
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="etb-fa-preview" style="width: 36px; height: 36px; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; color: #ba6827; flex-shrink: 0;">
                        <i class="${factIcon}"></i>
                    </span>
                    <select name="co_options[${optId}][trip_facts][${factIdx}][icon]" class="widefat etb-fa-icon-select" style="font-size: 12px; height: 36px; font-family: monospace;">
                        ${iconOptions}
                    </select>
                </div>

                <!-- Valeur -->
                <input type="text" name="co_options[${optId}][trip_facts][${factIdx}][value]" value="${factDefault}" class="widefat" placeholder="Valeur (ex: Available, Yes...)">
                
                <!-- Bouton Supprimer -->
                <button type="button" class="etb-btn-danger-icon etb-remove-fact-btn" title="Supprimer">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        `;

        $targetList.append(factHtml);
    });

    // 15. Ajouter un Trip Fact sur-mesure (+ Add Custom Info)
    $(document).on('click', '.etb-add-custom-info-btn', function (e) {
        e.preventDefault();
        var optId       = $(this).attr('data-optid') || $(this).closest('.co-pane').attr('id');
        var $targetList = $('#etb-tripinfo-list-' + optId);
        var factIdx     = Date.now();
        var iconOptions = getFaIconOptionsHtml('fas fa-star');

        var customHtml = `
            <div class="etb-tripinfo-item" style="display: grid; grid-template-columns: 28px 160px 220px 1fr 34px; gap: 10px; align-items: center; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px;">
                <span class="dashicons dashicons-menu" style="color: #94a3b8; cursor: grab;"></span>
                <input type="hidden" name="co_options[${optId}][trip_facts][${factIdx}][type]" value="custom">
                
                <!-- Nom -->
                <input type="text" name="co_options[${optId}][trip_facts][${factIdx}][label]" value="" class="widefat" style="font-weight: 600;" placeholder="ex: Carburant inclus">
                
                <!-- Aperçu dynamique + Menu déroulant -->
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="etb-fa-preview" style="width: 36px; height: 36px; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; color: #ba6827; flex-shrink: 0;">
                        <i class="fas fa-star"></i>
                    </span>
                    <select name="co_options[${optId}][trip_facts][${factIdx}][icon]" class="widefat etb-fa-icon-select" style="font-size: 12px; height: 36px; font-family: monospace;">
                        ${iconOptions}
                    </select>
                </div>

                <!-- Valeur -->
                <input type="text" name="co_options[${optId}][trip_facts][${factIdx}][value]" value="" class="widefat" placeholder="ex: Oui">
                
                <!-- Bouton Supprimer -->
                <button type="button" class="etb-btn-danger-icon etb-remove-fact-btn" title="Supprimer">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        `;

        $targetList.append(customHtml);
    });

        // 15b. Trip Facts - Supprimer une ligne
    $(document).on('click', '.etb-remove-fact-btn', function (e) {
        e.preventDefault();
        $(this).closest('.etb-tripinfo-item').remove();
    });

    // 16. NOUVEAU : Changement d'icône en direct (Mise à jour instantanée du carré visuel)
    $(document).on('change', '.etb-fa-icon-select', function () {
        var selectedClass = $(this).val();
        $(this).closest('.etb-tripinfo-item').find('.etb-fa-preview i').attr('class', selectedClass);
    });

   // 16. NOUVEAU : Changement d'icône en direct (Mise à jour instantanée du carré visuel)
    $(document).on('change', '.etb-fa-icon-select', function () {
        var selectedClass = $(this).val();
        $(this).closest('.etb-tripinfo-item').find('.etb-fa-preview i').attr('class', selectedClass);
    });

    // ==========================================================================
    // 17. MINI-BARRE D'OUTILS DE FORMATAGE RICHE (B, I, LIEN, LISTE)
    // ==========================================================================

    function initRichTextToolbars() {
        var selectors = 'textarea[name*="[hero_desc]"], textarea[name*="[overview_desc]"], textarea[name*="[timeline_desc]"], textarea[name*="[timeline_sub_desc]"], .etb-rich-text';

        $(selectors).each(function () {
            var $ta = $(this);
            $ta.addClass('etb-rich-text');
            if ($ta.prev('.etb-editor-toolbar').length) return;

            var toolbarHtml = `
                <div class="etb-editor-toolbar">
                    <button type="button" class="etb-format-btn" data-action="bold" title="Gras (Bold)"><strong>B</strong></button>
                    <button type="button" class="etb-format-btn" data-action="italic" title="Italique (Italic)"><em>I</em></button>
                    <button type="button" class="etb-format-btn" data-action="link" title="Insérer un lien (Link)">🔗 Lien</button>
                    <button type="button" class="etb-format-btn" data-action="list" title="Liste à puces (List)">• Liste</button>
                </div>
            `;
            $ta.before(toolbarHtml);
        });
    }

    // Montage initial au chargement
    initRichTextToolbars();

    // Montage automatique lors du changement d'onglet ou de ville
    $(document).on('click', '.co-tab-btn, .etb-engine-tab-btn, .etb-add-itinerary-step-btn, .etb-add-subblock-btn', function () {
        setTimeout(initRichTextToolbars, 100);
    });

    // Clic sur un bouton de la barre d'outils
    $(document).on('click', '.etb-format-btn', function (e) {
        e.preventDefault();
        var action   = $(this).data('action');
        var $toolbar = $(this).closest('.etb-editor-toolbar');
        var textarea = $toolbar.next('textarea')[0];
        if (!textarea) return;

        var start = textarea.selectionStart;
        var end   = textarea.selectionEnd;
        var val   = textarea.value;
        var sel   = val.substring(start, end);
        var rep   = '';

        if (action === 'bold') {
            rep = '<strong>' + (sel || 'texte en gras') + '</strong>';
        } else if (action === 'italic') {
            rep = '<em>' + (sel || 'texte en italique') + '</em>';
        } else if (action === 'link') {
            var url = prompt('Entrez l\'URL du lien :', 'https://');
            if (url) {
                rep = '<a href="' + url + '" target="_blank">' + (sel || 'cliquez ici') + '</a>';
            } else {
                return;
            }
        } else if (action === 'list') {
            if (sel) {
                var lines = sel.split('\n');
                rep = '<ul>\n' + lines.map(function(l) { return '  <li>' + l.trim() + '</li>'; }).join('\n') + '\n</ul>';
            } else {
                rep = '<ul>\n  <li>Élément 1</li>\n  <li>Élément 2</li>\n</ul>';
            }
        }

        textarea.value = val.substring(0, start) + rep + val.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start, start + rep.length);
        $(textarea).trigger('input');
    });

    // ==========================================================================
    // 18. GESTION DE LA GALERIE MÉDIA & CARTE (WP TRAVEL ENGINE STYLE)
    // ==========================================================================

    // Bascule des sous-onglets Gallery | Map
    $(document).on('click', '.etb-subtab-btn', function (e) {
        e.preventDefault();
        var targetSubpane = $(this).data('subpane');
        var $parent = $(this).closest('.etb-engine-pane');

        $parent.find('.etb-subtab-btn').removeClass('active');
        $(this).addClass('active');

        $parent.find('.etb-subpane').hide().removeClass('active');
        $('#' + targetSubpane).fadeIn(150).addClass('active');
    });

    var etbMediaFrames = {};

    $(document).on('click', '.etb-add-gallery-images-btn', function (e) {
        e.preventDefault();
        var optId     = $(this).data('optid') || $(this).closest('.co-pane').attr('id');
        var $hidden   = $('#etb-gallery-ids-' + optId);
        var $thumbs   = $('#etb-gallery-thumbs-' + optId);

        if (etbMediaFrames[optId]) {
            etbMediaFrames[optId].open();
            return;
        }

        etbMediaFrames[optId] = wp.media({
            title: 'Sélectionner les photos de la galerie',
            button: { text: 'Ajouter à la galerie' },
            multiple: true,
            library: { type: 'image' }
        });

        etbMediaFrames[optId].on('select', function () {
            var selection = etbMediaFrames[optId].state().get('selection');
            var currentIds = $hidden.val() ? $hidden.val().split(',').filter(Boolean) : [];

            selection.each(function (attachment) {
                var imgData = attachment.toJSON();
                var imgId = String(imgData.id);

                if (currentIds.indexOf(imgId) === -1) {
                    currentIds.push(imgId);

                    var thumbUrl = (imgData.sizes && imgData.sizes.thumbnail) ? imgData.sizes.thumbnail.url : imgData.url;
                    var thumbHtml = `
                        <div class="etb-gallery-thumb-card etb-gallery-thumb-item" data-id="${imgId}">
                            <span class="etb-thumb-handle dashicons dashicons-menu" title="Déplacer"></span>
                            <img src="${thumbUrl}">
                            <button type="button" class="etb-remove-gallery-img-btn" title="Supprimer">&times;</button>
                        </div>
                    `;
                    $thumbs.append(thumbHtml);
                }
            });

            $hidden.val(currentIds.join(','));
        });

        etbMediaFrames[optId].open();
    });

    // Suppression d'une photo
    $(document).on('click', '.etb-remove-gallery-img-btn', function (e) {
        e.preventDefault();
        var $card   = $(this).closest('.etb-gallery-thumb-card');
        var imgId   = String($card.data('id'));
        var $thumbs = $card.closest('.etb-gallery-thumbs-grid');
        var optId   = $thumbs.attr('id').replace('etb-gallery-thumbs-', '');
        var $hidden = $('#etb-gallery-ids-' + optId);

        var currentIds = $hidden.val() ? $hidden.val().split(',').filter(Boolean) : [];
        currentIds = currentIds.filter(function (id) {
            return id !== imgId;
        });
        $hidden.val(currentIds.join(','));

        $card.fadeOut(200, function () {
            $(this).remove();
        });
    });

        // 18b. Galerie : réordonner les photos par glisser-déposer
    function etbInitGallerySortable($grid) {
        if (!$.fn.sortable || $grid.hasClass('ui-sortable')) { return; }
        $grid.sortable({
            items: '.etb-gallery-thumb-item',
            cancel: '.etb-remove-gallery-img-btn',
            tolerance: 'pointer',
            placeholder: 'etb-thumb-placeholder',
            forcePlaceholderSize: true,
            start: function (e, ui) {
                ui.placeholder.css({ border: '2px dashed #94a3b8', borderRadius: '8px', background: '#f8fafc' });
            },
            update: function () {
                var optId = $grid.attr('id').replace('etb-gallery-thumbs-', '');
                var ids = $grid.find('.etb-gallery-thumb-item').map(function () {
                    return String($(this).data('id'));
                }).get();
                $('#etb-gallery-ids-' + optId).val(ids.join(','));
            }
        });
    }
    $('.etb-gallery-thumbs-grid').each(function () { etbInitGallerySortable($(this)); });
    // Filet pour les circuits/options ajoutés dynamiquement dans l'éditeur
    $(document).on('mouseenter', '.etb-gallery-thumbs-grid', function () { etbInitGallerySortable($(this)); });


    // ==========================================================================
    // 19. GESTION DES BLOCS DE PRÉSENTATION (COLONNE GAUCHE)
    // ==========================================================================

    // Ajouter une section (Titre + Paragraphe)
    $(document).on('click', '.etb-add-overview-sec-btn', function (e) {
        e.preventDefault();
        var optId = $(this).data('optid');
        var $list = $('#etb-overview-sections-' + optId);
        
        var html = `
            <div class="etb-overview-section-item" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px; position: relative; margin-top: 14px;">
                <div style="display: flex; justify-content: space-between; gap: 10px; margin-bottom: 10px;">
                    <input type="text" name="co_options[${optId}][overview_sec_title][]" value="" class="widefat" placeholder="Titre (ex: Overview)" style="font-weight: 600; font-size: 14px;">
                    <button type="button" class="etb-btn-danger-icon etb-remove-overview-sec-btn" title="Supprimer ce bloc" style="flex-shrink: 0;"><span class="dashicons dashicons-trash"></span></button>
                </div>
                <textarea name="co_options[${optId}][overview_sec_desc][]" rows="4" class="widefat etb-rich-text" placeholder="Rédigez votre paragraphe..."></textarea>
            </div>
        `;
        
        $list.append(html);
        
        // Initialiser la barre d'outils (Gras, Italique...) sur ce nouveau champ
        if (typeof initRichTextToolbars === 'function') {
            initRichTextToolbars();
        }
    });

    // Supprimer une section
    $(document).on('click', '.etb-remove-overview-sec-btn', function (e) {
        e.preventDefault();
        $(this).closest('.etb-overview-section-item').remove();
    });
});