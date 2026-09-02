Bien sûr. Voici une **fiche de référence complète du projet Elite Transfer Booking**, conçue spécialement pour pouvoir être donnée à un autre assistant IA (Claude, Gemini, ChatGPT, etc.) afin qu'il comprenne rapidement **l'objectif, l'architecture, les phases de développement, les règles de travail et l'état fonctionnel attendu**, sans avoir besoin de reprendre tout l'historique de nos conversations.

Je n'indique volontairement **aucun statut "terminé / en cours"** dans les phases.

---

# 📘 ELITE TRANSFER BOOKING — FICHE DE RÉFÉRENCE DU PROJET

## 1. Identité du projet

**Nom du plugin :** Elite Transfer Booking
**Type :** Plugin WordPress personnalisé
**Environnement de développement :** LocalWP
**CMS :** WordPress
**Architecture :** PHP côté serveur + JavaScript vanilla côté frontend + AJAX WordPress
**Version de développement observée :** `1.16.0`

Le plugin permet à un site de transfert/VTC/chauffeur privé de proposer un **widget de réservation frontend**, avec calcul dynamique du prix, sélection de véhicules, options supplémentaires et traitement serveur de la réservation.

---

# 2. 🎯 Objectif principal du plugin

L'objectif est de construire un système complet de réservation de transferts permettant au visiteur :

1. de choisir un ou plusieurs véhicules ;
2. de définir la quantité de chaque véhicule ;
3. de sélectionner le nombre de passagers ;
4. de renseigner le nombre de bagages ;
5. de choisir un point de départ ;
6. de sélectionner des options/extras ;
7. de renseigner ses coordonnées ;
8. de choisir une date et une heure ;
9. d'utiliser éventuellement un code promo ;
10. d'ajouter une demande spéciale ;
11. de voir le prix calculé dynamiquement ;
12. de soumettre sa réservation ;
13. de recevoir une confirmation par e-mail.

L'administrateur doit pouvoir :

* recevoir une notification de nouvelle réservation ;
* consulter la réservation dans WordPress ;
* voir les informations du client ;
* voir le trajet ;
* voir les véhicules et quantités ;
* voir les passagers ;
* voir les bagages ;
* voir les extras ;
* voir le code promo ;
* voir les notes ;
* voir le prix calculé côté serveur.

---

# 3. 🧱 Architecture générale

Le flux principal du plugin est :

```text
Utilisateur
    │
    ▼
Widget frontend
    │
    ├── Sélection véhicules
    ├── Passagers
    ├── Bagages
    ├── Pickup
    ├── Extras
    ├── Nom
    ├── Email
    ├── Date
    ├── Heure
    ├── Promo
    └── Note
    │
    ▼
JavaScript
    │
    ├── Validation frontend
    ├── Calcul / affichage du résumé
    └── FormData
    │
    ▼
WordPress AJAX
    │
    └── etb_submit_booking
    │
    ▼
class-ajax.php
    │
    ├── Nonce
    ├── Sanitization
    ├── Validation serveur
    ├── Vérification CPT
    ├── Vérification capacités
    ├── Pricing Engine
    ├── Création réservation
    ├── Sauvegarde metadata
    ├── wp_mail() client
    └── wp_mail() admin
    │
    ▼
CPT tour_booking
```

---

# 4. 📁 Structure principale du projet

Structure conceptuelle actuelle :

```text
elite-transfer-booking/
│
├── includes/
│   ├── class-elite-transfer-booking.php
│   ├── class-ajax.php
│   ├── class-cpt-manager.php
│   ├── class-meta-manager.php
│   ├── class-settings.php
│   ├── class-assets.php
│   ├── class-pricing-engine.php
│   └── class-shortcode.php
│
├── public/
│   ├── js/
│   │   └── booking-widget.js
│   │
│   └── css/
│       └── ...
│
├── templates/
│   └── booking-form.php
│
└── ...
```

Les noms exacts peuvent évoluer pendant le développement, mais cette architecture constitue la référence actuelle.

---

