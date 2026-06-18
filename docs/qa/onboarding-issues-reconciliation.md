# Consolidation issues onboarding marchand

## 1. Résumé

- PRs analysées : #541, #542.
- Contexte postérieur vérifié : #544 est aussi mergée sur `main` au 18 juin 2026 et couvre la tranche `temporary_password` de #501.
- Issues analysées : #498, #499, #500, #501, #502, #520, #521, #522, #523.
- Statut GitHub actuel : les 9 issues analysées sont encore ouvertes.
- Nombre d'issues probablement terminées : 7 (#499, #500, #502, #520, #521, #522, #523).
- Nombre d'issues à garder ouvertes : 2 (#498, #501).
- Prochaine priorité recommandée : fermer les issues couvertes par #541/#542, puis garder #501 comme epic de suivi pour l'invitation email et l'expiration des accès temporaires. La tranche mot de passe provisoire obligatoire a été livrée par #544.

Note de lecture : après #541 et #542 uniquement, #501 restait partiellement/non couvert sur le changement obligatoire au premier login. Sur l'état actuel de `main`, #544 a ajouté `password_change_required`, l'endpoint de première connexion, le blocage dashboard et l'écran marchand pour le mode mot de passe provisoire.

## 2. Matrice issues

| Issue | Titre | Statut GitHub actuel | Couverture par PR | Verdict | Action proposée |
|---|---|---|---|---|---|
| #498 | US — Gestion de compte marchand et onboarding admin | OPEN | #541 couvre onboarding admin et préchargement catalogue ; #542 couvre compte marchand ; #544 couvre première connexion `temporary_password` | Partiellement couvert — garder ouvert | convertir en epic de suivi jusqu'à décision sur l'invitation email et les enfants encore ouverts |
| #499 | S15-018 — Gestion marchand | OPEN | Couvert par #542 ; enfants #503 à #506 fermés | Couvert — peut être fermé | fermer avec commentaire |
| #500 | S15-019 — Onboarding admin | OPEN | Couvert par #541 : endpoint combiné, création marchand, création supérette, `Shop.owner`, UI admin, tests | Couvert — peut être fermé | fermer avec commentaire, puis aligner les enfants #507 à #511 encore ouverts |
| #501 | S15-020 — Première connexion marchand | OPEN | #541 génère le mot de passe provisoire ; #542 hors périmètre ; #544 couvre le changement obligatoire pour `temporary_password` | Partiellement couvert — garder ouvert | garder ouverte avec checklist restante ou scinder l'email invitation en issue enfant dédiée |
| #502 | S15-021 — Préchargement catalogue admin | OPEN | Couvert par #541 : sélection groupements, application à la supérette, import idempotent, résumé | Couvert — peut être fermé | fermer avec commentaire après fermeture de #520 à #523 |
| #520 | S15-037 — Catalogue admin backend | OPEN | Couvert par #541 via `ProductGroupCatalogImporter` et tests onboarding | Couvert — peut être fermé | fermer avec commentaire |
| #521 | S15-038 — UI catalogue onboarding | OPEN | Couvert par #541 via le drawer admin marchands et tests frontend | Couvert — peut être fermé | fermer avec commentaire |
| #522 | S15-034 — Résumé préchargement catalogue | OPEN | Couvert par #541 : `added_count`, `already_existing_count`, `ignored_count`, `errors` côté API et UI | Couvert — peut être fermé | fermer avec commentaire |
| #523 | S15-040 — QA catalogue admin | OPEN | Couvert par #541 : tests backend groupements/onboarding et tests frontend admin drawer/page | Couvert — peut être fermé | fermer avec commentaire |

## 3. Analyse attendue par issue

### #498 — Gestion de compte marchand et onboarding admin

Vérifications :

- création marchand : couverte par #541 via `POST /api/admin/merchant-onboarding` ;
- création supérette : couverte par #541 ;
- owner : couvert par #541 avec rattachement `Shop.owner` ;
- première connexion : partiellement couverte après #541/#542 ; la tranche mot de passe provisoire obligatoire est couverte depuis #544, mais l'invitation email reste ouverte ;
- préchargement catalogue : couvert par #541 ;
- sécurité champs sensibles : couverte par #542 pour le compte marchand, et par #541/#544 pour les secrets de première connexion ;
- audit admin : couvert par #541 pour création marchand, création supérette, owner, mot de passe temporaire et préchargement catalogue quand applicable.

Verdict : partiellement couvert — garder ouvert.

