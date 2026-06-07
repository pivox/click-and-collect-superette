# Réorganisation stratégique — depuis Sprint 13

Date de cadrage : 2026-06-07  
Rôles de cadrage : PO + Tech Lead  
Point de départ technique : **dernier item livré = S13-003 — Score de qualité des références produit**.

## 1. Décision

Cette réorganisation ne crée pas un nouveau sprint.

Le document sprint de référence est :

```text
docs/Sprint13/README.md
```

La roadmap ne vise plus une bêta publique rapide. À partir de S13-003, les sprints restants préparent une **V1 de lancement officiel**.

Décision :

```text
Ne pas lancer de bêta publique.
Préparer une V1 complète, monétisable, exploitable, mobile-first et supportable.
```

## 2. Règle de gouvernance documentaire

```text
Les anciens numéros de sprint restent conservés.
Le Sprint 13 porte la réorganisation stratégique.
Aucun dossier SprintLaunchReadiness séparé ne doit être utilisé.
Les issues gardent leur titre historique, mais leur priorité suit la logique de lancement officiel.
```

## 3. Lecture PO

Le PO valide :

- ne pas exposer clients et marchands à une bêta fragile ;
- terminer les capacités d'exploitation avant ouverture ;
- rendre le produit facturable avant lancement ;
- sécuriser le référentiel produit avant acquisition marchand large ;
- garder le natif après lancement ;
- limiter la growth avant lancement aux modules simples et utiles.

## 4. Lecture Tech Lead

Le Tech Lead valide :

- aucune nouvelle fonctionnalité ne doit contourner les règles de commande/retrait ;
- workers async, logs, healthchecks et runbooks sont des prérequis de lancement ;
- PWA client/marchand réutilisent `apps/frontend/` et l'API existante ;
- le natif reste post-lancement tant que les limites PWA ne sont pas prouvées ;
- les modules IA et référentiel doivent être gouvernés avant actions en masse ;
- toute nouvelle route bulk ou push doit être documentée dans le contrat API.

## 5. Nouvelle lecture des sprints restants

### Sprint 13 — Catalogue & référentiel, pivot de réorganisation

Point de départ : S13-003 livré.

Restant :

- #373 — S13-004 Gouvernance du référentiel.
- #391 — S13-005 Images produits web/mobile.
- #444 — S13-006 Traitement rapide admin.
- #445 — S13-007 Création inline marques/catégories.
- #446 — S13-008 Actions en masse.

Ordre :

```text
S13-004 → S13-005 → S13-006 → S13-007 → S13-008
```

Critère de sortie :

```text
Référentiel gouverné, illustré, contrôlable et traitable rapidement par l'admin.
```

### Sprint 10 — Préproduction technique & activation

Ancienne intention : bêta terrain.  
Nouvelle intention : prérequis de lancement officiel.

Issues : #352, #353, #354, #355, #356, #357, #358.

Critère de sortie :

```text
Worker async, monitoring, KPI, QR imprimable, checklist activation et journal minimal prêts.
```

### Sprint 11 — Monétisation & facturation avant lancement

Issues : #359, #360, #361, #362, #363, #364, #365.

Critère de sortie :

```text
Abonnement, phase tarifaire, paiement manuel, relance, suspension douce et réactivation opérationnels.
```

Décision Tech Lead : le cadrage fiscal doit précéder toute facture conforme.

### Sprint 12 — Support & exploitation avant lancement

Issues : #366, #367, #368, #369, #420, #421, #422.

Critère de sortie :

```text
Support capable de diagnostiquer incidents, santé marchand, jobs async et activation supérette depuis l'admin.
```

### Sprint 14 — Mobile Launch Readiness

Issues : #374, #375, #376, #377, #378, #379, #402, #403, #404.

Priorité :

```text
1. PWA client
2. PWA marchand
3. Accessibilité minimum
4. WhatsApp semi-manuel
5. Finitions i18n/accessibilité
6. Push notifications
```

Décision Tech Lead : le push ne bloque pas tout le lancement si PWA + notifications in-app + WhatsApp sont stables.

### Sprint 15 — Valeur commerciale minimale avant lancement

Avant lancement :

- #380 — S15-001 Statistiques marchand simples.
- #384 — S15-004 Promotions simples.
- #385 — S15-005 CRM léger marchand.

Après lancement :

- #382 — S15-002 Packs produits.
- #383 — S15-003 Suggestions de Kadhia.

### Sprint 16 — Post-lancement uniquement

Issues : #386, #387, #388, #389.

Critère de déclenchement :

```text
Usage réel prouvé, limites PWA constatées, facturation opérationnelle, support stable, besoin terrain confirmé.
```

## 6. Gates CTO avant lancement officiel

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
