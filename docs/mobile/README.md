# Mobile — Cadrage des applications Client et Marchand

> Épic MOBILE #561. Phase de cadrage : **aucun code mobile dans ce dépôt**
> (ADR-0005). L'architecture cible est actée par l'ADR-0007.

## Décision d'architecture (résumé)

React Native + Expo + TypeScript · un dépôt standalone `click-and-collect-mobile`
· `apps/client` + `apps/merchant` · packages partagés (`api-sdk`, `auth`,
`design-system`, `i18n`, `notifications`, `observability`, `mobile-core`).
Détails, options rejetées et décisions : `docs/adr/0007-mobile-applications-architecture.md`.

## Schéma des dépendances

```text
apps/client ─┐                       ┌─> Backend Symfony / API Platform
             ├─> packages/* ─> api-sdk┤    (unique source de vérité métier,
apps/merchant┘                       └─>  contrat OpenAPI /api/docs.json)
```

- Une app ne dépend jamais de l'autre app.
- Un package ne dépend jamais d'une app.
- `api-sdk` est généré depuis le contrat OpenAPI ; il n'implémente aucun métier.
- Aucune écriture directe en base ; toute action sensible est validée serveur.

## Feuille de route du cadrage (ordre #561)

| Issue | Objet | Livrable principal |
|---|---|---|
| #562 MOBILE-001 | Décision architecture et dépôt | ADR-0007 (ce cadrage) |
| #563 MOBILE-002 | Périmètre et écrans Mobile Client | `docs/mobile/client-scope.md` |
| #564 MOBILE-003 | Périmètre et écrans Mobile Marchand | `docs/mobile/merchant-scope.md` |
| #565 MOBILE-004 | Audit API, auth, sessions mobiles | `docs/mobile/api-readiness.md` |
| #566 MOBILE-005 | Push natif, QR, App/Universal Links | `docs/mobile/push-and-links.md` |
| #567 MOBILE-006 | Sécurité, stores, QA, publication | `docs/mobile/release-strategy.md` |

## Invariants (rappel #561)

- Deux produits mobiles : Client et Marchand ; admin mobile hors périmètre.
- FR / AR / RTL obligatoires ; montants en TND ; vocabulaire **Kadhia** préservé.
- Les parcours mobiles reprennent les parcours PWA validés avant d'innover.
- Ordre de lancement (ADR-0005) : Android marchand → Android client → iOS
  client → iOS marchand si confirmé.
- #386–#389 restent les issues de livraison par plateforme.
