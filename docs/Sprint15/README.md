# Sprint 15 — Monétisation, support, exploitation & onboarding catalogue

Date de cadrage : 2026-06-11  
Rôles de cadrage : PO + commercial terrain supérettes + Tech Lead  
Statut : cadrage Sprint 15 enrichi avec les groupements de produits référentiel. #466 et #467 livrés.

---

## 1. Objectif Sprint 15

Sprint 15 prépare le lancement officiel côté exploitation et adoption marchand.

L'objectif n'est pas seulement de livrer des écrans techniques. L'objectif terrain est que l'équipe commerciale puisse activer une supérette sans lui demander de saisir tout son catalogue produit par produit.

```text
Promesse commerciale :
On prépare les produits courants.
Le marchand choisit les groupements qui correspondent à sa supérette.
Il complète ses prix.
Sa boutique peut démarrer rapidement.
```

Le Sprint 15 conserve donc son périmètre existant :

```text
- monétisation ;
- reçu / facture à cadrer ;
- suspension douce et réactivation ;
- support et exploitation ;
- onboarding catalogue minimum ;
- import CSV / scan code-barres ;
- groupements de produits référentiel visibles marchand.
```

---

## 2. Issues Sprint 15 actives

### Monétisation

```text
#361 — Reçu / facture mensuelle à cadrer fiscalement
#364 — Suspension douce et réactivation
```

### Onboarding catalogue marchand

```text
#365 — Import CSV + scan code-barres
#464 — S15-006 / US-081 — Admin — Créer des groupements de produits référentiel
#465 — S15-007 / US-082 — Marchand — Voir et sélectionner un groupement de produits
#466 — S15-008 / US-083 — Marchand — Importer un groupement sans doublon catalogue — livré
#467 — S15-009 / US-084 — Marchand — Compléter les prix après import groupé — livré
```

---

## 3. Décision PO

Les groupements de produits sont ajoutés au Sprint 15, car ils servent directement l'onboarding catalogue marchand.

La fonctionnalité doit être comprise comme un outil d'activation catalogue, pas comme un pack commercial vendu au client.

### Wording produit recommandé

Côté admin :

```text
Groupements de produits
```

Côté marchand :

```text
Ajouter par groupement
Importer une sélection recommandée
```

À éviter pour cette fonctionnalité :

```text
Pack
Bundle
Offre groupée
```

Ces termes seront réservés aux packs commerciaux visibles client.

---

## 4. Décision commerciale terrain

Pour une supérette, la valeur est immédiate si le marchand peut démarrer avec des sélections prêtes.

Le discours commercial attendu :

```text
On ne vous demande pas de saisir tout votre catalogue.
Vous choisissez les familles de produits que vous vendez.
On ajoute les références courantes.
Vous mettez vos prix.
Votre boutique démarre plus vite.
```

Les premiers groupements utiles pour le marché tunisien :

```text
1. Premières nécessités
2. Petit déjeuner
3. Boissons froides
4. Épicerie sèche
5. Hygiène maison
```

Groupements à envisager plus tard :

```text
- Produits laitiers
- Pain & produits quotidiens
- Bébé
- Snacks enfants / école
- Ramadan
- Été / boissons / glaces
- Hygiène personnelle
```

---

## 5. Règles métier structurantes

### Règle 1 — Many-to-many produit / groupement

Un produit peut être affecté à plusieurs groupements.

Exemples :

```text
Lait UHT 1L
→ Premières nécessités
→ Petit déjeuner
→ Ramadan

Sucre en vrac — prix au kg
→ Premières nécessités
→ Épicerie sèche
→ Ramadan

Eau 1.5L
→ Premières nécessités
→ Boissons froides
→ Été
```

Conséquence technique :

```text
ProductGroup 1,N ProductGroupItem N,1 ProductReference
```

La table de jonction doit porter l'ordre d'affichage et les métadonnées du produit dans ce groupement.

### Règle 2 — Pas de doublon dans un même groupement

Un même `ProductReference` ne peut pas être ajouté deux fois dans le même `ProductGroup`.

Contrainte attendue :

```text
UNIQUE(product_group_id, product_reference_id)
```

### Règle 3 — Import catalogue idempotent

Lors de l'import d'un groupement dans le catalogue d'une supérette, si le produit est déjà présent, l'application ne doit pas créer de doublon.

Cette règle vaut quelle que soit l'origine du produit déjà présent :

