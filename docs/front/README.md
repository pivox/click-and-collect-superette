# Front tradingV3 — Console d'exploitation React

## Statut réel du front

Le dépôt contient déjà un front React dans `frontend/`.

Cette documentation ne part donc pas du principe qu'il faut créer un nouveau front Symfony/Twig. Le chantier consiste à **auditer, consolider et compléter le front React existant** pour en faire une vraie console d'exploitation tradingV3.

## Source de vérité

Le README racine peut être obsolète. Pour le front, la source de vérité doit être le code réel :

```text
frontend/package.json
frontend/src/App.js
frontend/src/services/api.js
frontend/src/config.js
frontend/src/pages/*
```

## Stack front observée

```text
React 18
React Router DOM 6
Axios
ApexCharts / React ApexCharts
Recharts
React Scripts
```

## Objectif produit

Transformer le front existant en console d'exploitation permettant de répondre rapidement à ces questions :

```text
1. Est-ce que le bot tourne correctement ?
2. Quels profils / exchanges / runtimes sont actifs ?
3. Pourquoi un signal MTF est accepté ou refusé ?
4. Quelle position est actuellement en risque ?
5. Y a-t-il un ordre bloqué, inconnu ou dupliqué ?
6. Quels symboles ou profils font perdre de l'argent ?
7. Quel workflow Temporal est bloqué ou en erreur ?
```

## Principe clé

Ne pas créer un deuxième front.

```text
Mauvaise direction : créer un front Twig séparé.
Bonne direction    : consolider le front React existant.
```

## Écrans existants à auditer

Le routing actuel expose déjà plusieurs pages :

```text
Dashboard
Dashboard MTF
Recherche globale
Graphiques
Contrats
Positions
Pipeline
Signaux
Klines
Bougies manquantes
États MTF
Audits MTF
Switches MTF
Snapshots indicateurs
Cache validation
Contrats blacklistés
Verrous MTF
Configurations
Comptes Exchange
Santé & Monitoring
Historique Runtime
```

Ces écrans ne doivent pas être recréés de zéro sans audit. Il faut d'abord décider pour chacun :

```text
keep
fix
merge
remove
replace
```

## Écrans / modules à ajouter ou renforcer

```text
Workflows Overview
Workflow Detail
Workflow Actions read-only d'abord
Risk Center consolidé
Order Detail
Signal Detail
Trade Detail
Entry Zones / Rejections
Performance par profil / symbole
Runtime Control exchange/profile/market_type
```

## Priorité produit

```text
P0 — Audit et consolidation du front existant
P1 — Risk Center, détails signal/ordre/position, workflows read-only
P2 — Performance, EntryZones, backtests, actions manuelles encadrées
```

## Règles critiques

```text
1. Ne jamais masquer le mode LIVE.
2. Ne jamais permettre une action dangereuse sans confirmation textuelle.
3. Ne jamais relancer un workflow lié à un ordre sans safety-check.
4. Ne jamais libérer un lock si une position ou un ordre actif existe.
5. Ne jamais créer une deuxième stack front concurrente.
6. Ne jamais se baser uniquement sur le README racine si le code dit autre chose.
```

## Documents associés

```text
docs/front/AUDIT_EXISTING_FRONT.md
docs/front/ROADMAP_REACT_FRONT.md
docs/front/API_CONTRACTS_FRONT.md
docs/front/SCREENS_SPEC.md
docs/front/WORKFLOWS_SPEC.md
docs/front/ISSUES_FRONT_PLAN.md
docs/front/CODEX_PROMPTS.md
```
