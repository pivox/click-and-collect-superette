# Mapping issues — V1 Launch Readiness après S13-003

Dernière issue livrée : **S13-003**.

Ce document sert de mapping de travail pour les issues restantes. Il complète `docs/roadmap/v1-launch-readiness-roadmap.md`.

## Légende

- **BLOCKER** : obligatoire avant lancement officiel.
- **SHOULD** : fortement recommandé avant lancement, mais peut être arbitré.
- **POST-LAUNCH** : à faire après lancement.
- **NON-BLOCKING** : utile, mais ne doit pas retarder le lancement.

---

## LR-1 — Catalogue & référentiel

| Issue | Statut lancement | Décision PO / Tech Lead |
|---|---|---|
| #373 — S13-004 Gouvernance référentiel | BLOCKER | À faire en premier. Fixe les règles avant les actions admin puissantes. |
| #391 — S13-005 Images produits web/mobile | BLOCKER | Catalogue plus crédible. Fallback obligatoire si image absente. |
| #444 — S13-006 Traitement rapide admin | SHOULD | Accélère la validation référentiel. À faire avant les actions en masse. |
| #445 — S13-007 Création inline marques/catégories | SHOULD | À faire après S13-006. Ne remplace pas le CRUD complet. |
| #446 — S13-008 Actions en masse admin | SHOULD | À faire après S13-004/S13-006/S13-007. Jamais d'action destructive silencieuse. |

### Ordre recommandé

```text
#373 → #391 → #444 → #445 → #446
```

---

## LR-2 — Préproduction, monétisation, support

Les anciens libellés “bêta” doivent être relus comme **pré-lancement V1**.

### Préproduction technique

| Sujet | Statut lancement | Décision |
|---|---|---|
| Worker async prod | BLOCKER | Les jobs différés doivent tourner avant lancement. |
| Monitoring jobs | BLOCKER | L'ops doit voir la santé worker/file sans SSH. |
| QR imprimable | BLOCKER | Nécessaire pour activation magasin. |
| Checklist activation | BLOCKER | Aucune supérette lancée sans checklist complète. |
| KPI minimum | SHOULD | Utile pour piloter le lancement, pas dashboard BI. |

### Monétisation

| Sujet | Statut lancement | Décision |
|---|---|---|
| Abonnement marchand | BLOCKER | Produit non lançable sans modèle commercial. |
| Phase tarifaire trial/promo/standard | BLOCKER | Ne pas mélanger phase tarifaire et lifecycle paiement. |
| Paiement manuel | BLOCKER | Suffisant V1, pas besoin carte bancaire. |
| Relance paiement | SHOULD | Email/WhatsApp manuel accepté au début. |
| Suspension douce / réactivation | BLOCKER | Ne jamais supprimer catalogue/données. |
| Facture fiscale complète | SHOULD | À cadrer fiscalement avant facture officielle. Reçu interne possible si validé. |

### Support

| Sujet | Statut lancement | Décision |
|---|---|---|
| Incident commande | BLOCKER | Tout incident doit être traçable. |
| Backoffice support | SHOULD | Support sans requêtes DB. |
| Journal marchand / vue santé | SHOULD | Aide au diagnostic et à la rétention. |
| Runbook support | BLOCKER | Même si outillage partiel, le process doit être clair. |

---

## LR-3 — Mobile launch readiness

| Issue | Statut lancement | Décision PO / Tech Lead |
|---|---|---|
| #374 — S14-001 PWA client | BLOCKER | Parcours client mobile installable obligatoire. |
| #375 — S14-002 PWA marchand | BLOCKER | Usage comptoir / retrait QR obligatoire. |
| #379 — S14-006 Accessibilité minimum | BLOCKER | Minimum mobile : contraste, labels, erreurs, boutons. |
| #378 — S14-005 WhatsApp semi-manuel | SHOULD | Très utile marché TN ; plus simple que WhatsApp Business API. |
| #402 — S14-post Server Components i18n | SHOULD | Corriger les textes visibles restants. |
| #403 — S14-post dates localisées | SHOULD | Important pour cohérence FR/AR. |
| #404 — S14-post aria-label notifications | SHOULD | Petit ticket accessibilité/i18n. |
| #376 — S14-003 Push notifications | NON-BLOCKING | À faire si stable. Ne bloque pas si PWA + in-app + WhatsApp sont bons. |

### Ordre recommandé

```text
#374 → #375 → #379 → #378 → #402/#403/#404 → #376
```

---

## LR-4 — Growth léger avant lancement

| Issue | Statut lancement | Décision PO / Tech Lead |
|---|---|---|
| #380 — S15-001 Statistiques marchand avancées | SHOULD, version simple | Avant lancement : stats simples. Avancé après données réelles. |
| #384 — S15-004 Promotions simples | SHOULD | OK si modèle prix stable et peu risqué. |
| #385 — S15-005 CRM léger marchands | SHOULD | Utile pour équipe commerciale et rétention. |
| #382 — S15-002 Packs produits | POST-LAUNCH | Peut attendre, sauf pack manuel très simple. |
| #383 — S15-003 Suggestions de Kadhia | POST-LAUNCH | Besoin d'historique réel, sinon faible valeur. |

### Règle

Sprint 15 ne doit pas bloquer le lancement si LR-1, LR-2 et LR-3 ne sont pas terminés.

---

## LR-5 — Applications natives

| Issue | Statut lancement | Décision PO / Tech Lead |
|---|---|---|
| #386 — S16-001 Android marchand | POST-LAUNCH | Déclencher seulement si limites PWA marchand prouvées. |
| #387 — S16-002 Android client | POST-LAUNCH | Après usage client réel. |
| #388 — S16-003 iOS client | POST-LAUNCH | Après Android client stabilisé et demande iOS confirmée. |
| #389 — S16-004 iOS marchand | POST-LAUNCH | Seulement si besoin confirmé. |

### Gate natif

```text
usage réel + facturation opérationnelle + limites PWA constatées + besoin terrain confirmé
```

---

# Actions à appliquer dans GitHub issues

Pour chaque issue restante, ajouter en haut du body ou en commentaire :

```text
Décision Launch Readiness :
- Phase : LR-x
- Statut lancement : BLOCKER / SHOULD / NON-BLOCKING / POST-LAUNCH
- Raison : ...
```

Ne pas renuméroter toutes les issues. Garder les références S13/S14/S15/S16 pour l'historique, mais la nouvelle lecture produit est LR-1 à LR-5.
