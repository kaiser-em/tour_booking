
# 📖 DOCUMENTATION TECHNIQUE & FONCTIONNELLE OFFICIELLE
# Elite Transfer Booking (Unified) — Version 2.2.0 (Jalon LimoExpress Master & In-Place Checkout)

---

## 📌 FICHE D'IDENTITÉ DU PROJET

| Propriété | Spécification officielle |
| :--- | :--- |
| **Nom de l'extension** | **Elite Transfer Booking (Unified)** |
| **Identifiant / Text Domain** | `elite-transfer-booking` |
| **Version actuelle** | **`2.2.0`** *(LimoExpress Command Center, Tunnel In-Place 2 étapes, Stripe Elements, Quote Token Serveur, Cartes Luxury)* |
| **Auteur** | Kaiser EM / Reich C |
| **Philosophie d'exploitation** | **LimoExpress-First** : LimoExpress est la tour de contrôle unique (dispatch, régulation, facturation). WordPress est la vitrine de conversion VIP en marque blanche. |
| **Compatibilité WP / PHP** | WordPress 5.8+ (testé 6.x+) / PHP 7.4, 8.0, 8.1, 8.2+ |
| **Dépendances externes** | **0 dépendance serveur lourde** (Stripe.js officiel v3 côté client, API REST native WordPress, Vanilla JS ES6+ pur, CSS3 tokenisé) |

---

## 📑 TABLE DES MATIÈRES

