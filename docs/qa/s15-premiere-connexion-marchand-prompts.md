# Prompts — S15 première connexion marchand

Ce fichier conserve uniquement les prompts encore utiles pour terminer l'epic
**#501 — Première connexion marchand**.

État au 19 juin 2026 :

- **#512 — Activation email marchand** : livrée via PR #546 ;
- **#513 — Accès temporaire première connexion marchand** : livrée via PR #548 ;
- **#517 — QA première connexion marchand** : livrée via PR #549 ;
- **#515 — Écran invitation première connexion** : en cours de livraison via la
  PR dédiée ;
- **#501 — Clôture epic première connexion marchand** : ouverte jusqu'au merge
  de #515 et à la vérification finale.

---

## Règles d'exécution restantes

- Ne pas regrouper #515 et la clôture #501 dans la même PR.
- Avant chaque PR : partir de `main`, `git pull --ff-only origin main`, working
  tree propre.
- Conserver le périmètre MVP strict : pas de paiement en ligne, pas de
  livraison, pas de programme fidélité, pas de marketplace multi-marchands.
- Ne jamais exposer token brut, hash, mot de passe provisoire ancien ou secret
  dans une réponse API, un log ou un snapshot de test.

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
