# S15-018 à S15-035 — Gestion compte marchand et onboarding admin

Issue chapeau : #498  
Statut : cadrage PO / documentation  
Sprint : Sprint 15 — Monétisation, support, exploitation & onboarding catalogue  
Date de cadrage : 2026-06-13  
Rôles concernés : admin, marchand

---

## 1. Objectif produit

Permettre à l'équipe plateforme d'onboarder une supérette de bout en bout depuis le backoffice admin :

```text
Admin
→ crée le compte marchand
→ crée la supérette
→ rattache la supérette au marchand
→ choisit le mode de première connexion
→ peut précharger le catalogue avec des groupements existants
→ le marchand finalise son accès
→ le marchand gère ensuite son compte, sa supérette et son catalogue
```

Cette fonctionnalité complète l'administration minimale et le Sprint 15 d'onboarding catalogue.

Le besoin terrain est simple : l'équipe commerciale doit pouvoir installer un marchand sans lui demander de tout configurer seul dès le premier contact.

---

## 2. Décision PO

La capacité est découpée en quatre blocs indépendants pour garder des PRs atomiques :

```text
S15-018 — Gestion du compte marchand
S15-019 — Onboarding admin marchand + supérette
S15-020 — Première connexion marchand
S15-021 — Préchargement catalogue admin via groupements existants
```

Chaque bloc peut être livré séparément.

Règle structurante :

```text
Compte marchand = User
Supérette = Shop
Catalogue supérette = MerchantProduct
Groupement produit = outil d'onboarding catalogue, pas pack commercial client
```

---

## 3. Alignement avec l'existant

### 3.1 Roadmap active

Ne pas réintroduire l'ancienne roadmap supprimée :

```text
docs/roadmap/mvp-roadmap.md
```

La roadmap active reste :

```text
docs/Sprint15/README.md
docs/roadmap/launch-readiness-reorganization.md
```

### 3.2 Groupements produits déjà livrés

Les groupements produits ne doivent pas être recréés dans cette série.

Le bloc existant couvre déjà :

```text
#464 — S15-006 — Admin — Créer des groupements de produits référentiel
#465 — S15-007 — Marchand — Voir et sélectionner un groupement de produits
#466 — S15-008 — Marchand — Importer un groupement sans doublon catalogue
#467 — S15-009 — Marchand — Compléter les prix après import groupé
```

Donc le nouveau périmètre est uniquement :

```text
Réutiliser ces groupements dans le parcours admin de création supérette.
```

Les issues #518 et #519 ont été fermées comme doublons de #464 / #475.

---

## 4. Parcours cible complet

### 4.1 Création par admin

```text
1. L'admin ouvre le backoffice.
2. L'admin saisit les informations du marchand.
3. L'admin saisit les informations de la supérette.
4. L'admin choisit le mode d'activation.
5. L'admin sélectionne éventuellement des groupements produits existants.
6. Le système crée le compte, la supérette et les liens nécessaires.
7. Le système prépare le catalogue initial si des groupements sont sélectionnés.
8. L'admin voit un résumé.
```

### 4.2 Activation côté marchand

Deux modes sont prévus :

```text
Mode 1 — Invitation email
Le marchand reçoit un lien expirant à usage unique et définit son accès.

Mode 2 — Accès temporaire terrain
L'admin ou le commercial remet un accès temporaire au marchand.
À la première connexion, le marchand doit définir son accès définitif avant d'ouvrir le dashboard.
```

Le mode email reste le mode recommandé. Le mode terrain existe pour les cas où le commercial configure la boutique avec le marchand en face de lui.

---

## 5. Epic S15-018 — Gestion du compte marchand

Issue epic : #499

### Objectif

Permettre au marchand de gérer les informations de son compte utilisateur, indépendamment du profil de sa supérette.

### Périmètre inclus

```text
- consultation du profil compte marchand ;
- modification prénom, nom, téléphone, langue ;
- changement sécurisé de l'accès ;
- page marchand Mon compte ;
- documentation API ;
- tests de non-régression.
```

### Hors périmètre

```text
- création par admin ;
- première connexion ;
- profil supérette ;
- multi-utilisateurs par supérette ;
- gestion fine des permissions employé.
```

### Issues atomiques

```text
#503 — S15-022 — Profil marchand connecté
#504 — S15-023 — Sécurité compte marchand
#505 — S15-024 — Page Mon compte marchand
#506 — S15-025 — Documentation profil marchand
```

---

## 6. Epic S15-019 — Onboarding admin

Issue epic : #500

### Objectif

Permettre à l'admin de créer un compte marchand et une supérette rattachée.

### Périmètre inclus

```text
- création compte marchand par admin ;
- email unique ;
- rôle marchand forcé côté serveur ;
- création supérette par admin ;
- rattachement de la supérette au marchand ;
- endpoint combiné d'onboarding si pertinent ;
- formulaire backoffice admin.
```

