




# 🚗 Elite Transfer Booking (Unified) — Guide d'Installation & Configuration LimoExpress

Extension WordPress professionnelle dédiée à la réservation d'excursions touristiques privées, circuits multi-villes et transferts VTC VIP, avec synchronisation temps réel vers la plateforme de dispatch **LimoExpress**.

---

## 📋 Table des matières

1. [Prérequis](#-prérequis)
2. [Installation rapide](#-installation-rapide)
3. [Configuration pas-à-pas de LimoExpress](#-configuration-pas-à-pas-de-limoexpress)
   - [Étape 1 : Récupérer le jeton API dans LimoExpress](#étape-1--récupérer-le-jeton-api-dans-limoexpress)
   - [Étape 2 : Activer la synchronisation dans WordPress](#étape-2--activer-la-synchronisation-dans-wordpress)
   - [Étape 3 : Lier les véhicules WordPress aux classes LimoExpress](#étape-3--lier-les-véhicules-wordpress-aux-classes-limoexpress)
4. [Cycle de vie d'une réservation & Dispatch](#-cycle-de-vie-dune-réservation--dispatch)
5. [Facturation détaillée (Option C)](#-facturation-détaillée-option-c)
6. [Guide de Dépannage & Bonnes pratiques](#-guide-de-dépannage--bonnes-pratiques)
7. [Guide des Shortcodes](#-guide-des-shortcodes)

---

## ⚡ Prérequis

* **WordPress** : Version 5.8 ou supérieure (testé et certifié WordPress 6.x).
* **PHP** : Version 7.4, 8.0, 8.1 ou 8.2+.
* **Compte LimoExpress** : Disposant des accès administrateur pour générer un jeton d'intégration API.
* **Dépendances externes** : **0 dépendance** (aucun framework tiers, Vanilla JS ES6+ natif, CSS3 isolé).

---

## 📦 Installation rapide

1. Téléversez le dossier `elite-transfer-booking` dans le répertoire `/wp-content/plugins/` de votre site WordPress (ou installez l'archive `.zip` via **Extensions > Ajouter > Téléverser**).
2. Activez l'extension via le menu **Extensions** de WordPress.
3. Un nouveau menu principal nommé **🚗 Tour Booking** apparaît dans votre tableau de bord WordPress.

---

## 🚙 Configuration pas-à-pas de LimoExpress

Le plugin intègre un système d'**auto-découverte dynamique (Zéro-Hardcode)** : vous n'avez besoin de copier-coller aucun identifiant UUID technique complexe. Tout se sélectionne via des menus déroulants conviviaux.

---

### Étape 1 : Récupérer le jeton API dans LimoExpress

1. Connectez-vous à votre espace d'administration **LimoExpress** (`https://app.limoexpress.me`).
2. Dans le menu de gauche, rendez-vous dans :  
   👉 **Administration > Organization > Advanced Settings > API Integration**.
3. Copiez votre **Jeton API (Bearer Token)**.

---

### Étape 2 : Activer la synchronisation dans WordPress

1. Dans votre tableau de bord WordPress, allez dans :  
   👉 **Tour Booking > Réglages > Onglet Général**.
2. Faites défiler jusqu'à la section **🚙 Intégration LimoExpress**.
3. Cochez la case : **`[✓] Activer la synchronisation`** (*Transmettre automatiquement chaque réservation validée vers LimoExpress*).
4. Collez votre jeton dans le champ **Jeton API (Bearer Token)**.
5. Cliquez sur **Enregistrer les modifications**.

> 💡 **Magie de l'auto-découverte** :  
> Dès l'enregistrement du jeton, WordPress interroge votre compte LimoExpress et affiche automatiquement trois nouveaux menus déroulants :
> * **Client par défaut LimoExpress** : sélectionnez **`👤 Regular`** (ou le profil client de votre agence).
> * **Type de réservation LimoExpress** : sélectionnez **`📋 Transfert`** (ou *Location horaire* selon votre choix par défaut).
> * **Statut initial LimoExpress** : sélectionnez **`⏳ En attente / Pending`**.
6. Cliquez de nouveau sur **Enregistrer les modifications**.

---

### Étape 3 : Lier les véhicules WordPress aux classes LimoExpress

Chaque véhicule créé dans WordPress doit être associé à la classe correspondante dans LimoExpress (ex: *Van Class*, *S Class*, *Sprinter*) pour que les réservations soient dispatchées dans la bonne catégorie de flotte.

1. Dans WordPress, allez dans **Tour Booking > Véhicules**.
2. Cliquez sur **Modifier** sur un de vos véhicules (ex: *Mercedes V-Class*).
3. Dans la boîte **Détails du Véhicule**, repérez le champ :  
   👉 **🚙 Classe de Véhicule LimoExpress**.
4. Déroulez la liste et sélectionnez la classe LimoExpress correspondante (ex: `🚙 Van Class`).
5. Cliquez sur **Mettre à jour**.

> 🔄 **Astuce Rafraîchissement** :  
> Si vous venez de créer une nouvelle classe de véhicule dans LimoExpress (ex: *Sprinter*) et qu'elle n'apparaît pas encore dans WordPress, cliquez simplement sur le bouton **`🔄 Rafraîchir la liste LimoExpress`** situé juste sous le menu déroulant. Le cache se vide et la nouvelle classe apparaît instantanément !

---

## 🔄 Cycle de vie d'une réservation & Dispatch

Quand un client effectue une réservation sur votre site web :

```text
[ Client sur le site ] ──► [ Validation Métier & Anti-Spam ] ──► [ Commande créée dans WP ]
                                                                       │
             ┌─────────────────────────────────────────────────────────┘
             ▼
[ Création automatique du Client dans LimoExpress ] (Nom, E-mail, Téléphone international)
             │
             ▼
[ Calcul automatique des Points de Passage ] (Étapes de l'itinéraire + heures d'arrivée ajustées)
             │
             ▼
[ Transmission via /booking-with-fees/ ] ──► Course créée en direct dans LimoExpress (Pending)
```

### Ce qui est automatiquement transmis à LimoExpress :
* **Client** : Fiche client créée automatiquement sous son vrai nom et son vrai numéro de mobile (plus de passagers fantômes `@guest.local`).
* **Points de passage (Checkpoints)** : L'itinéraire complet du circuit avec le titre propre de chaque étape et son heure d'arrivée recalculée selon l'heure de départ choisie.
* **Capacités** : Nombre d'adultes, enfants, bagages et sièges bébé (qui active nativement les frais de siège enfant LimoExpress).
* **Notes professionnelles formatées** :
  * *Note chauffeur* : Synthèse claire de la mission, matériel requis et note client.
  * *Note répartiteur* : Détail complet de la commande, de la prestation et du code promo appliqué avec sa devise (ex: `Remise (PROMO50) : -396 €`).
* **Tarif** : Montant net exact calculé par WordPress.

---

## 📄 Facturation détaillée (Option C)

LimoExpress étant un logiciel de régulation de transport, l'édition de la facture client finale détaillée est prise en charge **directement par WordPress** :

1. Ouvrez n'importe quelle réservation dans **Tour Booking > Toutes les Réservations**.
2. Cliquez en haut sur le bouton noir **`📄 Voir / Imprimer la Facture`**.
3. Une page dédiée s'ouvre avec votre facture officielle décomposée :
   * En-tête aux couleurs de votre entreprise avec logo et coordonnées.
   * Références officielles (`INV-YYYY-XXXX`), dossier WordPress et numéro de course LimoExpress associée.
   * **Tableau d'articles complet ligne par ligne** : véhicule avec durée et taux horaire, suppléments de départ, extras détaillés (Guide, Champagne, Sièges...), déduction du code promo et total net à payer.
4. Cliquez sur **`🖨️ Imprimer / Enregistrer en PDF`** pour générer un PDF prêt pour votre comptabilité ou pour envoi au client.

---

## 🛡️ Guide de Dépannage & Bonnes pratiques

### 1. Que faire si une réservation affiche `❌ Échec` ?
Si une coupure réseau ou un jeton expiré empêche la synchronisation automatique :
1. La réservation **est toujours conservée en sécurité dans WordPress**.
2. L'administrateur reçoit immédiatement un e-mail d'alerte rouge avec le motif du rejet.
3. Ouvrez la commande dans WordPress : dans la carte *Trajet & Passagers*, cliquez sur le bouton :  
   👉 **`🔄 Transférer vers LimoExpress`**.
4. Le transfert est relancé et le badge passe au vert `✅ Synchronisé`.

### 2. Comment fonctionne le Bouclier Anti-Duplication ?
* **Sur les réservations** : Si une commande est déjà synchronisée, le bouton affiche `⚠️ Forcer un re-transfert LimoExpress`. Un clic dessus déclenche une confirmation obligatoire pour vous empêcher de créer une deuxième course par erreur.
* **Sur les clients** : Dès qu'un voyageur est enregistré dans LimoExpress, son identifiant est mémorisé sur la commande WordPress. Tout re-transfert ultérieur réutilise cet identifiant sans jamais créer de client en double.

---

## 🔌 Guide des Shortcodes

| Shortcode | Description | Exemple d'utilisation |
| :--- | :--- | :--- |
| **`[circuit_view id="XX"]`** | Affiche la vue complète en *Split Layout* (grille haute de véhicules à sélection unique + détails du circuit à gauche + widget de réservation sticky à droite). | `[circuit_view id="85"]` |
| **`[tour_booking]`** | Affiche le widget de réservation seul (idéal pour une barre latérale ou une page de transfert direct). | `[tour_booking]` |

---

*Documentation officielle d'exploitation — **Elite Transfer Booking v2.0.0** — Kaiser EM & Reich C.*
```
