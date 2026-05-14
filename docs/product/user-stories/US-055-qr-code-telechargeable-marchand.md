# US-055 — Télécharger le QR code de sa supérette (marchand)

**Epic** : EPIC-001 — Onboarding par QR code
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Must Have

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
5. Un fichier haute résolution est téléchargé (300 DPI minimum pour impression).
6. Le PDF inclut : QR code, nom supérette, adresse, mention « Scannez pour commander ».

---

## Formats disponibles

| Format | Usage | Dimensions |
|---|---|---|
| PNG (haute résolution) | Impression rapide, usage numérique | 2000×2000 px |
| PDF A4 | Impression professionnelle, affichage vitrine | A4, marges incluses |

---

## Règles métier

- Le marchand ne peut télécharger que le QR code de sa propre supérette.
- Le QR code encode l'URL du parcours client : `https://app.superette.tn/qr/{qrCodeToken}`.
- Le format PDF inclut le nom et l'adresse de la supérette (données publiques uniquement).
- Si le `qrCodeToken` est régénéré par l'admin, le marchand doit télécharger un nouveau QR code.

---

## Critères d'acceptation

- [ ] Le marchand peut voir et télécharger le QR code depuis ses paramètres.
- [ ] Le PNG est en haute résolution (2000×2000 px minimum).
- [ ] Le PDF A4 est prêt à imprimer avec le nom et l'adresse de la supérette.
- [ ] Un message indique la date de dernière génération du token.
- [ ] Si le token est régénéré par l'admin, le marchand voit un avertissement « Vos anciens QR codes imprimés ne sont plus valides ».

---

## Notes techniques

**Endpoints :**
```http
GET /api/merchant/stores/{storeId}/qr.png
GET /api/merchant/stores/{storeId}/qr.pdf
```

Réponse headers :
```
Content-Type: image/png
Content-Disposition: attachment; filename="qr-superette-ezzahra.png"
```

**Génération :**
- PNG : bibliothèque PHP `endroid/qr-code` avec logo supérette en overlay si disponible.
- PDF : `dompdf` ou `mpdf` avec template HTML/CSS incluant le QR en base64.

**QR code content :** `https://app.superette.tn/qr/{qrCodeToken}`

**Sécurité :** `MerchantShopAccessChecker` sur les deux endpoints.