### Hors périmètre

```text
- première connexion détaillée ;
- préchargement catalogue ;
- facturation ;
- abonnement ;
- KYC complet ;
- contrat commercial.
```

### Issues atomiques

```text
#507 — S15-026 — Admin compte
#508 — S15-027 — Admin boutique
#509 — S15-028 — Admin onboarding combiné
#510 — S15-029 — UI admin
#511 — S15-030 — QA onboarding admin
```

---

## 7. Epic S15-020 — Première connexion marchand

Issue epic : #501

### Objectif

Permettre au marchand nouvellement créé de finaliser son accès de manière sécurisée.

### Périmètre inclus

```text
- invitation email ;
- accès temporaire terrain ;
- expiration ;
- usage unique si applicable ;
- redirection obligatoire vers finalisation ;
- blocage du dashboard tant que la première connexion n'est pas finalisée ;
- écrans front dédiés ;
- tests de sécurité.
```

### Règles non négociables

```text
- l'admin ne doit jamais connaître l'accès définitif du marchand ;
- l'accès temporaire ne doit pas permettre une utilisation durable ;
- la première connexion doit forcer la finalisation ;
- aucune page métier marchand ne doit être accessible avant finalisation ;
- les secrets, tokens et hashes ne sont jamais retournés par l'API.
```

### Issues atomiques

```text
#512 — S15-031 — Invitation première connexion marchand
#513 — S15-032 — Accès temporaire première connexion marchand
#514 — S15-033 — Garde première connexion marchand
#515 — S15-034 — Écran invitation première connexion
#516 — S15-035 — Écran finalisation accès marchand
#517 — S15-036 — Tests première connexion marchand
```

---

## 8. Epic S15-021 — Préchargement catalogue admin

Issue epic : #502

### Objectif

Permettre à l'admin de sélectionner des groupements produits existants lors de l'onboarding d'une supérette.

### Périmètre inclus

```text
- sélection de groupements dans le formulaire admin ;
- application des groupements à la supérette créée ;
- réutilisation de l'import idempotent existant ;
- résumé : produits ajoutés, déjà présents, ignorés ;
- conservation de la règle prix à compléter.
```

### Hors périmètre

```text
- recréer ProductGroup ;
- recréer ProductGroupItem ;
- recréer le CRUD admin groupements ;
- recréer l'import marchand ;
- packs commerciaux client ;
- suggestions intelligentes.
```

### Issues atomiques restantes

```text
#520 — S15-032 — Préchargement catalogue backend
#521 — S15-033 — UI groupements onboarding
#522 — S15-034 — Résumé préchargement catalogue
#523 — S15-035 — QA préchargement catalogue
```

Note : la numérotation de ces quatre issues peut être ajustée si l'équipe souhaite éviter le chevauchement avec les issues de première connexion. Le fond produit reste distinct.

---

## 9. Contrats API cibles

### 9.1 Compte marchand connecté

Décision d'implémentation #503 à #506 : conserver et durcir les endpoints
marchands déjà utilisés par le front, sans créer de doublon.

```http
GET   /api/merchant/me
PATCH /api/merchant/me
PATCH /api/merchant/me/password
POST  /api/merchant/first-login/change-password
```

Contrat appliqué :

- `User` = compte marchand : email de connexion, prénom, nom, téléphone,
  mot de passe hashé, statut interne ;
- `Shop` = supérette : nom public, adresse, téléphone visible client, QR code,
  apparence et catalogue ;
- le profil compte marchand ne modifie jamais la supérette ni son owner ;
- champs modifiables par le marchand : `first_name`, `last_name`, `phone` ;
- email non modifiable depuis `/api/merchant/me` ;
- mot de passe changé via `/api/merchant/me/password` avec ancien mot de passe
  obligatoire, nouveau mot de passe validé et hashé, réponse `204` ;
- `password_change_required` est exposé par `GET /api/merchant/me` pour orienter
  le marchand vers la finalisation de son accès ;
- champs interdits/refusés : `roles`, `active`/`is_active`, `status`, owner
  shop, `shop_id`, `password`, `passwordHash`, `plainPassword`, reset token,
  invitation token, temporary password, `deletedAt`, `lastLoginAt` ;
- les réponses profil compte n'exposent aucun rôle, token, hash, mot de passe,
  owner shop ou champ interne admin ;
- la préférence de langue marchand reste gérée côté interface (`merchant:lang`)
  tant qu'aucun champ `language`/`locale` n'existe sur `User`.

### 9.2 Onboarding admin

```http
POST /api/admin/merchants
POST /api/admin/stores
POST /api/admin/merchant-onboarding
```

Le endpoint combiné doit rester une orchestration transactionnelle, pas un contrôleur monolithique.

