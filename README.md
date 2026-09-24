
# 🚗 Elite Transfer Booking  — Manuel d'Exploitation v2.2.0

Extension WordPress professionnelle dédiée à la réservation de transferts VTC VIP, excursions privées et circuits, avec synchronisation en temps réel vers votre plateforme de dispatch **LimoExpress** et sécurisation bancaire **Stripe**.

---

## 📋 Table des matières

1. [Philosophie : LimoExpress Tour de Contrôle](#-philosophie--limoexpress-tour-de-contrôle)
2. [Installation & Configuration Rapide](#-installation--configuration-rapide)
3. [Configuration de la Passerelle Stripe](#-configuration-de-la-passerelle-stripe)
4. [Configuration du Tunnel de Réservation](#-configuration-du-tunnel-de-réservation)
   - [Étape 1 : Le Widget d'Accueil [etb_transfer]](#étape-1--le-widget-daccueil-etb_transfer)
   - [Étape 2 : Le Checkout VIP Blacklane [etb_checkout]](#étape-2--le-checkout-vip-blacklane-etb_checkout)
5. [Comment Gérer vos Courses dans LimoExpress](#-comment-gérer-vos-courses-dans-limoexpress)
6. [Guide des Shortcodes Officiels](#-guide-des-shortcodes-officiels)

---

## 🏛️ Philosophie : LimoExpress Tour de Contrôle

Ce plugin a été conçu pour simplifier votre quotidien :
* **WordPress** gère l'acquisition client, le calcul des tarifs en direct et l'expérience de réservation haut de gamme en marque blanche.
* **LimoExpress** est votre **seul outil de travail quotidien** : vous gérez vos chauffeurs, vos plannings, vos factures et vos encaissements directement sur `app.limoexpress.me`. Vous n'avez pas besoin d'ouvrir WordPress pour gérer vos courses !

---

## 💳 Configuration de la Passerelle Stripe

Le plugin intègre le modèle d'empreinte bancaire sécurisé de Blacklane (l'argent est pré-autorisé à la réservation, vous encaissez le montant exact une fois la mission terminée) :

1. Rendez-vous dans **Tour Booking > Réglages > Onglet Général**.
2. Dans la section **💳 Passerelle de Paiement Stripe** :
   * Cochez **`[✓] Activer Stripe`**.
   * Choisissez le mode : **`🟡 Test / Sandbox`** ou **`🟢 Live / Production`**.
   * Renseignez votre **Clé Publique** (`pk_test_...` ou `pk_live_...`).
   * Renseignez votre **Clé Secrète** (`sk_test_...` ou `sk_live_...`).
   * Méthode de prélèvement : sélectionnez **`🔒 Empreinte Bancaire / Pré-autorisation (Modèle Blacklane)`**.
3. Cliquez sur **Enregistrer les modifications**.

---

## 🚀 Configuration du Tunnel de Réservation

### Étape 1 : Le Widget d'Accueil `[etb_transfer]`
Placez le shortcode **`[etb_transfer]`** sur votre page d'accueil ou page de réservation rapide.
* Recherche Trajet simple (One way) ou À l'heure (By the hour de 3h à 24h).
* Cartes Dark Mode haute couture avec photos bord-à-bord, 6 équipements VIP et calcul en temps réel.
* Prise en charge automatique des demandes sur devis (*Custom Quote*).

### Étape 2 : Le Checkout VIP Blacklane `[etb_checkout]`
1. Créez une page WordPress nommée **Checkout** ou **Réservation** (ex: `/checkout/`).
2. Insérez le shortcode : **`[etb_checkout]`** et publiez la page.
3. Dans **Tour Booking > Réglages > Onglet Général**, renseignez le champ **Page de réservation finale (Checkout)** avec l'URL de cette page.
4. Le clic sur `Book this Trip >` depuis l'accueil transférera automatiquement vos clients vers ce checkout avec un **Quote Token serveur sécurisé** (`?ref=q_XXXX`).

---

## 📋 Comment Gérer vos Courses dans LimoExpress

Dès qu'une réservation est validée sur votre site :
1. Ouvrez votre compte **LimoExpress** (`app.limoexpress.me`).
2. Dans la liste de vos réservations, la course apparaît instantanément :
   * **Client & Passagers** : Fiche client créée avec son numéro de portable international.
   * **Paiement carte** : Dans la section *Historique des paiements*, la ligne de carte `Visa •••• 0000` est enregistrée avec le montant payé et la date d'expiration.
   * **Pourboire** : Si le client a ajouté un pourboire, il apparaît sous **`Gratuity amount`** dans le tableau *Extra fees* (séparé du transport pour éviter la TVA).
   * **Vol & Pancarte** : Le numéro de vol ✈️ et le nom de la pancarte chauffeur sont renseignés.
   * **Lien Stripe direct** : Dans la **Note Répartiteur** de la course, un lien direct vers votre transaction Stripe est affiché pour débiter ou ajuster la course en 1 clic !

---

## 🔌 Guide des Shortcodes Officiels

| Shortcode | Description | Emplacement recommandé |
| :--- | :--- | :--- |
| **`[etb_transfer]`** | Widget VTC d'accueil (Trajet simple / À l'heure 3-24h) avec cartes luxury. | Page d'accueil, landing page. |
| **`[etb_checkout]`** | Tunnel de réservation Blacklane 2 étapes (passager, vol, pourboire, Stripe). | Page dédiée `/checkout/`. |
| **`[circuit_view id="XX"]`** | Vue Split Layout pour excursions touristiques privées avec timeline. | Fiches circuits touristiques. |
| **`[tour_booking]`** | Formulaire de réservation classique. | Barres latérales. |

---

*Elite Transfer Booking v2.2.0 — Kaiser EM & Reich C.*





chore(release): bump version to 2.2.0, finalize Blacklane checkout, and sync docs

- feat(checkout): implement 2-step in-place Blacklane checkout funnel with Stripe Elements
- feat(security): add server-side Quote Token generator (?ref=q_XXXX) with 30-min transient expiry
- feat(limoexpress): full integration of payment_logs, gratuity_amount, flight_number, and waiting_board_text
- feat(limoexpress): inject direct clickable Stripe transaction link into LimoExpress dispatcher notes
- feat(ui): build 100% theme-immune Dark Mode Custom Select for billing country
- feat(ui): implement smooth GPU-accelerated bi-directional sticky summary sidebar
- feat(ui): context-aware airport arrival vs dropoff detection and dynamic wait times
- feat(ui): luxury vehicle cards with 6 amenities including SVG water bottle and baby seat
- feat(ux): add address input autocomplete spinner and loading border beam on Show Prices
- fix(stripe): resolve card last4/expiry extraction and card expiration month/year validation
- fix(responsive): ensure vehicle names and confirmation buttons wrap cleanly without clipping
- docs: update Document.md specification to v2.2.0 with LimoExpress-First architecture
- docs: update README.md user guide with complete checkout setup and LimoExpress workflow


