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

            // Blocage du bouton : uniquement si dépassement du nombre de passagers
            if (submitButton) {
                const isBlocked = isPaxCapacityExceeded;
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
        const urgentNoticeEl = quickRoot.querySelector('#etb-quick-urgent-notice');

        // Vérifie si la date et l'heure sélectionnées sont dans le passé
        const isDateTimeInPast = (dateStr, timeStr) => {
            if (!dateStr || !timeStr || timeStr === '-- : --') return false;
            const cleanTime = timeStr.toUpperCase().trim();
            let h = 9, m = 0;
            if (cleanTime.includes('AM') || cleanTime.includes('PM')) {
                const parts = cleanTime.split(/\s+/);
                const hm = (parts[0] || '').split(':');
                h = parseInt(hm[0], 10) || 0;
                m = parseInt(hm[1], 10) || 0;
                if (cleanTime.includes('PM') && h < 12) h += 12;
                if (cleanTime.includes('AM') && h === 12) h = 0;
            } else {
                const hm = cleanTime.split(':');
                h = parseInt(hm[0], 10) || 0;
                m = parseInt(hm[1], 10) || 0;
            }

            const dParts = dateStr.split('-');
            if (dParts.length !== 3) return false;

            const selectedDate = new Date(parseInt(dParts[0], 10), parseInt(dParts[1], 10) - 1, parseInt(dParts[2], 10), h, m, 0);
            const now = new Date();
            // Marge de tolérance de 2 minutes
            return selectedDate.getTime() < (now.getTime() - 120000);
        };

        // Détection dynamique des courses urgentes (< 24h)
        const checkIsUrgent = () => {
            const rawDate = dateInput ? dateInput.value.trim() : '';
            const rawTime = timeInput ? timeInput.value.trim() : '';
            if (!rawDate || !rawTime || rawTime === '-- : --') return false;

            let h = 9, m = 0;
            const cleanTime = rawTime.toUpperCase();
            if (cleanTime.includes('AM') || cleanTime.includes('PM')) {
                const parts = cleanTime.split(/\s+/);
                const hm = (parts[0] || '').split(':');
                h = parseInt(hm[0], 10) || 0;
                m = parseInt(hm[1], 10) || 0;
                if (cleanTime.includes('PM') && h < 12) h += 12;
                if (cleanTime.includes('AM') && h === 12) h = 0;
            } else {
                const hm = cleanTime.split(':');
                h = parseInt(hm[0], 10) || 0;
                m = parseInt(hm[1], 10) || 0;
            }

            const dParts = rawDate.split('-');
            if (dParts.length !== 3) return false;

            const pickupDate = new Date(parseInt(dParts[0], 10), parseInt(dParts[1], 10) - 1, parseInt(dParts[2], 10), h, m, 0);
            const now = new Date();
            const diffHours = (pickupDate.getTime() - now.getTime()) / (1000 * 60 * 60);

            // Retourne true UNIQUEMENT si la course a lieu entre 0h et 24h
            return diffHours >= 0 && diffHours < 24;
        };

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

            // Fonction de grisement des heures passées si la date est Aujourd'hui
            const refreshPastHoursRestrictions = () => {
                const selectedDateStr = dateInput ? dateInput.value.trim() : '';
                const now = new Date();
                const todayFormatted = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
                const isToday = (selectedDateStr === todayFormatted);

                const currentHour24 = now.getHours();
                const activeAmpm    = timePopup.querySelector('.etb-ampm-btn.active')?.dataset.val || 'AM';

                // 1. Bouton AM si on est déjà l'après-midi
                const amBtn = timePopup.querySelector('.etb-ampm-btn[data-val="AM"]');
                if (amBtn) {
                    if (isToday && currentHour24 >= 12) {
                        amBtn.style.opacity = '0.3';
                        amBtn.style.pointerEvents = 'none';
                        // Bascule auto sur PM si AM était actif
                        if (amBtn.classList.contains('active')) {
                            amBtn.classList.remove('active');
                            const pmBtn = timePopup.querySelector('.etb-ampm-btn[data-val="PM"]');
                            if (pmBtn) pmBtn.classList.add('active');
                        }
                    } else {
                        amBtn.style.opacity = '1';
                        amBtn.style.pointerEvents = 'auto';
                    }
                }

                // 2. Grisement des heures individuelles
                timePopup.querySelectorAll('.etb-hour-opt').forEach(opt => {
                    const hVal12 = parseInt(opt.dataset.val, 10) || 0;
                    let hVal24 = hVal12;
                    const currentAmpm = timePopup.querySelector('.etb-ampm-btn.active')?.dataset.val || 'AM';
                    if (currentAmpm === 'PM' && hVal12 < 12) hVal24 += 12;
                    if (currentAmpm === 'AM' && hVal12 === 12) hVal24 = 0;

                    if (isToday && hVal24 < currentHour24) {
                        opt.style.opacity = '0.25';
                        opt.style.pointerEvents = 'none';
                        opt.classList.remove('active');
                    } else {
                        opt.style.opacity = '1';
                        opt.style.pointerEvents = 'auto';
                    }
                });
            };

            // Ouvrir / Fermer le sélecteur au clic sur la colonne heure
            timeCol.addEventListener('click', function (e) {
                if (e.target.closest('#etb-quick-time-popup')) return;
                
                const isCurrentlyOpen = timePopup.classList.contains('is-open');
                if (isCurrentlyOpen) {
                    timePopup.classList.remove('is-open');
                    timePopup.style.setProperty('display', 'none', 'important');
                } else {
                    refreshPastHoursRestrictions();
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

            // 1. Bascule AM / PM avec rafraîchissement immédiat des heures disponibles
            timePopup.querySelectorAll('.etb-ampm-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    timePopup.querySelectorAll('.etb-ampm-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    // Rafraîchissement immédiat de l'état des heures (AM vs PM)
                    if (typeof refreshPastHoursRestrictions === 'function') {
                        refreshPastHoursRestrictions();
                    }

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

       // Fonction utilitaire pour dévoiler la flotte et évaluer le bandeau urgent
        const showFleetSection = () => {
            if (fleetSection) {
                fleetSection.style.display = 'block';
                fleetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // Évaluation immédiate dès l'affichage des prix : < 24h = visible, sinon = masqué
            if (urgentNoticeEl) {
                urgentNoticeEl.style.display = checkIsUrgent() ? 'block' : 'none';
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
                    priceBox.innerHTML = '<span class="etb-quick-quote-badge">Upon Request</span>';
                }

                var detailBox = c.querySelector('.etb-quick-price-detail');
                if (detailBox) {
                    detailBox.innerHTML = 'Select to ask a custom quote';
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
                if (isDateTimeInPast(dateVal, timeVal)) {
                    return showError('The pickup time cannot be in the past. Please select a future time.');
                }

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
                                        priceBox.innerHTML = '<span class="etb-quick-quote-badge">Upon Request</span>';
                                    }

                                    var priceDetailEl = targetCard.querySelector('.etb-quick-price-detail');
                                    if (priceDetailEl) {
                                        priceDetailEl.innerHTML = 'Select to ask a custom quote';
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
                    if (urgentNoticeEl) urgentNoticeEl.style.display = 'none';
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

            
                // Affiche la barre de confirmation inférieure en 3 colonnes
                if (bookingBar) {
                    let priceMainHtml = '';
                    let priceSubHtml  = '';
                    let priceKmHtml   = '';

                    if (isCarQuote) {
                        if (bookBtnLabel) bookBtnLabel.textContent = 'Request this Quote';
                        priceMainHtml = '<span style="color: #fbac18; font-size: 20px; font-weight: 800;">Custom Quote</span>';
                        priceSubHtml  = 'Pending dispatch review';
                    } else {
                        if (bookBtnLabel) bookBtnLabel.textContent = 'Book this Trip';
                        priceMainHtml = `<span style="color: #fbac18; font-size: 26px; font-weight: 900; letter-spacing: -0.5px;">${carPrice} <span style="font-size: 18px;">${currency}</span></span>`;
                        
                        // Condition de mode pour la ligne 2 et 3
                        if (currentMode === 'hourly') {
                            const detailEl = this.querySelector('.etb-quick-price-detail');
                            // Extrait et nettoie le texte (ex: "10h Package + 2 extra hour(s) • 125 € per extra hour")
                            priceSubHtml = detailEl ? detailEl.innerHTML.replace(/<br\s*[\/]?>/gi, ' • ').replace(/<\/?strong>/gi, '') : '';
                            
                            const kmInfoEl = this.querySelector('.etb-quick-km-info');
                            priceKmHtml = kmInfoEl ? kmInfoEl.textContent : '';
                        } else {
                            priceSubHtml = 'All inclusive (Fixed rate)';
                            priceKmHtml  = '';
                        }
                    }

                   
                    // Extraction de l'image miniature du véhicule
                    const carImgEl  = this.querySelector('.etb-img-static');
                    const carImgSrc = carImgEl ? carImgEl.src : '';

                    // Extraction des capacités et du HTML des icônes
                    const maxPax = this.dataset.maxPax || '1';
                    const maxBag = this.dataset.maxBag || '0';
                    const amenitiesBox = this.querySelector('.etb-quick-amenities');
                    const amenitiesHtml = amenitiesBox ? amenitiesBox.innerHTML : '';

                    // Assemblage selon la maquette (Ligne 1: Titre | Ligne 2: Pax/Bag | Ligne 3: Amenities)
                    const barContentHtml = `
                        <!-- Colonne 0 : Miniature Image (Optionnelle, masquée sur mobile) -->
                        ${carImgSrc ? `<div class="etb-bar-col-img"><img src="${carImgSrc}" alt="${carName}"></div>` : ''} 


                        <!-- Colonne 1 : Véhicule, Pax & Équipements -->
                        <div class="etb-bar-col1">
                            <div class="etb-bar-v-title">
                                <strong>${carName}</strong>
                            </div>
                            <div class="etb-bar-pax-bag">
                                <span class="etb-pax-bag-item">
                                    <svg viewBox="0 0 100 95" width="14" height="14" fill="var(--etb-accent-gold, #fbac18)"><path d="m88.484 31.117c0 8.0312-6.5117 14.539-14.539 14.539-8.0312 0-14.543-6.5078-14.543-14.539s6.5117-14.539 14.543-14.543c8.0273 0 14.539 6.5117 14.539 14.543z"/><path d="m0.90234 73.273c-3.0547 6.2812 2.0391 15.633 9.6914 17.562 16.133 3.6016 32.57 3.6016 48.703 0 7.6562-1.9336 12.75-11.281 9.6914-17.562-5.7109-11.867-18.844-22.293-34.047-22.391-15.203 0.10156-28.336 10.523-34.047 22.391z"/><path d="m54.445 25.965c0 10.77-8.7305 19.504-19.5 19.504-10.77 0-19.504-8.7344-19.504-19.504 0-10.77 8.7344-19.5 19.504-19.5 10.77-0.003906 19.5 8.7305 19.5 19.5z"/><path d="m99.328 66.391c-4.2578-8.8516-14.051-16.625-25.383-16.695-5.1719 0.03125-10.023 1.6719-14.176 4.2812 6.0273 4.4648 11.02 10.426 14.141 16.91 1.5391 3.1641 1.8438 6.8906 0.93359 10.605 5.7734-0.0625 11.539-0.73047 17.258-2.0078 5.707-1.4414 9.5078-8.4141 7.2266-13.094z"/></svg>
                                    ${maxPax} Pax
                                </span>
                                <span class="etb-sep"></span> 
                                <span class="etb-pax-bag-item">
                                    <svg viewBox="20 15 60 88" width="14" height="14" fill="var(--etb-accent-gold, #fbac18)"><path d="M70.75,26.75H60.028l1.056,5.476c2.108-0.315,4.109,1.073,4.517,3.185l0.868,4.5c0.418,2.169-1.001,4.267-3.171,4.685 l-1.227,0.237c-2.169,0.418-4.268-1.001-4.686-3.17l-0.867-4.5c-0.408-2.113,0.936-4.146,3.01-4.637l-1.113-5.775H54.75v-14 c0-0.019-0.01-0.034-0.011-0.052c0.002-0.032,0.01-0.062,0.01-0.095c0-0.773-0.626-1.397-1.397-1.397h-6.705 c-0.771,0-1.397,0.624-1.397,1.397c0,0.034,0.008,0.065,0.01,0.098c0,0.017-0.01,0.031-0.01,0.049v14h-16c-2.209,0-4,1.791-4,4v59 c0,2.209,1.791,4,4,4h6V94c0,0.69,0.559,1.25,1.25,1.25c0.689,0,1.25-0.56,1.25-1.25v-0.25h24.5V94c0,0.69,0.559,1.25,1.25,1.25 c0.689,0,1.25-0.56,1.25-1.25v-0.25h6c2.209,0,4-1.791,4-4v-59C74.75,28.541,72.959,26.75,70.75,26.75z M47.75,14h4.5v12.75h-4.5V14 z M63.39,84.078h-26.78c-1.027,0-1.86-0.834-1.86-1.859c0-1.028,0.833-1.859,1.86-1.859h26.78c1.026,0,1.859,0.831,1.859,1.859 C65.249,83.244,64.416,84.078,63.39,84.078z"/></svg>
                                    ${maxBag} Bag.
                                </span>
                            </div>
                            <div class="etb-quick-amenities etb-bar-amenities">${amenitiesHtml}</div>
                        </div>
                        
                        <!-- Colonne 2 : Lignes de Tarif -->
                        <div class="etb-bar-col2">
                            <div class="etb-bar-price">${priceMainHtml}</div>
                            <div class="etb-bar-sub">${priceSubHtml}</div>
                            ${priceKmHtml ? `<div class="etb-bar-km">${priceKmHtml}</div>` : ''}
                        </div>
                    `;

                    const dynamicContentEl = quickRoot.querySelector('#etb-quick-dynamic-bar-content');
                    if (dynamicContentEl) {
                        dynamicContentEl.innerHTML = barContentHtml;
                    }


                    // Données de la course pour WhatsApp et Mailto
                    const pVal      = pickupInput ? pickupInput.value.trim() : '';
                    const isHourly  = (currentMode === 'hourly');
                    const dVal      = isHourly ? `By the hour (${durationSelect ? durationSelect.value : 4} Hours rental)` : (dropoffInput ? dropoffInput.value.trim() : '');
                    const rawDate   = dateInput ? dateInput.value.trim() : '';
                    const tmVal     = timeInput ? timeInput.value.trim() : '';
                    const waPhone   = (typeof etbAjax !== 'undefined' && etbAjax.company_whatsapp) ? etbAjax.company_whatsapp : '';
                    const adminMail = (typeof etbAjax !== 'undefined' && etbAjax.admin_email) ? etbAjax.admin_email : '';

                    // Formatage compact WhatsApp / Email (ex: 2026-10-03 -> 03-Oct-26)
                    const formatPrettyDate = (dStr) => {
                        if (!dStr) return '';
                        const parts = dStr.split('-');
                        if (parts.length !== 3) return dStr;
                        const monthsEn = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        const mIdx      = parseInt(parts[1], 10) - 1;
                        const monthStr  = monthsEn[mIdx] || parts[1];
                        const shortYear = parts[0].slice(-2);
                        return `${parts[2]}-${monthStr}-${shortYear}`;
                    };

                    const prettyDate = formatPrettyDate(rawDate);
                    const formattedDateTime = (prettyDate && tmVal) ? `${prettyDate} at ${tmVal}` : (prettyDate || tmVal || '—');

                    const dropoffSymbol = isHourly ? '↻🄷🄾🅄🅁🄻🅈' : '➘🄳🄾□';
                    const rateText      = isCarQuote ? 'Custom Quote (Pending dispatch)' : `${carPrice} ${currency}`;

                    // Émoji main qui salue 👋
                    const waveEmoji = String.fromCodePoint(0x1F44B);

                    // Mise en forme officielle rigoureuse demandée
                    const formattedMessage = `Hey *EDEN CAB* team ${waveEmoji},\n\n`
                        + `I would like to inquire about booking a transfer with:\n\n`
                        + `*${carName}*\n\n`
                        + `➚🄿🅄■ ${pVal}\n\n`
                        + `> ${dropoffSymbol} ${dVal}\n\n`
                        + `➔ ${formattedDateTime}\n\n`
                        + `❏ Indicative rate: ${rateText}`;


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
                        const emailSubject = encodeURIComponent(`[Inquiry] ${carName} — ${formattedDateTime}`);
                        // Version lisible pour l'e-mail avec les mêmes symboles
                        const emailBody = encodeURIComponent(formattedMessage.replace(/\*/g, ''));
                        emailInquiryBtn.href = `mailto:${adminMail}?subject=${emailSubject}&body=${emailBody}`;
                    }


                    // Affichage du bandeau < 24h si le trajet est urgent
                    if (urgentNoticeEl) {
                        urgentNoticeEl.style.display = checkIsUrgent() ? 'block' : 'none';
                    }

                    bookingBar.classList.add('is-visible');

                    // Auto-scroll fluide vers le bouton "Book this trip"
                    setTimeout(() => {
                        bookingBar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 220);
                }
            });
        });


        
        

        // 5. Clic sur "Book this Trip" : Génération du Quote Token sécurisé et redirection
        if (bookNowBtn) {
            bookNowBtn.addEventListener('click', function (e) {
                e.preventDefault();

                if (!selectedCar) {
                    showError('Please select a vehicle from the list above before proceeding.');
                    return;
                }

                // Lecture directe et robuste du champ pickup dans le widget
                const pInputEl   = quickRoot.querySelector('#etb-quick-pickup');
                const dInputEl   = quickRoot.querySelector('#etb-quick-dropoff');
                
                const pickupVal  = pInputEl ? pInputEl.value.trim() : '';
                const dropoffVal = (currentMode === 'transfer' && dInputEl) ? dInputEl.value.trim() : '';
                const durVal     = (currentMode === 'hourly' && durationSelect) ? durationSelect.value : '4';
                const dateVal    = dateInput ? dateInput.value.trim() : '';
                const timeVal    = timeInput ? timeInput.value.trim() : '';

                // Sécurité front-end immédiate avec message clair avant l'appel AJAX
                if (!pickupVal) {
                    showError('Please enter a pickup location.');
                    if (pInputEl) pInputEl.focus();
                    return;
                }
                if (currentMode === 'transfer' && !dropoffVal) {
                    showError('Please enter a drop-off location.');
                    if (dInputEl) dInputEl.focus();
                    return;
                }

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
        const phoneInputEl    = checkoutRoot.querySelector('#etb-passenger-phone');

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

        const cabinBagMinusBtn = checkoutRoot.querySelector('.etb-cabin-bag-minus');
        const cabinBagPlusBtn  = checkoutRoot.querySelector('.etb-cabin-bag-plus');
        const cabinBagInputEl  = checkoutRoot.querySelector('#etb-chk-cabin-bag-input');

        const seatMinusBtn    = checkoutRoot.querySelector('.etb-seat-minus');
        const seatPlusBtn     = checkoutRoot.querySelector('.etb-seat-plus');
        const seatInput       = checkoutRoot.querySelector('#etb-chk-baby-seats');

        const boosterMinusBtn = checkoutRoot.querySelector('.etb-booster-minus');
        const boosterPlusBtn  = checkoutRoot.querySelector('.etb-booster-plus');
        const boosterInput    = checkoutRoot.querySelector('#etb-chk-booster-seats');

        // Champs de carte bancaire & Initialisation Stripe
        const cardNumInput    = checkoutRoot.querySelector('#etb-card-number');
        const cardExpInput    = checkoutRoot.querySelector('#etb-card-expiry');
        const cardCvcInput    = checkoutRoot.querySelector('#etb-card-cvc');
        const cardBrandBadges = checkoutRoot.querySelectorAll('.etb-brand-badge');

        let stripeInstance    = null;
        let stripeCardElement = null;
        let detectedBrand     = 'card';

        // Initialisation de la librairie internationale de téléphone (245 pays)
        let phoneIti = null;
        if (typeof window.intlTelInput !== 'undefined' && phoneInputEl) {
            phoneIti = window.intlTelInput(phoneInputEl, {
                initialCountry: "auto",
                preferredCountries: ["fr", "mc", "gb", "us", "ch", "ae", "de", "it"],
                // Exclut Guernesey, Jersey et l'Île de Man pour que +44 reste TOUJOURS sur le Royaume-Uni (GB)
                excludeCountries: ["gg", "je", "im"],
                separateDialCode: true,
                autoPlaceholder: "aggressive",
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js",
                geoIpLookup: function (callback) {
                    fetch("https://ipapi.co/json/")
                        .then(function (res) { return res.json(); })
                        .then(function (data) { callback(data && data.country_code ? data.country_code.toLowerCase() : "fr"); })
                        .catch(function () { callback("fr"); });
                }
            });

            // Formateur universel certifié par Google libphonenumber (couvre les 245 pays)
            const formatPhoneDigitsByCountry = function (digits, iso2) {
                if (!digits) return '';
                iso2 = (iso2 || 'fr').toLowerCase();

                // 1. Si libphonenumber (utils.js) est disponible, formatage officiel au standard national
                if (typeof window.intlTelInputUtils !== 'undefined') {
                    try {
                        const countryData = phoneIti ? phoneIti.getSelectedCountryData() : null;
                        const dialCode    = countryData ? ('+' + countryData.dialCode) : '';
                        const formatted   = window.intlTelInputUtils.formatNumber(
                            dialCode + digits,
                            iso2,
                            window.intlTelInputUtils.numberFormat.NATIONAL
                        );
                        // Retrait du 0 initial si l'indicatif est séparé visuellement
                        return formatted.replace(/^0/, '').trim();
                    } catch (e) {}
                }

                // 2. Règles de repli soignées en attendant le chargement de utils.js
                if (iso2 === 'fr' || iso2 === 'mc') {
                    const first = digits.charAt(0);
                    const rest  = digits.substring(1);
                    const parts = rest.match(/.{1,2}/g) || [];
                    return (first + ' ' + parts.join(' ')).trim();
                }
                if (iso2 === 'us' || iso2 === 'ca') {
                    if (digits.length <= 3) return digits;
                    if (digits.length <= 6) return digits.slice(0, 3) + ' ' + digits.slice(3);
                    return digits.slice(0, 3) + ' ' + digits.slice(3, 6) + ' ' + digits.slice(6, 10);
                }
                if (iso2 === 'gb') {
                    if (digits.length <= 4) return digits;
                    if (digits.length <= 7) return digits.slice(0, 4) + ' ' + digits.slice(4);
                    return digits.slice(0, 4) + ' ' + digits.slice(4, 7) + ' ' + digits.slice(7, 11);
                }
                if (iso2 === 'de' || iso2 === 'ch' || iso2 === 'at') {
                    if (digits.length <= 3) return digits;
                    if (digits.length <= 7) return digits.slice(0, 3) + ' ' + digits.slice(3);
                    return digits.slice(0, 3) + ' ' + digits.slice(3, 7) + ' ' + digits.slice(7);
                }
                if (iso2 === 'es') {
                    if (digits.length <= 3) return digits;
                    return digits.slice(0, 3) + ' ' + digits.slice(3).replace(/(\d{2})(?=\d)/g, '$1 ');
                }

                // Espacement fluide par blocs naturels de 3 ou 4 pour les autres pays
                if (digits.length > 6) {
                    return digits.slice(0, 3) + ' ' + digits.slice(3, 6) + ' ' + digits.slice(6);
                }
                return digits.replace(/(\d{3})(?=\d)/g, '$1 ').trim();
            };

            

            // Écouteur en direct sur la frappe et le copier-coller
            const handlePhoneInputLive = function (e) {
                // 0. NE PAS interférer si l'utilisateur efface sur mobile (Retour arrière)
                if (e && (e.inputType === 'deleteContentBackward' || e.inputType === 'deleteContentForward')) {
                    return;
                }

                // Mémorisation de la position du curseur sur mobile
                let cursorPosition = this.selectionStart;
                let oldLength      = this.value.length;
                let val            = this.value;

                // 1. Si le client colle un numéro international complet avec + ou 00
                if (val.includes('+') || val.startsWith('00')) {
                    let cleanPasted = val.replace(/[^\d+]/g, '');
                    if (cleanPasted.startsWith('00')) {
                        cleanPasted = '+' + cleanPasted.substring(2);
                    }

                    // Bascule de pays sécurisée (+44 verrouillé sur Royaume-Uni, +1 sur USA)
                    if (cleanPasted.startsWith('+44')) {
                        phoneIti.setCountry('gb');
                    } else if (cleanPasted.startsWith('+1')) {
                        phoneIti.setCountry('us');
                    } else {
                        phoneIti.setNumber(cleanPasted);
                    }

                    // Nettoie l'input visible pour ne garder que le numéro national sans '+'
                    const countryData  = phoneIti.getSelectedCountryData();
                    const dialCode     = countryData ? countryData.dialCode : '';
                    let nationalDigits = cleanPasted.replace(/\D/g, '');

                    if (dialCode && nationalDigits.startsWith(dialCode)) {
                        nationalDigits = nationalDigits.substring(dialCode.length);
                    }
                    if (nationalDigits.startsWith('0')) {
                        nationalDigits = nationalDigits.substring(1);
                    }
                    this.value = formatPhoneDigitsByCountry(nationalDigits, countryData ? countryData.iso2 : 'fr');
                    return;
                }

                // 2. Élimination formelle de tout signe '+' ou parenthèses dans le champ
                val = val.replace(/\+/g, '').replace(/\(0\)/g, '');

                // 3. Extraction des chiffres
                let digits = val.replace(/\D/g, '');

                // 4. Retrait de l'indicatif s'il a été tapé au début sans le '+'
                const countryData = phoneIti ? phoneIti.getSelectedCountryData() : null;
                const dialCode    = countryData ? countryData.dialCode : '';
                const iso2        = countryData ? countryData.iso2 : 'fr';

                if (dialCode && digits.startsWith(dialCode) && digits.length > dialCode.length) {
                    digits = digits.substring(dialCode.length);
                }

                // 5. Retrait du 0 initial
                if (digits.startsWith('0')) {
                    digits = digits.substring(1);
                }

                // 6. Plafond mondial strict de 15 chiffres (E.164)
                if (digits.length > 15) {
                    digits = digits.substring(0, 15);
                }

                // 7. Formatage dynamique en direct
                let formatted = formatPhoneDigitsByCountry(digits, iso2);
                this.value = formatted;

                // 8. Restauration fluide du curseur
                if (cursorPosition !== null) {
                    let newCursor = cursorPosition + (formatted.length - oldLength);
                    this.setSelectionRange(newCursor, newCursor);
                }
            };

            phoneInputEl.addEventListener('input', handlePhoneInputLive);

            // Re-formatage automatique si on change de drapeau
            phoneInputEl.addEventListener('countrychange', function () {
                let digits = phoneInputEl.value.replace(/\D/g, '');
                const countryData = phoneIti ? phoneIti.getSelectedCountryData() : null;
                const iso2        = countryData ? countryData.iso2 : 'fr';
                phoneInputEl.value = formatPhoneDigitsByCountry(digits, iso2);
            });
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

        // Calcul du supplément des sièges enfants payants (Baby > 1 = 50€, Booster > 2 = 50€)
        const calculateChildSeatsFee = () => {
            const babyCount    = parseInt(seatInput ? seatInput.value : 0, 10) || 0;
            const boosterCount = parseInt(boosterInput ? boosterInput.value : 0, 10) || 0;

            // Règle métier : 1er Baby Seat offert, 2 premiers Booster Seats offerts
            const paidBaby    = Math.max(0, babyCount - 1);
            const paidBooster = Math.max(0, boosterCount - 2);

            const feeBaby    = paidBaby * 50;
            const feeBooster = paidBooster * 50;

            // 1. Affichage dynamique de la ligne Siège Bébé
            const babyRow   = checkoutRoot.querySelector('#etb-chk-baby-seats-row');
            const babyLabel = checkoutRoot.querySelector('#etb-chk-baby-seats-label');
            const babyVal   = checkoutRoot.querySelector('#etb-chk-breakdown-baby-seats');

            if (babyRow) {
                if (feeBaby > 0) {
                    babyRow.style.setProperty('display', 'flex', 'important');
                    if (babyLabel) babyLabel.textContent = `Extra Baby Seat (${paidBaby})`;
                    if (babyVal) babyVal.textContent = `+ ${feeBaby.toFixed(2)} ${currency}`;
                } else {
                    babyRow.style.setProperty('display', 'none', 'important');
                }
            }

            // 2. Affichage dynamique de la ligne Rehausseur (Booster)
            const boosterRow   = checkoutRoot.querySelector('#etb-chk-booster-seats-row');
            const boosterLabel = checkoutRoot.querySelector('#etb-chk-booster-seats-label');
            const boosterVal   = checkoutRoot.querySelector('#etb-chk-breakdown-booster-seats');

            if (boosterRow) {
                if (feeBooster > 0) {
                    boosterRow.style.setProperty('display', 'flex', 'important');
                    if (boosterLabel) boosterLabel.textContent = `Extra Booster Seat (${paidBooster})`;
                    if (boosterVal) boosterVal.textContent = `+ ${feeBooster.toFixed(2)} ${currency}`;
                } else {
                    boosterRow.style.setProperty('display', 'none', 'important');
                }
            }

            return feeBaby + feeBooster;
        };

        // 2. Fonction de recalcul du total incluant sièges enfants et pourboire
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

            // Prise en compte du supplément sièges
            const seatsFee = calculateChildSeatsFee();
            
            // Le pourboire se calcule STRICTEMENT sur le tarif de transport (Base Fare), jamais sur les sièges enfants
            const tipVal = Math.round((baseNumericPrice * (tipPercent / 100)) * 100) / 100;
            if (tipAmountInput) tipAmountInput.value = tipVal.toFixed(2);

            const subtotalBeforeTip = baseNumericPrice + seatsFee;
            const totalWithTip   = subtotalBeforeTip + tipVal;
            const formattedTotal = totalWithTip.toFixed(2) + ' ' + currency;

            if (tipVal > 0) {
                if (tipRow) {
                    tipRow.classList.add('is-visible');
                    tipRow.style.setProperty('display', 'flex', 'important');
                }
                if (tipPercentText) tipPercentText.textContent = `${tipPercent}%`;
                if (tipBreakdown) tipBreakdown.textContent = `+ ${tipVal.toFixed(2)} ${currency}`;
                if (sumTotalPrice) sumTotalPrice.textContent = formattedTotal;

                if (payTipRowEl) {
                    payTipRowEl.classList.remove('is-hidden');
                    payTipRowEl.style.setProperty('display', 'flex', 'important');
                }
                if (payTipAmountEl) payTipAmountEl.textContent = `+ ${tipVal.toFixed(2)} ${currency}`;
                if (payGrandTotalEl) payGrandTotalEl.textContent = formattedTotal;
                if (payTotalDueEl) payTotalDueEl.textContent = formattedTotal;
            } else {
                const subtotalFormatted = subtotalBeforeTip.toFixed(2) + ' ' + currency;

                if (tipRow) {
                    tipRow.classList.remove('is-visible');
                    tipRow.style.setProperty('display', 'none', 'important');
                }
                if (sumTotalPrice) sumTotalPrice.textContent = subtotalFormatted;

                if (payTipRowEl) {
                    payTipRowEl.classList.add('is-hidden');
                    payTipRowEl.style.setProperty('display', 'none', 'important');
                }
                if (payGrandTotalEl) payGrandTotalEl.textContent = subtotalFormatted;
                if (payTotalDueEl) payTotalDueEl.textContent = subtotalFormatted;
            }

            if (currentCheckoutStep === 2 && submitText) {
                const finalBtnTotal = (tipVal > 0) ? formattedTotal : (subtotalBeforeTip.toFixed(2) + ' ' + currency);
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

            // Formatage raffiné de la date (ex: 03 Oct. 2026 at 09:00 AM)
            const formatSummaryDateTime = (dStr, tStr) => {
                if (!dStr) return '—';
                const parts = dStr.split('-');
                let dateFormatted = dStr;
                if (parts.length === 3) {
                    const monthsEn = ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'May', 'Jun.', 'Jul.', 'Aug.', 'Sep.', 'Oct.', 'Nov.', 'Dec.'];
                    const mIdx = parseInt(parts[1], 10) - 1;
                    const day = String(parts[2]).padStart(2, '0');
                    const month = monthsEn[mIdx] || parts[1];
                    dateFormatted = `${day} ${month} ${parts[0]}`;
                }
                return tStr ? `${dateFormatted} at ${tStr}` : dateFormatted;
            };

            if (sumDatetime && dateVal) {
                sumDatetime.textContent = formatSummaryDateTime(dateVal, timeVal);
            }

             // Calcul financier de base (précision centimes)
            baseNumericPrice = parseFloat(rawPrice) || 0;
            isQuoteRide      = (rawPrice === 'Custom Quote' || baseNumericPrice <= 0);

            const formattedPrice = isQuoteRide ? 'Custom Quote' : `${baseNumericPrice.toFixed(2)} ${currency}`;
            if (sumBasePrice) sumBasePrice.textContent = formattedPrice;
            if (sumTotalPrice) sumTotalPrice.textContent = formattedPrice;

            // ADAPTATION SUR-MESURE DE LA COLONNE RÉCAPITULATIF POUR CUSTOM QUOTE
            const tipSectionEl    = checkoutRoot.querySelector('.etb-chk-tip-section');
            const baseFareRowEl   = checkoutRoot.querySelector('.etb-chk-price-row:not(.total-row)');
            const totalLabelEl    = checkoutRoot.querySelector('.etb-chk-price-row.total-row span:first-child');
            const guaranteesBoxEl = checkoutRoot.querySelector('.etb-chk-guarantees');

            // Détection dynamique en temps réel de la réservation urgente (< 24h)
            const checkIsUrgentCheckout = () => {
                const rawDate = dateInput ? dateInput.value.trim() : '';
                const rawTime = timeInput ? timeInput.value.trim() : '';
                if (!rawDate || !rawTime || rawTime === '-- : --') return false;

                let h = 9, m = 0;
                const cleanTime = rawTime.toUpperCase();
                if (cleanTime.includes('AM') || cleanTime.includes('PM')) {
                    const parts = cleanTime.split(/\s+/);
                    const hm = (parts[0] || '').split(':');
                    h = parseInt(hm[0], 10) || 0;
                    m = parseInt(hm[1], 10) || 0;
                    if (cleanTime.includes('PM') && h < 12) h += 12;
                    if (cleanTime.includes('AM') && h === 12) h = 0;
                } else {
                    const hm = cleanTime.split(':');
                    h = parseInt(hm[0], 10) || 0;
                    m = parseInt(hm[1], 10) || 0;
                }

                const dParts = rawDate.split('-');
                if (dParts.length !== 3) return false;

                const pickupDate = new Date(parseInt(dParts[0], 10), parseInt(dParts[1], 10) - 1, parseInt(dParts[2], 10), h, m, 0);
                const now = new Date();
                const diffHours = (pickupDate.getTime() - now.getTime()) / (1000 * 60 * 60);

                // Urgent si la prise en charge a lieu entre maintenant et moins de 24h
                return diffHours >= 0 && diffHours < 24;
            };

            const isUrgentInput  = checkoutRoot.querySelector('#etb-chk-is-urgent');
            const urgentNoticeEl = checkoutRoot.querySelector('#etb-chk-urgent-notice-box');
            const payLaterWrap   = checkoutRoot.querySelector('#etb-chk-pay-later-wrap');

            // Évaluation synchronisée : validée si calculée par le navigateur OU initialisée par PHP
            const isUrgentRide = checkIsUrgentCheckout() || (isUrgentInput && isUrgentInput.value === '1');

            if (isUrgentInput) isUrgentInput.value = isUrgentRide ? '1' : '0';
            if (urgentNoticeEl) urgentNoticeEl.style.display = isUrgentRide ? 'block' : 'none';
            if (payLaterWrap && isUrgentRide) payLaterWrap.style.display = 'none';

            if (isQuoteRide) {
                // 1. Bouton d'action Devis
                if (submitText) submitText.textContent = 'Submit Quote Request';

                // 2. Masquage total de la section pourboire
                if (tipSectionEl) tipSectionEl.style.setProperty('display', 'none', 'important');
            } else if (isUrgentRide) {
                // Bouton d'action Course Urgente (< 24h)
                if (submitText) submitText.textContent = 'Request Urgent Booking';
                
                // 3. Masquage de la ligne Base Fare redondante
                if (baseFareRowEl) baseFareRowEl.style.setProperty('display', 'none', 'important');

                // 4. Libellé propre : "Estimated Rate" au lieu de "Total (All Inclusive)"
                if (totalLabelEl) totalLabelEl.textContent = 'Estimated Rate';

                // 5. Engagements adaptés au devis
                if (guaranteesBoxEl) {
                    guaranteesBoxEl.innerHTML = `
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-clock"></span>
                            <span>Tailored quotation sent within 1 hour</span>
                        </div>
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-shield"></span>
                            <span>Licensed professional chauffeur & premium vehicle</span>
                        </div>
                        <div class="etb-chk-guarantee-line">
                            <span class="dashicons dashicons-yes"></span>
                            <span>No payment required until quotation is approved</span>
                        </div>
                    `;
                }
            } else {
                // Course standard à tarif fixe : affichage normal
                if (tipSectionEl) tipSectionEl.style.display = '';
                if (baseFareRowEl) baseFareRowEl.style.display = 'flex';
                if (totalLabelEl) totalLabelEl.textContent = 'Total (All Inclusive)';
            }

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
                    if (flightLabel)  flightLabel.textContent  = 'Flight Number';
                    if (flightHint)   flightHint.textContent   = '1 hour free waiting time after landing.';
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
                    if (signField) {
                        const signInput = signField.querySelector('input');
                        if (signInput) signInput.value = '';
                    }
                }
            }

            // Initialisation des compteurs de passagers/bagages avec plafond fixe
            const maxPaxAllowed = parseInt(sumPax ? sumPax.textContent : 3, 10) || 3;
            const maxBagAllowed = parseInt(sumBag ? sumBag.textContent : 2, 10) || 2;

            if (paxHint) paxHint.textContent = `Max: ${maxPaxAllowed}`;
            if (bagHint) bagHint.textContent = '-'; /*`Max: ${maxBagAllowed}`;*/

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

        // 4. Écouteurs des pilules de pourboire avec animation d'émoji jaillissant
        tipPills.forEach(pill => {
            pill.addEventListener('click', function (e) {
                if (e.target.tagName && e.target.tagName.toLowerCase() === 'input') {
                    return;
                }

                tipPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');

                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;

                const percent = parseInt(this.dataset.tip, 10) || 0;

                // ── Émoji Animé Google Noto (Jaillit, s'élève et s'évapore) ──
                if (percent > 0) {
                    const notoCodeMap = {
                        10: '1f64f', // 🙏 Merci (Folded Hands)
                        15: '1f929', // 🤩 Star-Struck
                        20: '1f60d'  // 😍 Heart Eyes
                    };

                    const emojiCode = notoCodeMap[percent];
                    if (emojiCode) {
                        const rect   = this.getBoundingClientRect();
                        const startX = rect.left + rect.width / 2;
                        const startY = rect.top; // Part du sommet de la pilule

                        const burstContainer = document.createElement('div');
                        burstContainer.className = 'etb-animated-burst-emoji';
                        burstContainer.style.left = startX + 'px';
                        burstContainer.style.top  = startY + 'px';

                        burstContainer.innerHTML = `
                            <picture>
                                <source srcset="https://fonts.gstatic.com/s/e/notoemoji/latest/${emojiCode}/512.webp" type="image/webp">
                                <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/${emojiCode}/512.gif" alt="tip-reaction">
                            </picture>
                        `;

                        document.body.appendChild(burstContainer);

                        // Nettoyage automatique du DOM après 1.3 seconde
                        setTimeout(() => burstContainer.remove(), 1300);
                    }
                }

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

        // 6. Auto-remplissage de la pancarte : Prénom titré + NOM EN MAJUSCULE
        const updateGreetingSign = () => {
            const fName = firstNameInput ? firstNameInput.value.trim() : '';
            const lName = lastNameInput ? lastNameInput.value.trim() : '';
            if (pickupSignInput && !pickupSignInput.dataset.manualEdit) {
                const isSignVisible = airportCard && airportCard.style.display !== 'none' && checkoutRoot.querySelector('#etb-chk-sign-field')?.style.display !== 'none';
                if (isSignVisible && (fName || lName)) {
                    // 1. Prénom : Première lettre en majuscule, reste en minuscule (ex: "krasimir" -> "Krasimir")
                    const formattedFirst = fName ? fName.split(/(\s+|-)/).map(part => {
                        if (part.trim() === '' || part === '-') return part;
                        return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
                    }).join('') : '';

                    // 2. Nom : Tout en majuscule (ex: "ivanov" -> "IVANOV")
                    const formattedLast = lName ? lName.toUpperCase() : '';

                    // 3. Combinaison harmonieuse
                    if (formattedFirst && formattedLast) {
                        pickupSignInput.value = `${formattedFirst} ${formattedLast}`;
                    } else {
                        pickupSignInput.value = formattedLast || formattedFirst;
                    }
                } else {
                    pickupSignInput.value = ''; // Reste vide si masqué
                }
            }
        };

        if (firstNameInput) firstNameInput.addEventListener('input', updateGreetingSign);

        // Majuscule automatique sur le nom de famille (Last Name) lors de la saisie
        if (lastNameInput) {
            lastNameInput.addEventListener('input', function () {
                const start = this.selectionStart;
                const end   = this.selectionEnd;
                this.value  = this.value.toUpperCase();
                if (start !== null && end !== null) {
                    this.setSelectionRange(start, end);
                }
                updateGreetingSign();
            });
        }

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
                // Déverrouillé : le client peut ajouter ses bagages librement (jusqu'à 20)
                if (val < 20) {
                    bagInputEl.value = val + 1;
                }
            });
        }

        // Compteur Cabin Bags (Bagages cabine) déverrouillé jusqu'à 20
        if (cabinBagMinusBtn && cabinBagPlusBtn && cabinBagInputEl) {
            cabinBagMinusBtn.addEventListener('click', function () {
                let val = parseInt(cabinBagInputEl.value, 10) || 0;
                if (val > 0) {
                    cabinBagInputEl.value = val - 1;
                }
            });

            cabinBagPlusBtn.addEventListener('click', function () {
                let val = parseInt(cabinBagInputEl.value, 10) || 0;
                if (val < 20) {
                    cabinBagInputEl.value = val + 1;
                }
            });
        }

        // Récupère le pourcentage de pourboire actuellement sélectionné
        const getCurrentTipPercent = () => {
            const activeTipPill = checkoutRoot.querySelector('.etb-tip-pill.active');
            return activeTipPill ? (parseInt(activeTipPill.dataset.tip, 10) || 0) : 0;
        };

        // 8. Contrôle des sièges enfants (+/-) avec mise à jour immédiate
        if (seatMinusBtn && seatPlusBtn && seatInput) {
            seatMinusBtn.addEventListener('click', function () {
                let val = parseInt(seatInput.value, 10) || 0;
                if (val > 0) {
                    seatInput.value = val - 1;
                    const activePill = checkoutRoot.querySelector('.etb-tip-pill.active');
                    const tipPct = activePill ? (parseInt(activePill.dataset.tip, 10) || 0) : 0;
                    recalculateTotalWithTip(tipPct);
                }
            });

            seatPlusBtn.addEventListener('click', function () {
                let val = parseInt(seatInput.value, 10) || 0;
                // Plafonné à 2 sièges bébé maximum
                if (val < 2) {
                    seatInput.value = val + 1;
                    const activePill = checkoutRoot.querySelector('.etb-tip-pill.active');
                    const tipPct = activePill ? (parseInt(activePill.dataset.tip, 10) || 0) : 0;
                    recalculateTotalWithTip(tipPct);
                }
            });
        }

        if (boosterMinusBtn && boosterPlusBtn && boosterInput) {
            boosterMinusBtn.addEventListener('click', function () {
                let val = parseInt(boosterInput.value, 10) || 0;
                if (val > 0) {
                    boosterInput.value = val - 1;
                    const activePill = checkoutRoot.querySelector('.etb-tip-pill.active');
                    const tipPct = activePill ? (parseInt(activePill.dataset.tip, 10) || 0) : 0;
                    recalculateTotalWithTip(tipPct);
                }
            });

            boosterPlusBtn.addEventListener('click', function () {
                let val = parseInt(boosterInput.value, 10) || 0;
                // Plafonné à 3 rehausseurs maximum
                if (val < 3) {
                    boosterInput.value = val + 1;
                    const activePill = checkoutRoot.querySelector('.etb-tip-pill.active');
                    const tipPct = activePill ? (parseInt(activePill.dataset.tip, 10) || 0) : 0;
                    recalculateTotalWithTip(tipPct);
                }
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
                if (submitText) submitText.textContent = isQuoteRide ? 'Submit Quote Request' : 'Continue to Payment';
                checkoutRoot.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        // 11bis. Fonction universelle de finalisation et dispatch (Accessible pour Étape 1 & Étape 2)
        const executeFinalOrderDispatch = (paymentLogsData, isPayLater = false) => {
            const originalBtnText = isQuoteRide ? 'Submit Quote Request' : 'Continue to Payment';
            if (submitText) submitText.textContent = isPayLater ? 'Registering...' : 'Dispatching ...';

            const emailVal = checkoutRoot.querySelector('#etb-passenger-email')?.value.trim() || '';
            const tipVal   = checkoutRoot.querySelector('#etb-chk-tip-amount')?.value || '0';

            const formData = new FormData(formEl);

            // Injection du numéro international certifié E.164 (ex: +33612345678) pour LimoExpress
            if (phoneIti) {
                const fullPhone = phoneIti.getNumber();
                if (fullPhone) {
                    formData.set('etb_phone', fullPhone);
                }
            }

            formData.append('action', 'etb_submit_checkout');
            formData.append('nonce', etbAjax.nonce);
            if (isPayLater) {
                formData.set('etb_pay_later', '1');
            }

            fetch(etbAjax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if (res.success) {
                    try { sessionStorage.removeItem('etb_checkout_data'); } catch (e) {}

                    // SI PAIEMENT IMMÉDIAT : Redirection vers la page de paiement
                    const isExplicitPayLater = isPayLater || formData.get('etb_pay_later') === '1';
                    const isUrgentBooking    = formData.get('etb_is_urgent') === '1';

                    if (!isQuoteRide && !isExplicitPayLater && !isUrgentBooking && res.data && res.data.payment_url) {
                        if (submitText) submitText.textContent = 'Opening Payment...';
                        window.location.href = res.data.payment_url;
                        return;
                    }

                    // Déverrouillage si écran de confirmation sur place
                    setCheckoutButtonsLocked(false);

                    // Affichage de l'écran de confirmation
                    const homeReturnUrl = (typeof etbAjax !== 'undefined' && etbAjax.home_url) 
                        ? etbAjax.home_url 
                        : (window.location.origin + '/');

                    const leftCol = checkoutRoot.querySelector('.etb-checkout-left-col');
                    if (leftCol) {
                        const finalDisplayRef = res.data.booking_ref || ('#' + res.data.booking_id);
                        let successTitle    = 'Reservation Registered (Payment Pending)';
                        let successSubtitle = 'Your reservation <strong>#' + finalDisplayRef + '</strong> has been registered.';
                        let successDetails  = '<p style="margin: 6px 0;">An email with your secure payment link has been sent to <strong>' + emailVal + '</strong>.</p>'
                            + '<p style="margin: 6px 0;">You can complete your payment anytime before pickup to confirm your chauffeur.</p>';

                       if (isQuoteRide) {
                            successTitle    = 'Quote Request Successfully Submitted!';
                            successSubtitle = 'Your quote request <strong>#' + finalDisplayRef + '</strong> has been sent to our dispatch team.';
                            successDetails  = '<p style="margin: 6px 0;">An acknowledgment has been sent to <strong>' + emailVal + '</strong>.</p>'
                                + '<p style="margin: 6px 0;">Our dispatcher will send a tailored quotation within <strong>1 hour</strong>.</p>';
                       } else if (isUrgentBooking) {
                            successTitle    = 'Urgent Booking Received!';
                            successSubtitle = 'Your short-notice reservation <strong>#' + finalDisplayRef + '</strong> is being verified by dispatch.';
                            successDetails  = '<p style="margin: 6px 0;">Our team is immediately verifying chauffeur allocation for your pickup time.</p>'
                                + '<p style="margin: 6px 0;">You will receive final confirmation and your payment link within <strong>1 hour</strong>.</p>';
                        }

                        // Masquage propre de la colonne droite et centrage
                        const rightCol = checkoutRoot.querySelector('.etb-checkout-right-col');
                        if (rightCol) rightCol.style.setProperty('display', 'none', 'important');

                        const layoutGrid = checkoutRoot.querySelector('.etb-checkout-layout');
                        if (layoutGrid) {
                            layoutGrid.style.setProperty('grid-template-columns', '1fr', 'important');
                            layoutGrid.style.setProperty('max-width', '680px', 'important');
                            layoutGrid.style.setProperty('margin', '0 auto', 'important');
                        }

                        leftCol.innerHTML = '<div class="etb-checkout-card" style="text-align: center; padding: 45px 35px; border-color: #16a34a; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">'
                            + '<div style="display: inline-flex; align-items: center; justify-content: center; width: 68px; height: 68px; background: rgba(34, 197, 94, 0.15); border: 2px solid #22c55e; border-radius: 50%; margin-bottom: 20px; color: #4ade80;">'
                            + '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
                            + '</div>'
                            + '<h2 style="color: #4ade80; font-size: 24px; font-weight: 800; margin: 0 0 12px 0;">' + successTitle + '</h2>'
                            + '<p style="font-size: 15px; margin-bottom: 25px; line-height: 1.55;">' + successSubtitle + '</p>'
                            + '<div style="background: rgba(255,255,255,0.04); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.1)); border-radius: 12px; padding: 18px 22px; margin-bottom: 30px; text-align: left; font-size: 13.5px; line-height: 1.6;">'
                            + successDetails
                            + '</div>'
                            + '<div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">'
                            + (res.data.payment_url && isPayLater ? '<a href="' + res.data.payment_url + '" class="etb-chk-submit-btn" style="text-decoration: none; display: inline-flex; width: auto; padding: 14px 28px;">Proceed to Payment Now ➔</a>' : '')
                            + '<a href="' + homeReturnUrl + '" class="etb-chk-submit-btn" style="text-decoration: none; display: inline-flex; width: auto; padding: 14px 28px; background: #334155;">Return to Home</a>'
                            + '</div>'
                            + '</div>';

                        checkoutRoot.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                } else {
                    setCheckoutButtonsLocked(false);
                    showFeedback(res.data.message || 'Booking creation failed.', 'error');
                }
            })
            .catch(err => {
                console.error('Final dispatch error:', err);
                setCheckoutButtonsLocked(false);
                showFeedback('Communication error. Please try again.', 'error');
            });
        };

        // 12. Validation commune et gestion de soumission anti-doublon (Mutex Lock)
        let isSubmittingCheckout = false;

        const setCheckoutButtonsLocked = (locked, isPayLaterClick = false) => {
            isSubmittingCheckout = locked;

            if (submitBtn) {
                submitBtn.disabled = locked;
                if (locked && !isPayLaterClick) {
                    if (submitText) submitText.textContent = isQuoteRide ? 'Submitting Quote Request...' : 'Securing & Redirecting...';
                } else if (!locked) {
                    if (submitText) submitText.textContent = isQuoteRide ? 'Submit Quote Request' : 'Continue to Payment';
                }
            }

            if (payLaterLink) {
                if (locked) {
                    payLaterLink.style.pointerEvents = 'none';
                    payLaterLink.style.opacity = '0.4';
                    if (isPayLaterClick) payLaterLink.textContent = 'Registering order...';
                } else {
                    payLaterLink.style.pointerEvents = 'auto';
                    payLaterLink.style.opacity = '1';
                    payLaterLink.textContent = 'Or book now and Pay Later ➔';
                }
            }
        };

        const validatePassengerStep = () => {
            const fNameVal  = firstNameInput ? firstNameInput.value.trim() : '';
            const lNameVal  = lastNameInput ? lastNameInput.value.trim() : '';
            const emailVal  = checkoutRoot.querySelector('#etb-passenger-email')?.value.trim() || '';
            const phoneVal  = checkoutRoot.querySelector('#etb-passenger-phone')?.value.trim() || '';
            const vehicleId = vehicleIdInput ? vehicleIdInput.value : '';

            if (!fNameVal || !lNameVal) {
                showFeedback('Please enter the passenger first and last name.', 'error');
                return false;
            }
            if (!emailVal || !emailVal.includes('@')) {
                showFeedback('Please enter a valid email address for ride confirmation.', 'error');
                return false;
            }

            // Validation du numéro de téléphone avec détection intelligente par pays
            const rawPhone = phoneInputEl ? phoneInputEl.value.trim() : '';
            const digitsCount = rawPhone.replace(/\D/g, '').length;

            if (!rawPhone || digitsCount < 5) {
                showFeedback('Please enter a mobile phone number for chauffeur SMS updates.', 'error');
                if (phoneInputEl) phoneInputEl.focus();
                return false;
            }

            // Vérification de cohérence par pays avec Google libphonenumber si disponible
            if (phoneIti && typeof phoneIti.isValidNumber === 'function' && typeof window.intlTelInputUtils !== 'undefined') {
                if (!phoneIti.isValidNumber()) {
                    showFeedback('Please enter a valid phone number for the selected country.', 'error');
                    if (phoneInputEl) phoneInputEl.focus();
                    return false;
                }
            }

            if (!vehicleId) {
                showFeedback('No vehicle selected. Please return and choose a vehicle.', 'error');
                return false;
            }
            return true;
        };

        // Clic sur le Bouton Principal (Continue to Payment ou Submit Quote Request)
        if (submitBtn) {
            submitBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (feedbackEl) feedbackEl.style.display = 'none';

                if (isSubmittingCheckout) return;
                if (!validatePassengerStep()) return;

                // Verrouillage immédiat et total de tous les boutons et liens
                setCheckoutButtonsLocked(true, false);

                executeFinalOrderDispatch(null, false);
            });
        }

        // Clic sur le lien "Pay Later"
        const payLaterLink = checkoutRoot.querySelector('#etb-chk-pay-later-link');
        if (payLaterLink) {
            payLaterLink.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (feedbackEl) feedbackEl.style.display = 'none';

                if (isSubmittingCheckout) return;
                if (!validatePassengerStep()) return;

                // Verrouillage immédiat et total de tous les boutons et liens
                setCheckoutButtonsLocked(true, true);

                executeFinalOrderDispatch(null, true);
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
    // MOTEUR DE LA PAGE DE PAIEMENT AUTONOME ([etb_payment]) — STRIPE ELEMENTS
    // ==========================================================================
    const initStandalonePaymentPage = function () {
        const payRoot = document.querySelector('#etb-payment-page-app');
        if (!payRoot) return;

        const payForm       = payRoot.querySelector('#etb-standalone-payment-form');
        const payBtn        = payRoot.querySelector('#etb-standalone-pay-btn');
        const payText       = payRoot.querySelector('#etb-standalone-pay-text');
        const feedbackEl    = payRoot.querySelector('#etb-standalone-pay-feedback');
        const cardholderEl  = payRoot.querySelector('#etb-pay-cardholder');
        const countryEl     = payRoot.querySelector('#etb-pay-card-country');
        let walletPaymentRequest = null;

        
        // Custom Select Pays sur la page de paiement avec détection clavier & saut par lettre (Typeahead)
        const countrySelect = payRoot.querySelector('#etb-pay-country-select');
        if (countrySelect) {
            const trigger    = countrySelect.querySelector('.etb-custom-select-trigger');
            const label      = countrySelect.querySelector('#etb-pay-country-label');
            const optionsBox = countrySelect.querySelector('.etb-custom-select-options');
            const options    = countrySelect.querySelectorAll('.etb-custom-option');

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                countrySelect.classList.toggle('is-open');
            });

            const selectOption = function (opt) {
                options.forEach(o => o.classList.remove('selected', 'highlighted'));
                opt.classList.add('selected');
                const val = opt.dataset.val;
                if (label) label.textContent = opt.textContent.trim();
                if (countryEl) countryEl.value = val;
                countrySelect.classList.remove('is-open');
                trigger.focus();
            };

            options.forEach(opt => {
                opt.addEventListener('click', function (e) {
                    e.stopPropagation();
                    selectOption(this);
                });
            });

            // ── Navigation au clavier (Saut de lettre A-Z, Cycle & Touche Entrée) ──
            let keyBuffer   = '';
            let keyTimer    = null;
            let lastKeyChar = '';
            let cycleIndex  = 0;

            const handleKeyboardSearch = function (e) {
                if (e.key === 'Escape') {
                    countrySelect.classList.remove('is-open');
                    return;
                }

                if (e.key === 'Enter') {
                    if (countrySelect.classList.contains('is-open')) {
                        const highlighted = countrySelect.querySelector('.etb-custom-option.highlighted') 
                                         || countrySelect.querySelector('.etb-custom-option.selected');
                        if (highlighted) {
                            e.preventDefault();
                            selectOption(highlighted);
                            return;
                        }
                    }
                }

                // Détection de toute touche alphabétique (A-Z)
                if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                    e.preventDefault();

                    // Ouvre automatiquement la liste si elle est fermée
                    if (!countrySelect.classList.contains('is-open')) {
                        countrySelect.classList.add('is-open');
                    }

                    const pressedChar = e.key.toLowerCase();
                    clearTimeout(keyTimer);

                    // Cas 1 : Répétition de la même lettre (ex: F, puis F, puis F ➔ France ➔ Fiji ➔ Finland)
                    if (pressedChar === lastKeyChar && keyBuffer.length === 1) {
                        const letterMatches = Array.from(options).filter(opt => 
                            opt.textContent.trim().toLowerCase().startsWith(pressedChar)
                        );

                        if (letterMatches.length > 0) {
                            cycleIndex = (cycleIndex + 1) % letterMatches.length;
                            const targetOpt = letterMatches[cycleIndex];
                            options.forEach(o => o.classList.remove('highlighted'));
                            targetOpt.classList.add('highlighted');
                            targetOpt.scrollIntoView({ block: 'nearest' });
                        }
                    } else {
                        // Cas 2 : Recherche continue (ex: "sp" ➔ Spain)
                        keyBuffer += pressedChar;
                        cycleIndex = 0;

                        const matchedOpt = Array.from(options).find(opt => 
                            opt.textContent.trim().toLowerCase().startsWith(keyBuffer)
                        );

                        if (matchedOpt) {
                            options.forEach(o => o.classList.remove('highlighted'));
                            matchedOpt.classList.add('highlighted');
                            matchedOpt.scrollIntoView({ block: 'nearest' });
                        }
                    }

                    lastKeyChar = pressedChar;
                    keyTimer = setTimeout(() => {
                        keyBuffer = '';
                        lastKeyChar = '';
                    }, 700);
                }
            };

            countrySelect.addEventListener('keydown', handleKeyboardSearch);

            document.addEventListener('click', function (e) {
                if (!countrySelect.contains(e.target)) countrySelect.classList.remove('is-open');
            });
        }

        // ── 0. Navigation interactive entre les onglets de paiement ──
        const tabButtons = payRoot.querySelectorAll('.etb-pay-tab-btn');
        const panels     = payRoot.querySelectorAll('.etb-pay-panel');

        const switchPaymentTab = function (targetTab) {
            // Mise à jour visuelle des boutons d'onglets
            tabButtons.forEach(btn => {
                const isActive = (btn.dataset.tab === targetTab);
                btn.classList.toggle('active', isActive);
                if (isActive) {
                    btn.style.border = '1.5px solid var(--etb-accent-gold, #fbac18)';
                    btn.style.background = 'rgba(251, 172, 24, 0.1)';
                    btn.style.color = 'var(--etb-text-primary, #ffffff)';
                } else {
                    btn.style.border = '1px solid var(--etb-border-light, rgba(255,255,255,0.12))';
                    btn.style.background = 'rgba(255,255,255,0.03)';
                    btn.style.color = 'var(--etb-text-secondary, #94a3b8)';
                }
            });

            // Affichage du panneau correspondant
            panels.forEach(p => p.style.display = 'none');
            const activePanel = payRoot.querySelector('#etb-panel-' + targetTab);
            if (activePanel) {
                activePanel.style.display = 'block';
            }
        };

        tabButtons.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                switchPaymentTab(this.dataset.tab);
            });
        });

        // Boutons de repli : "Pay with Credit Card instead"
        payRoot.querySelectorAll('.etb-switch-to-card-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                switchPaymentTab('card');
            });
        });

        // ── Gestion dynamique du Pourboire Chauffeur sur la page de paiement ──
        const tipPills       = payRoot.querySelectorAll('.etb-tip-pill');
        const baseFareInput  = payRoot.querySelector('#etb-pay-base-fare');
        const tipPctInput    = payRoot.querySelector('#etb-pay-tip-percent');
        const tipAmtInput    = payRoot.querySelector('#etb-pay-tip-amount');
        const amountInput    = payRoot.querySelector('#etb-pay-amount');
        const displayTotalEl = payRoot.querySelector('#etb-pay-display-total');
        const tipLineEl      = payRoot.querySelector('#etb-pay-display-tip-line');
        const tipTextEl      = payRoot.querySelector('#etb-pay-display-tip-text');
        const tipPctEl       = payRoot.querySelector('#etb-pay-display-tip-pct');
        const currencySym    = (typeof etbAjax !== 'undefined' && etbAjax.currency) ? etbAjax.currency : '€';

        // Parseur sécurisé de prix (gère virgules, espaces et devises)
        const parseMoneyValue = function (val) {
            if (!val) return 0;
            const clean = String(val).replace(',', '.').replace(/[^-0-9.]/g, '');
            return parseFloat(clean) || 0;
        };

        const updateStandaloneTip = function (pct) {
            const seatsFeeEl = payRoot.querySelector('#etb-pay-child-seat-fee');
            const seatsFee   = parseMoneyValue(seatsFeeEl ? seatsFeeEl.value : 0);

            let baseFare = parseMoneyValue(baseFareInput ? baseFareInput.value : 0);
            if (baseFare <= 0) {
                const currentTotal = parseMoneyValue(amountInput ? amountInput.value : 0) 
                                  || parseMoneyValue(displayTotalEl ? displayTotalEl.textContent : 0);
                const currentTip   = parseMoneyValue(tipAmtInput ? tipAmtInput.value : 0);
                baseFare = (currentTotal > (currentTip + seatsFee)) ? (currentTotal - currentTip - seatsFee) : currentTotal;
                
                if (baseFare > 0 && baseFareInput) {
                    baseFareInput.value = baseFare.toFixed(2);
                }
            }

            const tipVal   = Math.round((baseFare * (pct / 100)) * 100) / 100;
            const newTotal = baseFare + seatsFee + tipVal;

            // Synchronisation des inputs
            if (tipPctInput) tipPctInput.value = pct;
            if (tipAmtInput) tipAmtInput.value = tipVal.toFixed(2);
            if (amountInput) amountInput.value = newTotal.toFixed(2);

           const formattedTotal = newTotal.toFixed(2) + ' ' + currencySym;
            const formattedTip   = tipVal.toFixed(2) + ' ' + currencySym;

            // Micro-transition fluide des montants (fondu doux)
            const smoothFadeUpdate = function (element, text) {
                if (!element || element.textContent === text) return;
                element.style.transition = 'opacity 0.16s ease, transform 0.16s ease';
                element.style.opacity   = '0.35';
                element.style.transform = 'translateY(-2px)';
                setTimeout(() => {
                    element.textContent     = text;
                    element.style.opacity   = '1';
                    element.style.transform = 'translateY(0)';
                }, 85);
            };

            // Application de la transition fluide
            smoothFadeUpdate(displayTotalEl, formattedTotal);
            if (payText) smoothFadeUpdate(payText, `Pay ${formattedTotal}`);

            payRoot.querySelectorAll('.etb-wallet-display-amount').forEach(el => {
                smoothFadeUpdate(el, formattedTotal);
            });

            // Synchronisation en temps réel du montant dans Google Pay & Apple Pay avec pourboire
            if (walletPaymentRequest) {
                const limoRefVal        = payRoot.querySelector('#etb-pay-limo-id')?.value;
                const bookingIdVal      = payRoot.querySelector('#etb-pay-booking-id')?.value || '0';
                const missionDisplayRef = (limoRefVal && limoRefVal !== 'OK') ? ('#' + limoRefVal) : ('#' + bookingIdVal);

                walletPaymentRequest.update({
                    total: {
                        label: 'Mission ' + missionDisplayRef,
                        amount: Math.round(newTotal * 100), // Montant en centimes incluant le pourboire
                    }
                });
            }

            if (tipLineEl) {
                if (pct > 0) {
                    tipLineEl.style.setProperty('display', 'flex', 'important');
                    if (tipTextEl) tipTextEl.textContent = '+' + formattedTip;
                    if (tipPctEl) tipPctEl.textContent = pct + '%';
                } else {
                    tipLineEl.style.setProperty('display', 'none', 'important');
                }
            }

            // Mise à jour instantanée du micro-récapitulatif dans les volets Apple Pay et Google Pay
            payRoot.querySelectorAll('.etb-wallet-display-amount').forEach(el => {
                el.textContent = formattedTotal;
            });
            payRoot.querySelectorAll('.etb-wallet-tip-detail').forEach(el => {
                el.style.display = (pct > 0) ? 'block' : 'none';
            });
            payRoot.querySelectorAll('.etb-wallet-tip-text').forEach(el => {
                el.textContent = '+' + formattedTip;
            });
            payRoot.querySelectorAll('.etb-wallet-tip-pct').forEach(el => {
                el.textContent = pct + '%';
            });
// ── Émoji persistant : s'anime 2.5s, se fige, et se réveille au survol ──
            const notoMap = {
                10: '1f64f', // 🙏 Merci
                15: '1f929', // 🤩 Étoiles
                20: '1f60d'  // 😍 Cœurs
            };

            payRoot.querySelectorAll('.etb-tip-persistent-badge').forEach(badge => {
                if (pct > 0 && notoMap[pct]) {
                    const code       = notoMap[pct];
                    const img        = badge.querySelector('.etb-tip-badge-img');
                    const animUrl    = `https://fonts.gstatic.com/s/e/notoemoji/latest/${code}/512.webp`;
                    const staticUrl  = `https://fonts.gstatic.com/s/e/notoemoji/latest/${code}/512.png`;

                    badge.dataset.animUrl   = animUrl;
                    badge.dataset.staticUrl = staticUrl;

                    if (img) {
                        // 1. Lance l'animation en WebP
                        img.src = animUrl;
                        badge.style.display = 'flex';

                        // 2. Fige l'animation en PNG statique après 2.5 secondes
                        clearTimeout(badge._stopTimer);
                        badge._stopTimer = setTimeout(() => {
                            if (badge.dataset.staticUrl) {
                                img.src = badge.dataset.staticUrl;
                            }
                        }, 2500);
                    }
                } else {
                    clearTimeout(badge._stopTimer);
                    badge.style.display = 'none';
                }
            });
        };

        // Écouteur de clic synchronisé sur TOUTES les pilules de pourboire (Carte, Apple Pay, Google Pay)
        payRoot.querySelectorAll('.etb-tip-pill').forEach(pill => {
            pill.addEventListener('click', function (e) {
                if (e.target.tagName && e.target.tagName.toLowerCase() === 'input') {
                    return;
                }

                const pct = parseInt(this.dataset.tip, 10) || 0;

                // ── Émoji Animé Google Noto unique (Jaillit, s'anime et s'évapore) ──
                if (pct > 0) {
                    const notoCodeMap = {
                        10: '1f64f', // 🙏 Folded Hands (Merci)
                        15: '1f929', // 🤩 Star-Struck
                        20: '1f60d'  // 😍 Heart Eyes (Votre exemple)
                    };

                    const emojiCode = notoCodeMap[pct];
                    if (emojiCode) {
                        const rect   = this.getBoundingClientRect();
                        const startX = rect.left + rect.width / 2;
                        const startY = rect.top; // Part du haut de la pilule

                        // Création du conteneur picture / webp animé haute performance
                        const burstContainer = document.createElement('div');
                        burstContainer.className = 'etb-animated-burst-emoji';
                        burstContainer.style.left = startX + 'px';
                        burstContainer.style.top  = startY + 'px';

                        burstContainer.innerHTML = `
                            <picture>
                                <source srcset="https://fonts.gstatic.com/s/e/notoemoji/latest/${emojiCode}/512.webp" type="image/webp">
                                <img src="https://fonts.gstatic.com/s/e/notoemoji/latest/${emojiCode}/512.gif" alt="tip-reaction">
                            </picture>
                        `;

                        document.body.appendChild(burstContainer);

                        // Nettoyage automatique du DOM après 1.3 seconde
                        setTimeout(() => burstContainer.remove(), 1300);
                    }
                }

                // Synchronisation visuelle de toutes les pilules du formulaire avec le même pourcentage
                payRoot.querySelectorAll('.etb-tip-pill').forEach(p => {
                    const isSameTip = (parseInt(p.dataset.tip, 10) || 0) === pct;
                    p.classList.toggle('active', isSameTip);
                    const radio = p.querySelector('input[type="radio"]');
                    if (radio) radio.checked = isSameTip;
                });

                updateStandaloneTip(pct);
            });
        });

        // ── Écouteur de survol pour réveiller l'animation de l'émoji au passage de la souris ──
        payRoot.querySelectorAll('.etb-tip-persistent-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function () {
                const img     = this.querySelector('.etb-tip-badge-img');
                const animUrl = this.dataset.animUrl;
                if (img && animUrl) {
                    img.src = animUrl; // Relance l'animation

                    clearTimeout(this._stopTimer);
                    this._stopTimer = setTimeout(() => {
                        if (this.dataset.staticUrl) {
                            img.src = this.dataset.staticUrl; // Se fige à nouveau
                        }
                    }, 2200);
                }
            });
        });

        // ── Initialisation sécurisée au chargement initial si un pourboire est pré-sélectionné ──
        const activeInitialPill = payRoot.querySelector('.etb-tip-pill.active');
        if (activeInitialPill) {
            const initialPct = parseInt(activeInitialPill.dataset.tip, 10) || 0;
            if (initialPct > 0) {
                updateStandaloneTip(initialPct);
            }
        }



        // Vérification de Stripe
        if (typeof Stripe === 'undefined' || typeof etbAjax === 'undefined' || etbAjax.stripe_enabled !== '1' || !etbAjax.stripe_pk) {
            if (feedbackEl) {
                feedbackEl.textContent = 'Payment gateway is currently in test mode or unconfigured.';
                feedbackEl.className = 'etb-chk-feedback is-error';
                feedbackEl.style.display = 'block';
            }
            return;
        }

        const stripe = Stripe(etbAjax.stripe_pk);
        const elements = stripe.elements();

        // ── 1. Montage du bouton Apple Pay / Google Pay (Payment Request) ──
        const walletWrapper = payRoot.querySelector('#etb-wallet-payment-wrapper');
        const initialAmount = parseMoneyValue(amountInput ? amountInput.value : 0);
        const bookingIdVal  = payRoot.querySelector('#etb-pay-booking-id')?.value || '';

        if (walletWrapper && initialAmount > 0) {
            paymentRequest = stripe.paymentRequest({
                country: (countryEl && countryEl.value) ? countryEl.value : 'FR',
                currency: 'eur',
                total: {
                    label: 'Booking #' + bookingIdVal,
                    amount: Math.round(initialAmount * 100),
                },
                requestPayerName: true,
                requestPayerEmail: true,
            });

            const isDark = document.documentElement.getAttribute('data-etb-theme') !== 'light' 
                        && localStorage.getItem('etb_theme_mode') !== 'light';

            const prButton = elements.create('paymentRequestButton', {
                paymentRequest: paymentRequest,
                style: {
                    paymentRequestButton: {
                        type: 'default',
                        theme: isDark ? 'dark' : 'light',
                        height: '46px',
                    },
                },
            });

            // Affichage conditionnel : uniquement si le wallet est supporté sur l'appareil
            paymentRequest.canMakePayment().then(function (result) {
                if (result) {
                    prButton.mount('#etb-payment-request-button');
                    walletWrapper.style.display = 'block';
                }
            });
        }

        // Style adaptatif Dark / Light
        const getElementStyle = () => {
            const isLight = document.documentElement.getAttribute('data-etb-theme') === 'light' 
                         || localStorage.getItem('etb_theme_mode') === 'light';
            return {
                base: {
                    color: isLight ? '#1e293b' : '#ffffff',
                    fontFamily: "'Inter', -apple-system, sans-serif",
                    fontSize: '14px',
                    fontWeight: '500',
                    letterSpacing: '0.03em',
                    '::placeholder': { color: isLight ? '#94a3b8' : 'rgba(148, 163, 184, 0.6)' }
                },
                invalid: {
                    color: '#f87171',
                    iconColor: '#f87171'
                }
            };
        };

        // Montage des 3 éléments individuels de Stripe avec détecteur de marque à gauche (Photo 2)
        const cardNumber = elements.create('cardNumber', { 
            style: getElementStyle(), 
            showIcon: true,
            iconStyle: 'solid'
        });
        const cardExpiry = elements.create('cardExpiry', { style: getElementStyle() });
        const cardCvc    = elements.create('cardCvc', { style: getElementStyle() });

        
        if (document.querySelector('#etb-card-number-mount')) cardNumber.mount('#etb-card-number-mount');
        if (document.querySelector('#etb-card-expiry-mount')) cardExpiry.mount('#etb-card-expiry-mount');
        if (document.querySelector('#etb-card-cvc-mount')) cardCvc.mount('#etb-card-cvc-mount');

        // Écoute de la bascule Dark/Light mode pour mettre à jour la couleur du texte DANS les iframes Stripe
        document.addEventListener('etb_theme_changed', function(e) {
            const isLight = (e.detail.theme === 'light');
            const newStyle = {
                base: {
                    color: isLight ? '#1e293b' : '#ffffff',
                    '::placeholder': { color: isLight ? '#94a3b8' : 'rgba(148, 163, 184, 0.6)' }
                }
            };
            if (cardNumber) cardNumber.update({ style: newStyle });
            if (cardExpiry) cardExpiry.update({ style: newStyle });
            if (cardCvc)    cardCvc.update({ style: newStyle });
        });

        // ── 2. Moteur Officiel Apple Pay & Google Pay (Sécurisé Stripe avec Référence Unifiée) ──
        const currentAmountVal   = parseMoneyValue(amountInput ? amountInput.value : 0);
        const bookingIdValue     = payRoot.querySelector('#etb-pay-booking-id')?.value || '';
        
        // Récupération de la référence officielle LimoExpress (ex: 205/2026) avec repli sécurisé
        const limoRefVal         = payRoot.querySelector('#etb-pay-limo-id')?.value;
        const missionDisplayRef  = (limoRefVal && limoRefVal !== 'OK') ? ('#' + limoRefVal) : ('#' + bookingIdValue);

        if (currentAmountVal > 0) {
            walletPaymentRequest = stripe.paymentRequest({
                country: (countryEl && countryEl.value) ? countryEl.value : 'FR',
                currency: 'eur',
                total: {
                    label: 'Mission ' + missionDisplayRef, // Affiché dans la fenêtre Apple Pay / GPay
                    amount: Math.round(currentAmountVal * 100),
                },
                requestPayerName: true,
                requestPayerEmail: true,
            });

            const isDarkTheme = document.documentElement.getAttribute('data-etb-theme') !== 'light' 
                             && localStorage.getItem('etb_theme_mode') !== 'light';

            // Détection matérielle et montage des boutons officiels
            walletPaymentRequest.canMakePayment().then(function (result) {
                if (!result) return;

                // A. Apple Pay disponible UNIQUEMENT (Affiche le pourboire + le bouton)
                if (result.applePay) {
                    const appleWrap     = payRoot.querySelector('#etb-apple-pay-active-wrap');
                    const appleFallback = payRoot.querySelector('#etb-apple-pay-fallback');
                    if (appleWrap) {
                        const appleBtn = elements.create('paymentRequestButton', {
                            paymentRequest: walletPaymentRequest,
                            style: { paymentRequestButton: { theme: isDarkTheme ? 'dark' : 'light', height: '48px', type: 'default' } }
                        });
                        appleBtn.mount('#etb-apple-pay-mount');
                        appleWrap.style.display = 'block';
                        if (appleFallback) appleFallback.style.display = 'none';
                    }
                }

                // B. Google Pay disponible UNIQUEMENT (Affiche le pourboire + le bouton)
                if (result.googlePay) {
                    const googleWrap     = payRoot.querySelector('#etb-google-pay-active-wrap');
                    const googleFallback = payRoot.querySelector('#etb-google-pay-fallback');
                    if (googleWrap) {
                        const googleBtn = elements.create('paymentRequestButton', {
                            paymentRequest: walletPaymentRequest,
                            style: { paymentRequestButton: { theme: isDarkTheme ? 'dark' : 'light', height: '48px', type: 'default' } }
                        });
                        googleBtn.mount('#etb-google-pay-mount');
                        googleWrap.style.display = 'block';
                        if (googleFallback) googleFallback.style.display = 'none';
                    }
                }
            });

            // C. Traitement réel et sécurisé de la transaction bancaire
            walletPaymentRequest.on('paymentmethod', async function (ev) {
                const bookingIdVal  = payRoot.querySelector('#etb-pay-booking-id')?.value || '0';
                const amountVal     = parseFloat(payRoot.querySelector('#etb-pay-amount')?.value || 0);
                const emailVal      = ev.payerEmail || payRoot.querySelector('#etb-pay-email')?.value.trim() || '';
                const cardholderVal = ev.payerName || payRoot.querySelector('#etb-pay-cardholder')?.value.trim() || 'Wallet Client';

                // 1. Création de l'intention de paiement Stripe
                const intentData = new FormData();
                intentData.append('action', 'etb_create_payment_intent');
                intentData.append('nonce', etbAjax.nonce);
                intentData.append('amount', amountVal);
                intentData.append('currency', 'eur');
                intentData.append('name', cardholderVal);
                intentData.append('email', emailVal);
                intentData.append('route', 'Settlement Mission ' + missionDisplayRef);

                try {
                    const intentRes = await fetch(etbAjax.ajax_url, { method: 'POST', body: intentData }).then(r => r.json());
                    if (!intentRes.success) {
                        ev.complete('fail');
                        showPayFeedback(intentRes.data.message || 'Payment initialization failed.', 'error');
                        return;
                    }

                    // 2. Débit sécurisé via Stripe
                    const confirmRes = await stripe.confirmCardPayment(
                        intentRes.data.client_secret,
                        { payment_method: ev.paymentMethod.id },
                        { handleActions: false }
                    );

                    if (confirmRes.error) {
                        ev.complete('fail');
                        showPayFeedback(confirmRes.error.message, 'error');
                        return;
                    }

                    ev.complete('success');

                    // 3. Clôture de la mission dans WordPress & LimoExpress
                    const pm = ev.paymentMethod;
                    const walletBrand = (pm.card && pm.card.wallet && pm.card.wallet.type) 
                        ? ( 'apple_pay' === pm.card.wallet.type ? 'Apple Pay' : 'Google Pay' )
                        : ( pm.card ? pm.card.brand : 'Wallet' );

                    const updateData = new FormData();
                    updateData.append('action', 'etb_settle_quote_payment');
                    updateData.append('nonce', etbAjax.nonce);
                    updateData.append('booking_id', bookingIdVal);
                    updateData.append('amount', amountVal);
                    updateData.append('payment_intent_id', confirmRes.paymentIntent.id);
                    updateData.append('card_last4', pm.card ? pm.card.last4 : 'Wallet');
                    updateData.append('card_brand', walletBrand);
                    updateData.append('card_exp', (pm.card && pm.card.exp_month) ? (pm.card.exp_month + '/' + pm.card.exp_year) : '');
                    updateData.append('tip_amount', payRoot.querySelector('#etb-pay-tip-amount')?.value || '0');
                    updateData.append('tip_percentage', payRoot.querySelector('#etb-pay-tip-percent')?.value || '0');

                    const settleRes = await fetch(etbAjax.ajax_url, { method: 'POST', body: updateData }).then(u => u.json());
                    if (settleRes.success) {
                        // Écran de confirmation de paiement réussi unifié avec Mission #205/2026
                        const layoutEl = payRoot.querySelector('.etb-checkout-layout');
                        if (layoutEl) {
                            layoutEl.style.setProperty('display', 'block', 'important');
                            layoutEl.innerHTML = '<div class="etb-checkout-card" style="text-align: center; padding: 50px 35px; border-color: #16a34a; max-width: 650px; margin: 0 auto; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">'
                                + '<div style="display: inline-flex; align-items: center; justify-content: center; width: 68px; height: 68px; background: rgba(34, 197, 94, 0.15); border: 2px solid #22c55e; border-radius: 50%; margin-bottom: 20px; color: #4ade80;">'
                                + '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
                                + '</div>'
                                + '<h2 style="color: #4ade80; font-size: 24px; font-weight: 800; margin: 0 0 10px 0;">Payment Authorized Successfully!</h2>'
                                + '<p style="font-size: 15px; margin-bottom: 25px; line-height: 1.55;">'
                                + 'Your payment of <strong>' + amountVal.toFixed(2) + ' €</strong> for Mission <strong>' + missionDisplayRef + '</strong> via <strong>' + walletBrand + '</strong> has been processed.'
                                + '</p>'
                                + '<div style="background: rgba(255,255,255,0.04); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.1)); border-radius: 12px; padding: 18px 22px; margin-bottom: 30px; text-align: left; font-size: 13.5px; line-height: 1.6;">'
                                + '<p style="margin: 6px 0;">An official paid confirmation receipt has been sent to <strong>' + (emailVal || 'your email') + '</strong>.</p>'
                                + '<p style="margin: 6px 0;">Your chauffeur will send an SMS update prior to pickup.</p>'
                                + '</div>'
                                + '<a href="' + (etbAjax.home_url || '/') + '" class="etb-chk-submit-btn" style="text-decoration: none; display: inline-flex; width: auto; padding: 14px 35px;">Return to Home</a>'
                                + '</div>';
                            payRoot.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    } else {
                        showPayFeedback(settleRes.data.message || 'Payment registered but dispatch update failed.', 'error');
                    }
                } catch (err) {
                    console.error('Wallet error:', err);
                    ev.complete('fail');
                    showPayFeedback('Communication error during wallet processing.', 'error');
                }
            });
        }
        
        // ── 3. Traitement officiel du paiement par Carte Bancaire au clic sur le bouton ──
        if (payBtn) {
            payBtn.addEventListener('click', async function (e) {
                e.preventDefault();
                if (feedbackEl) feedbackEl.style.display = 'none';

                const bookingIdVal  = payRoot.querySelector('#etb-pay-booking-id')?.value || '0';
                const amountVal     = parseFloat(payRoot.querySelector('#etb-pay-amount')?.value || 0);
                const emailVal      = payRoot.querySelector('#etb-pay-email')?.value.trim() || '';
                const cardholderVal = payRoot.querySelector('#etb-pay-cardholder')?.value.trim() || '';
                const countryVal    = payRoot.querySelector('#etb-pay-card-country')?.value || 'FR';
                const limoRefVal    = payRoot.querySelector('#etb-pay-limo-id')?.value;
                const missionRef    = (limoRefVal && limoRefVal !== 'OK') ? ('#' + limoRefVal) : ('#' + bookingIdVal);

                // Validations obligatoires
                if (!emailVal || !emailVal.includes('@')) {
                    showPayFeedback('Please enter a valid email address to receive your receipt.', 'error');
                    payRoot.querySelector('#etb-pay-email')?.focus();
                    return;
                }
                if (!cardholderVal) {
                    showPayFeedback('Please enter the name on the card.', 'error');
                    payRoot.querySelector('#etb-pay-cardholder')?.focus();
                    return;
                }
                if (amountVal <= 0) {
                    showPayFeedback('Invalid payment amount.', 'error');
                    return;
                }

                // Verrouillage du bouton pendant l'autorisation bancaire
                const originalBtnText = payText ? payText.textContent : 'Pay Now';
                payBtn.disabled = true;
                if (payText) payText.textContent = 'Authorizing payment...';

                try {
                  
                    // ÉTAPE A : Création de l'intention de paiement Stripe
                    const intentData = new FormData();
                    intentData.append('action', 'etb_create_payment_intent');
                    intentData.append('nonce', etbAjax.nonce);
                    intentData.append('amount', amountVal);
                    intentData.append('currency', 'eur');
                    intentData.append('name', cardholderVal);
                    intentData.append('email', emailVal);
                    intentData.append('phone', payRoot.querySelector('#etb-pay-client-phone')?.value || '');
                    intentData.append('country', countryVal);
                    intentData.append('route', 'Online Settlement Mission ' + missionRef);

                    const intentRes = await fetch(etbAjax.ajax_url, { method: 'POST', body: intentData }).then(r => r.json());

                    if (!intentRes.success) {
                        payBtn.disabled = false;
                        if (payText) payText.textContent = originalBtnText;
                        showPayFeedback(intentRes.data.message || 'Payment initialization failed.', 'error');
                        return;
                    }

                    // ÉTAPE B : Sécurisation 3D Secure et confirmation bancaire via Stripe.js
                    const confirmRes = await stripe.confirmCardPayment(intentRes.data.client_secret, {
                        payment_method: {
                            card: cardNumber,
                            billing_details: {
                                name: cardholderVal,
                                email: emailVal,
                                address: { country: countryVal }
                            }
                        }
                    });

                    if (confirmRes.error) {
                        payBtn.disabled = false;
                        if (payText) payText.textContent = originalBtnText;
                        showPayFeedback(confirmRes.error.message || 'Payment authorization failed.', 'error');
                        return;
                    }

                    // ÉTAPE C : Clôture officielle de la mission dans WordPress & LimoExpress
                    if (payText) payText.textContent = 'Confirming mission...';

                    const paymentIntent = confirmRes.paymentIntent;
                    let last4 = '4242';
                    let brand = 'card';
                    let exp   = '';

                    if (paymentIntent.charges && paymentIntent.charges.data && paymentIntent.charges.data.length > 0) {
                        const cardDetails = paymentIntent.charges.data[0].payment_method_details?.card;
                        if (cardDetails) {
                            last4 = cardDetails.last4 || last4;
                            brand = cardDetails.brand || brand;
                            exp   = (cardDetails.exp_month && cardDetails.exp_year) 
                                ? `${cardDetails.exp_month}/${String(cardDetails.exp_year).slice(-2)}` 
                                : '';
                        }
                    }

                    const settleData = new FormData();
                    settleData.append('action', 'etb_settle_quote_payment');
                    settleData.append('nonce', etbAjax.nonce);
                    settleData.append('booking_id', bookingIdVal);
                    settleData.append('amount', amountVal);
                    settleData.append('payment_intent_id', paymentIntent.id);
                    settleData.append('card_last4', last4);
                    settleData.append('card_brand', brand);
                    settleData.append('card_exp', exp);
                    settleData.append('tip_amount', payRoot.querySelector('#etb-pay-tip-amount')?.value || '0');
                    settleData.append('tip_percentage', payRoot.querySelector('#etb-pay-tip-percent')?.value || '0');

                    const settleRes = await fetch(etbAjax.ajax_url, { method: 'POST', body: settleData }).then(u => u.json());

                    if (settleRes.success) {
                        // Écran de confirmation de paiement réussi
                        const layoutEl = payRoot.querySelector('.etb-checkout-layout');
                        if (layoutEl) {
                            layoutEl.style.setProperty('display', 'block', 'important');
                            layoutEl.innerHTML = '<div class="etb-checkout-card" style="text-align: center; padding: 50px 35px; border-color: #16a34a; max-width: 650px; margin: 0 auto; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">'
                                + '<div style="display: inline-flex; align-items: center; justify-content: center; width: 68px; height: 68px; background: rgba(34, 197, 94, 0.15); border: 2px solid #22c55e; border-radius: 50%; margin-bottom: 20px; color: #4ade80;">'
                                + '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
                                + '</div>'
                                + '<h2 style="color: #4ade80; font-size: 24px; font-weight: 800; margin: 0 0 10px 0;">Payment Authorized Successfully!</h2>'
                                + '<p style="font-size: 15px; margin-bottom: 25px; line-height: 1.55;">'
                                + 'Your payment of <strong>' + amountVal.toFixed(2) + ' €</strong> for Mission <strong>' + missionRef + '</strong> has been processed.'
                                + '</p>'
                                + '<div style="background: rgba(255,255,255,0.04); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.1)); border-radius: 12px; padding: 18px 22px; margin-bottom: 30px; text-align: left; font-size: 13.5px; line-height: 1.6;">'
                                + '<p style="margin: 6px 0;">An official paid confirmation receipt has been sent to <strong>' + emailVal + '</strong>.</p>'
                                + '<p style="margin: 6px 0;">Your chauffeur has received the mission in dispatch.</p>'
                                + '</div>'
                                + '<a href="' + (etbAjax.home_url || '/') + '" class="etb-chk-submit-btn" style="text-decoration: none; display: inline-flex; width: auto; padding: 14px 35px;">Return to Home</a>'
                                + '</div>';
                            payRoot.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    } else {
                        payBtn.disabled = false;
                        if (payText) payText.textContent = originalBtnText;
                        showPayFeedback(settleRes.data?.message || 'Payment registered, but dispatch status update failed.', 'error');
                    }
                } catch (err) {
                    console.error('Credit card payment error:', err);
                    payBtn.disabled = false;
                    if (payText) payText.textContent = originalBtnText;
                    showPayFeedback('Communication error during payment. Please try again.', 'error');
                }
            });
        }

        const showPayFeedback = (msg, type) => {
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
        const applyTheme = (themeName) => {
            // Applique au conteneur racine <html> pour que les popups globales comme .pac-container en profitent
            document.documentElement.setAttribute('data-etb-theme', themeName);

           
            const targets = document.querySelectorAll('#etb-quick-widget-app, #etb-checkout-app, .co-circuit-wrapper');
            targets.forEach(el => {
                el.setAttribute('data-etb-theme', themeName);
            });

            // Déclenche un événement global pour avertir tous les modules (y compris Stripe) du changement de thème
            document.dispatchEvent(new CustomEvent('etb_theme_changed', { detail: { theme: themeName } }));
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
        initThemeDetector();          // Active la détection et la bascule Dark / Light
        init();                       // Initialise les circuits
        initQuickWidget();            // Initialise le widget minimal [etb_transfer]
        initCheckoutApp();            // Initialise le Checkout [etb_checkout]
        initStandalonePaymentPage();  // Initialise la page de paiement dédiée [etb_payment]
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startApp);
    } else {
        startApp();
    }
})();




