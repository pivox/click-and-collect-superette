# Sprint 11 — Activation commerciale + abonnement

## Objectif du sprint

Sprint 11 transforme la plateforme en produit monétisable pour les marchands, sans ajouter de paiement en ligne dans le parcours client.

Le modèle cible reste simple et MVP :

1. un marchand démarre avec une période gratuite ;
2. il passe ensuite en phase promotionnelle ;
3. il passe enfin en abonnement standard ;
4. la plateforme émet un document mensuel à destination du marchand ;
5. le paiement est encaissé manuellement, puis validé par un admin.

## Périmètre MVP

Inclus :

- abonnement marchand avec cycle de vie clair ;
- séparation entre phase tarifaire et statut opérationnel ;
- cadrage reçu / facture mensuelle marchand ;
- paiement manuel par espèces ou virement ;
- validation admin de l'encaissement ;
- relances de paiement par email si l'infrastructure email est disponible ;
- suspension douce si le retard devient trop long ;
- import CSV / scan code-barres comme levier d'activation commerciale.

Exclus :

- paiement en ligne ;
- paiement client de la Kadhia ;
- intégration comptable complète ;
- PDF fiscal complet tant que les règles fiscales ne sont pas validées ;
- entité `Invoice` tant que le statut fiscal du document n'est pas explicite ;
- génération ou transmission de facture électronique tant que le cadre légal applicable n'est pas validé.

## User stories concernées

| US | Sujet | Epic | Statut Sprint 11 |
|---|---|---|---|
| US-074 | Module abonnement marchand (`Subscription`) | EPIC-017 | Fondation backend livrée |
| US-075 | Statuts : séparer lifecycle et phase tarifaire | EPIC-017 | Fondation backend livrée |
| US-076 | Reçu / facture mensuelle marchand | EPIC-017 | Cadrage fiscal préalable requis |
| US-077 | Paiement manuel et validation admin | EPIC-017 | À cadrer avec US-076 |
| US-078 | Relances paiement par email | EPIC-018 | À cadrer après facturation |
| US-079 | Suspension douce et réactivation | EPIC-018 | À cadrer après paiement manuel |
| US-080 | Import CSV + scan code-barres | EPIC-019 | Levier d'activation commerciale |

## Articulation fonctionnelle

`Subscription` porte le contrat marchand : marchand facturé, période de départ, phase tarifaire, statut de cycle de vie et prochaine échéance. La fondation backend livrée couvre :

- une entité `Subscription` rattachée à un marchand ;
- un lifecycle séparé : `active`, `payment_due`, `grace_period`, `suspended`, `cancelled` ;
- une phase tarifaire séparée : `trial`, `promo`, `standard` ;
- le modèle cible 3 mois gratuits → 3 mois à 10 TND/mois → 50 TND/mois ;
- des endpoints de lecture admin et marchand pour consulter l'abonnement courant.

Elle ne crée pas de paiement en ligne, facture, relance email, suspension applicative ni import CSV.

US-076 transforme une période d'abonnement en document mensuel lisible pour le marchand. Ce document doit afficher la période facturée, le montant HT / TTC, la TVA si applicable, le timbre fiscal si applicable, un numéro lisible et le statut du document.

US-077 enregistre l'encaissement manuel. L'admin valide un paiement en espèces ou par virement, le rattache au document mensuel, puis met à jour le statut du document et, si nécessaire, le cycle de vie de `Subscription`.

Le paiement en ligne reste hors périmètre. Aucun flux carte bancaire, wallet, paiement client ou PSP ne doit être ajouté dans Sprint 11.

## Documents Sprint 11

- [US-076 — Reçu / facture mensuelle marchand](./US-076-recu-facture-mensuelle-marchand.md)

## Endpoints abonnement livrés

- `GET /api/merchant/subscription` — consultation par le marchand connecté de son abonnement.
- `GET /api/admin/subscriptions` — liste paginée des abonnements côté admin.
- `GET /api/admin/subscriptions/{subscriptionId}` — détail d'un abonnement côté admin.

## Sources fiscales à valider

Le cadrage US-076 s'appuie uniquement sur des principes généraux et sur des sources publiques consultées au moment du cadrage. Il ne remplace pas une validation par un comptable ou conseiller fiscal tunisien.

Sources publiques consultées :

- Ministère des Finances — obligations des assujettis à la TVA relatives à la facturation : https://www.finances.gov.tn/fr/node/75
- Ministère des Finances — obligations relatives aux factures et titres de mouvement : https://www.finances.gov.tn/fr/node/952
- Ministère des Finances — aperçu général sur la fiscalité : https://www.finances.gov.tn/fr/apercu-general-sur-la-fiscalite

## Critère de sortie Sprint 11 pour la facturation

La partie facturation est prête à être implémentée seulement lorsque les décisions fiscales bloquantes de US-076 sont validées : nature exacte du document, assujettissement TVA de la plateforme, règle de timbre fiscal, obligation éventuelle de facture électronique, numérotation et traitement des annulations / avoirs.
