# Front marchand — catalogue produit

Date : 2026-05-25

## Objectif

Livrer une interface marchand complète pour gérer le catalogue d'une supérette : consulter les produits vendus, ajuster prix/disponibilité/visibilité, ajouter depuis le référentiel, gérer les ruptures en masse, créer des produits locaux, organiser les catégories marchand et proposer un assistant guidé.

Le périmètre respecte le MVP Kadhia : pas de paiement en ligne, pas de livraison, pas de programme de fidélité, pas de panier multi-supérette.

## Décision de livraison

La PR est ouverte dès le checkpoint A, puis enrichie au fur et à mesure. Chaque checkpoint doit laisser l'application dans un état cohérent et vérifiable.

1. **Checkpoint A — Base catalogue**
   - Activer l'entrée `Catalogue` dans `MerchantShell`.
   - Créer `/merchant/catalogue`.
   - Lister le catalogue marchand de la supérette connectée.
   - Afficher recherche, filtres simples, prix TND, disponibilité, visibilité, note et catégorie.
   - Afficher la catégorie référentiel comme catégorie par défaut.
   - Gérer les états chargement, vide et erreur.

2. **Checkpoint B — Édition exploitation**
   - Modifier prix, disponibilité, visibilité et note marchand.
   - Ajouter la rupture en masse.
   - Limiter la sélection groupée à 50 produits.
   - Permettre `marquer indisponible` et `remettre disponible`.

3. **Checkpoint C — Ajout depuis référentiel**
   - Rechercher dans `ProductReference` par nom, marque ou code-barres.
   - Afficher `Déjà dans mon catalogue` quand applicable.
   - Ajouter un produit référentiel avec prix, disponibilité, visibilité et note.
   - Bloquer les doublons côté UI et gérer les erreurs backend.

4. **Checkpoint D — Produit local marchand**
   - Depuis une recherche sans résultat ou un besoin terrain, permettre au marchand de créer un produit utilisable dans son propre catalogue.
   - Ne pas bloquer la vente locale sur une validation admin.
   - Préparer la remontée admin pour enrichir ou corriger le référentiel commun.

5. **Checkpoint E — Catégories marchand**
   - Ajouter des catégories propres à la supérette.
   - Initialiser par défaut avec la catégorie référentiel.
   - Permettre un override marchand sans modifier le référentiel commun.
   - Utiliser ces catégories pour filtrer le backoffice marchand et organiser le catalogue client.

6. **Checkpoint F — Assistant guidé**
   - Ajouter un parcours pas à pas pour créer ou enrichir le catalogue.
   - Réutiliser les mêmes composants métier : recherche référentiel, produit local, prix, disponibilité, visibilité et catégorie marchand.

## Architecture frontend

Créer une surface dédiée au catalogue marchand :

- `apps/frontend/src/app/merchant/catalogue/page.tsx`
- `apps/frontend/src/lib/services/merchant-catalog.service.ts`
- types dédiés dans `merchant.types.ts` ou `merchant-catalog.types.ts` si le volume devient important ;
- composants sous `apps/frontend/src/components/merchant/catalogue/`.

Composants prévus :

- `MerchantCatalogTable` ou liste responsive ;
- `MerchantCatalogFilters` ;
- `MerchantCatalogEditDrawer` ;
- `MerchantCatalogBulkActions` ;
- `ProductReferenceSearchDrawer` ;
- `MerchantLocalProductDrawer` ;
- `MerchantCategorySelector` ;
- `MerchantCatalogWizard`.

L'interface doit rester dense et opérationnelle : le marchand doit pouvoir mettre à jour son catalogue rapidement depuis un ordinateur ou une tablette, sans écran marketing.

## Architecture backend

Les checkpoints A à C consomment principalement les endpoints existants :

- `GET /api/merchant/stores/{storeId}/catalog`
- `PATCH /api/merchant/catalog/{merchantProductId}`
- `POST /api/merchant/stores/{storeId}/catalog`
- `GET /api/merchant/stores/{storeId}/product-references`
- `PATCH /api/merchant/stores/{storeId}/products/bulk-availability`

