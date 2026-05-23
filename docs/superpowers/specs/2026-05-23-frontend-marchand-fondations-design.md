# Design — Fondations frontend marchand branchées backend

**Date** : 2026-05-23
**Scope** : espace marchand initial, authentification marchand, dashboard compact, commandes en lecture seule
**Approche retenue** : brancher le frontend marchand sur les endpoints réels, avec une hypothèse MVP stricte : un marchand gère une seule supérette active.

---

## Objectif

Créer l'espace marchand initial, connecté aux vrais endpoints backend, sans actions métier de traitement des commandes.

La PR doit permettre à un marchand de se connecter, d'arriver dans son espace, de voir l'état compact de sa supérette et de consulter une liste réelle de commandes. Elle ne doit pas implémenter l'acceptation, le refus, la préparation, le passage en prête, le retrait ou le scan QR.

---

## Inclus

- Backend minimal : `GET /api/merchant/me`.
- Page de connexion : `/merchant/login`.
- Shell marchand protégé avec navigation latérale ou supérieure selon le responsive.
- Dashboard marchand compact : `/merchant`.
- Page commandes : `/merchant/commandes`, structurée mais en lecture seule, branchée sur la vraie liste.
- Navigation active entre `Dashboard` et `Commandes`.
- Entrées visibles mais désactivées : `Créneaux`, `Catalogue`, `Paramètres`.
- Hypothèse MVP : un marchand correspond à une supérette active.

---

## Backend minimal

### Endpoint

```http
GET /api/merchant/me
```

### Payload attendu

```json
{
  "user_id": "uuid",
  "email": "marchand@example.test",
  "roles": ["ROLE_MERCHANT"],
  "store": {
    "id": "uuid",
    "name": "Supérette El Amel",
    "active": true
  },
  "onboarding_completed": true
}
```

### Règles

- `401` si l'utilisateur n'est pas connecté.
- `403` si l'utilisateur connecté n'a pas le rôle marchand.
- `403` si le compte marchand est suspendu ou inactif.
- `404` si le marchand n'a aucune supérette active.
- `409` si le marchand possède plusieurs supérettes actives.
- Lecture seule : aucun changement d'état, aucune mutation métier.
- Pas d'étapes onboarding dans cette PR : `onboarding_completed` est exposé, mais le frontend ne force aucun parcours guidé.

---

## Endpoints frontend à utiliser

- `POST /api/auth/login` : connexion marchand.
- `GET /api/merchant/me` : résolution de la session et de la supérette active.
- `GET /api/merchant/stores/{storeId}/dashboard/today` : dashboard du jour.
- `GET /api/merchant/stores/{storeId}/orders` : commandes actives.
- `GET /api/merchant/stores/{storeId}/orders/history` : historique si l'onglet historique est livré dans la page commandes.

---

## UX marchand

### `/merchant/login`

- Formulaire email + mot de passe.
- Soumission vers `POST /api/auth/login`.
- Après succès, appel de `GET /api/merchant/me`.
- Redirection vers `/merchant` si le marchand a une supérette active unique.
- Message clair pour les états `401`, `403`, `404`, `409`.

### Shell marchand

- Affiche le nom de la supérette active.
- Affiche l'email du marchand en contexte secondaire.
- Navigation :
  - `Dashboard` actif sur `/merchant`.
  - `Commandes` actif sur `/merchant/commandes`.
  - `Créneaux`, `Catalogue`, `Paramètres` visibles mais désactivés.
- Les entrées désactivées ne déclenchent pas de navigation.

### Dashboard compact

- Utilise `GET /api/merchant/stores/{storeId}/dashboard/today`.
- Affiche les compteurs utiles du jour : commandes soumises, acceptées, en préparation, prêtes.
- Affiche les créneaux du jour si disponibles.
- Affiche un état vide si aucune commande ou aucun créneau pertinent n'existe.
- Lecture seule : aucun bouton `Accepter`, `Refuser`, `Préparer`, `Prête`, `Retrait`.

### Commandes

- Utilise `GET /api/merchant/stores/{storeId}/orders`.
- Liste réelle, structurée par statut ou triée par rendez-vous de retrait selon le payload existant.
- Chaque ligne affiche au minimum : numéro de commande, statut, client si disponible, créneau de rendez-vous, total en TND.
- Aucun lien vers détail commande si le détail n'est pas inclus dans cette PR.
- Si l'onglet historique est livré, il utilise `GET /api/merchant/stores/{storeId}/orders/history`.

---

## États à couvrir

- Session expirée : retour vers `/merchant/login` avec message de reconnexion.
- Non marchand : accès refusé, pas de dashboard.
- Marchand suspendu : accès refusé, pas de dashboard.
- Aucune supérette active : écran d'état expliquant qu'aucune supérette active n'est rattachée au compte.
- Plusieurs supérettes actives (`409`) : écran d'état demandant une correction admin, car le MVP suppose une seule supérette active.
- Dashboard vide : état calme indiquant qu'aucune activité n'est prévue aujourd'hui.
- Aucune commande : état vide dans `/merchant/commandes`.
- Erreur API : message inline avec action `Réessayer`.

---

## Exclusions explicites

- Détail commande.
- Accepter ou refuser une Kadhia.
- Acceptation partielle.
- Préparation d'une commande.
- Déclarer une commande prête.
- Retrait, scan QR, session de retrait, double validation.
- Gestion des créneaux.
- Catalogue marchand.
- Paramètres marchand.
- Thème, personnalisation, logo ou couleur de supérette.
- Paiement en ligne.
- Livraison.
- Programme de fidélité.
- Marketplace multi-marchands ou Kadhia partagée entre marchands.

---

## Tests recommandés

### Backend

- `GET /api/merchant/me` retourne `401` sans authentification.
- `GET /api/merchant/me` retourne `403` pour un client non marchand.
- `GET /api/merchant/me` retourne `403` pour un marchand suspendu.
- `GET /api/merchant/me` retourne `404` pour un marchand sans supérette active.
- `GET /api/merchant/me` retourne `409` pour un marchand avec plusieurs supérettes actives.
- `GET /api/merchant/me` retourne le payload attendu pour un marchand avec une seule supérette active.
- Le payload n'expose pas de données internes de persistance hors contrat.

### Frontend

- Le service `merchantAuth/me` appelle les bons endpoints et normalise les erreurs `401`, `403`, `404`, `409`.
- `/merchant/login` redirige vers `/merchant` après une connexion marchand valide.
- Le shell protège `/merchant` et `/merchant/commandes`.
- La navigation marque correctement `Dashboard` et `Commandes` comme actifs.
- `Créneaux`, `Catalogue`, `Paramètres` sont visibles et désactivés.
- Le dashboard affiche les données réelles, l'état vide et l'erreur avec `Réessayer`.
- La page commandes affiche la liste réelle, l'état vide et l'erreur avec `Réessayer`.

---

## Contraintes et risques

- Le backend dispose déjà de plusieurs endpoints marchand, mais `GET /api/merchant/me` doit rester minimal et explicite pour éviter de coupler le frontend à l'onboarding ou aux ressources admin.
- L'hypothèse "un marchand = une supérette active" simplifie le MVP ; le `409` évite de masquer un cas multi-supérettes non conçu côté frontend.
- La page commandes peut exposer un historique uniquement si cela reste en lecture seule et sans détail commande.
- Les libellés doivent rester en français pour cette PR, avec une structure compatible i18n ultérieure français/arabe.
