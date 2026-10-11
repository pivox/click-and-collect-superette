# Abonnements mensuels et annuels avec remises par mois

## Décisions validées

Le marchand dispose des mêmes fonctionnalités avec un abonnement payant,
avec remise ou gratuit. Le terme « semi-payant » est retiré. La périodicité
peut être mensuelle ou annuelle. Les remises sont programmées par mois et
s'appliquent aussi au montant annuel. Le « go » du 11 octobre autorise
l'implémentation de ce périmètre.

## Règles retenues pour cette première version

- Un abonnement par organisation marchande, avec maintien du rattachement
  historique au marchand pour les comptes non migrés.
- Tarif de base configurable en TND pour une période de 1 ou 12 mois.
- Trois modes : `paid`, `discounted`, `free`. Une gratuité peut être permanente
  (`free_months: null`) ou limitée à un nombre de mois, suivie du tarif de base.
- Une remise est définie par `from_month`, `to_month`, `type` (`percentage` ou
  `fixed`) et `value`. Les mois sont numérotés à partir de la date d'effet des
  conditions. Les plages ne se chevauchent pas et ne se répètent pas chaque année.
- Une remise fixe est un montant TND par mois ; une remise en pourcentage
  s'applique au prix de base attribué à ce mois. Le montant net ne descend pas
  sous zéro. Les bornes de mois sont inclusives, de 1 à 120.
- L'annuel est réparti en 12 parts au millime ; le reliquat est attribué au
  dernier mois. Les remises s'appliquent ensuite à chaque part ; le total annuel
  est la somme exacte des 12 montants nets. Pas de seconde remise annuelle
  automatique : le tarif annuel choisi constitue déjà la base commerciale.
- Les nouvelles conditions prennent effet au prochain renouvellement. Un
  abonnement créé par l'admin prend effet immédiatement. Une seule modification
  future est programmable ; sa modification remplace cette programmation, avec
  audit. Les conditions précédentes restent consultables dans le journal admin.
- Les abonnements existants conservent le parcours historique tant que l'admin
  n'a pas programmé de nouvelles conditions. Les créations explicites admin
  utilisent les conditions saisies ; aucun compte existant n'est migré de force.
- Aucun changement rétroactif des documents émis ou des paiements. Le gratuit
  ne crée pas de créance ni de relance pour sa période. Une dette antérieure
  reste indépendante de la nouvelle tarification.
- Les montants annuels et la période couverte sont explicites. Les documents
  restent internes non fiscaux et les paiements manuels (espèces ou virement).

## API additive et interface

Entrée tarifaire : `billing_interval`, `pricing_mode`, `base_price_tnd`,
`free_months`, `discounts`, `reason` (motif admin requis).

- `POST /api/admin/subscriptions` : création avec `merchant_id` et les conditions.
- `POST /api/admin/subscriptions/pricing-preview` : simulation de création sans écriture.
- `PUT /api/admin/subscriptions/{id}/pricing` : programmer les conditions.
- `POST /api/admin/subscriptions/{id}/pricing-preview` : simulation sans écriture.
- `POST /api/admin/subscriptions/{id}/billing-document` : émission manuelle et
  idempotente du document courant pour en permettre le paiement manuel ; refus
  d'une période gratuite. Type `annual_statement` pour les 12 mois, conservation
  du type `monthly_statement` pour les documents mensuels.
- Lectures admin/marchand : conserver les champs historiques et ajouter
  `billing_interval`, `pricing_mode`, `base_price_tnd`, `period_price_tnd`,
  `pricing_terms`, `scheduled_pricing`, `period_breakdown`.
- `pricing_terms` et `scheduled_pricing` portent les conditions et `effective_at`.
- Chaque ligne de `period_breakdown` porte `month`, `starts_at`, `ends_at`,
  `base_amount_tnd`, `discount_amount_tnd`, `amount_tnd`.
- La prévisualisation renvoie la représentation de l'abonnement au début de la
  période visée, avec son détail mensuel.

Admin : création par sélection/recherche de marchand, édition des conditions,
simulation avant programmation, lignes de remise et récapitulatif. Marchand :
lecture FR/AR de la périodicité, du total de période, des remises et du prochain
changement ; aucune écriture tarifaire.

## Architecture et validation

Conditions versionnées persistées par Doctrine ; calcul monétaire central en
arithmétique décimale, calendrier ancré sur la date d'effet et bornes de fin de
mois déterministes. DTO explicites, validation des plages, montants et modes,
opérations réservées à `ROLE_ADMIN`, journalisation atomique. Le calcul sert
les lectures, la tâche de rafraîchissement et l'émission de documents.

Tests : héritage, annuel, répartition au millime, gratuité temporaire/permanente,
remises par plage, expiration, passage mensuel/annuel, années bissextiles,
autorisations, doublons d'organisation, simulation sans effet, documents
antérieurs inchangés et parcours admin/marchand. Vérifications par Docker.
