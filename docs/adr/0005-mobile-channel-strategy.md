# ADR 0005 — Stratégie PWA et applications natives

## Statut

Accepté.

## Contexte

Le MVP est déjà structuré autour d'un monorepo produit avec `apps/frontend/` et `apps/backend/`.

Les parcours client, marchand et admin partagent les mêmes contrats API, le même vocabulaire métier (**Kadhia**, supérette, marchand, rendez-vous, retrait) et les mêmes exigences de localisation français / arabe. Séparer trop tôt les canaux mobiles créerait plusieurs surfaces à maintenir avant d'avoir validé les usages terrain.

En parallèle, le produit pourra avoir besoin d'un groupe ou d'une organisation Git pour organiser plusieurs dépôts lorsque l'exploitation grandira : repo produit principal, infrastructure, assets, puis applications natives si elles sont déclenchées.

## Décision

Le repo `click-and-collect-superette` reste le monorepo principal du produit.

La PWA client et la PWA marchand se livrent dans `apps/frontend/`, comme évolution du frontend web responsive existant. Elles réutilisent l'API backend existante et ne créent pas de nouveau dépôt.

Les applications natives iOS et Android ne sont pas créées dans le MVP. Si elles deviennent nécessaires après preuve terrain, elles seront cadrées comme des applications séparées, idéalement dans des repos dédiés sous le groupe ou l'organisation Git du produit.

## Règles de déclenchement

Une application native ne démarre qu'après validation de ces conditions :

- usage terrain confirmé côté clients et marchands ;
- limites PWA documentées sur les parcours réels ;
- API backend suffisamment stable ;
- capacité de maintenance mobile identifiée ;
- décision technique explicite entre natif pur et cross-platform.

L'ordre de lancement recommandé reste : Android marchand, Android client, iOS client, puis iOS marchand seulement si le besoin marchand iOS est confirmé.

## Conséquences

- Aucun dossier `mobile/`, `ios/` ou `android/` n'est ajouté dans le repo MVP.
- Les issues PWA restent rattachées au repo actuel et à `apps/frontend/`.
- Les issues natives restent post-MVP et doivent reprendre les parcours validés par la PWA sans réinventer le produit.
- Les apps natives réutilisent l'API backend existante ; tout nouveau contrat API doit être justifié par un besoin mobile réel.
- Une organisation Git peut être créée sans changer la structure MVP : elle sert d'abord à ranger les dépôts, pas à forcer un découpage prématuré.

## Structure cible

Pendant le MVP et la bêta :

```text
click-and-collect-superette/
├── apps/
│   ├── frontend/          # Web responsive + PWA client/marchand/admin
│   └── backend/           # API, métier, persistance
├── docs/
└── README.md
```

Après preuve terrain, si les apps natives sont déclenchées :

```text
superette-click-collect/
├── click-and-collect-superette     # monorepo produit principal
├── infra-deploy                    # optionnel
├── brand-assets                    # optionnel
├── native-android-merchant         # optionnel, après décision
├── native-android-client           # optionnel, après décision
├── native-ios-client               # optionnel, après décision
└── native-ios-merchant             # optionnel, conditionnel
```
