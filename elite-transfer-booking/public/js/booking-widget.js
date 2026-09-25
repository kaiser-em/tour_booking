/**
 * check this
 * Elite Transfer Booking (Unified) - Frontend Engine
 * Ordre d'initialisation sécurisé (zéro erreur TDZ / ReferenceError)
 */



(function () {
    'use strict';

    const init = function () {
        // 1. Recherche du conteneur racine ETB
        const root = document.querySelector('#etb-booking-app');
        if (!root) return;

        // 2. Cache des éléments du DOM
        const circuitApp = document.querySelector('#co-circuit-app');
        const tabButtons = document.querySelectorAll('.co-pub-tab-btn');
        const panes = document.querySelectorAll('.co-option-pane');

        const vehicleInput = root.querySelector('#etb-vehicle-input');
        const pickupInput = root.querySelector('input[name="etb_pickup_address"]');
        const totalValEl = root.querySelector('#etb-total-val');
        const summaryTextEl = root.querySelector('#etb-summary-text');
        const paxCapacityErrorEl = root.querySelector('#etb-pax-capacity-error');
        const submitButton = root.querySelector('.etb-submit-button');
        const capacityDisplayEl = root.querySelector('#etb-capacity-display');
        const baggageCapacityErrorEl = root.querySelector('#etb-baggage-capacity-error');
        const nameInput = root.querySelector('input[name="etb_name"]');
        const emailInput = root.querySelector('input[name="etb_email"]');
        const dateInput = root.querySelector('input[name="etb_date"]');
        const timeInput = root.querySelector('input[name="etb_time"]');
        const nameErrorEl = root.querySelector('#etb-name-error');
        const emailErrorEl = root.querySelector('#etb-email-error');
        const dateErrorEl = root.querySelector('#etb-date-error');
        const timeErrorEl = root.querySelector('#etb-time-error');
        const vehicleSelectionErrorEl = root.querySelector('#etb-vehicle-selection-error');
        const pickupErrorEl = root.querySelector('#etb-pickup-error');
        const globalSubmitErrorEl = root.querySelector('#etb-global-submit-error') || document.querySelector('#etb-global-submit-error');

        // En-tête de prix latéral (Header Sidebar)
        const sidebarHeaderEl = document.querySelector('#etb-sidebar-price-header');
        const sidebarValEl = document.querySelector('#etb-sidebar-val');
        const sidebarMaxPaxEl = document.querySelector('#etb-sidebar-max-pax');

        // Éléments Drop-off
        const diffDropoffCb = root.querySelector('#etb-diff-dropoff-cb');
        const dropoffContainer = root.querySelector('#etb-dropoff-container');
        const dropoffInfo = root.querySelector('#etb-dropoff-info');

        // État interne global
        let state = {
            activeIndex: 0,
            pickup: { name: '', price: 0 },
            currency: (typeof etbAjax !== 'undefined' && etbAjax.currency) ? etbAjax.currency : '$',
            promo: { code: '', discount_type: 'fixed', discount_value: 0 },
            circuit: { duration: 1, additionalPrice: 0, optionId: '', cityName: '' }
        };

        let hasAttemptedSubmit = false;
        let hasSelectedVehicle = false;
        let hasRequiredFieldsMissing = false;

        // Date minimum : aujourd'hui (bloque les dates passées)
        const todayStr = new Date().toISOString().split('T')[0];
        if (dateInput) {
            dateInput.setAttribute('min', todayStr);
        }

        const parsePrice = (str) => {
            if (!str) return 0;
            const clean = str.replace(',', '.').replace(/[^-0-9.]/g, '');
            return parseFloat(clean) || 0;
        };

        // ------------------------------------------------------------------------
        // 3. FONCTIONS DE CALCUL ET MISES À JOUR (DÉCLARÉES EN PREMIER)
        // ------------------------------------------------------------------------

        

        // Moteur de calcul en direct (Récapitulatif & En-tête)
        const updateSummary = () => {
            let total = 0;
            let vehiclesHtml = '';
            let circuitPriceHtml = '';
            let totalCapacityPax = 0;
            let totalCapacityBaggage = 0;
            hasSelectedVehicle = false;

            const duration = state.circuit.duration || 1;
            const allCards = Array.from(document.querySelectorAll('.etb-vehicle-card'));

            // 1. Calcul des véhicules (Taux horaire × Durée × Quantité)
            allCards.forEach(card => {
                const qtyInput = card.querySelector('input[name^="etb_car_qty"]');
                const qty = parseInt(qtyInput ? qtyInput.value : 0);
                const price = parsePrice(card.querySelector('.etb-vehicle-price')?.textContent || '0');
                const name = card.querySelector('h3')?.textContent.trim() || 'Véhicule';
                const maxPax = parseInt(card.dataset.maxPax) || 0;
                const maxBaggage = parseInt(card.dataset.maxBaggage) || 0;

                // Met à jour la classe .etb-selected sur le DOM
                card.classList.toggle('etb-selected', qty > 0);

                if (qty > 0) {
                    hasSelectedVehicle = true;
                    const subtotal = (price * duration) * qty;
                    total += subtotal;
                    totalCapacityPax += maxPax * qty;
                    totalCapacityBaggage += maxBaggage * qty;
                    
                    const durationLabel = duration > 1 ? ` (${duration}h)` : '';
                    vehiclesHtml += `<div class="etb-summary-row"><strong>${qty}x ${name}${durationLabel}</strong><strong>${subtotal.toFixed(0)} ${state.currency}</strong></div>`;
                }
            });

            if (vehiclesHtml === "") {
                const activeCard = allCards[state.activeIndex];
                const name = activeCard ? activeCard.querySelector('h3')?.textContent.trim() : 'Véhicule';
                vehiclesHtml = `<div class="etb-summary-row"><strong>${name}</strong><strong>0 ${state.currency}</strong></div>`;
            }

            // 2. Supplément éventuel du circuit
            if (state.circuit.additionalPrice > 0) {
                total += state.circuit.additionalPrice;
                const cityLabel = state.circuit.cityName ? ` (${state.circuit.cityName})` : '';
                circuitPriceHtml = `<div class="etb-summary-row"><span>Supplément départ${cityLabel}</span><span>+ ${state.circuit.additionalPrice.toFixed(0)} ${state.currency}</span></div>`;
            }

            total += state.pickup.price;

            // Passagers
            const adults = parseInt(root.querySelector('input[name="etb_adults"]')?.value || 1);
            const children = parseInt(root.querySelector('input[name="etb_children"]')?.value || 0);

            // Validation de la capacité des passagers
            const totalPassengers = adults + children;
            const isPaxCapacityExceeded = totalPassengers > totalCapacityPax;

            if (capacityDisplayEl) {
                capacityDisplayEl.textContent = totalCapacityPax > 0 ? `👤 ${totalCapacityPax} passagers max` : '';
            }

            if (paxCapacityErrorEl) {
                paxCapacityErrorEl.style.display = (hasSelectedVehicle && isPaxCapacityExceeded) ? 'block' : 'none';
            }

            // Validation de la capacité des bagages
            const totalLuggage = parseInt(root.querySelector('input[name="etb_total_luggage"]')?.value || 0);
            const isBaggageCapacityExceeded = totalLuggage > totalCapacityBaggage;

            if (baggageCapacityErrorEl) {
                baggageCapacityErrorEl.style.display = (hasSelectedVehicle && isBaggageCapacityExceeded) ? 'block' : 'none';
            }

            // Validation des champs obligatoires
            const isNameMissing = !!nameInput && nameInput.value.trim() === '';
            if (nameErrorEl) nameErrorEl.style.display = (hasAttemptedSubmit && isNameMissing) ? 'block' : 'none';
            
            const isEmailMissing = !!emailInput && (!emailInput.validity.valid || emailInput.value.trim() === '');
            if (emailErrorEl) emailErrorEl.style.display = (hasAttemptedSubmit && isEmailMissing) ? 'block' : 'none';
            
            const isDateMissing = !!dateInput && dateInput.value.trim() === '';
            const isDatePast    = !!dateInput && dateInput.value !== '' && dateInput.value < todayStr;
            
            if (dateErrorEl) {
                if (hasAttemptedSubmit && isDateMissing) {
                    dateErrorEl.textContent = '⚠ Veuillez sélectionner une date.';
                    dateErrorEl.style.display = 'block';
                } else if (isDatePast) {
                    dateErrorEl.textContent = '⚠ La date ne peut pas être dans le passé.';
                    dateErrorEl.style.display = 'block';
                } else {
                    dateErrorEl.style.display = 'none';
                }
            }

            const isTimeMissing = !!timeInput && timeInput.value.trim() === '';
            if (timeErrorEl) timeErrorEl.style.display = (hasAttemptedSubmit && isTimeMissing) ? 'block' : 'none';
            
            const isVehicleMissing = !hasSelectedVehicle;
            if (vehicleSelectionErrorEl) {
                if (hasSelectedVehicle) {
                    vehicleSelectionErrorEl.style.display = 'none';
                } else if (hasAttemptedSubmit && isVehicleMissing) {
                    vehicleSelectionErrorEl.style.display = 'block';
                }
            }

            const isPickupMissing = !!pickupInput && pickupInput.value.trim() === '';
            if (pickupErrorEl) pickupErrorEl.style.display = (hasAttemptedSubmit && isPickupMissing) ? 'block' : 'none';

            hasRequiredFieldsMissing = isNameMissing || isEmailMissing || isDateMissing || isDatePast || isTimeMissing || isVehicleMissing || isPickupMissing;

            // Alerte globale au-dessus du récapitulatif
            if (globalSubmitErrorEl) {
                globalSubmitErrorEl.style.display = (hasAttemptedSubmit && hasRequiredFieldsMissing) ? 'block' : 'none';
            }

            // Blocage du bouton si dépassement de capacité
            if (submitButton) {
                const isBlocked = isPaxCapacityExceeded || isBaggageCapacityExceeded;                
                submitButton.disabled = isBlocked;
                submitButton.classList.toggle('etb-disabled', isBlocked);
            }

            // Options supplémentaires (Extras) : calcul et illumination visuelle dynamique
            let extrasHtml = '';
            root.querySelectorAll('input[name^="etb_extra_"]').forEach(inp => {
                const qty    = parseInt(inp.value) || 0;
                const parent = inp.closest('.etb-extra-item') || inp.closest('.etb-pax-card');

                // Illumination orange dès que la quantité est >= 1, extinction si 0
                if (parent) {
                    parent.classList.toggle('etb-selected', qty > 0);
                }

                if (qty > 0) {
                    const name      = parent?.querySelector('strong')?.textContent.trim() || 'Option';
                    const priceSpan = parent?.querySelector('.etb-price-tag');
                    const price     = parsePrice(priceSpan?.textContent || '0');
                    
                    total += (price * qty);
                    extrasHtml += `<div class="etb-summary-row"><span>${qty}x ${name}</span><span>+ ${(price * qty).toFixed(0)} ${state.currency}</span></div>`;
                }
            });

            // Remise Promo
            let discountAmount = 0;
            let promoHtml = '';
            const promoVal = parseFloat(state.promo.discount_value) || 0;

            if (promoVal > 0) {
                const promoType = String(state.promo.discount_type).toLowerCase();
                const isPercent = ['percentage', 'percent', 'pourcentage', '%'].includes(promoType);

                if (isPercent) {
                    discountAmount = total * (promoVal / 100);
                } else {
                    discountAmount = promoVal;
                }
                
                total = Math.max(0, total - discountAmount);
                promoHtml = `<div class="etb-summary-row etb-promo-row" id="promo-line"><strong>Code promo (${state.promo.code})</strong><strong>- ${discountAmount.toFixed(0)} ${state.currency}</strong></div>`;
            }

            // Mise à jour du Récapitulatif sombre
            if (summaryTextEl) {
                summaryTextEl.innerHTML = `
                    <div class="etb-summary-details">
                        ${vehiclesHtml}
                        <div class="etb-summary-row"><span>${root.querySelector('input[name="etb_adults"]')?.value || 1} Adulte(s), ${root.querySelector('input[name="etb_children"]')?.value || 0} Enfant(s)</span></div>
                        ${state.pickup.name ? `<div class="etb-summary-row"><span>Prise en charge : ${state.pickup.name}</span></div>` : ''}
                        ${circuitPriceHtml}
                        ${extrasHtml}
                        ${promoHtml}
                    </div>
                `;
            }

            if (totalValEl) totalValEl.textContent = total.toFixed(0);

            // Mise à jour de l'en-tête de prix latéral "À PARTIR DE"
            if (sidebarValEl) {
                sidebarValEl.textContent = total.toFixed(0);
            }
            if (sidebarMaxPaxEl) {
                sidebarMaxPaxEl.textContent = totalCapacityPax;
            }
            if (sidebarHeaderEl) {
                sidebarHeaderEl.classList.toggle('etb-visible', hasSelectedVehicle && totalCapacityPax > 0);
            }
        };

        const refreshAll = () => {
            updateSummary();
        };


        /**
         * Recalcule dynamiquement les horaires de la timeline selon l'heure de départ choisie
         */
        const updateTimelineTimes = (chosenTime) => {
            const activePane = document.querySelector('.co-option-pane.active');
            if (!activePane || !chosenTime) return;

            const baseDepStr = activePane.dataset.baseDeparture || '09:00';
            
            // Conversion HH:MM en minutes
            const parseMinutes = (tStr) => {
                if (!tStr || !tStr.includes(':')) return 0;
                const p = tStr.split(':');
                return (parseInt(p[0], 10) * 60) + parseInt(p[1], 10);
            };

            // Formatage minutes en HH:MM
            const formatTime = (totalMin) => {
                const normalized = ((totalMin % 1440) + 1440) % 1440; // Gestion des 24h
                const h = Math.floor(normalized / 60);
                const m = normalized % 60;
                return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            };

            const baseDepMin   = parseMinutes(baseDepStr);
            const chosenDepMin = parseMinutes(chosenTime);
            const deltaMin     = chosenDepMin - baseDepMin;

            // Décalage de chaque étape de la timeline
            activePane.querySelectorAll('.co-timeline-time').forEach(el => {
                const originalStepTime = el.dataset.baseTime;
                if (!originalStepTime || !originalStepTime.includes(':')) return;

                const originalMin = parseMinutes(originalStepTime);
                const updatedTime = formatTime(originalMin + deltaMin);
                el.textContent = updatedTime;
            });
        };
        // ------------------------------------------------------------------------
        // 4. GESTION DES ONGLETS DE VILLES (DÉCLARÉE APRÈS UPDATESUMMARY)
        // ------------------------------------------------------------------------
        const syncCircuitOption = (btn) => {
            const optionId        = btn.dataset.target || '';
            const duration        = parseFloat(btn.dataset.duration) || 1;
            const additionalPrice = parseFloat(btn.dataset.price) || 0;
            const departureTime   = btn.dataset.time || '';
            const cityName        = btn.textContent.trim();

            state.circuit = {
                duration: duration,
                additionalPrice: additionalPrice,
                optionId: optionId,
                cityName: cityName
            };

            let optionInput = root.querySelector('input[name="etb_option_id"]');
            if (!optionInput) {
                optionInput = document.createElement('input');
                optionInput.type = 'hidden';
                optionInput.name = 'etb_option_id';
                root.appendChild(optionInput);
            }
            optionInput.value = optionId;

            const circuitId = circuitApp ? (circuitApp.dataset.circuitId || 0) : 0;
            let circuitInput = root.querySelector('input[name="etb_circuit_id"]');
            if (!circuitInput) {
                circuitInput = document.createElement('input');
                circuitInput.type = 'hidden';
                circuitInput.name = 'etb_circuit_id';
                root.appendChild(circuitInput);
            }
            circuitInput.value = circuitId;

            if (timeInput && departureTime) {
                timeInput.value = departureTime;
            }

            // Mise à jour immédiate des horaires de la timeline
            updateTimelineTimes(departureTime || (timeInput ? timeInput.value : '09:00'));

            // Appel sécurisé maintenant que updateSummary est déclarée
            updateSummary();
        };

        // Écouteurs d'onglets de ville
        if (tabButtons.length > 0) {
            tabButtons.forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();

                    tabButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    panes.forEach(pane => pane.classList.remove('active'));
                    const targetPane = document.querySelector('#co-pane-' + this.dataset.target);
                    if (targetPane) {
                        targetPane.classList.add('active');
                    }

                    syncCircuitOption(this);
                });
            });

            // Initialisation de la première ville active au chargement
            const initialActiveBtn = document.querySelector('.co-pub-tab-btn.active') || tabButtons[0];
            if (initialActiveBtn) {
                syncCircuitOption(initialActiveBtn);
            }
        }

        // ------------------------------------------------------------------------
        // 5. GESTION DU DROP-OFF ET DU PICKUP
        // ------------------------------------------------------------------------
        if (diffDropoffCb && dropoffContainer && dropoffInfo) {
            diffDropoffCb.addEventListener('change', function () {
                if (this.checked) {
                    dropoffContainer.style.display = 'block';
                } else {
                    dropoffContainer.style.display = 'none';
                    dropoffInfo.value = '';
                }
            });
        }

        if (pickupInput) {
            pickupInput.addEventListener('input', function () {
                state.pickup = {
                    name: this.value.trim(),
                    price: 0
                };
                updateSummary();
            });
        }

        // ------------------------------------------------------------------------
        // 6. VALIDATION DU CODE PROMO (AJAX AVEC FEEDBACK ET LOADING)
        // ------------------------------------------------------------------------
        const promoBtn = root.querySelector('#etb-apply-promo-btn');
        if (promoBtn) {
            promoBtn.addEventListener('click', function (e) {
                e.preventDefault();

                const promoInput = root.querySelector('#etb-promo-input');
                const promoMsg   = root.querySelector('#etb-promo-message');
                const promoCode  = promoInput ? promoInput.value.trim() : '';

                if (!promoCode) {
                    if (promoMsg) {
                        promoMsg.style.display = 'block';
                        promoMsg.className = 'etb-field-feedback etb-error';
                        promoMsg.textContent = '⚠ Veuillez saisir un code promo.';
                    }
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'etb_validate_promo');
                formData.append('nonce', etbAjax.nonce);
                formData.append('promo_code', promoCode);

                // Animation de chargement
                const originalBtnText = promoBtn.textContent;
                promoBtn.disabled = true;
                promoBtn.classList.add('etb-loading');
                promoBtn.textContent = 'Vérification...';

                fetch(etbAjax.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(res => {
                    promoBtn.disabled = false;
                    promoBtn.classList.remove('etb-loading');
                    promoBtn.textContent = originalBtnText;
                    if (promoMsg) promoMsg.style.display = 'block';

                    if (res.success) {
                        state.promo = {
                            code: res.data.code,
                            discount_type: res.data.discount_type,
                            discount_value: res.data.discount_value
                        };
                        if (promoMsg) {
                            promoMsg.className = 'etb-field-feedback etb-success';
                            promoMsg.textContent = '✓ ' + res.data.message;
                        }
                    } else {
                        state.promo = { code: '', discount_type: 'fixed', discount_value: 0 };
                        if (promoMsg) {
                            promoMsg.className = 'etb-field-feedback etb-error';
                            promoMsg.textContent = '⚠ ' + res.data.message;
                        }
                    }
                    refreshAll();
                })
                .catch(() => {
                    promoBtn.disabled = false;
                    promoBtn.classList.remove('etb-loading');
                    promoBtn.textContent = originalBtnText;
                    if (promoMsg) {
                        promoMsg.style.display = 'block';
                        promoMsg.className = 'etb-field-feedback etb-error';
                        promoMsg.textContent = '⚠ Erreur réseau lors de la vérification.';
                    }
                });
            });
        }

        // ------------------------------------------------------------------------
        // 7. ÉCOUTEURS D'ÉVÉNEMENTS UTILISATEURS (CLIC CARTES & QUANTITÉ)
        // ------------------------------------------------------------------------

        // 1. Sélection exclusive d'un véhicule unique (1 réservation = 1 véhicule)
        document.addEventListener('click', (e) => {
            const card = e.target.closest('.etb-vehicle-card');
            if (!card) return;

            // Si clic dans les boutons +/- ou l'input quantité, ne pas basculer
            if (e.target.closest('.etb-qty-control')) {
                return;
            }

            const input = card.querySelector('input[name^="etb_car_qty"]');
            if (!input) return;

            const currentQty = parseInt(input.value) || 0;
            const allCards   = document.querySelectorAll('.etb-vehicle-card');

            if (currentQty > 0) {
                // Si le véhicule était déjà sélectionné, on le désélectionne (remise à zéro)
                input.value = 0;
                card.classList.remove('etb-selected');
            } else {
                // DÉSÉLECTION AUTOMATIQUE DE TOUS LES AUTRES VÉHICULES
                allCards.forEach(c => {
                    const otherInput = c.querySelector('input[name^="etb_car_qty"]');
                    if (otherInput) otherInput.value = 0;
                    c.classList.remove('etb-selected');
                });

                // Sélection exclusive du véhicule cliqué (quantité = 1)
                input.value = 1;
                card.classList.add('etb-selected');
            }

            refreshAll();
        });
        

        // 2. Boutons de quantité +/- (Gestion isolée sans conflit)
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.etb-qty-btn');
            if (!btn) return;

            e.preventDefault();
            e.stopPropagation();

            const input = btn.parentElement.querySelector('input');
            if (!input) return;

            const isPassengerInput = input.name === 'etb_adults' || input.name === 'etb_children';
            if (isPassengerInput && btn.classList.contains('etb-plus') && !hasSelectedVehicle) {
                if (vehicleSelectionErrorEl) vehicleSelectionErrorEl.style.display = 'block';
                return;
            }

            let val = parseInt(input.value) || 0;
            const min = parseInt(input.getAttribute('min') || 0);

            if (btn.classList.contains('etb-plus')) {
                val++;
            } else if (btn.classList.contains('etb-minus')) {
                if (val > min) val--;
            }

            input.value = val;
            refreshAll();
        });

        // 3. Toggle Extras simples
        root.addEventListener('click', (e) => {
            const item = e.target.closest('.etb-extra-item.etb-type-toggle');
            if (!item) return;

            const input = item.querySelector('input[type="hidden"]');
            const isSelected = item.classList.contains('etb-selected');
            
            item.classList.toggle('etb-selected');
            if (input) input.value = isSelected ? "0" : "1";
            
            refreshAll(); 
        });

        // 4. Écouteurs de saisie formulaire
        const luggageInput = root.querySelector('input[name="etb_total_luggage"]');
        if (luggageInput) luggageInput.addEventListener('input', refreshAll);
        if (nameInput) nameInput.addEventListener('input', refreshAll);
        if (emailInput) emailInput.addEventListener('input', refreshAll);
        if (dateInput) {
            dateInput.addEventListener('change', refreshAll);
            dateInput.addEventListener('input', refreshAll);
        }
        if (timeInput) {
            const handleTimeChange = function () {
                updateTimelineTimes(this.value);
                refreshAll();
            };
            timeInput.addEventListener('change', handleTimeChange);
            timeInput.addEventListener('input', handleTimeChange);
        }




        // ------------------------------------------------------------------------
        // 8. SOUMISSION FINALE AJAX (FETCH)
        // ------------------------------------------------------------------------
        if (submitButton) {
            submitButton.addEventListener('click', (e) => {
                hasAttemptedSubmit = true;
                refreshAll();
                if (hasRequiredFieldsMissing) {
                    e.preventDefault();
                    return;
                }

                e.preventDefault();

                const formData = new FormData();
                
                // Collecte globale des champs
                document.querySelectorAll('#co-circuit-app input, #co-circuit-app select, #co-circuit-app textarea, #etb-booking-app input, #etb-booking-app select, #etb-booking-app textarea').forEach(field => {
                    if (!field.name || field.disabled) return;

                    if (field.type === 'checkbox' || field.type === 'radio') {
                        if (field.checked) {
                            formData.append(field.name, field.value);
                        }
                    } else {
                        formData.append(field.name, field.value);
                    }
                });

                formData.append('action', 'etb_submit_booking');
                formData.append('nonce', etbAjax.nonce);

                const originalBtnText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Traitement en cours...';

                fetch(etbAjax.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then((response) => response.json())
                .then((result) => {
                    if (result.success) {
                        // 1. Affichage du message de confirmation vert
                        const formElement = root.querySelector('form') || root;
                        formElement.innerHTML = `
                            <div class="etb-success-notice" style="padding: 25px; text-align: center; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--etb-radius-md, 12px); margin: 10px 0;">
                                <div style="font-size: 38px; margin-bottom: 10px;">✅</div>
                                <h3 style="color: #166534; margin-top: 0; font-size: 18px; font-weight: 800;">Votre demande de réservation a bien été reçue !</h3>
                                <p style="color: #15803d; font-size: 16px; margin: 10px 0;">
                                    Numéro de dossier : <strong>#${result.data.booking_id}</strong>
                                </p>
                                <p style="color: #374151; font-size: 14px; margin-bottom: 0;">
                                    Un accusé de réception avec les détails de votre prestation a été envoyé à l'adresse <strong>${result.data.email}</strong>.
                                </p>
                            </div>
                        `;

                        // 2. Remise à zéro des cartes véhicules (quantités = 0 et fermeture des pilules)
                        document.querySelectorAll('.etb-vehicle-card input[name^="etb_car_qty"]').forEach(inp => {
                            inp.value = 0;
                        });
                        document.querySelectorAll('.etb-vehicle-card').forEach(card => {
                            card.classList.remove('etb-selected');
                        });

                        // 3. Rétractation animée de l'en-tête de prix latéral
                        if (sidebarHeaderEl) {
                            sidebarHeaderEl.classList.remove('etb-visible');
                        }

                        // 4. Auto-scroll fluide vers la grille des véhicules en haut
                        const scrollTarget = document.querySelector('.co-top-vehicles-section') || document.querySelector('#co-circuit-app') || root;
                        if (scrollTarget) {
                            scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    } else {
                        submitButton.disabled = false;
                        submitButton.textContent = originalBtnText;
                        alert(result.data.message || 'Une erreur est survenue lors de la réservation.');
                    }
                })
                .catch((error) => {
                    console.error('ETB AJAX error:', error);
                    submitButton.disabled = false;
                    submitButton.textContent = originalBtnText;
                    alert('Une erreur réseau est survenue. Veuillez vérifier votre connexion et réessayer.');
                });
            });
        }
        // 9. Animation automatique au centre de l'écran pour Mobile & Tablette
        if ('IntersectionObserver' in window) {
            const observerOptions = {
                root: null,
                rootMargin: '-40% 0px -40% 0px', // Cible la bande centrale de 50% de l'écran
                threshold: 0.2
            };

            const vehicleObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    entry.target.classList.toggle('etb-in-viewport', entry.isIntersecting);
                });
            }, observerOptions);

            document.querySelectorAll('.etb-vehicle-card').forEach(card => {
                vehicleObserver.observe(card);
            });
        }

        // Lancement initial
        refreshAll();
    };

    /**
     * Moteur interactif pour le Widget Minimal [etb_transfer] (Blacklane Style)
     */
    const initQuickWidget = function () {
        const quickRoot = document.querySelector('#etb-quick-widget-app');
        if (!quickRoot) return;

        // 1. Éléments du DOM
        const modeBtns       = quickRoot.querySelectorAll('.etb-quick-mode-btn');
        const dropoffCol     = quickRoot.querySelector('#etb-quick-dropoff-col');
        const durationCol    = quickRoot.querySelector('#etb-quick-duration-col');
        const pickupInput    = quickRoot.querySelector('#etb-quick-pickup');
        const pickupClearBtn = quickRoot.querySelector('#etb-quick-clear-pickup');
        const dropoffInput   = quickRoot.querySelector('#etb-quick-dropoff');
        const dropoffClearBtn= quickRoot.querySelector('#etb-quick-clear-dropoff');
        const durationSelect = quickRoot.querySelector('#etb-quick-duration'); // C'est maintenant un input hidden

        const dateInput      = quickRoot.querySelector('#etb-quick-date');
        const dateTextEl     = quickRoot.querySelector('#etb-quick-date-text'); // <-- Ajout de la sélection du texte
        const timeInput      = quickRoot.querySelector('#etb-quick-time');
        const getPriceBtn    = quickRoot.querySelector('#etb-quick-get-price-btn');
        const errorNotice    = quickRoot.querySelector('#etb-quick-error');
        const fleetSection   = quickRoot.querySelector('#etb-quick-fleet-section');
        const carCards       = quickRoot.querySelectorAll('.etb-quick-car-item');
        const bookingBar     = quickRoot.querySelector('#etb-quick-booking-bar');
        const selectedNameEl = quickRoot.querySelector('#etb-quick-selected-name');
        const selectedTotEl  = quickRoot.querySelector('#etb-quick-selected-total');
        const bookNowBtn     = quickRoot.querySelector('#etb-quick-book-now-btn');
        const quoteNoticeEl  = quickRoot.querySelector('#etb-quick-quote-notice');
        const whatsappBtn    = quickRoot.querySelector('#etb-quick-whatsapp-btn');
        const bookBtnLabel   = quickRoot.querySelector('#etb-quick-book-btn-label');

        let currentMode = 'transfer';
        let selectedCar = null;
        let isQuoteMode = false;
        const currency  = (typeof etbAjax !== 'undefined' && etbAjax.currency) ? etbAjax.currency : '€';
        const mapboxToken = (typeof etbAjax !== 'undefined' && etbAjax.mapbox_token) ? etbAjax.mapbox_token : '';
        const sessionToken = (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : ('st_' + Math.random().toString(36).substr(2, 9));


         // 1ter. Custom Select de la Durée (À l'heure)
        const customDurationWrapper = quickRoot.querySelector('#etb-duration-custom-select');
        if (customDurationWrapper) {
            const durationTrigger = customDurationWrapper.querySelector('.etb-custom-select-trigger');
            const durationLabel   = durationTrigger.querySelector('span');
            const durationOptions = customDurationWrapper.querySelectorAll('.etb-custom-option');

            // Ouvrir / Fermer au clic
            durationTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                customDurationWrapper.classList.toggle('is-open');
            });

            // Sélection d'une option
            durationOptions.forEach(opt => {
                opt.addEventListener('click', function(e) {
                    e.stopPropagation();
                    // Retirer la classe 'selected' partout
                    durationOptions.forEach(o => o.classList.remove('selected'));
                    
                    // Activer l'option cliquée
                    this.classList.add('selected');
                    const val = this.dataset.val;
                    const text = this.textContent;

                    // Mettre à jour l'affichage et l'input caché
                    durationLabel.textContent = text;
                    if (durationSelect) durationSelect.value = val;

                    // Fermer la liste
                    customDurationWrapper.classList.remove('is-open');

                    // MISE À JOUR AUTOMATIQUE EN DIRECT si la flotte est déjà affichée
                    if (currentMode === 'hourly' && fleetSection && fleetSection.style.display !== 'none') {
                        recalculateHourlyFleet();
                    }
                });
            });

            // Fermer si on clique ailleurs sur la page
            document.addEventListener('click', function(e) {
                if (!customDurationWrapper.contains(e.target)) {
                    customDurationWrapper.classList.remove('is-open');
                }
            });
        }




        // Gestion de l'affichage de la croix : UNIQUEMENT si une adresse a été réellement sélectionnée
        const setupClearButton = (inputEl, clearBtn, latEl, lngEl, suggestionsBox) => {
            if (!inputEl || !clearBtn) return;

            // La croix n'apparaît QUE si les coordonnées GPS sont présentes ET le champ non vide
            const updateClearBtnState = () => {
                const isAddressSelected = !!(latEl && latEl.value && lngEl && lngEl.value && inputEl.value.trim().length > 0);
                if (isAddressSelected) {
                    clearBtn.classList.add('is-visible');
                } else {
                    clearBtn.classList.remove('is-visible');
                }
            };

            // Si l'utilisateur retape du texte manuellement, on masque la croix et on réinitialise le GPS
            inputEl.addEventListener('input', function () {
                if (latEl) latEl.value = '';
                if (lngEl) lngEl.value = '';
                updateClearBtnState();
            });

            inputEl.addEventListener('change', updateClearBtnState);

            // Clic sur le bouton rond 'X'
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                inputEl.value = '';
                if (latEl) latEl.value = '';
                if (lngEl) lngEl.value = '';
                if (suggestionsBox) suggestionsBox.style.display = 'none';

                clearBtn.classList.remove('is-visible');
                inputEl.focus();
            });

            // Masqué d'office au chargement
            updateClearBtnState();
        };




        // 1bis. Formatage de la date (ex: 2026-09-15 -> 15 Sep 2026)
        const formatCustomDate = (dateVal) => {
            if (!dateVal) return '-- --- ----';
            const parts = dateVal.split('-');
            if (parts.length !== 3) return dateVal;
            const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
            const monthIdx = parseInt(parts[1], 10) - 1;
            return `${parts[2]} ${months[monthIdx] || parts[1]} ${parts[0]}`;
        };

        /// Mise à jour du texte de date dès le chargement et à chaque clic
        if (dateInput && dateTextEl) {
            dateTextEl.textContent = formatCustomDate(dateInput.value);
            dateInput.addEventListener('change', function () {
                dateTextEl.textContent = formatCustomDate(this.value);
            });
            dateInput.addEventListener('input', function () {
                dateTextEl.textContent = formatCustomDate(this.value);
            });
        }

        // Déclenchement du calendrier au clic N'IMPORTE OÙ sur la colonne Date
        const dateCol = quickRoot.querySelector('#etb-quick-date-col');
        if (dateCol && dateInput) {
            dateCol.addEventListener('click', function () {
                if (typeof dateInput.showPicker === 'function') {
                    try { dateInput.showPicker(); } catch (err) {}
                }
            });
        }

        // Gestion du sélecteur d'heures sur-mesure (Double sécurité JS + CSS)
        const timeCol   = quickRoot.querySelector('#etb-quick-time-col');
        const timePopup = quickRoot.querySelector('#etb-quick-time-popup');

        if (timeCol && timeInput && timePopup) {
            // Sécurité absolue au chargement : forcer l'extinction
            timePopup.classList.remove('is-open');
            timePopup.style.setProperty('display', 'none', 'important');

            // Ouvrir / Fermer le sélecteur au clic sur la colonne heure
            timeCol.addEventListener('click', function (e) {
                if (e.target.closest('#etb-quick-time-popup')) return;
                
                const isCurrentlyOpen = timePopup.classList.contains('is-open');
                if (isCurrentlyOpen) {
                    timePopup.classList.remove('is-open');
                    timePopup.style.setProperty('display', 'none', 'important');
                } else {
                    timePopup.classList.add('is-open');
                    timePopup.style.setProperty('display', 'flex', 'important');
                }
            });

            /// Fonction interne de mise à jour de la valeur 12h formatée
            const update12hTime = () => {
                const activeH     = timePopup.querySelector('.etb-hour-opt.active')?.dataset.val || '09';
                const activeM     = timePopup.querySelector('.etb-minute-opt.active')?.dataset.val || '00';
                const activeAmpm  = timePopup.querySelector('.etb-ampm-btn.active')?.dataset.val || 'AM';
                timeInput.value   = `${activeH}:${activeM} ${activeAmpm}`;
            };

            // 1. Bascule AM / PM
            timePopup.querySelectorAll('.etb-ampm-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    timePopup.querySelectorAll('.etb-ampm-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    update12hTime();
                });
            });

            // 2. Clic sur une Heure (01 à 12)
            timePopup.querySelectorAll('.etb-hour-opt').forEach(opt => {
                opt.addEventListener('click', function (e) {
                    e.stopPropagation();
                    timePopup.querySelectorAll('.etb-hour-opt').forEach(o => o.classList.remove('active'));
                    this.classList.add('active');
                    update12hTime();
                });
            });

            // 3. Clic sur une Minute (00 à 55) : met à jour et ferme la boîte
            timePopup.querySelectorAll('.etb-minute-opt').forEach(opt => {
                opt.addEventListener('click', function (e) {
                    e.stopPropagation();
                    timePopup.querySelectorAll('.etb-minute-opt').forEach(o => o.classList.remove('active'));
                    this.classList.add('active');
                    update12hTime();

                    // Fermeture immédiate de la boîte
                    timePopup.classList.remove('is-open');
                    timePopup.style.setProperty('display', 'none', 'important');
                });
            });

            // Fermer si clic n'importe où en dehors
            document.addEventListener('pointerdown', function (e) {
                if (!timeCol.contains(e.target)) {
                    timePopup.classList.remove('is-open');
                    timePopup.style.setProperty('display', 'none', 'important');
                }
            });
        }
             
           

        // Arrondi des minutes par crans de 5 min au chargement (ex: 14:31 -> 14:35)
        if (timeInput && timeInput.value) {
            const timeParts = timeInput.value.split(':');
            if (timeParts.length >= 2) {
                let m = parseInt(timeParts[1], 10);
                let roundedM = Math.ceil(m / 5) * 5;
                let h = parseInt(timeParts[0], 10);
                if (roundedM >= 60) {
                    roundedM = 0;
                    h = (h + 1) % 24;
                }
                timeInput.value = String(h).padStart(2, '0') + ':' + String(roundedM).padStart(2, '0');
            }
        }

        // Fonction d'autocomplétion Mapbox avec support clavier complet et sélection robuste
        const setupMapboxAutocomplete = (inputEl, latEl, lngEl, suggestionsBox) => {
            if (!inputEl || !mapboxToken) return;

            let debounceTimer   = null;
            let currentResults  = []; // Stockage en mémoire vive des suggestions
            let highlightedIdx  = -1; // Index de l'élément sélectionné au clavier

            // 1. Fonction interne pour valider une suggestion
            const selectSuggestion = (item) => {
                if (!item) return;

                const placeName = item.name + (item.full_address ? ' (' + item.full_address + ')' : '');
                inputEl.value   = placeName;
                inputEl.dispatchEvent(new Event('change')); // Affiche automatiquement la croix

                if (suggestionsBox) {
                    suggestionsBox.style.display = 'none';
                    suggestionsBox.innerHTML     = '';
                }
                highlightedIdx = -1;

                if (!item.mapbox_id) return;

                // Récupération des coordonnées GPS exactes
                const retrieveUrl = `https://api.mapbox.com/search/searchbox/v1/retrieve/${encodeURIComponent(item.mapbox_id)}?access_token=${mapboxToken}&session_token=${sessionToken}`;

                fetch(retrieveUrl)
                        .then(res => res.json())
                        .then(resData => {
                            if (resData.features && resData.features.length > 0) {
                                const feat = resData.features[0];
                                const lng  = feat.geometry.coordinates[0];
                                const lat  = feat.geometry.coordinates[1];
                                if (latEl) latEl.value = lat;
                                if (lngEl) lngEl.value = lng;
                                
                                // Déclenche l'apparition de la croix car l'adresse est validée
                                inputEl.dispatchEvent(new Event('change'));
                            }
                        })
                    .catch(err => console.error('Mapbox retrieve error:', err));
            };

            // 2. Mise à jour visuelle (scroll activé UNIQUEMENT au clavier, jamais à la souris)
            const updateHighlight = (isKeyboard = false) => {
                if (!suggestionsBox) return;
                const items = suggestionsBox.querySelectorAll('.etb-quick-suggestion-item');
                items.forEach((el, idx) => {
                    if (idx === highlightedIdx) {
                        el.classList.add('highlighted');
                        if (isKeyboard) {
                            el.scrollIntoView({ block: 'nearest' }); // Scroll fluide réservé au clavier
                        }
                    } else {
                        el.classList.remove('highlighted');
                    }
                });
            };

            // 3. Saisie dans le champ (avec debounce 350ms)
            inputEl.addEventListener('input', function () {
                const query = this.value.trim();
                clearTimeout(debounceTimer);
                highlightedIdx = -1;

                if (query.length < 3) {
                    if (suggestionsBox) suggestionsBox.style.display = 'none';
                    currentResults = [];
                    return;
                }

                debounceTimer = setTimeout(() => {
                    const url = `https://api.mapbox.com/search/searchbox/v1/suggest?q=${encodeURIComponent(query)}&access_token=${mapboxToken}&session_token=${sessionToken}&language=fr,en&country=fr,mc&proximity=7.26,43.71&limit=6`;

                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            currentResults = data.suggestions || [];

                            if (currentResults.length === 0) {
                                if (suggestionsBox) suggestionsBox.style.display = 'none';
                                return;
                            }

                            let html = '';
                            currentResults.forEach((item, idx) => {
                                const title    = item.name || '';
                                const subtitle = item.full_address || item.place_formatted || '';

                                html += `<div class="etb-quick-suggestion-item" data-idx="${idx}">
                                    <span class="dashicons dashicons-location"></span>
                                    <div class="etb-quick-sug-text">
                                        <strong class="etb-quick-sug-title">${title}</strong>
                                        ${subtitle ? `<br><small class="etb-quick-sug-sub">${subtitle}</small>` : ''}
                                    </div>
                                </div>`;
                            });

                            if (suggestionsBox) {
                                suggestionsBox.innerHTML     = html;
                                suggestionsBox.style.display = 'block';
                            }
                        })
                        .catch(() => {
                            if (suggestionsBox) suggestionsBox.style.display = 'none';
                        });
                }, 350);
            });

            // 4. Navigation au Clavier (Flèche Haut, Flèche Bas, Entrée, Échap)
            inputEl.addEventListener('keydown', function (e) {
                if (!suggestionsBox || suggestionsBox.style.display === 'none' || currentResults.length === 0) {
                    return;
                }

                // Flèche Bas ↓
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    highlightedIdx = (highlightedIdx + 1) >= currentResults.length ? 0 : (highlightedIdx + 1);
                    updateHighlight(true); // Autorise le scroll vers le bas
                }
                // Flèche Haut ↑
                else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    highlightedIdx = (highlightedIdx - 1) < 0 ? (currentResults.length - 1) : (highlightedIdx - 1);
                    updateHighlight(true); // Autorise le scroll vers le haut
                }
                // Touche Entrée ↵
                else if (e.key === 'Enter') {
                    if (highlightedIdx >= 0 && highlightedIdx < currentResults.length) {
                        e.preventDefault();
                        selectSuggestion(currentResults[highlightedIdx]);
                    }
                }
                // Touche Échap
                else if (e.key === 'Escape') {
                    suggestionsBox.style.display = 'none';
                    highlightedIdx = -1;
                }
            });

            // 5. Gestion des événements souris et défilement sur la boîte
            if (suggestionsBox) {
                // Intercepte le clic sur n'importe quel élément de la ligne
                suggestionsBox.addEventListener('pointerdown', function (e) {
                    const itemEl = e.target.closest('.etb-quick-suggestion-item');
                    if (!itemEl) {
                        e.stopPropagation(); // Clic sur la scrollbar : on empêche la fermeture !
                        return;
                    }

                    e.preventDefault();
                    e.stopPropagation(); // Bloque la fermeture externe

                    const idx = parseInt(itemEl.dataset.idx, 10);
                    if (!isNaN(idx) && currentResults[idx]) {
                        selectSuggestion(currentResults[idx]);
                    }
                });

                // Survol souris propre
                suggestionsBox.addEventListener('pointerover', function (e) {
                    const itemEl = e.target.closest('.etb-quick-suggestion-item');
                    if (!itemEl) return;
                    highlightedIdx = parseInt(itemEl.dataset.idx, 10);
                    updateHighlight(false);
                });

                // Isole le défilement de la molette sur la boîte
                suggestionsBox.addEventListener('wheel', function (e) {
                    e.stopPropagation();
                }, { passive: true });
            }

            // 6. Fermeture UNIQUEMENT lors d'un vrai clic hors de la colonne et de la boîte
            document.addEventListener('pointerdown', function (e) {
                if (!inputEl.contains(e.target) && (!suggestionsBox || !suggestionsBox.contains(e.target))) {
                    if (suggestionsBox) suggestionsBox.style.display = 'none';
                    highlightedIdx = -1;
                }
            });
        };
        // ------------------------------------------------------------------------
        // 6. AUTOCOMPLÉTION MODULAIRE (GOOGLE PLACES API OU MAPBOX)
        // ------------------------------------------------------------------------
        const addressProvider = (typeof etbAjax !== 'undefined' && etbAjax.address_provider) ? etbAjax.address_provider : 'google';

        // Fonction d'autocomplétion officielle Google Places API
        const setupGoogleAutocomplete = (inputEl, latEl, lngEl) => {
            if (!inputEl || typeof google === 'undefined' || !google.maps || !google.maps.places) return;

            const loaderEl = inputEl.parentElement ? inputEl.parentElement.querySelector('.etb-quick-input-loader') : null;
            let typingTimer = null;

            // Allumage du petit loader dès que l'utilisateur tape
            inputEl.addEventListener('input', function () {
                const query = this.value.trim();
                if (query.length >= 2 && loaderEl) {
                    loaderEl.style.display = 'block';
                } else if (loaderEl) {
                    loaderEl.style.display = 'none';
                }

                clearTimeout(typingTimer);
                // Extinction de sécurité après 1.8s
                typingTimer = setTimeout(() => {
                    if (loaderEl) loaderEl.style.display = 'none';
                }, 1800);
            });

            // Extinction du loader dès que Google affiche la liste de suggestions (.pac-container)
            const pacObserver = new MutationObserver(() => {
                const pac = document.querySelector('.pac-container');
                if (pac && pac.style.display !== 'none' && pac.querySelector('.pac-item')) {
                    if (loaderEl) loaderEl.style.display = 'none';
                    clearTimeout(typingTimer);
                }
            });
            pacObserver.observe(document.body, { childList: true, subtree: true, attributes: true });

            const options = {
                fields: ['formatted_address', 'geometry', 'name'],
                componentRestrictions: { country: ['fr', 'mc'] }
            };

            const autocomplete = new google.maps.places.Autocomplete(inputEl, options);

            autocomplete.addListener('place_changed', function () {
                if (loaderEl) loaderEl.style.display = 'none';
                clearTimeout(typingTimer);

                const place = autocomplete.getPlace();
                if (!place.geometry || !place.geometry.location) return;

                const placeName = place.name + (place.formatted_address ? ' (' + place.formatted_address + ')' : '');
                inputEl.value   = placeName;

                if (latEl) latEl.value = place.geometry.location.lat();
                if (lngEl) lngEl.value = place.geometry.location.lng();

                inputEl.dispatchEvent(new Event('change'));
            });

            inputEl.addEventListener('blur', function () {
                setTimeout(() => { if (loaderEl) loaderEl.style.display = 'none'; }, 300);
            });

            inputEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') e.preventDefault();
            });
        };
    

        // Branchement selon le fournisseur sélectionné dans les Réglages WP
        const pickupLatEl  = quickRoot.querySelector('#etb-quick-pickup-lat');
        const pickupLngEl  = quickRoot.querySelector('#etb-quick-pickup-lng');
        const pickupBox    = quickRoot.querySelector('#etb-quick-pickup-suggestions');

        const dropoffLatEl = quickRoot.querySelector('#etb-quick-dropoff-lat');
        const dropoffLngEl = quickRoot.querySelector('#etb-quick-dropoff-lng');
        const dropoffBox   = quickRoot.querySelector('#etb-quick-dropoff-suggestions');

        // Activation des boutons de suppression rapide
        setupClearButton(pickupInput, pickupClearBtn, pickupLatEl, pickupLngEl, pickupBox);
        setupClearButton(dropoffInput, dropoffClearBtn, dropoffLatEl, dropoffLngEl, dropoffBox);

        if (addressProvider === 'google') {
            setupGoogleAutocomplete(pickupInput, pickupLatEl, pickupLngEl);
            setupGoogleAutocomplete(dropoffInput, dropoffLatEl, dropoffLngEl);
        } else {
            setupMapboxAutocomplete(pickupInput, pickupLatEl, pickupLngEl, pickupBox);
            setupMapboxAutocomplete(dropoffInput, dropoffLatEl, dropoffLngEl, dropoffBox);
        }
            


        // 2. Bascule de Mode : Trajet simple vs À l'heure
        modeBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                modeBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentMode = this.dataset.mode;

                if (currentMode === 'hourly') {
                    if (dropoffCol) dropoffCol.style.display = 'none';
                    if (durationCol) durationCol.style.display = 'flex';
                } else {
                    if (dropoffCol) dropoffCol.style.display = 'flex';
                    if (durationCol) durationCol.style.display = 'none';
                }

                // Réinitialise la flotte, les bandeaux et les capsules si on change de mode
                isQuoteMode = false;
                if (quoteNoticeEl) quoteNoticeEl.classList.remove('is-visible');
                if (fleetSection) fleetSection.style.display = 'none';
                if (bookingBar) bookingBar.classList.remove('is-visible');
                if (errorNotice) errorNotice.style.display = 'none';
                selectedCar = null;
                carCards.forEach(c => {
                    c.classList.remove('selected');
                    const b = c.querySelector('.etb-quick-select-btn');
                    if (b) b.textContent = 'Select';

                    // Purge systématique de la capsule km pour ne laisser aucun résidu
                    const km = c.querySelector('.etb-quick-km-info');
                    if (km) {
                        km.textContent = '';
                        km.classList.remove('is-visible');
                    }
                });
            });
        });

        // Fonction utilitaire pour dévoiler la flotte
        const showFleetSection = () => {
            if (fleetSection) {
                fleetSection.style.display = 'block';
                fleetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        // Fonction de recalcul instantané des tarifs horaires
        const recalculateHourlyFleet = () => {
            // Désactivation formelle du mode devis pour le mode horaire
            isQuoteMode = false;
            if (quoteNoticeEl) quoteNoticeEl.classList.remove('is-visible');

            const durationHours = parseFloat(durationSelect ? durationSelect.value : 4);
            let availableCarsCount = 0;

            carCards.forEach(card => {
                const minHours    = parseFloat(card.getAttribute('data-min-hours')) || 4;
                const hourlyRate  = parseFloat(card.getAttribute('data-hourly-rate')) || 0;
                const pack10h     = parseFloat(card.getAttribute('data-pack-10h')) || 0;
                const supHourRate = parseFloat(card.getAttribute('data-sup-hour-rate')) || 0;
                const kmPerHour   = parseFloat(card.getAttribute('data-km-ph')) || 35;
                const kmSupRate   = parseFloat(card.getAttribute('data-km-sup-rate')) || 0;

                // Règle de durée minimale : masquage si insuffisant
                if (durationHours < minHours) {
                    card.style.display = 'none';
                    card.classList.add('etb-hidden');
                    card.classList.remove('selected');
                    return;
                } else {
                    card.style.display = 'flex';
                    card.classList.remove('etb-hidden');
                }

                let calculatedPrice = 0;
                let priceDetailHtml = '';

                if (durationHours < 10) {
                    calculatedPrice = hourlyRate > 0 ? Math.round(durationHours * hourlyRate) : pack10h;
                    if (hourlyRate > 0) {
                        priceDetailHtml = `Price for ${durationHours} hours <br> <strong>${hourlyRate} ${currency}</strong> per hour`;
                    }
                } else if (durationHours === 10) {
                    calculatedPrice = pack10h > 0 ? pack10h : Math.round(durationHours * hourlyRate);
                    const hourlyEquivalent = pack10h > 0 ? Math.round(pack10h / 10) : hourlyRate;
                    priceDetailHtml = `Price for 10 hours <br> <strong>${hourlyEquivalent} ${currency}</strong> per hour`;
                } else {
                    const extraHours = durationHours - 10;
                    const basePack   = pack10h > 0 ? pack10h : Math.round(10 * hourlyRate);
                    calculatedPrice  = Math.round(basePack + (extraHours * supHourRate));
                    if (pack10h > 0 && supHourRate > 0) {
                        priceDetailHtml = `10h Package + ${extraHours} extra hour(s) <br> <strong>${supHourRate} ${currency}</strong> per extra hour`;
                    }
                }

                // Quota kilométrique
                const totalKm = Math.round(durationHours * kmPerHour);
                const kmInfoEl = card.querySelector('.etb-quick-km-info');
                if (kmInfoEl) {
                    let kmText = '' + totalKm + ' km included';
                    if (kmSupRate > 0) {
                        kmText += ' (' + kmSupRate.toFixed(2) + ' €/km sup.)';
                    }
                    kmInfoEl.textContent = kmText;
                    kmInfoEl.classList.add('is-visible'); // <-- Activation via la classe CSS
                }

                // 1. Régénération complète du prix chiffré avec "All inclusive" à côté
                const priceBox = card.querySelector('.etb-quick-price-display');
                if (priceBox) {
                    priceBox.innerHTML = '<span class="etb-quick-amount">' + calculatedPrice + '</span> <span class="etb-quick-currency">' + currency + '</span> <span class="etb-quick-all-inclusive">All inclusive</span>';
                }

                // 2. Mise à jour de la ligne d'explication horaire
                const detailEl = card.querySelector('.etb-quick-price-detail');
                if (detailEl) {
                    if (priceDetailHtml) {
                        detailEl.innerHTML = priceDetailHtml;
                        detailEl.style.display = 'block';
                    } else {
                        detailEl.style.display = 'none';
                    }
                }

                // 3. Remise du bouton sur Select
                const selBtn = card.querySelector('.etb-quick-select-btn');
                if (selBtn) {
                    selBtn.textContent = 'Select';
                }

                card.dataset.calculatedPrice = calculatedPrice;
                card.dataset.isQuote = '0';
                card.style.display = 'flex';
                card.classList.remove('etb-hidden');
                availableCarsCount++;
            });

            // Si un véhicule est déjà sélectionné, on actualise la barre du bas
            if (selectedCar && selectedCar.id) {
                const activeCard = quickRoot.querySelector(`.etb-quick-car-item[data-id="${selectedCar.id}"]`);
                if (activeCard && activeCard.style.display !== 'none') {
                    const updatedPrice = activeCard.dataset.calculatedPrice || '0';
                    selectedCar.price   = updatedPrice;
                    selectedCar.isQuote = false;
                    if (selectedTotEl) selectedTotEl.textContent = updatedPrice + ' ' + currency;
                    if (bookBtnLabel) bookBtnLabel.textContent = 'Book this Trip';
                    const activeBtn = activeCard.querySelector('.etb-quick-select-btn');
                    if (activeBtn) activeBtn.textContent = '✓ Selected';
                } else {
                    selectedCar = null;
                    if (bookingBar) bookingBar.classList.remove('is-visible');
                }
            }

            return availableCarsCount;
        };

        // Fonction d'activation du mode Devis Sur Mesure (Custom Quote)
        const triggerCustomQuoteMode = () => {
            isQuoteMode = true;
            if (errorNotice) errorNotice.style.display = 'none';

            // 1. Afficher le bandeau d'information VIP
            if (quoteNoticeEl) {
                quoteNoticeEl.innerHTML = '<span class="dashicons dashicons-info-outline"></span> <div><strong>Custom Itinerary / Long Distance:</strong> This specific route requires a personalized quotation from our dispatch team. Please select your preferred vehicle below to submit a quote request.</div>';
                quoteNoticeEl.classList.add('is-visible');
            }

            // 2. Afficher toutes les cartes avec le badge Custom Quote et bouton Request Quote
            carCards.forEach(function(c) {
                c.dataset.calculatedPrice = 'Custom Quote';
                c.dataset.isQuote = '1';

                var priceBox = c.querySelector('.etb-quick-price-display');
                if (priceBox) {
                    priceBox.innerHTML = '<span class="etb-quick-quote-badge">Custom Quote</span>';
                }

                var detailBox = c.querySelector('.etb-quick-price-detail');
                if (detailBox) {
                    detailBox.innerHTML = 'Tailored pricing by dispatch';
                    detailBox.style.display = 'block';
                }

                // Masquage de la mention "All inclusive" pour les véhicules sur devis
                var allIncl = c.querySelector('.etb-quick-all-inclusive');
                if (allIncl) {
                    allIncl.style.display = 'none';
                }

                var kmBox = c.querySelector('.etb-quick-km-info');
                if (kmBox) {
                    kmBox.textContent = '';
                    kmBox.classList.remove('is-visible');
                }

                var selBtn = c.querySelector('.etb-quick-select-btn');
                if (selBtn) {
                    selBtn.textContent = 'Request Quote';
                }

                c.style.display = 'flex';
                c.classList.remove('selected', 'etb-hidden');
            });

            showFleetSection();
        };


        // 3. Clic sur "Show Prices"
        if (getPriceBtn) {
            getPriceBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (errorNotice) errorNotice.style.display = 'none';

                // Disparition progressive de la bordure animée de l'état initial
                const glassBar = quickRoot.querySelector('.etb-quick-glass-bar');
                if (glassBar) {
                    glassBar.classList.remove('etb-initial-border');
                }

                const pickupVal  = pickupInput ? pickupInput.value.trim() : '';
                const dropoffVal = dropoffInput ? dropoffInput.value.trim() : '';
                const dateVal    = dateInput ? dateInput.value.trim() : '';
                const timeVal    = timeInput ? timeInput.value.trim() : '';

                if (!pickupVal) return showError('Please enter a pickup location.');
                if (currentMode === 'transfer' && !dropoffVal) return showError('Please enter a drop-off location.');
                if (!dateVal) return showError('Please select a date.');
                if (!timeVal || timeVal === '-- : --') return showError('Please select a pickup time.');

                // ─────────────────────────────────────────────────────────────
                // MODE 1 : LOCATION À L'HEURE (By the hour)
                // Calcul local direct et instantané (Toute la France couverte)
                // ─────────────────────────────────────────────────────────────
                if (currentMode === 'hourly') {
                    isQuoteMode = false;
                    if (quoteNoticeEl) quoteNoticeEl.classList.remove('is-visible');

                    const count = recalculateHourlyFleet();
                    if (count > 0) {
                        showFleetSection();
                    } else {
                        showError('No vehicles available for this duration (minimum hours not met).');
                    }
                    return;
                }

                // ─────────────────────────────────────────────────────────────
                // MODE 2 : TRANSFERT POINT A -> B (One way) - API LimoExpress
                // ─────────────────────────────────────────────────────────────
                const fromLat = pickupLatEl ? pickupLatEl.value : '';
                const fromLng = pickupLngEl ? pickupLngEl.value : '';
                const toLat   = dropoffLatEl ? dropoffLatEl.value : '';
                const toLng   = dropoffLngEl ? dropoffLngEl.value : '';

                if (!fromLat || !fromLng || !toLat || !toLng) {
                    return showError('Please select exact addresses from the suggested list to calculate the route.');
                }

                const originalBtnText = getPriceBtn.innerHTML;
                getPriceBtn.disabled = true;
                getPriceBtn.classList.add('etb-loading-glow'); // Active le faisceau lumineux tournant
                getPriceBtn.innerHTML = '<span>Calculating...</span>';

                const formData = new FormData();
                formData.append('action', 'etb_quick_pricing');
                formData.append('nonce', etbAjax.nonce);
                formData.append('from_lat', fromLat);
                formData.append('from_lng', fromLng);
                formData.append('to_lat', toLat);
                formData.append('to_lng', toLng);

                fetch(etbAjax.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(res => {
                    getPriceBtn.disabled = false;
                    getPriceBtn.classList.remove('etb-loading-glow'); // Éteint le faisceau
                    getPriceBtn.innerHTML = originalBtnText;

                    if (res.success && res.data.pricing_data && res.data.pricing_data.length > 0) {
                        var pricingData = res.data.pricing_data;
                        var availableCarsCount = 0;

                        carCards.forEach(c => {
                            c.style.display = 'none';
                            c.classList.remove('selected');
                            var km = c.querySelector('.etb-quick-km-info');
                            if (km) {
                                km.textContent = '';
                                km.classList.remove('is-visible');
                            }
                        });

                        pricingData.forEach(item => {
                            if (!item || !item.vehicle_class || !item.vehicle_class.id || !item.prices || item.prices.length === 0) return;
                            var limoUuid = String(item.vehicle_class.id).trim().toLowerCase();
                            var targetCard = document.getElementById('limo-car-' + limoUuid) 
                                          || document.getElementById('limo-car-' + item.vehicle_class.id)
                                          || quickRoot.querySelector('[data-limo-class-id="' + limoUuid + '"]');

                            if (targetCard) {
                                var limoPrice = Math.round(item.prices[0].price);
                               var allInclEl = targetCard.querySelector('.etb-quick-all-inclusive');
                                var selBtn    = targetCard.querySelector('.etb-quick-select-btn');

                                // Si LimoExpress renvoie 0 € -> CUSTOM QUOTE
                                if (limoPrice <= 0) {
                                    targetCard.dataset.calculatedPrice = 'Custom Quote';
                                    targetCard.dataset.isQuote = '1';

                                    var priceBox = targetCard.querySelector('.etb-quick-price-display');
                                    if (priceBox) {
                                        priceBox.innerHTML = '<span class="etb-quick-quote-badge">Custom Quote</span>';
                                    }

                                    var priceDetailEl = targetCard.querySelector('.etb-quick-price-detail');
                                    if (priceDetailEl) {
                                        priceDetailEl.innerHTML = 'Tailored pricing by dispatch';
                                        priceDetailEl.style.display = 'block';
                                    }

                                    if (allInclEl) allInclEl.style.display = 'none';
                                    if (selBtn) selBtn.textContent = 'Request Quote';
                                } else {
                                    // Tarif chiffré officiel LimoExpress
                                    targetCard.dataset.calculatedPrice = limoPrice;
                                    targetCard.dataset.isQuote = '0';

                                    var priceBox = targetCard.querySelector('.etb-quick-price-display');
                                    if (priceBox) {
                                        priceBox.innerHTML = '<span class="etb-quick-amount">' + limoPrice + '</span> <span class="etb-quick-currency">' + currency + '</span> <span class="etb-quick-all-inclusive">All inclusive</span>';
                                    }

                                    var priceDetailEl = targetCard.querySelector('.etb-quick-price-detail');
                                    if (priceDetailEl) {
                                        priceDetailEl.style.display = 'none';
                                        priceDetailEl.innerHTML = '';
                                    }
                                    if (selBtn) selBtn.textContent = 'Select';
                                
                                }

                                var kmInfoEl = targetCard.querySelector('.etb-quick-km-info');
                                if (kmInfoEl) { 
                                    kmInfoEl.textContent = ''; 
                                    kmInfoEl.classList.remove('is-visible'); 
                                }

                                targetCard.style.display = 'flex';
                                targetCard.classList.remove('selected', 'etb-hidden');
                                availableCarsCount++;
                            }
                        });

                        if (availableCarsCount > 0) {
                            isQuoteMode = false;
                            if (quoteNoticeEl) quoteNoticeEl.classList.remove('is-visible');
                            if (whatsappBtn) whatsappBtn.classList.remove('is-visible');
                            showFleetSection();
                        } else {
                            triggerCustomQuoteMode();
                        }
                    } else {
                        // Trajet simple hors zone -> Custom Quote
                        triggerCustomQuoteMode();
                    }
                })
                .catch(err => {
                    console.error('Pricing error:', err);
                    getPriceBtn.disabled = false;
                    getPriceBtn.classList.remove('etb-loading-glow'); // Éteint le faisceau
                    getPriceBtn.innerHTML = originalBtnText;
                    triggerCustomQuoteMode();
                });
            });
        }

        const showError = (msg) => {
            if (errorNotice) {
                errorNotice.textContent = '⚠ ' + msg;
                errorNotice.style.display = 'block';
                // Remonte automatiquement et en douceur la fenêtre vers le message d'erreur
                errorNotice.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };

        // 4. Sélection exclusive d'un véhicule
        carCards.forEach(card => {
            card.addEventListener('click', function () {
                const isCarQuote = (this.dataset.isQuote === '1' || isQuoteMode);

                // Désélection si on reclique sur la même carte
                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                    selectedCar = null;
                    if (bookingBar) bookingBar.classList.remove('is-visible');
                    const btn = this.querySelector('.etb-quick-select-btn');
                    if (btn) {
                        btn.textContent = (this.dataset.isQuote === '1' || isQuoteMode) ? 'Request Quote' : 'Select';
                    }
                    return;
                }

                // Désélectionne toutes les autres cartes
                carCards.forEach(c => {
                    c.classList.remove('selected');
                    const b = c.querySelector('.etb-quick-select-btn');
                    if (b) {
                        b.textContent = (c.dataset.isQuote === '1' || isQuoteMode) ? 'Request Quote' : 'Select';
                    }
                });

                // Active la carte cliquée
                this.classList.add('selected');
                const activeBtn = this.querySelector('.etb-quick-select-btn');
                if (activeBtn) {
                    activeBtn.textContent = 'Selected';
                }

                
               

                const carName  = this.querySelector('h4')?.textContent.trim() || 'Vehicle';
                const carPrice = this.dataset.calculatedPrice || '0';

                selectedCar = {
                    id: this.dataset.id,
                    name: carName,
                    price: carPrice,
                    isQuote: isCarQuote
                };

                // Affiche la barre de confirmation inférieure avec les 2 options (Option C)
                if (bookingBar) {
                    if (selectedNameEl) selectedNameEl.textContent = carName;

                    if (isCarQuote) {
                        if (selectedTotEl) selectedTotEl.textContent = 'Custom Quote';
                        if (bookBtnLabel) bookBtnLabel.textContent = 'Request this Quote';
                    } else {
                        if (selectedTotEl) selectedTotEl.textContent = carPrice + ' ' + currency;
                        if (bookBtnLabel) bookBtnLabel.textContent = 'Book this Trip';
                    }

                    // Données de la course pour WhatsApp et Mailto
                    const pVal      = pickupInput ? pickupInput.value.trim() : '';
                    const isHourly  = (currentMode === 'hourly');
                    const dVal      = isHourly ? `By the hour (${durationSelect ? durationSelect.value : 4} Hours rental)` : (dropoffInput ? dropoffInput.value.trim() : '');
                    const dtVal     = dateInput ? dateInput.value.trim() : '';
                    const tmVal     = timeInput ? timeInput.value.trim() : '';
                    const waPhone   = (typeof etbAjax !== 'undefined' && etbAjax.company_whatsapp) ? etbAjax.company_whatsapp : '';
                    const adminMail = (typeof etbAjax !== 'undefined' && etbAjax.admin_email) ? etbAjax.admin_email : '';

                    const dropoffSymbol = isHourly ? '> ↪🄷🄾🅄🅁🄻🅈' : '> ➘🄳🄾□';
                    const rateText      = isCarQuote ? 'Custom Quote (Pending dispatch)' : `${carPrice} ${currency}`;

                   
                    // Génération native de l'émoji main qui salue (Point de code UTF-8 pur)
                    const waveEmoji = String.fromCodePoint(0x1F44B);

                    // Mise en forme officielle stylisée demandée
                    const formattedMessage = `Hey *EDEN CAB* team ${waveEmoji},\n\n`
                        + `I would like to inquire about booking a transfer with:\n\n`
                        + `*${carName}*\n\n`
                        + `➚🄿🅄■ ${pVal}\n\n`
                        + `${dropoffSymbol} ${dVal}\n\n`
                        + `D|T: ${dtVal} at ${tmVal}\n\n`
                        + `•Indicative rate: ${rateText}`;

                    // 1. Injection WhatsApp via l'API directe (élimine le bug de wa.me sur PC)
                    if (whatsappBtn) {
                        const waText = encodeURIComponent(formattedMessage);
                        whatsappBtn.href = waPhone 
                            ? `https://api.whatsapp.com/send?phone=${waPhone}&text=${waText}` 
                            : `https://api.whatsapp.com/send?text=${waText}`;
                    }

                    // 2. Injection E-mail (Mailto avec le même résultat stylisé)
                    const emailInquiryBtn = quickRoot.querySelector('#etb-quick-email-btn');
                    if (emailInquiryBtn) {
                        const emailSubject = encodeURIComponent(`[Inquiry] ${carName} — ${dtVal} at ${tmVal}`);
                        // Version lisible pour l'e-mail avec les mêmes symboles
                        const emailBody = encodeURIComponent(formattedMessage.replace(/\*/g, '')); // Retire les astérisques markdown de WhatsApp pour l'e-mail
                        emailInquiryBtn.href = `mailto:${adminMail}?subject=${emailSubject}&body=${emailBody}`;
                    }


                    bookingBar.classList.add('is-visible');
                }
            });
        });


        // ==========================================================================
        // CONTRÔLEUR DU MICRO-MODAL VIP (EMAIL INQUIRY)
        // ==========================================================================
        const inquiryModal    = document.querySelector('#etb-quick-inquiry-modal');
        const emailInquiryBtn = quickRoot.querySelector('#etb-quick-email-btn');

        if (inquiryModal && emailInquiryBtn) {
            const closeBtn      = inquiryModal.querySelector('#etb-inquiry-close');
            const submitBtn     = inquiryModal.querySelector('#etb-inquiry-submit');
            const submitText    = inquiryModal.querySelector('#etb-inquiry-btn-text');
            const feedbackEl    = inquiryModal.querySelector('#etb-inquiry-feedback');
            const carNameEl     = inquiryModal.querySelector('#etb-inquiry-car-name');
            const carPriceEl    = inquiryModal.querySelector('#etb-inquiry-car-price');
            const routeEl       = inquiryModal.querySelector('#etb-inquiry-route');
            const datetimeEl    = inquiryModal.querySelector('#etb-inquiry-datetime');
            const formEl        = inquiryModal.querySelector('#etb-inquiry-form');

            // Variables de stockage du devis actif
            let activeInquiry = {
                vehicle: '',
                route: '',
                datetime: '',
                price: ''
            };

            // 1. Ouvrir le modal et pré-remplir les informations verrouillées
            emailInquiryBtn.addEventListener('click', function (e) {
                e.preventDefault();

                const vName = selectedCar ? selectedCar.name : 'Selected Vehicle';
                const pVal  = pickupInput ? pickupInput.value.trim() : 'Pickup location';
                const dVal  = (currentMode === 'transfer' && dropoffInput) ? dropoffInput.value.trim() : `By the hour (${durationSelect ? durationSelect.value : 4}h)`;
                const dtVal = dateInput ? dateInput.value.trim() : '';
                const tmVal = timeInput ? timeInput.value.trim() : '';

                let priceDisplay = 'Custom Quote';
                if (selectedCar && !selectedCar.isQuote && selectedCar.price && selectedCar.price !== 'Custom Quote') {
                    priceDisplay = `${selectedCar.price} ${currency}`;
                }

                // Mémorisation des détails
                activeInquiry.vehicle  = vName;
                activeInquiry.route    = (currentMode === 'transfer') ? `${pVal} ➔ ${dVal}` : `${pVal} (${dVal})`;
                activeInquiry.datetime = `${dtVal} at ${tmVal}`;
                activeInquiry.price    = priceDisplay;

                // Injection visuelle dans le récapitulatif du modal
                if (carNameEl)  carNameEl.textContent  = activeInquiry.vehicle;
                if (carPriceEl) carPriceEl.textContent = activeInquiry.price;
                if (routeEl)    routeEl.textContent    = activeInquiry.route;
                if (datetimeEl) datetimeEl.textContent = activeInquiry.datetime;

                // Réinitialisation du feedback
                if (feedbackEl) {
                    feedbackEl.style.display = 'none';
                    feedbackEl.className = 'etb-inquiry-feedback';
                    feedbackEl.textContent = '';
                }

            // Affichage du modal avec animation fluide
                inquiryModal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
            });

            // 2. Fonctions de fermeture fluide
            const closeModal = () => {
                inquiryModal.classList.remove('is-open');
                document.body.style.overflow = '';
            };

            if (closeBtn) closeBtn.addEventListener('click', closeModal);

            // Clic sur le fond flou extérieur pour fermer
            inquiryModal.addEventListener('click', function (e) {
                if (e.target === inquiryModal) {
                    closeModal();
                }
            });

            // Touche Échap pour fermer
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && inquiryModal.classList.contains('is-open')) {
                    closeModal();
                }
            });

            // 3. Soumission du formulaire AJAX
            if (submitBtn) {
                submitBtn.addEventListener('click', function (e) {
                    e.preventDefault();

                    const nameInput  = inquiryModal.querySelector('#etb_inq_name');
                    const emailInput = inquiryModal.querySelector('#etb_inq_email');
                    const phoneInput = inquiryModal.querySelector('#etb_inq_phone');
                    const notesInput = inquiryModal.querySelector('#etb_inq_notes');

                    const nameVal  = nameInput ? nameInput.value.trim() : '';
                    const emailVal = emailInput ? emailInput.value.trim() : '';
                    const phoneVal = phoneInput ? phoneInput.value.trim() : '';
                    const notesVal = notesInput ? notesInput.value.trim() : '';

                    if (!nameVal) {
                        showInquiryFeedback('Please enter your full name.', 'error');
                        if (nameInput) nameInput.focus();
                        return;
                    }

                    if (!emailVal || !emailInput.validity.valid) {
                        showInquiryFeedback('Please enter a valid email address.', 'error');
                        if (emailInput) emailInput.focus();
                        return;
                    }

                    const originalText = submitText ? submitText.textContent : 'Send My Inquiry';
                    submitBtn.disabled = true;
                    if (submitText) submitText.textContent = 'Sending Inquiry...';

                    const formData = new FormData();
                    formData.append('action', 'etb_send_email_inquiry');
                    formData.append('nonce', etbAjax.nonce);
                    formData.append('inquiry_name', nameVal);
                    formData.append('inquiry_email', emailVal);
                    formData.append('inquiry_phone', phoneVal);
                    formData.append('inquiry_notes', notesVal);
                    formData.append('vehicle_name', activeInquiry.vehicle);
                    formData.append('trip_route', activeInquiry.route);
                    formData.append('trip_datetime', activeInquiry.datetime);
                    formData.append('estimated_price', activeInquiry.price);

                    fetch(etbAjax.ajax_url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(res => {
                        submitBtn.disabled = false;
                        if (submitText) submitText.textContent = originalText;

                        if (res.success) {
                            showInquiryFeedback('✓ ' + res.data.message, 'success');
                            if (formEl) formEl.reset();

                            // Fermeture soyeuse et remise à zéro progressive
                            setTimeout(() => {
                                // 1. Fermeture douce du modal
                                closeModal();

                                // 2. Défilement doux vers le haut du widget
                                const widgetRect = quickRoot.getBoundingClientRect();
                                const absoluteTop = widgetRect.top + window.pageYOffset - 40;
                                window.scrollTo({
                                    top: Math.max(0, absoluteTop),
                                    behavior: 'smooth'
                                });

                                // 3. Réinitialisation des états après le départ du scroll
                                setTimeout(() => {
                                    // Retour automatique sur le mode One Way
                                    currentMode = 'transfer';
                                    modeBtns.forEach(btn => {
                                        if (btn.dataset.mode === 'transfer') {
                                            btn.classList.add('active');
                                        } else {
                                            btn.classList.remove('active');
                                        }
                                    });
                                    if (dropoffCol) dropoffCol.style.display = 'flex';
                                    if (durationCol) durationCol.style.display = 'none';

                                    // Vidage des adresses, heure et coordonnées GPS
                                    if (pickupInput) pickupInput.value = '';
                                    if (dropoffInput) dropoffInput.value = '';
                                    if (pickupLatEl) pickupLatEl.value = '';
                                    if (pickupLngEl) pickupLngEl.value = '';
                                    if (dropoffLatEl) dropoffLatEl.value = '';
                                    if (dropoffLngEl) dropoffLngEl.value = '';
                                    if (pickupClearBtn) pickupClearBtn.classList.remove('is-visible');
                                    if (dropoffClearBtn) dropoffClearBtn.classList.remove('is-visible');

                                    // Réinitialisation de l'heure à vide (-- : --)
                                    if (timeInput) timeInput.value = '';
                                    if (timePopup) {
                                        timePopup.classList.remove('is-open');
                                        timePopup.querySelectorAll('.etb-time-opt').forEach(o => o.classList.remove('active'));
                                    }

                                    // Réinitialisation de la durée par défaut (4 Hours)
                                    if (durationSelect) durationSelect.value = '4';
                                    const durLabel = customDurationWrapper ? customDurationWrapper.querySelector('.etb-custom-select-trigger span') : null;
                                    if (durLabel) durLabel.textContent = '4 Hours';
                                    if (customDurationWrapper) {
                                        customDurationWrapper.querySelectorAll('.etb-custom-option').forEach(opt => {
                                            opt.classList.toggle('selected', opt.dataset.val === '4');
                                        });
                                    }

                                    // Désélection et repli de la flotte
                                    selectedCar = null;
                                    carCards.forEach(c => {
                                        c.classList.remove('selected');
                                        const b = c.querySelector('.etb-quick-select-btn');
                                        if (b) b.textContent = 'Select';
                                        const km = c.querySelector('.etb-quick-km-info');
                                        if (km) {
                                            km.textContent = '';
                                            km.classList.remove('is-visible');
                                        }
                                    });

                                    if (bookingBar) bookingBar.classList.remove('is-visible');
                                    if (fleetSection) fleetSection.style.display = 'none';
                                    if (quoteNoticeEl) quoteNoticeEl.classList.remove('is-visible');
                                    isQuoteMode = false;

                                    // Rallumage de la bordure animée bicolore
                                    const glassBar = quickRoot.querySelector('.etb-quick-glass-bar');
                                    if (glassBar) {
                                        glassBar.classList.add('etb-initial-border');
                                    }
                                }, 350);

                            }, 2000);
                        } else {
                            showInquiryFeedback('⚠ ' + (res.data.message || 'An error occurred.'), 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Inquiry AJAX error:', err);
                        submitBtn.disabled = false;
                        if (submitText) submitText.textContent = originalText;
                        showInquiryFeedback('⚠ Network communication error. Please try again.', 'error');
                    });
                });
            }

            // Fonction utilitaire de feedback
            const showInquiryFeedback = (msg, type) => {
                if (!feedbackEl) return;
                feedbackEl.textContent = msg;
                feedbackEl.className = 'etb-inquiry-feedback is-' + type;
                feedbackEl.style.display = 'block';
            };
        }
        

        // 5. Clic sur "Book this Trip" : Génération du Quote Token sécurisé et redirection
        if (bookNowBtn) {
            bookNowBtn.addEventListener('click', function (e) {
                e.preventDefault();

                if (!selectedCar) {
                    showError('Please select a vehicle from the list above before proceeding.');
                    return;
                }

                const pickupVal  = pickupInput ? pickupInput.value.trim() : '';
                const dropoffVal = (currentMode === 'transfer' && dropoffInput) ? dropoffInput.value.trim() : '';
                const durVal     = (currentMode === 'hourly' && durationSelect) ? durationSelect.value : '4';
                const dateVal    = dateInput ? dateInput.value.trim() : '';
                const timeVal    = timeInput ? timeInput.value.trim() : '';

                // État de chargement discret sur le bouton
                const originalBtnHtml = bookNowBtn.innerHTML;
                bookNowBtn.disabled = true;
                bookNowBtn.innerHTML = '<span>Securing quote...</span>';

                // Préparation de la requête AJAX vers notre générateur de devis
                const formData = new FormData();
                formData.append('action', 'etb_create_quote');
                formData.append('nonce', etbAjax.nonce);
                formData.append('mode', currentMode);
                formData.append('pickup', pickupVal);
                formData.append('dropoff', dropoffVal);
                formData.append('duration', durVal);
                formData.append('date', dateVal);
                formData.append('time', timeVal);
                formData.append('vehicle_id', selectedCar.id);
                formData.append('price', selectedCar.price);

                fetch(etbAjax.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(function (response) {
                    return response.json();
                })
                .then(function (res) {
                    if (res.success && res.data.redirect_url) {
                        // Redirection immédiate vers l'URL propre Blacklane : /checkout/?ref=q_XXXX
                        window.location.href = res.data.redirect_url;
                    } else {
                        bookNowBtn.disabled = false;
                        bookNowBtn.innerHTML = originalBtnHtml;
                        showError(res.data.message || 'Could not initialize quote. Please try again.');
                    }
                })
                .catch(function (err) {
                    console.error('Quote generation error:', err);
                    bookNowBtn.disabled = false;
                    bookNowBtn.innerHTML = originalBtnHtml;
                    showError('Communication error. Please try again.');
                });
            });
        }
                      
    };

    // ==========================================================================
    // MOTEUR DU CHECKOUT BLACKLANE ([etb_checkout])
    // ==========================================================================
    // ==========================================================================
    // MOTEUR DU CHECKOUT BLACKLANE ([etb_checkout]) - SYNCHRONISÉ ET SÉCURISÉ
    // ==========================================================================
    const initCheckoutApp = function () {
        const checkoutRoot = document.querySelector('#etb-checkout-app');
        if (!checkoutRoot) return;

        // 1. Éléments du formulaire et panneaux
        const formEl          = checkoutRoot.querySelector('#etb-checkout-form');
        const submitBtn       = checkoutRoot.querySelector('#etb-chk-submit-btn');
        const submitText      = checkoutRoot.querySelector('#etb-chk-submit-text');
        const feedbackEl      = checkoutRoot.querySelector('#etb-chk-feedback');

        const step1Panel      = checkoutRoot.querySelector('#etb-chk-step-1');
        const step2Panel      = checkoutRoot.querySelector('#etb-chk-step-2');
        const backToStep1Btn  = checkoutRoot.querySelector('#etb-chk-back-to-step1');

        // Champs cachés de la course
        const modeInput       = checkoutRoot.querySelector('#etb-chk-mode');
        const pickupInput     = checkoutRoot.querySelector('#etb-chk-pickup');
        const dropoffInput    = checkoutRoot.querySelector('#etb-chk-dropoff');
        const durationInput   = checkoutRoot.querySelector('#etb-chk-duration');
        const dateInput       = checkoutRoot.querySelector('#etb-chk-date');
        const timeInput       = checkoutRoot.querySelector('#etb-chk-time');
        const vehicleIdInput  = checkoutRoot.querySelector('#etb-chk-vehicle-id');
        const priceInput      = checkoutRoot.querySelector('#etb-chk-price');

        // Champs passager
        const firstNameInput  = checkoutRoot.querySelector('#etb-passenger-first-name');
        const lastNameInput   = checkoutRoot.querySelector('#etb-passenger-last-name');
        const pickupSignInput = checkoutRoot.querySelector('#etb-pickup-sign');
        const airportCard     = checkoutRoot.querySelector('#etb-chk-airport-card');

        // Bascule Myself vs Guest
        const toggleMyself    = checkoutRoot.querySelector('#etb-toggle-myself');
        const toggleGuest     = checkoutRoot.querySelector('#etb-toggle-guest');
        const guestFields     = checkoutRoot.querySelector('#etb-guest-booker-fields');

        // Compteurs passagers, bagages et sièges enfants
        const paxMinusBtn     = checkoutRoot.querySelector('.etb-pax-minus');
        const paxPlusBtn      = checkoutRoot.querySelector('.etb-pax-plus');
        const paxInputEl      = checkoutRoot.querySelector('#etb-chk-pax-input');
        const paxHint         = checkoutRoot.querySelector('#etb-chk-max-pax-hint');

        const bagMinusBtn     = checkoutRoot.querySelector('.etb-bag-minus');
        const bagPlusBtn      = checkoutRoot.querySelector('.etb-bag-plus');
        const bagInputEl      = checkoutRoot.querySelector('#etb-chk-bag-input');
        const bagHint         = checkoutRoot.querySelector('#etb-chk-max-bag-hint');

        const seatMinusBtn    = checkoutRoot.querySelector('.etb-seat-minus');
        const seatPlusBtn     = checkoutRoot.querySelector('.etb-seat-plus');
        const seatInput       = checkoutRoot.querySelector('#etb-chk-baby-seats');

        // Champs de carte bancaire & Initialisation Stripe
        const cardNumInput    = checkoutRoot.querySelector('#etb-card-number');
        const cardExpInput    = checkoutRoot.querySelector('#etb-card-expiry');
        const cardCvcInput    = checkoutRoot.querySelector('#etb-card-cvc');
        const cardBrandBadges = checkoutRoot.querySelectorAll('.etb-brand-badge');

        let stripeInstance    = null;
        let stripeCardElement = null;
        let detectedBrand     = 'card';

        // Initialisation officielle Stripe Elements si la passerelle est active
        if (typeof Stripe !== 'undefined' && typeof etbAjax !== 'undefined' && etbAjax.stripe_enabled === '1' && etbAjax.stripe_pk) {
            try {
                stripeInstance = Stripe(etbAjax.stripe_pk);
                const elements = stripeInstance.elements();

                // Détection de la couleur du texte selon le thème actif
                const getStripeThemeStyle = () => {
                    const isLight = document.documentElement.getAttribute('data-etb-theme') === 'light' 
                                 || localStorage.getItem('etb_theme_mode') === 'light';
                    return {
                        base: {
                            color: isLight ? '#1e293b' : '#ffffff',
                            fontFamily: "'Inter', -apple-system, sans-serif",
                            fontSize: '14px',
                            fontWeight: '500',
                            letterSpacing: '0.04em',
                            '::placeholder': { color: isLight ? '#94a3b8' : 'rgba(148, 163, 184, 0.6)' },
                            iconColor: '#fbac18'
                        },
                        invalid: {
                            color: '#f87171',
                            iconColor: '#f87171'
                        }
                    };
                };

                stripeCardElement = elements.create('card', {
                    hidePostalCode: true,
                    style: getStripeThemeStyle()
                });

                const mountPoint = checkoutRoot.querySelector('#etb-stripe-card-mount');
                if (mountPoint) {
                    stripeCardElement.mount('#etb-stripe-card-mount');

                    // Allumage automatique des badges Visa / MC / Amex au fur et à mesure de la saisie
                    stripeCardElement.on('change', function (event) {
                        cardBrandBadges.forEach(b => b.classList.remove('active'));
                        if (event.brand && event.brand !== 'unknown') {
                            detectedBrand = event.brand;
                            const badgeSelector = event.brand === 'mastercard' ? '.etb-brand-badge.mc' : `.etb-brand-badge.${event.brand}`;
                            const activeBadge = checkoutRoot.querySelector(badgeSelector);
                            if (activeBadge) activeBadge.classList.add('active');
                        }
                        if (event.error) {
                            showFeedback(event.error.message, 'error');
                        } else if (feedbackEl) {
                            feedbackEl.style.display = 'none';
                        }
                    });
                }
            } catch (err) {
                console.error('Stripe initialization error:', err);
            }
        }

        // Éléments du tableau comptable gauche
        const payBaseFareEl   = checkoutRoot.querySelector('#etb-pay-base-fare');
        const payTipRowEl     = checkoutRoot.querySelector('#etb-pay-tip-row');
        const payTipAmountEl  = checkoutRoot.querySelector('#etb-pay-tip-amount');
        const payGrandTotalEl = checkoutRoot.querySelector('#etb-pay-grand-total');
        const payTotalDueEl   = checkoutRoot.querySelector('#etb-pay-total-due');

        // Éléments de la colonne droite (Summary Sticky)
        const sumVehicleName  = checkoutRoot.querySelector('#etb-chk-summary-vehicle-name');
        const sumImg          = checkoutRoot.querySelector('#etb-chk-summary-img');
        const sumPax          = checkoutRoot.querySelector('#etb-chk-pax-count');
        const sumBag          = checkoutRoot.querySelector('#etb-chk-bag-count');
        const sumPickup       = checkoutRoot.querySelector('#etb-chk-summary-pickup');
        const sumDropoff      = checkoutRoot.querySelector('#etb-chk-summary-dropoff');
        const sumDropoffRow   = checkoutRoot.querySelector('#etb-chk-timeline-dropoff-row');
        const sumDuration     = checkoutRoot.querySelector('#etb-chk-summary-duration');
        const sumDurationRow  = checkoutRoot.querySelector('#etb-chk-timeline-duration-row');
        const sumDatetime     = checkoutRoot.querySelector('#etb-chk-summary-datetime');
        const sumBasePrice    = checkoutRoot.querySelector('#etb-chk-breakdown-base');
        const sumTotalPrice   = checkoutRoot.querySelector('#etb-chk-total-price');

        // Éléments pourboire
        const tipPills        = checkoutRoot.querySelectorAll('.etb-tip-pill');
        const tipAmountInput  = checkoutRoot.querySelector('#etb-chk-tip-amount');
        const tipRow          = checkoutRoot.querySelector('#etb-chk-tip-row');
        const tipPercentText  = checkoutRoot.querySelector('#etb-chk-tip-percent');
        const tipBreakdown    = checkoutRoot.querySelector('#etb-chk-breakdown-tip');

        const currency = (typeof etbAjax !== 'undefined' && etbAjax.currency) ? etbAjax.currency : '€';

        // Variables d'état globales déclarées en premier pour éviter toute ReferenceError
        let currentCheckoutStep = 1;
        let baseNumericPrice    = 0;
        let isQuoteRide         = false;

        // 2. Fonction de recalcul du pourboire (accessible partout)
        const recalculateTotalWithTip = (tipPercent) => {
            if (isQuoteRide) {
                if (tipRow) {
                    tipRow.classList.remove('is-visible');
                    tipRow.style.setProperty('display', 'none', 'important');
                }
                if (payTipRowEl) {
                    payTipRowEl.classList.add('is-hidden');
                    payTipRowEl.style.setProperty('display', 'none', 'important');
                }
                if (tipAmountInput) tipAmountInput.value = '0';
                if (sumTotalPrice) sumTotalPrice.textContent = 'Custom Quote';
                if (payGrandTotalEl) payGrandTotalEl.textContent = 'Custom Quote';
                if (payTotalDueEl) payTotalDueEl.textContent = 'Custom Quote';
                return;
            }

            const tipVal = Math.round((baseNumericPrice * (tipPercent / 100)) * 100) / 100;
            if (tipAmountInput) tipAmountInput.value = tipVal;

            const totalWithTip   = baseNumericPrice + tipVal;
            const formattedTotal = totalWithTip.toFixed(2) + ' ' + currency;

            if (tipVal > 0) {
                // Colonne Droite
                if (tipRow) {
                    tipRow.classList.add('is-visible');
                    tipRow.style.setProperty('display', 'flex', 'important');
                }
                if (tipPercentText) tipPercentText.textContent = `${tipPercent}%`;
                if (tipBreakdown) tipBreakdown.textContent = `+ ${tipVal.toFixed(2)} ${currency}`;
                if (sumTotalPrice) sumTotalPrice.textContent = formattedTotal;

                // Colonne Gauche
                if (payTipRowEl) {
                    payTipRowEl.classList.remove('is-hidden');
                    payTipRowEl.style.setProperty('display', 'flex', 'important');
                }
                if (payTipAmountEl) payTipAmountEl.textContent = `+ ${tipVal.toFixed(2)} ${currency}`;
                if (payGrandTotalEl) payGrandTotalEl.textContent = formattedTotal;
                if (payTotalDueEl) payTotalDueEl.textContent = formattedTotal;
            } else {
                const baseFormatted = baseNumericPrice.toFixed(0) + ' ' + currency;

                if (tipRow) {
                    tipRow.classList.remove('is-visible');
                    tipRow.style.setProperty('display', 'none', 'important');
                }
                if (sumTotalPrice) sumTotalPrice.textContent = baseFormatted;

                if (payTipRowEl) {
                    payTipRowEl.classList.add('is-hidden');
                    payTipRowEl.style.setProperty('display', 'none', 'important');
                }
                if (payGrandTotalEl) payGrandTotalEl.textContent = baseFormatted;
                if (payTotalDueEl) payTotalDueEl.textContent = baseFormatted;
            }

            // Mise à jour du bouton si l'utilisateur est déjà sur l'écran de paiement
            if (currentCheckoutStep === 2 && submitText) {
                const finalBtnTotal = (tipVal > 0) ? formattedTotal : (baseNumericPrice.toFixed(0) + ' ' + currency);
                submitText.textContent = `Pay ${finalBtnTotal} Now`;
            }
        };

        // 3. Hydratation automatique depuis le serveur (Quote Token) ou les paramètres
        const hydrateFromHandoff = () => {
            const urlParams = new URLSearchParams(window.location.search);
            let sessionData = {};

            try {
                const rawSession = sessionStorage.getItem('etb_checkout_data');
                if (rawSession) sessionData = JSON.parse(rawSession);
            } catch (e) {}

            // Lecture prioritaire des champs scellés par le serveur dans le template HTML
            const tripMode    = (modeInput && modeInput.value) ? modeInput.value : (urlParams.get('mode') || sessionData.mode || 'transfer');
            const pickupAddr  = (pickupInput && pickupInput.value) ? pickupInput.value : (urlParams.get('pickup') || sessionData.pickup || '');
            const dropoffAddr = (dropoffInput && dropoffInput.value) ? dropoffInput.value : (urlParams.get('dropoff') || sessionData.dropoff || '');
            const durVal      = (durationInput && durationInput.value) ? durationInput.value : (urlParams.get('duration') || sessionData.duration || '4');
            const dateVal     = (dateInput && dateInput.value) ? dateInput.value : (urlParams.get('date') || sessionData.date || '');
            const timeVal     = (timeInput && timeInput.value) ? timeInput.value : (urlParams.get('time') || sessionData.time || '');
            const vehicleId   = (vehicleIdInput && vehicleIdInput.value) ? vehicleIdInput.value : (urlParams.get('vehicle_id') || sessionData.vehicle_id || '');
            
            // Récupération du prix : input scellé PHP > session > défaut
            let rawPrice = (priceInput && priceInput.value && priceInput.value !== '0') ? priceInput.value : (urlParams.get('price') || sessionData.price || '');
            if (!rawPrice) rawPrice = 'Custom Quote';

            // Synchronisation dans les champs cachés
            if (modeInput) modeInput.value = tripMode;
            if (pickupInput) pickupInput.value = pickupAddr;
            if (dropoffInput) dropoffInput.value = dropoffAddr;
            if (durationInput) durationInput.value = durVal;
            if (dateInput) dateInput.value = dateVal;
            if (timeInput) timeInput.value = timeVal;
            if (vehicleIdInput) vehicleIdInput.value = vehicleId;
            if (priceInput) priceInput.value = rawPrice;

            // Affichage de l'itinéraire dans le récapitulatif
            if (sumPickup && pickupAddr) sumPickup.textContent = pickupAddr;

            if (tripMode === 'hourly') {
                if (sumDropoffRow) {
                    sumDropoffRow.classList.add('is-hidden');
                    sumDropoffRow.style.setProperty('display', 'none', 'important');
                }
                if (sumDurationRow) {
                    sumDurationRow.classList.remove('is-hidden');
                    sumDurationRow.style.setProperty('display', 'flex', 'important');
                    if (sumDuration) sumDuration.textContent = `${durVal} Hours rental`;
                }
            } else {
                if (sumDurationRow) {
                    sumDurationRow.classList.add('is-hidden');
                    sumDurationRow.style.setProperty('display', 'none', 'important');
                }
                if (sumDropoffRow) {
                    sumDropoffRow.classList.remove('is-hidden');
                    sumDropoffRow.style.setProperty('display', 'flex', 'important');
                    if (sumDropoff && dropoffAddr) sumDropoff.textContent = dropoffAddr;
                }
            }

            if (sumDatetime && dateVal && timeVal) {
                sumDatetime.textContent = `${dateVal} at ${timeVal}`;
            }

            // Calcul financier de base
            baseNumericPrice = parseFloat(rawPrice) || 0;
            isQuoteRide      = (rawPrice === 'Custom Quote' || baseNumericPrice <= 0);

            const formattedPrice = isQuoteRide ? 'Custom Quote' : `${baseNumericPrice.toFixed(0)} ${currency}`;
            if (sumBasePrice) sumBasePrice.textContent = formattedPrice;
            if (sumTotalPrice) sumTotalPrice.textContent = formattedPrice;

            // Détection aéroport contextuelle
            const checkAirportKeywords = (str) => {
                if (!str) return false;
                return /\b(airport|aeroport|aéroport|terminal|aeropuerto|flughafen|nce|cdg|ory|heathrow|gatwick|jfk)\b/i.test(str);
            };

            if (airportCard) {
                const isPickupAirport  = checkAirportKeywords(pickupAddr);
                const isDropoffAirport = checkAirportKeywords(dropoffAddr);

                const airportTitle = checkoutRoot.querySelector('#etb-chk-airport-title');
                const airportDesc  = checkoutRoot.querySelector('#etb-chk-airport-desc');
                const flightLabel  = checkoutRoot.querySelector('#etb-chk-flight-label');
                const flightHint   = checkoutRoot.querySelector('#etb-chk-flight-hint');
                const signField    = checkoutRoot.querySelector('#etb-chk-sign-field');
                const airportGrid  = checkoutRoot.querySelector('#etb-chk-airport-grid');
                const waitTimeText = checkoutRoot.querySelector('#etb-chk-wait-time-text');

                if (isPickupAirport) {
                    airportCard.style.display = 'block';
                    if (airportTitle) airportTitle.textContent = 'Airport Arrival & Greeting';
                    if (airportDesc)  airportDesc.textContent  = 'Real-time flight tracking included. Your chauffeur tracks your flight and adjusts pickup time automatically.';
                    if (flightLabel)  flightLabel.textContent  = 'Airline & Flight Number (e.g. AF 7704)';
                    if (flightHint)   flightHint.textContent   = '60 minutes complimentary wait time included after flight landing.';
                    if (signField)    signField.style.display  = 'block';
                    if (airportGrid)  airportGrid.style.gridTemplateColumns = '';
                    if (waitTimeText) waitTimeText.textContent = '60 min complimentary wait time included (flight tracking)';
                } else if (isDropoffAirport) {
                    airportCard.style.display = 'block';
                    if (airportTitle) airportTitle.textContent = 'Airport Drop-off & Departure Terminal';
                    if (airportDesc)  airportDesc.textContent  = 'Helps your chauffeur drop you off directly in front of the correct departures terminal gate.';
                    if (flightLabel)  flightLabel.textContent  = 'Flight Number or Departure Terminal (Optional)';
                    if (flightHint)   flightHint.textContent   = 'e.g. Terminal 2E, AF 7704 — Ensures a seamless curbside drop-off.';
                    if (signField) {
                        signField.style.display = 'none';
                        const signInput = signField.querySelector('input');
                        if (signInput) signInput.value = '';
                    }
                    if (airportGrid)  airportGrid.style.gridTemplateColumns = '1fr';
                    if (waitTimeText) waitTimeText.textContent = '15 min complimentary wait time included';
                } else {
                    airportCard.style.display = 'none';
                    if (waitTimeText) waitTimeText.textContent = '15 min complimentary wait time included';
                }
            }

            // Initialisation des compteurs de passagers/bagages avec plafond fixe
            const maxPaxAllowed = parseInt(sumPax ? sumPax.textContent : 3, 10) || 3;
            const maxBagAllowed = parseInt(sumBag ? sumBag.textContent : 2, 10) || 2;

            if (paxHint) paxHint.textContent = `Max: ${maxPaxAllowed}`;
            if (bagHint) bagHint.textContent = `Max: ${maxBagAllowed}`;

            if (paxInputEl) {
                paxInputEl.value = '1';
                paxInputEl.dataset.max = maxPaxAllowed;
            }
            if (bagInputEl) {
                bagInputEl.value = Math.min(1, maxBagAllowed);
                bagInputEl.dataset.max = maxBagAllowed;
            }

            // Calcul initial du pourboire à 0%
            recalculateTotalWithTip(0);
        };

        hydrateFromHandoff();

        // 4. Écouteurs des pilules de pourboire
        tipPills.forEach(pill => {
            pill.addEventListener('click', function () {
                tipPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');

                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;

                const percent = parseInt(this.dataset.tip, 10) || 0;
                recalculateTotalWithTip(percent);
            });
        });

        // 5. Bascule Booker Type (Myself / Guest)
        if (toggleMyself && toggleGuest && guestFields) {
            toggleMyself.addEventListener('click', function () {
                toggleMyself.classList.add('active');
                toggleGuest.classList.remove('active');
                guestFields.style.display = 'none';
            });

            toggleGuest.addEventListener('click', function () {
                toggleGuest.classList.add('active');
                toggleMyself.classList.remove('active');
                guestFields.style.display = 'block';
            });
        }

        // 6. Auto-remplissage de la pancarte
        const updateGreetingSign = () => {
            const fName = firstNameInput ? firstNameInput.value.trim() : '';
            const lName = lastNameInput ? lastNameInput.value.trim() : '';
            if (pickupSignInput && !pickupSignInput.dataset.manualEdit) {
                if (fName || lName) {
                    pickupSignInput.value = `Mr./Ms. ${lName || fName}`;
                }
            }
        };

        if (firstNameInput) firstNameInput.addEventListener('input', updateGreetingSign);
        if (lastNameInput) lastNameInput.addEventListener('input', updateGreetingSign);
        if (pickupSignInput) {
            pickupSignInput.addEventListener('input', function () {
                this.dataset.manualEdit = '1';
            });
        }

        // 7. Compteurs Passagers et Bagages
        if (paxMinusBtn && paxPlusBtn && paxInputEl) {
            paxMinusBtn.addEventListener('click', function () {
                let val = parseInt(paxInputEl.value, 10) || 1;
                if (val > 1) {
                    paxInputEl.value = val - 1;
                }
            });

            paxPlusBtn.addEventListener('click', function () {
                let val = parseInt(paxInputEl.value, 10) || 1;
                const maxLimit = parseInt(paxInputEl.dataset.max, 10) || 3;
                if (val < maxLimit) {
                    paxInputEl.value = val + 1;
                }
            });
        }

        if (bagMinusBtn && bagPlusBtn && bagInputEl) {
            bagMinusBtn.addEventListener('click', function () {
                let val = parseInt(bagInputEl.value, 10) || 0;
                if (val > 0) {
                    bagInputEl.value = val - 1;
                }
            });

            bagPlusBtn.addEventListener('click', function () {
                let val = parseInt(bagInputEl.value, 10) || 0;
                const maxLimit = parseInt(bagInputEl.dataset.max, 10) || 2;
                if (val < maxLimit) {
                    bagInputEl.value = val + 1;
                }
            });
        }

        // 8. Contrôle des sièges enfants (+/-)
        if (seatMinusBtn && seatPlusBtn && seatInput) {
            seatMinusBtn.addEventListener('click', function () {
                let val = parseInt(seatInput.value, 10) || 0;
                if (val > 0) seatInput.value = val - 1;
            });

            seatPlusBtn.addEventListener('click', function () {
                let val = parseInt(seatInput.value, 10) || 0;
                if (val < 4) seatInput.value = val + 1;
            });
        }

        // 9. Formatage et allumage de la marque de carte
        if (cardNumInput) {
            cardNumInput.addEventListener('input', function () {
                let val = this.value.replace(/\D/g, '').substring(0, 16);
                let formatted = val.replace(/(\d{4})(?=\d)/g, '$1 ');
                this.value = formatted;

                cardBrandBadges.forEach(b => b.classList.remove('active'));
                if (val.startsWith('4')) {
                    const visa = checkoutRoot.querySelector('.etb-brand-badge.visa');
                    if (visa) visa.classList.add('active');
                } else if (/^(5[1-5]|2[2-7])/.test(val)) {
                    const mc = checkoutRoot.querySelector('.etb-brand-badge.mc');
                    if (mc) mc.classList.add('active');
                } else if (/^(34|37)/.test(val)) {
                    const amex = checkoutRoot.querySelector('.etb-brand-badge.amex');
                    if (amex) amex.classList.add('active');
                }
            });
        }

        if (cardExpInput) {
            cardExpInput.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace') {
                    const val = this.value;
                    if (val.length === 5 && this.selectionStart >= 3) {
                        e.preventDefault();
                        this.value = val.substring(0, 1);
                    } else if (val.endsWith(' / ')) {
                        e.preventDefault();
                        this.value = val.slice(0, -3);
                    }
                }
            });

            cardExpInput.addEventListener('input', function (e) {
                if (e.inputType === 'deleteContentBackward') return;
                let digits = this.value.replace(/\D/g, '');

                if (digits.length >= 1) {
                    if (digits.length === 1 && parseInt(digits, 10) > 1) digits = '0' + digits;
                    if (digits.length >= 2) {
                        let month = parseInt(digits.substring(0, 2), 10);
                        if (month === 0) month = 1;
                        if (month > 12) month = 12;
                        digits = String(month).padStart(2, '0') + digits.substring(2);
                    }
                }

                digits = digits.substring(0, 4);
                this.value = (digits.length >= 2) ? (digits.substring(0, 2) + ' / ' + digits.substring(2)) : digits;
            });
        }

        if (cardCvcInput) {
            cardCvcInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').substring(0, 4);
            });
        }

        // 10. Custom Select Pays
        const countrySelectWrapper = checkoutRoot.querySelector('#etb-country-custom-select');
        if (countrySelectWrapper) {
            const countryTrigger = countrySelectWrapper.querySelector('.etb-custom-select-trigger');
            const countryLabel   = countrySelectWrapper.querySelector('#etb-country-selected-label');
            const countryOptions = countrySelectWrapper.querySelectorAll('.etb-custom-option');
            const countryInput   = checkoutRoot.querySelector('#etb-card-country');

            countryTrigger.addEventListener('click', function (e) {
                e.stopPropagation();
                countrySelectWrapper.classList.toggle('is-open');
            });

            countryOptions.forEach(opt => {
                opt.addEventListener('click', function (e) {
                    e.stopPropagation();
                    countryOptions.forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');

                    const val  = this.dataset.val;
                    const text = this.textContent.trim();

                    if (countryLabel) countryLabel.textContent = text;
                    if (countryInput) countryInput.value = val;

                    countrySelectWrapper.classList.remove('is-open');
                });
            });

            document.addEventListener('click', function (e) {
                if (!countrySelectWrapper.contains(e.target)) {
                    countrySelectWrapper.classList.remove('is-open');
                }
            });
        }

        // 11. Lien retour : Revenir à l'Étape 1 (Coordonnées)
        if (backToStep1Btn) {
            backToStep1Btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (step2Panel) step2Panel.style.display = 'none';
                if (step1Panel) step1Panel.style.display = 'block';
                currentCheckoutStep = 1;
                if (submitText) submitText.textContent = 'Continue'; // bouton continue to payement info
                checkoutRoot.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        // 12. Clic sur le Bouton Principal (Proceed to Payment -> Pay Now)
        if (submitBtn) {
            submitBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (feedbackEl) feedbackEl.style.display = 'none';

                // ÉTAPE 1 : Validation des coordonnées passager
                if (currentCheckoutStep === 1) {
                    const fNameVal  = firstNameInput ? firstNameInput.value.trim() : '';
                    const lNameVal  = lastNameInput ? lastNameInput.value.trim() : '';
                    const emailVal  = checkoutRoot.querySelector('#etb-passenger-email')?.value.trim() || '';
                    const phoneVal  = checkoutRoot.querySelector('#etb-passenger-phone')?.value.trim() || '';
                    const vehicleId = vehicleIdInput ? vehicleIdInput.value : '';

                    if (!fNameVal || !lNameVal) {
                        return showFeedback('Please enter the passenger first and last name.', 'error');
                    }
                    if (!emailVal || !emailVal.includes('@')) {
                        return showFeedback('Please enter a valid email address for ride confirmation.', 'error');
                    }
                    if (!phoneVal) {
                        return showFeedback('Please enter a mobile phone number for chauffeur SMS updates.', 'error');
                    }
                    if (!vehicleId) {
                        return showFeedback('No vehicle selected. Please return and choose a vehicle.', 'error');
                    }

                    // Bascule visuelle vers le paiement
                    const currentTotalText = sumTotalPrice ? sumTotalPrice.textContent.trim() : '0 €';
                    const baseFareText     = sumBasePrice ? sumBasePrice.textContent.trim() : '0 €';
                    const tipVal           = checkoutRoot.querySelector('#etb-chk-tip-amount')?.value || '0';

                    if (payBaseFareEl)   payBaseFareEl.textContent   = baseFareText;
                    if (payGrandTotalEl) payGrandTotalEl.textContent = currentTotalText;
                    if (payTotalDueEl)   payTotalDueEl.textContent   = currentTotalText;

                    if (parseFloat(tipVal) > 0 && payTipRowEl && payTipAmountEl) {
                        payTipRowEl.classList.remove('is-hidden');
                        payTipRowEl.style.setProperty('display', 'flex', 'important');
                        payTipAmountEl.textContent = `+ ${parseFloat(tipVal).toFixed(2)} ${currency}`;
                    } else if (payTipRowEl) {
                        payTipRowEl.classList.add('is-hidden');
                        payTipRowEl.style.setProperty('display', 'none', 'important');
                    }

                    if (step1Panel) step1Panel.style.display = 'none';
                    if (step2Panel) step2Panel.style.display = 'block';
                    currentCheckoutStep = 2;

                    // Auto-remplissage du Nom sur la carte avec le nom du voyageur
                    const cardHolderInput = checkoutRoot.querySelector('#etb-cardholder-name');
                    if (cardHolderInput && !cardHolderInput.value) {
                        cardHolderInput.value = fNameVal + ' ' + lNameVal;
                    }

                    if (submitText) {
                        submitText.textContent = 'Confirm & Book Now';
                    }

                    checkoutRoot.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    return;
                }

                // ─────────────────────────────────────────────────────────────
                // SI ON EST À L'ÉTAPE 2 : Sécurisation Stripe 3D Secure & Dispatch
                // ─────────────────────────────────────────────────────────────
                if (currentCheckoutStep === 2) {
                    const originalText = submitText ? submitText.textContent : 'Pay Now';
                    submitBtn.disabled = true;
                    if (submitText) submitText.textContent = 'Securing & Authorizing...';

                    const fNameVal  = firstNameInput ? firstNameInput.value.trim() : '';
                    const lNameVal  = lastNameInput ? lastNameInput.value.trim() : '';
                    const emailVal  = checkoutRoot.querySelector('#etb-passenger-email')?.value.trim() || '';
                    const phoneVal  = checkoutRoot.querySelector('#etb-passenger-phone')?.value.trim() || '';
                    const tipVal    = checkoutRoot.querySelector('#etb-chk-tip-amount')?.value || '0';
                    const finalDue  = baseNumericPrice + parseFloat(tipVal);

                    // Fonction interne de finalisation de commande
                    const executeFinalOrderDispatch = (paymentLogsData) => {
                        if (submitText) submitText.textContent = 'Dispatching ...';

                        const formData = new FormData(formEl);
                        formData.append('action', 'etb_submit_checkout');
                        formData.append('nonce', etbAjax.nonce);

                        const activeTipRadio = checkoutRoot.querySelector('input[name="etb_driver_tip"]:checked');
                        formData.set('etb_tip_amount', tipVal);
                        formData.set('etb_driver_tip', activeTipRadio ? activeTipRadio.value : '0');

                       // Transmission des métadonnées Stripe pour LimoExpress
                        if (paymentLogsData) {
                            formData.append('etb_payment_intent_id', paymentLogsData.id || '');
                            formData.append('etb_stripe_customer_id', paymentLogsData.customer || '');
                            formData.append('etb_card_last4', paymentLogsData.last4 || '');
                            formData.append('etb_card_brand', paymentLogsData.brand || 'card');
                            formData.append('etb_card_exp', paymentLogsData.exp || '');
                            formData.append('etb_payment_method', 'card');
                        }

                        fetch(etbAjax.ajax_url, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(res => {
                            submitBtn.disabled = false;
                            if (submitText) submitText.textContent = originalText;

                            if (res.success) {
                                try { sessionStorage.removeItem('etb_checkout_data'); } catch (e) {}

                                const homeReturnUrl = (typeof etbAjax !== 'undefined' && etbAjax.home_url) 
                                    ? etbAjax.home_url 
                                    : (window.location.origin + '/');

                                const leftCol = checkoutRoot.querySelector('.etb-checkout-left-col');
                                if (leftCol) {
                                    leftCol.innerHTML = '<div class="etb-checkout-card" style="text-align: center; padding: 45px 30px; border-color: #16a34a; background: #0b1410;">'
                                        + '<div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; background: rgba(34, 197, 94, 0.15); border: 2px solid #22c55e; border-radius: 50%; margin-bottom: 18px; color: #4ade80;">'
                                        + '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
                                        + '</div>'
                                        + '<h2 style="color: #4ade80; font-size: 24px; font-weight: 800; margin: 0 0 10px 0;">Payment Authorized & Reservation Confirmed!</h2>'
                                        + '<p style="color: #cbd5e1; font-size: 15px; margin-bottom: 25px; line-height: 1.5;">'
                                        + 'Your VIP transfer dossier <strong>#' + res.data.booking_id + '</strong> has been confirmed and dispatched to LimoExpress (Course <strong>#' + res.data.limo_id + '</strong>).'
                                        + '</p>'
                                        + '<div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 18px; margin-bottom: 30px; text-align: left; font-size: 13px; color: #cbd5e1; line-height: 1.6;">'
                                        + '<p style="margin: 6px 0;">An official paid confirmation receipt has been sent to <strong>' + emailVal + '</strong>.</p>'
                                        + '<p style="margin: 6px 0;">Your chauffeur will send an SMS notification prior to pickup.</p>'
                                        + '</div>'
                                        + '<a href="' + homeReturnUrl + '" class="etb-chk-submit-btn" style="text-decoration: none; display: inline-flex; width: auto; padding: 14px 35px;">Return to Home</a>'
                                        + '</div>';
                                }
                            } else {
                                showFeedback(res.data.message || 'Booking creation failed.', 'error');
                            }
                        })
                        .catch(err => {
                            console.error('Final dispatch error:', err);
                            submitBtn.disabled = false;
                            if (submitText) submitText.textContent = originalText;
                            showFeedback('Communication error. Please try again.', 'error');
                        });
                    };

                    // CAS A : STRIPE AVEC EXTRACTION DES VRAIES DONNÉES DE CARTE
                    if (stripeInstance && stripeCardElement) {
                        submitBtn.disabled = true;
                        if (submitText) submitText.textContent = 'Securing & Booking...';

                        // 1. Création du PaymentMethod pour extraire les VRAIS 4 chiffres et l'expiration
                        stripeInstance.createPaymentMethod({
                            type: 'card',
                            card: stripeCardElement,
                            billing_details: {
                                name: fNameVal + ' ' + lNameVal,
                                email: emailVal,
                                phone: phoneVal
                            }
                        }).then(function (pmResult) {
                            if (pmResult.error) {
                                submitBtn.disabled = false;
                                if (submitText) submitText.textContent = 'Confirm & Book Now';
                                return showFeedback(pmResult.error.message, 'error');
                            }

                            const pm = pmResult.paymentMethod;
                            // Données réelles certifiées par Stripe (ex: '0000', '10/28', 'visa')
                            const realLast4 = pm.card ? pm.card.last4 : '0000';
                            const realBrand = pm.card ? pm.card.brand : detectedBrand;
                            const realExp   = (pm.card && pm.card.exp_month && pm.card.exp_year) 
                                ? (String(pm.card.exp_month).padStart(2, '0') + '/' + String(pm.card.exp_year).slice(-2))
                                : '10/28';

                            // 2. Création de l'intention de paiement côté serveur
                            const intentFormData = new FormData();
                            intentFormData.append('action', 'etb_create_payment_intent');
                            intentFormData.append('nonce', etbAjax.nonce);
                            intentFormData.append('amount', finalDue);
                            intentFormData.append('currency', 'eur');
                            intentFormData.append('name', fNameVal + ' ' + lNameVal);
                            intentFormData.append('email', emailVal);
                            intentFormData.append('phone', phoneVal);
                            intentFormData.append('vehicle_name', sumVehicleName ? sumVehicleName.textContent : 'VIP Vehicle');
                            intentFormData.append('route', (sumPickup ? sumPickup.textContent : '') + ' -> ' + (sumDropoff ? sumDropoff.textContent : ''));

                            fetch(etbAjax.ajax_url, {
                                method: 'POST',
                                body: intentFormData
                            })
                            .then(r => r.json())
                            .then(intentRes => {
                                if (!intentRes.success) {
                                    submitBtn.disabled = false;
                                    if (submitText) submitText.textContent = 'Confirm & Book Now';
                                    return showFeedback(intentRes.data.message || 'Payment initialization failed.', 'error');
                                }

                           
                                // Récupération du nom sur la carte et du pays sélectionné
                                const cardHolderVal = checkoutRoot.querySelector('#etb-cardholder-name')?.value.trim() || (fNameVal + ' ' + lNameVal);
                                const selectedCountryVal = checkoutRoot.querySelector('#etb-card-country')?.value || 'FR';

                                // 3. Confirmation de l'empreinte bancaire et validation 3D Secure avec le pays officiel
                                stripeInstance.confirmCardPayment(intentRes.data.client_secret, {
                                    payment_method: {
                                        card: stripeCardElement,
                                        billing_details: {
                                            name: cardHolderVal,
                                            email: emailVal,
                                            phone: phoneVal,
                                            address: {
                                                country: selectedCountryVal
                                            }
                                        }
                                    }
                                })
                                .then(function (stripeResult) {
                                    if (stripeResult.error) {
                                        submitBtn.disabled = false;
                                        if (submitText) submitText.textContent = 'Confirm & Book Now';
                                        return showFeedback(stripeResult.error.message, 'error');
                                    }

                                    const pi = stripeResult.paymentIntent;
                                    
                                    // Transmission des VRAIES données de carte à LimoExpress
                                    const paymentLogs = {
                                        id: pi.id,
                                        customer: intentRes.data.customer_id,
                                        last4: realLast4, // '0000' réel
                                        brand: realBrand, // 'visa' réel
                                        exp: realExp      // '10/28' réel
                                    };

                                    executeFinalOrderDispatch(paymentLogs);
                                });
                            })
                            .catch(err => {
                                console.error('Payment intent error:', err);
                                submitBtn.disabled = false;
                                if (submitText) submitText.textContent = 'Confirm & Book Now';
                                showFeedback('Payment service communication error.', 'error');
                            });
                        });
                        return;
                    }

                    // CAS B : FALLBACK SI STRIPE N'EST PAS CONFIGURÉ
                    executeFinalOrderDispatch(null);
                }

            });
        }

        const showFeedback = function (msg, type) {
            if (!feedbackEl) return;
            feedbackEl.textContent = msg;
            feedbackEl.className = 'etb-chk-feedback is-' + type;
            feedbackEl.style.display = 'block';
            feedbackEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        };
    };


    // ==========================================================================
    // MOTEUR UNIVERSEL DE GESTION DARK / LIGHT MODE (INFAILLIBLE)
    // ==========================================================================
    const initThemeDetector = function () {
        
        // 1. Détection intelligente du mode à appliquer (Manuel -> Thème du site -> Système OS)
        const detectOptimalTheme = () => {
            const saved = localStorage.getItem('etb_theme_mode');
            if (saved === 'dark' || saved === 'light') return saved;

            // Détection via les classes courantes des thèmes WordPress sur <html> ou <body>
            const rootClasses = (document.documentElement.className + ' ' + document.body.className).toLowerCase();
            if (rootClasses.includes('dark') || rootClasses.includes('night') || rootClasses.includes('black')) {
                return 'dark';
            }
            if (rootClasses.includes('light') || rootClasses.includes('day') || rootClasses.includes('white')) {
                return 'light';
            }

            // Détection selon la préférence du système / navigateur
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                return 'light';
            }

            return 'dark'; // Repli par défaut : VIP Dark Mode
        };

        // 2. Application de l'attribut au DOM (sur <html> et sur chaque conteneur ETB)
        // 2. Application de l'attribut au DOM (sur <html> et sur chaque conteneur ETB)
        const applyTheme = (themeName) => {
            // Applique au conteneur racine <html> pour que les popups globales comme .pac-container en profitent
            document.documentElement.setAttribute('data-etb-theme', themeName);

            const targets = document.querySelectorAll('#etb-quick-widget-app, #etb-checkout-app, .co-circuit-wrapper');
            targets.forEach(el => {
                el.setAttribute('data-etb-theme', themeName);
            });

            // Mise à jour en direct de la couleur du texte Stripe Elements si présent sur la page
            const mountBox = document.querySelector('#etb-stripe-card-mount');
            if (mountBox && typeof stripeCardElement !== 'undefined' && stripeCardElement) {
                const isLight = (themeName === 'light');
                stripeCardElement.update({
                    style: {
                        base: {
                            color: isLight ? '#1e293b' : '#ffffff',
                            '::placeholder': { color: isLight ? '#94a3b8' : 'rgba(148, 163, 184, 0.6)' }
                        }
                    }
                });
            }

            console.log("🌟 Thème appliqué :", themeName);
        };

        // 3. Lancement immédiat
        applyTheme(detectOptimalTheme());

        // 4. Fonction d'attachement direct du clic sur les boutons
        const bindToggleButtons = () => {
            const buttons = document.querySelectorAll('.etb-theme-toggle-btn');
            buttons.forEach(btn => {
                if (btn.dataset.bound) return; // Évite les doubles clics
                btn.dataset.bound = 'true';
                
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Lecture du thème actuel et inversion
                    const currentTheme = localStorage.getItem('etb_theme_mode') || 'dark';
                    const nextTheme    = (currentTheme === 'dark') ? 'light' : 'dark';

                    localStorage.setItem('etb_theme_mode', nextTheme);
                    applyTheme(nextTheme);
                });
            });
        };

        bindToggleButtons();

        // 5. RADAR (MutationObserver) si le thème charge le widget en décalé
        const themeObserver = new MutationObserver(() => {
            bindToggleButtons();
            const unstyledWidgets = document.querySelectorAll('#etb-quick-widget-app:not([data-etb-theme]), #etb-checkout-app:not([data-etb-theme])');
            if (unstyledWidgets.length > 0) {
                applyTheme(detectOptimalTheme());
            }
        });
        themeObserver.observe(document.body, { childList: true, subtree: true });
    };
    
   // Lancement universel au chargement de la page
    const startApp = function () {
        initThemeDetector(); // Active la détection et la bascule Dark / Light
        init();              // Initialise les circuits
        initQuickWidget();   // Initialise le widget minimal [etb_transfer]
        initCheckoutApp();   // Initialise le Checkout [etb_checkout]
    };
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startApp);
    } else {
        startApp();
    }
})();




