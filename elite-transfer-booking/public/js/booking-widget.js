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

          
            // 1. Calcul des véhicules avec algorithme intelligent des paliers horaires (Pack 10h, Heures Sup)
            allCards.forEach(card => {
                const qtyInput = card.querySelector('input[name^="etb_car_qty"]');
                const qty = parseInt(qtyInput ? qtyInput.value : 0);
                const name = card.querySelector('h3')?.textContent.trim() || 'Véhicule';
                const maxPax = parseInt(card.dataset.maxPax) || 0;
                const maxBaggage = parseInt(card.dataset.maxBaggage) || 0;

                card.classList.toggle('etb-selected', qty > 0);

                if (qty > 0) {
                    hasSelectedVehicle = true;
                    
                    // Extraction stricte des tarifs depuis les attributs de la carte
                    const hRate   = parseFloat(card.dataset.hourlyRate) || 0;
                    const bPrice  = parseFloat(card.dataset.basePrice) || 0;
                    const pack10h = parseFloat(card.dataset.pack10h) || 0;
                    const supRate = parseFloat(card.dataset.supHourRate) || 0;
                    
                    const standardHourly = hRate > 0 ? hRate : bPrice;
                    let vehicleUnitPrice = 0;

                    // Application exacte du moteur de calcul PHP
                    if (pack10h > 0 && duration >= 10.0) {
                        if (duration === 10.0) {
                            vehicleUnitPrice = pack10h;
                        } else {
                            const extraHours = duration - 10.0;
                            const extraRate  = supRate > 0 ? supRate : standardHourly;
                            vehicleUnitPrice = pack10h + (extraHours * extraRate);
                        }
                    } else {
                        vehicleUnitPrice = standardHourly * duration;
                    }

                    const subtotal = vehicleUnitPrice * qty;
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
        // 4bis. GESTION ACCORDÉON TIMELINE & INTERRUPTEUR EXPAND / COLLAPSE ALL
        // ------------------------------------------------------------------------
        
        // 1. Clic sur une étape individuelle (Ouvrir / Fermer)
        document.addEventListener('click', function (e) {
            const header = e.target.closest('.co-vip-step-header');
            if (!header) return;

            const step = header.closest('.co-vip-step');
            if (step) {
                step.classList.toggle('is-open');

                // Met à jour l'état visuel du bouton global "Expand all"
                const activePane = header.closest('.co-option-pane');
                if (activePane) {
                    updateExpandAllSwitchState(activePane);
                }
            }
        });

        // 2. Clic sur le switch global "Expand all / Collapse all"
        document.addEventListener('click', function (e) {
            const wrap = e.target.closest('.co-expand-all-wrap');
            if (!wrap) return;

            e.preventDefault();
            const activePane = wrap.closest('.co-option-pane') || document.querySelector('.co-option-pane.active');
            if (!activePane) return;

            const steps = activePane.querySelectorAll('.co-vip-step');
            const switchBtn = wrap.querySelector('.co-expand-toggle-switch');
            const thumb = wrap.querySelector('.co-expand-switch-thumb');
            const label = wrap.querySelector('.co-expand-all-label');

            const isCurrentlyExpanded = switchBtn && switchBtn.classList.contains('is-active');

            if (isCurrentlyExpanded) {
                // Tout fermer
                steps.forEach(s => s.classList.remove('is-open'));
                if (switchBtn) {
                    switchBtn.classList.remove('is-active');
                    switchBtn.style.background = '#cbd5e1';
                }
                if (thumb) thumb.style.transform = 'translateX(0)';
                if (label) label.textContent = 'Expand all';
            } else {
                // Tout ouvrir
                steps.forEach(s => s.classList.add('is-open'));
                if (switchBtn) {
                    switchBtn.classList.add('is-active');
                    switchBtn.style.background = 'var(--etb-theme-primary, #ba6827)';
                }
                if (thumb) thumb.style.transform = 'translateX(18px)';
                if (label) label.textContent = 'Collapse all';
            }
        });

        // Fonction utilitaire pour synchroniser le switch si l'utilisateur ouvre/ferme manuellement
        function updateExpandAllSwitchState(pane) {
            const wrap = pane.querySelector('.co-expand-all-wrap');
            if (!wrap) return;

            const steps = pane.querySelectorAll('.co-vip-step');
            const openSteps = pane.querySelectorAll('.co-vip-step.is-open');
            const switchBtn = wrap.querySelector('.co-expand-toggle-switch');
            const thumb = wrap.querySelector('.co-expand-switch-thumb');
            const label = wrap.querySelector('.co-expand-all-label');

            const allAreOpen = (steps.length > 0 && steps.length === openSteps.length);

            if (allAreOpen) {
                if (switchBtn) {
                    switchBtn.classList.add('is-active');
                    switchBtn.style.background = 'var(--etb-theme-primary, #ba6827)';
                }
                if (thumb) thumb.style.transform = 'translateX(18px)';
                if (label) label.textContent = 'Collapse all';
            } else {
                if (switchBtn) {
                    switchBtn.classList.remove('is-active');
                    switchBtn.style.background = '#cbd5e1';
                }
                if (thumb) thumb.style.transform = 'translateX(0)';
                if (label) label.textContent = 'Expand all';
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

        
// Éléments du panier Multi-booking et du bouton doré flottant au-dessus de la barre
        const bridgeTripWrap  = quickRoot.querySelector('#etb-bridge-trip-wrap');
        const bridgeTripBtn   = quickRoot.querySelector('#etb-bridge-trip-btn');
        const bridgeTripLabel = quickRoot.querySelector('#etb-bridge-trip-label');
        const tripsCartEl     = quickRoot.querySelector('#etb-quick-trips-cart');
        const tripsCountEl    = quickRoot.querySelector('#etb-cart-trips-count');
        const tripsListEl     = quickRoot.querySelector('#etb-cart-trips-list');
        let cartTrips         = [];
        
          // Note (#16 revert) : variable conservée pour éviter un ReferenceError
        // dans le bloc `if (addTripBtn)` plus bas. Reste à null (jamais assignée),
        // donc la branche est inerte — c'est du code mort inoffensif.
        const addTripBtn      = null;


        // Toggle d'activation Multi-Booking
        const multiTripToggle = quickRoot.querySelector('#etb-multi-trip-toggle');
        let isMultiBookingEnabled = false; // Désactivé par défaut

        if (multiTripToggle) {
            multiTripToggle.addEventListener('change', (e) => {
                isMultiBookingEnabled = e.target.checked;
                sessionStorage.setItem('etb_multi_booking_enabled', isMultiBookingEnabled ? '1' : '0');
                
                if (!isMultiBookingEnabled) {
                    // Désactivation : On vide le panier et on masque les boutons
                    cartTrips = [];
                    saveCartToStorage();
                    renderTripsCart();
                    
                    if (selectedCar) {
                        if (bridgeTripWrap) bridgeTripWrap.style.display = 'none';
                        if (bookBtnLabel) bookBtnLabel.textContent = selectedCar.isQuote ? 'Request' : 'Book Trip';
                    }
                } else {
                    // Activation : On réaffiche le bouton d'ajout si une voiture est déjà sélectionnée
                    if (selectedCar && bridgeTripWrap && bridgeTripLabel) {
                        bridgeTripWrap.style.display = 'flex';
                        bridgeTripLabel.textContent = 'Add another trip';
                    }
                }
            });
        }

        // ── GESTION DE LA PERSISTANCE VIP (SESSIONSTORAGE) ──
        const saveCartToStorage = () => {
            try {
                sessionStorage.setItem('etb_cart_trips', JSON.stringify(cartTrips));
            } catch (e) {}
        };

        const saveFormState = () => {
            try {
                const formState = {
                    mode: currentMode,
                    pickup: pickupInput ? pickupInput.value : '',
                    pickupLat: pickupLatEl ? pickupLatEl.value : '',
                    pickupLng: pickupLngEl ? pickupLngEl.value : '',
                    dropoff: dropoffInput ? dropoffInput.value : '',
                    dropoffLat: dropoffLatEl ? dropoffLatEl.value : '',
                    dropoffLng: dropoffLngEl ? dropoffLngEl.value : '',
                    date: dateInput ? dateInput.value : '',
                    time: timeInput ? timeInput.value : '',
                    duration: durationSelect ? durationSelect.value : '4'
                };
                sessionStorage.setItem('etb_quick_form_state', JSON.stringify(formState));
            } catch (e) {}
        };

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

        // ── ACTUALISATION EN DIRECT DU BANDEAU D'URGENCE (< 24H) ──
        const updateUrgentNoticeLive = () => {
            if (urgentNoticeEl && fleetSection && fleetSection.style.display !== 'none') {
                urgentNoticeEl.style.display = checkIsUrgent() ? 'block' : 'none';
            }
        };

        /// Mise à jour du texte de date et réévaluation instantanée de l'urgence (< 24h)
        if (dateInput && dateTextEl) {
            dateTextEl.textContent = formatCustomDate(dateInput.value);
            dateInput.addEventListener('change', function () {
                dateTextEl.textContent = formatCustomDate(this.value);
                updateUrgentNoticeLive();
            });
            dateInput.addEventListener('input', function () {
                dateTextEl.textContent = formatCustomDate(this.value);
                updateUrgentNoticeLive();
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

            /// Fonction interne de mise à jour de la valeur 12h formatée et réévaluation de l'urgence en direct
            const update12hTime = () => {
                const activeH     = timePopup.querySelector('.etb-hour-opt.active')?.dataset.val || '09';
                const activeM     = timePopup.querySelector('.etb-minute-opt.active')?.dataset.val || '00';
                const activeAmpm  = timePopup.querySelector('.etb-ampm-btn.active')?.dataset.val || 'AM';
                timeInput.value   = `${activeH}:${activeM} ${activeAmpm}`;
                
                // Réévaluation immédiate du bandeau rouge (< 24h) en direct
                updateUrgentNoticeLive();
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
            

                // ── DÉCLENCHEMENT DU RECALCUL EN TEMPS RÉEL LORS DU CHANGEMENT D'ADRESSE ──
        const handleAddressChangeLive = () => {
            // 🔒 UX Fix : Toute modification d'adresse (pickup OU dropoff) invalide
            // la sélection en cours et masque la barre de confirmation tant qu'aucun
            // véhicule n'est re-sélectionné.
            //
            // ⚠️ Le panier multi-trajets (cartTrips) N'EST JAMAIS TOUCHÉ ici.
            //    La barre n'est masquée QUE si le panier est vide, ce qui préserve
            //    intégralement l'affichage multi-booking (le cas échéant).
            selectedCar = null;

            carCards.forEach(c => {
                c.classList.remove('selected');
                const btn = c.querySelector('.etb-quick-select-btn');
                if (btn) {
                    btn.textContent = (c.dataset.isQuote === '1' || isQuoteMode) ? 'Request Quote' : 'Select';
                }
            });

            // Masquage conditionnel de la barre : uniquement si le panier est vide
            if (cartTrips.length === 0) {
                if (bookingBar) bookingBar.classList.remove('is-visible');
                if (bridgeTripWrap) bridgeTripWrap.style.display = 'none';
            }

            // Recalcul en direct selon le mode actif
            if (currentMode === 'transfer') {
                refreshLiveTransferPricing();
            } else if (currentMode === 'hourly') {
                if (fleetSection && fleetSection.style.display !== 'none') {
                    recalculateHourlyFleet();
                }
            }
        };

        if (pickupInput)  pickupInput.addEventListener('change', handleAddressChangeLive);
        if (dropoffInput) dropoffInput.addEventListener('change', handleAddressChangeLive);


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

                // Réinitialise l'état pour le nouveau mode
                isQuoteMode = false;
                if (quoteNoticeEl) quoteNoticeEl.classList.remove('is-visible');
                if (errorNotice) errorNotice.style.display = 'none';

                // Si le panier contient des trajets, le conteneur principal reste TOUJOURS visible
                if (cartTrips.length > 0) {
                    if (fleetSection) fleetSection.style.display = 'block';
                } else {
                    if (fleetSection) fleetSection.style.display = 'none';
                }

                // En mode horaire, recalcul direct et affichage instantané de la flotte
                if (currentMode === 'hourly' && fleetSection && fleetSection.style.display !== 'none') {
                    recalculateHourlyFleet();
                }
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

                // PRÉSERVATION CRITIQUE DU PANIER ET DE LA BARRE LORS DU CHANGEMENT DE MODE
                if (cartTrips.length > 0) {
                    if (fleetSection) fleetSection.style.display = 'block';
                } else {
                    if (fleetSection) fleetSection.style.display = 'none';
                }

                // En mode horaire : recalcul et réveil IMMÉDIATS des cartes sur la durée active (ex: 4h)
                if (currentMode === 'hourly') {
                    recalculateHourlyFleet();
                    if (fleetSection && fleetSection.style.display !== 'none') {
                        carCards.forEach(c => {
                            if (!c.classList.contains('etb-hidden')) c.style.display = 'flex';
                        });
                    }
                } else if (currentMode === 'transfer' && fleetSection && fleetSection.style.display !== 'none') {
                    // Si on repasse en transfert et que des coordonnées existent, rafraîchir en direct
                    refreshLiveTransferPricing();
                }
                saveFormState();
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

      
        // ── RAFRAÎCHISSEMENT EN DIRECT DU PRIX TRANSFERT (BASCULE FLUIDE PRIX ⇄ DEVIS) ──
        const refreshLiveTransferPricing = () => {
            // Uniquement si la flotte est affichée et en mode transfert
            if (currentMode !== 'transfer' || !fleetSection || fleetSection.style.display === 'none') {
                return;
            }

            const fromLat = pickupLatEl ? pickupLatEl.value : '';
            const fromLng = pickupLngEl ? pickupLngEl.value : '';
            const toLat   = dropoffLatEl ? dropoffLatEl.value : '';
            const toLng   = dropoffLngEl ? dropoffLngEl.value : '';

            // Si les deux coordonnées GPS ne sont pas complètes, on attend
            if (!fromLat || !fromLng || !toLat || !toLng) {
                return;
            }

            // 1. État de chargement satiné VIP sur les cartes
            carCards.forEach(c => {
                c.style.pointerEvents = 'none';
                const priceBox = c.querySelector('.etb-quick-price-display');
                if (priceBox) {
                    priceBox.style.transition = 'opacity 0.2s ease, filter 0.2s ease';
                    priceBox.style.opacity = '0.35';
                    priceBox.style.filter = 'blur(1px)';
                }
            });

            if (getPriceBtn) {
                getPriceBtn.classList.add('etb-loading-glow');
            }

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
                if (getPriceBtn) getPriceBtn.classList.remove('etb-loading-glow');

                // 2. Réactivation des cartes
                carCards.forEach(c => {
                    c.style.pointerEvents = 'auto';
                    const priceBox = c.querySelector('.etb-quick-price-display');
                    if (priceBox) {
                        priceBox.style.opacity = '1';
                        priceBox.style.filter = 'none';
                    }
                });

                if (res.success && res.data.pricing_data && res.data.pricing_data.length > 0) {
                    const pricingData = res.data.pricing_data;
                    let availableCarsCount = 0;

                    // A. RÉINITIALISATION DU MODE DEVIS -> RETOUR EN MODE TARIF NORMAL
                    isQuoteMode = false;
                    if (quoteNoticeEl) quoteNoticeEl.classList.remove('is-visible');

                    pricingData.forEach(item => {
                        if (!item || !item.vehicle_class || !item.vehicle_class.id || !item.prices || item.prices.length === 0) return;
                        const limoUuid = String(item.vehicle_class.id).trim().toLowerCase();
                        const targetCard = document.getElementById('limo-car-' + limoUuid) 
                                      || document.getElementById('limo-car-' + item.vehicle_class.id)
                                      || quickRoot.querySelector('[data-limo-class-id="' + limoUuid + '"]');

                        if (targetCard) {
                            const limoPrice = Math.round(item.prices[0].price);
                            const priceBox  = targetCard.querySelector('.etb-quick-price-display');
                            const detailBox = targetCard.querySelector('.etb-quick-price-detail');
                            const selBtn    = targetCard.querySelector('.etb-quick-select-btn');
                            const allInclEl = targetCard.querySelector('.etb-quick-all-inclusive');

                            if (limoPrice > 0) {
                                targetCard.dataset.calculatedPrice = limoPrice;
                                targetCard.dataset.isQuote = '0';

                                if (priceBox) {
                                    priceBox.innerHTML = `<span class="etb-quick-amount">${limoPrice}</span> <span class="etb-quick-currency">${currency}</span> <span class="etb-quick-all-inclusive">All inclusive</span>`;
                                }
                                if (detailBox) {
                                    detailBox.style.display = 'none';
                                    detailBox.innerHTML = '';
                                }
                                if (selBtn) selBtn.textContent = 'Select';
                                if (allInclEl) allInclEl.style.display = 'flex';

                                targetCard.style.display = 'flex';
                                targetCard.classList.remove('etb-hidden');
                                availableCarsCount++;
                            }
                        }
                    });

                    if (availableCarsCount === 0) {
                        // Trajet non chiffrable ➔ Bascule automatique en Devis
                        triggerCustomQuoteMode();
                    }
                } else {
                    // Hors zone (ex: Nice ➔ Paris) ➔ Bascule automatique en Devis en direct !
                    triggerCustomQuoteMode();
                }
            })
            .catch(() => {
                if (getPriceBtn) getPriceBtn.classList.remove('etb-loading-glow');
                carCards.forEach(c => {
                    c.style.pointerEvents = 'auto';
                    const priceBox = c.querySelector('.etb-quick-price-display');
                    if (priceBox) {
                        priceBox.style.opacity = '1';
                        priceBox.style.filter = 'none';
                    }
                });
                triggerCustomQuoteMode();
            });
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


        // ── GÉNÉRATION DYNAMIQUE DES LIENS WHATSAPP ET EMAIL ──
        const updateInquiryLinks = () => {
            const waPhone   = (typeof etbAjax !== 'undefined' && etbAjax.company_whatsapp) ? etbAjax.company_whatsapp : '';
            const adminMail = (typeof etbAjax !== 'undefined' && etbAjax.admin_email) ? etbAjax.admin_email : '';
            const emailInquiryBtn = quickRoot.querySelector('#etb-quick-email-btn');

            let message = '';
            const waveEmoji = String.fromCodePoint(0x1F44B);

            if (selectedCar) {
                // Message pour le véhicule actuellement sélectionné
                const pVal      = pickupInput ? pickupInput.value.trim() : '—';
                const isHourly  = (currentMode === 'hourly');
                const dVal      = isHourly ? `By the hour (${durationSelect ? durationSelect.value : 4}h)` : (dropoffInput ? dropoffInput.value.trim() : '—');
                const rawDate   = dateInput ? dateInput.value.trim() : '';
                const tmVal     = timeInput ? timeInput.value.trim() : '';
                const rateText  = selectedCar.isQuote ? 'Custom Quote' : `${selectedCar.price} ${currency}`;

                message = `Hello team ${waveEmoji},\n\n`
                    + `I would like to inquire about booking a transfer:\n\n`
                    + `*${selectedCar.name}*\n`
                    + `• Pickup: ${pVal}\n`
                    + `• Destination: ${dVal}\n`
                    + `• Date & Time: ${rawDate} at ${tmVal}\n`
                    + `• Rate: ${rateText}\n\n`
                    + `Thank you!`;
            } else if (cartTrips.length > 0) {
                // Message groupé pour l'ensemble du panier
                message = `Hello team ${waveEmoji},\n\n`
                    + `I would like to inquire about my scheduled itinerary (${cartTrips.length} transfers):\n\n`;

                let totalSum = 0;
                let hasQuote = false;

                cartTrips.forEach((t, i) => {
                    const rText = t.isQuote ? 'Quote' : `${t.price} ${currency}`;
                    if (t.isQuote) hasQuote = true; else totalSum += parseFloat(t.price) || 0;
                    message += `*Transfer #${i + 1} (${t.vehicle_name})*\n`
                        + `  • Route: ${t.pickup} ➔ ${t.dropoff}\n`
                        + `  • Date: ${t.date} at ${t.time}\n`
                        + `  • Rate: ${rText}\n\n`;
                });

                const finalSumText = hasQuote ? 'Custom Quote' : `${totalSum.toFixed(0)} ${currency}`;
                message += `*Estimated Total: ${finalSumText}*\n\nThank you!`;
            }

            if (!message) return;

            // 1. Mise à jour du lien WhatsApp
            if (whatsappBtn) {
                const waText = encodeURIComponent(message);
                whatsappBtn.href = waPhone 
                    ? `https://api.whatsapp.com/send?phone=${waPhone}&text=${waText}` 
                    : `https://api.whatsapp.com/send?text=${waText}`;
            }

            // 2. Mise à jour du lien Email (mailto)
            if (emailInquiryBtn) {
                const emailSubject = encodeURIComponent(`[Inquiry] VIP Transfer Booking`);
                const emailBody    = encodeURIComponent(message.replace(/\*/g, ''));
                emailInquiryBtn.href = `mailto:${adminMail}?subject=${emailSubject}&body=${emailBody}`;
            }
        };

        // 4. Sélection d'une voiture : Illumination, mise à jour des détails et affichage du bouton doré
        carCards.forEach(card => {
            card.addEventListener('click', function () {
                const isCarQuote = (this.dataset.isQuote === '1' || isQuoteMode);

                // Désélection si on reclique sur la même carte
                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                    selectedCar = null;
                    const btn = this.querySelector('.etb-quick-select-btn');
                    if (btn) btn.textContent = isCarQuote ? 'Request Quote' : 'Select';

                    if (cartTrips.length > 0) {
                        renderTripsCart();
                    } else {
                        if (bookingBar) bookingBar.classList.remove('is-visible');
                        if (bridgeTripWrap) bridgeTripWrap.style.display = 'none';
                    }
                    return;
                }

                // Désélectionne toutes les autres cartes
                carCards.forEach(c => {
                    c.classList.remove('selected');
                    const b = c.querySelector('.etb-quick-select-btn');
                    if (b) b.textContent = (c.dataset.isQuote === '1' || isQuoteMode) ? 'Request Quote' : 'Select';
                });

                // Illumine la carte cliquée
                this.classList.add('selected');
                const activeBtn = this.querySelector('.etb-quick-select-btn');
                if (activeBtn) activeBtn.textContent = 'Selected';

                const carName    = this.querySelector('h4')?.textContent.trim() || 'Vehicle';
                const carPrice   = this.dataset.calculatedPrice || '0';
                const carImgSrc  = this.querySelector('.etb-img-static')?.src || '';
                const maxPax     = this.dataset.maxPax || '1';
                const maxBag     = this.dataset.maxBag || '0';
                const amenitiesEl= this.querySelector('.etb-quick-amenities');
                const amenitiesHtml = amenitiesEl ? amenitiesEl.innerHTML : '';

                selectedCar = {
                    id: this.dataset.id,
                    name: carName,
                    price: carPrice,
                    isQuote: isCarQuote,
                    img: carImgSrc,
                    maxPax: maxPax,
                    maxBag: maxBag
                };

                // 1. Mise à jour de la barre du bas avec ce véhicule précis ET les détails de l'itinéraire
                const dynamicContentEl = quickRoot.querySelector('#etb-quick-dynamic-bar-content');
                if (dynamicContentEl) {
                    const priceHtml = isCarQuote 
                        ? '<span style="color: var(--etb-accent-gold, #fbac18); font-size: 20px; font-weight: 800;">Custom Quote</span>' 
                        : `<span style="color: var(--etb-accent-gold, #fbac18); font-size: 26px; font-weight: 900;">${carPrice} <span style="font-size: 18px;">${currency}</span></span>`;

                    // Récupération des informations de trajet en direct
                    const pInputEl   = quickRoot.querySelector('#etb-quick-pickup');
                    const dInputEl   = quickRoot.querySelector('#etb-quick-dropoff');
                    const pickupVal  = pInputEl ? pInputEl.value.trim() : '';
                    const dropoffVal = (currentMode === 'transfer' && dInputEl) ? dInputEl.value.trim() : '';
                    const dateVal    = dateInput ? dateInput.value.trim() : '';
                    const timeVal    = timeInput ? timeInput.value.trim() : '';
                    const durVal     = (currentMode === 'hourly' && durationSelect) ? durationSelect.value : '4';
                    
                    const formattedDateTime = (typeof formatLuxuryDateText === 'function') ? formatLuxuryDateText(dateVal, timeVal) : `${dateVal} • ${timeVal}`;

                    // Construction de la colonne itinéraire
                    const routeHtml = (currentMode === 'hourly')
                        ? `<div class="etb-route-line">
                               <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="var(--etb-accent-gold, #fbac18)" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                               <strong style="color: #ffffff; font-weight: 600;">By the hour (${durVal}h)</strong>
                           </div>
                           <div class="etb-route-line">
                               <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #22c55e; flex-shrink: 0;"></span>
                               <span>${pickupVal || '—'}</span>
                           </div>`
                        : `<div class="etb-route-line">
                               <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #22c55e; flex-shrink: 0;"></span>
                               <span>${pickupVal || '—'}</span>
                           </div>
                           <div class="etb-route-line">
                               <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--etb-accent-gold, #fbac18); flex-shrink: 0;"></span>
                               <span>${dropoffVal || '—'}</span>
                           </div>`;

                    dynamicContentEl.innerHTML = `
                        <div class="etb-bar-col1" style="display: flex; flex-direction: column; justify-content: center; flex-shrink: 0;">
                            <div class="etb-bar-v-title" style="white-space: nowrap;">
                                <strong style="color: var(--etb-text-primary, #ffffff); font-size: 15px;">${carName}</strong>
                            </div>
                            <div class="etb-bar-pax-bag" style="color: var(--etb-text-secondary, #94a3b8); font-size: 11.5px; margin-top: 3px;">
                                <span>${maxPax} Pax • ${maxBag} Luggage</span>
                            </div>
                        </div>

                        <!-- DÉTAILS ITINÉRAIRE (DATE EN 1ER / PICKUP / DROPOFF) -->
                        <div class="etb-bar-route-info">
                            <div class="etb-route-line etb-route-date" style="margin-bottom: 2px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--etb-accent-gold, #fbac18)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                <span>${formattedDateTime}</span>
                            </div>
                            ${routeHtml}
                        </div>

                        <div class="etb-bar-col2" style="display: flex; flex-direction: column; justify-content: center;">
                            <div class="etb-bar-price">${priceHtml}</div>
                            <div class="etb-bar-sub" style="color: var(--etb-text-secondary, #94a3b8); font-size: 11px; text-transform: uppercase;">
                                ${currentMode === 'hourly' ? 'Hourly Charter' : 'All Inclusive (Fixed rate)'}
                            </div>
                        </div>
                    `;
                }

                // 2. Gestion des boutons selon l'activation du Multi-Booking
                if (bridgeTripWrap && bridgeTripLabel) {
                    if (isMultiBookingEnabled) {
                        bridgeTripWrap.style.display = 'flex';
                        bridgeTripLabel.textContent = (cartTrips.length === 0) ? 'Add another trip' : 'Add this trip';
                    } else {
                        bridgeTripWrap.style.display = 'none';
                    }
                    
                    if (bookBtnLabel) {
                        if (!isMultiBookingEnabled || cartTrips.length === 0) {
                            // Mono-trajet pur : le bouton principal engage ce véhicule unique
                            bookBtnLabel.textContent = isCarQuote ? 'Request' : 'Book Trip';
                        } else {
                            // Panier rempli : le bouton principal ne concerne QUE le panier existant
                            const hasQuoteInCart = cartTrips.some(t => t.isQuote);
                            bookBtnLabel.textContent = hasQuoteInCart ? `Request (${cartTrips.length} Transfers)` : `Checkout (${cartTrips.length} Transfers)`;
                        }
                    }
                }

               // 3. Affichage et scroll fluide vers le bas
                if (bookingBar) {
                    bookingBar.classList.add('is-visible');
                    setTimeout(() => {
                        bookingBar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 180);
                }

                // Active les liens WhatsApp et Email pour cette voiture
                updateInquiryLinks();
            });
        });

        // 4bis. Clic sur le bouton doré flottant ("Add this trip" ou "Add another trip")
        if (bridgeTripBtn) {
            bridgeTripBtn.addEventListener('click', function(e) {
                e.preventDefault();

                // Fonction magique pour vider l'ardoise avant le prochain trajet
                const resetFieldsForNewTrip = () => {
                    const pInputEl = quickRoot.querySelector('#etb-quick-pickup');
                    const dInputEl = quickRoot.querySelector('#etb-quick-dropoff');
                    
                    if (pInputEl) pInputEl.value = '';
                    if (dInputEl) dInputEl.value = '';
                    if (pickupLatEl) pickupLatEl.value = '';
                    if (pickupLngEl) pickupLngEl.value = '';
                    if (dropoffLatEl) dropoffLatEl.value = '';
                    if (dropoffLngEl) dropoffLngEl.value = '';
                    if (timeInput) timeInput.value = ''; 
                    
                    if (pickupClearBtn) pickupClearBtn.classList.remove('is-visible');
                    if (dropoffClearBtn) dropoffClearBtn.classList.remove('is-visible');

                    // Masquer les véhicules pour forcer un nouveau calcul
                    carCards.forEach(c => {
                        c.style.display = 'none';
                        c.classList.remove('selected');
                    });
                    if (quoteNoticeEl) quoteNoticeEl.classList.remove('is-visible');
                    if (errorNotice) errorNotice.style.display = 'none';
                    
                    saveFormState();
                };

                // Si un véhicule est sélectionné : on l'enregistre d'abord dans le panier
                if (selectedCar) {
                    const pInputEl   = quickRoot.querySelector('#etb-quick-pickup');
                    const dInputEl   = quickRoot.querySelector('#etb-quick-dropoff');
                    const pickupVal  = pInputEl ? pInputEl.value.trim() : '';
                    const dropoffVal = (currentMode === 'transfer' && dInputEl) ? dInputEl.value.trim() : '';
                    const durVal     = (currentMode === 'hourly' && durationSelect) ? durationSelect.value : '4';
                    const dateVal    = dateInput ? dateInput.value.trim() : '';
                    const timeVal    = timeInput ? timeInput.value.trim() : '';

                    if (!pickupVal) return showError('Please enter a pickup location.');
                    if (currentMode === 'transfer' && !dropoffVal) return showError('Please enter a drop-off location.');
                    if (!dateVal) return showError('Please select a date.');
                    if (!timeVal || timeVal === '-- : --') return showError('Please select a pickup time.');

                    cartTrips.push({
                        id: 'trip_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                        mode: currentMode,
                        pickup: pickupVal,
                        dropoff: dropoffVal,
                        duration: durVal,
                        date: dateVal,
                        time: timeVal,
                        vehicle_id: selectedCar.id,
                        vehicle_name: selectedCar.name,
                        vehicle_img: selectedCar.img || '',
                        price: selectedCar.price,
                        isQuote: selectedCar.isQuote,
                        maxPax: selectedCar.maxPax || '1',
                        maxBag: selectedCar.maxBag || '0'
                    });

                    saveCartToStorage();

                    const wasAddingThisTrip = bridgeTripLabel && bridgeTripLabel.textContent.includes('this trip');

                    // Réinitialisation de la sélection
                    selectedCar = null;
                    carCards.forEach(c => c.classList.remove('selected'));
                    renderTripsCart();

                    // Si c'était "Add another trip" (1er trajet), on vide les champs et on remonte
                    if (!wasAddingThisTrip) {
                        resetFieldsForNewTrip();
                        const searchBar = quickRoot.querySelector('.etb-quick-glass-bar') || quickRoot;
                        searchBar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else if (cartTrips.length > 0) {
                    // Clic direct sur "Add another trip" depuis le panier
                    resetFieldsForNewTrip();
                    const searchBar = quickRoot.querySelector('.etb-quick-glass-bar') || quickRoot;
                    searchBar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
        // ── FORMATAGE DE DATE HAUTE CONCIERGERIE VIP ──
        const formatLuxuryDateText = (dateStr, timeStr) => {
            if (!dateStr) return '—';
            const parts = dateStr.split('-');
            if (parts.length !== 3) return dateStr + (timeStr ? ` • ${timeStr}` : '');
            
            const dateObj = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            const daysShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const monthsShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            const dayName   = daysShort[dateObj.getDay()] || '';
            const monthName = monthsShort[dateObj.getMonth()] || parts[1];
            const dayNum    = String(parts[2]).padStart(2, '0');
            
            const cleanTime = (timeStr && timeStr !== '-- : --') ? ` • ${timeStr}` : '';
            return `${dayName}, ${dayNum} ${monthName} ${parts[0]}${cleanTime}`;
        };

        // ── GESTION DU PANIER MULTI-TRAJETS (VIP CART ENGINE) ──
        const renderTripsCart = () => {
            if (!tripsCartEl || !tripsListEl) return;

            // 1. SI LE PANIER EST VIDE
            if (cartTrips.length === 0) {
                tripsCartEl.style.display = 'none';
                tripsListEl.innerHTML = '';
                if (tripsCountEl) tripsCountEl.textContent = '0';
                if (bookBtnLabel) bookBtnLabel.textContent = 'Book this Trip';
                
                // Masque la barre du bas et le bouton doré uniquement si aucune voiture n'est en cours de sélection
                if (!selectedCar) {
                    if (bookingBar) bookingBar.classList.remove('is-visible');
                    if (bridgeTripWrap) bridgeTripWrap.style.display = 'none';
                }
                return;
            }

                       // 2. SI LE PANIER CONTIENT DES TRAJETS
            // Dévoile immédiatement le conteneur parent et le panier (Planned Itineraries)
            if (fleetSection) fleetSection.style.display = 'block';
            tripsCartEl.style.display = 'block';

            // 🔒 UX Fix : la barre de confirmation n'apparaît QUE si :
            //    - une voiture est en cours de sélection (single-trip), OU
            //    - le mode multi-trajets est actif (actuellement masqué côté UI)
            // Cela empêche la barre fantôme de s'afficher au refresh / retour checkout,
            // quand un ancien panier est restauré depuis sessionStorage sans sélection active.
            if (bookingBar && (isMultiBookingEnabled || selectedCar)) {
                bookingBar.classList.add('is-visible');
            }

            if (tripsCountEl) tripsCountEl.textContent = cartTrips.length;

            let html = '';
            let cumulativeTotal = 0;
            let hasCustomQuoteInCart = false;

            cartTrips.forEach((trip, index) => {
                const isQuote = trip.isQuote;
                if (isQuote) {
                    hasCustomQuoteInCart = true;
                } else {
                    cumulativeTotal += parseFloat(trip.price) || 0;
                }

                const priceDisplay = isQuote 
                    ? '<span style="color: var(--etb-accent-gold, #fbac18); font-weight: 800; font-size: 13px;">Quote Upon Request</span>' 
                    : `<strong style="color: var(--etb-text-primary, #ffffff); font-size: 15px; font-weight: 800;">${parseFloat(trip.price).toFixed(0)} <span style="font-size: 12px; color: var(--etb-accent-gold, #fbac18);">${currency}</span></strong>`;

                const formattedDateTime = formatLuxuryDateText(trip.date, trip.time);

                // Ligne d'itinéraire responsive VIP (2 lignes empilées)
                const routeHtml = (trip.mode === 'hourly')
                    ? `<div style="display: flex; flex-direction: column; gap: 4px; margin-top: 3px;">
                         <div style="display: flex; align-items: center; gap: 6px; font-size: 11.5px; color: var(--etb-accent-gold, #fbac18);">
                           <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                           <strong>By the hour (${trip.duration} Hours Charter)</strong>
                         </div>
                         <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--etb-text-secondary, #cbd5e1); overflow: hidden;">
                           <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #22c55e; flex-shrink: 0;"></span>
                           <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">${trip.pickup}</span>
                         </div>
                       </div>`
                    : `<div style="display: flex; flex-direction: column; gap: 4px; margin-top: 3px; width: 100%; overflow: hidden;">
                         <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--etb-text-secondary, #cbd5e1); overflow: hidden;">
                           <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #22c55e; flex-shrink: 0;" title="Pickup"></span>
                           <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">${trip.pickup}</span>
                         </div>
                         <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--etb-text-secondary, #cbd5e1); overflow: hidden;">
                           <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--etb-accent-gold, #fbac18); flex-shrink: 0;" title="Drop-off"></span>
                           <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">${trip.dropoff}</span>
                         </div>
                       </div>`;

                html += `
                    <div class="etb-cart-trip-item" style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid var(--etb-border-light, rgba(255,255,255,0.08)); border-radius: 9px; padding: 12px 16px; gap: 14px; transition: all 0.2s ease;">
                        <div style="display: flex; flex-direction: column; gap: 3px; overflow: hidden; flex: 1;">
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; overflow: hidden; width: 100%;">
                                <span style="background: var(--etb-accent-gold, #fbac18); color: #0f172a; font-size: 10px; font-weight: 900; padding: 2px 7px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.05em; flex-shrink: 0;">Leg #${index + 1}</span>
                                <strong style="color: var(--etb-text-primary, #ffffff); font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-shrink: 1;">${trip.vehicle_name}</strong>
                            </div>
                            <div style="display: flex; align-items: center; gap: 7px; font-size: 11.5px; color: var(--etb-text-secondary, #94a3b8); flex-wrap: wrap;">
                                <span style="color: var(--etb-accent-gold, #fbac18); font-weight: 700;">${trip.maxPax} Pax • ${trip.maxBag} Bags</span>
                                <span>•</span>
                                <span>${formattedDateTime}</span>
                            </div>
                            ${routeHtml}
                        </div>
                        <div style="display: flex; align-items: center; gap: 14px; flex-shrink: 0;">
                            <div>${priceDisplay}</div>
                            <button type="button" class="etb-cart-remove-trip-btn" data-index="${index}" title="Remove this transfer" style="background: rgba(239, 68, 68, 0.12) !important; border: 1px solid rgba(239, 68, 68, 0.4) !important; width: 30px !important; height: 30px !important; border-radius: 8px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; padding: 0 !important; transition: all 0.2s ease !important;">
                                <svg width="11" height="11" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: block; width: 11px; height: 11px; flex-shrink: 0;">
                                    <path d="M1.5 1.5L10.5 10.5M10.5 1.5L1.5 10.5" stroke="#f87171" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
            });

            tripsListEl.innerHTML = html;

            // Écouteurs de suppression
            tripsListEl.querySelectorAll('.etb-cart-remove-trip-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const idx = parseInt(this.dataset.index, 10);
                    if (!isNaN(idx) && idx >= 0 && idx < cartTrips.length) {
                        cartTrips.splice(idx, 1);
                        saveCartToStorage();
                        renderTripsCart();
                    }
                });
            });

            // Libellé intelligent du bouton d'action principal et déverrouillage systématique
            if (bookNowBtn) {
                bookNowBtn.disabled = false;
                const currentLabelEl = bookNowBtn.querySelector('#etb-quick-book-btn-label') || bookBtnLabel;

                if (currentLabelEl) {
                    if (cartTrips.length === 0) {
                        currentLabelEl.textContent = selectedCar ? (selectedCar.isQuote ? 'Request this Quote' : 'Book this Trip') : 'Book this Trip';
                    } else {
                        currentLabelEl.textContent = hasCustomQuoteInCart ? `Request Quote (${cartTrips.length} Transfers)` : `Checkout (${cartTrips.length} Transfers)`;
                    }
                }
            }

            const dynamicContentEl = quickRoot.querySelector('#etb-quick-dynamic-bar-content');
            if (dynamicContentEl && !selectedCar) {
                // Le bouton doré flottant au-dessus reste affiché SI multi-booking est actif
                if (bridgeTripWrap && bridgeTripLabel) {
                    if (isMultiBookingEnabled) {
                        bridgeTripWrap.style.display = 'flex';
                        bridgeTripLabel.textContent = 'Add another trip';
                    } else {
                        bridgeTripWrap.style.display = 'none';
                    }
                }

                dynamicContentEl.innerHTML = `
                    <div class="etb-bar-col1" style="display: flex; flex-direction: column; justify-content: center; flex-shrink: 0;">
                        <div class="etb-bar-v-title" style="white-space: nowrap;">
                            <strong style="color: var(--etb-text-primary, #ffffff); font-size: 15px; letter-spacing: 0.02em;">
                                ${cartTrips.length} Scheduled Transfers
                            </strong>
                        </div>
                    </div>
                    <div class="etb-bar-col2" style="display: flex; flex-direction: column; justify-content: center;">
                        <div class="etb-bar-price">
                            ${hasCustomQuoteInCart 
                                ? '<span style="color: var(--etb-accent-gold, #fbac18); font-size: 22px; font-weight: 800;">Custom Quote</span>' 
                                : `<span style="color: var(--etb-accent-gold, #fbac18); font-size: 28px; font-weight: 900; letter-spacing: -0.5px;">${cumulativeTotal.toFixed(0)} <span style="font-size: 18px;">${currency}</span></span>`}
                        </div>
                        <div class="etb-bar-sub" style="color: var(--etb-text-secondary, #94a3b8); font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">
                            Fixed All-Inclusive Rate
                        </div>
                    </div>
                `;
            } else if (dynamicContentEl && selectedCar) {
                const priceBox = dynamicContentEl.querySelector('.etb-bar-price');
                const subBox   = dynamicContentEl.querySelector('.etb-bar-sub');
                const kmBox    = dynamicContentEl.querySelector('.etb-bar-km');

                if (priceBox) {
                    priceBox.innerHTML = hasCustomQuoteInCart 
                        ? '<span style="color: var(--etb-accent-gold, #fbac18); font-size: 22px; font-weight: 800;">Custom Quote</span>' 
                        : `<span style="color: var(--etb-accent-gold, #fbac18); font-size: 28px; font-weight: 900; letter-spacing: -0.5px;">${cumulativeTotal.toFixed(0)} <span style="font-size: 18px;">${currency}</span></span>`;
                }
                if (subBox) {
                    subBox.innerHTML = `<span style="color: var(--etb-text-secondary, #94a3b8); font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">Fixed All-Inclusive Rate</span>`;
                }
                if (kmBox) kmBox.style.display = 'none';
            }

            // Met à jour les liens WhatsApp et Email pour le récapitulatif du panier
            updateInquiryLinks();
        };

       // 4bis. Clic sur "Add this Trip" / "Add another trip"
        if (addTripBtn) {
            addTripBtn.style.setProperty('background', 'rgba(251, 172, 24, 0.1)', 'important');
            addTripBtn.style.setProperty('border', '1.5px solid #fbac18', 'important');
            addTripBtn.style.setProperty('color', '#fbac18', 'important');

            addTripBtn.addEventListener('click', function(e) {
                e.preventDefault();

                // Si aucun nouveau véhicule n'est sélectionné et que le panier a déjà des trajets :
                // Le client veut enchaîner sur un autre trajet -> Auto-scroll fluide vers la barre du haut !
                if (!selectedCar && cartTrips.length > 0) {
                    const searchBar = quickRoot.querySelector('.etb-quick-glass-bar') || quickRoot;
                    searchBar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                if (!selectedCar) {
                    showError('Please select a vehicle above before adding this trip.');
                    return;
                }

                const pInputEl = quickRoot.querySelector('#etb-quick-pickup');
                const dInputEl = quickRoot.querySelector('#etb-quick-dropoff');
                const pickupVal  = pInputEl ? pInputEl.value.trim() : '';
                const dropoffVal = (currentMode === 'transfer' && dInputEl) ? dInputEl.value.trim() : '';
                const durVal     = (currentMode === 'hourly' && durationSelect) ? durationSelect.value : '4';
                const dateVal    = dateInput ? dateInput.value.trim() : '';
                const timeVal    = timeInput ? timeInput.value.trim() : '';

                if (!pickupVal) return showError('Please enter a pickup location.');
                if (currentMode === 'transfer' && !dropoffVal) return showError('Please enter a drop-off location.');
                if (!dateVal) return showError('Please select a date.');
                if (!timeVal || timeVal === '-- : --') return showError('Please select a pickup time.');

                const newTrip = {
                    id: 'trip_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                    mode: currentMode,
                    pickup: pickupVal,
                    dropoff: dropoffVal,
                    duration: durVal,
                    date: dateVal,
                    time: timeVal,
                    vehicle_id: selectedCar.id,
                    vehicle_name: selectedCar.name,
                    vehicle_img: selectedCar.img || '',
                    price: selectedCar.price,
                    isQuote: selectedCar.isQuote,
                    maxPax: selectedCar.maxPax || '1',
                    maxBag: selectedCar.maxBag || '0'
                };

                cartTrips.push(newTrip);
                saveCartToStorage();

                // 1. Désélectionne la carte véhicule dans la grille au-dessus
                carCards.forEach(c => {
                    c.classList.remove('selected');
                    const b = c.querySelector('.etb-quick-select-btn');
                    if (b) b.textContent = (c.dataset.isQuote === '1' || isQuoteMode) ? 'Request Quote' : 'Select';
                });

                // 2. Remet selectedCar à null AVANT d'actualiser le panier
                selectedCar = null;

                // 3. Bascule instantanément la barre en mode Récapitulatif officiel
                renderTripsCart();

                // 4. Défilement fluide automatique vers le haut pour configurer le trajet suivant
                setTimeout(() => {
                    const searchBar = quickRoot.querySelector('.etb-quick-glass-bar') || quickRoot;
                    searchBar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        }
        

        // 5. Clic sur "Book this Trip" : Génération du Quote Token sécurisé et redirection
        if (bookNowBtn) {
            bookNowBtn.addEventListener('click', function (e) {
                e.preventDefault();

                // Si aucun trajet n'est au panier et aucune voiture n'est sélectionnée : erreur
                if (cartTrips.length === 0 && !selectedCar) {
                    showError('Please select a vehicle or add at least one trip to your booking.');
                    return;
                }

                                // Ajout automatique du véhicule sélectionné — Modification #13.
                //
                // AVANT : seul le cas "panier vide" était géré. Si le panier contenait
                // déjà des trajets et que l'utilisateur cliquait directement sur
                // "Checkout", le véhicule en cours de sélection était perdu silencieusement.
                //
                // MAINTENANT : tout véhicule sélectionné est systématiquement ajouté au
                // panier avant la soumission, quel que soit l'état du panier. L'utilisateur
                // retrouve donc l'intégralité de ses trajets sur la page de checkout, et
                // peut en supprimer un individuellement s'il le souhaite (bouton ×).
                if (selectedCar) {
                    const pInputEl   = quickRoot.querySelector('#etb-quick-pickup');
                    const dInputEl   = quickRoot.querySelector('#etb-quick-dropoff');
                    const pickupVal  = pInputEl ? pInputEl.value.trim() : '';
                    const dropoffVal = (currentMode === 'transfer' && dInputEl) ? dInputEl.value.trim() : '';
                    const durVal     = (currentMode === 'hourly' && durationSelect) ? durationSelect.value : '4';
                    const dateVal    = dateInput ? dateInput.value.trim() : '';
                    const timeVal    = timeInput ? timeInput.value.trim() : '';

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

                    cartTrips.push({
                        id: 'trip_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                        mode: currentMode,
                        pickup: pickupVal,
                        dropoff: dropoffVal,
                        duration: durVal,
                        date: dateVal,
                        time: timeVal,
                        vehicle_id: selectedCar.id,
                        vehicle_name: selectedCar.name,
                        vehicle_img: selectedCar.img || '',
                        price: selectedCar.price,
                        isQuote: selectedCar.isQuote,
                        maxPax: selectedCar.maxPax || '1',
                        maxBag: selectedCar.maxBag || '0'
                    });

                    // Persiste le panier mis à jour avant l'envoi AJAX
                    saveCartToStorage();
                
                }

                // État de chargement discret sur le bouton (Sans détruire les balises internes)
                bookNowBtn.disabled = true;
                const activeLabelEl = bookNowBtn.querySelector('#etb-quick-book-btn-label');
                if (activeLabelEl) {
                    activeLabelEl.textContent = 'Checkout...';
                }

                // Préparation de la requête AJAX vers notre générateur de devis
                const formData = new FormData();
                formData.append('action', 'etb_create_quote');
                formData.append('nonce', etbAjax.nonce);

                // Envoi de l'ensemble des trajets du panier en JSON
                formData.append('trips', JSON.stringify(cartTrips));

                // Données de secours du premier trajet pour rétrocompatibilité
                const firstTrip = cartTrips[0];
                formData.append('mode', firstTrip.mode);
                formData.append('pickup', firstTrip.pickup);
                formData.append('dropoff', firstTrip.dropoff);
                formData.append('duration', firstTrip.duration);
                formData.append('date', firstTrip.date);
                formData.append('time', firstTrip.time);
                formData.append('vehicle_id', firstTrip.vehicle_id);
                formData.append('price', firstTrip.price);
                fetch(etbAjax.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(function (response) {
                    return response.json();
                })
                .then(function (res) {
                                       if (res.success && res.data.redirect_url) {
                        // 🧹 Multi-Trip DÉSACTIVÉ : on vide le panier avant la redirection
                        // pour que le BFCache (retour arrière) ne restaure rien du tout.
                        cartTrips = [];
                        saveCartToStorage();

                        // Redirection immédiate vers le Checkout
                        window.location.href = res.data.redirect_url;
                    } else {
                        bookNowBtn.disabled = false;
                        if (activeLabelEl) renderTripsCart();
                        showError(res.data.message || 'Could not initialize quote. Please try again.');
                    }
                })
                .catch(function (err) {
                    console.error('Quote generation error:', err);
                    bookNowBtn.disabled = false;
                    if (activeLabelEl) renderTripsCart();
                    showError('Communication error. Please try again.');
                });
            });
        }
         

         // ── RESTAURATION AUTOMATIQUE DE LA SESSION (RETOUR DU CHECKOUT OU ACTUALISATION) ──
        const restoreQuickSession = () => {

                // 0. Restauration de l'état du switch Multi-Booking
            try {
                const storedMb = sessionStorage.getItem('etb_multi_booking_enabled');
                if (storedMb === '1') {
                    isMultiBookingEnabled = true;
                    if (multiTripToggle) multiTripToggle.checked = true;
                } else {
                    isMultiBookingEnabled = false;
                    if (multiTripToggle) multiTripToggle.checked = false;
                }
            } catch (e) {}

            // 1. Restauration des champs du formulaire
            try {
                const savedState = sessionStorage.getItem('etb_quick_form_state');
                if (savedState) {
                    const st = JSON.parse(savedState);
                    if (st) {
                        if (st.pickup && pickupInput) {
                            pickupInput.value = st.pickup;
                            if (pickupClearBtn) pickupClearBtn.classList.add('is-visible');
                        }
                        if (st.pickupLat && pickupLatEl) pickupLatEl.value = st.pickupLat;
                        if (st.pickupLng && pickupLngEl) pickupLngEl.value = st.pickupLng;
                        if (st.dropoff && dropoffInput) {
                            dropoffInput.value = st.dropoff;
                            if (dropoffClearBtn) dropoffClearBtn.classList.add('is-visible');
                        }
                        if (st.dropoffLat && dropoffLatEl) dropoffLatEl.value = st.dropoffLat;
                        if (st.dropoffLng && dropoffLngEl) dropoffLngEl.value = st.dropoffLng;
                        if (st.date && dateInput) {
                            dateInput.value = st.date;
                            if (dateTextEl && typeof formatCustomDate === 'function') {
                                dateTextEl.textContent = formatCustomDate(st.date);
                            }
                        }
                        if (st.time && timeInput) {
                            timeInput.value = st.time;
                        }
                        if (st.duration && durationSelect) {
                            durationSelect.value = st.duration;
                        }
                        if (st.mode && st.mode !== currentMode) {
                            const targetModeBtn = quickRoot.querySelector(`.etb-quick-mode-btn[data-mode="${st.mode}"]`);
                            if (targetModeBtn) targetModeBtn.click();
                        }
                    }
                }
            } catch (e) {}

                       // 2. Restauration du panier — ⏸️ DÉSACTIVÉ (Multi-Trip OFF)
            //    Le Multi-Trip est temporairement désactivé côté UI (toggle masqué).
            //    Pour réactiver la fonctionnalité, décommenter simplement ce bloc.
            //
            // try {
            //     const storedCart = sessionStorage.getItem('etb_cart_trips');
            //     if (storedCart) {
            //         const parsedCart = JSON.parse(storedCart);
            //         if (Array.isArray(parsedCart) && parsedCart.length > 0) {
            //             cartTrips = parsedCart;
            //             renderTripsCart();
            //         }
            //     }
            // } catch (e) {}

            // Nettoyage préventif : purge toute donnée multi-trip résiduelle du sessionStorage
            // pour éviter l'affichage fantôme du panier au retour du checkout.
            try {
                sessionStorage.removeItem('etb_cart_trips');
                sessionStorage.removeItem('etb_multi_booking_enabled');
            } catch (e) {}

           // 3. RÉVEIL AUTOMATIQUE DES PRIX ET DE LA FLOTTE
            // (Simule intelligemment le clic sur "Show Prices" si les champs sont prêts)
            setTimeout(() => {
                const hasPickup = pickupInput && pickupInput.value.trim() !== '';
                const hasDate   = dateInput && dateInput.value.trim() !== '';
                const hasTime   = timeInput && timeInput.value.trim() !== '-- : --' && timeInput.value.trim() !== '';

                if (hasPickup && hasDate && hasTime) {
                    if (currentMode === 'hourly') {
                        if (getPriceBtn) getPriceBtn.click();
                    } else if (currentMode === 'transfer') {
                        const fromLat    = pickupLatEl ? pickupLatEl.value : '';
                        const toLat      = dropoffLatEl ? dropoffLatEl.value : '';
                        const hasDropoff = dropoffInput && dropoffInput.value.trim() !== '';
                        
                        // Si les coordonnées GPS du point A et du point B sont bien restaurées
                        if (fromLat && toLat && hasDropoff) {
                            if (getPriceBtn) getPriceBtn.click();
                        }
                    }
                }
            }, 100);
        };

        // Sauvegarde de l'état du formulaire à chaque saisie
        if (pickupInput)  pickupInput.addEventListener('change', saveFormState);
        if (dropoffInput) dropoffInput.addEventListener('change', saveFormState);
        if (dateInput)    dateInput.addEventListener('change', saveFormState);
        if (timeInput)    timeInput.addEventListener('change', saveFormState);

        // Lancement immédiat de la restauration
        restoreQuickSession();

                // Réveil forcé en cas de retour via le cache du navigateur (Bfcache / History Back)
                window.addEventListener('pageshow', function () {
            if (bookNowBtn) {
                bookNowBtn.disabled = false;

                // ♻️ Réinitialisation du libellé (évite qu'il reste bloqué
                //    sur "Securing quote..." au retour du checkout via BFCache).
                //    On recalcule le libellé selon l'état de sélection réel.
                const labelEl = bookNowBtn.querySelector('#etb-quick-book-btn-label');
                if (labelEl) {
                    labelEl.textContent = (selectedCar && selectedCar.isQuote)
                        ? 'Request'
                        : 'Book Trip';
                }
            }

            // ⏸️ Multi-Trip DÉSACTIVÉ : plus d'affichage automatique du panier au retour.
            //    Pour réactiver, décommenter simplement le bloc ci-dessous.
            //
            // if (cartTrips.length > 0) {
            //     renderTripsCart();
            // }

            // Nettoyage préventif : purge tout panier résiduel (y compris en mémoire BFCache)
            cartTrips = [];
        });
    };

    
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
        // Note (#17 revert) : ces 3 variables ciblent des sélecteurs qui n'existent
        // pas dans le template (Stripe Elements est utilisé). Les querySelector
        // retournent null → les blocs `if (cardNumInput)` plus bas sont inertes.
        // MAIS leur suppression totale (déclaration + tous les if) est trop risquée
        // pour un gain cosmétique → on les laisse en place.
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

            const isMultiTripEl = checkoutRoot.querySelector('#etb-chk-is-multi-trip');
            const isMultiTrip   = isMultiTripEl && isMultiTripEl.value === '1';

            // Affichage de l'itinéraire dans le récapitulatif (UNIQUEMENT si mono-trajet)
            if (!isMultiTrip) {
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

            // Détection dynamique de l'urgence (< 24h) sur TOUS les trajets du panier
            const checkIsUrgentCheckout = () => {
                const evaluateHoursDiff = (dateStr, timeStr) => {
                    if (!dateStr || !timeStr || timeStr === '-- : --') return 999;
                    let h = 9, m = 0;
                    const cleanTime = timeStr.toUpperCase();
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
                    if (dParts.length !== 3) return 999;

                    const pickupDate = new Date(parseInt(dParts[0], 10), parseInt(dParts[1], 10) - 1, parseInt(dParts[2], 10), h, m, 0);
                    const now = new Date();
                    return (pickupDate.getTime() - now.getTime()) / (1000 * 60 * 60);
                };

                // 1. Analyse de chaque trajet du panier mémorisé en session
                try {
                    const storedCart = sessionStorage.getItem('etb_cart_trips');
                    if (storedCart) {
                        const trips = JSON.parse(storedCart);
                        if (Array.isArray(trips) && trips.length > 0) {
                            for (let i = 0; i < trips.length; i++) {
                                const diffH = evaluateHoursDiff(trips[i].date, trips[i].time);
                                if (diffH >= 0 && diffH < 24) {
                                    return true; // AU MOINS UN trajet a lieu dans moins de 24h -> Urgent !
                                }
                            }
                        }
                    }
                } catch (e) {}

                // 2. Repli classique si mono-trajet
                const rawDate = dateInput ? dateInput.value.trim() : '';
                const rawTime = timeInput ? timeInput.value.trim() : '';
                const singleDiff = evaluateHoursDiff(rawDate, rawTime);
                return singleDiff >= 0 && singleDiff < 24;
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
                const isMultiTripOrder = checkoutRoot.querySelector('#etb-chk-is-multi-trip')?.value === '1';

                // Si Multi-Trajets : on ne touche à rien ici ! Les onglets (switchLegTab) gèrent l'affichage.
                // Si Mono-Trajet : on applique la logique classique.
                if (!isMultiTripOrder) {
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
                        if (airportTitle) airportTitle.textContent = 'Airport Arrival';
                        if (airportDesc)  airportDesc.textContent  = 'Real-time flight tracking included.';
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

        // ── GESTION DES ONGLETS DE TRAJETS ET CAPACITÉS INDÉPENDANTES (VIP PER-LEG) ──
        const legTabBtns     = checkoutRoot.querySelectorAll('.etb-chk-leg-tab-btn');
        const legInfoVehicle = checkoutRoot.querySelector('#etb-chk-leg-info-vehicle');
        const legInfoRoute   = checkoutRoot.querySelector('#etb-chk-leg-info-route');
        const legInfoDate    = checkoutRoot.querySelector('#etb-chk-leg-info-date');
        const legsDataInput  = checkoutRoot.querySelector('#etb-chk-legs-data');

        // Sécurité : Déclaration des éléments de l'Aéroport pour éviter les crashs (ReferenceError)
        const flightInputEl  = checkoutRoot.querySelector('#etb-flight-number');
        const signInputEl    = checkoutRoot.querySelector('#etb-pickup-sign');
        const airportTitle   = checkoutRoot.querySelector('#etb-chk-airport-title');
        const airportDesc    = checkoutRoot.querySelector('#etb-chk-airport-desc');
        const flightLabel    = checkoutRoot.querySelector('#etb-chk-flight-label');
        const flightHint     = checkoutRoot.querySelector('#etb-chk-flight-hint');
        const signField      = checkoutRoot.querySelector('#etb-chk-sign-field');

        let currentActiveLeg = 0;
        let perLegState      = [];

        // Initialisation de l'état pour chaque onglet
        if (legTabBtns.length > 0) {
            legTabBtns.forEach((btn, idx) => {
                const maxPax = parseInt(btn.dataset.maxPax, 10) || 3;
                const maxBag = parseInt(btn.dataset.maxBag, 10) || 2;
                perLegState.push({
                    pax: 1,
                    checked: Math.min(1, maxBag),
                    cabin: 0,
                    maxPax: maxPax,
                    maxBag: maxBag
                });
            });

            const syncLegsDataInput = () => {
                if (legsDataInput) {
                    legsDataInput.value = JSON.stringify(perLegState);
                }
            };

            const switchLegTab = (targetIdx) => {
                if (targetIdx < 0 || targetIdx >= perLegState.length) return;

                // 1. Sauvegarde des valeurs du trajet qui était ouvert (Capacités + Vol + Pancarte)
                if (paxInputEl)      perLegState[currentActiveLeg].pax          = parseInt(paxInputEl.value, 10) || 1;
                if (bagInputEl)      perLegState[currentActiveLeg].checked      = parseInt(bagInputEl.value, 10) || 0;
                if (cabinBagInputEl) perLegState[currentActiveLeg].cabin        = parseInt(cabinBagInputEl.value, 10) || 0;
                if (flightInputEl)   perLegState[currentActiveLeg].flightNumber = flightInputEl.value;
                if (signInputEl)     perLegState[currentActiveLeg].greetingSign = signInputEl.value;

                currentActiveLeg = targetIdx;
                const targetBtn  = legTabBtns[targetIdx];
                const legData    = perLegState[targetIdx];

                // 2. Mise à jour visuelle des pilules d'onglets
                legTabBtns.forEach((b, i) => {
                    const isActive = (i === targetIdx);
                    b.classList.toggle('active', isActive);
                    b.style.border     = isActive ? '1.5px solid var(--etb-accent-gold, #fbac18)' : '1px solid var(--etb-border-light, rgba(255,255,255,0.12))';
                    b.style.background = isActive ? 'rgba(251, 172, 24, 0.15)' : 'rgba(255,255,255,0.03)';
                    b.style.color      = isActive ? 'var(--etb-accent-gold, #fbac18)' : 'var(--etb-text-secondary, #94a3b8)';
                });

                // 3. Mise à jour du bandeau contextuel (Véhicule, 2 lignes d'adresses, Date)
                if (legInfoVehicle && targetBtn.dataset.vehicle) legInfoVehicle.textContent = targetBtn.dataset.vehicle;

                if (legInfoRoute) {
                    const p   = targetBtn.dataset.pickup || '';
                    const d   = targetBtn.dataset.dropoff || '';
                    const m   = targetBtn.dataset.mode || 'transfer';
                    const dur = targetBtn.dataset.duration || '4';

                    if (m === 'hourly') {
                        legInfoRoute.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 11.5px; color: var(--etb-accent-gold, #fbac18);">
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                <strong>By the hour (${dur} Hours Charter)</strong>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--etb-text-secondary, #64748b); overflow: hidden;">
                                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #22c55e; flex-shrink: 0;" title="Pickup"></span>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${p}</span>
                            </div>
                        `;
                    } else {
                        legInfoRoute.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--etb-text-secondary, #64748b); overflow: hidden;">
                                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #22c55e; flex-shrink: 0;" title="Pickup"></span>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${p}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--etb-text-secondary, #64748b); overflow: hidden;">
                                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--etb-accent-gold, #fbac18); flex-shrink: 0;" title="Drop-off"></span>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${d}</span>
                            </div>
                        `;
                    }
                }

                if (legInfoDate && targetBtn.dataset.date) {
                    legInfoDate.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> <span>${targetBtn.dataset.date}</span>`;
                }

                // 4. Adaptation dynamique des capacités MAX du véhicule actif
                if (paxHint) paxHint.textContent = 'Max: ' + legData.maxPax;
                if (paxInputEl) {
                    paxInputEl.dataset.max = legData.maxPax;
                    paxInputEl.value       = Math.min(legData.pax, legData.maxPax);
                }

                if (bagInputEl)      bagInputEl.value      = legData.checked;
                if (cabinBagInputEl) cabinBagInputEl.value = legData.cabin;
                if (flightInputEl)   flightInputEl.value   = legData.flightNumber || '';
                if (signInputEl)     signInputEl.value     = legData.greetingSign || '';

                // 5. Bascule dynamique et intelligente de la boîte Aéroport pour CE trajet
                if (airportCard) {
                    const checkApt = (str) => {
                        if (!str) return false;
                        return /\b(airport|aeroport|aéroport|terminal|aeropuerto|flughafen|nce|cdg|ory|heathrow|gatwick|jfk)\b/i.test(str);
                    };

                    const pickupStr  = targetBtn.dataset.pickup || '';
                    const dropoffStr = targetBtn.dataset.dropoff || '';
                    const routeStr   = targetBtn.dataset.route || '';

                    // Détection arrivée ou départ aéroport
                    const isPickupApt  = (targetBtn.dataset.airportPickup === '1') || checkApt(pickupStr) || (checkApt(routeStr) && !checkApt(dropoffStr));
                    const isDropoffApt = (targetBtn.dataset.airportDropoff === '1') || checkApt(dropoffStr);

                    const airportTitle = checkoutRoot.querySelector('#etb-chk-airport-title');
                    const airportDesc  = checkoutRoot.querySelector('#etb-chk-airport-desc');
                    const flightLabel  = checkoutRoot.querySelector('#etb-chk-flight-label');
                    const flightHint   = checkoutRoot.querySelector('#etb-chk-flight-hint');
                    const signField    = checkoutRoot.querySelector('#etb-chk-sign-field');

                    if (isPickupApt) {
                        airportCard.style.display = 'block';
                        if (airportTitle) airportTitle.textContent = `Airport Arrival (Trip #${targetIdx + 1})`;
                        if (airportDesc)  airportDesc.textContent  = 'Real-time flight tracking included.';
                        if (flightLabel)  flightLabel.textContent  = 'Flight Number';
                        if (flightHint)   flightHint.textContent   = '1 hour free waiting time after landing.';
                        if (signField)    signField.style.display  = 'block';

                        // Remplissage automatique de la pancarte si encore vide
                        if (!legData.greetingSign && typeof updateGreetingSign === 'function') {
                            updateGreetingSign();
                        }
                    } else if (isDropoffApt) {
                        airportCard.style.display = 'block';
                        if (airportTitle) airportTitle.textContent = `Airport Departure Terminal (Trip #${targetIdx + 1})`;
                        if (airportDesc)  airportDesc.textContent  = 'Helps your chauffeur drop you off directly at the correct terminal gate.';
                        if (flightLabel)  flightLabel.textContent  = 'Flight Number or Departure Terminal (Optional)';
                        if (flightHint)   flightHint.textContent   = 'e.g. Terminal 2E, AF 7704';
                        if (signField)    signField.style.display  = 'none';
                    } else {
                        // Trajet 100% ville : on masque complètement la boîte pour ce trajet
                        airportCard.style.display = 'none';
                    }
                }

                syncLegsDataInput();
            };

            // Écouteur de clic sur chaque onglet
            legTabBtns.forEach((btn, idx) => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    switchLegTab(idx);
                });
            });

            // Initialisation avec le premier onglet actif
            switchLegTab(0);

            // Synchronisation continue dès que l'utilisateur clique sur +/-
            [paxMinusBtn, paxPlusBtn, bagMinusBtn, bagPlusBtn, cabinBagMinusBtn, cabinBagPlusBtn].forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', () => {
                        if (paxInputEl)      perLegState[currentActiveLeg].pax     = parseInt(paxInputEl.value, 10) || 1;
                        if (bagInputEl)      perLegState[currentActiveLeg].checked = parseInt(bagInputEl.value, 10) || 0;
                        if (cabinBagInputEl) perLegState[currentActiveLeg].cabin   = parseInt(cabinBagInputEl.value, 10) || 0;
                        syncLegsDataInput();
                    });
                }
            });

            // Synchronisation en TEMPS RÉEL du vol et de la pancarte
            if (flightInputEl) {
                flightInputEl.addEventListener('input', () => {
                    perLegState[currentActiveLeg].flightNumber = flightInputEl.value;
                    syncLegsDataInput();
                });
            }
            if (signInputEl) {
                signInputEl.addEventListener('input', () => {
                    perLegState[currentActiveLeg].greetingSign = signInputEl.value;
                    syncLegsDataInput();
                });
            }

            // Mémorise la fonction pour forcer la synchronisation au moment du clic final
            window.etbForceSyncActiveLeg = () => {
                if (paxInputEl)      perLegState[currentActiveLeg].pax     = parseInt(paxInputEl.value, 10) || 1;
                if (flightInputEl)   perLegState[currentActiveLeg].flightNumber = flightInputEl.value;
                if (signInputEl)     perLegState[currentActiveLeg].greetingSign = signInputEl.value;
                syncLegsDataInput();
            };
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
            // Force la synchronisation des données de l'onglet actif juste avant l'envoi
            if (typeof window.etbForceSyncActiveLeg === 'function') window.etbForceSyncActiveLeg();

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
                        const rawRef = String(res.data.booking_ref || res.data.booking_id || '').replace(/^#+/, '');
                        const finalDisplayRef = rawRef ? ('#' + rawRef) : ('#' + res.data.booking_id);
                        let successTitle    = 'Reservation Registered (Payment Pending)';
                        let successSubtitle = 'Your reservation <strong>' + finalDisplayRef + '</strong> has been registered.';
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

                // NOTE (Modification #6) : le bloc de "Payment Request" historique a été
        // supprimé car :
        //   - il utilisait une variable non déclarée `paymentRequest` (ReferenceError en strict mode),
        //   - il ciblait des sélecteurs inexistants (#etb-wallet-payment-wrapper, #etb-payment-request-button),
        //   - il était redondant avec le moteur officiel Apple/Google Pay situé plus bas
        //     (fonction `walletPaymentRequest.canMakePayment()` — voir plus bas dans le fichier).
        // Le vrai moteur Wallet reste intact et fonctionnel.

        // Style adaptatif Dark / Light

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
                intentData.append('booking_id', bookingIdVal);
                intentData.append('tip_percentage', payRoot.querySelector('#etb-pay-tip-percent')?.value || '0');
                intentData.append('tip_amount', payRoot.querySelector('#etb-pay-tip-amount')?.value || '0');
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
                    intentData.append('booking_id', bookingIdVal);
                    intentData.append('tip_percentage', payRoot.querySelector('#etb-pay-tip-percent')?.value || '0');
                    intentData.append('tip_amount', payRoot.querySelector('#etb-pay-tip-amount')?.value || '0');

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

    // ==========================================================================
    // MODULE LIGHTBOX : VISIONNEUSE PHOTO VIP (BENTO GALLERY)
    // ==========================================================================
    var lightbox = document.getElementById('etb-vip-lightbox');
    if (lightbox) {
        var lbImg     = lightbox.querySelector('.etb-lightbox-img');
        var lbPrev    = lightbox.querySelector('.etb-lightbox-prev');
        var lbNext    = lightbox.querySelector('.etb-lightbox-next');
        var lbClose   = lightbox.querySelector('.etb-lightbox-close');
        var lbCurrent = document.getElementById('etb-lb-current');
        var lbTotal   = document.getElementById('etb-lb-total');
        
        var galleryImages = [];
        var currentIndex = 0;

        var galleryContainer = document.querySelector('.co-vip-bento-gallery');
        if (galleryContainer) {
            var rawGallery = galleryContainer.getAttribute('data-gallery');
            if (rawGallery) {
                try { 
                    galleryImages = JSON.parse(rawGallery); 
                } catch (e) {
                    galleryImages = [];
                }
            }

            if (galleryImages.length > 0) {
                // Liaison directe sur les conteneurs Bento
                var bentoItems = galleryContainer.querySelectorAll('.co-bento-item');
                bentoItems.forEach(function(item, fallbackIdx) {
                    item.style.cursor = 'pointer';
                    item.addEventListener('click', function(e) {
                        var idxAttr = item.getAttribute('data-index');
                        var idx = (idxAttr !== null) ? parseInt(idxAttr, 10) : fallbackIdx;
                        openLightbox(isNaN(idx) ? 0 : idx);
                    });
                });
                
                // Liaison sur le badge "Gallery"
                var galleryBadge = galleryContainer.querySelector('.co-bento-badge');
                if (galleryBadge) {
                    galleryBadge.style.cursor = 'pointer';
                    galleryBadge.addEventListener('click', function(e) {
                        e.stopPropagation();
                        var badgeIdxAttr = galleryBadge.getAttribute('data-index');
                        var targetIdx = (badgeIdxAttr !== null) ? parseInt(badgeIdxAttr, 10) : (galleryImages.length > 3 ? 3 : 0);
                        openLightbox(isNaN(targetIdx) ? 0 : targetIdx);
                    });
                }
            }
        }

        function openLightbox(index) {
            if (!galleryImages || galleryImages.length === 0) return;
            if (index < 0) index = 0;
            if (index >= galleryImages.length) index = galleryImages.length - 1;
            currentIndex = index;
            updateLightboxContent();
            lightbox.classList.add('etb-lightbox-active');
            document.body.style.overflow = 'hidden'; 
        }

        function closeLightbox() {
            lightbox.classList.remove('etb-lightbox-active');
            document.body.style.overflow = '';
        }

        function updateLightboxContent() {
            if (!galleryImages[currentIndex]) return;
            lbImg.style.opacity = '0';
            setTimeout(function() {
                lbImg.src = galleryImages[currentIndex];
                lbImg.style.opacity = '1';
            }, 120);
            
            if (lbCurrent) lbCurrent.textContent = currentIndex + 1;
            if (lbTotal) lbTotal.textContent = galleryImages.length;
            
            if (lbPrev) lbPrev.style.display = (currentIndex === 0) ? 'none' : 'flex';
            if (lbNext) lbNext.style.display = (currentIndex === galleryImages.length - 1) ? 'none' : 'flex';
        }

        if (lbClose) lbClose.addEventListener('click', closeLightbox);
        if (lbNext) lbNext.addEventListener('click', function() { 
            if (currentIndex < galleryImages.length - 1) { 
                currentIndex++; 
                updateLightboxContent(); 
            } 
        });
        if (lbPrev) lbPrev.addEventListener('click', function() { 
            if (currentIndex > 0) { 
                currentIndex--; 
                updateLightboxContent(); 
            } 
        });
        
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) closeLightbox();
        });

        document.addEventListener('keydown', function(e) {
            if (!lightbox.classList.contains('etb-lightbox-active')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowRight' && currentIndex < galleryImages.length - 1) { 
                currentIndex++; 
                updateLightboxContent(); 
            }
            if (e.key === 'ArrowLeft' && currentIndex > 0) { 
                currentIndex--; 
                updateLightboxContent(); 
            }
        });
    }
})();








