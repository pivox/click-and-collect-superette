# Parcours client — Documentation frontend

Date : 2026-05-25
PR de référence : PR #126 (fondations), branchements API à venir

## Objectif

Ce document décrit le parcours complet du client dans l'app Kadhia : de l'ouverture de la supérette jusqu'à la validation du retrait. Il combine l'état actuel implémenté et la roadmap des chantiers restants.

## Architecture du parcours

```
(client)/                          → accueil
(client)/stores                    → liste des supérettes
(client)/stores/[shopId]           → détail supérette
(client)/stores/[shopId]/catalog   → catalogue
(client)/kadhia                    → Kadhia
(client)/kadhia/slot               → choix de créneau
(client)/orders                    → mes commandes
(client)/orders/[orderId]          → suivi commande
(client)/orders/[orderId]/pickup   → QR de retrait
```

> `(client)` est un groupe de layout Next.js (route group). Ce préfixe n'apparaît pas dans les URLs réelles — `/` est accessible à la racine, `/stores` est accessible tel quel, etc.

---

## Flux nominal

1. Le client ouvre l'app → accueil `/`
2. Il scanne le QR ou cherche une supérette → `/stores`
3. Il consulte la fiche supérette → `/stores/[shopId]`
4. Il parcourt le catalogue et ajoute des produits → `/stores/[shopId]/catalog`
5. Il vérifie sa Kadhia et ajuste les quantités → `/kadhia`
6. Il choisit un créneau de retrait et ajoute une note → `/kadhia/slot`
7. Il soumet la commande → `/orders/[orderId]` (suivi)
8. Le marchand valide — le statut évolue (polling)
9. Quand la commande est prête (`ready`), le CTA QR s'active
10. Le client présente le QR → `/orders/[orderId]/pickup`
11. Le marchand scanne, les deux valident → commande `completed`

## Cas alternatifs couverts côté backend

- **Refus marchand** : statut `rejected`, client notifié, retour à l'historique.
- **Acceptation partielle** : statut `partially_accepted`, Kadhia repasse en `draft`, le client doit la revalider et re-soumettre.
- **Annulation** : statut `cancelled` (délai expiré ou action admin).
- **Force completion** : le marchand peut forcer la clôture avec une note.

---

## Étapes détaillées

### Étape 1 — Accueil

