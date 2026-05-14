# US-060 — Accessibilité WCAG 2.1 AA

**Epic** : EPIC-012 — Expérience client mobile
**Sprint** : Sprint 7 — Production et localisation
**Priorité** : Should Have

---

## Récit

En tant que **client ayant des besoins d'accessibilité**,
je veux **utiliser l'application avec un lecteur d'écran ou uniquement au clavier**,
afin de **pouvoir commander dans la supérette au même titre que n'importe quel autre client**.

---

## Préconditions

- L'interface frontend est développée (parcours client, Kadhia, sélection de créneau).

---

## Périmètre MVP

Les parcours prioritaires pour l'accessibilité sont :
1. Consultation du catalogue.
2. Composition de la Kadhia (ajout, modification, suppression).
3. Sélection d'un créneau de retrait.
4. Soumission de la commande.
5. Affichage du récapitulatif et du numéro de commande.

---

## Règles métier — Critères WCAG 2.1 AA

### 1. Contraste des couleurs (1.4.3)
- Texte normal : ratio ≥ 4.5:1 sur fond.
- Texte large (≥ 18pt ou ≥ 14pt gras) : ratio ≥ 3:1.
- Le système de thème (Sprint 6) affiche déjà un avertissement contraste — l'appliquer aussi aux couleurs par défaut.

### 2. Navigation clavier (2.1.1)
- Tous les éléments interactifs sont accessibles via Tab / Shift+Tab.
- Pas de piège clavier (l'utilisateur peut toujours sortir d'une modal ou d'un menu).
- L'ordre de tabulation est logique (de haut en bas, de gauche à droite).

### 3. Focus visible (2.4.7)
- Le focus clavier est toujours visible (outline non supprimé).
- L'outline est suffisamment contrasté par rapport au fond.

### 4. Alternatives textuelles (1.1.1)
- Les images produits ont un attribut `alt` avec le nom du produit.
- Les icônes décoratives ont `aria-hidden="true"`.
- Les boutons icône (ajout/suppression Kadhia) ont un `aria-label` explicite.

### 5. Lecteurs d'écran (4.1.2)
- Les composants interactifs custom (stepper quantité, sélecteur de créneau) ont les rôles ARIA appropriés.
- Les messages d'erreur de formulaire sont associés aux champs via `aria-describedby`.
- Les mises à jour dynamiques (ajout au panier, changement de quantité) sont annoncées via `aria-live`.

### 6. Taille des cibles tactiles (2.5.5 — niveau AA amélioré, recommandé)
- Toutes les cibles tactiles font au minimum 44×44 px.
- Les boutons d'ajout/suppression de quantité sont au minimum 44×44 px.

### 7. Redimensionnement du texte (1.4.4)
- L'interface reste utilisable à 200% de zoom sans perte de contenu ni scroll horizontal.

---

## Critères d'acceptation

- [ ] Audit Lighthouse Accessibility ≥ 90 sur les 5 parcours prioritaires.
- [ ] Navigation complète au clavier sur le parcours de commande.
- [ ] Toutes les images ont un `alt` pertinent.
- [ ] Les messages d'état dynamiques sont annoncés par les lecteurs d'écran (aria-live).
- [ ] Ratio de contraste ≥ 4.5:1 sur les textes du thème par défaut.
- [ ] Cibles tactiles ≥ 44×44 px sur les actions principales.
- [ ] Interface utilisable à 200% de zoom.

---

## Notes techniques

**Outils de vérification recommandés :**
- `@axe-core/react` — intégré dans les tests automatisés (Playwright ou Jest).
- Lighthouse CI — exécuté à chaque PR sur le frontend.
- VoiceOver (iOS) / TalkBack (Android) — tests manuels sur le parcours principal.

**Composants à auditer en priorité :**
- `QuantityStepper` — boutons +/- avec aria-label « Augmenter la quantité de {produit} ».
- `PickupSlotSelector` — liste de radio buttons avec `fieldset`/`legend`.
- `KadhiaSummary` — tableau de résumé avec headers de colonnes.
- `ProductCard` — image + bouton « Ajouter » (lien ou bouton, pas un div cliquable).
- `Toast` / alertes — utiliser `role="alert"` ou `aria-live="polite"`.

**Pattern pour les mises à jour dynamiques :**
```tsx
// Annonce ajout Kadhia au lecteur d'écran
<div aria-live="polite" aria-atomic="true" className="sr-only">
  {lastAddedProduct && `${lastAddedProduct.name} ajouté à votre Kadhia.`}
</div>
```

**RTL (arabe) :** les règles WCAG s'appliquent aussi en mode RTL — vérifier que le sens de tab reste logique en mode `dir="rtl"`.

**Aucune modification backend requise.** Tout est frontend.
