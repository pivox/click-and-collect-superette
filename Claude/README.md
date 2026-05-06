# Claude — Configuration projet

Ce dossier complète le fichier racine `CLAUDE.md`.

Claude Code doit charger `CLAUDE.md`, puis suivre les imports vers :

- `Claude/instructions.md` ;
- `Claude/workflows.md` ;
- `Claude/checklist.md`.

## Utilisation recommandée

Depuis la racine du dépôt :

```bash
claude
```

Puis vérifier les mémoires chargées avec :

```text
/memory
```

## Principe

- `CLAUDE.md` = point d'entrée auto-lu.
- `AI_CONTEXT.md` = contexte produit commun.
- `Claude/` = règles détaillées pour Claude.
- `.claude/rules/` = règles projet structurées pour Claude Code quand cette mécanique est disponible.
