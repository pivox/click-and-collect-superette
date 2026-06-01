# Workflow de verification marque / EAN / prix

Objectif : ne jamais transformer une marque candidate en marque confirmee sans preuve.

## Niveaux de preuve

| Niveau | Source | Effet |
|---|---|---|
| strong | GTIN/EAN verifie, facture, catalogue fournisseur | Peut remplir `brand` |
| medium | Page produit exacte | Peut proposer une marque, revue recommandee |
| weak | Deduction par categorie ou marque candidate | Ne remplit pas `brand` |
| none | Aucun element | Produit reste en draft |

## Regle d'import

Un produit devient `commerce_ready = true` seulement si :

1. `name_fr` est renseigne ;
2. `category` et `subcategory` sont renseignes ;
3. un identifiant fiable existe : GTIN/EAN, reference fournisseur ou page produit exacte ;
4. `brand` est confirmee ou le produit est volontairement marque sans marque ;
5. le prix TND est confirme ou le produit reste en draft.

## Champs Symfony a mapper

- sku
- nameFr
- nameTnLatin
- nameAr
- category
- subcategory
- unit
- brand nullable
- gtin nullable
- gtinType nullable
- brandCandidates JSON
- verificationStatus
- sourceUrl nullable
- supplierName nullable
- supplierSku nullable
- estimatedPriceTnd nullable
- status
