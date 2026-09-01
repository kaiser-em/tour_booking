document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const root = document.querySelector('#co-circuit-app');
    if (!root) return;

    const tabButtons = root.querySelectorAll('.co-pub-tab-btn');
    const panes = root.querySelectorAll('.co-option-pane');

    // Fonction de synchronisation avec le formulaire ETB
    const syncWithETB = (btn) => {
        const optionId        = btn.dataset.target;
        const duration        = parseFloat(btn.dataset.duration) || 1;
        const additionalPrice = parseFloat(btn.dataset.price) || 0;
        const departureTime   = btn.dataset.time;
        const cityName        = btn.textContent.trim();

        const etbForm = document.querySelector('#etb-booking-app');
        if (etbForm) {

            // 1. Injecter l'ID de l'option dans le formulaire
            let optionInput = etbForm.querySelector('input[name="etb_option_id"]');
            if (!optionInput) {
                optionInput = document.createElement('input');
                optionInput.type = 'hidden';
                optionInput.name = 'etb_option_id';
                etbForm.appendChild(optionInput);
            }
            optionInput.value = optionId;
            
            // NOUVEAUTÉ : Injecter aussi l'ID unique du Circuit actuel
            const circuitId = root.dataset.circuitId || 0;
            let circuitInput = etbForm.querySelector('input[name="etb_circuit_id"]');
            if (!circuitInput) {
                circuitInput = document.createElement('input');
                circuitInput.type = 'hidden';
                circuitInput.name = 'etb_circuit_id';
                etbForm.appendChild(circuitInput);
            }
            circuitInput.value = circuitId;

            // 2. Pré-remplir l'heure de départ
            const timeInput = etbForm.querySelector('input[name="etb_time"]');
            if (timeInput && departureTime) {
                timeInput.value = departureTime;
                timeInput.dispatchEvent(new Event('input', { bubbles: true }));
                timeInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        // 3. Émettre l'événement en direct pour le moteur de calcul JS d'ETB
        window.dispatchEvent(new CustomEvent('etb:circuit_changed', {
            detail: {
                optionId: optionId,
                duration: duration,
                additionalPrice: additionalPrice,
                cityName: cityName
            }
        }));
    };

    // Écouteur sur chaque onglet de ville
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();

            tabButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            panes.forEach(pane => pane.classList.remove('active'));
            const targetPane = root.querySelector('#co-pane-' + this.dataset.target);
            if (targetPane) {
                targetPane.classList.add('active');
            }

            syncWithETB(this);
        });
    });

    // Synchronisation initiale après chargement complet
    const initialActiveBtn = root.querySelector('.co-pub-tab-btn.active');
    if (initialActiveBtn) {
        setTimeout(() => syncWithETB(initialActiveBtn), 150);
    }
});