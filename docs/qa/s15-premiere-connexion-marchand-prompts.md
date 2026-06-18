# Prompts — S15 première connexion marchand

Ce fichier unique contient les prompts à exécuter, dans l’ordre, pour terminer l’epic **#501 — Première connexion marchand**.

Issues restantes :

1. **#512 — Activation email marchand**
2. **#513 — Accès temporaire première connexion marchand**
3. **#515 — Écran invitation première connexion**
4. **#517 — QA première connexion marchand**
5. **#501 — Clôture epic première connexion marchand**

---

## 0. Règles globales d’exécution

Ces règles s’appliquent à tous les prompts de ce fichier.

### 0.1 Ordre strict

- Exécuter les prompts dans l’ordre du fichier.
- Ne jamais passer au prompt suivant tant que la PR courante n’est pas mergée.
- Ne jamais regrouper plusieurs issues dans une seule PR, sauf si le prompt le demande explicitement.
- Chaque PR doit rester atomique, lisible et limitée à son issue.

### 0.2 État local obligatoire avant chaque prompt

Avant chaque prompt :

```bash
git checkout main
git pull --ff-only origin main
git status
```

Règles :

- Le working tree doit être propre avant de commencer.
- Ne jamais commencer sur une branche sale.
- Ne jamais coder directement sur `main`.
- Créer une branche dédiée avec un nom explicite, par exemple `codex/s15-031-merchant-email-invitation`.
- Vérifier les issues et PRs existantes avant de coder pour éviter les doublons.

### 0.3 Lecture obligatoire avant codage

Avant de modifier le code :

- lire l’issue concernée ;
- lire les PRs déjà mergées liées : #541, #544, #545 ;
- lire les issues parentes/enfants utiles : #501, #512, #513, #515, #517 ;
- lire le contrat API ;
- lire la documentation Sprint 15 ;
- lire le code existant avant de proposer une nouvelle structure ;
- vérifier les conventions de tests backend/frontend ;
- vérifier les patterns existants API Platform, Symfony, Next.js et tests.

### 0.4 Règles quota et contexte

- Si le quota restant descend à **3 % ou moins**, mettre le travail en pause.
- Ne pas lancer de nouvelle commande lourde, nouvelle PR, nouvelle review ou nouveau prompt avec un quota à 3 % ou moins.
- Attendre que le quota se régénère avant de reprendre.
- Après régénération, relire le dernier état : branche, PR, fichiers modifiés, tests exécutés, commentaires Codex, prochaine action.
- Reprendre exactement au point d’arrêt.
- Si le contexte dépasse **90 %**, lancer une compression/compact avant de continuer.
- La compression doit contenir : issue en cours, branche, PR, décisions prises, fichiers modifiés, tests exécutés, retours Codex, points restants, prochaine action.
- Après compression, vérifier que le contexte compacté suffit pour continuer sans perte d’information.

### 0.5 Cycle PR obligatoire

Pour chaque prompt :

1. Implémenter l’issue.
2. Exécuter les tests ciblés.
3. Exécuter les vérifications minimales utiles : lint, typecheck, phpunit/vitest selon périmètre.
4. Mettre à jour la documentation concernée.
5. Ouvrir une PR en **Ready for review**, jamais en draft.
6. La PR doit contenir : résumé, issue liée, périmètre livré, hors périmètre, tests exécutés, risques/limites.
7. Attendre les retours Codex.
8. Lire tous les retours Codex : conversation, review globale, inline comments.
9. Corriger chaque retour.
10. Répondre à chaque commentaire avec ce qui a été fait.
11. Marquer un thread résolu uniquement après correction réelle.
12. Attendre que Codex valide la PR.
13. Merger uniquement après validation Codex.
14. Revenir sur `main` et mettre à jour :

```bash
git checkout main
git pull --ff-only origin main
```

15. Supprimer la branche locale si elle n’est plus utile.
16. Compacter le contexte avec le résumé de la PR mergée.
17. Passer au prompt suivant.

### 0.6 Règles anti-dérive

