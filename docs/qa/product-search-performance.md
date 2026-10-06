# Recette et performance — Recherche produit instantanée FR/AR (#636)

Date : 2026-10-06
Statut : **protocole proposé**, aucune mesure réalisée. Ce document fixe la méthode
et le gabarit de rapport ; les chiffres cibles reprennent l'issue
[#636](https://github.com/pivox/click-and-collect-superette/issues/636) et restent
des cibles à démontrer, pas des performances obtenues.

Document d'architecture associé : [`docs/architecture/product-search.md`](../architecture/product-search.md).

## 1. Objet et règles de lecture

- Le protocole couvre les quatre parcours de recherche **produit** : catalogue client
  / Kadhia, catalogue marchand, ajout depuis le référentiel, référentiel admin.
- Un résultat n'est publié que s'il est accompagné de la configuration figée
  (section 3) et du corpus versionné (section 2). Sans ces deux éléments, la mesure
  n'est pas comparable.
- Les tests déterministes (section 5) vont en CI. Les tests de charge et de latence
  (sections 6 à 8) se jouent sur un environnement contrôlé ; **aucun seuil chronométré
  n'est ajouté à la CI partagée**.
- Un écart aux cibles se documente et s'arbitre (PO + backend) avant clôture ; il ne
  se résout jamais en modifiant la cible après coup.

## 2. Corpus de recette versionné

Emplacement proposé : `apps/backend/tests/Fixtures/search/` (fichiers JSON/YAML
chargés par les tests fonctionnels et par la commande de jeu synthétique).

### 2.1 Produits de démonstration

Jeu minimal de 60 à 80 références couvrant :

| Groupe | Exemples (à construire, pas à copier du référentiel réel) |
| --- | --- |
| Boissons cola, plusieurs marques | marque A cola 1 L verre, 1 L PET, 1,5 L, 33 cl canette, pack 6 × 1 L ; marque B cola 1 L ; marque C cola zéro 1 L |
| Lait | demi-écrémé 1 L, entier 1 L, 50 cl, pack 6 × 1 L, deux marques tunisiennes |
| Eau | 1,5 L, 50 cl, pack 6 × 1,5 L |
| Produits génériques sans marque | tomate (kg), baguette (pièce), œufs (boîte de 6 / 12) |
| Références avec `name_ar` renseigné | au moins 30 % du jeu |
| Références **sans** `name_ar` mais avec alias arabe | au moins 10 références |
| Codes-barres | EAN-13 valides, dont au moins 3 commençant par `0` |
| Pièges | produit « verre » (gobelets), « sans sucre », « zéro », produit nommé avec un chiffre (`Omo 3 kg`) |

Chaque supérette de recette reçoit un sous-ensemble distinct pour vérifier l'isolation
(`shop_id`), avec des offres masquées, indisponibles et à prix promotionnel échu.

### 2.2 Dictionnaire d'alias de recette

Fichier séparé du dictionnaire de production, relu humainement, avec pour chaque
entrée : `alias`, `langue` (`fr`, `ar`, `mixte`), `cible` (`brand`, `category`,
`family`, `reference`), `cible_id`, `portee` (`global`, `shop:<id>`), `valide_par`.

Entrées obligatoires du corpus : `coca`, `coka`, `كوكا`, `كوكا كولا` → marque ;
`lait` / `حليب` → catégorie/type ; `eau` / `ماء` → catégorie ; un alias **local** à une
seule supérette qui ne doit pas apparaître ailleurs.

### 2.3 Requêtes de recette

Chaque ligne a : `requete`, `contexte` (`client`, `marchand_catalogue`,
`marchand_referentiel`, `admin`), `supérette`, `attendu` (liste ordonnée ou « vide »),
`interdit` (identifiants qui ne doivent jamais apparaître), `clarification_attendue`
(booléen).

| Famille | Requêtes |
| --- | --- |
| Marque et alias | `coca`, `Coca`, `COCA`, `coka`, `كوكا`, `كوكا كولا` |
| Préfixes | `c`, `co`, `coc`, `كو`, `كوك`, `l`, `la`, `lai`, `lait`, `ح`, `حل`, `حلي` |
| Type de produit | `cola`, `lait`, `حليب`, `eau`, `ماء` |
| Volume et unité | `coca 1l`, `coca 1 l`, `coca 1L`, `coca 1000 ml`, `كوكا ١ لتر`, `lait 50cl`, `lait 500 ml` |
| Conditionnement | `coca 1l verre`, `coca canette`, `eau pack 6` |
| Ambiguïté | `coca 1 verre`, `coca 2`, `lait 6` |
| Termes à ne pas relâcher | `coca zéro`, `coca sans sucre`, `lait entier` |
| Code-barres | EAN-13 complet existant, EAN-13 avec un chiffre erroné, EAN-13 commençant par `0`, 12 chiffres |
| Mixte FR/AR | `حليب 1l`, `lait حليب` |
| Absence réelle | `fanta` (absent du corpus), `xyzqwerty` |
| Hors périmètre | identifiant d'une offre d'une autre supérette, référence `archived` depuis le contexte marchand |

Règles d'évaluation :

- `coca`, `coka`, `كوكا` retournent le même ensemble de candidats de la marque, formats distincts.
- `cola` retourne les trois marques ; aucune n'est exclue artificiellement.
- `coca 1 verre` produit une **clarification**, jamais une conversion en 1 litre.
- Un code-barres avec un chiffre erroné retourne **zéro** résultat exact ; les alternatives
  éventuelles sont marquées comme telles dans la réponse.
- Aucun identifiant de la colonne `interdit` n'apparaît, quelle que soit la page.

## 3. Configuration figée avant toute mesure

À consigner dans le rapport (section 9) :

| Élément | Valeur à relever |
| --- | --- |
| Machine hôte | CPU (modèle, cœurs), RAM, disque, OS |
| Docker | version, `cpus`/`mem_limit` par service (`backend`, `worker`, `postgres`, `meilisearch`) |
| Meilisearch | image et tag exact, `MEILI_MAX_INDEXING_MEMORY`, `MEILI_MAX_INDEXING_THREADS`, réglages d'index (hash du fichier versionné) |
| PostgreSQL | version, `shared_buffers`, `work_mem`, index présents sur `merchant_products` et `product_references` |
| PHP / Symfony | version PHP, OPcache activé, `APP_ENV=prod`, nombre de workers PHP-FPM |
| Worker Messenger | nombre de processus, transport |
| Frontend | build production, commit, `NEXT_PUBLIC_USE_MOCKS=0` |
| Volumétrie | nombre de références, d'offres, de supérettes ; taille index Meilisearch sur disque |

## 4. Jeu synthétique paramétrable

Commande proposée (nom à aligner sur les conventions du dépôt lors de l'implémentation) :

```bash
php bin/console app:search:seed-benchmark \
  --references=100000 --offers=300000 --shops=120 --big-shop-offers=10000 --seed=636
```

Contraintes :

- Génération déterministe à partir de `--seed` ; même seed, même jeu.
- Marques, catégories et familles tirées de listes fermées pour produire des collisions
  réalistes (plusieurs colas, plusieurs laits).
- Répartition : une supérette à 10 000 offres, dix à 3 000, le reste réparti ;
  10 % d'offres masquées, 10 % indisponibles, 5 % en promotion.
- 40 % des références avec `name_ar`, 20 % avec alias arabe sans `name_ar`.
- Codes-barres uniques, 5 % commençant par `0`.
- Deux paliers obligatoires : **petit** (1 000 références / 3 000 offres) et **cible**
  (100 000 / 300 000). Le rapport présente les deux pour observer la croissance.
- Le jeu n'est jamais chargé sur une base contenant des données réelles.

## 5. Tests déterministes (CI)

### 5.1 Backend — unitaires

| Composant | Cas |
| --- | --- |
| Normalisation | casse, accents FR, espaces multiples, chiffres arabes-indiens `١٢٣` → `123`, conservation des zéros initiaux, conservation de l'arabe |
| Alias | résolution par cible, portée globale vs locale, alias inconnu ignoré sans erreur |
| Extraction de contraintes | `1l`, `1 l`, `1L`, `1000 ml`, `1,5 l`, `50cl`, `١ لتر` → `volume_ml` ; `500 g`, `1 kg` → `mass_g` ; `pack 6`, `6 x 1l` → `pack_quantity` ; `verre`, `canette`, `PET` → `packaging` |
| Ambiguïté | `1 verre` → clarification, aucune contrainte posée ; `coca 2` → aucune contrainte |
| Texte résiduel | `sans sucre`, `zéro`, `entier` restent dans la requête texte |
| Construction de filtre Meilisearch | échappement des valeurs, refus de tout filtre fourni par le client, `shop_id` toujours présent en contexte client/marchand |
| Projection | document `merchant_products` et `product_references` complets depuis une entité ; `is_publicly_searchable` faux pour supérette inactive, offre masquée, indisponible, référence non approuvée |

### 5.2 Backend — fonctionnels (API)

Adaptateur Meilisearch remplacé par une implémentation en mémoire déterministe dans
l'environnement `test` (le moteur réel n'est pas requis en CI).

| Parcours | Cas |
| --- | --- |
| Client | résultats dans la supérette active uniquement ; offre masquée/indisponible absente ; offre supprimée en base mais encore indexée → absente de la réponse ; pagination et `estimated_total` non présenté comme exact |
| Marchand catalogue | compte membre autorisé → 200 ; compte d'une autre organisation → 403 ; filtres visible/indisponible |
| Marchand référentiel | « déjà dans votre catalogue » exact ; référence archivée absente ; code-barres exact ; ajout concurrent sans doublon |
| Admin | filtres statut/marque/catégorie conservés ; filtre invalide → 400 |
| Sécurité | tentative d'injection de filtre dans `q` ; requête trop longue → 400 ; dépassement du débit → 429 |
| Dégradé | moteur indisponible → repli SQL borné **ou** état explicite `SEARCH_UNAVAILABLE`, jamais une liste vide silencieuse |
| Synchronisation | écriture métier → message outbox dans la même transaction ; rollback → aucun message ; message en double → idempotent ; suppression puis ancien message → document absent ; tâche moteur `failed` → message vers `failed` |

### 5.3 Frontend web (vitest, horloge contrôlée)

| Cas | Attendu |
| --- | --- |
| Rafale `l → la → lai → lait` à 50 ms | une seule requête réseau après la temporisation |
| Entrée pendant la temporisation | requête immédiate, pas de second appel ensuite |
| `lait` puis `pain`, réponse `lait` reçue en dernier | seuls les résultats `pain` affichés |
| « Charger plus » puis changement de texte | la page 2 de l'ancienne recherche est ignorée |
| Changement de supérette | pagination remise à zéro, résultats précédents vidés |
| Effacement du champ | retour au catalogue complet sans requête de recherche supplémentaire |
| Erreur réseau | état d'erreur distinct de « aucun résultat » ; Kadhia et quantités conservées |
| Saisie IME / composition | aucune requête pendant la composition |
| RTL | champ et résultats rendus en `dir="rtl"` quand la langue est `ar` |

### 5.4 Mobile (jest-expo)

Mêmes cas de course et de temporisation que 5.3, sur l'écran catalogue client
(`src/app/(client)/stores/[storeId]/catalog.tsx` du dépôt mobile), plus : annulation
effective via `AbortController` et absence de doublons après « charger plus ».

## 6. Mesures de latence — définitions

Chaque mesure est relevée séparément. `processingTimeMs` renvoyé par Meilisearch ne
suffit jamais à qualifier l'expérience utilisateur.

| Code | Mesure | Point de départ | Point d'arrivée | Cible p95 |
| --- | --- | --- | --- | --- |
| M1 | Saisie → caractère affiché | événement clavier | peinture du caractère | ≤ 100 ms (profil mobile, CPU ×4) |
| M2 | Moteur | réception requête Meilisearch | réponse Meilisearch (`processingTimeMs`) | ≤ 50 ms, index chargé |
| M3 | API complète | réception HTTP Symfony | fin de sérialisation | ≤ 150 ms, sans cache applicatif de résultats |
| M4 | Dernière frappe → première page utilisable | dernier `keyup` | liste textuelle rendue (images exclues) | ≤ 350 ms, temporisation incluse, sans cache client |
| M5 | Résultats depuis cache client valide | dernier `keyup` | liste rendue | ≤ 100 ms (uniquement si un cache est livré) |
| M6 | Rafale | 4 frappes à 50 ms | nombre de requêtes réseau | exactement 1 hors chargement initial |

Pour chaque mesure, publier p50, p95, p99, min, max, nombre d'observations, taux
d'erreur et taille moyenne des réponses.

## 7. Protocole de charge (environnement contrôlé)

1. Charger le jeu synthétique au palier choisi ; attendre que toutes les tâches
   Meilisearch soient `succeeded` ; relever la taille d'index.
2. Échauffement : 200 recherches issues du corpus, non comptabilisées.
3. Charge nominale : **30 sessions concurrentes**, une recherche toutes les 2 s par
   session, requêtes tirées du corpus (section 2.3) avec 20 % de préfixes courts,
   au moins **1 000 observations** après échauffement. Outil proposé : k6 ou
   autocannon, script versionné dans `tests/perf/search/`.
4. Relever M2 et M3 côté serveur (journal structuré avec `request_id`, voir
   `X-Request-Id` déjà présent dans le projet), CPU/RAM par conteneur, requêtes
   SQL par recherche (attendu : une lecture groupée + une lecture images, pas de N+1).
5. Répéter avec **import représentatif en cours** (réindexation de 10 % des offres
   pendant la charge) ; vérifier l'impact sur M2/M3, sur la latence des endpoints
   commande (`POST /api/me/kadhias/{id}/submit` ou équivalent courant) et sur PostgreSQL.
6. Répéter en **démarrage à froid** du moteur (conteneur redémarré, premier accès).
7. Si un cache est livré : répéter cache désactivé puis activé ; publier le taux de hit.
8. Mode dégradé : couper Meilisearch pendant la charge ; vérifier le coupe-circuit,
   le temps de bascule, la latence du repli SQL et l'absence de réponses « aucun produit »
   masquant la panne.

## 8. Protocole frontend et terrain

- Build production, viewport 390 × 844, Chromium avec ralentissement CPU ×4,
  RTT 100 ms, descendant 5 Mbit/s, montant 1 Mbit/s (profil Playwright/DevTools versionné).
- Scénarios : rafale (M6), `lait` puis `pain`, « charger plus » puis changement,
  bascule FR → AR et saisie arabe, effacement, erreur réseau simulée.
- Mesures M1, M4, M5 via `performance.mark`/`measure` dans le build de recette
  (marqueurs retirés ou désactivés en production si nécessaire).
- Essai terrain : deux téléphones Android courants en Tunisie, réseau mobile
  d'un opérateur local, 50 recherches du corpus par appareil ; publier appareil,
  opérateur, heure, type de réseau. Les résultats datacenter ne sont pas extrapolés
  au téléphone.

## 9. Gabarit de rapport avant/après

Fichier : `docs/qa/product-search-performance-report-<date>.md`.

```markdown
# Rapport recherche produit — <date> — palier <petit|cible>

## Configuration figée
(tableau section 3, complet)

## Pertinence du corpus
| Requête | Contexte | Attendu | Obtenu | OK |
(une ligne par requête de la section 2.3)

## Latences
| Mesure | p50 | p95 | p99 | n | erreurs | cible | écart |
(M1 à M6, avant/après sur deux colonnes si comparaison)

## Ressources
| Service | CPU moyen | CPU max | RAM moyenne | RAM max | disque |

## Synchronisation
retard d'indexation p95, tâches échouées, durée import complet, documents indexés
vs lignes PostgreSQL (réconciliation)

## Pendant import / à froid / dégradé
(résultats des étapes 5, 6 et 8 de la section 7)

## Écarts et arbitrages
| Écart | Cause identifiée | Décision | Qui | Date |
```

## 10. Ce que ce protocole ne couvre pas

- La validation des prix et promotions : un résultat de recherche ou un cache ne
  valide jamais un prix final ni l'ajout à une Kadhia.
- La haute disponibilité du moteur.
- La recherche sur commandes, clients ou supérettes.
- Toute mesure impliquant un service d'IA : le chemin de recherche n'en contacte aucun.