Implémentation MVP :

- `POST /api/admin/merchant-onboarding` crée le compte marchand, la supérette et le rattachement `Shop.owner` dans une transaction unique ;
- le mode de première connexion supporte maintenant `temporary_password` et `email_invitation` ;
- `temporary_password` conserve le comportement terrain : le mot de passe provisoire est retourné uniquement dans la réponse immédiate ;
- `email_invitation` réutilise l'infrastructure d'invitation existante (`MerchantInvitationTokenManager`, sender email, token opaque hashé en base) et ne retourne jamais le token brut ;
- `password_change_required` passe à `true` jusqu'à finalisation de la première connexion ;
- l'accès temporaire porte une expiration `expires_at`, configurable via
  `MERCHANT_TEMPORARY_PASSWORD_TTL` avec un défaut de 7 jours ;
- l'invitation email porte une expiration `expires_at`, configurable via
  `MERCHANT_INVITATION_TOKEN_TTL` avec un défaut de 7 jours, et la réponse
  retourne `invitation_status: sent` ;
- l'endpoint peut appliquer zéro, un ou plusieurs groupements produits publiés et visibles marchand ;
- le résumé de préchargement retourne les compteurs `added_count`, `already_existing_count`, `ignored_count` et les erreurs métier.

### 9.3 Première connexion

```http
POST /api/merchant/first-login/change-password
POST /api/admin/merchants/{merchantId}/invitation
POST /api/admin/merchants/{merchantId}/invitation/resend
POST /api/auth/merchant-invitations/verify
POST /api/auth/merchant-invitations/complete
```

Implémentation livrée pour les deux modes de première connexion :

- le marchand se connecte avec le mot de passe provisoire ;
- `/api/merchant/me` et la réponse login exposent `password_change_required` ;
- tant que `password_change_required = true`, les endpoints métier marchand sont
  bloqués côté backend avec `MERCHANT_PASSWORD_CHANGE_REQUIRED` ;
- le marchand appelle `POST /api/merchant/first-login/change-password` avec
  `current_password`, `new_password` et `new_password_confirmation` ;
- si l'accès temporaire est expiré, le login et la finalisation première
  connexion sont refusés avec `MERCHANT_TEMPORARY_PASSWORD_EXPIRED` ;
- après succès, le mot de passe définitif est hashé, `password_change_required`
  repasse à `false`, les dates temporaires sont vidées et le dashboard marchand
  redevient accessible ;
- en mode invitation email, l'admin ne voit jamais le token brut : il est envoyé
  uniquement par email, stocké uniquement sous forme de hash et consommé à usage
  unique quand le marchand définit son mot de passe définitif ;
- aucun secret, hash, token d'invitation ni mot de passe provisoire n'est exposé
  hors réponse immédiate admin.

Note d'implémentation partielle #530 :

```http
POST /api/admin/merchants/{merchantId}/temporary-password
```

Cet endpoint permet à l'admin de générer un mot de passe temporaire pour un marchand existant. Le mot de passe temporaire est affiché une seule fois dans la réponse, n'est jamais stocké en clair et n'est jamais écrit dans le journal d'audit. L'admin ne peut pas voir le mot de passe actuel.

Le reset remet aussi `password_change_required` à `true`.
Il remplace intégralement l'accès temporaire précédent : l'ancien mot de passe
est refusé, une nouvelle expiration `expires_at` est calculée, et l'action est
auditée sans secret.

Implémentation invitation email #512 :

- l'admin peut envoyer une invitation à un marchand existant via
  `POST /api/admin/merchants/{merchantId}/invitation` ;
- la cible doit être un marchand actif et non supprimé ;
- l'admin peut renvoyer une invitation via
  `POST /api/admin/merchants/{merchantId}/invitation/resend` ;
- le renvoi révoque toute invitation pending précédente pour éviter plusieurs
  liens actifs concurrents ;
- le token brut est opaque, généré aléatoirement, envoyé uniquement par email
  et stocké uniquement sous forme de hash SHA-256 ;
- l'expiration est configurable via `MERCHANT_INVITATION_TOKEN_TTL`, avec un
  défaut de 7 jours ;
- le marchand peut vérifier le lien via
  `POST /api/auth/merchant-invitations/verify` ;
- le marchand définit son mot de passe définitif via
  `POST /api/auth/merchant-invitations/complete` avec `token`,
  `new_password` et `new_password_confirmation` ;
- les tokens expirés, utilisés, révoqués, invalides ou liés à un compte non
  éligible sont refusés ;
- après succès, le token est consommé atomiquement, le mot de passe est hashé
  et `password_change_required` repasse à `false` ;
- les actions admin sont auditées avec `merchant.invitation.create` et
  `merchant.invitation.resend`, sans token, hash, mot de passe ni secret.

Implémentation écran invitation #515 :

