# US-015 — Définir le prix et la disponibilité d'un produit de son catalogue

**Epic** : EPIC-011 — Référentiel produit et catalogue marchand
**Sprint** : Sprint 1 — Référentiel produit et catalogue marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **modifier le prix, la disponibilité ou la visibilité d'un produit de mon catalogue**,
afin de **maintenir mon offre à jour au quotidien**.

---

## Préconditions

- Le produit est déjà dans le catalogue du marchand (via US-014).
- Le marchand est connecté à son backoffice.

---

## Scénario nominal

1. Le marchand accède à la liste des produits de son catalogue.
2. Il sélectionne un produit.
3. Il modifie un ou plusieurs champs : prix TND, disponibilité (oui/non), visibilité (oui/non).
4. Il sauvegarde les modifications.
5. Les modifications sont immédiatement effectives pour les clients.

---

## Scénarios alternatifs

**Passage en indisponible d'un produit dans des Kadhias actives** :
- Le produit est masqué du catalogue public.
- Les Kadhias `draft` contenant ce produit conservent la ligne mais affichent un avertissement.
- Les commandes `submitted` ou au-delà ne sont pas affectées.

**Prix à zéro** :
- Interdit. Le système bloque la sauvegarde avec : « Le prix ne peut pas être nul. »

---

## Règles métier

- Le prix est le prix de vente public en TND, obligatoirement positif.
- `available = false` masque le produit du catalogue client mais le conserve dans le backoffice.
- `visible = false` masque également le produit du catalogue client (usage : retrait temporaire sans suppression).
- La modification du prix n'affecte pas les commandes déjà soumises (prix snapshot).

---

## Critères d'acceptation

- [ ] Le marchand peut modifier le prix d'un produit depuis son catalogue.
- [ ] La modification est effective immédiatement côté client.
- [ ] Un prix nul ou négatif est refusé.
- [ ] Le passage en `available = false` masque le produit du catalogue client.
- [ ] Les commandes existantes ne sont pas affectées par un changement de prix.
- [ ] Le marchand peut rendre un produit invisible sans le supprimer.

---

## Notes techniques

- Endpoint : `PATCH /api/merchant/catalog/{offerId}` avec `{ price?, available?, visible? }`
- Le snapshot de prix dans `KadhiaLine.unitPrice` est pris à l'ajout et ne change pas.
- L'invalidation du cache catalogue s'effectue à chaque modification.
