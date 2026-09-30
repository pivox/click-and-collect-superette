# Registre des images génériques et placeholders (#582)

Registre de provenance et de droits d'usage des assets visuels non spécifiques
à un SKU (épic #580). Toute image externe ajoutée ici doit conserver source,
licence et date de collecte ; une source inconnue ne peut pas devenir une
image officielle publique.

## Placeholders par catégorie (livrés — V1)

Pictogrammes Unicode neutres, sans marque ni emballage fictif, rendus par
`apps/frontend/src/lib/category-placeholder.ts` dans `ProductThumbnail`
(même composant web/PWA — rendu cohérent, jamais d'image cassée : le
placeholder est le fallback ultime après l'image exacte et son `onError`).

| Catégorie | Pictogramme | Source | Licence |
|---|---|---|---|
| Fruits et légumes | 🥬 | Unicode (generated_placeholder) | Libre (standard Unicode, rendu par la police système) |
| Pain / boulangerie | 🥖 | idem | idem |
| Produits laitiers | 🥛 | idem | idem |
| Épicerie salée / conserves | 🥫 | idem | idem |
| Épicerie sucrée / confiserie | 🍬 | idem | idem |
| Boissons | 🥤 | idem | idem |
| Vrac / céréales / légumineuses | 🌾 | idem | idem |
| Surgelés | ❄️ | idem | idem |
| Hygiène / beauté | 🧼 | idem | idem |
| Entretien / ménage | 🧽 | idem | idem |
| Bébé | 🍼 | idem | idem |
| Animaux | 🐾 | idem | idem |
| Dépannage / bazar | 🔦 | idem | idem |
| **Défaut (toute autre catégorie)** | 🛒 | idem | idem |

Correspondance par mots-clés sur le slug et le nom FR de la catégorie
(normalisés sans accents) — voir `category-placeholder.ts`. Le texte
alternatif reste le nom du produit (`ProductThumbnail`).

## Produits génériques prioritaires (statut de couverture)

Cible : une image générique photographique neutre par produit (`ProductImage`
`image_kind: generic` sur la référence générique, ADR-0006). **En attente du
sourcing sous droits clairs (#584 registre étendu, #585 audit des sources).**
D'ici là, le fallback explicite est le placeholder de catégorie ci-dessus —
la vente n'est jamais bloquée.

| Produit | Image générique | Fallback actuel |
|---|---|---|
| Tomate, Pomme de terre, Oignon, Piment, Persil, Ail, Citron, Banane, Pomme, Orange | à sourcer | 🥬 Fruits et légumes |
| Baguette | à sourcer | 🥖 Pain / boulangerie |
| Œuf | à sourcer | 🥛 Produits laitiers (ou catégorie dédiée du marchand) |
| Olives, Lentilles, Pois chiches, Haricots, Épices, Fruits secs, Sucre en vrac, Semoule en vrac, Farine en vrac | à sourcer | 🌾 Vrac |

Règles pour le sourcing à venir (rappel de l'épic) :

- aucune image ne doit afficher une marque ou un emballage fictif ;
- images carrées ou recadrables, compatibles variantes 200/400/800/1200 (#391) ;
- provenance, licence et date de collecte enregistrées dans ce registre ;
- pas de copie d'assets d'enseignes (Carrefour, Géant…) sans droit clair —
  une `retailer_observation` sert à identifier une source, jamais à publier.

## Historique

- 2026-09-30 : création du registre ; placeholders Unicode par catégorie
  livrés (#582 V1) ; photos génériques différées au sourcing (#584/#585).
