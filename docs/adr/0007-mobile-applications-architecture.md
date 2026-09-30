# ADR 0007 — Architecture des applications mobiles Client et Marchand

## Statut

Proposé (MOBILE-001, #562) — à accepter avant tout bootstrap de code mobile.

## Contexte

L'ADR-0005 a acté : PWA d'abord dans le monorepo, applications natives déclenchées
seulement après preuve terrain, dans des dépôts dédiés. Les PWA client (#374) et
marchand (#375) sont livrées ; l'épic MOBILE (#561) prépare la phase native :
deux produits (Client, Marchand) × deux plateformes (Android, iOS), backend
Symfony/API Platform unique source de vérité métier, FR/AR/RTL obligatoires,
aucune duplication du métier commande/Kadhia/retrait.

## Décision

**React Native + Expo (workflow managé, prebuild autorisé) + TypeScript**, dans
**un seul dépôt mobile standalone** `click-and-collect-mobile`, avec **deux
applications distinctes** (`apps/client`, `apps/merchant`) et des **packages
partagés** :

```text
click-and-collect-mobile/
├── apps/
│   ├── client/          # App Client (Android + iOS)
│   └── merchant/        # App Marchand (Android + iOS)
├── packages/
│   ├── api-sdk/         # Client API typé, généré depuis /api/docs.json (OpenAPI)
│   ├── auth/            # Login JWT, stockage sécurisé, refresh de session
│   ├── design-system/   # Tokens (couleurs/typo alignés PlatformTheme/ShopTheme), composants de base
│   ├── i18n/            # FR/AR + RTL, clés partagées avec le frontend quand le sens est identique
│   ├── notifications/   # Abstraction push (FCM/APNs via expo-notifications)
│   ├── observability/   # Logs client, remontée d'erreurs
│   └── mobile-core/     # Utilitaires transverses (dates TND/Africa-Tunis, formats, QR)
├── docs/
└── .github/workflows/   # EAS Build/Submit + tests
```

## Options comparées

| Critère | **RN + Expo (retenu)** | RN sans Expo | Flutter | Natif Kotlin + Swift |
|---|---|---|---|---|
| Compétences équipe (React/TS via Next.js) | **Réutilisation directe** | Réutilisation directe, outillage à construire | Dart à acquérir | 2 stacks à acquérir |
| Vitesse de livraison MVP mobile | **La plus rapide** (EAS, modules prêts) | Moyenne | Moyenne | Lente (4 surfaces) |
| Coût de maintenance | 1 codebase, upgrades gérés par Expo | 1 codebase, upgrades natifs manuels | 1 codebase | 2 codebases |
| Scan QR / caméra | expo-camera, mûr | lib communautaire | mûr | natif, mûr |
| Push FCM/APNs | expo-notifications (#566) | RNFirebase | firebase_messaging | natif |
| FR / AR / RTL | I18nManager + tokens partagés | idem | bon support RTL | natif |
| Deep links / App Links / Universal Links | expo-linking + config EAS | manuel | uni_links | natif |
| Stockage sécurisé | expo-secure-store (Keychain/Keystore) | lib dédiée | lib dédiée | natif |
| Hors-ligne / cache | TanStack Query + persistance (léger au départ) | idem | idem | idem |
| Tests / E2E | Jest + RN Testing Library + Maestro | idem | flutter_test | XCTest/Espresso ×2 |
| CI/CD, signature, publication | **EAS Build/Submit gère la signature** | fastlane à construire | fastlane | fastlane ×2 |
| Dépendance fournisseur | Expo/EAS (mitigée : `expo prebuild` rend les projets natifs autonomes) | faible | Google | aucune |
| OTA (limites stores respectées) | EAS Update (JS uniquement) | CodePush (fin de vie) | non | non |
| Code natif ponctuel | config plugins / prebuild | direct | platform channels | direct |
| Pérennité stack | forte (adoption massive RN/Expo) | forte | forte | maximale |

**Options rejetées** :
- *RN sans Expo* : mêmes bénéfices de fond mais reconstruit à la main ce qu'EAS
  fournit (signature, builds, updates) — coût injustifié pour une petite équipe.
- *Flutter* : stack solide mais Dart n'apporte aucune réutilisation des
  compétences ni des tokens/design web existants.
- *Natif pur* : 2 langages × 2 apps = 4 surfaces pour reproduire des parcours
  déjà validés en PWA — contraire à l'objectif de non-duplication.

## Décisions formalisées

- **Dépôt** : un seul dépôt mobile `click-and-collect-mobile`, séparé du
  monorepo produit (conforme ADR-0005), sous la même organisation Git.
- **Deux applications séparées** Client et Marchand : identités stores, cycles
  de release et publics distincts ; le partage passe exclusivement par
  `packages/`.
- **Mutualisable** : api-sdk, auth, i18n, design tokens, notifications,
  observabilité, utilitaires (mobile-core).
- **Non mutualisable** : écrans, navigation, state par app, textes spécifiques
  au rôle — pas de package « ui-screens » commun.
- **Nommage** : packages `@kadhia/<nom>` ; apps `kadhia-client`,
  `kadhia-merchant` (hypothèse de marque à valider).
- **Identifiants** (hypothèse à valider avec les comptes stores) :
  Android `tn.kadhia.client` / `tn.kadhia.merchant`, iOS bundle IDs identiques.
- **Versionnement** : semver applicatif indépendant par app + endpoint public
  de version API minimale exigée (à cadrer en MOBILE-004/#565) ; l'app affiche
  un écran de mise à jour obligatoire sous ce seuil.
- **Environnements** : `development`, `staging`, `production` via profils EAS
  (variables par profil, jamais de secret dans le dépôt).
- **Propriété** : comptes Apple Developer et Google Play + clés de signature
  détenus par le propriétaire du produit (décision humaine à acter — pas par un
  prestataire) ; EAS ne possède pas les clés, il les héberge.
- **Dépendances** : politique « Expo SDK courant ou n-1 », upgrade par vague
  Expo, aucune lib native hors config plugin sans justification écrite.
- **Frontière** : les apps consomment exclusivement l'API publique du backend
  (`/api/docs.json` comme contrat) ; aucune écriture directe en base ; aucun
  métier dupliqué ; la PWA reste le canal web de référence et les parcours
  mobiles reprennent les parcours PWA validés.

## Conséquences

- Le monorepo produit n'accueille aucun code mobile (inchangé, ADR-0005).
- Le SDK API est généré depuis OpenAPI : toute rupture de contrat backend se
  voit à la génération — le backend reste la source de vérité.
- L'ordre de lancement ADR-0005 demeure : Android marchand → Android client →
  iOS client → iOS marchand (si besoin confirmé).
- Points à revisiter : choix Maestro vs Detox pour l'E2E ; politique OTA
  détaillée (quota, rollback) ; opportunité d'un package `qr` séparé si le
  scan diverge entre apps.

## Risques et hypothèses

- Dépendance Expo/EAS : mitigée par `expo prebuild` (sortie possible vers des
  projets natifs autonomes) ; coût EAS à budgéter.
- Marque « Kadhia » pour les stores : hypothèse — les identifiants définitifs
  dépendent des comptes créés (MOBILE-006/#567).
- Compétences : un développeur React/TS peut livrer ; la revue des parties
  natives (config plugins, signature) demande une montée en compétence ciblée.
