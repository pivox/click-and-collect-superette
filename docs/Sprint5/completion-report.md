# Sprint 5 — Rapport de clôture

Date d'audit : 2026-05-20

## Objectif

Sprint 5 avait pour mission de rendre la plateforme opérable par un administrateur sans accès direct à la base de données : création de supérettes, comptes marchands, référentiel produit, validation des propositions, et onboarding guidé du marchand.

---

## US livrées

| US | Titre | PR | Endpoints couverts |
|---|---|---|---|
| US-009 | Créer et gérer les supérettes (admin) | #104, #107, #108 | GET/POST/PATCH /api/admin/stores + activate/deactivate |
| US-028 | Gérer les comptes marchands | #103, #106 | GET/POST/PATCH /api/admin/merchants + suspend/activate |
| US-029 | Superviser le référentiel produit global | #116, #115 | GET/POST/PATCH /api/admin/product-references + archive ; GET/POST/PATCH/DELETE /api/admin/brands |
| US-030 | Valider les propositions de nouveaux produits | #117 | GET/GET{id}/PATCH approve/PATCH reject /api/admin/product-proposals |
| US-050 | Photo et logo de la supérette | #118 | PATCH /api/admin/stores/{id} (logoUrl, coverUrl) |
| US-054 | Onboarding marchand guidé | #120 | GET /api/merchant/onboarding + PATCH /complete |
| US-055 | QR code téléchargeable par le marchand | #119 | GET /api/merchant/stores/{storeId}/qr-code |

---

## Endpoints livrés

### Admin — Supérettes (`ROLE_ADMIN`)

| Méthode | Route | Sécurité |
|---|---|---|
| GET | /api/admin/stores | JWT + ROLE_ADMIN |
| GET | /api/admin/stores/{storeId} | JWT + ROLE_ADMIN |
| POST | /api/admin/stores | JWT + ROLE_ADMIN |
| PATCH | /api/admin/stores/{storeId} | JWT + ROLE_ADMIN |
| PATCH | /api/admin/stores/{storeId}/activate | JWT + ROLE_ADMIN |
| PATCH | /api/admin/stores/{storeId}/deactivate | JWT + ROLE_ADMIN |
| GET | /api/admin/stores/{storeId}/qr-code | JWT + ROLE_ADMIN |
| POST | /api/admin/stores/{storeId}/regenerate-qr | JWT + ROLE_ADMIN |

### Admin — Marchands (`ROLE_ADMIN`)

| Méthode | Route | Sécurité |
|---|---|---|
| GET | /api/admin/merchants | JWT + ROLE_ADMIN |
| GET | /api/admin/merchants/{merchantId} | JWT + ROLE_ADMIN |
| POST | /api/admin/merchants | JWT + ROLE_ADMIN |
| PATCH | /api/admin/merchants/{merchantId} | JWT + ROLE_ADMIN |
| PATCH | /api/admin/merchants/{merchantId}/suspend | JWT + ROLE_ADMIN |
| PATCH | /api/admin/merchants/{merchantId}/activate | JWT + ROLE_ADMIN |

### Admin — Référentiel produit (`ROLE_ADMIN`)

| Méthode | Route | Sécurité |
|---|---|---|
| GET | /api/admin/categories | JWT + ROLE_ADMIN |
| GET | /api/admin/categories/{categoryId} | JWT + ROLE_ADMIN |
| POST | /api/admin/categories | JWT + ROLE_ADMIN |
| PATCH | /api/admin/categories/{categoryId} | JWT + ROLE_ADMIN |
| DELETE | /api/admin/categories/{categoryId} | JWT + ROLE_ADMIN |
| GET | /api/admin/brands | JWT + ROLE_ADMIN |
| GET | /api/admin/brands/{brandId} | JWT + ROLE_ADMIN |
| POST | /api/admin/brands | JWT + ROLE_ADMIN |
| PATCH | /api/admin/brands/{brandId} | JWT + ROLE_ADMIN |
| DELETE | /api/admin/brands/{brandId} | JWT + ROLE_ADMIN |
| GET | /api/admin/product-references | JWT + ROLE_ADMIN |
| GET | /api/admin/product-references/{productReferenceId} | JWT + ROLE_ADMIN |
| POST | /api/admin/product-references | JWT + ROLE_ADMIN |
| PATCH | /api/admin/product-references/{productReferenceId} | JWT + ROLE_ADMIN |
| PATCH | /api/admin/product-references/{productReferenceId}/archive | JWT + ROLE_ADMIN |
| GET | /api/admin/product-proposals | JWT + ROLE_ADMIN |
| GET | /api/admin/product-proposals/{proposalId} | JWT + ROLE_ADMIN |
| PATCH | /api/admin/product-proposals/{proposalId}/approve | JWT + ROLE_ADMIN |
| PATCH | /api/admin/product-proposals/{proposalId}/reject | JWT + ROLE_ADMIN |

### Marchand — QR et onboarding (`ROLE_MERCHANT`)

| Méthode | Route | Sécurité |
|---|---|---|
| GET | /api/merchant/stores/{storeId}/qr-code | JWT + ROLE_MERCHANT + propriétaire |
| GET | /api/merchant/onboarding | JWT + ROLE_MERCHANT |
| PATCH | /api/merchant/onboarding/complete | JWT + ROLE_MERCHANT |

---

## Entités et migrations ajoutées

| Migration | Description |
|---|---|
| Version20260520120000 | `ADD COLUMN logo_url`, `cover_url` à la table `shop` |
| Version20260520130000 | `ADD COLUMN onboarding_completed_at` à la table `user` |

Entités modifiées : `Shop` (logoUrl, coverUrl), `User` (onboardingCompletedAt).

