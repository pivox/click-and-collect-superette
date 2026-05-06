# US-001 — Scanner le QR code d'une supérette

**Epic** : EPIC-001 — Onboarding par QR code
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **scanner le QR code affiché dans une supérette**,
afin d'**accéder immédiatement à l'espace digital de cette supérette sans recherche manuelle**.

---

## Préconditions

- Le client dispose d'un smartphone avec appareil photo ou application de scan.
- Le QR code de la supérette est actif et valide.
- La supérette est active sur la plateforme.

---

## Scénario nominal

1. Le client ouvre l'application ou le navigateur mobile.
2. Il scanne le QR code affiché en vitrine ou en caisse.
3. Le système décode l'URL contenue dans le QR code.
4. Le système vérifie que la supérette est active.
5. Le client est redirigé vers la page d'accueil de la supérette.
6. La supérette est ajoutée automatiquement à la liste des supérettes du client (si connecté).

---

## Scénarios alternatifs

**QR code invalide** :
- Le système affiche un message d'erreur clair : « Ce QR code n'est pas reconnu. »
- Le client peut réessayer ou fermer.

**Supérette inactive ou suspendue** :
- Le système affiche : « Cette supérette n'est pas disponible pour le moment. »

**Client non connecté** :
- Le client accède à la page de la supérette en mode visiteur.
- Un bandeau invite à créer un compte ou se connecter pour passer commande.

---

## Règles métier

- Un QR code est unique par supérette.
- Le QR code encode une URL du type `/shops/{shopSlug}`.
- Une supérette inactive ne peut pas être accédée via QR code.
- L'ajout automatique aux favoris ne se fait que si le client est connecté.

---

## Critères d'acceptation

- [ ] Le scan d'un QR code valide ouvre la page de la supérette correspondante en moins de 2 secondes.
- [ ] Le scan d'un QR code invalide affiche un message d'erreur sans planter l'application.
- [ ] Le scan d'un QR code d'une supérette inactive affiche un message dédié.
- [ ] La supérette apparaît dans la liste des supérettes du client connecté après le premier scan.
- [ ] Le parcours fonctionne sur iOS et Android via le navigateur mobile (PWA).

---

## Notes techniques

- Le QR code encode une URL publique, pas un token opaque.
- L'endpoint `GET /api/shops/{slug}` vérifie le statut actif de la supérette.
- L'ajout aux favoris est géré par `POST /api/customer/shops` (idempotent).
- Pas de native camera SDK requis pour le MVP : utilisation de l'API `getUserMedia` ou d'un composant PWA.
