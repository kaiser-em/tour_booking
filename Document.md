Vous avez parfaitement raison ! Ne commitez pas tout de suite. En tant que développeur senior, la documentation doit toujours être **le reflet exact et fidèle du code avant chaque commit**.

Depuis notre version 2.0.3, nous avons développé des fonctionnalités majeures :

# 📖 DOCUMENTATION TECHNIQUE & FONCTIONNELLE OFFICIELLE
# Elite Transfer Booking (Unified) — Version 2.1.0 (Jalon Native Checkout)

---

## 📌 FICHE D'IDENTITÉ DU PROJET

| Propriété | Spécification officielle |
| :--- | :--- |
| **Nom de l'extension** | **Elite Transfer Booking (Unified)** |
| **Identifiant / Text Domain** | `elite-transfer-booking` |
| **Version actuelle** | **`2.1.0`** *(Tunnel de Checkout Blacklane VIP, Handoff dynamique, Pourboire LimoExpress Gratuity, Détection de Vol contextuelle, Cartes Luxury)* |
| **Auteur** | Kaiser EM / Reich C |
| **Type de solution** | Moteur WordPress 100 % autonome pour réservations d'excursions, circuits et transferts VTC VIP avec dispatch API LimoExpress |
| **Compatibilité WP / PHP** | WordPress 5.8+ (testé 6.x+) / PHP 7.4, 8.0, 8.1, 8.2+ |
| **Dépendances externes** | **0 dépendance serveur** (Vanilla JS ES6+ natif, Dashicons, SVG vectoriel inline, CSS3 tokenisé, Fetch API, WordPress Core APIs) |

---

## 📑 TABLE DES MATIÈRES

