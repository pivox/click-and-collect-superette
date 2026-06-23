# Audit fonctionnel MVP — Click & Collect Supérette Tunisie

Dernière révision : 2026-06-23

## Objectif

Ce document consolide l'état fonctionnel du MVP en croisant :

- la vision produit ;
- la roadmap active ;
- le contrat API ;
- le code et les tests présents sur la branche courante ;
- les statuts GitHub vérifiés en lecture seule quand ils sont mentionnés.

Il n'est pas une roadmap parallèle.

## Sources de vérité

- Point d'entrée projet : [docs/project/source-of-truth.md](../project/source-of-truth.md).
- Roadmap active : [docs/Sprint14/README.md](../Sprint14/README.md).
- Synthèse stratégique : [docs/roadmap/launch-readiness-reorganization.md](../roadmap/launch-readiness-reorganization.md).
- Contrat API : [docs/architecture/api-contract.md](../architecture/api-contract.md).
- Index historique court : [docs/product/mvp-roadmap.md](./mvp-roadmap.md).

Ne pas recréer `docs/roadmap/mvp-roadmap.md`.

## Statuts utilisés

| Statut | Signification |
|---|---|
| `OK` | Fonction cadrée, codée et raisonnablement couverte par tests ou preuves ciblées. |
| `PARTIEL` | Une partie existe, mais un pan du parcours ou de la validation reste incomplet. |
| `MANQUANT` | Bloc attendu non cadré ou non codé. |
| `A_ALIGNER` | Documentation ou contrat à corriger pour refléter le code réel. |
| `A_DECIDER` | Décision produit nécessaire avant développement ou lancement. |

## Synthèse globale

