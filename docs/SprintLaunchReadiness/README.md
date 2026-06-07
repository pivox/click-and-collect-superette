# Sprint Launch Readiness — Plan d'exécution PO / Tech Lead

Point de départ : **S13-003 livré**.  
Objectif : remplacer la logique bêta par une préparation de **V1 de lancement officiel**.

## 1. Principes d'exécution

### PO

- On ne lance pas de bêta publique.
- On prépare une V1 exploitable, monétisable et supportable.
- Les anciens sprints restent visibles pour l'historique.
- Les priorités d'exécution suivent maintenant les blocs Launch Readiness.
- Les apps natives sont post-lancement.

### Tech Lead

- Les PRs restent atomiques.
- Aucun gros refactoring hors périmètre.
- Toute nouvelle route API est documentée.
- Les fonctionnalités différées doivent rester idempotentes.
- Les jobs async doivent être vérifiables sans SSH.
- Les écrans admin doivent préserver pagination, filtres et contexte.

## 2. Ordre de livraison recommandé

```text
LR-0 — Catalogue & référentiel
LR-1 — Préproduction technique & activation
LR-2 — Monétisation & facturation
LR-3 — Support & exploitation
LR-4 — Mobile Launch Readiness
LR-5 — Valeur commerciale minimale
Post-lancement — Natif + growth avancée
```

## 3. LR-0 — Catalogue & référentiel

### Issues

- #373 — S13-004 Gouvernance référentiel.
- #391 — S13-005 Images produits.
- #444 — S13-006 Traitement rapide admin.
- #445 — S13-007 Création inline marque/catégorie.
- #446 — S13-008 Actions en masse.

### Décision PO

Le référentiel produit est un actif stratégique. Il passe avant le lancement, car l'onboarding marchand dépend de la qualité catalogue.

### Décision Tech Lead

Les actions en masse ne doivent pas précéder la gouvernance. Ordre strict : gouvernance → images → traitement rapide → création inline → bulk actions.

### Sortie attendue

```text
Référentiel gouverné, illustré, contrôlable et traitable rapidement par l'admin.
```

## 4. LR-1 — Préproduction technique & activation

### Issues

- #352 — Worker async production.
- #353 — Monitoring jobs async.
- #354 — KPI pré-lancement.
- #355 — QR imprimable.
- #356 — Checklist activation supérette.
- #357 — Journal marchand minimal.
- #358 — Décision langue pré-lancement.

### Décision PO

Ces issues ne sont plus des prérequis bêta. Elles deviennent des prérequis de lancement officiel.

### Décision Tech Lead

Sans worker actif, monitoring, checklist et QR imprimable, aucune ouverture large ne doit être autorisée.

### Sortie attendue

```text
Une supérette peut être préparée, testée et activée en préproduction sans intervention technique manuelle.
```

## 5. LR-2 — Monétisation & facturation

### Issues

- #359 — Abonnement marchand.
- #360 — Statuts lifecycle / phase tarifaire.
- #361 — Reçu / facture à cadrer fiscalement.
- #362 — Paiement manuel.
- #363 — Relances paiement.
- #364 — Suspension douce / réactivation.
- #365 — Import CSV + scan code-barres.

### Décision PO

Le produit doit être monétisable dès le lancement. Paiement manuel d'abord, pas d'intégration paiement complexe.

### Décision Tech Lead

Le cadrage fiscal doit précéder toute facture officielle. Le reçu interne ou document non fiscal peut être une étape temporaire si validée.

### Sortie attendue

```text
Abonnement, relance, paiement manuel, suspension douce et réactivation opérationnels.
```

## 6. LR-3 — Support & exploitation

### Issues

- #366 — Incidents commande.
- #367 — Backoffice support.
- #368 — Journal marchand complet + santé.
- #369 — Runbook support terrain.
- #420 — Écran santé jobs async.
- #421 — Écran KPI pré-lancement.
- #422 — Détail checklist activation.

### Décision PO

On ne lance pas sans capacité support. Les cas terrain doivent être traités avec une procédure écrite et un écran admin.

### Décision Tech Lead

Les écrans support doivent s'appuyer sur les données déjà disponibles : Order, OrderStatusLog, AdminAuditLog, incidents et statuts abonnement.

### Sortie attendue

```text
Support capable de diagnostiquer incidents, santé marchand, jobs async et activation supérette.
```

## 7. LR-4 — Mobile Launch Readiness

### Issues

- #374 — PWA client.
- #375 — PWA marchand.
- #379 — Accessibilité minimum.
- #378 — WhatsApp semi-manuel.
- #402 — i18n Server Components restants.
- #403 — Dates localisées.
- #404 — aria-label notifications.
- #376 — Push notifications.
- #377 — Arabe / RTL, déjà livré si fermé.

### Décision PO

Mobile-first avant lancement, mais pas d'app native avant preuve d'usage. PWA + WhatsApp sont les priorités.

### Décision Tech Lead

Le push Web ne doit pas bloquer tout le lancement si PWA, notifications in-app et WhatsApp sont stables.

### Sortie attendue

```text
Client et marchand peuvent installer et utiliser l'application mobile web en conditions terrain.
```

## 8. LR-5 — Valeur commerciale minimale

### Avant lancement

- #380 — Statistiques marchand simples.
- #384 — Promotions simples.
- #385 — CRM léger.

### Après lancement

- #382 — Packs produits.
- #383 — Suggestions de Kadhia.

### Décision PO

Les statistiques avancées et suggestions ont besoin de données réelles. Avant lancement, livrer une version simple et utile.

### Décision Tech Lead

Éviter les algorithmes ou agrégations complexes sans historique fiable. Commencer par des requêtes simples, testables et paginées.

## 9. Post-lancement

### Issues repoussées

- #386 — Android marchand.
- #387 — Android client.
- #388 — iOS client.
- #389 — iOS marchand.
- #382 — Packs produits avancés.
- #383 — Suggestions avancées.

### Gate de déclenchement

```text
Usage réel + limites PWA + besoin marchand/client confirmé + monétisation opérationnelle.
```

## 10. Go / No-Go lancement officiel

Le lancement officiel est autorisé uniquement si :

- commande client nominale OK ;
- acceptation/refus/préparation marchand OK ;
- retrait QR/code OK ;
- worker async actif ;
- checklist activation complète ;
- support incidents opérationnel ;
- abonnement + paiement manuel + relance OK ;
- PWA client/marchand installables ;
- aucune anomalie bloquante sur logs/healthcheck.
