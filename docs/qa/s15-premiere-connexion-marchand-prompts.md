# Prompts — S15 première connexion marchand

Ce fichier regroupe les prompts à exécuter un par un pour terminer l’epic #501 — Première connexion marchand.

Issues concernées :

- #512 — Activation email marchand
- #513 — Accès temporaire première connexion marchand
- #515 — Écran invitation première connexion
- #517 — QA première connexion marchand
- #501 — Clôture epic première connexion marchand

## Protocole obligatoire entre chaque prompt

À respecter strictement pour chaque issue.

1. Toujours partir de `main` à jour.
2. Créer une branche dédiée à une seule issue.
3. Lire l’issue GitHub concernée, les PRs déjà mergées liées (#541, #544, #545), la documentation Sprint 15, le contrat API et le code existant avant de coder.
4. Ne jamais mélanger plusieurs issues dans une même PR, sauf si le prompt le demande explicitement.
5. Faire une PR petite, atomique, testée et documentée.
6. Ouvrir la PR en **Ready for review**, jamais en draft.
7. La description de PR doit contenir : résumé, issue(s) liée(s), périmètre livré, hors périmètre, vérifications exécutées, risques ou limites.
8. Après ouverture de la PR, attendre les retours de Codex.
9. Lire tous les retours Codex : commentaires de conversation, review globale et commentaires inline.
10. Corriger chaque point demandé.
11. Répondre à chaque commentaire avec ce qui a été fait.
12. Marquer chaque thread résolu uniquement après correction réelle.
13. Pousser les corrections sur la même branche.
14. Attendre une nouvelle validation Codex.
15. Répéter la boucle tant que Codex n’a pas validé la PR.
16. Une fois validée, merger la PR.
17. Mettre à jour le `main` local :

```bash
git checkout main
git pull --ff-only origin main
```

18. Supprimer la branche locale si elle n’est plus utile.
19. Compacter le contexte de travail avec un résumé clair de ce qui vient d’être livré.
20. Passer seulement ensuite au prompt suivant.

Important : ne pas passer au prompt suivant tant que la PR précédente n’est pas mergée et que le `main` local n’est pas à jour.

---

## Prompt 1 — Issue #512 — Activation email marchand

```text
Tu es l’assistant technique du projet Click & Collect Supérette.

Langue : français.

Objectif : implémenter l’issue #512 — Activation email marchand.

Contexte :
- L’epic #501 première connexion marchand reste ouverte.
- Le mode `temporary_password` est déjà livré par la PR #544.
- L’onboarding admin est livré par la PR #541.
- L’invitation email est explicitement hors périmètre de #544 et reste à implémenter.
- Ne pas refaire le flow `temporary_password` déjà livré.

But fonctionnel :
Permettre à un admin d’inviter un marchand à définir son mot de passe via un lien email sécurisé.

Périmètre fonctionnel attendu :
1. Un admin peut créer une invitation de première connexion pour un marchand.
2. L’invitation contient un token opaque, robuste, non prédictible.
3. Le token ne doit jamais être stocké en clair en base.
4. Le token doit être expirant.
5. Le token doit être à usage unique.
6. Le renvoi d’invitation doit être possible.
7. Le renvoi doit invalider ou remplacer proprement l’invitation active précédente.
8. Le marchand peut définir son mot de passe définitif via le token.
9. Une invitation expirée doit être refusée.
10. Une invitation déjà utilisée doit être refusée.
11. Les actions importantes doivent être tracées/auditées.
12. Aucun secret, token brut, password hash ou information sensible ne doit être exposé après génération.

Travail de découverte obligatoire avant codage :
- Lire les entités `User`, `Shop`, les processors/admin resources existants et le flow `passwordChangeRequired` livré par #544.
- Lire le code d’onboarding admin livré par #541.
- Lire le reset de mot de passe temporaire admin existant.
- Identifier les conventions API Platform du projet.
- Identifier les conventions de tests fonctionnels backend.
- Identifier la stratégie d’audit/log existante.
- Identifier l’infrastructure mail existante. Si elle n’existe pas, créer une abstraction minimale, isolée et testable, sans coupler le métier à un provider externe.

Design technique attendu :
- Créer un modèle persistant dédié à l’invitation ou utiliser un modèle existant si déjà présent, mais sans détourner une entité non adaptée.
- Champs attendus ou équivalents : marchand cible, hash du token, date d’expiration, date d’utilisation, date de création, auteur admin si disponible, statut ou informations permettant de distinguer actif/expiré/utilisé/révoqué.
- Prévoir une configuration de durée d’expiration avec une valeur par défaut raisonnable.
- Générer le token avec une source cryptographiquement sûre.
- Hasher le token avant stockage.
- Comparer le token reçu avec le hash stocké sans exposer le token brut.
- S’assurer qu’il n’existe pas deux invitations actives concurrentes ambiguës pour le même marchand.
- Prévoir une stratégie claire pour le renvoi : soit révocation des anciennes invitations actives, soit remplacement atomique.
- Réutiliser le mécanisme `passwordChangeRequired` si cohérent, sans casser le mode mot de passe provisoire.

Endpoints attendus ou équivalents selon les conventions du projet :
- Endpoint admin pour créer/envoyer une invitation marchand.
- Endpoint admin pour renvoyer une invitation marchand si nécessaire.
- Endpoint public ou marchand pour vérifier/finaliser l’invitation.
- Endpoint de finalisation permettant de définir le mot de passe définitif à partir du token.

Contraintes de sécurité :
- Admin requis sur les endpoints admin.
- Token brut visible uniquement au moment de génération et uniquement pour composer le lien email.
- Token brut jamais loggé.
- Token brut jamais stocké en base.
- Mot de passe définitif validé selon les règles existantes du projet.
- Réponses d’erreur claires côté front mais sans fuite inutile d’informations sensibles.
- Une invitation utilisée ne peut jamais être réutilisée.
- Une invitation expirée ne peut jamais activer un compte.
- Une invitation révoquée/remplacée ne peut jamais être utilisée.

Email :
- Envoyer un email d’invitation contenant un lien frontend construit proprement à partir de la configuration existante.
- Ne pas hardcoder d’URL de production.
- Si `FRONTEND_URL` est utilisé, respecter les règles existantes et ne pas réouvrir le sujet #543.
- Le contenu doit être simple : nom du marchand si disponible, objectif du lien, expiration, consigne de sécurité.

Tests obligatoires :
- Création d’invitation par admin OK.
- Création refusée sans rôle admin.
- Token stocké hashé, jamais en clair.
- Finalisation avec token valide OK.
- Finalisation avec token expiré refusée.
- Finalisation avec token déjà utilisé refusée.
- Finalisation avec token invalide refusée.
- Renvoi d’invitation remplace/invalide l’ancienne selon la règle retenue.
- Après finalisation, le marchand peut utiliser son mot de passe définitif.
- Les secrets ne sont pas exposés dans les réponses API.
- Les actions importantes sont auditées ou tracées.

Documentation à mettre à jour :
- Contrat API concerné.
- Documentation Sprint 15 ou document équivalent.
- Notes de sécurité si nécessaire.

Hors périmètre :
- Ne pas refaire le flow `temporary_password`.
- Ne pas refaire l’onboarding admin.
- Ne pas modifier les règles QR/share/FRONTEND_URL hors strict besoin de construire le lien.
- Ne pas ajouter MFA/2FA.
- Ne pas ajouter paiement, marketplace ou livraison.
- Ne pas fermer #501 dans cette PR.

Livrable :
- Une PR dédiée à #512.
- PR ouverte en Ready for review, pas en draft.
- Description de PR complète avec : résumé, `Closes #512`, `Refs #501`, tests exécutés, limites et hors périmètre.

Après ouverture de PR :
- Attendre les retours Codex.
- Lire tous les commentaires Codex.
- Corriger chaque point.
- Répondre à chaque commentaire.
- Marquer les threads résolus après correction.
- Attendre validation Codex.
- Une fois validée, merger.
- Mettre `main` local à jour.
- Compacter le contexte.
- Passer seulement ensuite au prompt #513.
```

---

## Prompt 2 — Issue #513 — Accès temporaire première connexion marchand

```text
Tu es l’assistant technique du projet Click & Collect Supérette.

Langue : français.

Objectif : terminer l’issue #513 — Accès temporaire première connexion marchand.

Contexte :
- Le mode mot de passe provisoire existe déjà via #541 et #544.
- #541 a livré la génération one-shot côté admin.
- #544 a livré `passwordChangeRequired`, le changement obligatoire au premier login, le blocage dashboard/API métier et la page `/merchant/premiere-connexion`.
- L’issue #513 reste ouverte car l’expiration configurable, la régénération complète et le refus d’un accès temporaire expiré doivent être vérifiés ou finalisés.

But fonctionnel :
Sécuriser complètement le mode mot de passe provisoire en ajoutant ou validant l’expiration configurable, la régénération et le refus d’un accès temporaire expiré.

Travail de découverte obligatoire avant codage :
- Lire la PR #541 et le code actuel de génération/reset du mot de passe temporaire.
- Lire la PR #544 et le code `passwordChangeRequired`.
- Lire les tests existants liés à `MerchantAdminApiTest`, `MerchantFirstLoginApiTest`, `MerchantAccountApiTest` et équivalents.
- Vérifier si des champs d’expiration existent déjà sur `User` ou une autre entité.
- Vérifier les conventions d’audit/log admin.

Périmètre attendu :
1. L’admin peut générer ou régénérer un accès temporaire.
2. L’accès temporaire a une expiration configurable.
3. La régénération remplace l’accès précédent.
4. Le mot de passe temporaire reste affiché une seule fois.
5. Le mot de passe temporaire n’est jamais stocké en clair.
6. Le marchand doit définir un mot de passe définitif après connexion temporaire.
7. Un accès temporaire expiré est refusé.
8. Les actions importantes sont tracées.

Design technique attendu :
- Ajouter les champs nécessaires si absents, par exemple `temporaryPasswordExpiresAt`, `temporaryPasswordGeneratedAt`, ou équivalent.
- Ne pas rendre le modèle confus : distinguer clairement mot de passe provisoire, mot de passe définitif et invitation email.
- Si le marchand a déjà défini son mot de passe définitif, l’expiration de l’ancien accès temporaire ne doit plus bloquer le compte.
- Lors d’une régénération admin, remettre `passwordChangeRequired` à `true` et remplacer l’accès temporaire précédent.
- Prévoir une configuration de durée par défaut, par exemple via env/config Symfony, sans hardcoder partout.

Comportement attendu :
- Connexion avec mot de passe temporaire valide : autorisée, puis redirection/changement obligatoire déjà porté par #544.
- Connexion avec mot de passe temporaire expiré : refus clair, code d’erreur stable, pas d’accès dashboard.
- Régénération admin : nouveau mot de passe provisoire valide, ancien accès remplacé.
- Après définition du mot de passe définitif : accès normal, `passwordChangeRequired = false`, aucun blocage par l’expiration temporaire passée.

Tests obligatoires :
- Génération accès temporaire par admin OK.
- Mot de passe temporaire retourné une seule fois.
- Hash/secrets non exposés.
- Connexion avec accès temporaire valide OK puis changement obligatoire.
- Connexion avec accès temporaire expiré refusée.
- Régénération remplace l’ancien accès.
- Ancien accès après régénération refusé si le design permet de le tester.
- Après finalisation du mot de passe définitif, le marchand accède normalement.
- Audit ou trace des actions importantes.

Documentation à mettre à jour :
- Contrat API admin lié au mot de passe temporaire.
- Documentation Sprint 15 ou runbook admin.
- Mentionner la durée d’expiration configurable.

Hors périmètre :
- Ne pas implémenter l’invitation email si #512 n’est pas la PR courante.
- Ne pas modifier le flow front invitation email #515.
- Ne pas fermer #501 dans cette PR.
- Ne pas refaire l’onboarding admin.

Livrable :
- Une PR dédiée à #513.
- PR ouverte en Ready for review, pas en draft.
- Description de PR complète avec : résumé, `Closes #513`, `Refs #501`, tests exécutés, limites et hors périmètre.

Après ouverture de PR :
- Attendre les retours Codex.
- Lire tous les commentaires Codex.
- Corriger chaque point.
- Répondre à chaque commentaire.
- Marquer les threads résolus après correction.
- Attendre validation Codex.
- Une fois validée, merger.
- Mettre `main` local à jour.
- Compacter le contexte.
- Passer seulement ensuite au prompt #515.
```

---

## Prompt 3 — Issue #515 — Écran invitation première connexion

```text
Tu es l’assistant technique du projet Click & Collect Supérette.

Langue : français.

Objectif : implémenter l’issue #515 — Écran invitation première connexion.

Précondition :
- La PR #512 doit être mergée avant de commencer.
- Le `main` local doit être à jour.
- Les endpoints backend d’invitation email doivent être disponibles.

But fonctionnel :
Créer l’écran frontend permettant à un marchand invité par email de définir son mot de passe définitif via le lien d’invitation.

Travail de découverte obligatoire avant codage :
- Lire l’issue #515.
- Lire la PR #512 mergée et le contrat API final.
- Lire la page `/merchant/premiere-connexion` déjà livrée par #544 pour réutiliser les patterns UX et techniques.
- Lire les services frontend d’auth marchand.
- Lire les tests frontend existants `merchant*.test.tsx`.
- Identifier les conventions de routing Next.js du projet.

Périmètre attendu :
1. Créer la page de finalisation d’invitation.
2. Récupérer le token depuis l’URL selon le contrat retenu par #512.
3. Afficher un formulaire de nouveau mot de passe.
4. Afficher un champ de confirmation du mot de passe.
5. Valider côté front les erreurs simples avant appel API.
6. Appeler l’endpoint backend de finalisation invitation.
7. Afficher les erreurs API : token invalide, expiré, déjà utilisé, mot de passe invalide.
8. Rediriger après succès vers le parcours prévu : login marchand ou espace marchand selon le contrat backend.
9. L’écran doit être responsive.
10. L’écran doit rester cohérent avec le design existant du projet.

Contraintes UX :
- Message clair : “Définir mon mot de passe marchand” ou équivalent.
- Ne pas afficher le token.
- Ne pas exposer d’informations sensibles dans les erreurs.
- Gérer l’état loading.
- Gérer l’état succès.
- Gérer l’état token manquant.
- Gérer l’état lien expiré ou déjà utilisé.
- Prévoir une action utile en cas d’erreur : retourner au login ou demander une nouvelle invitation selon le produit existant.

Contraintes techniques :
- Réutiliser les composants, services API et conventions existantes.
- Ne pas dupliquer inutilement la logique du formulaire `/merchant/premiere-connexion` si une factorisation simple est possible.
- Ne pas casser le flow `temporary_password` existant.
- Respecter les conventions de tests frontend du projet.

Tests obligatoires :
- La page se rend correctement.
- Le formulaire exige mot de passe + confirmation.
- Une confirmation différente affiche une erreur.
- Un token valide permet d’appeler l’API et affiche/redirige le succès.
- Token expiré : message clair.
- Token déjà utilisé : message clair.
- Token invalide/manquant : message clair.
- Erreur API générique : message clair.
- Responsive ou structure testée selon les pratiques existantes.

Documentation :
- Mettre à jour la documentation frontend/parcours marchand si elle existe.
- Mettre à jour la documentation Sprint 15 si nécessaire.

Hors périmètre :
- Ne pas modifier les endpoints backend #512 sauf bug bloquant découvert.
- Ne pas refaire le flow mot de passe provisoire.
- Ne pas ajouter MFA/2FA.
- Ne pas fermer #501 dans cette PR.

Livrable :
- Une PR dédiée à #515.
- PR ouverte en Ready for review, pas en draft.
- Description de PR complète avec : résumé, `Closes #515`, `Refs #501`, tests exécutés, limites et hors périmètre.

Après ouverture de PR :
- Attendre les retours Codex.
- Lire tous les commentaires Codex.
- Corriger chaque point.
- Répondre à chaque commentaire.
- Marquer les threads résolus après correction.
- Attendre validation Codex.
- Une fois validée, merger.
- Mettre `main` local à jour.
- Compacter le contexte.
- Passer seulement ensuite au prompt #517.
```

---

## Prompt 4 — Issue #517 — QA première connexion marchand

```text
Tu es l’assistant technique du projet Click & Collect Supérette.

Langue : français.

Objectif : implémenter l’issue #517 — QA première connexion marchand.

Préconditions :
- #512 doit être mergée.
- #513 doit être mergée ou explicitement clarifiée.
- #515 doit être mergée.
- Le `main` local doit être à jour.

But :
Ajouter une couverture de non-régression complète pour les deux modes de première connexion marchand :
1. invitation email ;
2. accès temporaire / mot de passe provisoire.

Travail de découverte obligatoire avant codage :
- Lire l’issue #517.
- Lire les PRs mergées #541, #544, #512, #513 et #515.
- Lire les tests backend existants liés à l’onboarding admin, au marchand, au login et à la première connexion.
- Lire les tests frontend `merchant*.test.tsx` et admin drawer/page.
- Identifier les trous de couverture réels avant d’ajouter des tests.

Périmètre QA attendu :
- Invitation marchand.
- Accès temporaire marchand.
- Finalisation du mot de passe.
- Expiration.
- Réutilisation refusée.
- Statut actif ou équivalent après finalisation.
- Blocage dashboard/API métier avant finalisation.
- Accès normal après finalisation.

Tests backend obligatoires ou à compléter :
1. Admin crée une invitation email.
2. Invitation email valide finalise le mot de passe.
3. Invitation expirée refusée.
4. Invitation déjà utilisée refusée.
5. Invitation invalide refusée.
6. Renvoi d’invitation invalide l’ancien token ou respecte la règle retenue.
7. Accès temporaire valide impose le changement de mot de passe.
8. Accès temporaire expiré refusé.
9. Régénération accès temporaire remplace l’ancien accès.
10. Un marchand non finalisé ne peut pas accéder aux routes métier.
11. Un marchand finalisé peut accéder aux routes métier.
12. Les secrets ne sont pas exposés dans les réponses.

Tests frontend obligatoires ou à compléter :
1. Écran invitation email : succès.
2. Écran invitation email : token expiré.
3. Écran invitation email : token déjà utilisé.
4. Écran invitation email : token invalide/manquant.
5. Écran première connexion mot de passe provisoire : succès déjà existant ou à compléter.
6. Redirections auth cohérentes.
7. Messages d’erreur compréhensibles.

Contraintes :
- Ne pas ajouter de feature non demandée.
- Corriger uniquement les bugs bloquants révélés par les tests.
- Si un bug est découvert et dépasse le scope QA, documenter clairement et créer une issue dédiée plutôt que mélanger.
- Garder les tests déterministes.
- Ne pas dépendre d’un vrai provider email externe.
- Ne pas rendre les tests permissifs sur la sécurité.

Documentation :
- Mettre à jour la documentation QA/Sprint 15 avec la liste des scénarios couverts.
- La PR doit citer tous les tests exécutés.

Livrable :
- Une PR dédiée à #517.
- PR ouverte en Ready for review, pas en draft.
- Description de PR complète avec : résumé, `Closes #517`, `Refs #501`, tests exécutés, limites et hors périmètre.

Après ouverture de PR :
- Attendre les retours Codex.
- Lire tous les commentaires Codex.
- Corriger chaque point.
- Répondre à chaque commentaire.
- Marquer les threads résolus après correction.
- Attendre validation Codex.
- Une fois validée, merger.
- Mettre `main` local à jour.
- Compacter le contexte.
- Passer seulement ensuite au prompt #501.
```

---

## Prompt 5 — Issue #501 — Clôture epic première connexion marchand

```text
Tu es l’assistant technique du projet Click & Collect Supérette.

Langue : français.

Objectif : clôturer proprement l’epic #501 — Première connexion marchand.

Préconditions obligatoires :
- #512 est mergée et fermée.
- #513 est mergée et fermée, ou explicitement fermée avec justification produit si son reste était déjà couvert.
- #515 est mergée et fermée.
- #517 est mergée et fermée.
- Le `main` local est à jour.

But :
Vérifier que les deux modes de première connexion marchand sont réellement terminés, documentés et testés, puis préparer la clôture de #501.

Travail de vérification :
- Lire #501.
- Lire les PRs finales #512, #513, #515, #517.
- Vérifier que les critères de sortie de #501 sont couverts :
  - le marchand invité peut définir son mot de passe via un lien ;
  - le lien expire ;
  - le lien est à usage unique ;
  - le marchand peut se connecter avec un mot de passe provisoire ;
  - le mot de passe provisoire force une redirection vers définition du mot de passe ;
  - aucune page métier marchand n’est accessible avant finalisation ;
  - les tokens et mots de passe provisoires ne sont jamais exposés après génération.
- Vérifier que la documentation est alignée avec le comportement réel.
- Vérifier que les tests de #517 couvrent les deux modes.
- Vérifier que l’issue #527 ou le suivi prioritaire n’indique plus ce bloc comme ouvert, sauf mention historique.

Périmètre attendu :
- Pas de nouvelle feature.
- Mise à jour documentaire uniquement si nécessaire.
- Nettoyage des références obsolètes dans la documentation Sprint 15 ou QA si nécessaire.
- Ajout d’un petit rapport de clôture si utile.
- Préparer un commentaire GitHub de clôture pour #501 avec le résumé des PRs et les tests.

Commentaire GitHub recommandé pour #501 :
- Résumer les deux modes livrés.
- Lister les PRs qui couvrent les critères.
- Mentionner les tests principaux.
- Mentionner les limites restantes si elles existent, mais ne pas fermer si une limite bloque un critère de sortie.

Critère de décision :
- Si tous les critères #501 sont couverts : fermer #501 comme completed après merge de la PR documentaire éventuelle.
- Si un critère n’est pas couvert : ne pas fermer #501 ; créer ou mettre à jour une issue enfant précise.

Livrable :
- Si une mise à jour documentaire est nécessaire : ouvrir une PR dédiée à #501 en Ready for review.
- Si aucune modification code/doc n’est nécessaire : préparer le commentaire de clôture et fermer #501 uniquement après validation humaine.

Après ouverture de PR éventuelle :
- Attendre les retours Codex.
- Lire tous les commentaires Codex.
- Corriger chaque point.
- Répondre à chaque commentaire.
- Marquer les threads résolus après correction.
- Attendre validation Codex.
- Une fois validée, merger.
- Mettre `main` local à jour.
- Compacter le contexte final.
- Fermer #501 seulement après validation que tous les critères de sortie sont couverts.
```
