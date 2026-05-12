# US-020 — Visualiser le récapitulatif de la Kadhia avec le total en TND

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-003 — Gestion Kadhia.

## Objectif produit

Permettre au client de visualiser clairement sa Kadhia avant soumission : produits, quantités, prix unitaires, sous-totaux et total général en dinars tunisiens.

Cette user story est indispensable pour instaurer la confiance avant la création de commande. Le client doit comprendre ce qu'il va demander au marchand de préparer.

## Récit utilisateur

En tant que client connecté,
je veux voir le récapitulatif de ma Kadhia et son total en TND,
afin de vérifier ma commande avant de choisir un créneau de retrait.

## Acteurs

- Client connecté.
- Kadhia `draft`.
- Plateforme Click & Collect.

## Préconditions

- Le client est authentifié.
- Le client possède une Kadhia `draft` ou une Kadhia consultable.
- La Kadhia peut être vide ou contenir une ou plusieurs lignes.

## Données à afficher

Le récapitulatif doit afficher au minimum :

- identifiant de la Kadhia ;
- statut de la Kadhia ;
- supérette associée ;
- liste des lignes ;
- nom produit ;
- marque ;
- format ;
- quantité ;
- prix unitaire en TND ;
- total par ligne en TND ;
- total général en TND ;
- date de dernière mise à jour si disponible.

## Parcours nominal

1. Le client ajoute un ou plusieurs produits à la Kadhia.
2. Il ouvre le panneau ou la page récapitulative.
3. Le frontend appelle l'API de lecture de Kadhia.
4. Le backend retourne la Kadhia courante avec lignes et totaux.
5. Le client vérifie les produits, quantités et montants.
6. Le client peut continuer vers le choix du créneau.

## Règles métier

- Le total est calculé côté serveur.
- Le frontend ne doit pas être la source de vérité pour les montants.
- Les prix sont affichés avec trois décimales en TND.
- Le total d'une ligne est `quantity * unit_price_tnd`.
- Le total général est la somme des totaux de lignes.
- Les prix affichés sont les prix snapshotés au moment de l'ajout, pas les prix actuels du catalogue.
- Une Kadhia vide doit être affichée avec un état vide clair.
- Une Kadhia appartient à un seul client et ne doit pas être lisible par un autre client.

## API cible

Endpoint protégé client :

```http
GET /api/kadhia?storeId={storeId}
Authorization: Bearer <client_jwt>
```

Réponse attendue :

```json
{
  "id": "kadhia-uuid",
  "store_id": "store-uuid",
  "store_name": "Supérette El Amen",
  "status": "draft",
  "items": [
    {
      "id": "line-uuid",
      "merchant_product_id": "merchant-product-uuid",
      "name_fr": "Lait demi-écrémé Vitalait 1L",
      "brand": "Vitalait",
      "quantity": 2,
      "unit_price_tnd": "1.750",
      "line_total_tnd": "3.500"
    }
  ],
  "total_tnd": "3.500"
}
```

## Critères d'acceptation

### Kadhia avec lignes

Étant donné une Kadhia contenant plusieurs produits,
quand le client consulte le récapitulatif,
alors chaque ligne affiche le produit, la quantité, le prix unitaire et le total ligne.

### Total général

Étant donné plusieurs lignes dans la Kadhia,
quand le récapitulatif est affiché,
alors le total général correspond à la somme des lignes.

### Format TND

Étant donné un prix ou un total,
quand il est affiché,
alors il utilise le format TND avec trois décimales.

### Kadhia vide

Étant donné un client sans produit dans sa Kadhia,
quand il ouvre le récapitulatif,
alors le système affiche un état vide et l'invite à revenir au catalogue.

### Accès propriétaire

Étant donné une Kadhia appartenant à un autre client,
quand le client courant tente d'y accéder,
alors l'accès est refusé.

## Tests attendus

- Test lecture Kadhia avec une ligne.
- Test lecture Kadhia avec plusieurs lignes.
- Test calcul du total général.
- Test format des montants en string à trois décimales.
- Test Kadhia vide.
- Test accès interdit à la Kadhia d'un autre client.
- Test conservation des prix snapshotés.

## Hors périmètre

- Code promo.
- Frais de service.
- Paiement en ligne.
- Taxes détaillées.
- Export PDF.
- Historique de commandes.

## Dépendances

- US-003 — Ajouter un produit à la Kadhia.
- US-019 — Modifier ou retirer une ligne.
- Modèle de calcul serveur des totaux.

## Définition de fini

La story est terminée lorsque le client peut consulter une Kadhia fiable, lisible, calculée côté serveur, avec tous les montants en TND avant de passer au choix du créneau.