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

    // 5. Ajouter une étape dans le Programme (Timeline)
    $(document).on('click', '.co-btn-add-step', function (e) {
        e.preventDefault();
        const paneId = $(this).data('pane');
        const tableBody = $('#' + paneId).find('.co-timeline-table tbody');

        const stepRowHtml = `
            <tr>
                <td style="width: 100px;">
                    <input type="text" name="co_options[${paneId}][timeline_time][]" placeholder="09:00" class="widefat">
                </td>
                <td style="width: 200px;">
                    <input type="text" name="co_options[${paneId}][timeline_title][]" placeholder="Titre étape" class="widefat">
                </td>
                <td>
                    <textarea name="co_options[${paneId}][timeline_desc][]" rows="2" placeholder="Description courte..." class="widefat"></textarea>
                </td>
                <td style="width: 50px; text-align: center;">
                    <button type="button" class="co-btn-danger co-remove-step-btn">&times;</button>
                </td>
            </tr>
        `;

        tableBody.append(stepRowHtml);
    });

    // 6. Supprimer une étape de la Timeline
    $(document).on('click', '.co-remove-step-btn', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });
});