# 5. 🗃️ CPT utilisés

Le plugin utilise plusieurs Custom Post Types.

### `tour_booking`

Représente une réservation.

Informations associées notamment :

```text
_etb_customer_name
_etb_customer_email
_etb_booking_date
_etb_booking_time
_etb_pickup_id
_etb_adults
_etb_children
_etb_luggage
_etb_vehicles
_etb_extras
_etb_note
_etb_total_price
_etb_pricing_details
```

### `tour_vehicle`

Représente un véhicule disponible.

Les capacités utilisées comprennent notamment :

```text
_etb_max_pax
_etb_max_baggage
```

### `tour_pickup`

Représente un point de départ.

### `tour_extra`

Représente une option/extras disponible à la réservation.

---

# 6. 🧮 Pricing Engine

Le prix ne doit **jamais être considéré comme fiable lorsqu'il provient du navigateur**.

Le principe architectural est :

```text
Frontend
    ↓
données de réservation
    ↓
PHP
    ↓
ETB_Pricing_Engine
    ↓
prix recalculé côté serveur
```

Le prix enregistré dans :

```text
_etb_total_price
```

provient du calcul serveur.

Le détail est également sauvegardé dans :

```text
_etb_pricing_details
```

Cela permet notamment de protéger le système contre une modification arbitraire du prix via DevTools.

---

# 7. 🚗 Sélection multi-véhicules

Le système doit permettre par exemple :

```text
S Class × 1
Van × 2
Car Special × 1
```

Les données sont transmises sous la forme conceptuelle :

```text
etb_car_qty[vehicle_id] = quantity
```

Le backend reconstruit un tableau :

```php
$vehicles[ $vehicle_id ] = $qty;
```

Le serveur vérifie ensuite chaque véhicule avant de l'utiliser.

---

# 8. 👥 Gestion des passagers

Le système distingue :

```text
Adultes
Enfants
```

Le total :

```text
adultes + enfants
```

est comparé à la capacité totale des véhicules sélectionnés.

Exemple :

```text
Véhicule A
capacité = 4
quantité = 2

Capacité totale = 8

Passagers = 6

→ valide
```

Cette vérification existe côté frontend **et** côté serveur.

---

# 9. 🧳 Gestion des bagages

Chaque véhicule possède une capacité maximale de bagages.

Le système calcule :

```text
capacité bagages véhicule × quantité
```

puis compare avec :

```text
etb_total_luggage
```

La validation existe côté frontend et côté backend.

Le champ :

```text
etb_total_luggage
```

a actuellement une valeur par défaut de :

```text
0
```

`0` est considéré comme une valeur valide.

---

# 10. 📋 Validation frontend

La validation frontend doit fournir un retour rapide à l'utilisateur.

Les champs obligatoires comprennent notamment :

```text
Nom
Email
Date
Heure
Pickup
Véhicule
Adultes
```

Les champs configurables peuvent être affichés ou masqués via les réglages du plugin.

Une règle importante :

> Un champ désactivé dans les réglages et absent du DOM ne doit pas être considéré comme invalide.

---

# 11. 👁️ UX des messages d'erreur

Le système utilise une logique de type **touched state**.

Conceptuellement :

```js
touched = {
    name: false,
    email: false,
    date: false,
    time: false,
    vehicle: false
};
```

Cela permet de distinguer :

```text
champ invalide
```

de :

```text
champ invalide + utilisateur l'a déjà touché
```

Ainsi, au chargement :

```text
bouton désactivé
mais
pas une avalanche de messages d'erreur
```

Après interaction :

```text
champ invalide
→ message correspondant affiché
```

---

# 12. 🔐 Sécurité

Le backend doit considérer toutes les données frontend comme **non fiables**.

Le système utilise notamment :

```php
check_ajax_referer()
```

avec :

```text
etb_booking_nonce
```

Les données sont nettoyées avant utilisation.

Exemples :

```php
sanitize_text_field()
sanitize_email()
sanitize_textarea_field()
absint()
```

L'adresse e-mail doit également être validée avec :

