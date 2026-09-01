/**
 * Elite Transfer Booking - Front-end Logic V2
 * Intégration du Taux horaire et synchronisation en direct avec Circuit Options
 */
(function () {
    'use strict';

    const init = function () {
        // 1. Recherche du conteneur racine
        const root = document.querySelector('#etb-booking-app');
        if (!root) return;

        // 2. Cache des éléments du DOM
        // Détection de toutes les cartes véhicules (en haut et dans le widget)
        const cards = Array.from(document.querySelectorAll('.etb-vehicle-card'));
        const sidebarValEl = document.querySelector('#etb-sidebar-val');
        const sidebarMaxPaxEl = document.querySelector('#etb-sidebar-max-pax');
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
        // NOUVEAUTÉ V2 : Empêcher la sélection des dates passées
        const todayStr = new Date().toISOString().split('T')[0];
        if (dateInput) {
            dateInput.setAttribute('min', todayStr);
        }
        const timeInput = root.querySelector('input[name="etb_time"]');
        const nameErrorEl = root.querySelector('#etb-name-error');
        const emailErrorEl = root.querySelector('#etb-email-error');
        const dateErrorEl = root.querySelector('#etb-date-error');
        const timeErrorEl = root.querySelector('#etb-time-error');
        const vehicleSelectionErrorEl = root.querySelector('#etb-vehicle-selection-error');
        const pickupErrorEl = root.querySelector('#etb-pickup-error');

        const globalSubmitErrorEl = root.querySelector('#etb-global-submit-error') || document.querySelector('#etb-global-submit-error');

        // Éléments Drop-off
        const diffDropoffCb = root.querySelector('#etb-diff-dropoff-cb');
        const dropoffContainer = root.querySelector('#etb-dropoff-container');
        const dropoffInfo = root.querySelector('#etb-dropoff-info');

        // État interne global
        let state = {
            activeIndex: 0,
            pickup: { name: '', price: 0 },
            currency: '€',
            promo: { code: '', discount_type: 'fixed', discount_value: 0 },
            circuit: { duration: 1, additionalPrice: 0, optionId: '', cityName: '' }
        };

        let hasAttemptedSubmit = false;
        let hasSelectedVehicle = false;
        let hasRequiredFieldsMissing = false;

        const parsePrice = (str) => {
            if (!str) return 0;
            const clean = str.replace(',', '.').replace(/[^-0-9.]/g, '');
            return parseFloat(clean) || 0;
        };

        // Gestion du Drop-off conditionnel
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

        // Adresse de prise en charge (Pickup libre)
        if (pickupInput) {
            pickupInput.addEventListener('input', function () {
                state.pickup = {
                    name: this.value.trim(),
                    price: 0
                };
                updateSummary();
            });
        }

        // Code promo : soumission AJAX
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
                        promoMsg.textContent = 'Veuillez saisir un code promo.';
                    }
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'etb_validate_promo');
                formData.append('nonce', etbAjax.nonce);
                formData.append('promo_code', promoCode);

                promoBtn.disabled = true;

                fetch(etbAjax.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(res => {
                    promoBtn.disabled = false;
                    if (promoMsg) promoMsg.style.display = 'block';

                    if (res.success) {
                        state.promo = {
                            code: res.data.code,
                            discount_type: res.data.discount_type,
                            discount_value: res.data.discount_value
                        };
                        if (promoMsg) {
                            promoMsg.className = 'etb-field-feedback etb-success';
                            promoMsg.textContent = res.data.message;
                        }
                    } else {
                        state.promo = { code: '', discount_type: 'fixed', discount_value: 0 };
                        if (promoMsg) {
                            promoMsg.className = 'etb-field-feedback etb-error';
                            promoMsg.textContent = res.data.message;
                        }
                    }

                    refreshAll();
                })
                .catch(() => {
                    promoBtn.disabled = false;
                    if (promoMsg) {
                        promoMsg.style.display = 'block';
                        promoMsg.className = 'etb-field-feedback etb-error';
                        promoMsg.textContent = 'Erreur réseau lors de la vérification du code.';
                    }
                });
            });
        }

       // Animation et confinement du Carousel (3 cartes visibles max)
        const updateCarousel = () => {
            if (!cards || cards.length === 0) return;
            const n = cards.length;

            cards.forEach((card, i) => {
                let offset = i - state.activeIndex;

                // Logique circulaire (boucle infinie)
                if (offset > n / 2) offset -= n;
                else if (offset < -n / 2) offset += n;

                const absOffset = Math.abs(offset);

                let horizontalShift = 0;
                let scale = 1;
                let opacity = 0;
                let zIndex = 0;
                let visibility = 'hidden';

                if (offset === 0) {
                    // 1. CARTE CENTRALE ACTIVE
                    horizontalShift = 0;
                    scale = 1.05;
                    opacity = 1;
                    zIndex = 20;
                    visibility = 'visible';
                    card.style.pointerEvents = 'auto';
                } else if (absOffset === 1) {
                    // 2. CARTES ADJACENTES (1 à gauche, 1 à droite)
                    horizontalShift = offset * 25; // Décalage contrôlé en %
                    scale = 0.85;
                    opacity = 0.80; // Semi-transparence élégante
                    zIndex = 10;
                    visibility = 'visible';
                    card.style.pointerEvents = 'auto';
                } else {
                    // 3. TOUTES LES AUTRES CARTES (Masquées en arrière-plan)
                    horizontalShift = offset > 0 ? 35 : -35;
                    scale = 0.7;
                    opacity = 0;
                    zIndex = 1;
                    visibility = 'hidden';
                    card.style.pointerEvents = 'none';
                }

                // Application des transformations
                card.style.transform = `translateX(${horizontalShift}%) scale(${scale})`;
                card.style.opacity = opacity;
                card.style.zIndex = zIndex;
                card.style.visibility = visibility;

                card.classList.toggle('etb-active', offset === 0);
            });

            // Mise à jour de l'ID du véhicule actif
            if (vehicleInput && cards[state.activeIndex]) {
                vehicleInput.value = cards[state.activeIndex].dataset.id;
            }
        };

        // Recalcul du prix et mise à jour du résumé
        const updateSummary = () => {
            let total = 0;
            let vehiclesHtml = '';
            let circuitPriceHtml = ''; // Déclaré proprement ici
            let totalCapacityPax = 0;
            let totalCapacityBaggage = 0;
            hasSelectedVehicle = false;

            const duration = state.circuit.duration || 1;

            // 1. Calcul des véhicules (Taux horaire × Durée × Quantité)
            cards.forEach(card => {
                const qtyInput = card.querySelector('input[name^="etb_car_qty"]');
                const qty = parseInt(qtyInput ? qtyInput.value : 0);
                const price = parsePrice(card.querySelector('.etb-vehicle-price').textContent);
                const name = card.querySelector('h3').textContent.trim();
                const maxPax = parseInt(card.dataset.maxPax) || 0;
                const maxBaggage = parseInt(card.dataset.maxBaggage) || 0;

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
                const activeCard = cards[state.activeIndex];
                const name = activeCard ? activeCard.querySelector('h3').textContent.trim() : 'Véhicule';
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

            // Validation capacité passagers
            const totalPassengers = adults + children;
            const isPaxCapacityExceeded = totalPassengers > totalCapacityPax;

            if (capacityDisplayEl) {
                capacityDisplayEl.textContent = totalCapacityPax > 0
                    ? `👤 ${totalCapacityPax} passagers max`
                    : '';
            }

            if (paxCapacityErrorEl) {
                paxCapacityErrorEl.style.display = (hasSelectedVehicle && isPaxCapacityExceeded) ? 'block' : 'none';
            }

            // Validation capacité bagages
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
            // Validation date manquante ou passée
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
                // Masquer l'erreur dès qu'un véhicule est sélectionné
                if (hasSelectedVehicle) {
                    vehicleSelectionErrorEl.style.display = 'none';
                } else if (hasAttemptedSubmit && isVehicleMissing) {
                    vehicleSelectionErrorEl.style.display = 'block';
                }
            }
            const isPickupMissing = !!pickupInput && pickupInput.value.trim() === '';
            if (pickupErrorEl) pickupErrorEl.style.display = (hasAttemptedSubmit && isPickupMissing) ? 'block' : 'none';

            hasRequiredFieldsMissing = isNameMissing || isEmailMissing || isDateMissing || isDatePast || isTimeMissing || isVehicleMissing || isPickupMissing;

            // Affichage/Masquage de l'alerte globale au-dessus du récapitulatif
            if (globalSubmitErrorEl) {
                globalSubmitErrorEl.style.display = (hasAttemptedSubmit && hasRequiredFieldsMissing) ? 'block' : 'none';
            }
            
            // État du bouton
            if (submitButton) {
                const isBlocked = isPaxCapacityExceeded || isBaggageCapacityExceeded;                
                submitButton.disabled = isBlocked;
                submitButton.classList.toggle('etb-disabled', isBlocked);
            }

            // Options supplémentaires (Extras)
            let extrasHtml = '';
            root.querySelectorAll('input[name^="etb_extra_"]').forEach(inp => {
                const qty = parseInt(inp.value) || 0;
                if (qty > 0) {
                    const parent = inp.closest('.etb-pax-card');
                    const name = parent?.querySelector('strong')?.textContent.trim() || 'Option';
                    const priceSpan = parent?.querySelector('.etb-price-tag');
                    const price = parsePrice(priceSpan?.textContent || '0');
                    
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
                promoHtml = `<div class="etb-summary-row etb-promo-row" style="color: #22c55e;"><strong>Code promo (${state.promo.code})</strong><strong>- ${discountAmount.toFixed(0)} ${state.currency}</strong></div>`;
            }

            // Mise à jour du résumé
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

            // NOUVEAUTÉ V2.2 : Mise à jour de l'en-tête latéral "À PARTIR DE" et "Passagers max"
            if (sidebarValEl) {
                sidebarValEl.textContent = total.toFixed(0);
            }
            if (sidebarMaxPaxEl) {
                sidebarMaxPaxEl.textContent = totalCapacityPax;
            }

            // Affichage/Masquage animé de l'en-tête si un véhicule est choisi
            const sidebarHeaderEl = document.querySelector('#etb-sidebar-price-header');
            if (sidebarHeaderEl) {
                sidebarHeaderEl.classList.toggle('etb-visible', hasSelectedVehicle && totalCapacityPax > 0);
            }

        };

        const refreshAll = () => {
            updateCarousel();
            updateSummary();
        };

        // Écouteur d'événement envoyé par Circuit Options
        window.addEventListener('etb:circuit_changed', (e) => {
            if (e.detail) {
                state.circuit = {
                    duration: parseFloat(e.detail.duration) || 1,
                    additionalPrice: parseFloat(e.detail.additionalPrice) || 0,
                    optionId: e.detail.optionId || '',
                    cityName: e.detail.cityName || ''
                };
                refreshAll();
            }
        });

        // Événements Carousel
        root.querySelector('.prev')?.addEventListener('click', (e) => {
            e.preventDefault();
            state.activeIndex = (state.activeIndex - 1 + cards.length) % cards.length;
            refreshAll();
        });

        root.querySelector('.next')?.addEventListener('click', (e) => {
            e.preventDefault();
            state.activeIndex = (state.activeIndex + 1) % cards.length;
            refreshAll();
        });

        // Délégation boutons +/- (Quantités)
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.etb-qty-btn');
            if (!btn) return;
            
            e.preventDefault();
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

        // Toggle Extras
        root.addEventListener('click', (e) => {
            const item = e.target.closest('.etb-extra-item.etb-type-toggle');
            if (!item) return;

            const input = item.querySelector('input[type="hidden"]');
            const isSelected = item.classList.contains('etb-selected');
            
            item.classList.toggle('etb-selected');
            input.value = isSelected ? "0" : "1";
            
            refreshAll(); 
        });

        // 1. Clic sur la carte entière pour sélectionner (Quantité = 1)
        document.addEventListener('click', (e) => {
            const card = e.target.closest('.etb-vehicle-card');
            if (!card) return;

            // Ignorer si le clic provient déjà des boutons +/- ou de la coche
            if (e.target.closest('.etb-qty-control') || e.target.closest('.etb-selection-check')) {
                return;
            }

            const input = card.querySelector('input[name^="etb_car_qty"]');
            if (input) {
                const currentQty = parseInt(input.value) || 0;
                // Si la carte n'est pas encore sélectionnée, on l'active à 1
                if (currentQty === 0) {
                    input.value = 1;
                    refreshAll();
                }else{
                    input.value = 0;
                    refreshAll();
                }

            }
        });

        // 2. Clic sur la coche orange pour désélectionner (Quantité = 0)
        document.addEventListener('click', (e) => {
            const check = e.target.closest('.etb-selection-check');
            if (!check) return;

            e.preventDefault();
            e.stopPropagation();

            const card = check.closest('.etb-vehicle-card');
            const input = card?.querySelector('input[name^="etb_car_qty"]');
            if (input) {
                input.value = 0;
                refreshAll();
            }


        });
        // Écouteurs de saisie
        const luggageInput = root.querySelector('input[name="etb_total_luggage"]');
        if (luggageInput) luggageInput.addEventListener('input', refreshAll);
        if (nameInput) nameInput.addEventListener('input', refreshAll);
        if (emailInput) emailInput.addEventListener('input', refreshAll);
        if (dateInput) {
            dateInput.addEventListener('change', refreshAll);
            dateInput.addEventListener('input', refreshAll);
        }
        if (timeInput) {
            timeInput.addEventListener('change', refreshAll);
            timeInput.addEventListener('input', refreshAll);
        }

        // Soumission finale AJAX
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
                // Collecte tous les champs du formulaire ET les quantités des véhicules du haut
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
                        const formElement = root.querySelector('form') || root;
                        formElement.innerHTML = `
                            <div class="etb-success-notice" style="padding: 25px; text-align: center; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin: 20px 0;">
                                <h3 style="color: #166534; margin-top: 0;">Votre demande de réservation a bien été reçue !</h3>
                                <p style="color: #15803d; font-size: 16px;">
                                    Numéro de dossier : <strong>#${result.data.booking_id}</strong>
                                </p>
                                <p style="color: #374151;">
                                    Un accusé de réception a été envoyé à l'adresse <strong>${result.data.email}</strong>.
                                </p>
                            </div>
                        `;
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

        window.addEventListener('resize', updateCarousel);

        if (cards.length > 0) {
            const pText = cards[0].querySelector('.etb-vehicle-price').textContent;
            if (pText.includes('$')) state.currency = '$';
            else if (pText.includes('£')) state.currency = '£';
        }

        refreshAll();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
