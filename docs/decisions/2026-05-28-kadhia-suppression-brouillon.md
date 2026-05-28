# Décision produit — Suppression d'une Kadhia brouillon

Date : 2026-05-28
Statut : acceptée
Issue de référence : #209

## Décision

Un client peut supprimer une Kadhia en statut `draft` et recommencer.

Cette suppression concerne uniquement une Kadhia brouillon. Une Kadhia déjà soumise reste dans le cycle commande existant et ne doit pas être supprimée silencieusement.

## Décision rendue obsolète

La décision Sprint 2 suivante est désormais **obsolète** :

> Suppression d'une Kadhia entière : hors périmètre MVP. Un client ne peut pas supprimer une Kadhia ; il peut uniquement retirer ses lignes.

## Nouvelle règle produit

- Une Kadhia `draft` peut être supprimée par son client propriétaire.
- La suppression doit supprimer les lignes associées.
- Après suppression, le client peut recommencer une nouvelle Kadhia pour la même supérette.
- Le client peut toujours avoir plusieurs Kadhia pour la même supérette.
- Les Kadhia soumises restent consultables et suivent le cycle commande.

## Impacts attendus

- Ajouter ou exposer un endpoint de suppression d'une Kadhia draft, si absent.
- Adapter le frontend pour proposer `Supprimer cette Kadhia` uniquement sur les brouillons.
- Nettoyer l'état local si la Kadhia supprimée était la Kadhia active.
- Conserver l'historique des Kadhia soumises et commandes.

## Critères d'acceptation

- Le client peut supprimer une Kadhia brouillon.
- Le client ne peut pas supprimer une Kadhia soumise comme un brouillon.
- Une Kadhia supprimée ne reste pas sélectionnée comme Kadhia active côté front.
- Une autre Kadhia de la même supérette n'est pas impactée.
