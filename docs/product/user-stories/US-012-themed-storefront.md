# US-012 — Client voit la PWA aux couleurs de la supérette

## Rôle
Client.

## Besoin
Je veux que l'interface de la supérette que j'ouvre reflète son identité visuelle.

## Bénéfice
Je distingue immédiatement la supérette Al Baraka de la supérette Carthage ; l'expérience est personnalisée et mémorable.

---

## Préconditions

- La supérette est active.
- La supérette a un `ShopTheme` propre ou hérite du `PlatformTheme`.
- Le client a scanné le QR code ou ouvert la supérette depuis sa liste.

---

## Scénario nominal

1. Le client ouvre la supérette (via QR code ou liste).
2. La PWA appelle `GET /api/shops/{id}/theme`.
3. L'API retourne le thème actif (propre ou hérité).
4. La PWA injecte les variables CSS dans `:root` avant le premier rendu.
5. Toute l'interface (header, boutons, badges, typographie, fond) utilise les couleurs de la supérette.
6. Le client navigue dans le catalogue, compose sa Kadhia et soumet sa commande dans l'environnement visuel de la supérette.

---

## Scénarios alternatifs

**A — Supérette sans thème propre**
- L'API retourne le `PlatformTheme` global par défaut.
- L'interface est cohérente avec le thème plateforme.
- Le comportement est identique pour le client.

**B — Chargement lent ou erreur réseau**
- Si l'appel thème échoue, la PWA applique les variables CSS codées en dur (valeurs de fallback identiques au thème par défaut).
- Aucun écran blanc ni crash.

**C — Changement de supérette**
- Quand le client navigue vers une autre supérette, les variables CSS sont remplacées par le thème de la nouvelle supérette.
- Le changement est instantané et ne nécessite pas de rechargement de page.

---

## Règles métier

- Le thème appliqué est toujours celui de la supérette active, jamais un mélange entre deux supérettes.
- Le client ne peut pas choisir ou modifier le thème ; c'est une décision du marchand ou de l'administrateur.
- Le thème ne modifie pas la structure, la navigation ni les fonctionnalités de la PWA.
- Les couleurs choisies doivent respecter un contraste minimum lisible (règle de validation côté backoffice, pas côté client).

---

## Critères d'acceptation

- [ ] La PWA charge le thème de la supérette avant le premier rendu visible.
- [ ] Les couleurs primaire, secondaire et accent sont appliquées sur les éléments correspondants.
- [ ] La police sélectionnée est appliquée à l'ensemble de l'interface.
- [ ] L'image de fond (si présente) est affichée avec l'opacité, la position et le comportement définis.
- [ ] Si la supérette n'a pas de thème propre, le thème global par défaut s'applique sans erreur.
- [ ] Si l'appel thème échoue, les valeurs de fallback s'appliquent sans casser l'interface.
- [ ] La transition entre deux supérettes remplace le thème correctement.
- [ ] Le thème est compatible avec le mode RTL (arabe).

---

## Notes techniques

- Endpoint : `GET /api/shops/{id}/theme` — public, sans authentification requise.
- Réponse : objet JSON des variables CSS : `{ "--color-primary": "#1B6CA8", "--font-family": "Inter", ... }`.
- Injection côté PWA : `document.documentElement.style.setProperty('--color-primary', value)` au montage du contexte supérette.
- Cache HTTP : `Cache-Control: public, max-age=300` pour éviter un appel à chaque navigation.
- Fallback CSS : valeurs par défaut définies dans `:root` dans le fichier CSS global de la PWA.
- Le thème RTL (direction du texte) n'est pas piloté par le `ShopTheme` mais par la langue active (US-008).
