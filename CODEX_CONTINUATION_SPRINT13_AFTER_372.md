# Prompt de continuation Codex - Sprint 13 apres #372

Contexte projet : /Users/haythem.mabrouk/workspace/perso/click-and-collect-superette.
Repondre en francais. Respecter AGENTS.md : lire AI_CONTEXT.md, README.md, docs/product/, Codex/instructions.md, Codex/workflows.md, Codex/checklist.md avant modification.

Etat actuel au 2026-06-06 :
- PR #443 "[S13-002] Deduplication du referentiel produit" mergee.
- #371 clos via #443.
- PR #447 "[S13-003] Score de qualite des references produit" mergee.
- Merge/main commit #447 : f1ec0b2 "[S13-003] Score de qualite des references produit".
- #372 clos via #447.
- Workspace attendu sur main, propre, a jour avec origin/main.
- Fichier de reprise non versionne : CODEX_CONTINUATION_SPRINT13_AFTER_372.md.

Resume #372 implemente :
- Service backend ProductReferenceQualityScorer avec score deterministe 0..100 et niveaux low/medium/good.
- Exposition admin `quality_score` / `quality_level` en liste, detail, doublons, comparaison et merge.
- Filtre liste admin `quality_min` / `quality_max`.
- Tri serveur `sort=quality_score` + `direction`.
- Front admin referentiel produits : badge qualite, filtre par tranche, tri qualite serveur.
- Correction review Codex : les flux doublons/compare/merge chargent les images officielles avant mapping pour eviter un score 15 points trop bas.

Verifications #372 effectuees :
- `vendor/bin/phpunit tests/Functional/Api/AdminProductReferenceApiTest.php tests/Functional/Api/AdminProductReferenceDedupApiTest.php tests/Functional/Api/AdminProductProposalValidationApiTest.php tests/Unit/Service/ProductReferenceQualityScorerTest.php` : OK (80 tests, 302 assertions apres correctif review).
- `vendor/bin/phpstan analyse --memory-limit=512M` : OK.
- `vendor/bin/php-cs-fixer fix --dry-run --diff` : OK, avec warning runtime PHP 8.4 vs projet PHP 8.2.
- `npm run test:run -- src/tests/admin.product-reference-dedup.service.test.ts` : OK.
- `npx tsc --noEmit` : OK.
- `npm run lint` : OK.
- Verification navigateur locale sur `http://127.0.0.1:3013/admin/referentiel/produits` : redirection login attendue, aucune erreur console relevee.
- Apres checks GitHub verts + review Codex clean, suite complete locale `vendor/bin/phpunit` : OK (1346 tests, 6217 assertions).
- Checks GitHub #447 verts : Lint/Type-check/Build, Unit Tests, Quality & Tests.
- Review Codex finale #447 : "Didn't find any major issues." Thread P2 precedent resolu.

Mode de travail a reprendre :
- Enchainer les issues dans l'ordre logique Sprint 13.
- Pour chaque issue :
  1. partir de main a jour ;
  2. creer une branche `codex/...` ;
  3. implementer petit et propre ;
  4. tester reellement ;
  5. ouvrir une PR ;
  6. dans la description de chaque PR, ajouter une section "Prochaines issues" listant les tickets suivants ;
  7. demander `@codex review` ;
  8. attendre les checks GitHub et les retours review ;
  9. corriger tous les retours ;
  10. redemander review si necessaire ;
  11. resoudre les threads ;
  12. merger quand checks verts + review OK ;
  13. revenir sur main a jour ;
  14. passer a l'issue suivante, sauf instruction utilisateur contraire.
- Preference utilisateur pour les tests : pendant l'implementation et les corrections review, lancer tests impactes + statique ; lancer la suite complete locale seulement quand Codex est clean et checks verts, juste avant merge.
- Si possible, utiliser des agents paralleles pour exploration independante.
- Ne jamais inventer de tests passes.
- Ne pas revert des changements utilisateur non lies.
- Utiliser apply_patch pour les modifications manuelles.
- Utiliser Docker pour `php bin/console doctrine:schema:validate` si besoin DB ; Postgres local `127.0.0.1:5433` peut refuser.
- Si `doctrine:schema:validate` via Docker echoue sur drift preexistant, documenter precisement sans pretendre que c'est lie.

Issue suivante a traiter :
#373 "[S13-004] Gouvernance qualite du referentiel"

Prochaines issues a mentionner dans la PR #373 :
- #391 "[S13-005] Images produits web/mobile"
- #392 "[S13-006] Matching images produits via IA"
- #393 "[S13-007] Rapport qualite catalogue marchand"

Contraintes produit :
- Ne pas ajouter paiement en ligne, wallet, livraison, marketplace, ni paiement client de Kadhia.
- Preserver vocabulaire : Kadhia, superette, marchand, client, rendez-vous, retrait, TND.

Premieres actions recommandees a la reprise :
1. Relire le contexte obligatoire AGENTS.md / AI_CONTEXT.md / README.md / docs/product/ / Codex/*.md.
2. Verifier `git status --short --branch`, `git fetch origin`, `git switch main`, `git pull --ff-only origin main`.
3. Inspecter `gh issue view 373 --comments`.
4. Creer `codex/s13-004-reference-quality-governance`.
5. Explorer l'existant autour score qualite #372, audit admin, historique, referentiel produit, front admin.
6. Proposer un design court si le flux de gouvernance est ambigu, puis implementer en TDD.
