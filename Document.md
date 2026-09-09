# 📖 DOCUMENTATION TECHNIQUE & FONCTIONNELLE OFFICIELLE
# Elite Transfer Booking (Unified) — Version 2.0.0 (Jalon LimoExpress)

---

## 📌 FICHE D'IDENTITÉ DU PROJET

| Propriété | Spécification officielle |
| :--- | :--- |
| **Nom de l'extension** | **Elite Transfer Booking (Unified)** |
| **Identifiant / Text Domain** | `elite-transfer-booking` |
| **Version actuelle** | **`2.0.0`** *(Version consolidée, sécurisée, intégration LimoExpress complète, facture Option C, UX véhicule unique)* |
| **Auteur** | Kaiser EM / Reich C |
| **Type de solution** | Moteur WordPress 100 % autonome pour la réservation d'excursions touristiques privées, circuits multi-villes et transferts VTC VIP avec dispatch API |
| **Compatibilité WP / PHP** | WordPress 5.8+ (testé 6.x+) / PHP 7.4, 8.0, 8.1, 8.2+ |
| **Dépendances externes** | **0 dépendance** (Vanilla JS ES6+ natif, Dashicons, CSS3 tokenisé, Fetch API, WordPress Core APIs) |

---

## 📑 TABLE DES MATIÈRES