- Ne pas ajouter de feature hors issue.
- Ne pas réécrire une architecture complète si une extension locale suffit.
- Ne pas modifier paiement, livraison, marketplace, QR/share ou Messenger sauf demande explicite de l’issue.
- Ne pas rouvrir #543 sauf si un test strict impose une correction de configuration.
- Ne pas modifier les secrets, tokens, mots de passe ou logs sans vérifier la sécurité.
- Ne jamais exposer token brut, password hash, mot de passe temporaire ancien ou secret dans une réponse API, log ou test snapshot.
- Ne pas fermer #501 tant que #512, #513, #515 et #517 ne sont pas terminées ou explicitement justifiées.

### 0.7 Règles de reprise après interruption

Si le travail est interrompu :

1. Vérifier la branche courante.
2. Vérifier `git status`.
3. Relire la dernière PR ouverte.
4. Relire les derniers retours Codex.
5. Relancer uniquement les tests nécessaires pour confirmer l’état.
6. Continuer depuis le dernier point stable.
7. Ne pas recommencer l’issue depuis zéro si le travail est déjà engagé.

### 0.8 Règles de validation finale avant merge

Avant merge :

- tous les tests annoncés dans la PR doivent être exécutés ;
- aucun commentaire Codex non traité ;
- aucun thread non résolu si une correction est attendue ;
- la documentation doit être alignée avec le comportement réel ;
- la PR doit fermer uniquement l’issue réellement couverte ;
- les limites connues doivent être écrites dans la description de PR.

---

## Prompt 1 — #512 Activation email marchand

```text
Tu es l’assistant technique du projet Click & Collect Supérette. Réponds en français.

Objectif : implémenter #512 — Activation email marchand.

Contexte : #501 reste ouverte. #541 livre l’onboarding admin. #544 livre le mode `temporary_password`, mais laisse l’invitation email hors périmètre. Ne pas refaire le mode mot de passe provisoire.

But : permettre à un admin d’inviter un marchand à définir son mot de passe via un lien email sécurisé.

Périmètre :
- création d’une invitation par admin ;
- token opaque, robuste et non prédictible ;
- token hashé en base, jamais stocké en clair ;
- expiration configurable ;
- usage unique ;
- renvoi possible ;
- renvoi qui invalide ou remplace proprement l’invitation active précédente ;
- définition du mot de passe définitif par le marchand ;
- refus des invitations expirées, utilisées, révoquées ou invalides ;
- audit/trace des actions importantes ;
- aucune exposition de secret, hash ou token en réponse API.

Avant codage : lire `User`, `Shop`, les resources/processors admin, le flow `passwordChangeRequired` de #544, l’onboarding #541, le reset temporaire admin, les conventions API Platform, les tests fonctionnels, l’audit/log et l’infrastructure mail existante.

Design attendu : modèle persistant dédié ou modèle existant adapté avec marchand cible, hash du token, expiration, utilisation, création, auteur admin si disponible, statut ou équivalent. Génération cryptographiquement sûre. Comparaison sûre. Pas d’invitations actives concurrentes ambiguës.

Endpoints attendus ou équivalents : endpoint admin créer/envoyer, endpoint admin renvoyer, endpoint public/marchand vérifier/finaliser, endpoint de définition du mot de passe à partir du token.

Email : envoyer un lien frontend construit depuis la configuration existante, sans URL de production en dur, sans réouvrir #543. Contenu simple : objectif, expiration, consigne de sécurité.

Tests obligatoires : création admin OK, refus sans admin, stockage hashé, finalisation token valide, refus expiré/utilisé/invalide, renvoi qui remplace ou invalide, login avec mot de passe définitif, secrets non exposés, audit/trace.

Documentation : contrat API, doc Sprint 15, notes sécurité si nécessaire.

Hors périmètre : ne pas refaire `temporary_password`, ne pas refaire onboarding admin, ne pas modifier QR/share/FRONTEND_URL hors lien invitation, pas de MFA/2FA, paiement, marketplace, livraison, ne pas fermer #501.

Livrable : PR dédiée à #512, Ready for review, description complète avec `Closes #512`, `Refs #501`, tests, limites, hors périmètre.