```php
is_email()
```

Les IDs des CPT doivent être vérifiés côté serveur.

---

# 13. 🔄 AJAX

L'action AJAX principale est :

```text
etb_submit_booking
```

Elle accepte les utilisateurs connectés et non connectés :

```php
wp_ajax_etb_submit_booking
wp_ajax_nopriv_etb_submit_booking
```

Le frontend utilise :

```text
FormData
```

pour envoyer les données.

Le serveur effectue ensuite toute la validation avant de créer la réservation.

---

# 14. 💾 Création de la réservation

Après validation :

```text
Validation
    ↓
Pricing Engine
    ↓
wp_insert_post()
    ↓
tour_booking
    ↓
update_post_meta()
```

La réservation est créée avec notamment :

```text
post_type = tour_booking
post_status = pending
```

Les données détaillées sont ensuite enregistrées dans les métadonnées.

---

# 15. 📧 Système d'e-mail

Deux notifications sont générées.

### E-mail client

Destinataire :

```text
etb_email
```

Il contient notamment :

* nom ;
* numéro de réservation ;
* pickup ;
* date ;
* heure ;
* adultes ;
* enfants ;
* total passagers ;
* bagages ;
* véhicules ;
* quantités ;
* extras ;
* quantités ;
* code promo si présent ;
* demande spéciale si présente ;
* montant total ;
* devise.

---

### E-mail administrateur

Destinataire :

```text
etb_general_settings[admin_email]
```

avec fallback vers :

```text
get_option('admin_email')
```

Il contient les informations nécessaires au traitement de la réservation.

Le header :

```text
Reply-To
```

pointe vers le client.

Exemple :

```text
Reply-To: Frederic Godeat <oreacrey@gmail.com>
```

Ainsi, l'administrateur peut répondre directement au client.

---

# 16. 💰 Devise

La devise doit provenir des réglages :

```text
etb_general_settings[currency]
```

avec :

```text
EUR
```

comme fallback.

Il ne faut pas coder :

```text
€
```

directement dans le code des e-mails.

Exemple :

```text
182.00 EUR
```

---

# 17. 📬 `wp_mail()`

Le système utilise :

```php
wp_mail()
```

pour envoyer les deux notifications.

Point architectural important :

```text
Réservation enregistrée
        ↓
Tentative d'envoi e-mail
        ↓
Réponse AJAX
```

Un échec d'envoi d'e-mail ne doit pas transformer une réservation correctement enregistrée en :

```text
échec de réservation
```

Les résultats de `wp_mail()` doivent être contrôlés et les éventuelles erreurs peuvent être journalisées.

---

# 18. 🧪 Tests e-mail

Pour les tests locaux, **WP Mail Logging** peut être utilisé.

Il permet de vérifier :

```text
destinataire
sujet
contenu
headers
Reply-To
attachments
erreur retournée
```

Mais une distinction fondamentale doit être conservée :

```text
wp_mail() === true
```

signifie que WordPress a accepté/transmis la demande d'envoi.

Cela **ne signifie pas nécessairement** :

```text
Gmail a reçu le message
```

Dans LocalWP, il faut distinguer :

### Test interne

```text
WordPress
→ wp_mail()
→ système local / MailHog / logger
```

### Test réel

```text
WordPress
→ SMTP / serveur mail
→ Internet
→ Gmail/Outlook/etc.
```

---

# 19. ⚙️ Réglages du plugin

`class-settings.php` gère notamment :

### Réglages généraux

```text
currency
min_delay
admin_email
```

### Réglages formulaire

```text
show_vehicle
show_adults
show_children
show_pickup
show_extras
show_name
show_email
show_date
show_time
show_total_bag
show_promo
show_note
```

Un champ désactivé doit pouvoir disparaître du formulaire sans provoquer d'erreur dans le JavaScript.

---

# 20. 🏗️ PHASES DE DÉVELOPPEMENT

Voici la feuille de route complète à utiliser avec un autre assistant IA.

---

## PHASE 1 — Structure et fondations du plugin