```text
- ajouté manuellement ;
- ajouté par recherche référentiel ;
- ajouté par import CSV ;
- ajouté par scan code-barres ;
- ajouté par un autre groupement ;
- ajouté précédemment par le même groupement.
```

Contrôle minimum attendu :

```text
store_id + product_reference_id
```

### Règle 4 — Le groupement ne porte jamais le prix

Le groupement ne contient pas :

```text
- prix ;
- promotion ;
- stock ;
- disponibilité ;
- visibilité client ;
- ordre commercial propre à une supérette.
```

Ces données appartiennent au catalogue marchand.

### Règle 5 — Les produits importés restent à compléter si nécessaire

Si un produit est importé sans prix, il ne doit pas apparaître côté client tant que le marchand n'a pas complété les informations minimales.

Comportement produit attendu :

```text
import groupement
→ création produit catalogue marchand
→ statut à compléter si prix absent
→ non visible côté client
→ visible seulement après prix + visibilité + disponibilité valides
```

---

## 6. Modèle fonctionnel cible

### ProductGroup

Représente une sélection admin de références produit.

Champs recommandés :

```text
id
name_fr
name_ar nullable
slug
description_fr nullable
description_ar nullable
market_country default TN
status draft|published|archived
visibility admin_only|merchant
icon nullable
sort_order integer
created_at
updated_at
published_at nullable
archived_at nullable
```

### ProductGroupItem

Représente la présence d'une référence produit dans un groupement.

Champs recommandés :

```text
id
product_group_id
product_reference_id
sort_order integer
importance required|recommended|optional
merchant_help_text_fr nullable
merchant_help_text_ar nullable
created_at
updated_at
```

Contraintes :

```text
UNIQUE(product_group_id, product_reference_id)
INDEX(product_reference_id)
INDEX(product_group_id, sort_order)
```

### Catalogue marchand après import

L'import d'un groupement crée ou réutilise des produits catalogue marchand.

Le modèle exact doit respecter le code existant, mais le comportement attendu est :

```text
store_id
product_reference_id
price_tnd = 0.000 pour les imports #466 tant que le marchand n'a pas complété le prix
is_available
is_visible = false
source = product_group_import optionnel
source_product_group_id optionnel
created_at
updated_at
```

Décision #466 :

```text
Le modèle existant garde price_tnd non-nullable.
L'import groupement crée les produits avec price_tnd = 0.000.
Les produits créés restent is_visible = false.
defaultVisibility est accepté dans le payload mais n'active pas la visibilité.
L'historique de prix initial n'est pas créé pour 0.000.
```

La décision PO reste la même : aucun produit sans prix valide ne doit être visible côté client. La saisie du prix, le statut à compléter et l'historique de prix associé restent le périmètre de #467.

Décision #467 :

```text
Le catalogue marchand expose les produits à compléter via completion=needs_price.
La réponse catalogue marque ces lignes avec requires_price_completion = true.
Le marchand peut saisir un prix positif puis publier le produit dans la même action.
Le backend refuse is_visible = true tant que le prix final reste à 0.000.
Le catalogue client filtre aussi les produits sans prix positif.
Le passage de 0.000 au premier prix réel crée une ligne d'historique prix marchand.
```

---

## 7. Contrats API cibles

### Admin

```http
GET    /api/admin/product-groups
POST   /api/admin/product-groups
GET    /api/admin/product-groups/{groupId}
PATCH  /api/admin/product-groups/{groupId}
PATCH  /api/admin/product-groups/{groupId}/publish
PATCH  /api/admin/product-groups/{groupId}/archive
POST   /api/admin/product-groups/{groupId}/items
PATCH  /api/admin/product-groups/{groupId}/items/{itemId}
DELETE /api/admin/product-groups/{groupId}/items/{itemId}
```

### Marchand

```http
GET  /api/merchant/stores/{storeId}/product-groups
GET  /api/merchant/stores/{storeId}/product-groups/{groupId}
POST /api/merchant/stores/{storeId}/catalog/import-from-product-group  # #466
```

Les endpoints de lecture marchand sont rattachés à la supérette, car l'état
`déjà présent` dépend de `store_id + product_reference_id`. #465 expose
uniquement ces lectures et la sélection côté interface. #466 livre le POST
d'import réel, idempotent et sans doublon catalogue.

Payload cible :

