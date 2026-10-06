# Issue mobile #27 — Génération des rendez-vous côté backend

Date : 3 octobre 2026. Référence : [issue mobile #27](https://github.com/pivox/click-and-collect-mobile/issues/27).

## Correctifs

- Les règles qui se chevauchent prennent désormais en compte les rendez-vous déjà ajoutés dans le lot, avant le flush Doctrine. Les candidats en conflit alimentent `skipped_existing_count`.
- La génération et la création ponctuelle prennent un verrou PostgreSQL `FOR UPDATE` sur la même supérette, dans une transaction qui englobe les lectures de contrôle et la persistance. Deux créations concurrentes pour cette supérette sont sérialisées.
- L'horizon de 1 ou 3 mois est calculé en `Africa/Tunis`, à partir de minuit aujourd'hui, avec fin exclusive. Le jour est ramené au dernier jour du mois cible si nécessaire : 31 janvier 2026 + 1 mois = 28 février ; 31 janvier 2028 + 1 mois = 29 février ; 31 janvier + 3 mois = 30 avril. Cela corrige le débordement PHP précédent vers le mois suivant.
- Seuls les rendez-vous d'une heure, dont le début est strictement futur, sont générés. Le reliquat inférieur à une heure reste ignoré.
- Une fermeture active totale ou partielle exclut un candidat. Un rendez-vous existant de même plage, même désactivé, est conservé ; un rendez-vous actif qui chevauche le candidat est également conservé. Capacités, réservations et commandes associées ne sont pas réécrites.
- Modifier ou désactiver une règle conserve les rendez-vous déjà générés. Aucun renouvellement automatique ni nouvelle opération API n'est ajouté.

## Vérifications

Les tests ont été exécutés via Docker Compose. Avant correction, les nouveaux tests ont reproduit les doublons internes au lot, le débordement de fin de mois et l'absence de verrou avant les lectures.

Commande des suites ciblées :

```bash
docker compose exec -T backend php bin/phpunit \
  tests/Unit/Processor/CreateMerchantPickupSlotProcessorTest.php \
  tests/Unit/Service/PickupSlotRuleGeneratorTest.php \
  tests/Functional/Api/MerchantPickupSlotRuleApiTest.php \
  tests/Functional/Api/PickupSlotApiTest.php \
  tests/Functional/Api/PickupSlotLeadTimeApiTest.php \
  tests/Functional/Api/MerchantExceptionalClosureApiTest.php
```

Passe complète verte : **97 tests, 1 453 assertions**. Elle couvre notamment les générations répétées, les règles chevauchantes, les fermetures partielles, les rendez-vous inactifs ou réservés, les horizons de 1/3 mois, l'année bissextile, un instant fourni dans un autre fuseau et le délai minimal de retrait. Un test vérifie également la conservation du rendez-vous réservé et de la commande associée après modification puis désactivation de la règle.

Après le durcissement supplémentaire pour les doublons historiques, trois tests ciblés (doublons historiques, idempotence avec réservation, chevauchements internes au lot) passent : **3 tests, 27 assertions**. Le nouveau test reproduisait `NonUniqueResultException` avant le bornage de la recherche.

Le test de concurrence est volontairement séparé des tests API SQLite :

```bash
# Crée uniquement la base de test configurée si elle est absente.
docker compose exec -T backend php bin/console doctrine:database:create --env=test --if-not-exists

docker compose exec -T -e RUN_POSTGRES_CONCURRENCY_TESTS=1 backend \
  php bin/phpunit tests/Functional/PostgreSQL/PickupSlotConcurrencyTest.php
```

Résultat : **3 tests, 34 assertions**. Deux processus PHP effectuent réellement deux requêtes HTTP sur PostgreSQL : génération/génération, génération/ponctuel et ponctuel/ponctuel. Le test constate leur attente sur le verrou de supérette avant de le libérer, puis contrôle le résultat et l'absence de chevauchement. Chaque scénario utilise un schéma isolé, supprimé dans `finally`. La base PostgreSQL de test reste disponible ; aucune table de développement n'est modifiée. Sans la variable d'activation, ces trois tests sont ignorés.

PHPStan sur les trois sources de ce correctif : aucune erreur. CS Fixer en mode vérification sur les huit fichiers PHP concernés : aucune modification nécessaire. `git diff --check` : aucune erreur. Le contrôle PostgreSQL après exécution confirme l'absence de schéma `slot_concurrency_*` résiduel.

## Hypothèses et limites

- Les données temporelles historiques restent des horloges locales tunisiennes, conformément à `PickupSlotDisplayTime` ; aucune migration de fuseau n'est introduite.
- Le verrou protège les deux chemins de création cités. Cette passe ne prétend pas sérialiser toutes les modifications concurrentes des règles, rendez-vous, fermetures ou réservations.
- La génération conserve le comportement des rendez-vous inactifs : elle ne réactive pas une plage identique. Une plage différente qui chevauche seulement un rendez-vous inactif n'est pas bloquée par celui-ci.
- Les doublons historiques éventuellement déjà présents ne sont pas supprimés automatiquement. La recherche d’une plage existante est bornée à un résultat pour les tolérer sans exception Doctrine.
- La recette Android/iPhone et la réservation réelle depuis une Kadhia restent du ressort de la recette mobile de l'issue.