Objectif :

Créer l'architecture de base du plugin.

Comprend notamment :

* fichier principal ;
* chargement des classes ;
* structure `includes/`;
* structure `public/`;
* structure `templates/`;
* initialisation du plugin ;
* protection contre l'accès direct aux fichiers.

---

## PHASE 2 — CPT et gestion des données

Objectif :

Créer les entités métier du système.

Comprend :

* `tour_booking`
* `tour_vehicle`
* `tour_pickup`
* `tour_extra`

ainsi que leurs métadonnées et capacités.

---

## PHASE 3 — Réglages administrateur

Objectif :

Permettre à l'administrateur de configurer le comportement du plugin.

Comprend :

* devise ;
* délai minimum ;
* e-mail administrateur ;
* visibilité des champs du formulaire.

Fichier central :

```text
includes/class-settings.php
```

---

## PHASE 4 — Widget de réservation frontend

Objectif :

Construire l'interface utilisateur.

Comprend :

* shortcode ;
* template ;
* champs ;
* véhicules ;
* quantités ;
* passagers ;
* bagages ;
* pickup ;
* extras ;
* informations client ;
* date/heure ;
* promo ;
* note ;
* résumé.

Fichier central :

```text
templates/booking-form.php
```

---

## PHASE 5 — Interaction JavaScript

Objectif :

Rendre le widget dynamique.

Comprend :

* carousel véhicules ;
* sélection multiple ;
* quantités ;
* extras ;
* calculs frontend ;
* mise à jour du résumé ;
* capacité passagers ;
* capacité bagages ;
* état du bouton ;
* événements utilisateur.

Fichier principal :

```text
public/js/booking-widget.js
```

---

## PHASE 6 — Validation frontend

Objectif :

Empêcher l'utilisateur de continuer avec des données manifestement invalides.

Comprend :

* nom ;
* email ;
* date ;
* heure ;
* pickup ;
* véhicule ;
* passagers ;
* bagages ;
* messages d'erreur ;
* logique `touched`.

La validation frontend améliore l'UX mais **ne remplace jamais la validation serveur**.

---

## PHASE 7 — Soumission et AJAX

Objectif :

Connecter réellement le formulaire au backend WordPress.

Comprend :

```text
FormData
    ↓
AJAX
    ↓
etb_submit_booking
```

avec :

* nonce ;
* utilisateurs connectés ;
* utilisateurs non connectés ;
* réception des données ;
* réponses JSON.

---

## PHASE 8 — Validation serveur

Objectif :

Revalider entièrement les données côté PHP.

Comprend :

* champs obligatoires ;
* email ;
* pickup ;
* véhicules ;
* extras ;
* passagers ;
* bagages ;
* capacités ;
* données provenant du navigateur ;
* vérification des CPT.

Principe fondamental :

> Le serveur ne fait jamais confiance à la validation JavaScript.

---

## PHASE 9 — Pricing Engine

Objectif :

Calculer le prix définitif côté serveur.

Comprend :

* prix véhicule ;
* quantité ;
* pickup/tarif ;
* extras ;
* quantité extras ;
* promotions ;
* total ;
* devise.

Le prix serveur devient la source de vérité.

---

## PHASE 10 — Création de réservation

Objectif :

Enregistrer une réservation validée.

Comprend :

```text
tour_booking
```

et toutes les métadonnées nécessaires.

Le prix serveur est également enregistré.

---

## PHASE 11 — Notifications e-mail

Objectif :

Informer automatiquement :

1. le client ;
2. l'administrateur.

Comprend :

* génération HTML ;
* destinataire client ;
* destinataire admin ;
* Reply-To ;
* devise ;
* détails réservation ;
* véhicules ;
* extras ;
* prix ;
* code promo ;
* note ;
* lien administration ;
* gestion des retours `wp_mail()` ;
* journalisation éventuelle des erreurs.

---

## PHASE 12 — Tests et fiabilisation

Objectif :

Vérifier l'ensemble du système.

Tests notamment :

### Frontend

