**Raison :** Fournir le guide d'utilisation opérationnel mis à jour pour l'administrateur du site.

Voici le contenu à remplacer dans `README.md` :

```markdown
# 🚗 Elite Transfer Booking (Unified) — Manuel d'Exploitation v2.0.3

Extension WordPress professionnelle dédiée à la réservation d'excursions touristiques privées, circuits multi-villes et transferts VTC VIP, avec synchronisation vers la plateforme de dispatch **LimoExpress**.

---

## 📋 Table des matières

1. [Prérequis](#-prérequis)
2. [Installation & Configuration Rapide](#-installation--configuration-rapide)
3. [Configuration du Widget Minimaliste VTC](#-configuration-du-widget-minimaliste-vtc)
4. [Paramétrage de WhatsApp pour les Devis Directs](#-paramétrage-de-whatsapp-pour-les-devis-directs)
5. [Liaison API LimoExpress](#-liaison-api-limoexpress)
6. [Guide des Shortcodes](#-guide-des-shortcodes)

---

## ⚡ Prérequis

* **WordPress** : Version 5.8 ou supérieure (certifié WP 6.x).
* **PHP** : Version 7.4 à 8.2+.
* **Dépendances** : 0 dépendance externe (Vanilla JS pur, CSS3 isolé).

---

## 🚙 Configuration du Widget Minimaliste VTC

Pour afficher le widget moderne VTC sur votre page d'accueil ou page de transfert :
Insérez le shortcode : **`[etb_transfer]`**

### Fonctionnalités intégrées :
* **One Way (Trajet simple)** : Calcul en direct du tarif d'itinéraire via l'API LimoExpress.
* **By the hour (À l'heure)** : Calcul instantané selon la durée choisie (4h, 8h, 10h, 12h) avec affichage du tarif horaire unifié et du quota kilométrique inclus.
* **Custom Quote (Itinéraire sur mesure)** : Si un trajet longue distance n'est pas couvert par les tarifs automatiques, les véhicules basculent automatiquement en mode devis sans bloquer le client.

---

## 💬 Paramétrage de WhatsApp pour les Devis Directs

Pour recevoir les demandes de devis et réservations directement sur le WhatsApp de votre entreprise :

1. Allez dans **Tour Booking > Réglages > Onglet Général**.
2. Renseignez le champ **Numéro WhatsApp de l'entreprise** au format international (ex: `+33 6 12 34 56 78` ou `+261...`).
3. Cliquez sur **Enregistrer les modifications**.
4. Dès qu'un client clique sur **Quick Inquiry**, WhatsApp s'ouvre avec le véhicule sélectionné, les adresses, la date et l'heure pré-remplies.

---

## 🗺️ Choix du Fournisseur d'Adresses (Google Maps ou Mapbox)

Dans **Tour Booking > Réglages > Onglet Général** :
* **Google Places API** : Recommandé. Renseignez votre clé API Google. Les suggestions s'affichent avec sous-titre sur la 2e ligne et en anglais.
* **Mapbox** : Renseignez votre jeton public (`pk.eyJ...`). Comporte la navigation complète au clavier (flèches haut/bas et entrée).

---

## 🔌 Guide des Shortcodes

| Shortcode | Description | Cas d'usage |
| :--- | :--- | :--- |
| **`[etb_transfer]`** | Widget VTC moderne (One Way / By the hour) avec redirection LimoExpress et WhatsApp. | Page d'accueil, landing page transfert VIP. |
| **`[circuit_view id="XX"]`** | Vue Split Layout complète (véhicules en haut + timeline et inclusions à gauche + widget à droite). | Fiches circuits touristiques et excursions. |
| **`[tour_booking]`** | Formulaire de réservation autonome seul. | Barre latérale ou page dédiée. |

---

*Elite Transfer Booking v2.0.3 — Kaiser EM & Reich C.*