```json
{
  "groupId": "uuid",
  "selectedProductReferenceIds": ["uuid1", "uuid2"],
  "skipExisting": true,
  "defaultVisibility": false,
  "defaultAvailability": true
}
```

Réponse cible :

```json
{
  "created": 42,
  "alreadyInCatalog": 8,
  "skipped": 0,
  "requiresPriceCompletion": 42,
  "errors": []
}
```

---

## 8. Algorithme d'import attendu

```text
1. Vérifier ROLE_MERCHANT.
2. Vérifier que le marchand possède la supérette ciblée.
3. Charger le groupement demandé.
4. Refuser si le groupement n'est pas published.
5. Vérifier que les selectedProductReferenceIds appartiennent au groupement.
6. Démarrer une transaction.
7. Pour chaque ProductReference sélectionnée :
   a. chercher un produit catalogue existant pour store_id + product_reference_id ;
   b. si trouvé : ne pas insérer, ajouter dans alreadyInCatalog ;
   c. si hors groupement ou non approuvée : ignorer et ajouter une erreur ;
   d. sinon : créer le produit catalogue marchand à compléter avec price_tnd = 0.000 ;
   e. marquer non visible.
8. Retourner le résumé : créés, déjà présents, ignorés, erreurs.
```

Protection importante :

```text
L'import doit être idempotent.
Relancer deux fois le même import ne doit pas créer deux fois les mêmes produits.
```

Protection concurrence :

```text
Ajouter une contrainte unique store_id + product_reference_id côté catalogue marchand quand c'est compatible avec le modèle existant.
Sinon, ajouter un verrou transactionnel ou une vérification robuste côté service.
```

---

## 9. US détaillées

## US-081 — Admin — Créer des groupements de produits référentiel

Issue : #464

### User story

En tant qu'admin plateforme, je veux créer, organiser, publier et archiver des groupements de produits référentiel afin de proposer aux marchands des sélections prêtes à importer.

### Périmètre inclus

```text
- modèle ProductGroup ;
- modèle ProductGroupItem ;
- relation many-to-many avec ProductReference ;
- ajout/retrait de produits ;
- ordre des produits ;
- importance required/recommended/optional ;
- publication ;
- archivage ;
- tests d'unicité.
```

### Critères d'acceptation

```text
- L'admin peut créer un groupement en draft.
- L'admin peut ajouter des références produit au groupement.
- Le même produit peut être présent dans plusieurs groupements.
- Le même produit ne peut pas être présent deux fois dans le même groupement.
- L'admin peut publier un groupement.
- Un groupement publié devient visible côté marchand.
- L'admin peut archiver un groupement.
- Un groupement archivé n'est plus visible côté marchand.
```

### Hors périmètre

```text
- import marchand ;
- saisie des prix ;
- packs commerciaux client ;
- suggestions intelligentes.
```

---

## US-082 — Marchand — Voir et sélectionner un groupement

Issue : #465

### User story

En tant que marchand, je veux ouvrir un groupement, voir les produits proposés, décocher ceux que je ne vends pas et repérer ceux déjà présents dans mon catalogue.

### Périmètre inclus

```text
- liste des groupements publiés ;
- lecture store-scoped pour calculer les statuts par supérette ;
- détail d'un groupement ;
- produits proposés ;
- état par produit : nouveau / déjà présent / non importable ;
- sélection et désélection avant import ;
- préparation du payload `{ groupId, selectedProductReferenceIds }` sans créer de produit.
```

### Critères d'acceptation

```text
- Le marchand voit seulement les groupements published.
- Le marchand peut ouvrir un groupement.
- Le marchand voit les produits du groupement dans l'ordre admin.
- Les produits déjà présents dans son catalogue sont signalés.
- Le marchand peut décocher les produits qu'il ne vend pas.
- Le bouton prépare uniquement les références sélectionnées.
- Aucun produit catalogue marchand n'est créé dans #465.
```

---

## US-083 — Marchand — Importer un groupement sans doublon catalogue

Issue : #466

### User story

En tant que marchand, je veux importer les produits sélectionnés d'un groupement afin d'activer rapidement mon catalogue, sans créer de doublons si un produit existe déjà via un groupement ou un autre ajout.

### Périmètre inclus

```text
- endpoint d'import groupé ;
- contrôle des droits marchand ;
- contrôle ownership supérette ;
- contrôle appartenance des produits au groupement ;
- création des produits manquants ;
- non-création des produits déjà présents ;
- réponse avec résumé détaillé.
```

