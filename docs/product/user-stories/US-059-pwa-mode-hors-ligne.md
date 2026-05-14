# US-059 — PWA installable et mode hors ligne

**Epic** : EPIC-012 — Expérience client mobile
**Sprint** : Sprint 7 — Production et localisation
**Priorité** : Should Have

---

## Récit

En tant que **client**,
je veux **installer l'application sur mon téléphone et continuer à composer ma Kadhia même sans connexion**,
afin de **ne pas perdre mon panier si le réseau est instable dans la supérette**.

---

## Préconditions

- Le client accède à l'application via un navigateur mobile compatible PWA (Chrome Android, Safari iOS 16.4+).
- La supérette a été visitée au moins une fois avec connexion (données mises en cache).

---

## Scénario nominal — Installation

1. Le client visite la vitrine de la supérette.
2. Le navigateur affiche une invite « Ajouter à l'écran d'accueil » (ou le client la déclenche manuellement).
3. L'application s'installe sans passer par un store.
4. L'icône de la supérette (ou de la plateforme) apparaît sur l'écran d'accueil.
5. Au prochain lancement, l'application s'ouvre en mode standalone (sans barre d'URL).

---

## Scénario nominal — Composition hors ligne

1. Le client a déjà chargé le catalogue d'une supérette avec connexion.
2. Il perd la connexion dans la supérette.
3. Il peut toujours consulter le catalogue mis en cache.
4. Il peut modifier les quantités et ajouter des produits à sa Kadhia (stockée en `localStorage` / IndexedDB).
5. Lorsque la connexion est rétablie, la Kadhia locale est synchronisée avec le serveur automatiquement.

---

## Scénario alternatif — Produit non mis en cache

1. Le client tente d'accéder à un produit qui n'a pas été mis en cache.
2. Un message s'affiche : « Ce produit n'est pas disponible hors ligne. Reconnectez-vous pour voir le catalogue complet. »

---

## Scénario alternatif — Conflit de synchronisation

1. Le client modifie sa Kadhia hors ligne.
2. Entre-temps, un produit de sa Kadhia est passé en rupture de stock.
3. À la synchronisation, le serveur retourne `PRODUCT_UNAVAILABLE` pour ce produit.
4. L'application affiche : « Un produit a été retiré de votre Kadhia car il n'est plus disponible. »

---

## Règles métier

- Le mode hors ligne ne permet pas de soumettre une commande (la soumission nécessite une connexion).
- Le cache du catalogue est valide 30 minutes maximum — au-delà, une connexion est requise pour rafraîchir.
- La Kadhia locale est toujours prioritaire sur la Kadhia serveur en cas de conflit hors ligne (merge strategy : l'utilisateur décide en cas de divergence importante).
- L'installation PWA est optionnelle — l'application fonctionne normalement en mode navigateur.

---

## Critères d'acceptation

- [ ] L'application affiche une invite d'installation sur mobile.
- [ ] L'application se lance en mode standalone après installation.
- [ ] Le catalogue est accessible hors ligne si préalablement chargé avec connexion.
- [ ] La Kadhia est modifiable hors ligne et synchronisée à la reconnexion.
- [ ] Un message clair indique l'état hors ligne.
- [ ] La soumission de commande est bloquée hors ligne avec un message explicite.
- [ ] L'icône et le nom de la plateforme sont corrects dans le manifest.

---

## Notes techniques

**Fichiers frontend à créer :**

```
/public/manifest.json          — app name, icons, theme_color, display: standalone
/public/sw.js                  — service worker (ou généré via next-pwa / workbox)
```

**Manifest minimal :**
```json
{
  "name": "Click & Collect Supérette",
  "short_name": "Supérette",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#1a6e3c",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

**Stratégies de cache (Workbox) :**
- Pages shell (layout, navigation) : `CacheFirst` avec mise à jour en arrière-plan.
- Catalogue produits : `StaleWhileRevalidate` — affiche le cache, rafraîchit en fond.
- Images produits : `CacheFirst` avec expiration 7 jours.
- API `/api/stores/{id}/theme` : `NetworkFirst` — 30s timeout puis cache.

**Stockage Kadhia hors ligne :** IndexedDB via `idb` ou `localforage`. Clé : `kadhia-{storeId}`.

**Synchronisation à la reconnexion :**
```typescript
// hook useNetworkStatus — event 'online' → appel PATCH /api/me/kadhias/{id}/sync
```

**Bibliothèque recommandée :** `next-pwa` (wrapper Workbox pour Next.js 14 App Router).

**Pas de backend à modifier** — la PWA est entièrement côté frontend.
