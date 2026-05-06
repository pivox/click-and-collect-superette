# US-028 — Gérer les comptes marchands

**Epic** : EPIC-009 — Administration plateforme
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Must Have

---

## Récit

En tant qu'**administrateur plateforme**,
je veux **créer, activer, suspendre et consulter les comptes marchands**,
afin de **contrôler qui peut accéder au backoffice marchand et proposer des commandes**.

---

## Préconditions

- L'administrateur est connecté à l'interface d'administration.

---

## Scénario nominal — Création d'un compte marchand

1. L'administrateur accède à la section « Marchands ».
2. Il clique sur « Créer un marchand ».
3. Il saisit : nom complet, email, téléphone, supérette(s) associée(s).
4. Le système crée le compte avec un mot de passe temporaire.
5. Un email d'invitation est envoyé au marchand.
6. Le marchand peut se connecter et changer son mot de passe.

---

## Scénario nominal — Suspension d'un compte

1. L'administrateur sélectionne un marchand actif.
2. Il clique sur « Suspendre ».
3. Il saisit une raison (optionnel).
4. Le marchand ne peut plus se connecter.
5. Les commandes en cours ne sont pas affectées.

---

## Scénarios alternatifs

**Email déjà utilisé** :
- Le système affiche : « Un compte avec cet email existe déjà. »

**Marchand suspendu tente de se connecter** :
- Le système retourne : « Votre compte est suspendu. Contactez l'administrateur. »

---

## Règles métier

- Un marchand est toujours associé à au moins une supérette.
- Un même compte marchand peut gérer plusieurs supérettes.
- La suspension ne supprime pas les données ni les commandes.
- L'administrateur ne peut pas accéder au backoffice marchand à la place du marchand (séparation des rôles).

---

## Critères d'acceptation

- [ ] L'administrateur peut créer un compte marchand avec email, nom et téléphone.
- [ ] Un email d'invitation est envoyé lors de la création.
- [ ] L'administrateur peut suspendre un compte marchand.
- [ ] Un compte suspendu ne peut pas se connecter.
- [ ] L'administrateur peut réactiver un compte suspendu.
- [ ] La liste des marchands est filtrables par statut (actif, suspendu).

---

## Notes techniques

- Endpoint : `POST /api/admin/merchants` avec `{ name, email, phone, shopIds: [] }`
- Endpoint : `PATCH /api/admin/merchants/{id}/suspend`
- Endpoint : `PATCH /api/admin/merchants/{id}/activate`
- Les rôles Symfony : `ROLE_MERCHANT`, `ROLE_ADMIN` (voter séparé par rôle).
- Mot de passe temporaire généré via `bin/console security:hash-password` ou token d'invitation.