1. [Philosophie d'Exploitation : LimoExpress Tour de Contrôle](#1-philosophie-dexploitation--limoexpress-tour-de-contrôle)
2. [Vue d'Ensemble & Nouveautés v2.2.0](#2-vue-densemble--nouveautés-v220)
3. [Arborescence Réelle du Codebase](#3-arborescence-réelle-du-codebase)
4. [Architecture Sécurisée du Quote Token (Handoff)](#4-architecture-sécurisée-du-quote-token-handoff)
5. [Tunnel de Checkout In-Place (2 Étapes Blacklane)](#5-tunnel-de-checkout-in-place-2-étapes-blacklane)
6. [Passerelle Stripe & Transmission LimoExpress Payment Logs](#6-passerelle-stripe--transmission-limoexpress-payment-logs)
7. [Modèle de Données & Dictionnaire des Métadonnées](#7-modèle-de-données--dictionnaire-des-métadonnées)
8. [Sécurité, Anti-Spam & Rate Limiting](#8-sécurité-anti-spam--rate-limiting)

---

## 1. PHILOSOPHIE D'EXPLOITATION : LIMOEXPRESS TOUR DE CONTRÔLE

L'architecture v2.2.0 consacre **LimoExpress comme unique source de vérité opérationnelle (Single Source of Truth)** :

* **Rôle de WordPress** : Vitrine d'acquisition client haut de gamme, moteur de calcul réactif en marque blanche, générateur de devis scellés et tunnel de réservation sans friction.
* **Rôle de LimoExpress** : Tour de contrôle unique. L'administrateur et ses régulateurs gèrent 100 % de l'exploitation (attribution des chauffeurs, modifications d'horaires, factures, encaissements de soldes et suppléments) **exclusivement dans LimoExpress**.
* **Zéro double saisie** : Les courses et empreintes bancaires arrivent prêtes à l'emploi dans LimoExpress avec le lien direct vers la transaction Stripe dans la note de dispatch.

---

## 2. VUE D'ENSEMBLE & NOUVEAUTÉS v2.2.0

1. **Tunnel In-Place en 2 Étapes (`[etb_checkout]`)** :
   * **Étape 1 (Coordonnées)** : Passager (Myself vs Guest), accueil aéroport intelligent, compteurs interactifs de passagers et bagages, sièges enfants, notes chauffeur. Bouton : `Continue to Payment ➔`.
   * **Étape 2 (Paiement Stripe EDEN CAB)** : Transition fluide dans la colonne de gauche sans quitter la page. Composant Stripe Elements Dark Mode certifié PCI-DSS, détection visuelle de marque (Visa, Mastercard, Amex), formatage intelligent MM/YY, mandat d'autorisation différée et sélection de pays 100 % immunisée contre les thèmes. Bouton officiel Blacklane : `Confirm & Book Now ➔`.
2. **Quote Token Serveur (`?ref=q_...`)** :
   * Élimination totale des prix et adresses privées en clair dans l'URL.
   * Génération d'un devis scellé en base temporaire WordPress (Transient 30 min) à la sélection de la course.
   * URL de redirection courte, luxueuse et inviolable : `votresite.com/checkout/?ref=q_8f94c2d1`.
3. **Paiement Stripe & Modèle Blacklane (Delayed Capture)** :
   * Empreinte bancaire 3D Secure sécurisée (`capture_method: manual`).
   * Injection officielle du tableau `payment_logs` dans l'API LimoExpress (`amount`, `method: 'card'`, `last_4_digits`, `brand`, `expire_date`, `paid_at`).
   * Injection du lien direct vers la transaction Stripe dans la note de dispatch LimoExpress.
4. **Gestion du Pourboire Chauffeur (Gratuity)** :
   * Sélecteur de pourboire dynamique (None, 10%, 15%, 20%) synchronisé en temps réel sur les deux colonnes.
   * Injection native dans LimoExpress sous la catégorie officielle `extra_fees` : **`gratuity_amount`** (séparée du prix transport pour ne pas taxer le pourboire à la TVA).
5. **Détection Aéroport Contextuelle** :
   * Arrivée (Pickup aéroport) : Vol ✈️ + Pancarte d'accueil chauffeur + 60 min d'attente gratuites.
   * Dépose (Dropoff aéroport) : Pancarte masquée automatiquement + terminal de départ optionnel + 15 min d'attente.
   * Ville à ville : Boîte aéroport entièrement masquée (regex étanche sans faux positif sur le mot "France").
6. **Ergonomie Mobile & GPU** :
   * Colonne de synthèse avec glissement fluide bi-directionnel assisté par GPU (`transform: translateY`).
   * Remplacement intégral des émojis par des tracés vectoriels SVG dorés.
   * Défilement des durées de 3h à 24h avec recalcul instantané.

---

## 3. ARBORESCENCE RÉELLE DU CODEBASE

wp-content/plugins/elite-transfer-booking/
│
├── elite-transfer-booking.php          # Orchestrateur Singleton, assets, enqueue Stripe & version 2.2.0
│
├── includes/
│   ├── class-etb-cpt-manager.php       # Enregistrement des 6 CPTs & colonnes d'administration
│   ├── class-etb-settings.php          # Réglages généraux, devise, Stripe API, URL checkout & WhatsApp
│   ├── class-etb-security.php          # Nonces, Honeypot, Timestamp signé & Rate Limiting IP
│   ├── class-etb-pricing-engine.php    # Moteur financier unifié (forfaits 10h, extras, promos)
│   ├── class-etb-meta-manager.php      # Métaboxes d'administration, bouton Stripe direct & facture PDF
│   ├── class-etb-stripe.php            # Connecteur API REST Stripe autonome (Customers & PaymentIntents)
│   ├── class-etb-limoexpress.php       # Connecteur API LimoExpress complet (Swagger conforme)
│   ├── class-etb-dispatcher-manager.php# Gestionnaire de dispatch (Autonome / LimoExpress)
│   ├── class-etb-ajax.php              # Contrôleur AJAX (validation, quote token, checkout, stripe, quick pricing)
│   └── class-etb-shortcode.php         # Shortcodes [circuit_view], [tour_booking], [etb_transfer], [etb_checkout]
│
├── admin/
│   ├── css/etb-admin.css               # Styles de l'administration et répéteurs
│   └── js/etb-admin.js                 # Scripts des onglets et timeline circuits
│
├── public/
│   ├── css/booking-widget.css          # Design System Dark Mode VIP, Theme Shield, Checkout & Widgets
│   └── js/booking-widget.js            # Moteur réactif client, Stripe Elements, Handoff & autocomplétion
│
└── templates/
    ├── circuit-view.php                # Split layout complet pour circuits touristiques
    ├── booking-form.php                # Formulaire classique de réservation
    ├── transfer-widget.php             # Widget minimal VTC One way / By the hour
    ├── checkout-view.php               # Tunnel de finalisation VIP Blacklane (2 étapes in-place)
    └── invoice-print.php               # Facture officielle décomposée prête à l'impression / PDF


---

## 4. ARCHITECTURE SÉCURISÉE DU QUOTE TOKEN (HANDOFF)

[ Client choisit sa course sur l'accueil : Mercedes V-Class ]
                              │
                              ▼
                     CLIC SUR "BOOK THIS TRIP"
                              │
                              ▼
         [ MICRO-APPEL AJAX SERVEUR : etb_create_quote ]
         • Validation du véhicule et recalcul du tarif par le serveur.
         • Création du Transient WordPress (valide 30 min) :
           Clé : "etb_quote_q_8f94c2d1"
           Données : { pickup, dropoff, date, time, vehicle_id, price, pax, bag }
                              │
                              ▼
       [ REDIRECTION PROPRE VERS LE CHECKOUT ]
       https://votresite.com/checkout/?ref=q_8f94c2d1
                              │
                              ▼
       [ HYDRATATION SERVEUR INSTANTANÉE (PHP SSR) ]
       • checkout-view.php lit get_transient('etb_quote_q_8f94c2d1').
       • Le récapitulatif s'affiche 100 % rempli au premier millième de seconde.
       • Zéro risque de falsification de prix par l'URL.


---

## 5. CONNECTEUR API LIMOEXPRESS (SPÉCIFICATIONS & PAYLOAD)

Endpoint officiel : `PUT https://api.limoexpress.me/api/integration/booking-with-fees/`

### Schéma du Payload JSON transmis :
```json
{
  "booking_type_id": "UUID()",
  "booking_status_id": "UUID()",
  "vehicle_class_id": "UUID()",
  "client_id": "UUID()",
  "pickup_time": "2026-09-24 09:00:00",
  "expected_drop_off_time": "2026-09-24 10:00:00",
  "duration": "01:00",
  "from_location": { "name": "Nice Côte d'Azur Airport (NCE)" },
  "to_location": { "name": "Cannes, France" },
  "price": 197,
  "price_type": "NET",
  "passenger_count": 3,
  "suitcase_count": 2,
  "baby_seat_count": 1,
  "flight_number": "AF 7704",
  "waiting_board_text": "Mr. Bruce Wayne",
  "note": "══════ PAIEMENT STRIPE (EMPREINTE) ══════\n💳 ID Transaction : pi_3UlhhCCw...\n🔗 LIEN DIRECT STRIPE :\nhttps://dashboard.stripe.com/test/payments/pi_3UlhhCCw...\n════════════════════════════════════════\n\n📋 DOSSIER WP #1052\n✈️ VOL : AF 7704\n👶 SIÈGES BÉBÉ REQUIS : 1\n💸 POURBOIRE CHAUFFEUR INCLUS : 19.70 € (10%)\n⭐ Extras : Aucun\n📝 Note client : Terminal 2...",
  "note_for_driver": "📋 DOSSIER WP #1052\n✈️ VOL : AF 7704\n👶 SIÈGES BÉBÉ REQUIS : 1\n💸 POURBOIRE CHAUFFEUR INCLUS : 19.70 € (10%)\n...",
  "paid": true,
  "extra_fees": [
    {
      "category": "gratuity_amount",
      "amount": 19.70
    }
  ],
  "payment_logs": [
    {
      "amount": 216.70,
      "method": "card",
      "last_4_digits": "0000",
      "brand": "visa",
      "expire_date": "10/28",
      "receipt_number": "pi_3UlhhCCw...",
      "remark": "https://dashboard.stripe.com/test/payments/pi_3UlhhCCw...",
      "paid_at": "2026-09-24 11:30:00"
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

## 6. DICTIONNAIRE DES MÉTADONNÉES (`wp_postmeta`)

Sur chaque réservation (`tour_booking`) :
* `_etb_customer_name` *(string)* : Nom complet du passager principal.
* `_etb_customer_email` *(string)* / `_etb_customer_phone` *(string)* : Coordonnées voyageur.
* `_etb_booking_date` / `_etb_booking_time` : Horodatage mission.
* `_etb_pickup_address` / `_etb_dropoff_info` : Adresses ou durée horaire.
* `_etb_adults` / `_etb_luggage` : Quantités exactes de passagers et valises.
* `_etb_base_price` *(float)* : Tarif net de transport.
* `_etb_tip_amount` *(float)* : Montant du pourboire chauffeur.
* `_etb_tip_percentage` *(int)* : Pourcentage de pourboire alloué (0, 10, 15, 20).
* `_etb_total_price` *(float)* : Total global incluant le pourboire.
* `_etb_flight_number` *(string)* : Numéro de vol / train.
* `_etb_waiting_board_text` *(string)* : Texte affiché sur la pancarte d'accueil.
* `_etb_booker_type` *(string)* : `myself` ou `guest`.
* `_etb_booker_name` / `_etb_booker_email` : Coordonnées de l'assistant si réservation pour un tiers.
* `_etb_baby_seat_count` *(int)* : Nombre de sièges bébé (0 à 4).
* `_etb_cost_center` *(string)* : Référence de facturation / bon de commande client.
* `_etb_stripe_payment_intent_id` *(string)* : Identifiant officiel Stripe `pi_3...`.
* `_etb_limo_status` *(string)* : `synced` ou `failed`.
* `_etb_limo_booking_id` *(string)* : Numéro de course LimoExpress.

---

## 7. ARCHITECTURE DE SÉCURITÉ & RATE LIMITING

1. **Stripe Elements PCI-DSS** : Aucune donnée de carte bancaire ne transite par les serveurs WordPress ou LimoExpress.
2. **CSRF & Nonce** : Contrôle systématique via `check_ajax_referer( 'etb_booking_nonce', 'nonce' )`.
3. **Honeypot** : Champ piège invisible `etb_hp_email`.
4. **Rate Limiting par Transients IP** :
   * Création de Quote Token : Max 20 devis / 10 minutes par IP.
   * Soumission Checkout : Max 10 réservations / 10 minutes par IP.
   * Estimations LimoExpress : Max 30 requêtes / 10 minutes par IP.
5. **Recalcul Serveur Indestructible** : Le serveur WordPress et LimoExpress valident les montants indépendamment du DOM client.



