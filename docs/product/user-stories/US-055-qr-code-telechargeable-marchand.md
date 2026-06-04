# US-055 — Télécharger le QR code de sa supérette (marchand)

**Epic** : EPIC-001 — Onboarding par QR code
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Must Have

**État** : livré Sprint 10 via #355.

---

## Récit

En tant que **marchand**,
je veux **télécharger le QR code de ma supérette en haute résolution**,
afin de **l'imprimer et le coller à l'entrée de mon magasin sans dépendre de l'administrateur**.

---

## Préconditions

- Le marchand est connecté et propriétaire de la supérette.

---

## Scénario nominal

1. Le marchand accède aux paramètres de sa supérette.
2. Il voit la section « QR code d'accès ».
3. Il voit un aperçu du QR code avec le nom de la supérette en dessous.
4. Il clique sur « Télécharger en PNG » ou « Télécharger en PDF ».
5. Un fichier PNG ou PDF est téléchargé depuis l'API marchand.
6. Le PDF inclut : nom de la supérette, QR code, URL cible et mention d'usage.

---

## Formats disponibles

| Format | Usage | Dimensions |
|---|---|---|
| PNG | Impression rapide, usage numérique | QR généré par le backend |
| PDF A4 | Impression professionnelle, affichage vitrine | A4, marges incluses |

---

## Règles métier

- Le marchand ne peut télécharger que le QR code de sa propre supérette.
- Le QR code encode l'URL du parcours client : `{FRONTEND_URL}/stores/by-qr/{qrCodeToken}`.
- Le format PDF inclut le nom de la supérette et l'URL cible.
- Si le `qrCodeToken` est régénéré par l'admin, le marchand doit télécharger un nouveau QR code.

---

## Critères d'acceptation

- [x] Le marchand peut voir et télécharger le QR code depuis ses paramètres.
- [x] Le PNG est généré côté backend et téléchargé depuis l'espace marchand.
- [x] Le PDF est généré côté backend et téléchargé depuis l'espace marchand.
- [ ] Un message indique la date de dernière génération du token.
- [ ] Si le token est régénéré par l'admin, le marchand voit un avertissement « Vos anciens QR codes imprimés ne sont plus valides ».

---

## Notes techniques

**Endpoints :**
```http
GET /api/merchant/stores/{storeId}/qr-code
GET /api/merchant/stores/{storeId}/qr-code.png
GET /api/merchant/stores/{storeId}/qr-code.pdf
```

Réponse headers :
```
Content-Type: image/png
Content-Disposition: attachment; filename="qr-superette-ezzahra.png"
```

**Génération :**
- PNG : service backend `QrCodePngGenerator`.
- PDF : service backend `MerchantStoreQrPdfGenerator` avec QR encodé en image.

**QR code content :** `{FRONTEND_URL}/stores/by-qr/{qrCodeToken}`

**Sécurité :** `MerchantShopAccessChecker` sur les deux endpoints.
