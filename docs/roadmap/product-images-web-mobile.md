# Roadmap addendum — Images produits web/mobile

## Contexte

L'US-041 existe déjà dans le scope historique, mais elle est encore marquée manquante dans la synthèse Sprint 1. Elle couvre l'affichage des photos produits et un premier upload admin, mais ne cadre pas suffisamment la stratégie moderne web/mobile : formats optimisés, variantes responsive, fallback, CDN/cache et exposition API.

Ce sujet doit donc être rattaché à la roadmap post-MVP comme un lot explicite, sans le confondre avec l'import catalogue par photo assisté IA. L'import photo IA sert à détecter des produits depuis un ticket, un rayon ou une liste ; ce lot sert à stocker, transformer et servir les images finales affichées dans le catalogue client et marchand.

## Positionnement recommandé

Ajouter au **Sprint 13 — Catalogue intelligent & qualité** un lot dédié :

```text
[S13-005] Gestion optimisée des images produits web/mobile
```

Ce lot complète :

- US-041 — Afficher les photos des produits dans le catalogue.
- S13-001 — Import catalogue par photo assisté IA.
- S13-003 — Score de qualité des références produit, qui inclut déjà la présence d'une image dans le score.

## Objectif business

Améliorer la lisibilité du catalogue, la confiance client et la performance web/mobile en servant des images produits propres, rapides et cohérentes sur catalogue, recherche, panier, fiche produit et administration.

## Périmètre inclus

- Upload admin d'une image produit référentiel.
- Stockage de l'original.
- Génération automatique de variantes responsive.
- Format principal servi : WebP.
- Fallback JPEG si nécessaire.
- Variantes recommandées : 200, 400, 800 et 1200 px.
- Ratio recommandé : 1:1 carré.
- Placeholder par catégorie si aucune image n'est disponible.
- Texte alternatif exploitable côté accessibilité.
- Exposition API des URLs par taille.
- Invalidations simples de cache après remplacement d'image.
- Tests upload, validation MIME/taille, génération de variantes et exposition catalogue.

## Hors périmètre

- Copie d'images depuis les catalogues d'autres enseignes.
- Génération IA d'images produit.
- Retouche avancée ou détourage automatique.
- CDN externe obligatoire dès le MVP.
- AVIF obligatoire dès le premier lot ; à prévoir comme optimisation ultérieure.
- Images spécifiques par marchand, sauf décision produit séparée.

## Règles produit

- L'image officielle d'un produit appartient d'abord au `ProductReference`.
- Le marchand ne remplace pas librement l'image référentiel partagée.
- Une image manquante ne bloque jamais la vente du produit.
- Les images externes issues de sources commerciales ne doivent pas être copiées sans droit clair.
- Les imports IA peuvent produire des contributions, mais ne valident pas automatiquement une image officielle.

## Modèle technique cible

Première version compatible avec l'US-041 :

```text
ProductReference.imageUrl
ProductReference.imageUrlThumbnail
ProductReference.imageUrlMedium
```

Version plus robuste recommandée :

```text
product_image
- id
- product_reference_id nullable
- merchant_product_id nullable
- original_path
- mime_type
- width
- height
- variants_json
- source
- status
- created_at
- updated_at
```

Exemple de `variants_json` :

```json
{
  "200": "/uploads/products/{id}/200.webp",
  "400": "/uploads/products/{id}/400.webp",
  "800": "/uploads/products/{id}/800.webp",
  "1200": "/uploads/products/{id}/1200.webp",
  "fallback_jpeg": "/uploads/products/{id}/800.jpg"
}
```

## Critères d'acceptation

- Une image JPEG, PNG ou WebP valide peut être uploadée par l'admin pour une référence produit.
- Le serveur conserve l'original et génère au minimum 200, 400 et 800 px en WebP.
- Le catalogue public expose les URLs adaptées aux cartes produit et aux fiches produit.
- Le frontend affiche un placeholder catégorie si aucune image n'existe.
- Une image trop lourde, trop petite ou au MIME non autorisé est refusée avec une erreur claire.
- Le remplacement d'une image met à jour les variantes et évite d'afficher une ancienne image en cache.
- Les tests couvrent l'upload, la validation, le resize et l'exposition API.

## Ajout recommandé dans `docs/roadmap/mvp-roadmap.md`

Dans la section **Sprint 13 — Catalogue intelligent & qualité**, ajouter :

```markdown
- **[#TODO](https://github.com/pivox/click-and-collect-superette/issues/TODO)** — Gestion optimisée des images produits web/mobile : réactivation US-041, upload admin, original conservé, variantes WebP 200/400/800/1200, fallback JPEG, placeholder catégorie, exposition API responsive.
```

Adapter ensuite le critère de sortie du Sprint 13 :

```markdown
Un marchand crée un catalogue exploitable sans saisir produit par produit ; le référentiel reste propre, gouverné et illustré avec des images produit optimisées pour le web/mobile.
```

## Prochaine étape

Créer l'issue GitHub dédiée, puis remplacer `#TODO` dans la roadmap principale par son numéro.