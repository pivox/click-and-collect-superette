# Design — Admin supérettes : activate/deactivate + QR code

**Date** : 2026-05-23
**Scope** : backoffice admin, section Supérettes
**Approche retenue** : A — QR dans le StoreDrawer, raccourci dans la ligne du tableau

---

## Contexte

Le frontend admin expose déjà la liste des supérettes avec création, modification et archivage. Le backend dispose de quatre endpoints non encore câblés :

- `PATCH /api/admin/stores/{id}/activate`
- `PATCH /api/admin/stores/{id}/deactivate`
- `GET /api/admin/stores/{id}/qr-code`
- `POST /api/admin/stores/{id}/regenerate-qr` (POST, pas PATCH)

Ce spec couvre leur intégration complète dans le frontend.

---

## Fichiers touchés

| Fichier | Nature du changement |
|---|---|
| `src/lib/services/admin/stores.service.ts` | +4 fonctions : `activateStore`, `deactivateStore`, `getStoreQrCode`, `regenerateStoreQrCode` |
| `src/lib/types/admin/stores.types.ts` | +1 interface `StoreQrCode` |
| `src/app/admin/superettes/page.tsx` | Toggle switch dans la colonne statut + bouton "QR" dans les actions |
| `src/components/admin/superettes/StoreDrawer.tsx` | Checkbox `isActive` dans le formulaire + section QR dépliable en bas |

Aucun nouveau fichier de page. `AdminConfirmDialog` réutilisé pour la confirmation de régénération.

---

## Feature 1 — Toggle activate / deactivate

### Liste (SuperettesPage)

La colonne "Statut" devient interactive pour les supérettes non archivées.

- **Supérette archivée** : badge gris "Archivée", non cliquable (inchangé).
- **Supérette active** : toggle switch pill vert. Clic → `deactivateStore(id)` → mise à jour optimiste de l'état local.
- **Supérette inactive** : toggle switch pill gris. Clic → `activateStore(id)` → mise à jour optimiste de l'état local.

Pas de dialog de confirmation — l'action est réversible d'un clic.

En cas d'erreur réseau ou backend : le switch revient à son état précédent et un message d'erreur inline s'affiche dans la page (même pattern que les erreurs existantes).

### Drawer (StoreDrawer)

En mode édition uniquement :

- Checkbox `isActive` (libellé "Supérette active") ajoutée dans le formulaire.
- Désactivée (non cliquable) si la supérette est archivée (`archived_at` non null).
- À la sauvegarde, `isActive` est inclus dans le payload `updateStore` via `UpdateStorePayload.isActive?: boolean` déjà supporté par le backend.

---

## Feature 2 — QR code

### Section dans le StoreDrawer

Visible uniquement en mode édition (pas à la création).

**Chargement** : la section est dépliée par défaut et appelle `getStoreQrCode(id)` à l'ouverture du drawer. Un spinner remplace le contenu pendant le chargement.

**Contenu affiché** :
- Image QR générée côté frontend (SVG) à partir du lien de partage — 180×180 px, centrée. Librairie : `react-qr-code` (nouvelle dépendance, SVG-based, ~5 kb).
- Lien de partage : `NEXT_PUBLIC_APP_URL + target_url` (ex. `https://…/api/stores/by-qr/{token}`) — texte tronqué + bouton "Copier".
- Token brut `qr_code_token` — police monospace, petite taille, bouton "Copier".
- Bouton "Régénérer le QR".

**Régénération** :
1. Clic sur "Régénérer le QR".
2. `AdminConfirmDialog` : titre "Régénérer le QR ?", message "L'ancien QR imprimé ne fonctionnera plus.", bouton "Régénérer" (variante warning).
3. Confirmation → `regenerateStoreQrCode(id)` → rechargement de la section QR uniquement (pas du formulaire entier).
4. Erreur → message inline dans la section QR, drawer reste ouvert.

### Raccourci dans la liste (SuperettesPage)

Un bouton "QR" ajouté dans la colonne actions de chaque ligne (à côté de "Modifier" et "Archiver").

Comportement : ouvre le StoreDrawer en mode édition sur la supérette concernée. La section QR est visible en scrollant — pas de scroll automatique forcé.

---

## Types ajoutés

```typescript
// stores.types.ts
// Correspond exactement à AdminStoreQrOutput côté backend
export interface StoreQrCode {
  store_id: string;
  store_name: string;
  slug: string;
  qr_code_token: string;
  target_url: string;   // chemin relatif : /api/stores/by-qr/{token}
}
// Le QR image est généré côté frontend via react-qr-code depuis NEXT_PUBLIC_APP_URL + target_url
```

---

## Fonctions de service ajoutées

```typescript
// stores.service.ts
activateStore(id: string): Promise<void>       // PATCH /api/admin/stores/{id}/activate
deactivateStore(id: string): Promise<void>     // PATCH /api/admin/stores/{id}/deactivate
getStoreQrCode(id: string): Promise<StoreQrCode>          // GET  /api/admin/stores/{id}/qr-code
regenerateStoreQrCode(id: string): Promise<StoreQrCode>   // POST /api/admin/stores/{id}/regenerate-qr
```

## Dépendances ajoutées

- `react-qr-code` — génération SVG du QR côté client, aucune dépendance native, compatible Next.js SSR.

---

## Contraintes et risques

- `target_url` retournée par le backend est `/api/stores/by-qr/{token}` (chemin relatif). La variable d'environnement `NEXT_PUBLIC_APP_URL` doit être définie pour composer l'URL absolue encodée dans le QR et affichée comme lien de partage.
- Le backend ne retourne pas d'image — la génération du QR est entièrement côté client via `react-qr-code`. Si `NEXT_PUBLIC_APP_URL` n'est pas définie, l'URL de partage sera incomplète.
- Le toggle optimiste peut temporairement afficher un état incorrect si le réseau est lent et que l'utilisateur navigue rapidement. Risque faible en usage admin.
- La régénération du QR invalide immédiatement l'ancien token : tout QR imprimé devient obsolète. La confirmation simple est suffisante pour ce contexte admin.
- Si `archived_at` est non null, les actions activate/deactivate et la checkbox `isActive` sont désactivées — une supérette archivée ne peut pas être réactivée sans désarchivage (endpoint non couvert dans ce spec).
