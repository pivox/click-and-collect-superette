# Checklist Codex

## Avant modification

- [ ] `AGENTS.md` lu.
- [ ] `AI_CONTEXT.md` lu (MVP scope, vocabulaire, statuts de commande, entités).
- [ ] Documentation métier pertinente dans `docs/product/` lue.
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
- [ ] Markdown lisible si documentation.

## Réponse finale Codex

Inclure systématiquement : résumé, fichiers modifiés, tests/vérifications, hypothèses, risques, prochaines étapes.
