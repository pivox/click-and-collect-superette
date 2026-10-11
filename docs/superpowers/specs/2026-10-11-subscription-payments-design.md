# Gestionnaire de règlements des abonnements

## Décision validée

Le 11 octobre 2026, le marchand reste facturé au mois ou à l’année, après application des remises. L’utilisateur valide une page admin Règlements, un historique par abonnement et les versements complets ou partiels, en espèces ou par virement.

## Parcours

- `/admin/reglements` présente les documents à régler et l’historique des règlements, avec pagination, recherche marchand/document/référence et filtre abonnement depuis sa fiche.
- Chaque document affiche la période, le montant facturé après remises, le cumul réglé et le solde. Un montant intermédiaire affiche « Partiellement réglé » ; un retard reste visible.
- L’admin choisit un document, renseigne montant, date réelle, moyen et référence facultative, puis confirme le versement. Le montant est prérempli avec le solde et peut être réduit.
- Le formulaire existant dans Facturation utilise le même composant. Le marchand conserve son historique en lecture seule.
- Un abonnement gratuit n’a pas de nouveau montant à régler. Les dettes historiques restent consultables.

## Règles

Un règlement concerne exactement un document et son abonnement. Plusieurs versements sont autorisés. Montants calculés en décimal à trois chiffres, strictement positifs et inférieurs ou égaux au solde. Rejeter les dates futures, documents brouillons/annulés/payés et abonnements annulés. Aucun changement des tarifs ou échéances lors d’un règlement.

Les écritures sont transactionnelles avec verrouillage de l’abonnement puis du document. Le cumul payé et le solde sont actualisés ensemble. Un règlement partiel conserve le statut émis/en retard ; le dernier versement marque le document payé. Une réactivation n’a lieu qu’après règlement complet et absence d’autres documents exigibles impayés de cet abonnement. Les relances portent sur le solde restant.

La clé facultative `idempotency_key` UUID permet de rejouer une requête sans ajouter un versement ; réutiliser la clé avec un autre contenu renvoie un conflit. Le formulaire conserve la clé et le contenu après une erreur réseau pour permettre une nouvelle tentative sûre. Les appels historiques sans clé restent compatibles.

## API et stockage

Réutiliser `SubscriptionPayment`, `BillingDocument` et les routes existantes. Supprimer l’unicité du document dans `subscription_payments`, ajouter la clé d’idempotence unique nullable par migration. Porter la logique de confirmation dans un service. Ajouter `document_number` à la lecture des règlements.

Filtres admin : `subscription_id`, `q` sur documents et règlements ; `outstanding=true` sur les documents ; `billing_document_id` sur les règlements. Les filtres et le comptage sont appliqués côté serveur avant pagination. Les routes marchand gardent leur contrôle d’accès actuel.

## Vérification et limites

Tests API : versements successifs, solde et millimes, trop-perçu, rejouabilité, accès, filtres et réactivation. Tests interface : saisie partielle, erreur puis nouvelle tentative, historique, liens depuis abonnement et facturation. Vérification navigateur et contrôles PHP/TypeScript.

Périmètre : enregistrement manuel immédiat par admin. Pas de remboursement, suppression de règlement, échéancier automatique, paiement en ligne ou document fiscal. La PR inclut la tarification mensuelle/annuelle déjà présente sur la branche, nécessaire au parcours complet.
