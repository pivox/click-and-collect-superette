# ADR 0002 — Choix de Next.js 14 pour le frontend

## Statut

Accepté.

## Contexte

Le frontend doit couvrir trois espaces (client, marchand, admin) dans une seule application web responsive.

Contraintes principales :
- Mobile-first PWA pour le parcours client.
- Support arabe RTL obligatoire.
- Connexions 4G Tunisie : performances critiques.
- Équipe réduite : un seul codebase frontend.
- Séparation claire des espaces par rôle.

Trois options ont été évaluées : Next.js 14, Nuxt 3, SvelteKit.

## Décision

Le frontend est implémenté avec **Next.js 14** (App Router, TypeScript, Tailwind CSS).

## Justification

| Critère | Next.js 14 | Nuxt 3 | SvelteKit |
|---|---|---|---|
| Server Components (perf mobile) | ✅ | ✅ | ✅ |
| i18n français/arabe natif | ✅ next-intl | ✅ @nuxtjs/i18n | ⚠️ moins outillé |
| RTL support | ✅ | ✅ | ⚠️ |
| PWA installable | ✅ | ✅ | ✅ |
| Ecosystème / bibliothèques | ✅ Large | ⚠️ Plus petit | ⚠️ Jeune |
| Marché recrutement Tunisie | ✅ React dominant | ⚠️ | ❌ |
| Route groups (client/merchant/admin) | ✅ natif App Router | ✅ | ✅ |

## Structure des routes

```text
apps/frontend/src/app/
├── (client)/      → espace client mobile-first
├── (merchant)/    → backoffice marchand
└── (admin)/       → administration plateforme
```

## Conséquences

- Un seul dépôt frontend pour les trois espaces.
- La séparation des espaces est assurée par les route groups et les middlewares d'authentification.
- Le rendu serveur (SSR) améliore les performances initiales sur mobile 4G.
- `next-intl` gère le basculement français/arabe et le RTL.
- Les futures applications mobiles natives resteront indépendantes de ce dépôt.