Action proposée : convertir #498 en epic de suivi ou la garder ouverte jusqu'à clarification de #501. Si l'équipe accepte de sortir l'invitation email dans une issue séparée, #498 pourra être fermée après fermeture administrative des enfants déjà couverts.

### #499 — Gestion marchand

Vérifications :

- profil marchand : couvert par #542 avec `GET /api/merchant/me` et `PATCH /api/merchant/me` ;
- modification champs non sensibles : couvert pour `first_name`, `last_name`, `phone` ;
- changement mot de passe connecté : couvert par `PATCH /api/merchant/me/password` ;
- page Mon compte : couverte par `/merchant/parametres/compte` ;
- documentation : couverte dans le contrat API et la documentation Sprint 15 ;
- tests : `MerchantAccountApiTest`, `MerchantMeApiTest`, `merchant.account.test.tsx` ;
- enfants #503, #504, #505, #506 : fermés.

Verdict : couvert — peut être fermé.

Action proposée : fermer #499 avec commentaire.

### #500 — Onboarding admin

Vérifications :

- création marchand par admin : couverte par #541 ;
- création supérette : couverte par #541 ;
- `Shop.owner` : couvert par #541 ;
- endpoint combiné : couvert par `POST /api/admin/merchant-onboarding` ;
- écran admin : couvert par le drawer admin marchands ;
- tests ownership/permissions : couverts dans `AdminMerchantOnboardingApiTest` et tests frontend admin.

Verdict : couvert — peut être fermé.

Action proposée : fermer #500 avec commentaire, puis aligner les enfants #507 à #511 qui sont encore ouverts côté GitHub.

### #501 — Première connexion marchand

Vérifications :

- invitation email : non couverte ;
- mot de passe provisoire : couvert par #541 pour la génération one-shot et par #544 pour le changement obligatoire ;
- changement obligatoire : non couvert par #541/#542, couvert depuis #544 pour le mode `temporary_password` ;
- blocage dashboard : non couvert par #541/#542, couvert depuis #544 côté backend et frontend pour le mode `temporary_password` ;
- écran première connexion : non couvert par #541/#542, couvert depuis #544 pour `/merchant/premiere-connexion`.

Verdict : partiellement couvert — garder ouvert.

Action proposée : garder #501 ouverte comme epic tant que l'invitation email, le token expirant/usage unique et l'expiration du mot de passe provisoire ne sont pas tranchés. Commenter que la tranche mot de passe provisoire obligatoire est maintenant livrée par #544.

### #502 — Préchargement catalogue admin

Vérifications :

- sélection groupements : couverte par #541 dans le formulaire admin ;
- application à la supérette : couverte par #541 dans l'orchestration onboarding ;
- import idempotent : couvert via `ProductGroupCatalogImporter` ;
- résumé résultat : couvert avec `added_count`, `already_existing_count`, `ignored_count`, `errors` ;
- produits sans prix invisibles côté client : couvert par la règle prix à compléter / invisible côté client.

Verdict : couvert — peut être fermé.

Action proposée : fermer #520, #521, #522, #523 puis #502.

### #520 — Catalogue admin backend

Vérifications :

- logique backend d'application de groupements : couverte par `ProductGroupCatalogImporter` ;
- pas de doublons : couvert par l'import idempotent ;
- résumé : couvert par `AdminCatalogPreloadOutput` ;
- produits sans prix invisibles : couvert par les règles de préchargement ;
- tests : présents dans `AdminMerchantOnboardingApiTest` et tests groupements import.

Verdict : couvert — peut être fermé.

Action proposée : fermer #520 avec commentaire.

### #521 — UI catalogue onboarding

Vérifications :

- groupements visibles dans formulaire admin : couvert par `MerchantDrawer` ;
- sélection multiple : couverte ;
- transmission backend : couverte via `product_group_ids` ;
- erreurs affichées : couvertes dans le drawer admin ;
- tests : présents dans `admin.merchant-drawer.test.tsx`.

Verdict : couvert — peut être fermé.

Action proposée : fermer #521 avec commentaire.

### #522 — Résumé préchargement catalogue

Vérifications :

- `added_count` : couvert ;
- `already_existing_count` : couvert ;
- `ignored_count` : couvert ;
- `errors` : couvert ;
- affichage compréhensible admin : couvert dans le drawer admin avec résumé one-shot.

Verdict : couvert — peut être fermé.

Action proposée : fermer #522 avec commentaire.