Après PR : attendre Codex, lire, corriger, répondre, marquer résolu, attendre validation, merger, mettre `main` à jour, compacter, passer au prompt #513.
```

---

## Prompt 2 — #513 Accès temporaire première connexion marchand

```text
Tu es l’assistant technique du projet Click & Collect Supérette. Réponds en français.

Objectif : terminer #513 — Accès temporaire première connexion marchand.

Contexte : #541 génère le mot de passe temporaire one-shot. #544 livre `passwordChangeRequired`, le changement obligatoire, le blocage dashboard/API et `/merchant/premiere-connexion`. #513 reste ouverte pour expiration configurable, régénération complète et refus d’accès expiré.

But : sécuriser complètement le mode mot de passe provisoire.

Avant codage : lire #541, #544, le code de reset temporaire, `MerchantAdminApiTest`, `MerchantFirstLoginApiTest`, `MerchantAccountApiTest`, les champs existants sur `User`, les conventions d’audit/log.

Périmètre : génération/régénération admin, expiration configurable, remplacement de l’accès précédent, affichage one-shot, aucun stockage en clair, changement obligatoire vers mot de passe définitif, refus d’accès expiré, audit/trace.

Design attendu : ajouter les champs nécessaires si absents (`temporaryPasswordExpiresAt`, `temporaryPasswordGeneratedAt` ou équivalent). Distinguer mot de passe provisoire, mot de passe définitif et invitation email. Après finalisation définitive, l’expiration passée ne doit plus bloquer le compte. Régénération admin : remettre `passwordChangeRequired` à true et remplacer l’ancien accès.

Comportement : temporaire valide → connexion puis changement obligatoire ; temporaire expiré → refus clair ; régénération → ancien accès remplacé ; après définitif → accès normal.

Tests obligatoires : génération admin, one-shot, secrets non exposés, connexion temporaire valide, expiré refusé, régénération remplace, ancien accès refusé si testable, accès normal après finalisation, audit/trace.

Documentation : contrat API admin, doc Sprint 15/runbook, durée d’expiration configurable.

Hors périmètre : ne pas implémenter invitation email, ne pas modifier #515, ne pas fermer #501, ne pas refaire onboarding admin.

Livrable : PR dédiée à #513, Ready for review, description complète avec `Closes #513`, `Refs #501`, tests, limites, hors périmètre.

Après PR : attendre Codex, lire, corriger, répondre, marquer résolu, attendre validation, merger, mettre `main` à jour, compacter, passer au prompt #515.
```

---

## Prompt 3 — #515 Écran invitation première connexion

```text
Tu es l’assistant technique du projet Click & Collect Supérette. Réponds en français.

Objectif : implémenter #515 — Écran invitation première connexion.

Préconditions : #512 mergée, `main` local à jour, endpoints backend d’invitation disponibles.

But : créer l’écran frontend permettant à un marchand invité par email de définir son mot de passe définitif via le lien d’invitation.

Avant codage : lire #515, la PR #512, le contrat API, `/merchant/premiere-connexion` de #544, les services frontend auth marchand, les tests `merchant*.test.tsx`, les conventions Next.js.

Périmètre : page de finalisation d’invitation, récupération du token depuis l’URL, formulaire mot de passe + confirmation, validations front simples, appel API de finalisation, erreurs token invalide/expiré/utilisé/mot de passe invalide, redirection après succès, responsive, cohérence design.

Contraintes UX : message clair, ne pas afficher le token, ne pas exposer d’informations sensibles, gérer loading/succès/token manquant/lien expiré/déjà utilisé, proposer une action utile en erreur.

Contraintes techniques : réutiliser composants/services/conventions existants, factoriser si simple avec `/merchant/premiere-connexion`, ne pas casser `temporary_password`.

Tests obligatoires : rendu page, validation mot de passe/confirmation, confirmation différente, succès token valide, messages expiré/utilisé/invalide/manquant, erreur API générique, responsive ou structure selon pratiques.

