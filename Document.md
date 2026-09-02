# 📖 DOCUMENTATION TECHNIQUE & FONCTIONNELLE OFFICIELLE
# Elite Transfer Booking (Unified) — Version 1.0.3

---

## 📌 FICHE D'IDENTITÉ DU PROJET

| Propriété | Spécification |
| :--- | :--- |
| **Nom de l'extension** | **Elite Transfer Booking (Unified)** |
| **Identifiant / Text Domain** | `elite-transfer-booking` |
| **Version actuelle** | **`1.0.3`** |
| **Dépôt GitHub** | [https://github.com/kaiser-em/tour_booking](https://github.com/kaiser-em/tour_booking) |
| **Auteur** | Kaiser EM |
| **Type de solution** | Moteur WordPress complet et autonome de réservation pour excursions, circuits touristiques et transferts privés |
| **Compatibilité WP / PHP** | WordPress 5.8+ (testé 6.x+) / PHP 7.4, 8.0, 8.1, 8.2+ |
| **Dépendances externes** | **0 dépendance** (Pur Vanilla JS ES6+, Dashicons natifs, CSS3 tokenisé, Fetch API, WordPress APIs) |

---

## 📑 TABLE DES MATIÈRES

1. [Présentation & Objectifs Métier](#1-présentation--objectifs-métier)
2. [Arborescence Officielle du Codebase](#2-arborescence-officielle-du-codebase)
3. [Modèle de Données & Base de Données (CPTs & Métas)](#3-modèle-de-données--base-de-données-cpts--métas)
4. [Architecture de Sécurité & Anti-Spam (Module ETB_Security)](#4-architecture-de-sécurité--anti-spam-module-etb_security)
5. [Moteur de Tarification (Pricing Engine)](#5-moteur-de-tarification-pricing-engine)
6. [Gestionnaire des Circuits & Options Dynamiques](#6-gestionnaire-des-circuits--options-dynamiques)
7. [Expérience Utilisateur Frontend (Split Layout & Design Photo 1)](#7-expérience-utilisateur-frontend-split-layout--design-photo-1)
8. [Moteur JavaScript Réactif (Client Engine)](#8-moteur-javascript-réactif-client-engine)
9. [Système CSS, Design Tokens & Bouclier Anti-Thème](#9-système-css-design-tokens--bouclier-anti-thème)
10. [Pipeline AJAX & Notifications E-mail](#10-pipeline-ajax--notifications-e-mail)
11. [Administration Back-Office WordPress](#11-administration-back-office-wordpress)
12. [Guide des Shortcodes & Intégration](#12-guide-des-shortcodes--intégration)
13. [Roadmap Technique & Évolutions Futures](#13-roadmap-technique--évolutions-futures)

---

## 1. PRÉSENTATION & OBJECTIFS MÉTIER

**Elite Transfer Booking (v1.0.3)** est un système de réservation unifié combinant la puissance d'un moteur de tarification horaire par véhicule et la flexibilité d'un gestionnaire de circuits touristiques multi-villes.

### Les 4 Fondations du Système :
1. **Flotte Multi-Véhicules & Taux Horaires** : Sélection interactive de véhicules avec quantités multiples, calcul dynamique basé sur la durée réelle de l'excursion, et contrôle strict des capacités maximales (passagers et bagages).
2. **Gestionnaire de Circuits Multi-Villes** : Création d'itinéraires touristiques proposant plusieurs villes de départ (chacune disposant de sa durée propre, de son éventuel supplément financier, de son programme/timeline et de ses inclusions).
3. **Architecture "Split Layout" Épurée** : Grille supérieure de sélection de véhicules (design blanc lumineux "Photo 1" avec sélecteur orange) et mise en page inférieure à 2 colonnes (Détails du circuit à gauche, Formulaire sticky à droite).
4. **Sécurité Native & Anti-Spam** : Protection triple couche (Honeypot invisible, jeton temporel cryptographique, Rate Limiting souple par Transients IP) garantissant la protection contre les bots sans bloquer les clients légitimes (hôtels, réseaux Wi-Fi).

---

## 2. ARBORESCENCE OFFICIELLE DU CODEBASE

```text
wp-content/plugins/elite-transfer-booking/
│
├── elite-transfer-booking.php          # Point d'entrée principal (Singleton, Constantes, Initialisation, Flush)
│
├── includes/                           # Modules PHP métier (Nomenclature homogène class-etb-*.php)
│   ├── class-etb-cpt-manager.php       # Enregistrement de TOUS les CPTs & gestion des colonnes d'administration
│   ├── class-etb-settings.php          # Réglages (Onglets "Général" et "Configuration du Formulaire")
│   ├── class-etb-security.php          # Module utilitaire de sécurité (Honeypot, Timestamp, Rate Limiting)
│   ├── class-etb-meta-manager.php      # Metaboxes Admin (Éditeur Circuits, Véhicules, Fiche Réservation)
│   ├── class-etb-pricing-engine.php    # Calculateur tarifaire autonome (Formule horaire & lecture BDD)
│   ├── class-etb-ajax.php              # Contrôleur AJAX (Validation stricte, création réservation, e-mails)
│   └── class-etb-shortcode.php         # Gestion des shortcodes [circuit_view] et [tour_booking]
│
├── admin/                              # Assets d'administration (Circuits & Répéteurs)
│   ├── css/
│   │   └── etb-admin.css               # Styles des onglets de départ et de la timeline en admin
│   └── js/
│       └── etb-admin.js                # Répéteur interactif d'options et d'étapes de timeline
│
├── public/                             # Assets Frontend unifiés
│   ├── css/
│   │   └── booking-widget.css          # Design System tokenisé, layout 2 colonnes, animations et responsive
│   └── js/
│       └── booking-widget.js           # Moteur client réactif (Onglets, sélection cartes, live pricing, Fetch)
│
└── templates/                          # Gabarits de vues HTML
    ├── circuit-view.php                # Layout d'assemblage (Grille haute + Détails gauche + Sidebar)
    └── booking-form.php                # Formulaire latéral droit de réservation
```

---

## 3. MODÈLE DE DONNÉES & BASE DE DONNÉES (CPTS & MÉTAS)

Toutes les entités s'appuient sur les tables standards `wp_posts` et `wp_postmeta`.

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
* **`_circuit_options_data`** *(array sérialisé)* : Contient toutes les options de départ du circuit :
  * `city_name` *(string)* : Nom de la ville de départ (ex: `Cannes`).
  * `duration_hours` *(float)* : Durée du circuit en heures (ex: `4.0`, `6.5`).
  * `departure_time` *(string)* : Heure conseillée par défaut (format `HH:MM`).
  * `additional_price` *(float)* : Supplément financier éventuel de la ville.
  * `badge_1` à `badge_4` *(string)* : Textes des 4 badges récapitulatifs.
  * `timeline` *(array)* : Liste ordonnée des étapes `[ ['time' => '09:00', 'title' => '...', 'desc' => '...'], ... ]`.
  * `inclusions` / `exclusions` *(string)* : Éléments inclus et non inclus (ligne par ligne).

#### 2. Méta-clés du CPT `tour_vehicle` :
* `_etb_hourly_rate` *(float)* : Taux horaire du véhicule en €/h.
* `_etb_base_price` *(float)* : *(Fallback)* Ancien tarif fixe utilisé si le taux horaire vaut `0`.
* `_etb_max_pax` *(int)* : Nombre maximal de passagers autorisés.
* `_etb_max_baggage` *(int)* : Nombre maximal de bagages autorisés.
* `_etb_allowed_extras` *(array d'IDs)* : IDs des options autorisées pour ce véhicule.

#### 3. Méta-clés du CPT `tour_extra` :
* `_etb_price` *(float)* : Prix unitaire de l'option.
* `_etb_price_type` *(string)* : `fixed` (forfait réservation), `per_day` (par jour), `per_quantity` (par unité).
* `_etb_max_qty` *(int)* : Quantité maximale sélectionnable.
* `_etb_icon` *(string)* : Classe Dashicons (ex: `dashicons-tag`).

#### 4. Méta-clés du CPT `tour_promo` :
* `_etb_promo_type` *(string)* : `percentage` (%) ou `fixed` (€).
* `_etb_promo_value` *(float)* : Valeur de la remise.
* `_etb_promo_active` *(string)* : Statut d'activation (`1` = Actif, `0` = Inactif).
* `_etb_promo_code` *(string)* : Code promo normalisé en majuscules.

#### 5. Méta-clés du CPT `tour_booking` (Dossier de Commande) :
* `_etb_customer_name` *(string)* : Nom complet du client.
* `_etb_customer_email` *(string)* : E-mail du client.
* `_etb_booking_date` *(string)* : Date souhaitée (`YYYY-MM-DD`).
* `_etb_booking_time` *(string)* : Heure de départ (`HH:MM`).
* `_etb_pickup_address` *(string)* : Adresse précise de prise en charge saisie.
* `_etb_dropoff_info` *(string)* : Adresse de dépose (ou vide si identique au départ).
* `_etb_circuit_id` *(int)* : ID du post `circuit` (`0` si transfert simple).
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

## 4. ARCHITECTURE DE SÉCURITÉ & ANTI-SPAM (MODULE ETB_SECURITY)

Classe : `ETB_Security` (`includes/class-etb-security.php`)

Pour garantir une protection maximale sans dégrader l'expérience utilisateur (aucun CAPTCHA intrusif), le système déploie une **triple barrière de sécurité native** :

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
                    [ ÉTAPE 3 : CONTRÔLE DE VÉLOCITÉ (TIMESTAMP) ]
                    ETB_Security::verify_timestamp_token( $time, $token, 2, 86400 )
                    • Vérifie la signature cryptographique wp_hash()
                    • Si soumis en < 2 secondes (Bot script) ──► REJET IMMÉDIAT
                    • Si formulaire ouvert depuis > 24h ──────► REJET IMMÉDIAT
                                            │
                                            ▼
                    [ ÉTAPE 4 : RATE LIMITING DYNAMIQUE (TRANSIENTS) ]
                    ETB_Security::check_rate_limit( 'booking', 10, 600 )
                    • Limite : 10 réservations / 10 minutes par IP (Tolérant Hôtels/Wi-Fi)
                    • Limite Promo : 15 échecs / 10 minutes par IP (Anti Brute-Force)
                                            │
                                            ▼
                    [ ÉTAPE 5 : VALIDATION MÉTIER & SANITIZATION ]
                    • Plafonnement des quantités (Véhicules max 50, Extras max 20)
                    • Validation stricte des dates (Rejet des dates passées)
                    • Recalcul 100% serveur du prix par ETB_Pricing_Engine
```

---

## 5. MOTEUR DE TARIFICATION (PRICING ENGINE)

Classe : `ETB_Pricing_Engine` (`includes/class-etb-pricing-engine.php`)

### A. La Formule Mathématique Officielle

$$\text{Montant Total} = \left( \sum_{i=1}^{n} (\text{Taux\_Horaire}_i \times \text{Durée\_Circuit} \times \text{Quantité}_i) \right) + \text{Supplément\_Ville} + \text{Total\_Extras} - \text{Remise\_Promo}$$

### B. Mécanismes d'Exécution :
1. **Autonomie BDD** : La méthode `find_circuit_option_data($option_id, $circuit_id)` lit directement les données de durée et de supplément dans `_circuit_options_data` (avec recherche par `circuit_id` direct et fallback `$wpdb` sécurisé par `prepare()`).
2. **Sécurité financière absolue** : Aucun montant envoyé par le navigateur n'est pris en compte. Le serveur recharge tous les taux horaires des véhicules et les prix des extras depuis la base de données.
3. **Contrôle d'activation promo** : Si un code promo est configuré avec `_etb_promo_active = '0'`, le moteur refuse d'appliquer la réduction même si le code est saisi.

---

## 6. GESTIONNAIRE DES CIRCUITS & OPTIONS DYNAMIQUES

Géré par `ETB_Meta_Manager` (`class-etb-meta-manager.php`) dans **Circuits & Tours**.

Chaque fiche de circuit permet de configurer une infinité d'options de départ grâce à un gestionnaire d'onglets dynamique :
* **Identifiant unique automatique** : Chaque option génère un ID unique (`opt_a1b2c3d4`) évitant toute collision entre deux circuits distincts.
* **Paramètres de liaison** : Ville de départ, Durée en heures (décimale acceptée : `1.75`, `4.0`, `6.5`), Heure conseillée par défaut, Supplément financier.
* **Contenu éditorial** : 4 Badges récapitulatifs, Programme/Timeline par étapes (Horaire, Titre, Description), Listes des éléments Inclus (✓) et Non Inclus (✕).

---

## 7. EXPÉRIENCE UTILISATEUR FRONTEND (SPLIT LAYOUT & DESIGN PHOTO 1)

Le gabarit `templates/circuit-view.php` orchestre la mise en page générale :

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

## 8. MOTEUR JAVASCRIPT RÉACTIF (CLIENT ENGINE)

Fichier : `wp-content/plugins/elite-transfer-booking/public/js/booking-widget.js`

Le script est encapsulé dans une IIFE en JavaScript Vanilla natif ES6+ (0 dépendance jQuery en frontend).

### Fonctionnalités Réactives Majeures :
1. **Ordre d'Initialisation Sécurisé (Anti-TDZ)** : Les fonctions de calcul (`updateSummary`, `refreshAll`) sont déclarées avant `syncCircuitOption` pour éliminer toute erreur `ReferenceError` au chargement de page.
2. **Sélection / Désélection 2-Voies des Véhicules** :
   * Clic sur une carte inactive (`qté = 0`) $\rightarrow$ Passage à `qté = 1`, image qui rétrécit doucement de 105px à 68px, apparition de la pilule orange et de la coche avec rebond élastique.
   * Clic sur une carte déjà active (`qté >= 1`) $\rightarrow$ Remise à `0` et désélection immédiate.
   * Clics sur les boutons `+` / `-` $\rightarrow$ Protégés par `e.stopPropagation()`.
3. **Synchronisation Synchrone des Villes** : Clic sur un onglet de ville $\rightarrow$ Bascule instantanée de la timeline, mise à jour de `state.circuit`, injection des champs cachés `etb_circuit_id` et `etb_option_id`, et recalcul immédiat du prix.
4. **Verrouillage Anti-Antériorité** : `dateInput.setAttribute('min', todayStr)` bloque les dates passées dans le sélecteur natif.
5. **Avertissement Passagers sans Véhicule** : Clic sur `+` passagers sans véhicule choisi $\rightarrow$ Blocage et affichage du message rouge d'erreur sous le titre "NOMBRE DE PASSAGERS".
6. **Code Promo Réactif avec État de Chargement** : Clic sur "APPLIQUER" $\rightarrow$ Bouton affichant *"Vérification..."*, validation AJAX et feedback stylisé (vert `✓` succès, rouge `⚠` erreur).
7. **Auto-Scroll & Reset après Commande** : À la confirmation de réservation, la fenêtre remonte automatiquement en douceur (`scrollIntoView({ behavior: 'smooth' })`) au sommet de la page et remet toutes les cartes de véhicules à zéro.

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

### B. Bouclier Anti-Thème (Protection contre Elementor, Divi, Astra...) :
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
6. **Réponse JSON** : Renvoi du numéro de dossier `#ID` pour affichage du bandeau de confirmation.

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
* Encadré de changement de statut (`⏳ En attente`, `✅ Confirmée`, `🏁 Terminée`, `❌ Annulée`) déclenchant un e-mail automatique au client lors d'une modification.
* Ligne Prestation explicite : **`Prestation : Nom du Circuit — Départ : [Ville] ([Durée]h)`** *(ex: "Venes Circuit 2 — Départ : Monaco (9h)")*.
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

## 13. ROADMAP TECHNIQUE & ÉVOLUTIONS FUTURES

Le socle technique v1.0.3 est optimisé pour accueillir les futures évolutions suivantes :

1. **Intégration Cartographique (Phase Carte)** :
   * Solutions : Leaflet + OpenStreetMap ou API Geoapify.
   * Fonctionnalités : Autocomplétion d'adresses pour la prise en charge et la dépose, géocodage (`lat`/`lng`), et tracé d'itinéraire interactif.
2. **Passerelles de Paiement en Ligne** :
   * Solutions : Stripe Elements / PayPal SDK.
   * Fonctionnalités : Encaissement sécurisé d'un acompte ou de la totalité avec passage automatique du statut à `confirmed` après validation du webhook.
3. **API REST & Webhooks (Applications Mobiles / Chauffeurs)** :
   * Endpoints : `GET /wp-json/etb/v1/circuits`, `GET /wp-json/etb/v1/vehicles`, `POST /wp-json/etb/v1/bookings`, `PATCH /wp-json/etb/v1/bookings/{id}`.
   * Synchronisation iCal pour Google Calendar et agendas chauffeurs.

---

*Documentation technique officielle et exhaustive — **Elite Transfer Booking v1.0.3**.*