Aucune entité créée de zéro dans Sprint 5 (les entités `Brand`, `Category`, `ProductReference`, `ProductReferenceProposal` existaient depuis les sprints précédents).

---

## Tests exécutés — résultats réels

Exécutés le 2026-05-20 sur la branche `docs/s5-012-sprint5-completion-audit`.

### Tests ciblés Sprint 5

| Fichier de test | Résultat | Tests | Assertions |
|---|---|---|---|
| CategoryAdminApiTest | ✅ OK | 19 | 89 |
| MerchantAdminApiTest | ✅ OK | 26 | 126 |
| StoreAdminApiTest | ✅ OK | 31 | 224 |
| AdminProductReferenceApiTest | ✅ OK | 37 | 138 |
| AdminProductProposalApiTest | ✅ OK | 6 | 28 |
| MerchantStoreQrApiTest | ✅ OK | 7 | 20 |
| MerchantOnboardingApiTest | ✅ OK | 16 | 72 |

### Suite complète backend

```
OK (933 tests, 3885 assertions)
```

Aucun test en échec sur l'ensemble du backend.

---

## Qualité statique

| Outil | Résultat |
|---|---|
| PHPStan (niveau 8, 348 fichiers) | ✅ No errors |
| PHP CS Fixer | ✅ No files changed |
| composer validate | ✅ ./composer.json is valid |

---

## Schéma Doctrine

La migration `Version20260520130000` (ajout `onboarding_completed_at`) n'était pas appliquée localement au moment de l'audit. Elle a été exécutée manuellement (`doctrine:migrations:migrate`) et validée.

Un diff résiduel est présent après migration : il s'agit exclusivement de **renommages d'index cosmétiques** (noms custom définis dans les migrations → noms auto-générés par Doctrine). Ce drift est dû à la divergence entre les noms d'index définis manuellement dans les migrations et les conventions de nommage Doctrine DBAL. Il n'affecte pas le comportement fonctionnel. Aucune colonne, contrainte fonctionnelle ou table n'est manquante.

**Action recommandée** avant production : consolider les noms d'index dans une migration dédiée ou accepter le drift comme connu et documenté.

---

## Limites MVP conservées

Les limites identifiées pendant Sprint 5 et conservées intentionnellement :

- **Slug race condition** : la contrainte `UNIQUE` sur `shop.slug` n'a pas été ajoutée en base (US-065 — risque faible admin-only, identifié, documenté, reporté).
- **QR target_url relatif** : l'URL retournée par `GET /qr-code` est un chemin relatif (`/api/stores/by-qr/{token}`). La composition en URL absolue est à la charge du frontend.
- **onboarding_completed_at PATCH idempotent** : `PATCH /complete` accepte la requête même si toutes les étapes ne sont pas complétées. Aucun prérequis de complétion n'est vérifié côté backend.
- **Critère `qr_code`** : redondant avec `store_profile` (le `qrCodeToken` est toujours non-null sur un shop actif). Documenté comme tel dans l'API contract.
- **Critère `theme`** : seul un `ShopTheme` explicitement configuré satisfait l'étape. Le `PlatformTheme` singleton n'est pas pris en compte.
- **Performance onboarding** : le calculateur exécute 1 + 4N requêtes pour N shops actifs. Acceptable dans le MVP (quasi-tous les marchands ont 1 shop).
- **Statut `merged`** : le statut `merged` de `ProductReferenceProposalStatus` est orphelin — l'endpoint `POST /merge` a été supprimé. Les enregistrements `merged` existants restent lisibles mais ne peuvent plus être créés. Migration one-shot recommandée avant production.
- **Labels onboarding en français uniquement** : `step.label` est en FR. Le frontend utilise `step.key` comme clé i18n pour l'arabe.
- **Collection proposals sans total** : `GET /api/admin/product-proposals` ne retourne pas `hydra:totalItems` / `X-Total-Count`.
- **Email d'invitation marchand** : hors périmètre MVP. Le marchand créé ne peut pas se connecter de manière autonome sans une réinitialisation de mot de passe initiée par l'admin ou le marchand lui-même.

---

## Risques production identifiés

| Risque | Sévérité | Recommandation |
|---|---|---|
| `shop.slug` sans contrainte UNIQUE en base | Moyen | Ajouter `ALTER TABLE shop ADD CONSTRAINT uq_shop_slug UNIQUE (slug)` + migration avant passage en prod sous charge |
| `QR target_url` relatif | Faible | Composer l'URL absolue côté frontend (pas de changement backend requis) |
| Migration `merged→approved` | Faible | Script one-shot avant prod si des enregistrements `merged` existent en base |
| Drift index cosmétique `doctrine:schema:validate` | Informatif | Accepter ou nettoyer dans une migration cosmétique dédiée |
| Transport Messenger sync en dev | Moyen | Configurer un transport async persistant + worker en production pour les délais (hors Sprint 5 scope) |

---

## Décision finale

**Sprint 5 terminé.**

Toutes les US du Sprint 5 sont livrées et couvertes par des tests fonctionnels passants. La qualité statique est conforme (PHPStan niveau 8, CS Fixer clean). Les 933 tests backend passent sans erreur. Les limites connues sont documentées et non bloquantes pour un démarrage en production contrôlé.

---

## Suite recommandée — Sprint 6 (thème) et Sprint 7

Sprint 6 (personnalisation visuelle) est déjà implémenté côté backend (`PlatformTheme`, `ShopTheme`, `GET /api/stores/{storeId}/theme`). La prochaine priorité recommandée est :

1. **Frontend MVP** — intégrer les endpoints backend livrés (parcours client + marchand) dans Next.js.
2. **US-065** — contrainte UNIQUE sur `shop.slug` (migration + gestion 409 dans le processor).
3. **Sprint 7** — observabilité, localisation FR/AR complète, PWA, RGPD.
