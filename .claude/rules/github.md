# GitHub PR Workflow

## Branchement des PRs

Toujours créer les branches de feature depuis `main` (ou la branche cible), jamais depuis
une autre feature branch. Brancher sur une feature branch inclut ses commits dans le diff,
rendant la review confuse et le merge risqué.

```bash
# Correct
git checkout main && git pull && git checkout -b feat/s5-007-ma-feature

# Incorrect — le diff de la PR inclut tous les commits de la feature source
git checkout feat/s5-006-autre && git checkout -b feat/s5-007-ma-feature
```

## Répondre à une review soumise

`PATCH /repos/{owner}/{repo}/pulls/{id}/reviews/{reviewId}/events` retourne **422**
si la review est déjà soumise (état `SUBMITTED`). Ce endpoint ne fonctionne que pour
les reviews en état `PENDING`.

Pour ajouter une réponse visible : utiliser un commentaire de PR classique.

```bash
gh issue comment {pr_number} --body "..."
```

## Récupérer les reviews d'une PR

```bash
gh pr view {pr_number} --json reviews,comments,reviewRequests
```

Retourne le corps complet des reviews (state, body, author). À utiliser avant d'adresser les retours d'une PR.

## Résoudre un thread de review

Aucun endpoint REST ne permet de résoudre un thread. Utiliser l'API GraphQL :

1. Récupérer le `node_id` du thread (`PRRT_...`) :
   `gh api repos/{owner}/{repo}/pulls/{pr}/comments`
2. Appeler la mutation `resolveReviewThread` :

```bash
gh api graphql -f query='
  mutation {
    resolveReviewThread(input: {threadId: "PRRT_..."}) {
      thread { isResolved }
    }
  }
'
```
