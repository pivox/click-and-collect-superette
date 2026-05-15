# Init Context — Lecture obligatoire + conception

Avant toute action sur ce projet, lis dans l'ordre les fichiers suivants.
Ne passe pas à la phase de conception tant que tous ces fichiers n'ont pas été lus.

## Fichiers de contexte obligatoires

1. `CLAUDE.md`
2. `AGENTS.md`
3. `AI_CONTEXT.md`
4. `Claude/instructions.md`
5. `Claude/workflows.md`
6. `Claude/checklist.md`
7. `README.md`
8. `docs/roadmap/mvp-roadmap.md` — si présent
9. `docs/architecture/api-contract.md` — si présent
10. Sprint actif le plus récent — lister `docs/Sprint*/README.md` et `docs/SprintAuth/README.md`, lire le README du dossier ayant le nom le plus récent (ex. SprintAuth > Sprint5 > Sprint4)
11. `apps/backend/src/` — parcourir la structure (entités, API resources, services, processors)
12. `apps/backend/config/` — parcourir packages/, services.yaml, security.yaml
13. `apps/backend/tests/` — parcourir les tests existants

## Règles de lecture

- Si un fichier est absent, noter l'absence et continuer.
- Mémoriser les entités et statuts de commande définis dans `AI_CONTEXT.md`.
- Les fichiers `.claude/rules/*.md` (backend-patterns, migrations, testing, security) sont injectés automatiquement par Claude Code — pas besoin de les relire explicitement, mais s'y référer activement pendant la conception et l'implémentation.

## Phase de conception — choix du sous-agent

Après la lecture, identifier le type de tâche et utiliser le sous-agent correspondant :

**Tâche backend / API / données / architecture** → sous-agent `symfony-architect`

- identifier les entités et ressources API concernées ;
- proposer les opérations (lecture/écriture séparées si les payloads diffèrent) ;
- définir DTO, Provider, Processor, Voter si nécessaires ;
- vérifier la cohérence avec le périmètre MVP ;
- lister les migrations Doctrine à prévoir ;
- lister les tests à écrire.

**Tâche produit / cadrage / user story / UX** → sous-agent `product-owner`

- identifier le besoin : MVP, post-MVP ou hors scope ;
- rédiger ou affiner la user story selon le format `Claude/workflows.md` (Workflow 2) ;
- lister les impacts sur API, data model et interface ;
- vérifier la cohérence du vocabulaire métier.

Présente le plan de conception avant d'écrire du code.

## Phase d'implémentation

Implémente uniquement après validation du plan de conception.
Applique systématiquement :

- migrations Doctrine pour tout changement de schéma ;
- tests unitaires ou fonctionnels pour toute règle métier ;
- séparation client / marchand / admin dans les ressources API ;
- vocabulaire métier : Kadhia, TND, supérette, marchand, rendez-vous, retrait.

## Résumé final obligatoire

Termine par :

- fichiers modifiés ;
- vérifications effectuées ;
- hypothèses posées ;
- risques et prochaines étapes.
