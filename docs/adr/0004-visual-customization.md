# ADR 0004 — Personnalisation visuelle dans le MVP

## Statut

Accepté.

## Contexte

L'application sert plusieurs supérettes différentes au sein d'une même plateforme. Chaque supérette doit pouvoir avoir une identité visuelle distincte pour que le client reconnaisse l'enseigne au premier écran.

Le MVP inclut déjà le parcours QR code, le catalogue, la Kadhia, les créneaux de retrait et le workflow de commande (Sprints 1 à 5). La personnalisation visuelle est ajoutée en Sprint 6, avant la mise en production.

## Décision

La personnalisation visuelle est incluse dans le MVP avec le périmètre suivant :

**Inclus Sprint 6 :**

- `PlatformTheme` singleton géré par l'administrateur : 5 couleurs (primaire, secondaire, accent, texte, fond) et police.
- `ShopTheme` par supérette, géré par le marchand lors de son onboarding : 5 couleurs et police.
- `GET /api/stores/{id}/theme` : endpoint public, retourne le thème résolu de la supérette (`ShopTheme` si présent, sinon `PlatformTheme`).
- Injection des valeurs sous forme de variables CSS (`--color-primary`, `--font-family`, etc.) dans `:root` côté PWA client.
- Valeurs de fallback CSS statiques dans la PWA si l'appel thème échoue.
- Cache HTTP `public, max-age=300` sur l'endpoint thème.
- Avertissement contraste WCAG 2.1 AA (ratio 4.5:1) dans le backoffice lors de la saisie de couleurs.

**Exclu du MVP (post-MVP) :**

- Upload et gestion d'image de fond (stockage serveur, livraison, CDN, optimisation).
- Prévisualisation temps réel desktop + mobile dans l'admin.
- Réinitialisation aux valeurs d'usine avec confirmation.
- Export de configuration de thème.
- Versioning / cache-busting du thème.

## Architecture retenue

### Modèle

```
PlatformTheme (singleton, id fixe)
  primaryColor, secondaryColor, accentColor, textColor, backgroundColor
  fontFamily (enum)
  baseFontSize (int, px)

ShopTheme (OneToOne nullable → Shop)
  [mêmes champs que PlatformTheme]
```

`ShopTheme` surcharge **entièrement** le `PlatformTheme` pour la supérette concernée. Si `ShopTheme` est présent, aucun champ du `PlatformTheme` n'est utilisé. Le formulaire marchand est pré-rempli avec les valeurs du `PlatformTheme` pour simplifier la saisie.

### API

```
GET  /api/stores/{id}/theme          — public, sans auth
PUT  /api/admin/theme                — ROLE_ADMIN
PUT  /api/stores/{id}/theme          — ROLE_MERCHANT, propriétaire
POST /api/stores/{id}/theme          — ROLE_MERCHANT, propriétaire (création)
```

La réponse de `GET /api/stores/{id}/theme` retourne les variables CSS directement :

```json
{
  "--color-primary": "#1B6CA8",
  "--color-secondary": "#F0A500",
  "--color-accent": "#E63946",
  "--color-text": "#1A1A1A",
  "--color-background": "#FFFFFF",
  "--font-family": "Inter",
  "--font-size-base": "16px"
}
```

Ce couplage API/CSS est un choix délibéré pour le MVP : il simplifie l'injection côté frontend sans couche d'adaptation. Si le frontend change de technologie, la réponse devra être transformée.

### Naming

Tous les endpoints utilisent le préfixe `/api/stores/` pour rester cohérents avec les contrats API existants (`api-contract.md`, `ADR-003`).

## Alternatives considérées

| Option | Raison d'écart |
|---|---|
| Un seul `ShopTheme` sans `PlatformTheme` | Pas de cohérence visuelle par défaut pour les supérettes non configurées |
| Surcharge partielle (seuls les champs renseignés écrasent) | Résolution plus complexe, comportement moins prévisible |
| CSS-in-JS ou classes Tailwind dynamiques | Couplage frontend fort, non portable |
| Feature post-MVP entière | Perd la valeur différenciatrice à la mise en production |

## Conséquences

- `PlatformTheme` et `ShopTheme` sont ajoutés au modèle de données (`data-model.md`).
- `GET /api/stores/{id}/theme` est ajouté aux routes publiques (ADR-003).
- L'onboarding marchand inclut une étape de personnalisation (optionnelle par défaut).
- L'upload d'image de fond est explicitement hors périmètre MVP et doit faire l'objet d'une nouvelle ADR avant implémentation.
