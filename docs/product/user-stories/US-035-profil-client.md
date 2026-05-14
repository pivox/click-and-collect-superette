# US-035 — Consulter et modifier son profil client

**Epic** : EPIC-013 — Compte client
**Sprint** : Sprint Auth — Authentification et compte
**Priorité** : Must Have

---

## Récit

En tant que **client connecté**,
je veux **consulter et mettre à jour mes informations personnelles**,
afin de **maintenir un profil exact et être joignable par le marchand si besoin**.

---

## Préconditions

- Le client est connecté (JWT valide).

---

## Scénario nominal — Consultation

1. Le client accède à « Mon profil » depuis le menu.
2. Il voit : nom, email, téléphone.
3. L'email est affiché mais non modifiable (identifiant de connexion).

---

## Scénario nominal — Modification

1. Le client clique sur « Modifier ».
2. Il peut changer : nom, téléphone.
3. Il sauvegarde.
4. Les modifications sont effectives immédiatement.

---

## Scénarios alternatifs

**Nom vide** :
- Erreur 422 : « Le nom est obligatoire. »

**Téléphone invalide** :
- Format attendu : numéro tunisien ou international.
- Erreur 422 si format invalide.

---

## Règles métier

- L'email ne peut pas être modifié dans le MVP (identifiant stable).
- Le mot de passe est géré séparément — la réinitialisation par email est couverte par US-046 (Sprint Auth, Must Have).
- Un client ne peut consulter et modifier que son propre profil.

---

## Critères d'acceptation

- [ ] Le client peut voir son nom, email et téléphone.
- [ ] Le client peut modifier son nom et son téléphone.
- [ ] L'email n'est pas modifiable depuis cette interface.
- [ ] Un nom vide est refusé.
- [ ] Les modifications sont persistées et reflétées immédiatement.

---

## Notes techniques

**Endpoints :**
```http
GET   /api/me/profile
PATCH /api/me/profile
```

**Réponse GET 200 :**
```json
{
  "id": "<uuid>",
  "email": "client@example.com",
  "name": "Fatima Ben Ali",
  "phone": "+21698765432",
  "created_at": "2026-05-01T10:00:00+01:00"
}
```

**Payload PATCH :**
```json
{
  "name": "Fatima Ben Salah",
  "phone": "+21698000000"
}
```

- Seuls `name` et `phone` sont patchables.
- Validation Symfony : `name` (NotBlank, Length max:100), `phone` (Regex ou nullable).
- Le champ `email` est ignoré s'il est envoyé dans le payload.
