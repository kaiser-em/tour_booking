
# 🚗 Elite Transfer Booking (Unified) — Manuel d'Exploitation v2.1.0

Extension WordPress professionnelle dédiée à la réservation d'excursions touristiques privées, circuits multi-villes et transferts VTC VIP, avec synchronisation temps réel vers la plateforme de dispatch **LimoExpress**.

---

## 📋 Table des matières

1. [Prérequis](#-prérequis)
2. [Installation Rapide](#-installation-rapide)
3. [Configuration du Tunnel de Réservation Complet](#-configuration-du-tunnel-de-réservation-complet)
   - [Étape 1 : Le Widget d'Accueil [etb_transfer]](#étape-1--le-widget-daccueil-etb_transfer)
   - [Étape 2 : La Page de Checkout [etb_checkout]](#étape-2--la-page-de-checkout-etb_checkout)
   - [Étape 3 : Relier le bouton de réservation](#étape-3--lier-la-page-dans-les-réglages)
4. [Intégration WhatsApp & Devis E-mail](#-intégration-whatsapp--devis-e-mail)
5. [Liaison LimoExpress & Pourboires (Gratuity)](#-liaison-limoexpress--pourboires-gratuity)
6. [Guide des Shortcodes Officiels](#-guide-des-shortcodes-officiels)

---

## ⚡ Prérequis

* **WordPress** : Version 5.8 ou supérieure (certifié WP 6.x).
* **PHP** : Version 7.4, 8.0, 8.1 ou 8.2+.
* **Dépendances** : **0 dépendance** (Vanilla JS natif, CSS3 isolé, SVG vectoriel pur).

---

## 🚀 Configuration du Tunnel de Réservation Complet

Le plugin intègre désormais le tunnel de réservation standardisé de l'industrie (modèle Blacklane) en 2 étapes sans friction :

### Étape 1 : Le Widget d'Accueil `[etb_transfer]`
Insérez le shortcode **`[etb_transfer]`** sur votre page d'accueil ou page de présentation.
* Permet au client de choisir entre **One way (Trajet simple)** et **By the hour (À l'heure)**.
* Propose les durées de 3h à 24h avec recalcul instantané des tarifs en direct.
* Affiche votre flotte sur des cartes Dark Mode VIP avec photos bord-à-bord, 5 prestations (Climate, Water, Comfort, Safety, Wifi) et badge de réassurance *All inclusive*.

### Étape 2 : La Page de Checkout `[etb_checkout]`
1. Créez une nouvelle page WordPress (ex: **Pages > Ajouter une page**) nommée **Réservation** ou **Checkout** (slug : `/checkout/`).
2. Insérez le shortcode : **`[etb_checkout]`**.
3. Publiez la page.

### Étape 3 : Lier la page dans les Réglages
1. Rendez-vous dans **Tour Booking > Réglages > Onglet Général**.
2. Dans le champ **Page de réservation finale (Checkout)**, collez l'adresse complète de votre page (ex: `https://monsite.com/checkout/`).
3. Cliquez sur **Enregistrer les modifications**.
4. Désormais, dès qu'un client choisit une voiture sur l'accueil et clique sur **`Book this Trip >`**, il est automatiquement transféré vers votre page de Checkout avec son véhicule, son itinéraire et son tarif déjà verrouillés !

---

## 💬 Intégration WhatsApp & Devis E-mail

Dans la barre de confirmation, le client dispose de 3 actions complémentaires :
* **Quick Inquiry (WhatsApp)** : Ouvre une discussion directe avec votre numéro d'entreprise pré-configuré (dans les Réglages) avec un message pré-rédigé incluant le véhicule et l'itinéraire.
* **Email Inquiry** : Génère un e-mail pré-rempli ciblant votre adresse d'administration pour les demandes personnalisées.
* **Book this Trip** : Finalise la réservation sur votre Checkout.

---

## 🚙 Liaison LimoExpress & Pourboires (Gratuity)

* **Transmission immédiate** : Dès la validation du Checkout, la course est créée dans LimoExpress (`PUT /api/integration/booking-with-fees/`) avec le statut initial choisi.
* **Pourboire Chauffeur détaxé** : Le pourboire sélectionné par le client (10%, 15%, 20%) est injecté sous la catégorie officielle **`gratuity_amount`** dans `extra_fees` LimoExpress, garantissant qu'aucune taxe de transport ne soit prélevée sur le pourboire.
* **Accueil Chauffeur** : Le numéro de vol ✈️ et le texte de la pancarte d'accueil sont automatiquement transmis sur l'application du chauffeur.

---

## 🔌 Guide des Shortcodes Officiels

| Shortcode | Rôle | Recommandation d'emplacement |
| :--- | :--- | :--- |
| **`[etb_transfer]`** | Widget de recherche VTC rapide (One Way / Hourly) avec cartes luxury et calcul en direct. | Page d'accueil, page d'atterrissage. |
| **`[etb_checkout]`** | Tunnel de réservation Blacklane 2 colonnes (coordonnées, vol, pancarte, pourboire, récapitulatif). | Page dédiée `/checkout/` ou `/booking/`. |
| **`[circuit_view id="XX"]`** | Vue complète pour excursions touristiques privées avec timeline et inclusions. | Fiches circuits touristiques. |
| **`[tour_booking]`** | Formulaire de réservation classique. | Barres latérales. |

---

*Elite Transfer Booking v2.1.0 — Kaiser EM & Reich C.*