1. [Vue d'Ensemble & Nouveautés v2.1.0](#1-vue-densemble--nouveautés-v210)
2. [Arborescence Réelle du Codebase](#2-arborescence-réelle-du-codebase)
3. [Modèle de Données & Dictionnaire des Métadonnées](#3-modèle-de-données--dictionnaire-des-métadonnées)
4. [Tunnel de Réservation Blacklane & Handoff](#4-tunnel-de-réservation-blacklane--handoff)
5. [Connecteur API LimoExpress (Spécifications & Payload)](#5-connecteur-api-limoexpress-spécifications--payload)
6. [Système de Facturation & E-mails](#6-système-de-facturation--e-mails)
7. [Architecture de Sécurité & Rate Limiting](#7-architecture-de-sécurité--rate-limiting)
8. [Administration Back-Office WordPress](#8-administration-back-office-wordpress)

---

## 1. VUE D'ENSEMBLE & NOUVEAUTÉS v2.1.0

La version **2.1.0** émancipe totalement l'extension des formulaires externes hébergés en introduisant un tunnel de réservation VIP en marque blanche inspiré des standards Blacklane / Wheely :

1. **Tunnel de Checkout Dédié (`[etb_checkout]`)** :
   * Gabarit 2 colonnes (`templates/checkout-view.php`) en Dark Mode noble (`#0f1420`, `#fbac18`).
   * **Colonne gauche** : 4 sections fluides (Accueil vol/pancarte, passager/corporate, sièges enfants, notes chauffeur).
   * **Colonne droite (Sticky GPU)** : Carte de synthèse immuable avec glissement bi-directionnel soyeux (`transform: translateY`), garanties de service, sélection de pourboire et validation directe.
2. **Passerelle Automatique (Search-to-Checkout Handoff)** :
   * Le clic sur `Book this Trip >` depuis `[etb_transfer]` transfère instantanément l'itinéraire, la date, l'heure, le véhicule choisi et son tarif vers `[etb_checkout]` via URL et `sessionStorage` (zéro ressaisie client).
3. **Détection Aéroportuaire Intelligente & Contextuelle** :
   * **Arrivée d'avion (Pickup = Aéroport)** : Révèle le numéro de vol, la pancarte d'accueil chauffeur sur tablette et la mention des 60 min d'attente gratuites après atterrissage.
   * **Dépose à l'aéroport (Drop-off = Aéroport)** : Masque automatiquement la pancarte chauffeur (inutile) et propose le champ optionnel du terminal de départ.
   * **Trajet de ville à ville** : La boîte de vol reste 100 % masquée (sans faux positif sur le mot "France").
4. **Pourboire Chauffeur Intégré & Comptabilité LimoExpress** :
   * Sélecteur de pourboire en pourcentage (`None`, `10%`, `15%`, `20%`) avec recalcul du total en temps réel.
   * Transmission officielle à LimoExpress dans le tableau `extra_fees` sous la catégorie native **`gratuity_amount`** (distincte du prix transport pour ne pas appliquer la TVA sur le pourboire).
5. **Cartes de Flotte Haute Couture** :
   * Photos et GIF plein format bord-à-bord avec dégradé fondu progressif (`linear-gradient`) vers le fond de carte.
   * 5 prestations VIP vectorielles SVG (Climate, Water, Comfort, Safety, Wifi).
   * Pied de carte harmonieux avec prix en grand à gauche, mention `All inclusive ✨` et bouton d'action.
   * Prise en charge automatique des véhicules sur devis (`Custom Quote` / `On request 💬` si 0 €).
6. **Interaction "Border Beam" & Micro-loaders** :
   * Titre animé `FARE CALCULATOR` avec faisceau traversant continu inspiré du mode Thinking de Google AI Studio.
   * Mini-spinner doré dans le champ d'adresses pendant la saisie.
   * Liseré lumineux tournant bicolore (Or & Cyan) autour de *Show Prices* pendant le calcul.

---

## 2. ARBORESCENCE RÉELLE DU CODEBASE

```text
wp-content/plugins/elite-transfer-booking/
│
├── elite-transfer-booking.php          # Orchestrateur Singleton, enqueue assets & localisation JS
│
├── includes/
│   ├── class-etb-cpt-manager.php       # Enregistrement des 6 CPTs & colonnes d'administration
│   ├── class-etb-settings.php          # Réglages généraux, devise, URL checkout & WhatsApp
│   ├── class-etb-security.php          # Nonces, Honeypot, Timestamp signé & Rate Limiting IP
│   ├── class-etb-pricing-engine.php    # Moteur financier unifié (forfaits 10h, extras, promos)
│   ├── class-etb-meta-manager.php      # Métaboxes d'administration, resync Limo & facture Option C
│   ├── class-etb-limoexpress.php       # Connecteur API LimoExpress complet (Swagger conforme)
│   ├── class-etb-dispatcher-manager.php# Gestionnaire de dispatch (Autonome / LimoExpress)
│   ├── class-etb-ajax.php              # Contrôleur AJAX (validation, quick pricing, checkout, inquiries)
│   └── class-etb-shortcode.php         # Shortcodes [circuit_view], [tour_booking], [etb_transfer], [etb_checkout]
│
├── admin/
│   ├── css/etb-admin.css               # Styles de l'administration et répéteurs
│   └── js/etb-admin.js                 # Scripts des onglets et timeline circuits
│
├── public/
│   ├── css/booking-widget.css          # Design System tokenisé, Theme Shield, Checkout & Widgets
│   └── js/booking-widget.js            # Moteur réactif client, calculs live, autocomplétion & Checkout
│
└── templates/
    ├── circuit-view.php                # Split layout complet pour circuits touristiques
    ├── booking-form.php                # Formulaire classique de réservation
    ├── transfer-widget.php             # Widget minimal VTC One way / By the hour
    ├── checkout-view.php               # Tunnel de finalisation VIP Blacklane (2 colonnes)
    └── invoice-print.php               # Facture officielle décomposée prête à l'impression / PDF
```

---

## 3. MODÈLE DE DONNÉES & DICTIONNAIRE DES MÉTADONNÉES

### A. Dossier de Réservation (`tour_booking`) :
* `_etb_customer_name` *(string)* : Nom complet du voyageur principal.
* `_etb_customer_email` *(string)* : E-mail de confirmation.
* `_etb_customer_phone` *(string)* : Téléphone mobile international.
* `_etb_booking_date` *(string)* : Date de la prestation (`YYYY-MM-DD`).
* `_etb_booking_time` *(string)* : Heure de prise en charge (`HH:MM`).
* `_etb_pickup_address` *(string)* : Lieu de prise en charge précis.
* `_etb_dropoff_info` *(string)* : Lieu de dépose (ou `By the hour (Xh)`).
* `_etb_duration_hours` *(float)* : Durée totale facturée.
* `_etb_adults` *(int)* : Nombre exact de passagers déclarés.
* `_etb_luggage` *(int)* : Nombre exact de bagages déclarés.
* `_etb_vehicles` *(array)* : `[ vehicle_id => 1 ]`.
* `_etb_base_price` *(float)* : Tarif net de la course hors pourboire.
* `_etb_tip_amount` *(float)* : Montant du pourboire chauffeur alloué.
* `_etb_tip_percentage` *(int)* : Pourcentage de pourboire choisi (`0`, `10`, `15`, `20`).
* `_etb_total_price` *(float)* : Montant global final (`Base + Pourboire`).
* `_etb_flight_number` *(string)* : Numéro de vol ou train (ex: `AF 7704`).
* `_etb_waiting_board_text` *(string)* : Nom affiché sur la pancarte d'accueil du chauffeur.
* `_etb_booker_type` *(string)* : `myself` (le passager) ou `guest` (corporate/assistant).
* `_etb_booker_name` *(string)* / `_etb_booker_email` *(string)* : Coordonnées de l'assistant si applicable.
* `_etb_baby_seat_count` *(int)* : Nombre de sièges enfants demandés (0 à 4).
* `_etb_cost_center` *(string)* : Centre de coûts / Bon de commande interne pour la facture.
* `_etb_note` *(string)* : Notes pour le chauffeur incluant récapitulatif du vol et du pourboire.
* `_etb_limo_status` *(string)* : `synced` ou `failed`.
* `_etb_limo_booking_id` *(string)* : Numéro de course LimoExpress (ex: `47fb3082`).

### B. Options Globales (`wp_options` $\rightarrow$ `etb_general_settings`) :
* `currency` : Symbole monétaire actif (`€`, `$`, `CHF`...).
* `checkout_page_url` : URL de redirection de la page de Checkout (`[etb_checkout]`).
* `company_whatsapp` : Numéro international officiel pour les demandes directes.
* `address_provider` : Moteur d'autocomplétion (`google` ou `mapbox`).
* `google_maps_api_key` / `mapbox_token`.
* `active_dispatcher` : `none` (Autonome) ou `limoexpress`.
* `limo_api_token`, `limo_client_id`, `limo_booking_type_id`, `limo_booking_status_id`.

---

## 4. TUNNEL DE RÉSERVATION BLACKLANE & HANDOFF

Le parcours client s'articule sans couture :

```text
[ Widget d'accueil [etb_transfer] ]
  │
  ├──► Calcul direct de trajet ou forfait horaire
  ├──► Sélection de la voiture sur cartes luxury bord-à-bord
  ├──► Clic sur [ Book this Trip > ]
  │
  ▼
[ Handoff automatique ] (SessionStorage + Query params URL)
  │
  ▼
[ Page Checkout [etb_checkout] ]
  │
  ├──► Colonne Gauche : Formulaire Blacklane
  │    • Section 1 : Accueil aéroport (conditionnelle, masquée si ville à ville)
  │    • Section 2 : Passager (Bascule Myself / Guest) + Compteurs Passagers/Bagages
  │    • Section 3 : Sièges enfants (0 à 4) + Notes chauffeur + Centre de coûts
  │
  ├──► Colonne Droite : Sticky Summary Card (Glissement GPU fluide)
  │    • Véhicule, timeline d'itinéraire, date/heure
  │    • Garanties (Annulation gratuite à 8h, attente 60 min aéroport / 15 min ville)
  │    • Sélecteur de pourboire chauffeur (0%, 10%, 15%, 20%)
  │
  ▼
[ Clic sur CONFIRM & BOOK NOW ]
  │
  ├──► Validation et insertion WordPress (#ID)
  ├──► Dispatch direct LimoExpress PUT /api/integration/booking-with-fees/
  └──► Écran vert de confirmation VIP avec numéro de dossier et course LimoExpress
```

---

## 5. CONNECTEUR API LIMOEXPRESS (SPÉCIFICATIONS & PAYLOAD)

Endpoint officiel : `PUT https://api.limoexpress.me/api/integration/booking-with-fees/`

### Payload JSON envoyé :
```json
{
  "booking_type_id": "UUID()",
  "booking_status_id": "UUID()",
  "vehicle_class_id": "UUID()",
  "client_id": "UUID()",
  "pickup_time": "2026-09-22 09:00:00",
  "expected_drop_off_time": "2026-09-22 10:00:00",
  "duration": "01:00",
  "from_location": { "name": "Nice Côte d'Azur Airport" },
  "to_location": { "name": "Cannes, France" },
  "price": 197,
  "price_type": "NET",
  "passenger_count": 2,
  "suitcase_count": 2,
  "baby_seat_count": 1,
  "flight_number": "AF 7704",
  "waiting_board_text": "Mr. Bruce Wayne",
  "note": "📋 DOSSIER WP #1048\n✈️ VOL : AF 7704\n👶 SIÈGES BÉBÉ REQUIS : 1\n💸 POURBOIRE CHAUFFEUR INCLUS : 19.70 € (10%)\n⭐ Extras : Aucun\n📝 Note client : Terminal 2...",
  "note_for_driver": "📋 DOSSIER WP #1048\n✈️ VOL : AF 7704\n👶 SIÈGES BÉBÉ REQUIS : 1\n💸 POURBOIRE CHAUFFEUR INCLUS : 19.70 € (10%)\n...",
  "extra_fees": [
    {
      "category": "gratuity_amount",
      "amount": 19.70
    }
  ],
  "passengers": [
    {
      "first_name": "Bruce",
      "last_name": "Wayne",
      "email": "b.wayne@corp.com",
      "phone": "+33612345678"
    }
  ]
}
```

---

## 6. ARCHITECTURE DE SÉCURITÉ & RATE LIMITING

1. **CSRF & Nonce** : Vérification stricte via `check_ajax_referer( 'etb_booking_nonce', 'nonce' )`.
2. **Honeypot** : Champ invisible `etb_hp_email`.
3. **Timestamp Cryptographique** : Signature serveur rejetant les bots ultrarapides (< 2s).
4. **Rate Limiting par Transients IP** :
   * Réservations & Checkouts : Max 10 soumissions / 10 minutes par IP.
   * Devis e-mail : Max 5 demandes / 10 minutes par IP.
   * Estimations LimoExpress : Max 30 calculs / 10 minutes par IP.
5. **Recalcul Métier Serveur** : Le serveur ne fait jamais confiance aux prix du DOM et recalcule les totaux en base.

---


