# Checklist Codex

## Avant modification

- [ ] `AGENTS.md` lu.
- [ ] `AI_CONTEXT.md` lu.
- [ ] Documentation métier pertinente lue.
- [ ] Périmètre MVP vérifié.
- [ ] Hypothèses listées.

## Code Symfony/API Platform

- [ ] Entités nommées clairement.
- [ ] Migrations Doctrine présentes si schéma modifié.
- [ ] DTO utilisés quand l'entrée API ne correspond pas directement à l'entité.
- [ ] Groupes de sérialisation explicites.
- [ ] Sécurité client/marchand/admin vérifiée.
- [ ] Logique métier hors contrôleurs.
- [ ] Tests ajoutés ou mis à jour.

## Produit

- [ ] Kadhia conservée comme terme métier.
- [ ] TND utilisé pour les prix.
- [ ] Français/arabe pris en compte si interface ou libellés.
- [ ] Paiement non ajouté sans demande.
- [ ] Livraison non ajoutée sans demande.
- [ ] Marketplace multi-marchands non ajoutée sans demande.

## Qualité

- [ ] Changement petit et relisible.
- [ ] Pas de dépendance inutile.
- [ ] Pas de résultat de test inventé.
- [ ] Markdown lisible si documentation.
- [ ] Liens internes cohérents.

## Réponse finale Codex

Inclure systématiquement :

- résumé ;
- fichiers modifiés ;
- tests/vérifications ;
- hypothèses ;
- risques ;
- prochaines étapes.
