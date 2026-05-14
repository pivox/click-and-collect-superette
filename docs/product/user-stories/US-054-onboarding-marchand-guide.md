# US-054 — Onboarding guidé du marchand

**Epic** : EPIC-009 — Administration plateforme
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Must Have

---

## Récit

En tant que **marchand nouvellement créé**,
je veux **être guidé étape par étape lors de ma première connexion**,
afin de **configurer ma supérette et être prêt à recevoir des commandes sans aide externe**.

---

## Préconditions

- Le compte marchand vient d'être créé par l'administrateur.
- Le marchand se connecte pour la première fois.

---

## Parcours d'onboarding

```
Étape 1 : Bienvenue et présentation de l'application (1 écran)
     ↓
Étape 2 : Personnaliser le thème de la supérette (couleurs + police)
     ↓
Étape 3 : Ajouter ses premiers produits au catalogue (min. 1 recommandé)
     ↓
Étape 4 : Configurer ses créneaux de retrait (min. 1 recommandé)
     ↓
Étape 5 : Télécharger et imprimer le QR code
     ↓
Étape 6 : C'est prêt ! Aperçu du backoffice
```

---

## Règles métier

- L'onboarding n'est déclenché qu'à la **première connexion** (`User.onboardingCompletedAt IS NULL`).
- Chaque étape est optionnelle (le marchand peut passer avec « Passer cette étape »).
- Le marchand peut reprendre l'onboarding depuis les paramètres si il le quitte avant la fin.
- L'onboarding est marqué `completed` à la fin de l'étape 6 (ou après la 1ère connexion post-délai de 24h, même incomplet).
- Les étapes 2, 3 et 4 s'appuient sur les endpoints existants (thème, catalogue, créneaux).

---

## Critères d'acceptation

- [ ] Le marchand voit l'onboarding à sa première connexion uniquement.
- [ ] Chaque étape peut être passée (non bloquante).
- [ ] L'avancement est visible (indicateur de progression : 2/6).
- [ ] Le QR code est téléchargeable à l'étape 5.
- [ ] L'onboarding peut être relancé depuis les paramètres.
- [ ] Après l'onboarding, le marchand arrive sur son dashboard.

---

## Notes techniques

**Champ à ajouter sur `User` :**
```php
#[ORM\Column(nullable: true)]
private ?\DateTimeImmutable $onboardingCompletedAt = null;
```

**Endpoint complétion :**
```http
POST /api/merchant/onboarding/complete
```

**Endpoint état :**
```http
GET /api/merchant/onboarding/status
```

Réponse :
```json
{
  "completed": false,
  "current_step": 3,
  "steps": {
    "theme": true,
    "catalog": false,
    "slots": false,
    "qr": false
  }
}
```

**Côté frontend :** un composant de wizard multi-étapes avec `localStorage` pour la progression intermédiaire. La progression définitive est persistée via l'API.
