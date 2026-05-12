# Workflows Claude

## Workflow 1 — Cadrage produit

1. Lire `AI_CONTEXT.md` et `README.md`.
2. Identifier le besoin : MVP, post-MVP ou hors scope.
3. Proposer une décision claire.
4. Mettre à jour la documentation dans `docs/product/`.
5. Lister les impacts sur user stories, API et data model.

## Workflow 2 — User story

Pour chaque user story :

- titre ;
- rôle ;
- besoin ;
- bénéfice ;
- préconditions ;
- scénario nominal ;
- scénarios alternatifs ;
- règles métier ;
- critères d'acceptation ;
- notes techniques.

## Workflow 3 — Architecture Symfony/API Platform

1. Identifier les rôles concernés : client, marchand, admin.
2. Définir les ressources API.
3. Séparer les opérations de lecture et d'écriture si nécessaire.
4. Définir DTO, Entity, Processor, Provider et Security.
5. Ajouter migration et tests.
6. Documenter les décisions.

## Workflow 4 — Modèle de données

1. Partir du vocabulaire métier.
2. Séparer référentiel produit et offre marchand.
3. Vérifier les statuts de commande.
4. Ajouter dates de création, mise à jour, audit minimal.
5. Prévoir la localisation français/arabe.
6. Prévoir les prix en TND.

## Workflow 5 — Revue de changement

Avant de finaliser :

- vérifier cohérence MVP ;
- vérifier vocabulaire métier ;
- vérifier impacts client/marchand/admin ;
- vérifier que les exclusions MVP ne sont pas réintroduites ;
- vérifier les tests ou limites de vérification.

## Workflow 6 — Validation finale (checklist)

### Avant de commencer

- [ ] Lire `AI_CONTEXT.md`.
- [ ] Lire `README.md`.
- [ ] Lire la documentation pertinente dans `docs/`.
- [ ] Identifier si la demande concerne produit, architecture, code ou documentation.
- [ ] Vérifier le périmètre MVP.

### Pendant le travail

- [ ] Garder le vocabulaire métier cohérent.
- [ ] Ne pas ajouter de paiement/livraison/marketplace sans demande explicite.
- [ ] Séparer référentiel produit et offre marchand.
- [ ] Prévoir français/arabe quand l'interface ou les libellés sont concernés.
- [ ] Prévoir TND pour les montants.
- [ ] Faire des changements petits et traçables.

### Avant de répondre

- [ ] Résumer les changements.
- [ ] Lister les fichiers modifiés.
- [ ] Lister les vérifications effectuées.
- [ ] Signaler ce qui n'a pas pu être testé.
- [ ] Lister les risques et prochaines étapes.

### Critères de qualité

- [ ] La réponse est exploitable par un développeur.
- [ ] Le MVP reste cohérent.
- [ ] Les rôles client/marchand/admin sont clairs.
- [ ] Les statuts de commande restent cohérents.
- [ ] Le modèle ne crée pas de dépendance inutile.
