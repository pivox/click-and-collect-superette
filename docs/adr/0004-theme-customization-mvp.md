# ADR 0004 — Personnalisation visuelle MVP

## Statut

Accepté.

## Contexte

L'application sert plusieurs supérettes sur une même plateforme. Le client arrive souvent par scan du QR code magasin ; il doit reconnaître rapidement l'espace de la supérette qu'il vient d'ouvrir, tout en conservant une expérience stable pour consulter le catalogue, préparer sa Kadhia, choisir un rendez-vous et suivre le retrait.

Le MVP inclut la personnalisation visuelle par supérette, mais cette décision doit rester limitée avant l'implémentation backend. L'objectif est de couvrir l'identité visuelle essentielle sans introduire de gestion média, de CDN, de versioning avancé ou de complexité frontend prématurée.

## Décision

La personnalisation visuelle est incluse dans le MVP avec deux niveaux de thème :

- `PlatformTheme` : thème global de la plateforme, géré par l'administrateur.
- `ShopTheme` : thème propre à une supérette, géré par le marchand et associé à sa supérette.

Le thème actif d'une supérette est exposé par l'endpoint public :

```http
GET /api/stores/{storeId}/theme
```

Le contrat HTTP utilise `stores` pour rester cohérent avec l'API publique. L'entité PHP pourra éventuellement s'appeler `Shop`, mais ce choix interne ne doit pas modifier le contrat HTTP public.

## Périmètre MVP

Le MVP couvre uniquement :

- 5 couleurs : `primary`, `secondary`, `accent`, `text`, `background`.
- Une police sélectionnée parmi une liste approuvée.
- Une taille de base de police.
- Un `PlatformTheme` global, présent par défaut.
- Un `ShopTheme` optionnel par supérette.
- La résolution du thème actif : `ShopTheme` si présent, sinon `PlatformTheme`.
- L'exposition publique du thème actif via `GET /api/stores/{storeId}/theme`.
- La réponse sous forme de variables CSS consommables par la PWA.
- Un cache HTTP court : `Cache-Control: public, max-age=300`.
- Un avertissement de contraste non bloquant dans les écrans admin et marchand.
- Des valeurs CSS de fallback côté PWA si l'appel thème échoue.

Exemple de réponse publique :

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

## Hors MVP / post-MVP

Sont explicitement exclus du MVP :

- Upload d'image de fond.
- Stockage, livraison, CDN ou optimisation d'image.
- Cache-busting ou versioning avancé du thème.
- Prévisualisation desktop/mobile avancée.
- Réinitialisation avancée aux valeurs d'usine.
- Export ou import de configuration de thème.
- Personnalisation de layout, navigation ou composants métier.

Les mentions d'image de fond doivent rester documentées uniquement comme extension future ou sujet post-MVP.

## Choix `stores` côté API

Le nom public de ressource API est `stores`, y compris pour le thème :

```http
GET  /api/stores/{storeId}/theme
POST /api/stores/{storeId}/theme
PUT  /api/stores/{storeId}/theme
```

Ce choix évite d'exposer les détails de nommage interne. Si le backend Symfony/API Platform conserve une entité `Shop`, une opération dédiée devra porter explicitement le chemin `/api/stores/{storeId}/theme`.

## Choix `PlatformTheme` / `ShopTheme`

`PlatformTheme` sert de thème global obligatoire. Il garantit qu'une supérette sans personnalisation reste affichable avec une identité cohérente.

`ShopTheme` est optionnel et lié à une seule supérette. Quand il existe, il surcharge entièrement le `PlatformTheme` pour cette supérette. Le MVP ne retient pas de surcharge champ par champ afin de garder une résolution prévisible et simple à tester.

## Cache public 5 minutes

L'endpoint public de lecture du thème doit retourner :

```http
Cache-Control: public, max-age=300
```

Conséquence attendue : après modification d'un thème, certains clients peuvent voir l'ancien thème pendant au plus 5 minutes. Ce compromis est accepté pour le MVP car le thème n'est pas une donnée transactionnelle de Kadhia, commande ou retrait.

## Contraste

Les écrans de configuration affichent un avertissement si le contraste texte/fond est insuffisant, par exemple sous le seuil WCAG 2.1 AA de 4.5:1.

L'avertissement est non bloquant dans le MVP. Il informe l'administrateur ou le marchand, mais n'empêche pas l'enregistrement du thème.

## Conséquences techniques

- Le contrat API documenté doit rester en `/api/stores/{storeId}/theme`.
- Les routes de création et modification du thème de supérette doivent être réservées au marchand propriétaire.
- La route de modification du `PlatformTheme` doit rester réservée à l'administrateur.
- La route publique de lecture ne doit exposer que les variables CSS nécessaires au rendu.
- La PWA doit appliquer les variables CSS au contexte de la supérette active et prévoir un fallback statique.
- Le mode RTL reste piloté par la langue active, pas par le thème.
- Aucun upload, stockage ou traitement d'image n'est introduit par cette décision.
- Aucune dépendance, migration ou code Symfony n'est ajouté par cette PR documentaire.
