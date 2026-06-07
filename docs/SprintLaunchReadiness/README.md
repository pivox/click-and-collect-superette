# SprintLaunchReadiness — Réorganisation des sprints restants

## Décision

On ne prépare plus une bêta publique.

On prépare une **V1 Launch Readiness** : produit complet, préproduction contrôlée, puis lancement officiel.

Dernière issue considérée livrée : **S13-003**.

Cette documentation devient le point d'entrée pour les sprints restants.

## Rôles de décision

### PO

Le produit doit être crédible au lancement : catalogue propre, mobile installable, abonnement prêt, support disponible.

### Tech Lead

La V1 doit rester livrable. Les modules qui nécessitent des données réelles ou des retours terrain sont repoussés après lancement.

## Nouvelle lecture des sprints

| Phase | Source | Décision |
|---|---|---|
| LR-1 | Sprint 13 restant | Catalogue & référentiel avant lancement |
| LR-2 | Sprints 10/11/12 | Préproduction, monétisation et support |
| LR-3 | Sprint 14 + S14-post | Mobile launch readiness |
| LR-4 | Sprint 15 | Growth léger avant lancement, avancé après lancement |
| LR-5 | Sprint 16 | Natif post-lancement uniquement |

## Ordre recommandé

```text
1. LR-1 — S13 restant : référentiel, images, UX admin catalogue
2. LR-2 — S10/S11/S12 : ops, abonnement, support
3. LR-3 — S14 : PWA, accessibilité, WhatsApp, i18n, push non bloquant
4. LR-4 — S15 light : stats simples, promotions simples, CRM léger
5. Lancement officiel
6. LR-5 — S16 natif seulement après preuve d'usage
```

## Références

- `docs/roadmap/v1-launch-readiness-roadmap.md`
- `docs/roadmap/v1-launch-readiness-issue-map.md`

## Règles de travail

- Garder les numéros d'issues existants.
- Ne pas renuméroter S13/S14/S15/S16.
- Utiliser LR-1 à LR-5 comme lecture stratégique.
- Ajouter la décision Launch Readiness dans chaque issue restante.
- Ne pas laisser S15 avancé ou S16 bloquer le lancement.
- Le push Web est utile, mais non bloquant si PWA + in-app + WhatsApp sont prêts.
- Le natif ne démarre qu'après lancement et preuve d'usage.

## Gate final avant lancement

La V1 peut être lancée quand :

- le référentiel produit est gouverné ;
- les images/fallback catalogue sont propres ;
- les supérettes sont activables par checklist ;
- le paiement manuel et la suspension douce sont prêts ;
- les incidents et le support sont traçables ;
- la PWA client et marchand sont installables ;
- les parcours commande et retrait sont testés ;
- le lancement ne dépend pas des apps natives.
