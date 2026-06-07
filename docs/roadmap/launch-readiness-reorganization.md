# Réorganisation stratégique — Launch Readiness

Date de cadrage : 2026-06-07  
Rôles de cadrage : PO + Tech Lead  
Point de départ technique : **dernier item livré = S13-003 — Score de qualité des références produit**.

## 1. Décision stratégique

La roadmap ne vise plus une bêta publique rapide.

Nouvelle décision :

```text
Ne pas lancer de bêta publique.
Préparer une V1 de lancement complète, monétisable, exploitable, mobile-first et supportable.
```

Le mot **bêta** doit être remplacé dans les sprints restants par :

```text
Launch Readiness
Pré-lancement contrôlé
Préproduction terrain
Go / No-Go lancement officiel
```

## 2. Lecture PO

Le PO valide la réorganisation suivante :

- ne pas exposer les clients et marchands à une bêta fragile ;
- terminer les capacités d'exploitation avant ouverture ;
- rendre le produit facturable avant lancement ;
- sécuriser le référentiel produit avant acquisition marchand large ;
- garder le natif après lancement, sauf preuve forte contraire ;
- ne faire qu'une version légère des modules growth avant lancement.

## 3. Lecture Tech Lead

Le Tech Lead valide les principes suivants :

- aucune nouvelle grosse fonctionnalité ne doit contourner les règles de commande/retrait existantes ;
- les workers async, logs, healthchecks et runbooks sont des prérequis de lancement ;
- les PWA doivent réutiliser `apps/frontend/` et l'API existante ;
- le natif doit rester post-lancement tant que les limites PWA ne sont pas prouvées ;
- les modules IA et référentiel doivent être gouvernés avant actions en masse ;
- toute nouvelle route bulk ou push doit être documentée dans le contrat API.

## 4. Nouveau découpage cible

### LR-0 — Catalogue & référentiel avant lancement

Ancien périmètre : Sprint 13 restant après S13-003.

Objectif : sécuriser l'actif produit avant d'élargir le parc marchand.

Issues :

- #373 — S13-004 Gouvernance du référentiel.
- #391 — S13-005 Gestion optimisée des images produits web/mobile.
- #444 — S13-006 UX de traitement rapide du référentiel admin.
- #445 — S13-007 Création inline marques/catégories.
- #446 — S13-008 Actions en masse et file de priorisation.

Ordre recommandé :

```text
1. S13-004 gouvernance
2. S13-005 images produits
3. S13-006 traitement rapide admin
4. S13-007 création inline marque/catégorie
5. S13-008 actions en masse
```

Critère de sortie :

```text
Le référentiel est gouverné, illustré, contrôlable et traitable rapidement par l'admin.
```

### LR-1 — Préproduction technique & activation supérette

Ancien périmètre : Sprint 10, sans notion de bêta publique.

Objectif : préparer l'exploitation technique et l'activation de supérettes avant lancement.

Issues :

- #352 — S10-001 Worker async production.
- #353 — S10-002 Monitoring jobs async.
- #354 — S10-003 Métriques de pré-lancement / KPI terrain.
- #355 — S10-004 QR magasin imprimable.
- #356 — S10-005 Checklist d'activation supérette.
- #357 — S10-006 Journal opérationnel marchand minimal.
- #358 — S10-007 Décision FR-only vs FR+AR, à relire comme décision pré-lancement.

Critère de sortie :

```text
Une supérette peut être activée en préproduction avec QR, checklist complète, workers actifs, logs et indicateurs minimum.
```

### LR-2 — Monétisation & facturation avant lancement

Ancien périmètre : Sprint 11.

Objectif : rendre le produit monétisable dès l'ouverture officielle.

Issues :

- #359 — S11-001 Module abonnement marchand.
- #360 — S11-002 Statuts abonnement : lifecycle + phase tarifaire.
- #361 — S11-003 Reçu / facture mensuelle à cadrer fiscalement.
- #362 — S11-004 Paiement manuel.
- #363 — S11-005 Relances paiement email + WhatsApp manuel.
- #364 — S11-006 Suspension douce et réactivation.
- #365 — S11-007 Import CSV + scan code-barres, conservé comme prérequis conversion marchand.

Critère de sortie :

```text
Un marchand peut entrer dans une phase tarifaire, être facturé ou suivi manuellement, payer hors ligne, être relancé, suspendu doucement et réactivé.
```

Décision Tech Lead : la facture fiscale conforme ne doit pas être codée sans cadrage fiscal validé.

### LR-3 — Support & exploitation contrôlée