Point à confirmer avant implémentation du checkpoint D : la documentation et le code divergent sur l'endpoint de proposition produit marchand. La doc mentionne `/api/merchant/product-proposals`, tandis que le code expose `/api/merchant/stores/{storeId}/product-proposals`.

Le checkpoint D nécessite une capacité backend plus forte que `ProductReferenceProposal` : le marchand doit pouvoir vendre immédiatement un produit local.

Recommandation backend :

- ajouter une entité `MerchantLocalProduct`, scoped par `Shop` ;
- garder `ProductReference` pour le référentiel commun ;
- faire porter à `MerchantProduct` une relation vers soit `ProductReference`, soit `MerchantLocalProduct` ;
- garantir qu'un `MerchantProduct` a exactement une source produit ;
- exposer des routes marchand pour créer et modifier un produit local ;
- exposer ensuite une vue admin des produits locaux candidats au référentiel commun.

Cette approche évite de rendre visibles des `ProductReference` brouillons dans le catalogue client et garde une séparation nette entre réalité locale et référentiel partagé.

Le checkpoint E nécessite :

- une entité `MerchantCategory`, scoped par `Shop` ;
- un lien optionnel `MerchantProduct.merchantCategory` ;
- un fallback vers la catégorie du `ProductReference` ou du produit local quand aucun override n'existe ;
- une migration Doctrine ;
- des endpoints marchand pour gérer ces catégories.

## Expérience utilisateur

### Page catalogue

En haut de page :

- titre `Catalogue` ;
- compteur de produits ;
- recherche texte ;
- filtres rapides : `Tous`, `Disponibles`, `Indisponibles`, `Masqués` ;
- filtre catégorie ;
- bouton `Ajouter un produit`.

Dans la liste :

- nom produit ;
- marque ;
- volume et unité ;
- catégorie ;
- prix en TND à 3 décimales ;
- badge disponibilité ;
- badge visibilité ;
- note marchand si présente ;
- action `Modifier`.

### Édition exploitation

Le drawer d'édition permet de modifier :

- prix TND, obligatoire et positif ;
- disponibilité ;
- visibilité ;
- note marchand optionnelle ;
- catégorie marchand à partir du checkpoint E.

Avant le checkpoint E, la catégorie est affichée comme information non modifiable.

### Rupture en masse

Le mode sélection permet :

- de sélectionner jusqu'à 50 produits ;
- de marquer les produits sélectionnés indisponibles ;
- de remettre les produits sélectionnés disponibles ;
- d'afficher le nombre de produits modifiés après confirmation API.

L'UI ne doit pas appliquer définitivement un état optimiste si l'API échoue.

### Ajout depuis référentiel

Le marchand recherche un produit par nom, marque ou code-barres. Les résultats affichent les informations référentiel et l'état `Déjà dans mon catalogue` si applicable.

Si le produit n'est pas encore dans le catalogue, le marchand saisit :

- prix TND ;
- disponibilité initiale ;
- visibilité initiale ;
- note marchand optionnelle.

La catégorie référentiel est affichée comme catégorie par défaut.

### Produit local marchand

Quand aucun résultat référentiel ne convient, le marchand peut créer un produit local utilisable immédiatement dans sa supérette.

Champs de départ :

- nom français obligatoire ;
- nom arabe optionnel ;
- marque libre ou référence marque si disponible ;
- volume et unité ;
- code-barres optionnel ;
- catégorie marchand ou catégorie par défaut ;
- prix TND ;
- disponibilité ;
- visibilité ;
- note marchand optionnelle.

L'admin peut ensuite décider si ce produit local doit enrichir ou corriger le référentiel commun, mais cette validation ne bloque pas la vente locale.

### Assistant guidé

L'assistant réutilise les mêmes opérations :

1. chercher dans le référentiel ;
2. choisir un résultat ou créer un produit local ;
3. saisir prix, disponibilité, visibilité et note ;
4. choisir ou confirmer la catégorie marchand ;
5. publier dans le catalogue de la supérette.

## Règles métier

