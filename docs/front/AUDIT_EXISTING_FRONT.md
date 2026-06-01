# Audit du front React existant — tradingV3

## Objectif

Faire l'état réel du front actuel avant tout développement.

Le README racine peut être obsolète. L'audit doit donc partir du code réel :

```text
frontend/package.json
frontend/src/App.js
frontend/src/services/api.js
frontend/src/config.js
frontend/src/pages/*
frontend/src/components/*
frontend/src/styles/*
```

## Constat initial

Le projet contient déjà un front React.

Stack observée :

```text
React 18
React Router DOM 6
Axios
ApexCharts
React ApexCharts
Recharts
React Scripts
```

Il ne faut donc pas créer un nouveau front Twig ou un deuxième front séparé.

## Écrans déclarés dans le router

À auditer un par un :

| Route | Page | Décision à prendre |
| --- | --- | --- |
| `/` | DashboardPage | keep / fix / replace |
| `/mtf-dashboard` | MtfDashboardPage | keep / fix / merge |
| `/search` | GlobalSearchPage | keep / fix |
| `/graph` | ChartsPage | keep / fix |
| `/contracts` | ContractPage | keep / fix |
| `/positions` | PositionsPage | keep / fix |
| `/pipeline` | PipelinePage | keep / fix / merge |
| `/signals` | SignalsPage | keep / fix |
| `/klines` | KlinesPage | keep / fix |
| `/missing-klines` | MissingKlinesPage | keep / fix |
| `/mtf-state` | MtfStatePage | keep / fix |
| `/mtf-audit` | MtfAuditPage | keep / fix |
| `/mtf-switch` | MtfSwitchPage | keep / fix |
| `/indicator-snapshots` | IndicatorSnapshotPage | keep / fix |
| `/validation-cache` | ValidationCachePage | keep / fix |
| `/blacklisted-contracts` | BlacklistedContractPage | keep / fix |
| `/mtf-locks` | MtfLockPage | keep / fix |
| `/trading-configurations` | TradingConfigurationsPage | keep / fix |
| `/exchange-accounts` | ExchangeAccountsPage | keep / fix |
| `/health` | HealthMonitoringPage | keep / fix |
| `/runtime-history` | RuntimeHistoryPage | keep / fix |

## Statut à produire pour chaque écran

Chaque écran doit être classé selon cette grille :

```text
KEEP      : écran utile, connecté, à conserver.
FIX       : écran utile mais incomplet ou cassé.
MERGE     : écran redondant à fusionner avec un autre.
REMOVE    : écran obsolète ou inutile.
REPLACE   : écran à remplacer par un écran métier plus clair.
UNKNOWN   : statut non déterminé.
```

## Checklist d'audit par page

Pour chaque page dans `frontend/src/pages`, vérifier :

```text
- Route déclarée dans App.js ?
- Page encore utilisée ?
- Appels API présents ?
- Endpoint backend réellement existant ?
- Gestion loading / error / empty state ?
- Filtres utiles ?
- Données critiques visibles ?
- Page orientée métier ou seulement technique ?
- Risque de doublon avec une autre page ?
```

## Checklist d'audit API front

À partir de `frontend/src/services/api.js`, produire un tableau :

| Fonction front | Endpoint | Page consommatrice | Backend confirmé ? | Statut |
| --- | --- | --- | --- | --- |
| `getPositions` | `/api/positions` | PositionsPage | à vérifier | UNKNOWN |
| `getSignals` | `/api/signals` | SignalsPage | à vérifier | UNKNOWN |
| `getRuntimeHistory` | `/api/runtime-history` | RuntimeHistoryPage | à vérifier | UNKNOWN |
| `getMtfLocks` | `/api/mtf-locks` | MtfLockPage | à vérifier | UNKNOWN |

## Livrable attendu de l'audit

Créer ou mettre à jour un tableau dans ce fichier avec :

```text
Page
Route
API utilisée
Statut UX
Statut API
Décision produit
Priorité
Commentaire
```

## Première conclusion produit

La première PR front ne doit pas développer un nouvel écran.

Elle doit produire :

```text
1. L'audit réel des pages existantes.
2. La cartographie des endpoints utilisés.
3. La liste des écrans à garder / corriger / fusionner.
4. La liste des écrans manquants.
5. La roadmap recalée sur le front React existant.
```

## Écrans probablement à ajouter après audit

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

## Règle de sécurité

Tant que l'audit n'est pas terminé :

```text
- ne pas supprimer de page ;
- ne pas renommer les routes publiques ;
- ne pas créer un second front ;
- ne pas activer d'action live sans confirmation ;
- ne pas ajouter d'authentification pour ce chantier.
```
