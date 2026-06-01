# Roadmap React Front — tradingV3

## Contexte

Le front tradingV3 existe déjà dans `frontend/` et utilise React. La roadmap doit donc consolider l'existant, pas créer un deuxième front.

## Objectif final

Transformer le front React en console d'exploitation tradingV3 :

```text
- supervision live ;
- analyse des signaux MTF ;
- suivi positions / ordres ;
- contrôle du risque ;
- visualisation workflows Temporal ;
- analyse performances ;
- tuning EntryZone / rejets.
```

## Priorités produit

### P0 — Audit et sécurité

Objectif : savoir ce qui existe vraiment et éviter les risques critiques.

```text
FRONT-00 — Audit du front React existant
FRONT-01 — Nettoyage navigation / layout / contexte global
FRONT-02 — Dashboard opérationnel trading
FRONT-03 — Positions / Orders / Risk Center
```

### P1 — Explicabilité et workflows

Objectif : comprendre les décisions du bot et les runs Temporal.

```text
FRONT-04 — Signals MTF + Signal Detail
FRONT-05 — MTF States / Audits / Locks consolidés
FRONT-06 — Workflows Overview read-only
FRONT-07 — Workflow Detail read-only
FRONT-08 — Runtime History amélioré
```

### P2 — Analyse et tuning

Objectif : améliorer la qualité des setups avant d'augmenter la fréquence.

```text
FRONT-09  — Entry Zones / Rejections
FRONT-10  — Trades & Performance
FRONT-11  — Trade Detail
FRONT-12  — Performance par profil / symbole
FRONT-13  — Backtests / Forward Tests
FRONT-14  — Rate Limiter Monitor
FRONT-15  — Manual Intervention très encadrée
```

## Ordre de développement recommandé

### PR 1 — Audit existant

```text
But : documenter l'état réel du front React.
Fichiers : docs/front/AUDIT_EXISTING_FRONT.md
Pas de changement fonctionnel.
```

Critères :

```text
- routes listées ;
- pages listées ;
- endpoints listés ;
- statut keep/fix/merge/remove/replace ;
- roadmap ajustée.
```

### PR 2 — Layout et contexte global

```text
But : clarifier la navigation et afficher le contexte global.
```

À ajouter :

```text
- bandeau global ENV / exchange / market_type / profile / mode ;
- mode LIVE très visible ;
- navigation groupée par domaine métier ;
- état loading/error/empty homogène.
```

### PR 3 — Dashboard opérationnel

```text
But : voir l'état du bot en moins de 10 secondes.
```

Contenu :

```text
- bot status ;
- mode global ;
- positions ouvertes ;
- ordres ouverts ;
- alertes critiques ;
- PnL journalier ;
- daily loss cap ;
- statuts exchange ;
- statuts runtime.
```

### PR 4 — Risk Center consolidé

```text
But : centraliser les risques avant toute action live.
```

Contenu :

```text
- positions sans SL ;
- ordre unknown ;
- lock stale ;
- daily loss cap ;
- désynchronisation exchange ;
- boutons pause avec confirmation.
```

### PR 5 — Signals MTF + Signal Detail

```text
But : expliquer les décisions du bot.
```

Contenu :

```text
- signal accepted/rejected ;
- timeframe bloquant ;
- indicateurs ;
- reasons ;
- lien vers ordre / position / logs.
```

### PR 6 — Workflows read-only

```text
But : visualiser Temporal sans action dangereuse.
```

Contenu :

```text
- liste workflows ;
- status ;
- schedule id ;
- workflow id ;
- profile / exchange / market_type ;
- lien Temporal UI ;
- dernier résultat ;
- erreurs / retries.
```

### PR 7 — Workflow Detail read-only

```text
But : comprendre un run précis.
```

Contenu :

```text
- timeline ;
- activités ;
- payload masqué ;
- réponse formatter ;
- full response si disponible ;
- erreurs et attempts.
```

### PR 8 — Entry Zones / Rejections

```text
But : analyser pourquoi le bot ne rentre pas en position.
```

Contenu :

```text
- skipped_out_of_zone ;
- zone_dev_pct ;
- zone_max_dev_pct ;
- candidate price ;
- entry zone min/max ;
- distribution par seuil.
```

### PR 9 — Trades & Performance

```text
But : trouver les profils et symboles perdants.
```

Contenu :

```text
- winrate ;
- PnL USDT ;
- PnL R ;
- profit factor ;
- expectancy ;
- MFE / MAE ;
- performance par profil ;
- performance par symbole.
```

## Règles de sécurité pendant la roadmap

```text
- Pas d'auth dans ce chantier.
- Pas de second front.
- Pas d'ouverture manuelle de trade au MVP.
- Pas de relance workflow d'ordre sans safety-check.
- Pas de release lock si position ou ordre actif.
- Pas de bouton LIVE sans confirmation textuelle.
```

## Définition de Done globale

Chaque PR doit respecter :

```text
- PR atomique ;
- build front OK ;
- pas de dette volontaire non documentée ;
- pas de régression navigation ;
- erreurs API affichées proprement ;
- état vide utile ;
- documentation mise à jour si comportement nouveau.
```
