# Décision produit — Document mensuel d'abonnement marchand

Date : 2026-06-05
Statut : acceptée pour le MVP
Issue de référence : #361

## Décision

Le Sprint 11 retient un **document mensuel interne non fiscal** pour l'abonnement plateforme marchand.

Ce document est exposé dans le produit comme `monthly_statement` et affiché avec le libellé **Document mensuel interne non fiscal**. Il ne doit pas être présenté comme une facture fiscale tunisienne conforme, ni générer de PDF fiscal complet, tant qu'un comptable ou conseiller fiscal n'a pas validé le régime exact de la plateforme.

## Contexte

US-076 demande un reçu ou une facture mensuelle pour donner au marchand une trace lisible de son abonnement Kadhia : période, montant TND, échéance et statut.

Les pages publiques du Ministère des Finances tunisien rappellent que les assujettis TVA doivent notamment utiliser des factures numérotées dans une série ininterrompue et mentionner l'identification fiscale, la désignation du service, le prix hors TVA, les taux et montants de TVA lorsque ces obligations s'appliquent.

Ces éléments confirment qu'une facture conforme nécessite un cadrage fiscal propre. Ils ne suffisent pas à décider le régime fiscal de Kadhia, le taux TVA applicable, le timbre fiscal, les mentions obligatoires ni l'obligation éventuelle de facturation électronique.

Sources de cadrage consultées le 2026-06-05 :

- https://www.finances.gov.tn/fr/node/75
- https://www.finances.gov.tn/fr/node/952
- https://www.finances.gov.tn/fr/apercu-general-sur-la-fiscalite

## Justification

- Le MVP doit fournir une traçabilité claire au marchand sans créer une fausse promesse fiscale.
- Le paiement en ligne reste hors périmètre : le document mensuel décrit l'abonnement plateforme, pas le paiement d'une Kadhia client.
- Le modèle reste raccordable à US-077 paiement manuel : un document émis peut devenir `paid` après validation admin d'un paiement espèces ou virement.
- La numérotation `MS-YYYY-000001` identifie les documents mensuels internes sans utiliser le préfixe `INV`.

## Impact implémentation

- Nom de domaine retenu : `BillingDocument`, pas `Invoice`.
- Type de document MVP : `monthly_statement`.
- Statuts exposés : `draft`, `issued`, `paid`, `overdue`, `cancelled`.
- Montants exposés : `amount_tnd`, `amount_paid_tnd`, `amount_due_tnd`, `currency=TND`.
- Aucun champ TVA, timbre fiscal ou matricule fiscal obligatoire n'est figé dans cette PR.
- Aucun PDF fiscal complet n'est généré.

## Suivi

La future facture conforme, si retenue, devra faire l'objet d'une décision dédiée avant de renommer le document, d'ajouter les champs fiscaux, de produire un PDF fiscal ou d'introduire une numérotation `INV`.
