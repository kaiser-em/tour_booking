Voici la clôture officielle de notre cycle de développement. Nous mettons à jour la **documentation technique (`Document.md`)**, le **guide d'exploitation (`README.md`)**, puis nous préparons le message de **commit Git**.

---

# 📄 1. MISE À JOUR : `Document.md`

### Fichier : `Document.md`
**Raison :** Mettre à niveau la documentation officielle de référence avec l'ensemble des modules ajoutés (Widget minimaliste `[etb_transfer]`, Mode Custom Quote Option C, géocodage Google/Mapbox, tarification par paliers, et intégration WhatsApp).

Voici le contenu complet et assaini à placer dans votre fichier `Document.md` :

```markdown
# 📖 DOCUMENTATION TECHNIQUE & FONCTIONNELLE OFFICIELLE
# Elite Transfer Booking (Unified) — Version 2.0.3

---

## 📌 FICHE D'IDENTITÉ DU PROJET

| Propriété | Spécification officielle |
| :--- | :--- |
| **Nom de l'extension** | **Elite Transfer Booking (Unified)** |
| **Identifiant / Text Domain** | `elite-transfer-booking` |
| **Version actuelle** | **`2.0.3`** *(Widget Minimal VTC, Mode Devis Hybride Option C, WhatsApp Entreprise, Zéro-Hardcode, Sécurité durcie)* |
| **Auteur** | Kaiser EM / Reich C |
| **Type de solution** | Moteur WordPress autonome pour réservations d'excursions, transferts VTC VIP avec dispatch API (LimoExpress) |
| **Compatibilité WP / PHP** | WordPress 5.8+ (testé 6.x+) / PHP 7.4, 8.0, 8.1, 8.2+ |
| **Dépendances externes** | **0 dépendance serveur** (Vanilla JS ES6+ natif, Dashicons, CSS3 tokenisé, Fetch API, WordPress Core APIs) |

---

## 📑 TABLE DES MATIÈRES

1. [Vue d'Ensemble & Nouveautés v2.0.3](#1-vue-densemble--nouveautés-v203)
2. [Arborescence Réelle du Codebase](#2-arborescence-réelle-du-codebase)
3. [Modèle de Données & Dictionnaire des Métadonnées](#3-modèle-de-données--dictionnaire-des-métadonnées)
4. [Moteur Frontend & Widgets de Réservation](#4-moteur-frontend--widgets-de-réservation)
5. [Connecteur API LimoExpress & Tarification Itinéraire](#5-connecteur-api-limoexpress--tarification-itinéraire)
6. [Système de Facturation Détaillée (Option C)](#6-système-de-facturation-détaillée-option-c)
7. [Architecture de Sécurité & Rate Limiting](#7-architecture-de-sécurité--rate-limiting)
8. [Administration Back-Office WordPress](#8-administration-back-office-wordpress)

---

## 1. VUE D'ENSEMBLE & NOUVEAUTÉS v2.0.3

La version **2.0.3** consolide le socle avec des fonctionnalités avancées pour le transport VTC VIP :

1. **Widget VTC Minimaliste (`[etb_transfer]`)** :
   * Interface ergonomique inspirée des standards de l'industrie (Blacklane / Eden Cab).
   * Double mode : **Trajet simple (One way)** et **À l'heure (By the hour)**.
   * Sélecteur 12H (AM/PM) sur-mesure avec crans de 5 minutes.
   * Calcul en direct des tarifs au changement de durée sans rechargement.
2. **Gestion des Itinéraires Hors Zone ("Custom Quote" Hybride - Option C)** :
   * Détection automatique des trajets non couverts par la matrice tarifaire automatique LimoExpress.
   * Affichage élégant d'un badge ambré `Custom Quote` sur l'ensemble de la flotte.
   * Double action finale : Redirection vers le formulaire LimoExpress pour formaliser la demande + Bouton vert direct `Quick Inquiry` ciblant le numéro WhatsApp officiel de l'entreprise.
3. **Double Moteur d'Autocomplétion d'Adresses** :
   * Prise en charge modulaire de **Google Places API** (avec affichage sur 2 lignes et langue anglaise native) et de **Mapbox Search Box API** (avec session tokens et navigation clavier).
4. **Grille Tarifaire Avancée par Paliers Horaires** :
   * Paramétrage sur les véhicules : Durée minimale (`_etb_min_hours`), Forfait 10h (`_etb_pack_10h`), Heure supplémentaire au-delà de 10h (`_etb_sup_hour_rate`), Quota km inclus (`_etb_km_included_ph`).
   * Calcul unifié et harmonisé côté serveur (`ETB_Pricing_Engine`) et sur la facture imprimable (`invoice-print.php`).
5. **Gestion Évoluée des Codes Promo** :
   * Période de validité (`valid_from`, `valid_to`) et décompte automatique du stock restant (`_etb_promo_remaining`).
   * Correction du flux de sauvegarde métabox (isolation stricte par `break`).

---

## 2. ARBORESCENCE RÉELLE DU CODEBASE

```text
wp-content/plugins/elite-transfer-booking/
│
├── elite-transfer-booking.php          # Orchestrateur Singleton, enqueue assets & localisation JS
│
├── includes/
│   ├── class-etb-cpt-manager.php       # Enregistrement des 6 CPTs & colonnes d'administration
│   ├── class-etb-settings.php          # Réglages généraux, devise, WhatsApp & dispatcher
│   ├── class-etb-security.php          # Nonces, Honeypot, Timestamp signé & Rate Limiting IP
│   ├── class-etb-pricing-engine.php    # Moteur financier unifié (forfaits 10h, extras, promos)
│   ├── class-etb-meta-manager.php      # Métaboxes d'administration, resync Limo & facture
│   ├── class-etb-limoexpress.php       # Connecteur API LimoExpress complet
│   ├── class-etb-dispatcher-manager.php# Gestionnaire de dispatch (Autonome / LimoExpress)
│   ├── class-etb-ajax.php              # Contrôleur AJAX (validation, quick pricing, réservations)
│   └── class-etb-shortcode.php         # Shortcodes [circuit_view], [tour_booking], [etb_transfer]
│
├── admin/
│   ├── css/etb-admin.css               # Styles de l'administration et répéteurs
│   └── js/etb-admin.js                 # Scripts des onglets et timeline circuits
│
├── public/
│   ├── css/booking-widget.css          # Design System tokenisé, Theme Shield & widget minimal
│   └── js/booking-widget.js            # Moteur réactif client, calculs live & autocomplétion
│
└── templates/
    ├── circuit-view.php                # Split layout complet pour circuits
    ├── booking-form.php                # Formulaire latéral de réservation
    ├── transfer-widget.php             # Widget minimal VTC One way / Hourly
    └── invoice-print.php               # Facture officielle décomposée prête à l'impression / PDF
