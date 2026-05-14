# US-046 — Réinitialiser son mot de passe oublié

**Epic** : EPIC-013 — Compte client
**Sprint** : Sprint Auth — Authentification et compte
**Priorité** : Must Have

---

## Récit

En tant que **client ou marchand**,
je veux **réinitialiser mon mot de passe si je l'ai oublié**,
afin de **retrouver l'accès à mon compte sans contacter le support**.

---

## Préconditions

- L'utilisateur a un compte existant.
- L'utilisateur ne se souvient plus de son mot de passe.

---

## Parcours nominal

```
Écran de login
→ [Mot de passe oublié ?]
→ Saisie de l'email
→ Réception d'un email avec lien de réinitialisation (valable 1 heure)
→ Clic sur le lien → formulaire de nouveau mot de passe
→ Confirmation → redirection vers login
```

---

## Scénarios alternatifs

**Email inexistant :**
- Le système répond toujours : « Si un compte existe avec cet email, vous recevrez un lien. »
- Ne pas confirmer ni infirmer l'existence du compte (sécurité).

**Token expiré :**
- Le lien retourne : « Ce lien a expiré. Demandez un nouveau lien. »

**Token déjà utilisé :**
- Le lien retourne : « Ce lien a déjà été utilisé. »

---

## Règles métier

- Le token de réinitialisation est opaque (UUID v4), à usage unique, valable 1 heure.
- Une nouvelle demande invalide le token précédent.
- Le mot de passe doit respecter les mêmes règles que l'inscription (min 8 caractères).
- L'email de réinitialisation est envoyé en français par défaut, en arabe si la préférence est connue.
- Applicable aux clients (`ROLE_CUSTOMER`) et marchands (`ROLE_MERCHANT`).

---

## Critères d'acceptation

- [ ] L'utilisateur peut demander un lien de réinitialisation avec son email.
- [ ] Un email contenant un lien valide 1 heure est envoyé.
- [ ] Le lien permet de définir un nouveau mot de passe.
- [ ] Un token expiré ou déjà utilisé affiche un message clair.
- [ ] Une nouvelle demande invalide le token précédent.
- [ ] L'email ne confirme pas l'existence du compte (sécurité).

---

## Notes techniques

**Nouvelle entité `PasswordResetToken` :**
```text
password_reset_tokens
- id (uuid)
- user_id
- token (varchar 64, unique)
- expires_at
- used_at (nullable)
- created_at
INDEX(token)
```

**Endpoints :**
```http
POST /api/auth/forgot-password
Body: { "email": "client@example.com" }
Réponse 200 toujours (ne pas révéler l'existence du compte)

POST /api/auth/reset-password
Body: { "token": "<uuid>", "password": "nouveaumotdepasse" }
Réponse 200 : { "message": "Mot de passe mis à jour." }
```

**Email :** Symfony Mailer + template Twig bilingue. En développement : Mailer DSN `null://` ou Mailpit.

**Sécurité :** token généré avec `bin2hex(random_bytes(32))` (64 chars hex), hashé en base si nécessaire.
