# Garde-fou « état déclaré » — ce que la CI vérifie et comment réagir

> Statut : actif. Workflow `.github/workflows/declared-state.yml`,
> script `scripts/ci/check-declared-state.php`,
> point de contrôle `docs/project/open-issues.yml`.

## Pourquoi

`AI_CONTEXT.md` est la source de vérité déclarée du projet, et la consigne est de
le lire avant toute proposition. Il a dérivé plusieurs mois sans que personne ne
s'en aperçoive. L'audit du 14 octobre 2026 a constaté qu'il :

- déclarait ouvertes trois issues fermées depuis le 30 septembre 2026
  (#378, #490-#494, #543) ;
- ignorait 15 issues ouvertes, dont l'épic CATALOG-AI #637-#649 (13 issues),
  pourtant le plus gros chantier en cours ;
- listait 24 entités Doctrine sur 61 réelles.

Conséquence concrète : une demande d'implémentation a porté sur du travail déjà
livré. Un document qui mentait sur l'état du projet a coûté du temps de
développement. Le garde-fou existe pour que cette dérive échoue bruyamment en CI
au lieu de pourrir silencieusement.

## Ce que le garde-fou vérifie

### 1. Issues ouvertes (bloquant, dans les deux sens)

`docs/project/open-issues.yml` liste les issues déclarées ouvertes. Le script
interroge l'API GitHub et compare :

- une issue listée dans le fichier mais **fermée** sur GitHub → échec ;
- une issue **ouverte** sur GitHub mais absente du fichier → échec.

Les deux sens comptent. C'est l'oubli du second qui a fait rater CATALOG-AI.

Les pull requests sont filtrées : l'endpoint `/issues` de GitHub renvoie aussi
les PR (elles portent une clé `pull_request`). Sans ce filtre, le garde-fou
produirait des centaines de faux positifs. La pagination est gérée
(`per_page=100`), le nombre d'issues peut donc croître sans adaptation.

**Pourquoi un fichier dédié et pas du parsing de prose ?** `AI_CONTEXT.md` est un
document narratif qui cite des dizaines de numéros de PR à côté des numéros
d'issues, souvent dans la même phrase (« #378 fermée complétée (PR #609) »). En
extraire de façon fiable les numéros « déclarés ouverts » est infaisable.
`open-issues.yml` est donc le point de contrôle : machine-lisible, explicite,
et sa mise à jour est un geste conscient.

### 2. Entités Doctrine (bloquant, un seul sens)

Comparaison entre la section `## Entités métier de référence` d'`AI_CONTEXT.md`
et les fichiers de `apps/backend/src/Entity/` portant `#[ORM\Entity]`.

- Entité réelle **non citée** dans la section → échec. C'est la dérive observée.
- Nombre annoncé (`**61 entités**`) différent du nombre réel de fichiers → échec.
- Classe citée **sans fichier correspondant** → avertissement seulement. La
  section mentionne légitimement des anciens noms pour mémoire, par exemple
  `MerchantProduct` (anciennement `MerchantProductOffer`). Bloquer là-dessus
  aurait rendu le garde-fou pénible pour rien.

Détection des noms : tokens entre backticks en PascalCase. Ce filtre écarte les
noms de propriétés (`baseNameFr`), les colonnes snake_case (`actor_*`) et les
chemins, qui apparaissent aussi entre backticks dans cette section.

### 3. Statuts de commande (bloquant, dans les deux sens)

Comparaison entre la liste de la section `## Statuts de commande`
d'`AI_CONTEXT.md` et les `case` de `apps/backend/src/Enum/OrderStatus.php`.
Divergence dans un sens ou dans l'autre → échec. Les 10 statuts sont un contrat
métier central (client, marchand, admin, frontend) : un écart n'a pas d'excuse.

## Fragilité assumée : les vérifications 2 et 3 lisent de la prose

Les contrôles 2 et 3 s'appuient sur des **titres de section Markdown** d'un
document narratif, maintenu à la main et modifié fréquemment. Si une section est
renommée ou déplacée, le script ne la trouve plus.

Choix explicite : dans ce cas il **avertit et sort en 0**, il n'échoue pas. Un
garde-fou qui casse la CI parce qu'un titre a bougé est un garde-fou qu'on
désactive. Conséquence à connaître : **une section renommée désactive
silencieusement sa vérification**. L'avertissement est visible dans les logs du
job, et c'est la seule alerte. Si vous renommez `## Entités métier de référence`
ou `## Statuts de commande`, mettez à jour les appels à `markdownSection()` dans
`scripts/ci/check-declared-state.php` dans la même PR.

La vérification 1, elle, ne dépend d'aucune prose : c'est la seule qui soit
structurellement solide.

## Divergence réelle vs vérification impossible

Le script distingue deux catégories, et c'est central pour sa survie :

| Situation | Sortie |
|---|---|
| Issue déclarée ouverte mais fermée | **Échec (1)** |
| Issue ouverte non déclarée | **Échec (1)** |
| Entité réelle non citée, compte annoncé faux | **Échec (1)** |
| Statut en écart avec l'enum | **Échec (1)** |
| API GitHub injoignable (panne, rate limit, HTTP 4xx/5xx) | Avertissement, sortie 0 |
| Pas de `GITHUB_TOKEN` (fork, exécution locale sans token) | Avertissement, sortie 0 |
| Section Markdown introuvable | Avertissement, sortie 0 |
| Classe citée sans fichier (ancien nom) | Avertissement, sortie 0 |
| Doublon dans `open-issues.yml` | Avertissement, sortie 0 |
| Argument invalide, fichier illisible | Erreur de script (2) |

Une panne d'infrastructure ne doit jamais faire échouer une PR. Si elle le
faisait, le garde-fou serait retiré au premier agacement.

En revanche une syntaxe invalide dans `open-issues.yml` **échoue** (avec le
numéro de ligne) : un fichier de contrôle qu'on ne sait pas lire est pire que
pas de contrôle.

## Lancer le garde-fou en local

Exactement la même commande qu'en CI, depuis la racine du dépôt :

```bash
php scripts/ci/check-declared-state.php
```

Sans token, la vérification des issues est annoncée comme non effectuée. Pour la
faire réellement :

```bash
GITHUB_TOKEN=$(gh auth token) php scripts/ci/check-declared-state.php
```

Options utiles :

```bash
php scripts/ci/check-declared-state.php --help
php scripts/ci/check-declared-state.php --skip-issues          # hors ligne, contrôles 2 et 3 seulement
php scripts/ci/check-declared-state.php --context-file=/tmp/AI_CONTEXT.md   # tester un brouillon
```

Aucune dépendance : PHP 8.3+ seul, pas de `composer install`, pas d'`ext-yaml`.
Le script embarque un lecteur du sous-ensemble YAML strict décrit ci-dessous.

### Pourquoi PHP et pas Python

Les deux étaient possibles sans dépendance. PHP a été retenu parce que le
backend du monorepo est en PHP : un développeur du projet peut lire et modifier
le script sans changer de langage, et le workflow réutilise
`shivammathur/setup-php@v2` déjà employé par `backend.yml`. Ni PHP ni Python ne
disposent d'un parseur YAML en standard (`ext-yaml` n'est pas bundlée, `PyYAML`
n'est pas dans la stdlib) ; le lecteur maison était nécessaire dans les deux cas,
ce critère ne départageait donc pas. Aucune dépendance de production n'est
ajoutée au projet (règle `AGENTS.md`).

## Mettre à jour `open-issues.yml`

C'est un geste de clôture ou d'ouverture d'issue, pas une corvée d'audit.

**Quand une issue est ouverte** — ajouter une entrée :

```yaml
  - number: 650
    title: "[X] Libellé court de l'issue"
```

**Quand une issue est fermée** — supprimer son entrée, et vérifier dans la même
passe qu'`AI_CONTEXT.md` ne la présente plus comme un chantier en cours.

**En cas d'échec CI**, le message nomme précisément l'issue, le sens de la
divergence et l'entrée YAML exacte à ajouter ou retirer. Il suffit de l'appliquer.

### Sous-ensemble YAML accepté

Volontairement étroit pour que le lecteur embarqué reste court et sans surprise :

- clés de premier niveau : `repository` (chaîne `owner/name`) et `issues` ;
- chaque élément de `issues` commence par `number:` (entier) ;
- clés optionnelles dans un élément : `title`, `note` ;
- commentaires `#` en début de ligne, lignes vides libres.

Tout le reste est refusé avec le numéro de ligne. Le fichier reste du YAML
valide : un parseur standard le lit sans problème, c'est seulement l'inverse qui
est restreint.

## Ce que le garde-fou ne détectera pas

À connaître, pour ne pas confondre « CI verte » et « documentation exacte » :

- **La prose fausse.** Le garde-fou compte et compare des listes. Il ne lit pas
  les phrases. « Backend MVP : 100 % », « Sprint 7 clôturé », « notifications
  in-app uniquement », une limite résolue depuis six mois et toujours décrite
  comme ouverte : rien de tout cela n'est vérifiable automatiquement.
- **Les numéros de PR.** `AI_CONTEXT.md` en cite des dizaines. Aucun n'est
  confronté à l'historique git.
- **Le classement des issues.** Il vérifie qu'une issue ouverte est déclarée,
  pas qu'elle est déclarée au bon endroit, ni qu'`AI_CONTEXT.md` la présente
  correctement. Ajouter une ligne dans `open-issues.yml` sans toucher à
  `AI_CONTEXT.md` fait passer la CI au vert.
- **Les pointeurs documentaires.** « Roadmap active : `docs/Sprint14/README.md` »
  alors que le plus gros chantier est CATALOG-AI reste invisible.
- **Les dossiers de sprint manquants** (`docs/Sprint8`, `Sprint9`, `Sprint10`,
  `Sprint12`, `Sprint16` n'existent pas) et les sprints déclarés livrés sans
  trace documentaire.
- **Les listes autres que les trois contrôlées** : endpoints, migrations,
  commandes console, codes d'erreur, routes frontend.
- **Une section renommée**, voir « Fragilité assumée » plus haut.
- **Les champs `title` / `note`** de `open-issues.yml` : informatifs, jamais
  comparés aux titres réels. Un libellé périmé ne fait pas échouer la CI.

Autrement dit : le garde-fou empêche la récidive précise du mode de panne
observé (issues et listes d'entités/statuts qui divergent sans bruit). Il ne
remplace pas une relecture humaine d'`AI_CONTEXT.md`.
