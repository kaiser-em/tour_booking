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

            const options = {
                fields: ['formatted_address', 'geometry', 'name'],
                componentRestrictions: { country: ['fr', 'mc'] } // France & Monaco
            };

            const autocomplete = new google.maps.places.Autocomplete(inputEl, options);

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place.geometry || !place.geometry.location) return;

                const placeName = place.name + (place.formatted_address ? ' (' + place.formatted_address + ')' : '');
                inputEl.value   = placeName;

                if (latEl) latEl.value = place.geometry.location.lat();
                if (lngEl) lngEl.value = place.geometry.location.lng();

                // Déclenche l'apparition de la croix car l'adresse est validée
                inputEl.dispatchEvent(new Event('change'));
            });

            // Empêche la touche Entrée dans Google Places de soumettre le formulaire par erreur
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

                // 1. Régénération complète du prix chiffré (écrase tout ancien badge Custom Quote)
                const priceBox = card.querySelector('.etb-quick-price-display');
                if (priceBox) {
                    priceBox.innerHTML = '<span class="etb-quick-amount">' + calculatedPrice + '</span> <span class="etb-quick-currency">' + currency + '</span>';
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
                                targetCard.dataset.calculatedPrice = limoPrice;
                                targetCard.dataset.isQuote = '0';

                                var priceBox = targetCard.querySelector('.etb-quick-price-display');
                                if (priceBox) {
                                    priceBox.innerHTML = '<span class="etb-quick-amount">' + limoPrice + '</span> <span class="etb-quick-currency">' + currency + '</span>';
                                }
                                var priceDetailEl = targetCard.querySelector('.etb-quick-price-detail');
                                if (priceDetailEl) { priceDetailEl.style.display = 'none'; }
                                var kmInfoEl = targetCard.querySelector('.etb-quick-km-info');
                                if (kmInfoEl) { kmInfoEl.textContent = ''; kmInfoEl.classList.remove('is-visible'); }
                                var selBtn = targetCard.querySelector('.etb-quick-select-btn');
                                if (selBtn) { selBtn.textContent = 'Select'; }

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
                        btn.textContent = isCarQuote ? 'Request Quote' : 'Select';
                    }
                    return;
                }

                // Désélectionne toutes les autres cartes
                carCards.forEach(c => {
                    c.classList.remove('selected');
                    const b = c.querySelector('.etb-quick-select-btn');
                    if (b) {
                        const otherIsQuote = (c.dataset.isQuote === '1' || isQuoteMode);
                        b.textContent = otherIsQuote ? 'Request Quote' : 'Select';
                    }
                });

                // Active la carte cliquée
                this.classList.add('selected');
                const selectBtn = this.querySelector('.etb-quick-select-btn');
                if (selectBtn) {
                    selectBtn.textContent = '✓ Selected';
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

                    // Génération du lien WhatsApp officiel pour toutes les réservations
                    if (whatsappBtn) {
                        const pVal = pickupInput ? pickupInput.value.trim() : '';
                        const dVal = dropoffInput ? dropoffInput.value.trim() : '';
                        const dtVal = dateInput ? dateInput.value.trim() : '';
                        const tmVal = timeInput ? timeInput.value.trim() : '';
                        const waPhone = (typeof etbAjax !== 'undefined' && etbAjax.company_whatsapp) ? etbAjax.company_whatsapp : '';

                        let msg = `Hello, I would like to `;
                        if (isCarQuote) {
                            msg += `request a custom quote for a transfer with ${carName}.\n• From: ${pVal}\n• To: ${dVal}\n• Date: ${dtVal} at ${tmVal}\n• Note: Pending dispatch confirmation.`;
                        } else {
                            msg += `inquire about booking a transfer with ${carName}.\n• From: ${pVal}\n• To: ${dVal}\n• Date: ${dtVal} at ${tmVal}\n• Indicative online rate: ${carPrice} ${currency} (Subject to dispatch verification)`;
                        }

                        const waText = encodeURIComponent(msg);
                        whatsappBtn.href = waPhone 
                            ? `https://wa.me/${waPhone}?text=${waText}` 
                            : `https://api.whatsapp.com/send?text=${waText}`;
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
        

        // 5. Clic sur "Réserver ce trajet" : Redirection pré-remplie avec contrôle véhicule
        if (bookNowBtn) {
            bookNowBtn.addEventListener('click', function (e) {
                e.preventDefault();
                
                // Contrôle strict : un véhicule doit être obligatoirement sélectionné (English)
                if (!selectedCar) {
                    showError('Please select a vehicle from the list above before proceeding.');
                    return;
                }

                const pickupVal  = pickupInput ? pickupInput.value.trim() : '';
                const dropoffVal = (currentMode === 'transfer' && dropoffInput) ? dropoffInput.value.trim() : '';
                const dateVal    = dateInput ? dateInput.value.trim() : ''; // YYYY-MM-DD
                const timeVal    = timeInput ? timeInput.value.trim() : '09:00'; // HH:MM

                // Conversion de la date au format officiel exigé par le formulaire LimoExpress : DD-MM-YYYY
                let formattedDateTime = '';
                if (dateVal) {
                    const dParts = dateVal.split('-');
                    if (dParts.length === 3) {
                        formattedDateTime = `${dParts[2]}-${dParts[1]}-${dParts[0]} ${timeVal}`;
                    }
                }

                // Paramètres LimoExpress configurés dynamiquement dans WordPress (Zéro-Hardcode)
                const limoBaseUrl      = (typeof etbAjax !== 'undefined' && etbAjax.limo_form_url) ? etbAjax.limo_form_url : 'https://app.limoexpress.me/public/reservation-form';
                const limoParam        = (typeof etbAjax !== 'undefined' && etbAjax.limo_param) ? etbAjax.limo_param : '479812783e34cb527161c28bee8748d95cee8c85dd26fcdb51f1086d31b3108be443d2';
                const oneWayTypeId     = (typeof etbAjax !== 'undefined' && etbAjax.limo_oneway_type) ? etbAjax.limo_oneway_type : 'e52e0f08-878d-4e0c-8e2d-b09225b5a0cf';
                const hourlyRentTypeId = (typeof etbAjax !== 'undefined' && etbAjax.limo_hourly_type) ? etbAjax.limo_hourly_type : '89bc0301-9af8-4bd0-858a-998e21f0bf13';
                
                // Construction de l'URL avec les IDs officiels LimoExpress
                const queryParams = new URLSearchParams({
                    param: limoParam,
                    from: pickupVal,
                    language: 'en'
                });

                // Mode 1 : Location À l'heure (Hourly rent)
                if (currentMode === 'hourly') {
                    const durHours = durationSelect ? durationSelect.value : '4';
                    
                    // Formatage de la durée au format attendu par LimoExpress : HH:MM (ex: "04:00")
                    const formattedDuration = String(durHours).padStart(2, '0') + ':00';

                    // L'URL accepte ces paramètres pour forcer l'onglet "Hourly" et masquer la dépose
                    queryParams.append('booking_type_id', hourlyRentTypeId);
                    queryParams.append('driving_type_id', '2'); // 2 = Hourly (selon la nomenclature interne de leur Vue.js)
                    queryParams.append('duration', formattedDuration);
                    queryParams.append('num_of_hours', durHours);
                } 
                // Mode 2 : Trajet simple Point A -> B
                else {
                    queryParams.append('booking_type_id', oneWayTypeId);
                    if (dropoffVal) {
                        queryParams.append('to', dropoffVal);
                    }
                }


                if (formattedDateTime) {
                    queryParams.append('pickup_time', formattedDateTime);
                }

                const finalUrl = `${limoBaseUrl}?${queryParams.toString()}`;

                // Ouverture propre dans un nouvel onglet
                window.open(finalUrl, '_blank');
            });
        }
    };

    // Lancement universel au chargement de la page
    const startApp = function () {
        init();            // Initialise le layout classique des circuits (si présent)
        initQuickWidget();   // Initialise le widget minimal [etb_transfer] (si présent)
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startApp);
    } else {
        startApp();
    }
})();