- le lien email pointe vers `/merchant/invitation?token=...` ;
- la page vérifie le token via `POST /api/auth/merchant-invitations/verify` ;
- le marchand définit son mot de passe définitif via
  `POST /api/auth/merchant-invitations/complete` ;
- les états token manquant, invalide, expiré, utilisé ou révoqué affichent un
  message dédié sans exposer le token ;
- après succès, le marchand est redirigé vers `/merchant/login`.

État de clôture #501 au 19 juin 2026 :

- #512 invitation email marchand est livrée via PR #546 ;
- #513 accès temporaire terrain est livré via PR #548 ;
- #517 QA première connexion marchand est livrée via PR #549 ;
- #515 écran frontend d'invitation est livré par la PR dédiée.

Décision : après merge de #515, reprendre la clôture #501 en vérifiant que la QA
frontend invitation email et la documentation restent alignées.

### 9.4 Préchargement catalogue admin

Implémenté dans l'orchestration admin via le service d'import groupement partagé.

Choix produit #552 : l'application admin de groupements après création n'est pas
exposée comme endpoint autonome dans cette PR. Pour l'instant, l'admin peut
précharger des groupements pendant l'onboarding combiné ; après création,
l'application d'un groupement reste portée côté marchand propriétaire via le
parcours déjà livré.

Option autonome non retenue dans cette tranche :

```http
POST /api/admin/stores/{storeId}/product-groups/apply
```

Inclusion livrée dans l'orchestration :

```json
{
  "merchant": {},
  "shop": {},
  "first_login_mode": "temporary_password|email_invitation",
  "product_group_ids": ["uuid-1", "uuid-2"]
}
```

Règles appliquées : pas de doublon catalogue, pas de doublon entre groupements, produits ajoutés avec prix à compléter et invisibles côté client.

---

## 10. Ordre recommandé des PRs

```text
1. #503 + #504 — backend compte marchand
2. #505 — page Mon compte marchand
3. #506 — documentation / QA compte marchand

4. #507 — création compte marchand admin
5. #508 — création supérette admin
6. #509 — endpoint combiné onboarding
7. #510 — formulaire admin
8. #511 — QA onboarding admin

9. #512 — invitation première connexion
10. #513 — accès temporaire terrain
11. #514 — garde première connexion
12. #515 — écran invitation
13. #516 — écran finalisation
14. #517 — QA première connexion

15. #520 — préchargement catalogue backend
16. #521 — sélection groupements dans l'UI admin
17. #522 — résumé de préchargement
18. #523 — QA préchargement catalogue
```

---

## 11. Critères d'acceptation globaux

```text
- L'admin peut créer un marchand.
- L'admin peut créer une supérette rattachée au marchand.
- Le marchand peut finaliser son accès.
- Le marchand ne peut pas ouvrir le dashboard avant finalisation.
- Le marchand peut gérer son compte séparément de sa supérette.
- L'admin peut précharger un catalogue avec des groupements existants.
- Le préchargement ne crée pas de doublons.
- Les produits sans prix restent invisibles côté client.
- Les champs sensibles ne sont jamais exposés.
- Les actions sensibles sont auditées si l'audit trail existant le permet.
```

---

## 12. Points d'attention PO / Tech Lead

### 12.1 Ne pas confondre compte marchand et supérette

```text
Compte marchand = identité et accès utilisateur.
Supérette = boutique visible, adresse, téléphone, logo, catalogue.
```

### 12.2 Ne pas recréer les groupements

Le travail déjà livré #464 à #467 doit être réutilisé.

### 12.3 Préserver les PRs atomiques

Ne pas livrer en une seule PR :

```text
admin creation + first login + account page + group preload
```

Chaque bloc doit pouvoir être relu, testé et mergé séparément.

### 12.4 Sécurité première connexion

Le mode terrain doit rester un mode de démarrage, pas un accès permanent.

### 12.5 UX terrain

L'admin ou le commercial doit comprendre rapidement :

```text
- compte créé ;
- supérette créée ;
- mode d'activation choisi ;
- catalogue préchargé ou non ;
- prochaines actions du marchand.
```

---

## 13. Hors périmètre global

```text
- multi-utilisateurs par supérette ;
- rôles employé / manager ;
- facturation ;
- abonnement ;
- paiement en ligne ;
- signature contrat ;
- KYC ;
- import IA catalogue ;
- packs commerciaux client ;
- suggestions intelligentes ;
- application mobile native.
```

---

## 14. Vérification documentation

Cette PR est une PR de cadrage/documentation.

Elle ne doit pas :

```text
- implémenter du backend ;
- implémenter du frontend ;
- ajouter une migration ;
- modifier les contrats API réels ;
- réintroduire docs/roadmap/mvp-roadmap.md.
```

Vérification attendue :

```text
git diff --check
```
