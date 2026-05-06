# Codex — Configuration projet

Ce dossier complète le fichier racine `AGENTS.md`.

Codex doit utiliser `AGENTS.md` comme point d'entrée, puis suivre les fichiers :

- `AI_CONTEXT.md` ;
- `Codex/instructions.md` ;
- `Codex/workflows.md` ;
- `Codex/checklist.md`.

## Utilisation recommandée

Depuis la racine du dépôt :

```bash
codex
```

## Principe

- `AGENTS.md` = instructions racine pour Codex et agents compatibles.
- `AI_CONTEXT.md` = contexte produit partagé.
- `Codex/` = workflows détaillés pour les tâches Codex.

## Bon usage

Codex est particulièrement utile pour :

- modifier le code ;
- générer migrations, DTO, API resources et tests ;
- faire des refactors ciblés ;
- appliquer des checklists de validation ;
- préparer des PR propres.

Pour les décisions produit longues, commencer par documenter dans `docs/product/` avant d'implémenter.
