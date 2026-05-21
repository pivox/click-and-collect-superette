# Politique de conservation et suppression des données client

## Objectif MVP

S7-003 couvre une suppression de compte client minimale côté backend. Le client peut demander la suppression de son compte via `DELETE /api/me/account`. Le compte n'est pas supprimé physiquement : il est marqué comme supprimé, anonymisé et bloqué à la connexion.

Cette politique reste volontairement limitée au MVP. Elle ne crée pas de portail RGPD complet, pas d'export de données personnelles et pas de suppression physique immédiate des commandes.

## Données anonymisées

Lors de la suppression du compte client :

- `User.deletedAt` est renseigné avec la date courante ;
- `User.email` devient une adresse technique `deleted-<uuid>@deleted.local` ;
- `User.name`, `User.firstName` et `User.lastName` deviennent `[supprimé]` ;
- `User.phone` devient `null` ;
- `User.active` passe à `false`.

Ces valeurs évitent d'exposer les coordonnées du client dans les historiques marchand qui lisent encore le `User` rattaché à une commande.

## Données supprimées ou invalidées

- Les `PasswordResetToken` actifs du client sont consommés immédiatement.
- Le compte supprime ne peut plus se connecter : `DeletedUserChecker` bloque tout `User` avec `deletedAt` non null.
- Les JWT déjà émis ne sont pas stockés dans une denylist dans le MVP. En pratique, ils ne doivent plus ouvrir les routes protégées car l'identifiant email d'origine est anonymisé et le `UserChecker` bloque le compte marqué supprimé.

## Données conservées

- Le `User` est conservé physiquement pour garder les références existantes.
- Les `Order` et `OrderLine` sont conservées pour l'historique marchand.
- Les montants TND, statuts, dates, lignes de commande et données de supérette sont conservés.
- Les logs techniques et traces d'audit éventuelles sont conservés selon le besoin opérationnel et la politique d'exploitation.

## Commandes historiques

Les commandes ne sont pas supprimées physiquement dans cette PR. Elles restent utiles au marchand pour suivre l'historique de préparation, retrait et finalisation de la Kadhia.

Recommandation MVP : conserver les commandes pendant 2 ans, puis définir une purge ou anonymisation supplémentaire dans un ticket dédié, après validation produit et juridique.

## lastLoginAt

`User.lastLoginAt` est ajouté et alimenté après une connexion JWT réussie. Ce champ pourra servir plus tard à détecter les comptes inactifs.

## Purge automatique

La commande `app:users:purge-deleted` n'est pas implémentée dans S7-003. Une purge physique des comptes ou commandes demande une politique plus stricte sur les délais, les exceptions comptables et les logs, donc elle reste hors PR backend ciblée.

## Hors scope MVP

- Portail RGPD complet.
- Export de données personnelles.
- Suppression physique immédiate des commandes.
- Suppression physique immédiate du `User`.
- Frontend de confirmation.
- Facturation.
- Refonte auth ou denylist JWT persistante.