- Le marchand ne modifie jamais directement un `ProductReference` commun.
- Le marchand peut gérer librement son offre locale dans sa supérette.
- Le prix est saisi et affiché en TND avec 3 décimales.
- Le prix doit être strictement positif pour un produit vendable.
- Les changements de prix ne modifient pas les commandes déjà soumises.
- Un produit indisponible ou invisible ne doit pas être commandable côté client.
- Un produit local marchand peut être vendu sans validation admin.
- La validation admin sert seulement à mutualiser, corriger ou rattacher une donnée au référentiel commun.
- Une catégorie marchand ne modifie jamais la catégorie référentiel.
- Une Kadhia reste limitée à une seule supérette.

## Sécurité

- Toutes les routes catalogue marchand restent réservées à `ROLE_MERCHANT`.
- Chaque opération vérifie que le marchand est propriétaire de la supérette.
- `MerchantLocalProduct` et `MerchantCategory` sont toujours scoped par `Shop`.
- Les actions groupées refusent tout produit hors supérette.
- Les endpoints admin de référentiel restent séparés des endpoints marchand.

## Gestion des erreurs

- Catalogue inaccessible : afficher un état erreur avec action `Réessayer`.
- Prix invalide : bloquer le formulaire avant appel API.
- Doublon référentiel : afficher `Déjà dans mon catalogue` et proposer la modification de l'offre existante.
- Sélection groupée supérieure à 50 : bloquer l'action.
- Erreur bulk API : conserver l'état précédent et afficher le message d'erreur.
- Produit local incomplet : valider les champs obligatoires avant création.
- Endpoint de proposition produit ambigu : confirmer le contrat backend avant de brancher le checkpoint D.

## Tests attendus

Frontend :

- service catalogue : list, update, add reference, bulk availability ;
- rendu page catalogue : états chargement, vide, erreur et liste ;
- filtres disponibilité, visibilité et catégorie ;
- édition prix/disponibilité/visibilité/note ;
- sélection groupée et limite 50 ;
- recherche référentiel et garde anti-doublon ;
- création produit local ;
- assistant guidé.

Backend pour checkpoints D et E :

- migration Doctrine ;
- création produit local scoped par supérette ;
- modification produit local par propriétaire uniquement ;
- refus d'accès marchand hors supérette ;
- création catégorie marchand ;
- fallback catégorie référentiel ;
- override catégorie marchand ;
- rattachement ou remontée admin sans modification involontaire du référentiel.

Vérifications recommandées selon les fichiers modifiés :

- `npm run lint` et `npm run build` côté frontend, via Docker si nécessaire ;
- tests Vitest frontend ciblés ;
- `php bin/phpunit` ciblé côté backend pour les checkpoints D/E ;
- `vendor/bin/phpstan analyse` pour les changements backend ;
- `vendor/bin/php-cs-fixer fix --dry-run --diff`.

## Décisions à confirmer avant chaque checkpoint

- Checkpoint A : le backend expose-t-il déjà tous les champs nécessaires à la liste, notamment catégorie et note ?
- Checkpoint D : créer `MerchantLocalProduct` comme source locale vendable immédiatement, puis conserver `ProductReferenceProposal` seulement pour la remontée vers le référentiel commun si ce flux reste utile.
- Checkpoint D : définir la vue admin qui liste les produits locaux candidats au référentiel commun.
- Checkpoint E : les catégories marchand doivent-elles être créées librement ou initialisées automatiquement depuis toutes les catégories référentiel utilisées par la supérette ?
- Checkpoint F : l'assistant est-il affiché comme bouton permanent ou seulement depuis un état vide / onboarding ?

## Risques

- Le chantier est plus large qu'une simple page frontend car produit local et catégorie marchand introduisent du backend.
- Le contrat actuel de proposition produit semble incohérent entre documentation et code.
- `MerchantProduct` devra évoluer sans casser le catalogue client existant ni les commandes déjà soumises.
- Les catégories marchand peuvent complexifier la recherche si le fallback référentiel n'est pas défini clairement.

## Hors périmètre

- Paiement en ligne.
- Livraison.
- Programme de fidélité.
- Marketplace multi-supérette.
- Gestion de stock avancée ou multi-entrepôts.
- Modification directe du référentiel commun par un marchand.
