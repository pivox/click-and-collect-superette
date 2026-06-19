# Prompts — S15 première connexion marchand

Ce fichier conserve uniquement les prompts encore utiles pour terminer l'epic
**#501 — Première connexion marchand**.

État au 19 juin 2026 :

- **#512 — Activation email marchand** : livrée via PR #546 ;
- **#513 — Accès temporaire première connexion marchand** : livrée via PR #548 ;
- **#517 — QA première connexion marchand** : livrée via PR #549 ;
- **#515 — Écran invitation première connexion** : encore ouverte ;
- **#501 — Clôture epic première connexion marchand** : ouverte tant que #515
  reste non livrée.

---

## Règles d'exécution restantes

- Ne pas fermer #501 tant que #515 n'est pas livrée ou explicitement sortie du
  périmètre de l'epic.
- Ne pas regrouper #515 et la clôture #501 dans la même PR.
- Avant chaque PR : partir de `main`, `git pull --ff-only origin main`, working
  tree propre.
- Conserver le périmètre MVP strict : pas de paiement en ligne, pas de
  livraison, pas de programme fidélité, pas de marketplace multi-marchands.
- Ne jamais exposer token brut, hash, mot de passe provisoire ancien ou secret
  dans une réponse API, un log ou un snapshot de test.

---

## Prompt restant — #515 Écran invitation première connexion

```text
Tu es l'assistant technique du projet Click & Collect Supérette. Réponds en français.

Objectif : implémenter #515 — Écran invitation première connexion.

Préconditions : #512, #513 et #517 sont mergées ; main local à jour ; endpoints backend d'invitation disponibles.

But : créer l'écran frontend permettant à un marchand invité par email de définir son mot de passe définitif via le lien d'invitation.

Avant codage : lire #515, #501, PR #546, PR #548, PR #549, le contrat API Sprint 15, `/merchant/premiere-connexion`, les services frontend auth marchand, les tests `merchant*.test.tsx`, les conventions Next.js.

Périmètre : page de finalisation d'invitation, récupération du token depuis l'URL, formulaire mot de passe + confirmation, validations front simples, appel API de finalisation, erreurs token invalide/expiré/utilisé/mot de passe invalide, redirection après succès, responsive, cohérence design.

Contraintes UX : message clair, ne pas afficher le token, ne pas exposer d'informations sensibles, gérer loading/succès/token manquant/lien expiré/déjà utilisé, proposer une action utile en erreur.

Contraintes techniques : réutiliser composants/services/conventions existants, factoriser si simple avec `/merchant/premiere-connexion`, ne pas casser `temporary_password`.

Tests obligatoires : rendu page, validation mot de passe/confirmation, confirmation différente, succès token valide, messages expiré/utilisé/invalide/manquant, erreur API générique, redirections attendues.

Documentation : mettre à jour la documentation Sprint 15 et/ou QA si le parcours frontend d'invitation change les limites connues.

Hors périmètre : ne pas modifier backend #512 sauf bug bloquant, ne pas refaire mot de passe provisoire, pas de MFA/2FA, ne pas fermer #501.

Livrable : PR dédiée à #515, Ready for review, description complète avec `Closes #515`, `Refs #501`, tests, limites, hors périmètre.

Après PR #515 : attendre Codex, lire, corriger, répondre, marquer résolu, attendre validation, merger, mettre `main` à jour. Ensuite vérifier si la QA frontend invitation email est couverte par #515 ; sinon ouvrir une issue QA complémentaire avant de reprendre #501.
```

---

## Prompt final — #501 Clôture epic première connexion marchand

```text
Tu es l'assistant technique du projet Click & Collect Supérette. Réponds en français.

Objectif : clôturer proprement #501 — Première connexion marchand.

Préconditions : #512, #513, #515 et #517 sont mergées et fermées, ou explicitement justifiées ; `main` local à jour.

But : vérifier que les deux modes de première connexion sont terminés, documentés et testés.

Vérifications : lien invitation fonctionnel, lien expirant, lien à usage unique, connexion avec mot de passe provisoire, redirection obligatoire vers définition du mot de passe, aucune page métier marchand avant finalisation, secrets jamais exposés après génération, documentation alignée, tests #517 ou QA complémentaire couvrant les deux modes, #527 non bloquant.

Périmètre : pas de feature. Mise à jour documentaire seulement si nécessaire. Préparer un commentaire GitHub de clôture avec résumé des PRs et tests.

Décision : si tous les critères sont couverts, fermer #501 comme completed après validation ; sinon ne pas fermer et créer/mettre à jour une issue enfant précise.

Livrable : PR documentaire dédiée à #501 si nécessaire, Ready for review ; sinon commentaire de clôture prêt à coller et fermeture après validation humaine.

Après PR éventuelle : attendre Codex, lire, corriger, répondre, marquer résolu, attendre validation, merger, mettre `main` à jour, compacter le contexte final, fermer #501 seulement si tous les critères sont couverts.
```
