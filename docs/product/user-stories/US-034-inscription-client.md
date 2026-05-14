# US-034 — S'inscrire en tant que client

**Epic** : EPIC-013 — Compte client
**Sprint** : Sprint Auth — Authentification et compte
**Priorité** : Must Have

---

## Récit

En tant que **nouveau visiteur**,
je veux **créer mon compte client**,
afin de **pouvoir composer une Kadhia, soumettre des commandes et accéder à mon historique**.

---

## Préconditions

- L'utilisateur n'a pas encore de compte.
- L'application est accessible depuis le navigateur (PWA).

---

## Scénario nominal

1. L'utilisateur clique sur « Créer un compte » depuis l'écran de connexion.
2. Il saisit : prénom et nom, email, mot de passe, téléphone (optionnel).
3. Il valide le formulaire.
4. Le système crée le compte avec le rôle `ROLE_CUSTOMER`.
5. Le système retourne un JWT valide.
6. L'utilisateur est immédiatement connecté et redirigé vers l'écran principal.

---

## Scénarios alternatifs

**Email déjà utilisé** :
- Le système retourne une erreur 422 avec le message : « Un compte avec cet email existe déjà. »

**Mot de passe trop court** :
- Minimum 8 caractères. Erreur 422 si non respecté.

**Email invalide** :
- Validation format RFC 5322. Erreur 422.

---

## Règles métier

- Le rôle `ROLE_CUSTOMER` est attribué automatiquement. L'utilisateur ne peut pas choisir son rôle.
- L'email est unique et sert d'identifiant de connexion.
- Le mot de passe est hashé côté serveur (bcrypt). Jamais retourné dans la réponse.
- Aucune vérification d'email par lien n'est requise dans le MVP (à prévoir post-MVP).
- Un compte créé est actif immédiatement.

---

## Critères d'acceptation

- [ ] Un utilisateur peut créer un compte avec email + mot de passe + nom.
- [ ] La réponse contient un JWT utilisable immédiatement.
- [ ] Un email déjà utilisé retourne une erreur explicite.
- [ ] Un mot de passe de moins de 8 caractères est refusé.
- [ ] Le rôle `ROLE_CUSTOMER` est attribué sans intervention admin.
- [ ] Le compte est visible dans l'admin (liste des clients).

---

## Notes techniques

**Endpoint :**
```http
POST /api/auth/register/customer
```

**Payload :**
```json
{
  "email": "client@example.com",
  "password": "motdepasse123",
  "name": "Fatima Ben Ali",
  "phone": "+21698765432"
}
```

**Réponse 201 :**
```json
{
  "token": "<jwt>",
  "user": {
    "id": "<uuid>",
    "email": "client@example.com",
    "name": "Fatima Ben Ali"
  }
}
```

- Entité `User` avec `roles: ['ROLE_CUSTOMER']`.
- Contrainte unique sur `email`.
- Validation Symfony sur `email`, `password` (NotBlank, Length min:8), `name` (NotBlank).
- Aucun refresh token dans le MVP (JWT 1h, ADR-0003).