### Critères d'acceptation

```text
- Importer un groupement une première fois crée les produits absents.
- Importer le même groupement une deuxième fois ne crée aucun doublon.
- Importer un autre groupement contenant un produit déjà présent ne crée pas de doublon pour ce produit.
- Ajouter manuellement un produit puis importer un groupement contenant ce produit ne crée pas de doublon.
- La réponse distingue created, alreadyInCatalog, skipped, requiresPriceCompletion et errors.
- Les produits créés sans prix ne sont pas visibles côté client.
```

---

## US-084 — Marchand — Compléter les prix après import groupé

Issue : #467

### User story

En tant que marchand, je veux retrouver les produits importés à compléter, saisir leurs prix et choisir leur visibilité afin de rendre mon catalogue exploitable côté client.

### Périmètre inclus

```text
- vue ou filtre Produits à compléter ;
- saisie rapide des prix ;
- activation / masquage ;
- indication des produits non publiables ;
- sauvegarde sans modifier le référentiel global.
```

### Critères d'acceptation

```text
- Après import, les produits sans prix apparaissent comme à compléter — livré.
- Le marchand peut saisir un prix ligne par ligne depuis le catalogue — livré.
- Un produit sans prix reste non visible côté client — livré.
- Un produit devient visible seulement si les conditions catalogue sont remplies — livré.
- Modifier le prix modifie uniquement le catalogue marchand — livré.
```

---

## 10. Groupement MVP — Premières nécessités

Premier groupement recommandé :

```text
Premières nécessités
```

Contenu initial recommandé :

```text
- Sel
- Sucre en vrac — prix au kg
- Sucre paquet
- Farine
- Semoule
- Couscous
- Riz
- Pâtes
- Huile
- Lait
- Eau
- Pain — prix pièce
- Oeufs — prix pièce
- Tomate concentrée
- Harissa
- Thon
- Sardines
```

Note PO : pour le MVP, le groupement doit utiliser uniquement des `ProductReference` existantes ou créées proprement par l'admin. Aucun produit ne doit être créé automatiquement dans le référentiel global par l'import marchand.

---

## 11. Produits vrac et produits quotidiens

Les produits comme sucre en vrac, pain ou oeufs doivent être cadrés avec une unité claire.

Règle MVP :

```text
Sucre en vrac — prix au kg
Semoule en vrac — prix au kg
Pain — prix pièce
Oeufs — prix pièce
```

Hors périmètre Sprint 15 : gestion avancée du poids final, prix ajusté au gramme, substitutions intelligentes.

---

## 12. Dépendances

```text
- ProductReference approuvées et visibles côté marchand.
- Catalogue marchand existant.
- Recherche produit référentiel.
- Import CSV / scan code-barres (#365) pour cohérence onboarding.
- Gouvernance référentiel Sprint 13.
```

---

## 13. Risques et garde-fous

### Risque : confusion avec les packs client

Garde-fou : utiliser `groupement` ou `assortiment`, jamais `pack`.

### Risque : duplication catalogue marchand

Garde-fou : contrainte unique ou protection transactionnelle sur `store_id + product_reference_id`.

### Risque : produit visible sans prix

Garde-fou : produit importé sans prix = à compléter, non visible client.

### Risque : groupement trop gros

Garde-fou : commencer avec 5 groupements MVP maximum, mesurer le retour terrain.

### Risque : références produit insuffisantes

Garde-fou : le groupement ne doit contenir que des références admin maîtrisées. Les produits manquants peuvent passer par les flux de proposition existants.

---

## 14. Critère de sortie Sprint 15 pour ce bloc

```text
Un admin peut créer et publier un groupement.
Un marchand peut voir ce groupement.
Un marchand peut sélectionner les produits utiles.
L'import ajoute uniquement les produits absents.
Aucun doublon catalogue n'est créé.
Les produits sans prix restent à compléter et invisibles côté client.
```

---

## 15. Ordre recommandé d'exécution

```text
1. #464 — Modèle + CRUD admin groupements.
2. #465 — Lecture marchand store-scoped + sélection + payload préparé.
3. #466 — Import idempotent sans doublon — livré.
4. #467 — Complétion prix et visibilité après import — livré.
```

L'US #466 est livrée avec la contrainte unique existante `UNIQ_MERCHANT_PRODUCTS_SHOP_REF`. L'US #467 ajoute l'expérience de complétion prix et publication sans changer le référentiel global.