**Route :** `/`  
**Statut :** livré (PR #126)

Flux nominal : Hero avec 3 supérettes en vedette, deux CTA "Scanner un QR code" et "Chercher une supérette".

Limitations connues :
- Les deux CTA redirigent vers `/stores` (liste manuelle) — le scan QR caméra réel n'est pas encore implémenté.

---

### Étape 2 — Liste des supérettes

**Route :** `/stores`  
**Statut :** livré (PR #126)

Flux nominal : liste complète des supérettes avec SearchInput. Mention "Scanner directement le QR à l'entrée" en bas de page.

Limitations connues :
- La recherche par nom n'est pas filtrée côté API (chargement complet).
- Pas de scan QR caméra intégré.

---

### Étape 3 — Détail supérette

**Route :** `/stores/[shopId]`  
**Statut :** livré (PR #126)

Flux nominal : fiche supérette avec horaires, distance, note, prochain créneau, bouton "Commencer ma Kadhia".

Cas alternatif :
- Supérette fermée : badge "Fermée" affiché, le CTA reste présent (pas de blocage UI).

Note : le backend (Sprint 7, S7-001) distingue deux états — `archivedAt !== null` (archivée définitivement, ne réapparaît plus) et fermeture temporaire selon les horaires. Le frontend mappe `archivedAt !== null → isActive: false` pour l'affichage, mais les deux cas ont un sens métier différent et devront être traités séparément lors du branchement API.

---

### Étape 4 — Catalogue

**Route :** `/stores/[shopId]/catalog`  
**Statut :** livré (PR #126)

Flux nominal : recherche produit, filtres par catégorie (pills), grille produits, ajout à la Kadhia avec badge compteur en temps réel.

Limitations connues :
- Les catégories viennent d'un mock local (`PRODUCT_CATEGORIES`) — pas encore tirées de l'API.

---

### Étape 5 — Kadhia

**Route :** `/kadhia`  
**Statut :** livré (PR #126)

Flux nominal : liste des lignes, modification de quantité, total en TND, résumé, bouton "Choisir un créneau".

Limitations connues :
- `DEMO_SHOP_ID = "shop-el-amel"` hardcodé — la Kadhia n'est pas encore liée dynamiquement à la supérette active.

---

### Étape 6 — Choix de créneau

**Route :** `/kadhia/slot`  
**Statut :** livré (PR #126)

Flux nominal : filtres par jour (aujourd'hui / demain / vendredi), grille de créneaux, note au marchand, bouton "Envoyer la commande".

Limitations connues :
- Les filtres jour sont semi-statiques (pas de dates dynamiques).
- La soumission redirige vers `/orders/CMD-4821` hardcodé — l'appel API `POST /api/me/stores/{shopId}/kadhias/.../submit` n'est pas encore branché.

---

### Étape 7 — Suivi commande

**Route :** `/orders/[orderId]`  
**Statut :** livré (PR #126)

Flux nominal : badge statut, résumé (créneau, total, code), timeline de suivi, CTA "Afficher le QR retrait" activé quand statut `ready` ou `pickup_pending`.

Limitations connues :
- La liste `/orders` utilise un `MOCK_ORDER` — pas encore branchée sur `GET /api/me/orders`.
- Pas de polling ou rafraîchissement automatique du statut.
- L'identifiant d'URL utilise le code (`CMD-4821`) au lieu de l'UUID backend.

---

### Étape 8 — QR de retrait

**Route :** `/orders/[orderId]/pickup`  
**Statut :** livré (PR #126)

Flux nominal : affichage du QR code et du code de commande, résumé supérette + créneau. Guard : redirect si statut ni `ready` ni `pickup_pending`.

Limitations connues :
- `QrPlaceholder` : pas de génération QR réelle — le vrai QR doit être basé sur le token `PickupSession` fourni par `GET /api/me/orders/{id}`.

---

## Roadmap — Prochains chantiers client

### P1 — Authentification client

Objectif : permettre au client de s'inscrire, se connecter et accéder à ses commandes personnelles.

À livrer :
- pages `/login` et `/register` ;
- contexte `ClientAuthContext` avec JWT stocké côté client ;
- protection des routes `/kadhia`, `/orders` (redirect si non connecté) ;
- consommation `POST /api/auth/register/customer`, `POST /api/auth/login`.

---

### P1 — Branchement API Kadhia et soumission de commande

Objectif : remplacer les mocks par les vrais appels backend.

À livrer :
- Kadhia liée dynamiquement au `shopId` actif (supprimer `DEMO_SHOP_ID`) ;
- soumission réelle via `POST /api/me/stores/{shopId}/kadhias` puis `POST /api/me/kadhias/{kadhiaId}/submit` ;
- liste des commandes `GET /api/me/orders` ;
- suivi commande `GET /api/me/orders/{id}` avec polling simple (intervalle recommandé : 10 s, mis en pause sur `visibilitychange` quand la page est en arrière-plan pour limiter la charge backend).

---

### P1 — QR de retrait réel

Objectif : afficher un QR scannable basé sur le token `PickupSession`.

À livrer :
- récupération du token via `GET /api/me/orders/{orderId}/pickup-session` (champs `token` et `qr_payload`) ;
- génération QR côté frontend (ex. `qrcode.react`) à partir de `qr_payload` ;
- remplacement de `QrPlaceholder` par le vrai composant.

---

### P1 — Scan QR caméra

Objectif : permettre au client de scanner le QR d'une supérette depuis l'app sans passer par la liste manuelle.

À livrer :
- accès caméra via `getUserMedia` ou une lib dédiée (ex. `html5-qrcode`) ;
- lecture du token `qrCodeToken` → redirect vers `/stores/[shopId]` ;
- fallback gracieux si la caméra est refusée ou indisponible.

Points d'attention :
- HTTPS obligatoire en production pour `getUserMedia` ;
- demande de permission caméra explicite avec message clair.

---

### P2 — Notifications client

Objectif : informer le client des changements de statut de sa commande sans push ni temps réel dans le MVP.

À livrer :
- page ou panneau `/notifications` ;
- badge de notifications non lues ;
- consommation `GET /api/me/notifications` ;
- actions `PATCH /api/me/notifications/{id}/read` et `PATCH /api/me/notifications/read-all`.

Note : veiller à l'ordre de déclaration des routes côté backend pour éviter un conflit entre `{id}/read` et `read-all` (même problématique que PR #139 côté notifications marchand).

---

### P2 — Gestion acceptation partielle

Objectif : guider le client quand le marchand accepte partiellement sa Kadhia.

À livrer :
- écran dédié sur la commande `partially_accepted` expliquant les lignes acceptées / refusées ;
- CTA "Modifier ma Kadhia" → retour sur `/kadhia` en mode `draft` ;
- re-soumission après modification.

---

### P2 — Localisation FR/AR + RTL

Objectif : rendre le parcours client disponible en arabe avec RTL.

À livrer :
- intégration `next-intl` ou équivalent ;
- traduction des labels clés (statuts, boutons, messages d'erreur) ;
- attribut `dir="rtl"` sur le layout quand arabe sélectionné ;
- vérification visuelle des composants en RTL (pills, cards, topbar, timeline).

---

### P3 — Recherche supérettes côté API

Objectif : filtrer les supérettes par nom côté serveur.

À livrer :
- endpoint `GET /api/stores/search?query=` (route dédiée backend `StoreSearchOutput`) ;
- debounce 400 ms sur le `SearchInput` de `/stores`.

---

## Chantiers hors MVP client

- Push notifications (mobile) ;
- Paiement en ligne ;
- Livraison ;
- Géolocalisation et tri par distance réelle ;
- Historique multi-supérettes avec panier partagé.