### #523 — QA catalogue admin

Vérifications :

- tests backend groupements : présents ;
- tests front admin drawer/page : présents ;
- tests import idempotent : présents ;
- tests erreurs : présents ;
- limites : les vérifications globales mentionnées dans #541 avaient des failures hors périmètre, mais les tests ciblés de #541 étaient verts selon la PR.

Verdict : couvert — peut être fermé.

Action proposée : fermer #523 avec commentaire.

## 4. Commentaires GitHub proposés

### #498

Partiellement couvert par #541, #542 et #544 : onboarding admin, gestion du compte marchand, préchargement catalogue et première connexion `temporary_password` sont livrés. Restent à clarifier ou livrer côté epic : invitation email, token expirant/usage unique, expiration éventuelle du mot de passe provisoire et alignement des issues enfants encore ouvertes. Proposition : garder ouverte comme epic de suivi ou scinder les restes dans une issue dédiée avant fermeture.

### #499

Couvert par la PR #542 : profil compte marchand, `PATCH /api/merchant/me`, `PATCH /api/merchant/me/password`, email non modifiable, suppression des champs sensibles des réponses, page Mon compte et documentation User vs Shop. Les enfants #503 à #506 sont fermés. Proposition : fermer cette issue.

### #500

Couvert par la PR #541 : endpoint d'onboarding admin, création marchand, création supérette, rattachement `Shop.owner`, écran admin, permissions et tests associés. Proposition : fermer cette issue, puis aligner les enfants #507 à #511 encore ouverts si GitHub les maintient comme découpage atomique.

### #501

Partiellement couvert. #541 a livré la génération du mot de passe temporaire affiché une seule fois, et #544 a depuis livré le changement obligatoire au premier login pour le mode `temporary_password`, le flag `password_change_required`, le blocage dashboard et la page `/merchant/premiere-connexion`. L'invitation email, le token expirant/usage unique et l'expiration du mot de passe provisoire restent hors périmètre. Proposition : garder ouverte comme epic de suivi ou créer/mettre à jour une issue enfant pour l'invitation email.

### #502

Couvert par la PR #541 : sélection de groupements dans l'onboarding admin, application à la supérette créée, import idempotent, résumé ajouté/déjà présent/ignoré/erreurs et règle produits sans prix invisibles côté client. Proposition : fermer cette issue après fermeture de #520, #521, #522 et #523.

### #520

Couvert par la PR #541 : la logique backend de préchargement catalogue réutilise `ProductGroupCatalogImporter`, évite les doublons, retourne un résumé et conserve les produits sans prix invisibles côté client. Proposition : fermer cette issue.

### #521

Couvert par la PR #541 : le drawer admin marchands charge les groupements publiés, permet la sélection multiple, transmet `product_group_ids` au backend et affiche les erreurs. Proposition : fermer cette issue.

### #522

Couvert par la PR #541 : la réponse API et l'UI admin affichent le résumé de préchargement avec produits ajoutés, déjà présents, ignorés et erreurs. Proposition : fermer cette issue.

### #523

Couvert par la PR #541 : tests backend de préchargement catalogue/onboarding, tests frontend du drawer admin et cas d'import idempotent/erreurs présents dans la PR. Proposition : fermer cette issue.

## 5. Prochaine PR recommandée

Priorité initiale après #541/#542 uniquement :

#501 — Première connexion marchand, tranche mot de passe provisoire uniquement.

Pourquoi :

- #541 générait déjà un mot de passe provisoire ;
- mais #541/#542 ne livraient pas encore `password_change_required`, le changement obligatoire et le blocage dashboard ;
- sans ce bloc, le marchand pouvait utiliser le mot de passe provisoire comme mot de passe durable.

Actualisation au 18 juin 2026 :

- cette tranche est maintenant livrée par #544 ;
- la prochaine PR recommandée n'est donc plus la tranche `temporary_password`, mais l'un des deux travaux suivants selon décision produit :
  - invitation email première connexion (#512 / partie restante de #501) avec token opaque, expirant et usage unique ;
  - consolidation GitHub administrative : commenter/fermer #499, #500, #502, #520, #521, #522, #523 et mettre #498/#501 à jour avec les restes réels.

Limites restantes :

- invitation email non traitée ;
- expiration dédiée du mot de passe provisoire non traitée ;
- enfants #507 à #517 encore ouverts côté GitHub malgré des tranches déjà livrées ;
- aucune issue n'a été fermée automatiquement dans cet audit.