1. [Vue d'Ensemble & Changements Majeurs v2.0.0](#1-vue-densemble--changements-majeurs-v200)
2. [Arborescence Réelle et Assainie du Codebase](#2-arborescence-réelle-et-assainie-du-codebase)
3. [Modèle de Données & Dictionnaire des Métadonnées](#3-modèle-de-données--dictionnaire-des-métadonnées)
4. [Moteur Frontend & Expérience "Véhicule Unique"](#4-moteur-frontend--expérience-véhicule-unique)
5. [Connecteur API LimoExpress (Spécifications & Flux)](#5-connecteur-api-limoexpress-spécifications--flux)
6. [Système de Facturation Détaillée (Option C)](#6-système-de-facturation-détaillée-option-c)
7. [Boucliers Anti-Duplication & Résilience](#7-boucliers-anti-duplication--résilience)
8. [Architecture de Sécurité & Anti-Spam Triangulaire](#8-architecture-de-sécurité--anti-spam-triangulaire)
9. [Administration Back-Office WordPress](#9-administration-back-office-wordpress)
10. [Feuille de Route Future (Architecture Multi-Dispatch)](#10-feuille-de-route-future-architecture-multi-dispatch)

---

## 1. VUE D'ENSEMBLE & CHANGEMENTS MAJEURS v2.0.0

La version **2.0.0** marque la stabilisation complète du module de dispatching externe et la refonte de l'expérience de réservation :

1. **Sélection de Véhicule Unique (Single Vehicle UX)** : Fin des compteurs multiples `[- 1 +]`. Le client choisit un seul véhicule exclusif par réservation (comportement radio élégant).
2. **Liaison LimoExpress Validée de Bout en Bout** :
   * Endpoint de synchronisation : `PUT /api/integration/booking-with-fees/`.
   * Création et détection dynamique des clients via `PUT /api/integration/clients`.
   * Points de passage (`checkpoints`) injectés chronologiquement avec calcul automatique des heures d'arrivée ajustées.
   * Auto-découverte dynamique des classes de véhicules, types de réservation, statuts et clients sans aucun UUID en dur dans le code (*Zero-Hardcode*).
3. **Facturation Détaillée Décomposée (Option C)** : Moteur de facturation natif WordPress générant un document officiel imprimable et exportable en PDF avec ventilation ligne par ligne (véhicule, suppléments, extras, remises).
4. **Bouclier Anti-Duplication Double Niveau** : Mémorisation locale de l'UUID client (`_etb_limo_client_id`) et confirmation préventive obligatoire sur le bouton de transfert pour empêcher la création de courses en doublon.
5. **Collecte du Téléphone Client** : Champ téléphone natif intégré au formulaire, sauvegardé en base, affiché dans l'administration et transmis au chauffeur LimoExpress.
6. **Assainissement du Codebase** : Suppression définitive des fichiers orphelins (`class-elite-transfer-booking.php` et `class-etb-assets.php`) et élimination du code mort du carrousel dans le moteur JavaScript.

---

## 2. ARBORESCENCE RÉELLE ET ASSAINIE DU CODEBASE

```text
wp-content/plugins/elite-transfer-booking/
│
├── elite-transfer-booking.php          # Point d'entrée orchestrateur (Singleton, hooks, enqueue)
│
├── includes/                           # Modules PHP métier (Préfixe homogène class-etb-*.php)
│   ├── class-etb-cpt-manager.php       # Enregistrement des 6 CPTs & colonnes administratives
│   ├── class-etb-settings.php          # Réglages (Général, Formulaire, LimoExpress dynamique)
│   ├── class-etb-security.php          # Sécurité (Honeypot, Timestamp signé, Rate Limiting IP)
│   ├── class-etb-pricing-engine.php    # Calculateur financier serveur et résolution des circuits
│   ├── class-etb-meta-manager.php      # Metaboxes, boutons d'action (Facture, Resync Limo)
│   ├── class-etb-limoexpress.php       # Connecteur API LimoExpress complet et autonome
│   ├── class-etb-ajax.php              # Contrôleur AJAX (Validation, création CPT, resync, e-mails)
│   └── class-etb-shortcode.php         # Shortcodes [circuit_view] et [tour_booking] dynamiques
│
├── admin/                              # Assets d'administration WordPress
│   ├── css/
│   │   └── etb-admin.css               # Styles du gestionnaire d'onglets et répéteur back-office
│   └── js/
│       └── etb-admin.js                # Répéteur interactif d'options et timeline de circuit
│
├── public/                             # Assets Frontend publics
│   ├── css/
│   │   └── booking-widget.css          # Design System tokenisé, Shield Anti-Thème, Split Layout
│   └── js/
│       └── booking-widget.js           # Moteur réactif client (Véhicule unique, live pricing, Fetch)
│
└── templates/                          # Gabarits de vues HTML
    ├── circuit-view.php                # Split layout (Grille haute véhicules + Détails gauche + Widget droit)
    ├── booking-form.php                # Formulaire latéral droit (Téléphone, passagers, options, récapitulatif)
    └── invoice-print.php               # Facture officielle décomposée, imprimable et téléchargeable en PDF
```

---

## 3. MODÈLE DE DONNÉES & DICTIONNAIRE DES MÉTADONNÉES

### A. Les 6 Custom Post Types (CPTs)

| CPT | Identifiant | Visibilité / Menu | Rôle |
| :--- | :--- | :--- | :--- |
| **Circuit** | `circuit` | Public (`/circuits/%slug%/`) | Fiches descriptives des circuits et options de départ. |
| **Réservation** | `tour_booking` | Menu `Tour Booking` | Commandes et dossiers de réservation passés par les clients. |
| **Véhicule** | `tour_vehicle` | Sous-menu `Tour Booking` | Flotte (tarifs horaires, passagers/bagages max, classe LimoExpress). |
| **Option / Extra** | `tour_extra` | Sous-menu `Tour Booking` | Services additionnels (Guide, Champagne, Siège bébé...). |
| **Code Promo** | `tour_promo` | Sous-menu `Tour Booking` | Coupons de réduction (% ou montant fixe) avec statut d'activation. |
| **Point de départ** | `tour_pickup` | Masqué | *(Legacy)* Ancien CPT conservé pour rétrocompatibilité. |

---

### B. Dictionnaire des Méta-clés (`wp_postmeta`)

#### 1. Dossier de Réservation (`tour_booking`) :
* `_etb_customer_name` *(string)* : Nom complet du client.
* `_etb_customer_email` *(string)* : E-mail du client.
* `_etb_customer_phone` *(string)* : Numéro de téléphone international du client.
* `_etb_booking_date` *(string)* : Date de la prestation (`YYYY-MM-DD`).
* `_etb_booking_time` *(string)* : Heure de prise en charge (`HH:MM`).
* `_etb_pickup_address` *(string)* : Adresse ou hôtel de départ.
* `_etb_dropoff_info` *(string)* : Lieu de dépose (ou vide si identique au départ).
* `_etb_circuit_id` *(int)* : ID du post `circuit` (`0` si transfert simple).
* `_etb_circuit_option_id` *(string)* : Identifiant unique de l'option choisie (`opt_...`).
* `_etb_duration_hours` *(float)* : Durée totale facturée en heures.
* `_etb_adults` *(int)* / `_etb_children` *(int)* / `_etb_luggage` *(int)* : Capacités réservées.
* `_etb_vehicles` *(array)* : `[ vehicle_id => 1 ]` (Véhicule unique sélectionné).
* `_etb_extras` *(array)* : `[ extra_id => quantite ]`.
* `_etb_total_price` *(float)* : Montant net final facturé.
* `_etb_pricing_details` *(array)* : Snapshot complet de la décomposition financière.
* `_etb_promo_code` *(string)* : Code promo validé.
* `_etb_discount_amount` *(float)* : Montant déduit par la réduction.
* `_etb_status` *(string)* : `pending`, `confirmed`, `completed`, `cancelled`.
* `_etb_limo_status` *(string)* : `synced` ou `failed`.
* `_etb_limo_booking_id` *(string)* : Numéro de course LimoExpress (ex: `47fb308210e9c3`).
* `_etb_limo_client_id` *(string)* : UUID du client LimoExpress mémorisé pour anti-duplication.
* `_etb_limo_error` *(string)* : Dernier message d'erreur API en cas d'échec.

#### 2. Véhicule (`tour_vehicle`) :
* `_etb_hourly_rate` *(float)* : Tarif horaire ($/h ou €/h).
* `_etb_base_price` *(float)* : *(Fallback)* Tarif de base si taux horaire non renseigné.
* `_etb_max_pax` *(int)* : Capacité passagers maximale.
* `_etb_max_baggage` *(int)* : Capacité bagages maximale.
* `_etb_limo_class_id` *(string)* : UUID de la classe LimoExpress associée (`S Class`, `Van Class`, etc.).

---

### C. Options Globales (`wp_options`)

* **`etb_general_settings`** :
  * `currency` : Symbole monétaire (`€`, `$`).
  * `min_delay` : Délai minimum avant réservation en heures.
  * `admin_email` : Destinataire des alertes de commande et d'échec API.
  * `limo_enabled` : Activation de la passerelle LimoExpress (`1` ou `0`).
  * `limo_api_token` : Jeton Bearer Token LimoExpress.
  * `limo_client_id` : UUID du client par défaut choisi en menu déroulant.
  * `limo_booking_type_id` : UUID du type de réservation choisi en menu déroulant.
  * `limo_booking_status_id` : UUID du statut initial choisi en menu déroulant.
* **`etb_form_settings`** :
  * Drapeaux booléens (`show_name`, `show_phone`, `show_email`, `show_adults`, `show_children`, `show_extras`, `show_promo`, `show_note`...) appliqués dynamiquement via `wp_parse_args()`.

---

## 4. MOTEUR FRONTEND & EXPÉRIENCE "VÉHICULE UNIQUE"

### A. Ergonomie "Radio Card" Exclusive
Dans `templates/circuit-view.php` et `public/js/booking-widget.js` :
* La grille supérieure présente les véhicules sous forme de cartes blanches épurées.
* La pilule `[- 1 +]` a été supprimée au profit d'un champ caché `<input type="hidden" name="etb_car_qty[ID]" value="0">`.
* **Comportement exclusif au clic** :
  * Clic sur un véhicule inactif $\rightarrow$ Sa quantité passe à `1`, bordure orange, halo lumineux et coche animée.
  * **Toutes les autres cartes de véhicules sont immédiatement remises à `0` et désélectionnées**.
  * Le récapitulatif latéral et le prix recalculent instantanément le devis pour ce véhicule unique.
  * Clic sur le véhicule actif $\rightarrow$ Désélection (remise à 0).

### B. Moteur Réactif Client
* **Timeline Dynamique Relative** : Si le client modifie son heure de départ, `updateTimelineTimes()` recalcule et décale en temps réel chaque étape de la journée.
* **Protection Capacités** : Si les passagers (adultes + enfants) ou les bagages dépassent la capacité du véhicule choisi, le bouton de réservation est verrouillé avec un message d'alerte explicite.
* **Sécurisation Anti-Antériorité** : `dateInput.setAttribute('min', todayStr)` bloque les dates passées.

---

## 5. CONNECTEUR API LIMOEXPRESS (SPÉCIFICATIONS & FLUX)

Classe : `ETB_LimoExpress` (`includes/class-etb-limoexpress.php`)

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              RESERVATION VALIDEE DANS WP                               │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
                    [ ETAPE 1 : RESOLUTION DU CLIENT VOYAGEUR ]
                    ETB_LimoExpress::get_or_create_client()
                    1. Vérifie si _etb_limo_client_id existe déjà sur la commande WP.
                    2. Sinon, cherche si l'email existe déjà dans LimoExpress (anti-doublon).
                    3. Sinon, appelle PUT /api/integration/clients (name, email, phone, type: natural_person).
                    4. Mémorise l'UUID obtenu dans _etb_limo_client_id.
                                            │
                                            ▼
                    [ ETAPE 2 : PREPARATION DES POINTS DE PASSAGE ]
                    • Extrait les étapes du circuit (timeline).
                    • Calcule les horaires d'arrivée ajustés (HH:MM).
                    • Génère les checkpoints ordonnés (location.name pur, arrival_time, order_number).
                                            │
                                            ▼
                    [ ETAPE 3 : FORMATAGE DU PAYLOAD OFFICIEL ]
                    • Endpoint : PUT /api/integration/booking-with-fees/
                    • Types stricts : round_trip (bool), price (int arrondi net), duration (HH:MM).
                    • Notes : note_for_driver (synthèse mission) & note (détail complet + remise).
                    • Passagers : 1 seul passager principal propre (nom, prénom, email, téléphone).
                                            │
                                            ▼
                    [ ETAPE 4 : EXECUTION & TRACABILITE ]
                    • Succès (HTTP 200/201) ──► Postmeta _etb_limo_status = 'synced', course #ID.
                    • Échec (HTTP 4xx/5xx)  ──► Postmeta _etb_limo_status = 'failed', erreur loggée,
                                                Alerte e-mail rouge envoyée à l'administrateur.
```

---

## 6. SYSTÈME DE FACTURATION DÉTAILLÉE (OPTION C)

Gabarit : `templates/invoice-print.php`  
Contrôleur : `ETB_Meta_Manager::handle_print_invoice()`

Comme l'API LimoExpress ne permet pas d'injecter des lignes de facturation personnalisées (seuls 2 endpoints `GET invoices` et `POST mark-as-paid` existent), WordPress prend en charge l'édition de la facture client officielle :

* **Accessibilité** : Bouton noir **`📄 Voir / Imprimer la Facture`** présent sur chaque commande dans WordPress (`admin-post.php?action=etb_print_invoice`).
* **Numérotation Officielle** : Format `INV-YYYY-XXXX` (ex: `INV-2026-0031`) avec référence de dossier WP et numéro de course LimoExpress associée.
* **Tableau d'Articles Décomposé Ligne par Ligne** :
  1. *Ligne(s) Véhicule(s)* : Nom du véhicule, mise à disposition horaire, quantité, tarif horaire $\times$ durée, sous-total.
  2. *Ligne Supplément départ circuit* : Frais de liaison kilométrique pour la ville de départ.
  3. *Ligne Supplément prise en charge* : Si pickup personnalisé hors zone.
  4. *Lignes Extras / Options* : Chaque option (Guide, Champagne, Siège bébé...) avec quantité et montant total.
  5. *Ligne Remise Code Promo* : Affichage en vert de la déduction avec le nom du coupon.
  6. *Ligne Total Net* : Montant final à payer avec symbole de la devise configurée.
* **Prêt pour l'Impression / PDF** : Feuille de style optimisée `@media print` avec bouton d'export direct en PDF vectoriel sans barre d'outils parasite.

---

## 7. BOUCLIERS ANTI-DUPLICATION & RÉSILIENCE

### A. Anti-Duplication des Réservations (Courses)
* **Détection d'état** : Si une commande est déjà synchronisée (`_etb_limo_status === 'synced'`), le bouton de transfert de la commande affiche `⚠️ Forcer un re-transfert LimoExpress`.
* **Confirmation bloquante** : Un clic déclenche un dialogue d'avertissement :  
  `"⚠️ ATTENTION ANTI-DOUBLON : Cette commande est DÉJÀ synchronisée dans LimoExpress (Course #XXXX). Voulez-vous vraiment générer une DEUXIÈME course en doublon ?"`.
* Si l'administrateur clique sur **Annuler**, la requête est avortée sans aucun appel réseau.

### B. Anti-Duplication des Clients
* Dès qu'un client voyageur est créé ou identifié dans LimoExpress, son UUID est enregistré dans `_etb_limo_client_id`.
* Lors de tout transfert ou re-synchronisation ultérieur de cette commande, le connecteur lit directement cette métadonnée locale : **aucun nouvel appel de création client n'est envoyé à LimoExpress**.

### C. Alerte E-mail Administrateur en Cas d'Échec Réseau
* Si la synchronisation LimoExpress échoue lors de la commande d'un client (ex: coupure réseau, API indisponible), la réservation **est conservée en toute sécurité dans WordPress**.
* L'administrateur reçoit immédiatement un e-mail avec objet préfixé `[Action Requise - Échec LimoExpress]` contenant un encadré rouge détaillant l'erreur et l'invitant à relancer le transfert en un clic dès le rétablissement de la connexion.

---

## 8. ARCHITECTURE DE SÉCURITÉ & ANTI-SPAM TRIANGULAIRE

```text
REQUÊTE POST ENTRANTE
 │
 ├──► 1. VÉRIFICATION DU NONCE CSRF (check_ajax_referer)
 │
 ├──► 2. CONTRÔLE HONEYPOT INVISIBLE (Champ piège etb_hp_email non vide ──► Rejet)
 │
 ├──► 3. CONTRÔLE DE VÉLOCITÉ PAR TIMESTAMP SIGNÉ (Signature wp_hash(), soumission < 2s ──► Rejet)
 │
 ├──► 4. RATE LIMITING PAR TRANSIENTS IP (Max 10 réservations / 10 min, Max 15 promos échouées / 10 min)
 │
 └──► 5. RECALCUL 100 % SERVEUR (ETB_Pricing_Engine ignore tout prix envoyé par le navigateur)
```

---

## 9. ADMINISTRATION BACK-OFFICE WORDPRESS

### A. Menus Unifiés
* **Tour Booking**
  * *Toutes les Réservations* : Tableau de bord avec statuts, montants, coordonnées avec téléphone `📞` et statut LimoExpress.
  * *Circuits & Tours* : Fiches circuits avec gestionnaire d'onglets de villes de départ et répéteur de timeline.
  * *Véhicules* : Gestion de la flotte avec sélecteur de classe LimoExpress automatique et bouton de rafraîchissement `🔄`.
  * *Options* : Extras avec types de tarification (fixe, par unité).
  * *Codes Promo* : Remises fixes ou en pourcentage.
  * *Réglages* :
    * Onglet Général : Devise, délai minimum, e-mail admin, activation LimoExpress, Token API, et menus déroulants automatiques (*Client par défaut*, *Type de réservation*, *Statut initial*).
    * Onglet Configuration du Formulaire : Cases à cocher pour afficher/masquer chaque champ frontend.

### B. Boîte de Détail de Commande (`render_booking_box`)
* Statut de réservation modifiable (`En attente`, `Confirmée`, `Terminée`, `Annulée`) avec envoi automatique d'e-mail au client.
* Bouton **`📄 Voir / Imprimer la Facture`** (Option C).
* Badge LimoExpress dynamique avec bouton **`🔄 Transférer vers LimoExpress`** (ou `⚠️ Forcer un re-transfert`) et spinner interactif.
* Affichage du client (Nom, E-mail, Téléphone), trajet, véhicule, options, et décomposition financière.
* Grand bloc récapitulatif du programme avec timeline aux horaires recalculés.

---

## 10. FEUILLE DE ROUTE FUTURE (ARCHITECTURE MULTI-DISPATCH)

Le socle ETB v2.0.0 a été conçu pour accueillir la transition vers le **Design Pattern "Adapter" (Multi-Dispatch)** :

```text
                     [ SOCLE CENTRAL ETB ]
      (Circuits, Pricing Engine, Formulaires, Sécurité, Factures PDF)
                               │
                               ▼
                 [ INTERFACE UNIVERSELLE DISPATCH ]
                 interface-etb-dispatcher.php
                               │
         ┌─────────────────────┼─────────────────────┐
         ▼                     ▼                     ▼
[ CONNECTEUR LIMOEXPRESS ] [ CONNECTEUR KYMARK ] [ 100% AUTONOME ]
 class-etb-limoexpress.php  class-etb-kymark.php  (Aucun dispatch)
```

1. **Phase Suivante (Kymark / Multi-App)** :
   * Création de l'interface `ETB_Dispatcher_Interface` standardisant les méthodes `send_booking()`, `get_vehicles()`, `get_settings()`.
   * Intégration du sélecteur d'application dans les réglages : `[ Autonome | LimoExpress | Kymark ]`.
   * Développement du connecteur dédié Kymark sans toucher au cœur d'ETB.
2. **Synchronisation Bidirectionnelle WP $\rightarrow$ LimoExpress** :
   * Détection des modifications de date, heure ou statut dans WordPress pour mise à jour de la course existante via l'API.

---

*Documentation technique et fonctionnelle officielle — **Elite Transfer Booking v2.0.0** — Source de vérité absolue du projet.*