| Bloc fonctionnel | Documenté | Codé | Testé | Contrat API | Statut | Preuve / action restante |
|---|---|---|---|---|---|---|
| Login JWT | Oui | Oui | Oui | Oui | OK | `POST /api/auth/login`. |
| Inscription client | Oui | Oui | Oui | Oui | OK | `POST /api/auth/register/customer`, rôle client forcé. |
| Profil client | Oui | Oui | Oui | Oui | OK | `GET/PATCH /api/me/profile`. |
| Reset password multi-rôles | Oui | Oui | Oui | Oui | OK | Tests backend/front ; #528 lié dans #527. |
| Inscription marchand publique | Oui | Non | Non | Non | A_DECIDER | MVP : création marchand contrôlée par admin. |
| Admin marchands / supérettes | Oui | Oui | Oui | Oui | OK | CRUD, suspension/activation, onboarding admin, mot de passe temporaire. |
| QR code magasin | Oui | Oui | Oui | Oui | OK | QR admin/marchand, PNG/PDF marchand ; #543 suit la robustesse `FRONTEND_URL`. |
| Recherche supérette | Oui | Oui | Oui | Oui | OK | `GET /api/stores/search`. |
| Relation client / supérette | Oui | Oui | Oui | Oui | OK | `/api/me/stores/*`. |
| Catalogue public store | Oui | Oui | Oui | Oui | OK | Catalogue visible même si suspension douce ; soumission bloquée séparément. |
| Référentiel produit marchand | Oui | Oui | Oui | Oui | OK | Recherche `q` et `barcode`. |
| Catalogue marchand | Oui | Oui | Oui | Oui | OK | Liste, ajout, édition, suppression, catégories, disponibilité. |
| Import CSV catalogue marchand (#365) | Oui | Oui | Oui | Oui | PARTIEL | Issue fermée `completed`; backend/front et tests présents. Scan caméra mobile non constaté ; recherche/saisie code-barres couverte par API. |
| Kadhia multiple | Oui | Oui | Oui | Oui | OK | `/api/me/kadhias`, partage, reprise draft. |
| Soumission Kadhia | Oui | Oui | Oui | Oui | OK | Soumission, idempotence, capacité créneau. |
| Historique commandes client | Oui | Oui | Oui | Oui | OK | `GET /api/me/orders`. |
| Commandes marchand | Oui | Oui | Oui | Oui | OK | Liste, détail et historique store-scoped. |
| Acceptation / refus marchand | Oui | Oui | Oui | Oui | OK | Transitions `submitted`, logs, capacité. |
| Acceptation partielle | Oui | Oui | Oui | Oui | OK | Rejet lignes, Kadhia draft, détails client. |
| Annulation commande client | Oui | Oui | Oui | Oui | OK | `POST /api/me/orders/{orderId}/cancel`. |
| Préparation ligne par ligne | Oui | Oui | Oui | Oui | OK | `prepared` par ligne. |
| Mark-ready strict | Oui | Oui | Oui | Oui | OK | Toutes lignes préparées ; création `PickupSession`. |
| Créneaux / horaires / fermetures | Oui | Oui | Oui | Oui | OK | CRUD, règles récurrentes, génération, fermetures, opening-hours. |
| Dashboard marchand journalier | Oui | Oui | Oui | Oui | OK | `/dashboard/today`. |
| Statistiques marchand (#380) | Oui | Oui | Oui | Oui | OK | Issue fermée `completed`; `/api/merchant/stores/{storeId}/statistics`, front marchand, `MerchantStatisticsApiTest`. |
| Thèmes plateforme / supérette | Oui | Oui | Oui | Oui | OK | Thème public, marchand, admin. |
| QR code retrait | Oui | Oui | Oui | Oui | OK | `PickupSession`, code retrait, scan, session client. |
| Double validation retrait | Oui | Oui | Oui | Oui | OK | Scan marchand, confirmations client/marchand, force completion. |
| Notifications in-app client/marchand | Oui | Oui | Oui | Oui | OK | Endpoints client/marchand, polling. |
| Web Push (#376) | Oui | Oui | Partiel | Oui | PARTIEL | Issue fermée `completed`; souscriptions client/marchand, service worker et toggle testés. Envoi réel navigateur/plateformes non validé en E2E dans cette passe. |
| Suivi statut client | Oui | Oui | Oui | Oui | OK | `GET /api/me/orders/{orderId}/status`. |
| Rappel retrait 1h | Oui | Oui | Oui | Oui | PARTIEL | Planification Messenger et transport Doctrine livrés ; contenu notification encore générique. |
| Historique statuts commande | Oui | Oui | Oui | Oui | OK | `OrderStatusLog` client/marchand. |
| Admin catégories / marques / ProductReference | Oui | Oui | Oui | Oui | OK | CRUD référentiel admin. |
| Images produits web/mobile | Oui | Oui | Oui | Oui | OK | S13-005 / #391. |
| i18n FR/AR/RTL | Oui | Oui | Oui | Oui | OK | Client FR/AR RTL et préférence langue marchand légère. |
| PWA client (#374) | Oui | Oui | Partiel | Oui | PARTIEL | Issue fermée `completed`; manifest, layout et service worker présents. Validation terrain installée / Lighthouse non relancée. |
| PWA marchand (#375) | Oui | Oui | Partiel | Oui | PARTIEL | Issue fermée `completed`; manifest marchand et service worker partagé. Validation terrain installée non relancée. |
| Accessibilité minimum (#379) | Oui | Oui | Partiel | N/A | PARTIEL | Issue fermée `completed`; labels/aria visibles dans code/tests. Pas d'audit WCAG complet relancé. |
| Frontend client | Oui | Oui | Oui | Oui | OK | Parcours client complet, notifications, QR retrait, thèmes, i18n. |
| Frontend marchand | Oui | Oui | Oui | Oui | OK | Commandes, retrait, catalogue, créneaux, QR, thème, compte, abonnement, stats. |
| Frontend admin | Oui | Oui | Partiel | Oui | OK | Backoffice admin avancé ; E2E complet admin non relancé dans cette passe. |
| Observabilité / audit logs | Oui | Oui | Oui | Oui | OK | Healthcheck, diagnostics, logs, audit admin. |
| Fiabilité production EPIC-015 | Oui | Oui | Oui | N/A | OK | Worker async, monitoring Messenger, KPI terrain. |
| Activation terrain EPIC-016 | Oui | Oui | Oui | N/A | OK | QR imprimable et checklist activation. |
| Document mensuel interne non fiscal (#361) | Oui | Oui | Oui | Oui | OK | Issue fermée `completed`; décision 2026-06-05, `BillingDocument`, endpoints admin/marchand, tests. Pas une facture fiscale conforme. |
| Paiement manuel abonnement | Oui | Oui | Oui | Oui | OK | Paiement admin espèces/virement, consultation marchand/admin, audit, tests. |
| Relances paiement | Oui | Oui | Oui | Oui | OK | Relances email / WhatsApp manuel côté billing, traces et tests ciblés. |
| Suspension douce / réactivation (#364) | Oui | Oui | Oui | Oui | OK | Issue fermée `completed`; soumission bloquée si marchand/subscription suspendu, catalogue conservé, paiement réactive. |
| Packs produits (#382) | Oui | Partiel | Oui | Partiel | PARTIEL | Issue fermée `completed` pour le socle backend/API : `ProductPack`, `ProductPackItem`, endpoints marchand/client, ajout à la Kadhia et `MerchantProductPackApiTest`. Aucun consommateur, service, composant ou route packs n'est constaté dans `apps/frontend/src`; contrat API documentaire à compléter. |
| Suggestions de Kadhia (#383) | Oui | Oui | Oui | Partiel | OK | Issue fermée `completed`; PR #484 fusionnée : `StoreSuggestionsOutput`, favoris, remplacements indisponibles, `KadhiaSuggestionService`, `StoreSuggestionsApiTest`, `KadhiaSuggestionServiceTest` et tests frontend suggestions/favoris. Contrat API documentaire à compléter hors de cette PR. |
| Promotions simples (#384) | Oui | Oui | Oui | Oui | OK | Issue fermée `completed`; promo active/expirée, prix effectif, snapshot Kadhia, suivi admin. |
| CRM léger marchand (#385) | Oui | Oui | Oui | Oui | OK | Issue fermée `completed`; profil CRM, contacts, filtres, tests backend/front. |
| WhatsApp semi-manuel (#378) | Oui | Partiel | Partiel | Partiel | PARTIEL | Issue ouverte ; WhatsApp billing manuel existe, mais généralisation client/marchand commande reste active. |
| Facebook Messenger (#490-#494) | Oui | Non | Non | Non | A_DECIDER | Issues ouvertes ; optionnel, dépend du spike Meta et ne bloque pas le lancement sans décision PO. |
| Apps natives | Oui | Non | Non | Non | A_DECIDER | Post-lancement, gate terrain. |

## Écarts et nuances à conserver

### PWA, push et accessibilité

#374, #375, #376 et #379 sont fermées GitHub avec `state_reason=completed`.
Le dépôt contient des preuves techniques ciblées, mais cette passe documentaire
n'a pas relancé de Lighthouse, audit WCAG complet ni scénario mobile installé.
Le statut reste donc `PARTIEL` pour la validation terrain, pas `MANQUANT`.

### Import CSV + code-barres

#365 est fermée GitHub. Le dépôt contient l'import CSV, le modèle, les tests, et
la recherche exacte par code-barres. Le scan caméra mobile n'a pas été constaté
comme livré ; l'audit le traite comme nuance restante plutôt que replanifier
tout #365.

### Facturation

#361 livre un document mensuel interne non fiscal (`monthly_statement`), pas une
facture fiscale tunisienne conforme. Toute facture fiscale complète reste à
décider après validation comptable.

### Packs et suggestions

#382 et #383 sont fermées GitHub et techniquement représentées sur `main`.
Les packs couvrent côté backend/API la création, l'édition et la suppression
marchand, le listing client et l'ajout à la Kadhia avec snapshot prix. Aucune UI
PWA consommant ces endpoints n'est constatée dans `apps/frontend/src` : les packs
restent donc `PARTIEL` fonctionnellement et hors gate de valeur visible.
Les suggestions couvrent co-occurrence, récents, favoris et remplacements de
produits indisponibles avec des tests backend/front. Le contrat API documentaire
des deux blocs reste à compléter sans remettre en cause leurs acquis techniques.

### WhatsApp et Messenger

WhatsApp semi-manuel #378 reste ouvert pour le contexte commande. Facebook
Messenger #490 à #494 reste conditionnel et optionnel. L'in-app reste la source
de vérité.

### Audit #527 et `FRONTEND_URL`

L'audit #527 reste historique. L'issue #543 est ouverte pour sécuriser
`FRONTEND_URL` sur les QR magasin et liens Kadhia, sans bloquer les sujets MVP
plus urgents.

## Règle pour les prochaines PR IA

Avant de coder un nouveau bloc, vérifier dans cet ordre :

1. le code et les tests existants ;
2. [le contrat API](../architecture/api-contract.md) ;
3. [la roadmap active](../Sprint14/README.md) ;
4. cet audit ;
5. la documentation de sprint ou d'US concernée.

Ne pas réintroduire les anciens endpoints obsolètes du type
`/api/orders/{orderId}/items` pour la Kadhia.
