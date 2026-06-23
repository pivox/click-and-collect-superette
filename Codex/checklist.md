# Checklist Codex

## Avant modification

- [ ] `AGENTS.md` lu.
- [ ] `AI_CONTEXT.md` lu comme contexte IA prioritaire du MVP scope, du vocabulaire, des statuts de commande et des entités.
- [ ] `README.md` lu.
- [ ] `docs/project/source-of-truth.md` lu pour la hiérarchie et la précédence documentaire.
- [ ] Documentation métier, sprint ou roadmap pertinente lue.
- [ ] Hypothèses listées.

## Code Symfony/API Platform

- [ ] Entités nommées clairement et orientées métier.
- [ ] Migrations Doctrine présentes si schéma modifié (règles : `.claude/rules/migrations.md`).
- [ ] DTO utilisés quand l'entrée API ne correspond pas directement à l'entité.
- [ ] Groupes de sérialisation explicites.
- [ ] Sécurité client/marchand/admin vérifiée (règles : `.claude/rules/security.md`).
- [ ] Logique métier hors contrôleurs.
- [ ] Tests ajoutés ou mis à jour (règles : `.claude/rules/testing.md`).

## Produit (voir `AI_CONTEXT.md` pour les détails)

- [ ] Kadhia conservée comme terme métier.
- [ ] TND utilisé pour les prix.
- [ ] Français/arabe pris en compte si interface ou libellés.
- [ ] Paiement / livraison / marketplace non ajoutés sans demande explicite.

## Qualité

- [ ] Changement petit et relisible.
- [ ] Pas de dépendance inutile.
- [ ] Pas de résultat de test inventé.
- [ ] Commandes PHP/Symfony/Composer exécutées via Docker Compose ou `make` si elles sont nécessaires.
- [ ] Markdown lisible si documentation.

## Réponse finale Codex

Inclure systématiquement : résumé, fichiers modifiés, tests/vérifications, hypothèses, risques, prochaines étapes.