Ancien périmètre : Sprint 12.

Objectif : donner à l'équipe les outils pour traiter les incidents et comprendre la santé opérationnelle avant lancement.

Issues :

- #366 — S12-001 Incidents commande.
- #367 — S12-002 Backoffice support.
- #368 — S12-003 Journal opérationnel complet + vue santé.
- #369 — S12-004 Runbook support terrain.
- #420 — S12-005 Écran santé plateforme / jobs async.
- #421 — S12-006 Écran métriques pré-lancement.
- #422 — S12-007 Détail checklist activation supérette.

Critère de sortie :

```text
Le support sait diagnostiquer commande, marchand, activation supérette, incidents et santé technique sans requête manuelle en base.
```

### LR-4 — Mobile Launch Readiness

Ancien périmètre : Sprint 14.

Objectif : livrer une expérience mobile installable avant lancement officiel.

Issues :

- #374 — S14-001 PWA client.
- #375 — S14-002 PWA marchand.
- #379 — S14-006 Accessibilité minimum.
- #378 — S14-005 WhatsApp semi-manuel.
- #402 — S14-post Server Components i18n restants.
- #403 — S14-post localisation des dates.
- #404 — S14-post aria-label notifications.
- #376 — S14-003 Push notifications.
- #377 — S14-004 Arabe / RTL, considéré livré si fermé.

Ordre recommandé :

```text
1. PWA client
2. PWA marchand
3. Accessibilité minimum
4. WhatsApp semi-manuel
5. Corrections i18n/accessibilité post-S14
6. Push notifications
```

Critère de sortie :

```text
Client et marchand peuvent utiliser l'application comme une app mobile installable, avec parcours commande/retrait utilisable et communication WhatsApp fallback.
```

Le push est utile, mais ne doit pas retarder tout le lancement si PWA + notifications in-app + WhatsApp sont stables.

### LR-5 — Valeur commerciale minimale avant lancement

Ancien périmètre : Sprint 15, réduit.

Objectif : lancer avec assez de valeur business sans construire des modules avancés sans données réelles.

À faire avant lancement :

- #380 — S15-001 Statistiques marchand, en version simple.
- #384 — S15-004 Promotions simples.
- #385 — S15-005 CRM léger marchand.

À repousser après lancement :

- #382 — S15-002 Packs produits.
- #383 — S15-003 Suggestions de Kadhia.

Critère de sortie :

```text
Le marchand voit une valeur minimale de pilotage et l'équipe peut suivre la relation commerciale.
```

### Post-lancement — Natif et growth avancée

Ancien périmètre : Sprint 16 + S15 avancé.

À repousser :

- #386 — S16-001 App native Android marchand.
- #387 — S16-002 App native Android client.
- #388 — S16-003 App native iOS client.
- #389 — S16-004 App native iOS marchand.
- #382 — Packs produits avancés.
- #383 — Suggestions intelligentes.

Critère de déclenchement :

```text
Usage réel prouvé, limites PWA constatées, facturation opérationnelle, support stable, besoin terrain confirmé.
```

## 5. Gates CTO avant lancement officiel

### Gate technique

- Worker async actif et supervisé.
- Monitoring jobs disponible.
- Healthcheck OK.
- Logs exploitables.
- Aucun message critique bloqué.

### Gate marchand

- Checklist activation complète.
- QR imprimable.
- Horaires et créneaux configurés.
- Catalogue minimum prêt.
- Commande test passée.
- Retrait test validé.

### Gate client

- PWA client installable.
- Catalogue mobile utilisable.
- Kadhia fluide.
- Suivi commande OK.
- Retrait QR/code OK.
- FR/AR propre sur les écrans visibles.

### Gate business

- Abonnement marchand créé.
- Phase tarifaire claire.
- Paiement manuel enregistrable.
- Relance possible.
- Suspension douce possible.
- Réactivation possible.

### Gate support

- Incident commande traçable.
- Journal marchand consultable.
- Runbook support disponible.
- Vue santé marchand disponible.

### Gate Go / No-Go

- Aucun bug bloquant sur commande.
- Aucun bug bloquant sur retrait.
- Aucun bug bloquant sur paiement marchand.
- Aucun bug bloquant sur activation supérette.
- Rollback, logs et procédures support prêts.

## 6. Règle de gouvernance roadmap

À partir de cette réorganisation :

```text
Les anciens numéros de sprint restent dans les titres d'issues pour l'historique.
La priorité d'exécution suit les phases LR-0 à LR-5.
Les apps natives et les modules growth avancés sont post-lancement.
```
