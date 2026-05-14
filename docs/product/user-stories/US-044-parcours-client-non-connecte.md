# US-044 — Parcours client non connecté après scan QR

**Epic** : EPIC-001 — Onboarding par QR code
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **visiteur non connecté**,
je veux **pouvoir consulter le catalogue d'une supérette après avoir scanné son QR code**,
et **être invité à me connecter ou créer un compte uniquement au moment d'ajouter un produit à ma Kadhia**,
afin de **ne pas être bloqué par un écran de login avant même de voir les produits**.

---

## Préconditions

- Le visiteur n'a pas de compte ou n'est pas connecté.
- Il a scanné le QR code d'une supérette.

---

## Parcours nominal

```
Scan QR code
→ fiche publique supérette (sans login)
→ catalogue public (sans login)
→ [clic sur "Ajouter à ma Kadhia"]
→ pop-up : "Connectez-vous pour composer votre Kadhia"
   ├── [Se connecter] → écran login → retour sur le catalogue avec le produit pré-ajouté
   └── [Créer un compte] → écran inscription → retour sur le catalogue avec le produit pré-ajouté
```

---

## Règles métier

- Le catalogue est accessible publiquement (endpoint existant, `PUBLIC_ACCESS`).
- La création d'une Kadhia nécessite `ROLE_CUSTOMER`.
- La redirection post-login conserve l'intention de l'utilisateur (produit à ajouter, supérette).
- L'URL de la supérette et le produit sélectionné sont passés en paramètre de redirection.
- Aucun "mode invité" : la Kadhia nécessite un compte pour être persistée.

---

## Critères d'acceptation

- [ ] Un visiteur non connecté peut voir la fiche supérette et le catalogue sans login.
- [ ] La tentative d'ajout à la Kadhia déclenche une invite de connexion/inscription.
- [ ] Après connexion ou inscription, le visiteur est redirigé vers le catalogue, le produit est ajouté automatiquement.
- [ ] Si le visiteur annule la connexion, il revient sur le catalogue sans être bloqué.
- [ ] Le message de l'invite est clair : pas de jargon technique, orienté bénéfice.

---

## Contenu de l'invite (UX)

**FR :**
> « Pour ajouter ce produit à votre Kadhia, connectez-vous ou créez un compte gratuit. »
> [Se connecter] [Créer un compte]

**AR :**
> « لإضافة هذا المنتج إلى قضيتك، سجّل دخولك أو أنشئ حسابًا مجانيًا. »

---

## Notes techniques

- Côté frontend (Next.js) : stocker l'intention dans `sessionStorage` (`{ storeId, merchantProductId, quantity }`) avant la redirection vers `/login`.
- Après authentification réussie, le middleware de login lit `sessionStorage` et appelle automatiquement `PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}`.
- L'URL contient le `storeId` pour permettre la re-création du contexte après retour.
- Paramètre de redirection : `/login?redirect=/stores/{storeId}/catalog&addProduct={merchantProductId}`.
