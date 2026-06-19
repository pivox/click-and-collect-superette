# Prompt Codex — Issue #554 — UX delivery_failed invitation onboarding admin

Ce fichier est à donner à Codex CLI tel quel.

## Lancement recommandé

Depuis le dépôt local :

```bash
git checkout main
git pull --ff-only origin main
git status
```

Puis lancer Codex avec une instruction courte :

```text
Lis le fichier docs/qa/prompt-554-delivery-failed-ux.md et exécute uniquement ce prompt. Ne traite aucune autre issue.
```

---

## Prompt

Tu es l’assistant technique du projet Click & Collect Supérette.

Langue : réponds toujours en français.

Objectif : implémenter l’issue #554 — UX : améliorer le rendu `delivery_failed` de l’invitation onboarding admin.

## Contexte

Suite à #553, l’onboarding admin marchand supporte deux modes de première connexion :

- `temporary_password` ;
- `email_invitation`.

En mode `email_invitation`, si l’envoi email échoue après création du marchand, de la supérette et du token, l’API retourne :

```text
invitation_status: delivery_failed
```

Ce comportement backend est volontaire :

- l’onboarding reste créé ;
- l’échec est audité ;
- l’admin peut relancer l’invitation.

Le problème est uniquement UX : le drawer admin affiche déjà `Invitation email non envoyée` et un bouton `Renvoyer l’invitation`, mais ce message est dans le bloc global vert `Onboarding créé`, donc l’échec email n’est pas assez visible.

## Fichiers à lire obligatoirement avant modification

- `apps/frontend/src/components/admin/marchands/MerchantDrawer.tsx`
- `apps/frontend/src/tests/admin.merchant-drawer.test.tsx`
- les types/services frontend admin marchands liés à l’onboarding si utilisés par le drawer ;
- les PRs/issues liées #552 et #553 si nécessaire pour comprendre la décision produit.

## Périmètre strict

- Modifier uniquement le rendu frontend du drawer admin.
- Ne pas changer le backend.
- Ne pas modifier la transaction backend.
- Ne pas modifier le contrat API.
- Ne pas ajouter d’endpoint.
- Ne pas revenir sur la décision produit #552 concernant les groupements après création.
- Ne jamais afficher token, secret, hash ou mot de passe.

## Comportement attendu

Quand `first_login.mode = email_invitation` et `invitation_status = delivery_failed` :

- afficher un état visuel clairement distinct d’un succès complet ;
- utiliser un rendu type warning / amber / action requise ;
- expliquer clairement que le marchand et la supérette sont créés, mais que l’email d’invitation n’a pas été envoyé ;
- garder visible le bouton `Renvoyer l’invitation` ;
- conserver le résumé existant de création marchand/supérette et de préchargement catalogue ;
- ne pas transformer tout l’onboarding en erreur bloquante.

Quand le renvoi réussit :

- l’état doit redevenir clairement positif ;
- afficher `Invitation email envoyée` ou message équivalent ;
- retirer ou remplacer le warning `delivery_failed`.

## Critères d’acceptation

- Le cas `delivery_failed` est visuellement distinct d’un succès complet.
- Le message indique que la création marchand/supérette est OK, mais que l’email n’a pas été envoyé.
- Le bouton de renvoi reste disponible.
- Après renvoi réussi, l’état devient positif.
- Aucun changement backend.
- Les tests frontend du drawer admin sont adaptés.

## Tests attendus

Adapter ou ajouter des tests dans :

```text
apps/frontend/src/tests/admin.merchant-drawer.test.tsx
```

Tester au minimum :

- le rendu `delivery_failed` ;
- la présence du bouton `Renvoyer l’invitation` ;
- le message indiquant que marchand/supérette sont créés mais que l’email n’a pas été envoyé ;
- l’état positif après renvoi réussi ;
- l’absence d’affichage de token, secret, hash ou mot de passe si le cas est couvert par les helpers existants.

Commandes minimales :

```bash
cd apps/frontend
npm run test:run -- src/tests/admin.merchant-drawer.test.tsx
npm run lint
```

Si nécessaire, lancer aussi les tests frontend proches du drawer admin.

## Livrable

- Une PR dédiée uniquement à #554.
- PR ouverte en Ready for review, pas en draft.
- Description de PR avec :
  - résumé ;
  - `Closes #554` ;
  - périmètre livré ;
  - hors périmètre ;
  - tests exécutés ;
  - limites éventuelles.

## Cycle review obligatoire

- Attendre les retours Codex.
- Lire tous les commentaires.
- Corriger chaque point.
- Répondre à chaque commentaire.
- Marquer les threads résolus uniquement après correction.
- Attendre validation Codex.
- Merger seulement après validation.
- Puis mettre `main` local à jour :

```bash
git checkout main
git pull --ff-only origin main
```

## Règles quota et contexte

- Si le quota restant descend à 3 % ou moins, arrêter le travail et attendre la régénération.
- Si le contexte dépasse 90 %, compacter avant de continuer.
- Après reprise, relire : branche, PR, commentaires Codex, fichiers modifiés, tests exécutés et prochaine action.

## Important

Cette PR est une amélioration UX ciblée. Ne pas élargir le scope.
