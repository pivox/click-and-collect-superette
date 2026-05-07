# US-011 — Marchand configure le thème de sa supérette lors de l'onboarding

## Rôle
Marchand.

## Besoin
Je veux choisir les couleurs et la police de ma supérette lors de mon inscription.

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
4. L'aperçu se met à jour.
5. Le marchand valide en cliquant sur **Confirmer mon thème**.
6. Un `ShopTheme` est créé et associé à sa supérette.
7. L'onboarding passe à l'étape suivante.

---

## Scénarios alternatifs

**A — Marchand conserve le thème par défaut**
- Le marchand ne modifie rien et clique sur **Conserver le thème par défaut**.
- Aucun `ShopTheme` n'est créé ; la supérette hérite du `PlatformTheme`.

**B — Avertissement contraste**
- Si le ratio de contraste entre la couleur de texte et la couleur de fond est inférieur à 4.5:1 (WCAG 2.1 AA), un avertissement est affiché.
- L'enregistrement n'est pas bloqué ; l'avertissement est informatif.

**C — Modification ultérieure**
- Depuis **Backoffice > Paramètres > Apparence**, le marchand peut modifier son thème après l'onboarding.
- Les modifications s'appliquent immédiatement à sa supérette.

---

## Hors périmètre MVP

- Upload d'image de fond (post-MVP, voir ADR-0004).
- Prévisualisation temps réel de la PWA client complète.

---

## Règles métier

- Le thème est configuré **une seule fois** lors de l'onboarding ; l'étape ne se représente pas.
- La modification ultérieure est possible depuis les paramètres.
- Un `ShopTheme` propre surcharge entièrement le `PlatformTheme` pour cette supérette.
- Si le marchand supprime son `ShopTheme`, la supérette revient au thème global.
- L'aperçu montré au marchand est celui de la PWA client (vue mobile).

---

## Critères d'acceptation

- [ ] L'étape de personnalisation apparaît une fois dans le tunnel d'onboarding marchand.
- [ ] Le thème global par défaut est pré-chargé comme point de départ.
- [ ] Le marchand peut choisir parmi les thèmes prédéfinis.
- [ ] Le marchand peut modifier les 5 couleurs : primaire, secondaire, accent, texte, fond.
- [ ] Le marchand peut choisir une police dans la liste approuvée.
- [ ] L'aperçu reflète les choix du marchand.
- [ ] Le marchand peut conserver le thème par défaut sans rien modifier.
- [ ] La validation crée le `ShopTheme` et le lie à la supérette.
- [ ] Un avertissement contraste est affiché si le ratio texte/fond est inférieur à 4.5:1.
- [ ] La modification ultérieure est accessible depuis Backoffice > Paramètres > Apparence.

---

## Notes techniques

- Entité : `ShopTheme` lié à `Shop` (relation OneToOne, nullable).
- Endpoint création : `POST /api/stores/{id}/theme`.
- Endpoint modification : `PUT /api/stores/{id}/theme`.
- Endpoint lecture thème actif : `GET /api/stores/{id}/theme` — retourne `ShopTheme` si présent, sinon `PlatformTheme`.
- Sécurité : modification réservée au `ROLE_MERCHANT` propriétaire de la supérette.
- Champs identiques à `PlatformTheme` (voir US-010 notes techniques).
