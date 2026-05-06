# US-010 — Administrateur configure le thème visuel global

## Rôle
Administrateur plateforme.

## Besoin
Je veux définir les couleurs, la police et l'image de fond par défaut de la plateforme.

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
3. L'administrateur modifie une ou plusieurs valeurs (couleur primaire, police, etc.).
4. L'aperçu en direct se met à jour instantanément.
5. L'administrateur clique sur **Enregistrer et appliquer**.
6. Le thème global est mis à jour en base.
7. Toutes les supérettes sans thème propre reflètent immédiatement le nouveau thème.

---

## Scénarios alternatifs

**A — Réinitialisation**
- L'administrateur clique sur **Réinitialiser**.
- Le thème revient aux valeurs d'usine (seed initial).
- Une confirmation est demandée avant application.

**B — Upload image de fond**
- L'administrateur glisse ou sélectionne un fichier (JPEG/PNG/WebP, max 2 Mo).
- Le fichier est uploadé et l'aperçu se met à jour.
- Si le fichier dépasse 2 Mo ou a un format non supporté, un message d'erreur est affiché.

**C — Suppression image de fond**
- L'administrateur supprime l'image en cliquant sur la croix.
- Le fond revient à la couleur de fond unie définie.

---

## Règles métier

- Le thème global est un singleton (`PlatformTheme`) ; il ne peut pas être supprimé, seulement modifié.
- Une supérette avec un `ShopTheme` propre n'est jamais affectée par les modifications du thème global.
- Les valeurs sont stockées en JSON et injectées comme variables CSS via `GET /api/theme/default`.
- L'image de fond est stockée côté serveur ; seul le chemin est enregistré en base.
- L'opacité de l'image de fond est comprise entre 0 % et 100 %.

---

## Critères d'acceptation

- [ ] L'administrateur peut modifier les 5 couleurs (primaire, secondaire, accent, texte, fond).
- [ ] L'administrateur peut choisir la police parmi la liste approuvée.
- [ ] L'administrateur peut uploader une image de fond (max 2 Mo, formats JPEG/PNG/WebP).
- [ ] L'administrateur peut régler l'opacité, la position et le comportement de l'image de fond.
- [ ] L'aperçu se met à jour en temps réel sans rechargement de page.
- [ ] L'aperçu bascule entre vue desktop et vue mobile.
- [ ] L'enregistrement met à jour le thème global immédiatement.
- [ ] Les supérettes sans thème propre héritent du nouveau thème.
- [ ] La réinitialisation demande une confirmation explicite.
- [ ] Un fichier trop lourd ou au mauvais format affiche un message d'erreur explicite.

---

## Notes techniques

- Entité : `PlatformTheme` (singleton, id fixe en base).
- Endpoint : `PUT /api/admin/theme`, `POST /api/admin/theme/background`.
- Les valeurs sont exposées via `GET /api/theme/default` sous forme de variables CSS JSON.
- Le frontend injecte les variables dans `:root` au chargement.
- Stocker l'image dans `storage/themes/platform/` avec un nom unique (UUID).
- Sécurité : route réservée au rôle `ROLE_ADMIN`.
