# 📖 DOCUMENTATION TECHNIQUE & FONCTIONNELLE OFFICIELLE
# Elite Transfer Booking (Unified) — Version 1.1.0

---

## 📌 FICHE D'IDENTITÉ DU PROJET

| Propriété | Spécification officielle |
| :--- | :--- |
| **Nom de l'extension** | **Elite Transfer Booking (Unified)** |
| **Identifiant / Text Domain** | `elite-transfer-booking` |
| **Version actuelle** | **`1.1.0`** *(Version consolidée, sécurisée, autonome et nettoyée)* |
| **Dépôts de référence** | [https://github.com/kaiser-em/tour_booking](https://github.com/kaiser-em/tour_booking) / [https://github.com/kaiser-em/etb](https://github.com/kaiser-em/etb) |
| **Auteur** | Kaiser EM |
| **Type de solution** | Moteur WordPress complet, 100 % autonome, dédié à la réservation d'excursions touristiques privatisées, circuits multi-villes et transferts VTC VIP |
| **Compatibilité WP / PHP** | WordPress 5.8+ (testé 6.x+) / PHP 7.4, 8.0, 8.1, 8.2+ |
| **Dépendances externes** | **0 dépendance** (Vanilla JS ES6+ natif, Dashicons, CSS3 tokenisé, Fetch API, WordPress APIs) |

---

## 📑 TABLE DES MATIÈRES

1. [Vue d'Ensemble & Périmètre Métier](#1-vue-densemble--périmètre-métier)
2. [Arborescence Réelle du Codebase](#2-arborescence-réelle-du-codebase)
3. [Modèle de Données & Base de Données (CPTs & Métadonnées)](#3-modèle-de-données--base-de-données-cpts--métadonnées)
4. [Moteur de Tarification (ETB_Pricing_Engine)](#4-moteur-de-tarification-etb_pricing_engine)
5. [Architecture de Sécurité & Anti-Spam (ETB_Security)](#5-architecture-de-sécurité--anti-spam-etb_security)
6. [Gestionnaire des Circuits & Options de Départ](#6-gestionnaire-des-circuits--options-de-départ)
7. [Expérience Frontend (Split Layout & Design Photo 1)](#7-expérience-frontend-split-layout--design-photo-1)
8. [Moteur JavaScript Réactif (`booking-widget.js`)](#8-moteur-javascript-réactif-booking-widgetjs)
9. [Système CSS, Design Tokens & Bouclier Anti-Thème](#9-système-css-design-tokens--bouclier-anti-thème)
10. [Pipeline AJAX & Notifications E-mail](#10-pipeline-ajax--notifications-e-mail)
11. [Administration Back-Office WordPress](#11-administration-back-office-wordpress)
12. [Guide des Shortcodes & Intégration](#12-guide-des-shortcodes--intégration)
13. [État de Nettoyage & Roadmap Immédiate](#13-état-de-nettoyage--roadmap-immédiate)

---

## 1. VUE D'ENSEMBLE & PÉRIMÈTRE MÉTIER

L'extension **Elite Transfer Booking (v1.1.0)** est l'aboutissement de l'unification technique d'ETB et de Circuit Options. Elle ne dépend plus d'aucun second plugin.

### Les 5 Piliers Métier de la Solution :
1. **Flotte Multi-Véhicules & Facturation Horaire** : Sélection interactive de véhicules avec quantités multiples, calcul du coût au prorata de la durée réelle de l'excursion (`Taux horaire × Heures × Quantité`), et validation stricte des capacités en passagers et bagages.
2. **Circuits & Villes de Départ Multiples** : Gestion d'itinéraires avec choix de ville de départ (chacune disposant de sa durée propre, de son supplément éventuel, de ses 4 badges, de sa timeline dynamique recalculée et de ses inclusions/exclusions).
3. **Ergonomie "Split Layout" (Design Photo 1)** : Grille supérieure de véhicules en cartes blanches lumineuses avec sélection réactive au clic, colonne gauche pour les détails du circuit, et colonne droite sticky pour la réservation en direct.
4. **Sécurité Native & Anti-Spam Triangulaire** : Protection invisible pour l'utilisateur sans CAPTCHA intrusif (Honeypot, vérification de vélocité par jeton d'horodatage signé, et Rate Limiting par Transients IP non bloquant pour les réseaux partagés).
5. **Autonomie Absolue** : Aucun résidu de code tiers orphelin. Le système fonctionne de manière fluide, légère et isolée.

---

## 2. ARBORESCENCE RÉELLE DU CODEBASE

Le plugin est structuré selon les standards WordPress officiels les plus stricts :

```text
wp-content/plugins/elite-transfer-booking/
│
├── elite-transfer-booking.php          # Bootstrap orchestrateur (Singleton, constantes, activation, flush)
│
├── includes/                           # Modules PHP métier (Préfixe homogène class-etb-*.php)
│   ├── class-etb-cpt-manager.php       # Enregistrement de TOUS les CPTs & colonnes d'administration
│   ├── class-etb-settings.php          # Réglages (Onglets "Général" et "Configuration du Formulaire")
│   ├── class-etb-security.php          # Module de sécurité (Honeypot, Timestamp signé, Rate Limiting)
│   ├── class-etb-pricing-engine.php    # Calculateur tarifaire autonome (Recherche BDD et formule horaire)
│   ├── class-etb-meta-manager.php      # Metaboxes Admin (Éditeur Circuits, Véhicules, Fiche Réservation)
│   ├── class-etb-ajax.php              # Contrôleur AJAX (Validation stricte, création réservation, e-mails)
│   └── class-etb-shortcode.php         # Gestion des shortcodes [circuit_view] et [tour_booking]
│
├── admin/                              # Assets d'administration
│   ├── css/
│   │   └── etb-admin.css               # Styles du gestionnaire d'onglets de circuits en back-office
│   └── js/
│       └── etb-admin.js                # Répéteur interactif d'onglets et de timeline dans l'admin
│
├── public/                             # Assets Frontend unifiés
│   ├── css/
│   │   └── booking-widget.css          # Design System tokenisé, layout 2 colonnes, animations Photo 1
│   └── js/
│       └── booking-widget.js           # Moteur réactif client (Onglets, sélection cartes, live pricing, Fetch)
│
└── templates/                          # Gabarits de vues HTML
    ├── circuit-view.php                # Layout principal (Grille haute + Détails gauche + Sidebar)
    └── booking-form.php                # Formulaire latéral droit de réservation
```

---

## 3. MODÈLE DE DONNÉES & BASE DE DONNÉES (CPTS & MÉTADONNÉES)

### A. Les 6 Custom Post Types (CPTs)

| CPT | Identifiant | Visibilité / Menu | Rôle & Description |
| :--- | :--- | :--- | :--- |
| **Circuit** | `circuit` | Public (`/circuits/%slug%/`) | Fiches des circuits touristiques, options de départ et programmes. |
| **Réservation** | `tour_booking` | Menu `Tour Booking` | Commandes et dossiers de réservation passés par les clients. |
| **Véhicule** | `tour_vehicle` | Sous-menu `Tour Booking` | Flotte de véhicules (taux horaires, passagers et bagages max). |
| **Option / Extra** | `tour_extra` | Sous-menu `Tour Booking` | Services additionnels (Siège bébé, Champagne, Guide privé...). |
| **Code Promo** | `tour_promo` | Sous-menu `Tour Booking` | Coupons de réduction (% ou montant fixe) avec statut actif/inactif. |
| **Point de départ** | `tour_pickup` | Masqué | *(Legacy)* Ancien CPT technique conservé pour rétrocompatibilité. |

---

### B. Dictionnaire Complet des Méta-clés (`wp_postmeta`)

#### 1. Méta-clé du CPT `circuit` :
* **`_circuit_options_data`** *(array sérialisé)* : Contient toutes les options de départ associées au circuit :
  * `city_name` *(string)* : Nom de la ville de départ (ex: `Cannes`).
  * `duration_hours` *(float)* : Durée du circuit en heures (ex: `4.0`, `6.5`).
  * `departure_time` *(string)* : Heure conseillée par défaut (format `HH:MM`).
  * `additional_price` *(float)* : Supplément tarifaire éventuel pour cette ville.
  * `badge_1` à `badge_4` *(string)* : Textes des 4 badges récapitulatifs.
  * `timeline` *(array)* : Liste ordonnée des étapes `[ ['time' => '09:00', 'title' => '...', 'desc' => '...'], ... ]`.
  * `inclusions` / `exclusions` *(string)* : Éléments inclus et non inclus (ligne par ligne).

#### 2. Méta-clés du CPT `tour_vehicle` :
* `_etb_hourly_rate` *(float)* : Taux horaire du véhicule ($/h ou €/h).
* `_etb_base_price` *(float)* : *(Fallback)* Ancien tarif fixe utilisé si le taux horaire vaut `0`.
* `_etb_max_pax` *(int)* : Nombre maximal de passagers autorisés.
* `_etb_max_baggage` *(int)* : Nombre maximal de bagages autorisés.
* `_etb_allowed_extras` *(array d'IDs)* : IDs des options autorisées pour ce véhicule.

#### 3. Méta-clés du CPT `tour_extra` :
* `_etb_price` *(float)* : Prix unitaire de l'option.
* `_etb_price_type` *(string)* : `fixed` (par réservation), `per_day` (par jour), `per_quantity` (par unité).
* `_etb_max_qty` *(int)* : Quantité maximale sélectionnable.
* `_etb_icon` *(string)* : Classe Dashicons (ex: `dashicons-tag`).

#### 4. Méta-clés du CPT `tour_promo` :
* `_etb_promo_type` *(string)* : `percentage` (%) ou `fixed` ($/€).
* `_etb_promo_value` *(float)* : Valeur de la remise.
* `_etb_promo_active` *(string)* : Statut d'activation (`1` = Actif, `0` = Inactif).
* `_etb_promo_code` *(string)* : Code promo normalisé en majuscules.

#### 5. Méta-clés du CPT `tour_booking` (Dossier de Commande) :
* `_etb_customer_name` *(string)* : Nom complet du client.
* `_etb_customer_email` *(string)* : E-mail du client.
* `_etb_booking_date` *(string)* : Date souhaitée (`YYYY-MM-DD`).
* `_etb_booking_time` *(string)* : Heure de départ (`HH:MM`).
* `_etb_pickup_address` *(string)* : Adresse de prise en charge saisie.
* `_etb_pickup_id` *(int)* : *(Fallback)* ID de pickup legacy si utilisé.
* `_etb_dropoff_info` *(string)* : Adresse de dépose spécifique (ou vide si identique au départ).
* `_etb_circuit_id` *(int)* : ID du post `circuit` lié (`0` si transfert standard).
* `_etb_circuit_option_id` *(string)* : Identifiant unique de l'option choisie (`opt_...`).
* `_etb_duration_hours` *(float)* : Durée exacte facturée.
* `_etb_adults` *(int)* : Nombre d'adultes.
* `_etb_children` *(int)* : Nombre d'enfants.
* `_etb_luggage` *(int)* : Nombre total de bagages.
* `_etb_vehicles` *(array)* : Dictionnaire `[ vehicle_id => quantite ]`.
* `_etb_extras` *(array)* : Dictionnaire `[ extra_id => quantite ]`.
* `_etb_note` *(string)* : Demande spéciale ou note du client.
* `_etb_total_price` *(float)* : Montant total final facturé.
* `_etb_pricing_details` *(array)* : Snapshot complet de la décomposition financière.
* `_etb_promo_code` *(string)* : Code promo appliqué.
* `_etb_discount_amount` *(float)* : Montant de la remise déduite.
* `_etb_status` *(string)* : Statut (`pending`, `confirmed`, `completed`, `cancelled`).

---

### C. Options WordPress Globales (`wp_options`)

* **`etb_general_settings`** *(array)* :
  * `currency` : Symbole de la devise configuré (ex: `$`, `€`).
  * `min_delay` : Délai minimum avant réservation en heures (ex: `24`).
  * `admin_email` : E-mail destinataire des notifications de commande.
* **`etb_form_settings`** *(array)* :
  * Drapeaux booléens pour afficher/masquer chaque champ dans le formulaire frontend (`show_vehicle`, `show_adults`, `show_children`, etc.).

---

## 4. MOTEUR DE TARIFICATION (ETB_PRICING_ENGINE)

Classe : `ETB_Pricing_Engine` (`includes/class-etb-pricing-engine.php`)

### A. La Formule Mathématique Officielle

$$\text{Montant Total} = \left( \sum_{i=1}^{n} (\text{Taux\_Horaire}_i \times \text{Durée\_Circuit} \times \text{Quantité}_i) \right) + \text{Supplément\_Ville} + \text{Total\_Extras} - \text{Remise\_Promo}$$

### B. Mécanismes d'Exécution :
1. **Autonomie BDD** : La méthode `find_circuit_option_data($option_id, $circuit_id)` lit directement les données de durée et de supplément dans `_circuit_options_data` (avec lecture ciblée par `circuit_id` et fallback SQL sécurisé par `$wpdb->prepare()`).
2. **Sécurité financière absolue** : Aucun montant envoyé par le navigateur n'est accepté. Le serveur recharge tous les taux horaires des véhicules et les prix des extras directement depuis la base de données.
3. **Contrôle d'activation promo** : Si un code promo a `_etb_promo_active = '0'`, le moteur refuse d'appliquer la réduction même si le code est envoyé.
4. **Plafonnement des quantités** : Les véhicules sont bornés à un maximum de 50 et les extras à 20 pour éviter tout débordement numérique.

---

## 5. ARCHITECTURE DE SÉCURITÉ & ANTI-SPAM (ETB_SECURITY)

Classe : `ETB_Security` (`includes/class-etb-security.php`)

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                REQUÊTE POST ENTRANTE                                   │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
                    [ ÉTAPE 1 : VÉRIFICATION DU NONCE CSRF ]
                    check_ajax_referer( 'etb_booking_nonce', 'nonce' )
                                            │
                                            ▼
                    [ ÉTAPE 2 : CONTRÔLE DU CHAMP HONEYPOT ]
                    ETB_Security::verify_honeypot( 'etb_hp_email' )
                    • Si rempli (Bot détecté) ──► REJET IMMÉDIAT
                                            │
                                            ▼
                    [ ÉTAPE 3 : CONTRÔLE DE VÉLOCITÉ (TIMESTAMP SIGNÉ) ]
                    ETB_Security::verify_timestamp_token( $time, $token, 2, 86400 )
                    • Vérifie la signature cryptographique wp_hash()
                    • Si soumis en < 2 secondes (Bot script) ──► REJET IMMÉDIAT
                    • Si formulaire ouvert depuis > 24h ──────► REJET IMMÉDIAT
                                            │
                                            ▼
                    [ ÉTAPE 4 : RATE LIMITING DYNAMIQUE (TRANSIENTS) ]
                    ETB_Security::check_rate_limit( 'booking', 10, 600 )
                    • Limite Réservation : 10 réservations / 10 minutes par IP
                    • Limite Promo       : 15 échecs / 10 minutes par IP
                                            │
                                            ▼
                    [ ÉTAPE 5 : VALIDATION MÉTIER & SANITIZATION ]
                    • Plafonnement des quantités (Véhicules max 50, Extras max 20)
                    • Validation stricte des dates (Rejet des dates passées)
                    • Recalcul 100% serveur du prix par ETB_Pricing_Engine
```

---

## 6. GESTIONNAIRE DES CIRCUITS & OPTIONS DYNAMIQUES

Géré par `ETB_Meta_Manager` (`class-etb-meta-manager.php`) dans **Circuits & Tours**.

Chaque fiche de circuit permet de configurer une infinité d'options de départ grâce à un gestionnaire d'onglets dynamique :
* **Identifiant unique automatique** : Chaque option génère un ID unique (`opt_a1b2c3d4`) évitant toute collision entre deux circuits distincts.
* **Paramètres de liaison** : Ville de départ, Durée en heures (décimale acceptée : `1.75`, `4.0`, `6.5`), Heure conseillée par défaut, Supplément financier.
* **Contenu éditorial** : 4 Badges récapitulatifs structurés, Programme/Timeline par étapes (Horaire, Titre, Description), Listes des éléments Inclus (✓) et Non Inclus (✕).
* **Affichage dans l'onglet de départ** : Si la ville a un supplément de prix, son onglet affiche automatiquement son coût additionnel (ex: `Cannes (+50 $)`).

---

## 7. EXPÉRIENCE FRONTEND (SPLIT LAYOUT & DESIGN PHOTO 1)

Gabarit : `templates/circuit-view.php`

```text
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│ [ 1. GRILLE HAUTE : CHOISISSEZ VOTRE VÉHICULE (Pleine Largeur) ]                         │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐                     │
│ │ [ ] S class  │ │ [✓] Sprinter │ │ [ ] V class  │ │ [ ] Coach    │  (Cartes Blanches   │
│ │ 79 $ /h      │ │ 72 $ /h      │ │ 50 $ /h      │ │ 30 $ /h      │   Sélection Orange  │
│ │ 👤 3  🧳 2   │ │ 👤 20 🧳 10  │ │ 👤 7  🧳 4   │ │ 👤 40 🧳 20  │   Pilule [- 1 +])   │
│ └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘                     │
│                                                                                         │
│ [ 2. SECTION BASSE : LAYOUT 2 COLONNES (Grille 1.4fr / 1fr) ]                           │
│ ┌──────────────────────────────────────────────┐ ┌────────────────────────────────────┐ │
│ │ COLONNE GAUCHE (Détails de l'Itinéraire)     │ │ COLONNE DROITE (Widget Sticky ETB) │ │
│ │                                              │ │ ┌────────────────────────────────┐ │ │
│ │ • Villes de départ : [ Cannes ] [ Monaco ]   │ │ │ $ 288                          │ │ │
│ │ • 4 Badges (Durée, Langue, Type, Véhicule)   │ │ │ passager maximum : 20          │ │ │
│ │                                              │ │ ├────────────────────────────────┤ │ │
│ │ • PROGRAMME / TIMELINE (Heures dynamiques)   │ │ │ 👥 NOMBRE DE PASSAGERS         │ │ │
│ │   08:00 - Prise en charge à l'hôtel          │ │ │ Adultes [- 1 +]  Enfants [0]   │ │ │
│ │   10:00 - Visite du Château                  │ │ │                                │ │ │
│ │                                              │ │ │ 📍 ADRESSE DE PRISE EN CHARGE  │ │ │
│ │ • CE QUI EST INCLUS / NON INCLUS             │ │ │ [ Ex: Hôtel des Thermes... ]   │ │ │
│ │   ✓ Véhicule privé et chauffeur              │ │ │ [ ] Lieu de dépose différent   │ │ │
│ │   ✓ Guide                                    │ │ │                                │ │ │
│ │   ✗ Repas                                    │ │ │ ★ OPTIONS SUPPLÉMENTAIRES      │ │ │
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

## 8. MOTEUR JAVASCRIPT RÉACTIF (`booking-widget.js`)

Fichier : `wp-content/plugins/elite-transfer-booking/public/js/booking-widget.js`

Le script est encapsulé dans une IIFE en JavaScript Vanilla natif ES6+ (0 dépendance jQuery en frontend).

### Fonctionnalités Réactives Majeures :
1. **Ordre d'Initialisation Sécurisé (Anti-TDZ)** : Les fonctions de calcul (`updateSummary`, `refreshAll`) sont déclarées avant `syncCircuitOption` pour éliminer toute erreur `ReferenceError` au chargement.
2. **Sélection / Désélection 2-Voies des Véhicules** :
   * Clic sur une carte inactive (`qté = 0`) $\rightarrow$ Activation instantanée (`qté = 1`), image qui rétrécit doucement de 105px à 68px, apparition de la pilule orange et de la coche avec rebond élastique.
   * Clic sur une carte déjà active (`qté >= 1`) $\rightarrow$ Remise à `0` et désélection immédiate.
   * Clics sur les boutons `+` / `-` $\rightarrow$ Protégés par `e.stopPropagation()` pour éviter toute désélection involontaire.
3. **Timeline Dynamique Relative à l'Heure de Départ (`updateTimelineTimes`)** :
   * Si le client (ou l'option) modifie l'heure de départ (ex: passe de `09:00` à `08:00`), le script calcule le delta en minutes et **décale automatiquement chaque étape horaire de la timeline en temps réel** !
4. **Synchronisation Synchrone des Villes** : Clic sur un onglet de ville $\rightarrow$ Bascule instantanée de la timeline, mise à jour de `state.circuit`, injection des champs cachés `etb_circuit_id` et `etb_option_id`, et recalcul immédiat du prix.
5. **Verrouillage Anti-Antériorité** : `dateInput.setAttribute('min', todayStr)` bloque les dates passées dans le calendrier natif.
6. **Avertissement Passagers sans Véhicule** : Clic sur `+` passagers sans véhicule choisi $\rightarrow$ Blocage et affichage du message rouge d'erreur sous le titre "NOMBRE DE PASSAGERS".
7. **Code Promo Réactif avec État de Chargement** : Clic sur "APPLIQUER" $\rightarrow$ Bouton affichant *"Vérification..."*, validation AJAX et feedback stylisé (vert `✓` succès, rouge `⚠` erreur).
8. **Auto-Scroll & Reset après Commande** : À la confirmation de réservation, la fenêtre remonte automatiquement en douceur (`scrollIntoView({ behavior: 'smooth' })`) au sommet de la page et remet toutes les cartes de véhicules à zéro.

---

## 9. SYSTÈME CSS, DESIGN TOKENS & BOUCLIER ANTI-THÈME

Fichier : `wp-content/plugins/elite-transfer-booking/public/css/booking-widget.css`

### A. Variables CSS Centralisées (`:root`)

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

### B. Bouclier Anti-Thème :
* `box-sizing: border-box !important` forcé sur 100 % des balises internes.
* Protection stricte de `font-family: dashicons !important; display: inline-flex !important; float: none !important; position: static !important;`.
* `padding-left: 42px !important` sur les champs avec icônes (évite que le texte saisi ne chevauche les icônes).
* Dimensions strictes `255px` avec transitions continues `cubic-bezier(0.25, 1, 0.5, 1)` sur les cartes pour éliminer tout saut visuel.

---

## 10. PIPELINE AJAX & NOTIFICATIONS E-MAIL

Classe : `ETB_Ajax` (`includes/class-etb-ajax.php`)

### Traitement de la Soumission (`etb_submit_booking`) :
1. **Contrôles de Sécurité** : Vérification Nonce + Vérification Honeypot + Vérification Timestamp vélocité + Vérification Rate Limiting IP.
2. **Validation Métier** : Nom, Email valide, Date non passée, Heure, Adresse, Véhicules sélectionnés (`array_sum > 0`), Capacités passagers et bagages respectées.
3. **Calcul & Insertion** : Recalcul par `ETB_Pricing_Engine`, création du post `tour_booking` (statut `pending`), enregistrement des métadonnées `_etb_*`.
4. **Formatage du Libellé** : Génération de **`Prestation : Nom du Circuit — Départ : [Ville] ([Durée]h)`**.
5. **Expédition E-mail** : Envoi au client et à l'administrateur via `wp_mail()` avec en-têtes `Reply-To` nettoyés.
6. **Réponse JSON** : Renvoi du numéro de dossier `#ID` pour affichage du bandeau vert de confirmation.

---

## 11. ADMINISTRATION BACK-OFFICE WORDPRESS

Le menu d'administration est unifié sous une seule entrée principale :

```text
🚗 Tour Booking
   ├── Toutes les Réservations   (Liste des commandes, statuts colorés, dates, montants)
   ├── Circuits & Tours          (Éditeur des fiches circuits et répéteur d'options)
   ├── Véhicules                 (Gestion de la flotte, images, capacités et taux horaires)
   ├── Options                   (Gestion des extras et types de tarification)
   ├── Codes Promo               (Gestion des remises fixes/% et statuts actif/inactif)
   └── Réglages                  (2 onglets : "Général" et "Configuration du Formulaire")
```

### Fiche Détail Réservation (`render_booking_box`) :
* Encadré de statut (`⏳ En attente`, `✅ Confirmée`, `🏁 Terminée`, `❌ Annulée`) déclenchant un e-mail automatique au client lors d'une modification.
* Ligne Prestation explicite : **`Prestation : Nom du Circuit — Départ : [Ville] ([Durée]h)`** *(ex: "Un Circuits — Départ : Cannes (4h)")*.
* **Grand bloc en bas "🗺️ Programme & Détails du Circuit"** : affiche les badges, les inclusions/exclusions et la **timeline dont les heures sont automatiquement recalculées** en fonction de l'heure réservée par le client.
* Décomposition financière complète au centime près.

---

## 12. GUIDE DES SHORTCODES & INTÉGRATION

### 1. `[circuit_view id="XX"]`
* **Rôle** : Affiche la vue complète en split layout (Grille haute des véhicules + Détails itinéraire à gauche + Formulaire de réservation sticky à droite).
* **Utilisation** :
  * Dans une Page standard / Elementor : `[circuit_view id="85"]` *(où 85 est l'ID du circuit)*.
  * Automatique : Tout post de type `circuit` affiche cette vue nativement sur son URL `/circuits/%slug%/`.

### 2. `[tour_booking]`
* **Rôle** : Affiche le formulaire latéral seul (En-tête prix, Passagers, Prise en charge, Extras, Coordonnées, Récapitulatif).
* **Utilisation** : Dans une barre latérale ou une page de transfert direct.

---

## 13. ÉTAT DE NETTOYAGE & ROADMAP IMMÉDIATE

### A. État de Nettoyage (OctopusPro)
* ✅ Le fichier `class-etb-octopus.php` a été **supprimé**.
* ✅ Les appels d'envoi en arrière-plan dans `class-etb-ajax.php` ont été **supprimés**.
* ✅ Les champs d'options dans `class-etb-settings.php` ont été **supprimés**.
* ✅ L'affichage de badge dans `class-etb-meta-manager.php` a été **supprimé**.
* 👉 **Le code source est 100 % sain, propre et sans dépendances orphelines.**

### B. Feuille de Route Immédiate (Prochaine Étape) :
* **Intégration Chauffeur / Dispatch LimoExpress** :
  * Endpoint validé : `PUT https://api.limoexpress.me/api/integration/bookings/` (Status 201 Created).
  * Création du connecteur dédié `class-etb-limoexpress.php` basé sur la matrice de correspondance validée (`from_location`, `to_location`, `start`, `end`, `duration`, `price`, `passenger_count`, `suitcase_count`, `baby_seat_count`, `checkpoints`).

---

*Documentation technique officielle et exhaustive — **Elite Transfer Booking v1.1.0** — Source de vérité absolue.*