Documentation : parcours marchand/frontend et Sprint 15 si nécessaire.

Hors périmètre : ne pas modifier backend #512 sauf bug bloquant, ne pas refaire mot de passe provisoire, pas de MFA/2FA, ne pas fermer #501.

Livrable : PR dédiée à #515, Ready for review, description complète avec `Closes #515`, `Refs #501`, tests, limites, hors périmètre.

Après PR : attendre Codex, lire, corriger, répondre, marquer résolu, attendre validation, merger, mettre `main` à jour, compacter, passer au prompt #517.
```

---

## Prompt 4 — #517 QA première connexion marchand

```text
Tu es l’assistant technique du projet Click & Collect Supérette. Réponds en français.

Objectif : implémenter #517 — QA première connexion marchand.

Préconditions : #512 mergée, #513 mergée ou clarifiée, #515 mergée, `main` local à jour.

But : ajouter une couverture de non-régression complète pour les deux modes : invitation email et accès temporaire/mot de passe provisoire.

Avant codage : lire #517, #541, #544, #512, #513, #515, les tests backend onboarding/marchand/login/première connexion, les tests frontend `merchant*.test.tsx` et admin drawer/page. Identifier les trous réels avant d’ajouter des tests.

Périmètre QA : invitation marchand, accès temporaire, finalisation mot de passe, expiration, réutilisation refusée, statut actif après finalisation, blocage dashboard/API avant finalisation, accès normal après finalisation.

Tests backend à couvrir : création invitation, finalisation valide, expirée refusée, utilisée refusée, invalide refusée, renvoi conforme à la règle retenue, accès temporaire valide, accès temporaire expiré, régénération, blocage routes métier avant finalisation, accès après finalisation, secrets non exposés.

Tests frontend à couvrir : écran invitation succès, expiré, utilisé, invalide/manquant, première connexion mot de passe provisoire si manque, redirections auth, messages d’erreur.

Contraintes : pas de feature non demandée, corriger seulement les bugs bloquants révélés par tests, créer une issue dédiée si bug hors scope, tests déterministes, pas de vrai provider email externe, sécurité non permissive.

Documentation : doc QA/Sprint 15 avec scénarios couverts ; PR citant tous les tests exécutés.

Livrable : PR dédiée à #517, Ready for review, description complète avec `Closes #517`, `Refs #501`, tests, limites, hors périmètre.

Après PR : attendre Codex, lire, corriger, répondre, marquer résolu, attendre validation, merger, mettre `main` à jour, compacter, passer au prompt #501.
```

---

## Prompt 5 — #501 Clôture epic première connexion marchand

```text
Tu es l’assistant technique du projet Click & Collect Supérette. Réponds en français.

Objectif : clôturer proprement #501 — Première connexion marchand.

Préconditions : #512, #513, #515 et #517 sont mergées et fermées, ou explicitement justifiées ; `main` local à jour.

But : vérifier que les deux modes de première connexion sont terminés, documentés et testés.

Vérifications : lien invitation fonctionnel, lien expirant, lien à usage unique, connexion avec mot de passe provisoire, redirection obligatoire vers définition du mot de passe, aucune page métier avant finalisation, secrets jamais exposés après génération, documentation alignée, tests #517 couvrant les deux modes, #527 non bloquant.

Périmètre : pas de feature. Mise à jour documentaire seulement si nécessaire. Préparer un commentaire GitHub de clôture avec résumé des PRs et tests.

Décision : si tous les critères sont couverts, fermer #501 comme completed après validation ; sinon ne pas fermer et créer/mettre à jour une issue enfant précise.

Livrable : PR documentaire dédiée à #501 si nécessaire, Ready for review ; sinon commentaire de clôture prêt à coller et fermeture après validation humaine.

Après PR éventuelle : attendre Codex, lire, corriger, répondre, marquer résolu, attendre validation, merger, mettre `main` à jour, compacter le contexte final, fermer #501 seulement si tous les critères sont couverts.
```
