# ADR — Écosystème standalone et plateforme centrale dockerisée

## Statut

Accepté — 2026-06-09.

Cet ADR prolonge l'ADR `0005-mobile-channel-strategy.md` (stratégie PWA / natif) et ne le contredit pas.

## Contexte

Le repo `click-and-collect-superette` est aujourd'hui une **plateforme centrale dockerisée** : monorepo
`apps/backend` (Symfony 7 / API Platform 4) + `apps/frontend` (Next.js 14), orchestré par
`docker-compose.yml` (PostgreSQL, Redis, backend PHP-FPM, worker Messenger, Nginx, frontend, Mailpit,
scraper outillé).

Le produit grandit et plusieurs composants nouveaux se profilent : un agent IA, une orchestration
d'infrastructure staging/prod, puis des applications mobiles natives. Les ajouter dans le monorepo
actuel ferait grossir le repo, mélangerait des cycles de vie différents et risquerait de casser
l'orchestration Docker existante.

Par ailleurs, le backend contient déjà une **brique IA interne** d'extraction catalogue-photo. Il faut
éviter toute confusion : le futur agent IA est un **projet distinct**, pas une migration de cette brique.

## Décision

1. **La plateforme centrale reste le monorepo dockerisé et la source de vérité métier.** Elle porte
   l'API, la logique métier, la persistance et le frontend. On ne la transforme pas en monorepo plus
   gros et on ne déplace pas l'existant.

2. **Les nouveaux composants sont des repos standalones** qui gravitent autour de la plateforme :
   - `click-and-collect-ai-agent` — agent IA standalone ;
   - `click-and-collect-infra` — orchestration staging / production ;
   - `click-and-collect-mobile-android-merchant`, `-android-client`, `-ios-client`, `-ios-merchant` —
     apps natives.

3. **Tout satellite consomme l'API**, ne duplique aucune logique métier et **n'écrit jamais directement
   dans la base principale**. L'API reste responsable du commit métier.

4. **Le nouvel agent IA est un repo réservé distinct**, dont le périmètre exact sera cadré dans une issue
   dédiée. Il produit du **JSON structuré + score de confiance** pour le matching produit. Il **ne migre
   pas** la brique IA catalogue-photo déjà présente dans le backend, qui **reste interne** à la plateforme.

5. **La stratégie mobile reste celle de l'ADR-0005** : PWA d'abord dans `apps/frontend/`, apps natives
   post-lancement après gate terrain, Android marchand prioritaire, iOS marchand conditionnel.

## Conséquences

- **Un Dockerfile par repo serveur** (plateforme, agent IA) ; les apps mobiles ont leur build natif.
- **La plateforme garde son `docker-compose.yml` local** (dev) ; il n'est pas dupliqué ailleurs et n'est
  pas un artefact de production.
- **Le repo `infra` orchestre staging/prod** à partir d'**images Docker versionnées**, et porte backup
  PostgreSQL, logs, monitoring et SSL.
- **Secrets jamais dupliqués entre repos** : chaque repo fournit un `.env.example`, les valeurs réelles
  sont injectées au déploiement par l'infra.
- **Le contrat API est la frontière** : tout nouveau besoin satellite passe par un contrat API documenté
  (`docs/architecture/api-contract.md`).
- Aucune logique métier n'est dupliquée dans les satellites ; le vocabulaire métier (Kadhia, supérette,
  marchand, client, rendez-vous, retrait) est préservé partout.

## Références

- `docs/architecture/standalone-ecosystem.md` — vue d'ensemble de l'écosystème ;
- `docs/architecture/dockerized-platform.md` — détail de la plateforme dockerisée ;
- `docs/adr/0005-mobile-channel-strategy.md` — stratégie PWA / natif ;
- `docs/roadmap/launch-readiness-reorganization.md` — section post-lancement.
