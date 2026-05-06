# US-011 — Marchand configure le thème de sa supérette lors de l'onboarding

## Rôle
Marchand.

## Besoin
Je veux choisir les couleurs, la police et l'image de fond de ma supérette une seule fois lors de mon inscription.

## Bénéfice
Ma supérette a une identité visuelle qui lui appartient ; les clients reconnaissent mon enseigne dès l'ouverture de l'application.

---

## Préconditions

- Le compte marchand a été créé par l'administrateur.
- Le marchand est en cours d'onboarding (étape non encore complétée).
- Le thème global par défaut existe en base et sert de point de départ.

---

## Scénario nominal

1. Lors de l'onboarding, le marchand arrive à l'étape **Personnalisation de votre supérette**.
2. L'écran présente le thème global par défaut pré-rempli en point de départ.
3. Le marchand choisit un thème prédéfini ou modifie manuellement les couleurs et la police.
4. Le marchand uploade optionnellement une image de fond pour sa supérette.
5. L'aperçu mobile se met à jour en temps réel.
6. Le marchand valide en cliquant sur **Confirmer mon thème**.
7. Un `ShopTheme` est créé et associé à sa supérette.
8. L'onboarding passe à l'étape suivante.

---

## Scénarios alternatifs

**A — Marchand conserve le thème par défaut**
- Le marchand ne modifie rien et clique sur **Conserver le thème par défaut**.
- Aucun `ShopTheme` n'est créé ; la supérette hérite du `PlatformTheme`.

**B — Modification ultérieure**
- Depuis **Backoffice > Paramètres > Apparence**, le marchand peut modifier son thème après l'onboarding.
- Ce n'est pas un nouveau parcours d'onboarding ; c'est un écran d'édition classique.
- Les modifications s'appliquent immédiatement à sa supérette.

**C — Upload image trop lourde**
- Si le fichier dépasse 2 Mo ou a un format non supporté, un message d'erreur est affiché.
- Le marchand reste sur l'étape sans perte des autres valeurs saisies.

---

## Règles métier

- Le thème est configuré **une seule fois** lors de l'onboarding ; l'étape ne se représente pas.
- La modification ultérieure est possible depuis les paramètres, mais elle n'est pas obligatoire.
- Un `ShopTheme` propre surcharge entièrement le `PlatformTheme` pour cette supérette.
- Si le marchand supprime son `ShopTheme`, la supérette revient au thème global.
- L'image de fond est spécifique à la supérette ; elle est stockée séparément de celle du thème global.
- L'aperçu montré au marchand est celui de la PWA client (vue mobile), pas du backoffice.

---

## Critères d'acceptation

- [ ] L'étape de personnalisation apparaît une fois dans le tunnel d'onboarding marchand.
- [ ] Le thème global par défaut est pré-chargé comme point de départ.
- [ ] Le marchand peut choisir parmi les thèmes prédéfinis.
- [ ] Le marchand peut modifier manuellement les couleurs (primaire, secondaire, accent).
- [ ] Le marchand peut choisir une police dans la liste approuvée.
- [ ] Le marchand peut uploader une image de fond (max 2 Mo, JPEG/PNG/WebP).
- [ ] L'aperçu reflète la vue mobile de la PWA client en temps réel.
- [ ] Le marchand peut conserver le thème par défaut sans rien remplir.
- [ ] La validation crée le `ShopTheme` et lie à la supérette.
- [ ] La modification ultérieure est accessible depuis Backoffice > Paramètres > Apparence.
- [ ] Un fichier non conforme affiche un message d'erreur sans bloquer le reste du formulaire.

---

## Notes techniques

- Entité : `ShopTheme` lié à `Shop` (relation OneToOne, nullable).
- Endpoint création : `POST /api/shops/{id}/theme`.
- Endpoint modification : `PUT /api/shops/{id}/theme`.
- Endpoint upload fond : `POST /api/shops/{id}/theme/background`.
- Endpoint suppression fond : `DELETE /api/shops/{id}/theme/background`.
- Endpoint lecture thème actif : `GET /api/shops/{id}/theme` — retourne `ShopTheme` si présent, sinon `PlatformTheme`.
- Stocker l'image dans `storage/themes/shops/{shopId}/` avec un nom unique (UUID).
- Sécurité : modification réservée au `ROLE_MERCHANT` propriétaire de la supérette.
- Champs ThemeConfig (communs à `PlatformTheme` et `ShopTheme`) :
  - `primaryColor` (hex)
  - `secondaryColor` (hex)
  - `accentColor` (hex)
  - `textColor` (hex)
  - `backgroundColor` (hex)
  - `fontFamily` (enum : `inter`, `cairo`, `roboto`, `noto_sans_arabic`, `system`)
  - `baseFontSize` (int, px, entre 14 et 20)
  - `backgroundImagePath` (string, nullable)
  - `backgroundOpacity` (int, 0–100)
  - `backgroundPosition` (enum : `center`, `top`, `bottom`)
  - `backgroundSize` (enum : `cover`, `contain`, `repeat`)
