# US-008 — Basculer la langue de l'interface (français / arabe)

**Epic** : EPIC-008 — Localisation français / arabe
**Sprint** : Sprint 7 — Production (intégration progressive dès Sprint 2 pour les données bilingues)
**Priorité** : Must Have

---

## Récit

En tant qu'**utilisateur** (client, marchand ou admin),
je veux **choisir entre le français et l'arabe**,
afin de **utiliser l'application dans ma langue et avec la mise en page adaptée** (LTR pour le français, RTL pour l'arabe).

---

## Préconditions

- L'application est chargée.
- Les données bilingues (`nameFr` / `nameAr`) sont présentes dans le référentiel.

---

## Scénario nominal

1. L'utilisateur accède au sélecteur de langue (icône dans le menu ou en pied de page).
2. Il choisit « العربية » ou « Français ».
3. L'interface se rechargement dans la langue choisie.
4. En arabe : la mise en page bascule en RTL (texte aligné à droite, flux de navigation inversé).
5. Les montants restent en TND avec la notation locale.
6. La préférence est sauvegardée localement (localStorage ou cookie) et persistée dans le profil si connecté.

---

## Scénarios alternatifs

**Aucune traduction arabe disponible pour un produit** :
- Le nom français est affiché par défaut (`nameAr ?? nameFr`).

**Utilisateur non connecté** :
- La préférence est stockée localement (localStorage).
- Elle est appliquée à la reconnexion.

---

## Règles métier

- Les deux langues supportées dans le MVP sont : `fr` (français) et `ar` (arabe).
- L'arabe requiert un affichage RTL (direction `rtl` sur `<html>` ou le conteneur racine).
- La police Cairo ou Noto Sans Arabic est utilisée pour l'arabe (ThemeFontFamily enum).
- Les montants sont affichés en TND dans les deux langues.
- Les messages d'erreur de l'API doivent être traduisibles côté frontend (codes d'erreur machine, pas de messages en dur).

---

## Critères d'acceptation

- [ ] L'utilisateur peut basculer entre français et arabe en un clic.
- [ ] L'interface bascule en RTL quand l'arabe est sélectionné.
- [ ] Les noms de produits s'affichent en arabe si `nameAr` est renseigné.
- [ ] La préférence est mémorisée entre les sessions.
- [ ] Les montants sont toujours affichés en TND.
- [ ] Les vues principales (catalogue, Kadhia, commande, historique) supportent les deux langues.

---

## Notes techniques

**Côté API :**
- Pas de gestion de langue côté serveur dans le MVP. Les deux versions (`fr` et `ar`) sont retournées dans la réponse.
- Exemple dans le catalogue :
  ```json
  {
    "name_fr": "Lait demi-écrémé",
    "name_ar": "حليب نصف دسم",
    "brand": "Vitalait"
  }
  ```
- Le frontend choisit quelle version afficher selon la langue active.

**Côté frontend (Next.js) :**
- Utiliser `next-intl` ou `react-i18next` pour les libellés d'interface.
- Ajouter `dir="rtl"` sur `<html>` pour l'arabe via middleware Next.js.
- Polices : `Inter` (français), `Cairo` ou `Noto Sans Arabic` (arabe) — déjà modélisées dans `ThemeFontFamily`.

**Persistance :**
- Non connecté : `localStorage.setItem('lang', 'ar')`.
- Connecté : `PATCH /api/me/profile` avec `{ "lang": "ar" }` (champ `lang` à ajouter à `User`).