* sélection véhicule ;
* multi-véhicules ;
* quantités ;
* passagers ;
* bagages ;
* extras ;
* validation ;
* champs conditionnels ;
* prix.

### Backend

* données invalides ;
* IDs falsifiés ;
* capacités ;
* prix manipulé ;
* nonce invalide ;
* réservation invalide.

### Réservation

* création CPT ;
* métadonnées ;
* statut ;
* prix.

### E-mails

* mail client ;
* mail admin ;
* Reply-To ;
* contenu ;
* destinataires ;
* WP Mail Logging ;
* réception réelle.

---

# 21. 🧭 Règles de travail pour un assistant IA

Ces règles sont importantes si tu transmets ce projet à Claude, Gemini ou un autre assistant.

### Règle 1 — Ne pas modifier sans validation

Avant une modification importante :

```text
AUDIT
↓
PLAN
↓
VALIDATION UTILISATEUR
↓
CODE
↓
TEST
```

---

### Règle 2 — Pas de refactoring inutile

Ne pas profiter d'une petite correction pour :

* réécrire une classe entière ;
* renommer toutes les variables ;
* changer l'architecture ;
* déplacer des fichiers ;
* modifier plusieurs fichiers sans nécessité.

Privilégier :

> **modification minimale et additive.**

---

### Règle 3 — Préserver les fonctionnalités validées

Une nouvelle phase ne doit pas casser :

* le carousel ;
* le multi-véhicules ;
* les extras ;
* le calcul ;
* la validation ;
* AJAX ;
* l'enregistrement.

---

### Règle 4 — Toujours distinguer frontend et backend

Le frontend est destiné à l'UX.

Le backend est la source de vérité.

```text
Frontend
→ aide l'utilisateur

Backend
→ décide si la réservation est valide
```

---

### Règle 5 — Ne jamais faire confiance au prix frontend

Le prix affiché dans JavaScript peut être manipulé.

Le serveur doit recalculer.

---

### Règle 6 — Tester après chaque phase

Après une modification :

```text
modifier
→ tester
→ confirmer
→ seulement ensuite continuer
```

---

# 22. 🧪 Exemple de flux complet attendu

Une réservation typique :

```text
Client :
Frederic Godeat

Email :
oreacrey@gmail.com

Pickup :
Saint Tropez

Date :
2026-08-29

Heure :
16:11

Passagers :
2 adultes
1 enfant

Bagages :
0

Véhicule :
S Class × 1

Extras :
Siège Bébé × 1
Guide × 1

Promo :
PROMO10

Note :
optionnelle
```

Le système :

```text
1. Validation frontend
        ↓
2. FormData
        ↓
3. AJAX etb_submit_booking
        ↓
4. Nonce
        ↓
5. Sanitization
        ↓
6. Validation serveur
        ↓
7. Vérification véhicules
        ↓
8. Vérification capacités
        ↓
9. Vérification extras
        ↓
10. Pricing Engine
        ↓
11. Création tour_booking
        ↓
12. Sauvegarde metadata
        ↓
13. Mail client
        ↓
14. Mail admin
        ↓
15. JSON success
```

---

# 23. 🎯 Vision finale du plugin

À terme, **Elite Transfer Booking** doit être un système de réservation WordPress complet et fiable, avec cette philosophie :

```text
                    ELITE TRANSFER BOOKING
                             │
          ┌──────────────────┼──────────────────┐
          │                  │                  │
      FRONTEND           BACKEND             ADMIN
          │                  │                  │
     UX rapide          Validation          Gestion
     dynamique          sécurisée           réservations
          │                  │                  │
     JavaScript             PHP               CPT
          │                  │                  │
          └────────────── AJAX ────────────────┘
                             │
                       Pricing Engine
                             │
                         Réservation
                             │
                    ┌────────┴────────┐
                    │                 │
                Mail client       Mail admin
```

**Principe central du projet :**

> Le frontend facilite la réservation, mais le backend reste l'autorité absolue sur la validité de la réservation, les capacités, les données et le prix.

---
