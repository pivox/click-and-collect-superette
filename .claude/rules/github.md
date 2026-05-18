# GitHub PR Workflow

## Répondre à une review soumise

`PATCH /repos/{owner}/{repo}/pulls/{id}/reviews/{reviewId}/events` retourne **422**
si la review est déjà soumise (état `SUBMITTED`). Ce endpoint ne fonctionne que pour
les reviews en état `PENDING`.

Pour ajouter une réponse visible : utiliser un commentaire de PR classique.

```bash
gh issue comment {pr_number} --body "..."
```

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