```

---

## 3. MODÈLE DE DONNÉES & DICTIONNAIRE DES MÉTADONNÉES

### A. Véhicule (`tour_vehicle`) :
* `_etb_base_price` *(float)* : Tarif forfaitaire de base.
* `_etb_hourly_rate` *(float)* : Tarif horaire standard (< 10h).
* `_etb_min_hours` *(int)* : Durée minimale requise (ex: 4h pour berline, 10h pour Sprinter).
* `_etb_pack_10h` *(float)* : Forfait fixe 10 heures.
* `_etb_sup_hour_rate` *(float)* : Tarif horaire supplémentaire au-delà de 10h.
* `_etb_km_included_ph` *(int)* : Kilomètres inclus par heure de mise à disposition.
* `_etb_km_sup_rate` *(float)* : Tarif facturé par kilomètre supplémentaire.
* `_etb_max_pax` *(int)* / `_etb_max_baggage` *(int)* : Capacités maximales passagers et bagages.
* `_etb_limo_class_id` *(string)* : UUID de la classe associée dans LimoExpress.
* `_etb_hover_image` *(url)* : Image animée (GIF) déclenchée au survol.

### B. Options Globales (`wp_options` $\rightarrow$ `etb_general_settings`) :
* `currency` : Symbole monétaire (`€`, `$`, `CHF`...).
* `company_whatsapp` : Numéro international officiel pour les demandes de devis.
* `address_provider` : Fournisseur de géocodage actif (`google` ou `mapbox`).
* `google_maps_api_key` : Clé API Google Cloud (Places & Maps JS API).
* `mapbox_token` : Jeton public Mapbox.
* `active_dispatcher` : `none` (Autonome) ou `limoexpress`.
* `limo_api_token` / `limo_client_id` / `limo_booking_type_id` / `limo_booking_status_id`.

---

## 4. MOTEUR FRONTEND & WIDGETS DE RÉSERVATION

### A. Widget Minimal VTC (`[etb_transfer]`)
1. **Bascule One way / By the hour** : Affiche dynamiquement le champ de dépose ou le sélecteur de durée.
2. **Empilement Z-Index Garanti** : Les menus déroulants de durée et d'heures flottent toujours au premier plan au-dessus des cartes sans troncature.
3. **Barre de Confirmation Réactive** : Masquée par défaut, elle s'affiche instantanément dès qu'un véhicule est choisi, et se referme immédiatement si la carte est désélectionnée.

---

## 5. CONNECTEUR API LIMOEXPRESS & TARIFICATION ITINÉRAIRE

* **Calcul de Trajet en Direct (`etb_quick_pricing`)** : Interroge `GET /api/integration/pricing` avec les coordonnées GPS exactes (`from_lat`, `from_lng`, `to_lat`, `to_lng`) et allume les véhicules éligibles avec leurs tarifs officiels.
* **Résilience "Custom Quote"** : Si l'API ne couvre pas l'itinéraire, la flotte reste visible en mode devis au lieu de bloquer l'utilisateur.

---

## 6. ARCHITECTURE DE SÉCURITÉ & RATE LIMITING

1. **CSRF** : Nonces WordPress contrôlés sur chaque requête (`check_ajax_referer`).
2. **Honeypot** : Champ invisible `etb_hp_email`.
3. **Timestamp Signé Cryptographiquement** : Rejet des soumissions automatisées en moins de 2 secondes.
4. **Rate Limiting par Transients IP** :
   * Réservations : Max 10 soumissions / 10 minutes par IP.
   * Codes promo : Max 15 échecs / 10 minutes par IP.
   * Estimation LimoExpress : Max 30 calculs / 10 minutes par IP.
```


```text
feat: release v2.0.3 with minimal VTC widget, hybrid quote mode and hardened security

- feat: add [etb_transfer] minimal VTC widget with One Way / By the Hour modes
- feat: implement Option C Custom Quote mode for unpriced and long-distance routes
- feat: add official company WhatsApp direct inquiry button with prefilled trip data
- feat: support live instant price recalculation upon duration selection
- feat: improve Google Places address suggestions with 2-line layout and English locale
- fix: resolve critical missing break statement in tour_promo post meta save
- fix: correct CSS syntax errors and remove duplicate rules in booking-widget.css
- fix: eliminate z-index stacking context bug on time and duration dropdowns
- fix: fix button deselection state keeping 'Selected' text
- fix: hide bottom confirmation bar by default until a vehicle is selected (.is-visible pattern)
- refactor: harmonize ETB_Pricing_Engine with 10h packages and tiered hourly rates
- refactor: align printable invoice vehicle unit calculations with pricing engine
- refactor: replace hardcoded Euro symbols with dynamic currency in admin columns and metaboxes
- security: add transient rate limiting on public quick pricing endpoint (30 req/10 min)
- docs: synchronize Document.md and README.md with v2.0.3 specifications
```
