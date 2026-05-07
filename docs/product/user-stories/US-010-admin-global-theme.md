# US-010 — Administrateur configure le thème visuel global

## Rôle
Administrateur plateforme.

## Besoin
Je veux définir les couleurs et la police par défaut de la plateforme.

## Bénéfice
Toute supérette qui n'a pas configuré son propre thème hérite d'une identité visuelle cohérente et professionnelle.

---

## Préconditions

- L'administrateur est authentifié.
- Un thème global par défaut existe déjà en base (seed initial au déploiement).

---

## Scénario nominal

1. L'administrateur accède à **Admin > Personnalisation**.
2. L'écran affiche le thème actif avec ses valeurs courantes.
3. L'administrateur modifie une ou plusieurs valeurs (couleurs, police).
4. L'aperçu se met à jour.
5. L'administrateur clique sur **Enregistrer et appliquer**.
6. Le thème global est mis à jour en base.
7. Toutes les supérettes sans thème propre reflètent le nouveau thème au prochain chargement.

---

## Scénarios alternatifs

**A — Avertissement contraste**
- Lors de la saisie, si le ratio de contraste entre la couleur de texte et la couleur de fond n'atteint pas 4.5:1 (WCAG 2.1 AA), un avertissement est affiché.
- L'enregistrement n'est pas bloqué ; l'avertissement est informatif.

---

## Hors périmètre MVP

- Upload d'image de fond (post-MVP, voir ADR-0004).
- Prévisualisation temps réel desktop + mobile.
- Réinitialisation aux valeurs d'usine.
- Export de configuration.

---

## Règles métier

- Le thème global est un singleton (`PlatformTheme`) ; il ne peut pas être supprimé, seulement modifié.
- Une supérette avec un `ShopTheme` propre n'est jamais affectée par les modifications du thème global.
- Les valeurs sont stockées en base et exposées comme variables CSS via `GET /api/stores/{id}/theme`.

---

## Critères d'acceptation

- [ ] L'administrateur peut modifier les 5 couleurs : primaire, secondaire, accent, texte, fond.
- [ ] L'administrateur peut choisir la police parmi la liste approuvée.
- [ ] Un avertissement contraste est affiché si le ratio texte/fond est inférieur à 4.5:1 (WCAG 2.1 AA).
- [ ] L'enregistrement met à jour le thème global.
- [ ] Les supérettes sans thème propre héritent du nouveau thème.

---

## Notes techniques

- Entité : `PlatformTheme` (singleton, id fixe en base, seed au déploiement).
- Endpoint modification : `PUT /api/admin/theme`.
- Le thème résolu d'une supérette est exposé via `GET /api/stores/{id}/theme`.
- Le frontend injecte les variables dans `:root` au chargement.
- Sécurité : route réservée au rôle `ROLE_ADMIN`.
- Champs `ThemeConfig` (communs à `PlatformTheme` et `ShopTheme`) :
  - `primaryColor` (hex)
  - `secondaryColor` (hex)
  - `accentColor` (hex)
  - `textColor` (hex)
  - `backgroundColor` (hex)
  - `fontFamily` (enum : `inter`, `cairo`, `roboto`, `noto_sans_arabic`, `system`)
  - `baseFontSize` (int, px, entre 14 et 20)
