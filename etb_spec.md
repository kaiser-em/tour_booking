# 📖 DOCUMENTATION TECHNIQUE & FONCTIONNELLE OFFICIELLE
# Elite Transfer Booking (Unified) — Version 1.0.3

---

## FICHE D'IDENTITÉ DU PROJET

| Propriété | Valeur |
| :--- | :--- |
| **Nom de l'extension** | **Elite Transfer Booking (Unified)** |
| **Identifiant / Text Domain** | `elite-transfer-booking` |
| **Version actuelle** | **`1.0.3`** |
| **Dépôt GitHub** | [https://github.com/kaiser-em/tour_booking](https://github.com/kaiser-em/tour_booking) |
| **Auteur** | Kaiser EM |
| **Type de projet** | Extension WordPress autonome (Réservation d'excursions, circuits et transferts privés) |
| **Compatibilité WordPress** | WordPress 5.8+ (Testé jusqu'à 6.x+) |
| **Compatibilité PHP** | PHP 7.4 / PHP 8.0 / PHP 8.1 / PHP 8.2+ |
| **Dépendances tierces** | **0 dépendance** (Pur Vanilla JS ES6+, Dashicons natifs, CSS natif, Fetch API) |

---

## TABLE DES MATIÈRES

1. [Vue d'Ensemble & Objectifs](#1-vue-densemble--objectifs)
2. [Arborescence Complète du Codebase](#2-arborescence-complète-du-codebase)
3. [Modèle de Données & Base de Données (CPT & Métadonnées)](#3-modèle-de-données--base-de-données-cpt--métadonnées)
4. [Moteur de Tarification (Pricing Engine)](#4-moteur-de-tarification-pricing-engine)
5. [Gestionnaire des Circuits & Options de Départ](#5-gestionnaire-des-circuits--options-de-départ)
6. [Expérience Utilisateur Frontend (Split Layout)](#6-expérience-utilisateur-frontend-split-layout)
7. [Moteur JavaScript Réactif & Événements](#7-moteur-javascript-réactif--événements)
8. [Système CSS, Design Tokens & Bouclier Anti-Thème](#8-système-css-design-tokens--bouclier-anti-thème)
9. [Pipeline AJAX, Validation & Notifications E-mail](#9-pipeline-ajax-validation--notifications-e-mail)
10. [Administration Back-Office WordPress](#10-administration-back-office-wordpress)
11. [Guide d'Intégration & Shortcodes](#11-guide-dintégration--shortcodes)
12. [Roadmap Technique & Évolutions Futures](#12-roadmap-technique--évolutions-futures)

---

## 1. VUE D'ENSEMBLE & OBJECTIFS

**Elite Transfer Booking (v1.0.3)** est le résultat de la fusion technique réussie du moteur de réservation **ETB** et du gestionnaire de voyages **Circuit Options** au sein d'une seule et même extension WordPress.

### Les 4 Piliers Métier :
1. **Gestion de Flotte & Taux Horaires** : Sélection multi-véhicules avec quantités, calculs basés sur le temps de location, et contrôle strict des jauges de passagers/bagages.
2. **Gestion des Circuits & Itinéraires** : Création d'excursions avec plusieurs villes de départ dynamiques (durée dédiée, supplément tarifaire, programme/timeline par étapes, liste d'inclusions/exclusions).
3. **Expérience Utilisateur Fluide (Split Layout)** : Grille supérieure de véhicules sélectionnables au clic (design blanc épuré "Photo 1"), colonne de gauche dédiée aux détails du circuit, et colonne de droite sticky pour la réservation en direct.
4. **Source de Vérité Côté Serveur** : Le frontend ne dicte jamais le prix. Le serveur recalcule et valide l'ensemble des montants, durées et capacités lors de la soumission AJAX.

---

## 2. ARBORESCENCE COMPLÈTE DU CODEBASE

```text
wp-content/plugins/elite-transfer-booking/
│
├── elite-transfer-booking.php          # Bootstrap principal (Singleton, instanciation, constantes, flush)
│
├── includes/                           # Cœur PHP Métier (Préfixe homogène class-etb-*.php)
│   ├── class-etb-cpt-manager.php       # Déclaration de TOUS les CPTs & colonnes admin
│   ├── class-etb-settings.php          # Page de réglages (Onglets "Général" et "Formulaire")
│   ├── class-etb-meta-manager.php      # Metaboxes Admin (Circuits, Véhicules, Réservations)
│   ├── class-etb-pricing-engine.php    # Calculateur tarifaire autonome (Formule mathématique & BDD)
│   ├── class-etb-ajax.php              # Contrôleur AJAX (Validation, création réservation, e-mails HTML)
│   └── class-etb-shortcode.php         # Gestion des shortcodes [circuit_view] et [tour_booking]
│
├── admin/                              # Assets d'administration (Circuits & Répéteurs)
│   ├── css/
│   │   └── etb-admin.css               # Styles des onglets de départ et de la timeline en admin
│   └── js/
│       └── etb-admin.js                # Répéteur interactif d'options et d'étapes de timeline
│
├── public/                             # Assets Frontend publics
│   ├── css/
│   │   └── booking-widget.css          # Design System tokenisé, layout 2 colonnes, animations et responsive
│   └── js/
│       └── booking-widget.js           # Moteur réactif client (Onglets, sélection cartes, live pricing, Fetch)
│
└── templates/                          # Vues HTML
    ├── circuit-view.php                # Layout d'assemblage (Grille haute + Détails gauche + Sidebar)
    └── booking-form.php                # Formulaire latéral droit de réservation
```

---

## 3. MODÈLE DE DONNÉES & BASE DE DONNÉES (CPT & MÉTADONNÉES)

Le plugin exploite les tables natives `wp_posts` et `wp_postmeta` de WordPress.

### A. Inventaire des Custom Post Types (CPTs)

| CPT | Slug / Identifiant | Visibilité UI | Rôle & Responsabilité |
| :--- | :--- | :--- | :--- |
| **`circuit`** | `circuit` | Public (`/circuits/%slug%/`) | Fiches des circuits touristiques et itinéraires. |
| **`tour_booking`** | `tour_booking` | Privé (Admin seul) | Commandes et dossiers de réservation des clients. |
| **`tour_vehicle`** | `tour_vehicle` | Sous-menu `Tour Booking` | Véhicules de la flotte (capacités, taux horaire). |
| **`tour_extra`** | `tour_extra` | Sous-menu `Tour Booking` | Options additionnelles (siège bébé, champagne, guide...). |
| **`tour_promo`** | `tour_promo` | Sous-menu `Tour Booking` | Codes de réduction promotionnels. |
| **`tour_pickup`** | `tour_pickup` | Masqué | *(Legacy)* Ancien CPT de points de rassemblement. |

---

### B. Dictionnaire Complet des Méta-clés (`wp_postmeta`)

#### 1. Méta-clé du CPT `circuit` :
* **`_circuit_options_data`** *(array sérialisé)* : Dictionnaire de toutes les options de départ associées au circuit :
```php
[
    'opt_wynyfatcn' => [
        'city_name'        => 'Cannes',         // (string) Nom de la ville / onglet
        'duration_hours'   => 4.0,              // (float) Durée du circuit en heures
        'departure_time'   => '09:00',          // (string) Heure conseillée par défaut
        'additional_price' => 0.0,              // (float) Supplément tarifaire pour cette ville
        'badge_1'          => '⏱ 4h d\'excursion',// (string) Badge récapitulatif 1
        'badge_2'          => '📍 Prise en charge hôtel', // (string) Badge 2
        'badge_3'          => '👥 Visite privée',// (string) Badge 3
        'badge_4'          => '🗣 Guide en français', // (string) Badge 4
        'timeline'         => [                 // (array) Étapes du programme
            [ 'time' => '09:00', 'title' => 'Prise en charge', 'desc' => 'Départ de votre hôtel.' ],
            [ 'time' => '10:00', 'title' => 'Le Suquet',       'desc' => 'Visite de la vieille ville.' ]
        ],
        'inclusions'       => "Véhicule privé et chauffeur\nEau à bord", // (string)
        'exclusions'       => "Repas\nBoissons supplémentaires"         // (string)
    ],
    'opt_monaco123' => [ ... ]
]
```

#### 2. Méta-clés du CPT `tour_vehicle` :
* `_etb_hourly_rate` *(float)* : Taux horaire du véhicule en €/h.
* `_etb_base_price` *(float)* : *(Fallback)* Ancien tarif fixe utilisé si le taux horaire vaut `0`.
* `_etb_max_pax` *(int)* : Capacité maximale en passagers.
* `_etb_max_baggage` *(int)* : Capacité maximale en bagages.
* `_etb_allowed_extras` *(array d'IDs)* : Liste des IDs d'extras autorisés pour ce véhicule.

#### 3. Méta-clés du CPT `tour_extra` :
* `_etb_price` *(float)* : Tarif de l'option.
* `_etb_price_type` *(string)* : `fixed` (par réservation), `per_day` (par jour), ou `per_quantity` (par unité).
* `_etb_max_qty` *(int)* : Quantité maximale autorisée.
* `_etb_icon` *(string)* : Classe Dashicons (ex: `dashicons-tag`).

#### 4. Méta-clés du CPT `tour_promo` :
* `_etb_promo_type` *(string)* : `percentage` (%) ou `fixed` (€).
* `_etb_promo_value` *(float)* : Valeur de la réduction.
* `_etb_promo_active` *(string)* : Statut d'activation (`1` = Actif, `0` = Inactif).
* `_etb_promo_code` *(string)* : Code promo en majuscules (ex: `WELCOME10`).

#### 5. Méta-clés du CPT `tour_booking` :
* `_etb_customer_name` *(string)* : Nom complet du client.
* `_etb_customer_email` *(string)* : Adresse e-mail du client.
* `_etb_booking_date` *(string)* : Date souhaitée (`YYYY-MM-DD`).
* `_etb_booking_time` *(string)* : Heure de prise en charge (`HH:MM`).
* `_etb_pickup_address` *(string)* : Adresse de prise en charge saisie.
* `_etb_pickup_id` *(int)* : *(Fallback)* ID de pickup legacy si utilisé.
* `_etb_dropoff_info` *(string)* : Adresse de dépose spécifique (ou vide si identique au départ).
* `_etb_circuit_id` *(int)* : ID du post `circuit` (vaut `0` si transfert standard).
* `_etb_circuit_option_id` *(string)* : Identifiant de l'option choisie (`opt_...`).
* `_etb_duration_hours` *(float)* : Durée exacte facturée au moment de la réservation.
* `_etb_adults` *(int)* : Nombre d'adultes.
* `_etb_children` *(int)* : Nombre d'enfants.
* `_etb_luggage` *(int)* : Nombre total de bagages.
* `_etb_vehicles` *(array)* : Dictionnaire `[ vehicle_id => quantite ]`.
* `_etb_extras` *(array)* : Dictionnaire `[ extra_id => quantite ]`.
* `_etb_note` *(string)* : Demande spéciale ou note du client.
* `_etb_total_price` *(float)* : Montant total final facturé.
* `_etb_pricing_details` *(array)* : Snapshot complet de la décomposition financière.
* `_etb_promo_code` *(string)* : Code promo utilisé.
* `_etb_discount_amount` *(float)* : Montant déduit de la réduction.
* `_etb_status` *(string)* : Statut (`pending`, `confirmed`, `completed`, `cancelled`).

---

### C. Options WordPress Globales (`wp_options`)

* **`etb_general_settings`** *(array)* :
  * `currency` : Symbole de la devise (ex: `€`, `$`, `£`).
  * `min_delay` : Délai minimum avant réservation en heures (ex: `24`).
  * `admin_email` : Adresse e-mail destinataire des notifications de commande.
* **`etb_form_settings`** *(array)* :
  * Dictionnaire de drapeaux booléens (`1` ou `0`) pour afficher ou masquer chaque section du formulaire frontend (`show_vehicle`, `show_adults`, `show_children`, `show_pickup`, `show_extras`, `show_name`, `show_email`, `show_date`, `show_time`, `show_total_bag`, `show_promo`, `show_note`).

---

## 4. MOTEUR DE TARIFICATION (PRICING ENGINE)

Classe : `ETB_Pricing_Engine` (`includes/class-etb-pricing-engine.php`)

### A. La Formule Mathématique Officielle

$$\text{Grand Total} = \left( \sum_{i=1}^{n} (\text{Taux\_Horaire}_i \times \text{Durée\_Circuit} \times \text{Quantité}_i) \right) + \text{Supplément\_Ville} + \text{Total\_Extras} - \text{Remise\_Promo}$$

*Toute valeur vide ou non définie est automatiquement évaluée à `0`.*

---

### B. Méthodes Clés de la Classe

#### 1. `find_circuit_option_data( $option_id, $circuit_id = 0 )`
* **Objectif** : Recherche ciblée et infaillible des données d'une option de circuit.
* **Algorithme** :
  1. Si `$circuit_id > 0` : lecture directe dans `get_post_meta($circuit_id, '_circuit_options_data', true)`.
  2. Si `$circuit_id == 0` : requête SQL directe via `$wpdb->get_results()` sur la table `wp_postmeta` pour retrouver le post `circuit` parent.
* **Retourne** : `array` complet de l'option (`circuit_id`, `circuit_title`, `city_name`, `duration_hours`, `additional_price`, etc.) ou `null`.

#### 2. `get_circuit_option_label( $option_id, $circuit_id = 0, $default_fallback = '' )`
* **Objectif** : Construit le libellé human-readable formaté pour les e-mails et le tableau de bord admin.
* **Format produit** : **`[Titre du Circuit] — Départ : [Nom de la Ville] ([Durée]h)`** *(ex: "Tour Côte d'Azur — Départ : Monaco (9h)")*.

#### 3. `calculate_total( array $data )`
* **Entrées** : Tableau `$data` sanitizé contenant `vehicles`, `option_id`, `circuit_id`, `extras`, `promo`, `pickup_id`.
* **Sorties** : Tableau associatif complet :
  ```php
  [
      'vehicles_total'           => 632.0,
      'pickup_surcharge'         => 0.0,
      'extras_total'             => 104.0,
      'circuit_additional_price' => 0.0,
      'duration_hours'           => 4.0,
      'discount_amount'          => 25.0,
      'promo_code'               => 'PROMO25',
      'grand_total'              => 711.0,
  ]
  ```

---

## 5. GESTIONNAIRE DES CIRCUITS & OPTIONS DYNAMIQUES

Géré par `ETB_Meta_Manager` (`class-etb-meta-manager.php`) et `templates/circuit-view.php`.

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ CIRCUIT : "Tour de la Riviera" (CPT circuit)                                           │
│ ┌────────────────────────────────────────────────────────────────────────────────────┐ │
│ │  Onglet 1 : [ Cannes ]    Onglet 2 : [ Monaco ]    Onglet 3 : [ Nice ]             │ │
│ ├────────────────────────────────────────────────────────────────────────────────────┤ │
│ │  • Durée en heures : 4.0 h          • Heure conseillée : 09:00                     │ │
│ │  • Supplément départ : 0.00 €       • Identifiant unique : opt_a1b2c3d4            │ │
│ │                                                                                    │ │
│ │  🏷 4 Badges Récapitulatifs :                                                       │ │
│ │  [ ⏱ 4h d'excursion ] [ 📍 Départ hôtel ] [ 👥 Visite privée ] [ 🗣 Guide français ] │
│ │                                                                                    │ │
│ │  📍 Programme / Timeline (Répéteur dynamique) :                                     │ │
│ │  ├── 09:00 | Prise en charge | Départ de votre hôtel sur la Riviera                │ │
│ │  ├── 10:00 | Le Suquet       | Visite guidée de la vieille ville                   │ │
│ │  └── 11:30 | La Croisette    | Balade et pause café                                │ │
│ │                                                                                    │ │
│ │  📋 Inclusions & Exclusions :                                                      │ │
│ │  Inclus : Véhicule avec chauffeur, Eau minérale, Guide                             │ │
│ │  Non inclus : Repas, Boissons supplémentaires                                      │ │
│ └────────────────────────────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 6. EXPÉRIENCE UTILISATEUR FRONTEND (SPLIT LAYOUT)

Le gabarit `templates/circuit-view.php` orchestre la mise en page générale en deux zones distinctes :

```text
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│ [ 1. GRILLE HAUTE : CHOISISSEZ VOTRE VÉHICULE (Pleine Largeur) ]                         │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐                     │
│ │ [ ] S class  │ │ [✓] Sprinter │ │ [ ] V class  │ │ [ ] Coach    │  (Cartes Blanches   │
│ │ 79 €/h       │ │ 72 €/h       │ │ 50 €/h       │ │ 30 €/h       │   Sélection Orange  │
│ │ 👤 3  🧳 2   │ │ 👤 20 🧳 10  │ │ 👤 7  🧳 4   │ │ 👤 40 🧳 20  │   Pilule [- 1 +])   │
│ └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘                     │
│                                                                                         │
│ [ 2. SECTION BASSE : LAYOUT 2 COLONNES (Grille 1.4fr / 1fr) ]                           │
│ ┌──────────────────────────────────────────────┐ ┌────────────────────────────────────┐ │
│ │ COLONNE GAUCHE (Détails de l'Itinéraire)     │ │ COLONNE DROITE (Widget Sticky ETB) │ │
│ │                                              │ │ ┌────────────────────────────────┐ │ │
│ │ • Villes de départ : [ Cannes ] [ Monaco ]   │ │ │ À PARTIR DE                    │ │ │
│ │ • 4 Badges (Durée, Langue, Type, Véhicule)   │ │ │ € 288                          │ │ │
│ │                                              │ │ │ passager maximum : 20          │ │ │
│ │ • PROGRAMME / TIMELINE                       │ │ ├────────────────────────────────┤ │ │
│ │   09:00 - Prise en charge à l'hôtel          │ │ │ 👥 NOMBRE DE PASSAGERS         │ │ │
│ │   10:00 - Visite du Château                  │ │ │ Adultes [- 1 +]  Enfants [0]   │ │ │
│ │                                              │ │ │                                │ │ │
│ │ • CE QUI EST INCLUS / NON INCLUS             │ │ │ 📍 ADRESSE DE PRISE EN CHARGE  │ │ │
│ │   ✓ Véhicule privé et chauffeur              │ │ │ [ Ex: Hôtel Martinez... ]      │ │ │
│ │   ✓ Guide                                    │ │ │ [ ] Lieu de dépose différent   │ │ │
│ │   ✗ Repas                                    │ │ │                                │ │ │
│ │                                              │ │ │ ★ OPTIONS SUPPLÉMENTAIRES      │ │ │
│ │                                              │ │ │ [ Siège bébé ] [ Champagne ]   │ │ │
│ │                                              │ │ │                                │ │ │
│ │                                              │ │ │ 👤 COORDONNÉES & DATE          │ │ │
│ │                                              │ │ │                                │ │ │
│ │                                              │ │ │ [ RÉSERVER MAINTENANT ]        │ │ │
│ │                                              │ │ └────────────────────────────────┘ │ │
│ └──────────────────────────────────────────────┴──────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 7. MOTEUR JAVASCRIPT RÉACTIF & ÉVÉNEMENTS

Fichier : `wp-content/plugins/elite-transfer-booking/public/js/booking-widget.js`

Le moteur frontend est encapsulé dans une IIFE en JavaScript Vanilla natif ES6+ (0 dépendance jQuery en frontend).

### Mécanismes Réactifs Clés :

1. **Ordre d'Exécution Sécurisé (Anti-TDZ)** : Les fonctions de calcul (`updateSummary`, `refreshAll`) sont déclarées avant `syncCircuitOption` pour garantir qu'aucune `ReferenceError` ne survienne au chargement.
2. **Gestionnaire de Clic sur les Cartes Véhicules (Toggle 2-Voies)** :
   * Clic sur une carte inactive (`qté = 0`) $\rightarrow$ Activation instantanée (`qté = 1`), image qui rétrécit doucement de 105px à 68px, déploiement de la pilule orange `[- 1 +]` et coche orange active.
   * Clic sur une carte active (`qté >= 1`) $\rightarrow$ Désactivation immédiate (`qté = 0`), rétractation de la pilule et retour de l'image à 105px.
   * Clics sur les boutons `+` / `-` $\rightarrow$ Protégés par `e.stopPropagation()` pour éviter toute désélection involontaire.
3. **Contrôle de Cohérence des Passagers** : Clic sur `+` passagers sans véhicule sélectionné $\rightarrow$ Blocage immédiat et affichage du message d'erreur rouge sous le titre "NOMBRE DE PASSAGERS".
4. **Verrouillage des Dates Passées** : `dateInput.setAttribute('min', todayStr)` bloque les dates antérieures dans le calendrier natif et rejette toute saisie manuelle passée.
5. **Code Promo Interactif avec Loading** : Clic sur "APPLIQUER" $\rightarrow$ Bouton désactivé affichant *"Vérification..."*, validation Fetch AJAX, affichage du feedback stylisé (vert `✓` ou rouge `⚠`) et recalcul immédiat du total.
6. **Auto-Scroll & Reset après Réservation** : À la confirmation réussie de la commande, le script remonte automatiquement avec fluidité (`scrollIntoView({ behavior: 'smooth' })`) au sommet de la page et remet toutes les cartes de véhicules à `0`.

---

## 8. SYSTÈME CSS, DESIGN TOKENS & BOUCLIER ANTI-THÈME

Fichier : `wp-content/plugins/elite-transfer-booking/public/css/booking-widget.css`

### A. Design Tokens (Variables Centralisées)

Toutes les couleurs et dimensions sont personnalisables depuis le bloc `:root` :

```css
:root,
.co-circuit-wrapper,
.etb-booking-widget {
    --etb-primary: #052021;              /* Titres et boutons foncés */
    --etb-primary-dark: #0F172A;         /* Noir ardoise (Titres cartes) */
    --etb-accent: #E65A15;               /* Orange vif (Prix, sélections, boutons) */
    --etb-accent-hover: #D1541F;         /* Orange foncé au survol */
    --etb-accent-glow: rgba(230, 90, 21, 0.18);
    --etb-bg-light: #F8F9FA;             /* Fond doux badges et conteneurs */
    --etb-white: #FFFFFF;                /* Fond des cartes et inputs */
    --etb-border: #E2E8F0;               /* Bordures subtiles standard */
    --etb-border-input: #D1D5DB;         /* Bordures des champs de saisie */
    --etb-text-main: #172326;            /* Texte principal */
    --etb-text-muted: #5F6B6B;           /* Textes secondaires */
    --etb-text-specs: #64748B;           /* Spécifications passagers/bagages */
    --etb-radius-sm: 8px;
    --etb-radius-md: 14px;
    --etb-radius-pill: 50px;
}
```

### B. Bouclier d'Isolation Anti-Thème
* **`box-sizing: border-box !important`** appliqué à 100 % des nœuds enfants.
* **`dashicons` protégé** : `font-family: dashicons !important; display: inline-flex !important; float: none !important; position: static !important;`.
* **Champs de saisie protégés** : `padding-left: 42px !important;` pour que les icônes de calendrier et d'adresse ne chevauchent jamais le texte tapé.
* **Cartes "Photo 1" protégées** : Cartes blanches lumineuses avec courbe d'animation fluide `cubic-bezier(0.25, 1, 0.5, 1)` sur l'image et la pilule orange.

---

## 9. PIPELINE AJAX, VALIDATION & NOTIFICATIONS E-MAIL

Fichier : `wp-content/plugins/elite-transfer-booking/includes/class-etb-ajax.php`

```text
[ FORMULAIRE FRONTEND ]
  │
  ├── 1. Clic sur "Réserver maintenant"
  │      Collecte globale du FormData (véhicules, circuit_id, option_id, coordonnées, date, heure)
  │      Envoi Fetch vers admin-ajax.php avec nonce 'etb_booking_nonce'
  │
  ├── 2. VALIDATION SERVEUR (class-etb-ajax.php) :
  │      ├── Vérification du Nonce : check_ajax_referer()
  │      ├── Contrôle de complétude des champs requis (Nom, E-mail valide, Date, Heure, Adresse)
  │      ├── Contrôle anti-antériorité : rejet si date < date du jour
  │      ├── Contrôle des capacités réelles : passagers ≤ total_max_pax et bagages ≤ total_max_baggage
  │      └── Exécution de ETB_Pricing_Engine::calculate_total()
  │
  ├── 3. PERSISTANCE EN BASE DE DONNÉES :
  │      ├── wp_insert_post( CPT 'tour_booking', statut 'pending' )
  │      └── update_post_meta() pour l'ensemble des 20 méta-clés (_etb_*)
  │
  ├── 4. EXPÉDITION DES NOTIFICATIONS E-MAIL (HTML Responsive) :
  │      ├── Résolution du libellé : ETB_Pricing_Engine::get_circuit_option_label()
  │      ├── E-mail Client : Accusé de réception avec récapitulatif complet et dossier #ID
  │      └── E-mail Administrateur : Notification détaillée de nouvelle commande avec lien direct d'édition
  │
  └── 5. RÉPONSE JSON SUCCESS :
         Affichage du bandeau vert de confirmation avec le numéro de dossier #ID
```

---

## 10. ADMINISTRATION BACK-OFFICE (TABLEAU DE BORD UNIFIÉ)

L'administration WordPress est entièrement rassemblée sous une entrée principale unique :

```text
🚗 Tour Booking
   ├── Toutes les Réservations   (Liste des commandes, statuts colorés, dates, montants)
   ├── Circuits & Tours          (Éditeur de circuits touristiques et répéteur d'onglets)
   ├── Véhicules                 (Gestion de la flotte, images, capacités et taux horaires)
   ├── Options                   (Gestion des extras et tarifs fixes/quantitatifs)
   ├── Codes Promo               (Gestion des remises en % ou montant fixe)
   └── Réglages                  (2 onglets : "Général" et "Configuration du Formulaire")
```

### Fiche Détail Réservation (`render_booking_box`) :
* Encadré Statut dynamique (`⏳ En attente`, `✅ Confirmée`, `🏁 Terminée`, `❌ Annulée`) déclenchant un e-mail automatique au client lors d'un changement d'état.
* Ligne Prestation explicite : **`Prestation : Nom du Circuit — Départ : [Ville] ([Durée]h)`** *(ex: "Venes Circuit 2 — Départ : Oklahoma (9h)")*.
* Décomposition financière détaillée avec prix par véhicule, durée, extras, remises promo et total facturé.

---

## 11. GUIDE D'INTÉGRATION & SHORTCODES

### 1. `[circuit_view id="XX"]`
* **Rôle** : Affiche la vue complète en 2 colonnes du circuit spécifié (Grille haute des véhicules + Détails de l'itinéraire + Formulaire latéral).
* **Utilisation** :
  * Sur une Page standard WordPress / Elementor : `[circuit_view id="85"]` *(où 85 est l'ID du circuit)*.
  * Automatique : Tout post de type `circuit` affiche cette vue nativement sur son URL `/circuits/%slug%/`.

### 2. `[tour_booking]`
* **Rôle** : Affiche le formulaire latéral de réservation seul (avec en-tête de prix, passagers, prise en charge, extras, coordonnées et récapitulatif).
* **Utilisation** : `[tour_booking]` dans un widget de barre latérale ou une page dédiée.

---

## 12. ROADMAP TECHNIQUE & ÉVOLUTIONS FUTURES

L'architecture v1.0.3 a été conçue pour accueillir directement les extensions suivantes :

### 1. Intégration Cartographique & Autocomplétion (Phase Carte)
* **Solutions cibles** : Leaflet + OpenStreetMap ou API Geoapify.
* **Fonctionnalités prêtes** : Le champ libre `etb_pickup_address` et le conteneur `etb_dropoff_info` accueilleront l'autocomplétion prédictive d'adresses et la conversion en coordonnées géographiques (`latitude`, `longitude`, `place_id`).

### 2. Passerelles de Paiement en Ligne
* **Solutions cibles** : Stripe Elements / PayPal SDK.
* **Fonctionnalités prêtes** : Déclenchement de la session de paiement sécurisé lors de la soumission AJAX avec bascule automatique du statut de `pending` à `confirmed` après validation du webhook de paiement.

### 3. API REST & Webhooks pour Applications Mobiles / Dispatch Chauffeurs
* **Endpoints à exposer** :
  * `GET /wp-json/etb/v1/circuits` : Catalogue des circuits et villes de départ.
  * `GET /wp-json/etb/v1/vehicles` : Liste des véhicules et taux horaires.
  * `POST /wp-json/etb/v1/bookings` : Création de réservations depuis une application mobile externe.
  * `PATCH /wp-json/etb/v1/bookings/{id}` : Mise à jour du statut par un chauffeur en mission.
* **Flux iCal** : Exportation de flux calendrier `.ics` pour synchronisation automatique des courses dans Google Calendar ou Apple Calendar.

---

*Fin du document officiel de référence technique — **Elite Transfer Booking v1.0.3**.*