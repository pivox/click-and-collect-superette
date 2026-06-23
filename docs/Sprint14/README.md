# Sprint 14 — Roadmap active de lancement

Date de cadrage initial : 2026-06-07
Dernière révision documentaire : 2026-06-23
Rôles de cadrage : PO + Tech Lead  
Statut : roadmap active à partir du Sprint 14.

## 1. Décision PO / Tech Lead

À partir de Sprint 14, la roadmap ne vise plus une bêta publique fragile.

Intention active :

```text
Préparer une V1 de lancement officiel complète, mobile-first, monétisable et exploitable.
```

Règles :

- ne pas renommer les sprints historiques 10 à 13 ;
- ne pas replanifier une issue déjà livrée ;
- distinguer livré, actif avant lancement, reporté après lancement,
  conditionnel et à décider ;
- garder Facebook Messenger optionnel tant qu'aucune décision PO explicite ne
  le rend bloquant ;
- garder les apps natives derrière une gate terrain.

## 2. Hiérarchie documentaire

- Point d'entrée projet : [docs/project/source-of-truth.md](../project/source-of-truth.md).
- Roadmap active : ce document.
- Synthèse stratégique : [docs/roadmap/launch-readiness-reorganization.md](../roadmap/launch-readiness-reorganization.md).
- Audit fonctionnel : [docs/product/mvp-functional-audit.md](../product/mvp-functional-audit.md).
- Contrat API : [docs/architecture/api-contract.md](../architecture/api-contract.md).

`docs/product/mvp-roadmap.md` reste un index court. Ne pas recréer
`docs/roadmap/mvp-roadmap.md`.

## 3. Découpage actif

```text
Sprint 14 — Mobile Launch Readiness
Sprint 15 — Monétisation, support et exploitation avant lancement
Sprint 16 — Valeur commerciale minimale avant lancement
Conditionnel — Canaux externes Messenger / WhatsApp
Post-lancement — Apps natives et growth avancée
```

---

# Sprint 14 — Mobile Launch Readiness

## Objectif

Transformer l'expérience web responsive en expérience mobile installable et
utilisable en conditions terrain.

## Acquis livrés

| Issue | Sujet | Preuve documentaire / technique |
|---|---|---|
| #374 | PWA client | Fermée GitHub `completed`; `manifest.webmanifest`, `next-pwa`, service worker partagé. |
| #375 | PWA marchand | Fermée GitHub `completed`; `merchant-manifest.webmanifest`, scope marchand, service worker partagé. |
| #376 | Push notifications | Fermée GitHub `completed`; ressources API `CustomerPushSubscriptionResource`, `MerchantPushSubscriptionResource`, `PushSubscriptionApiTest`, toggle frontend. |
| #377 | Arabe / RTL câblé | Déjà acquis Sprint 14 ; ne pas replanifier. |
| #379 | Accessibilité minimum | Fermée GitHub `completed`; corrections front ciblées constatées, sans prétendre à un audit WCAG complet. |

## Actif avant lancement

| Issue | Sujet | Position |
|---|---|---|
| #378 | WhatsApp semi-manuel client + marchand | Ouverte ; fallback utile terrain, non bloquant si notifications in-app + Web Push couvrent les cas minimum. |
| #527 | Suivi global prioritaire | Ouverte ; issue mère de gouvernance et stabilisation. |
| #543 | Sécurisation `FRONTEND_URL` | Ouverte P2 ; fiabilisation QR magasin et liens Kadhia, non bloquante pour les PR MVP urgentes. |

## Reporté après lancement

| Issue | Sujet | Raison |
|---|---|---|
| #386 | App native Android marchand | Gate terrain : seulement si limites PWA constatées. |
| #387 | App native Android client | Gate terrain. |
| #388 | App native iOS client | Gate terrain et limites iOS/PWA à mesurer. |
| #389 | App native iOS marchand | Gate terrain. |

## Critère de sortie

```text
Le client et le marchand peuvent installer la PWA, commander, traiter une commande,
suivre le retrait et utiliser un canal de contact terrain si nécessaire.
```

---

# Sprint 15 — Monétisation, support et exploitation avant lancement

## Objectif

Lancer officiellement sans perdre le contrôle opérationnel, commercial et
onboarding marchand.

## Acquis livrés

| Issue | Sujet | Preuve documentaire / technique |
|---|---|---|
| #359 | Module abonnement marchand | Fondation abonnement existante. |
| #360 | Statuts abonnement lifecycle / phase tarifaire | `SubscriptionLifecycle`, `SubscriptionPricingPhase`. |
| #361 | Document mensuel interne non fiscal | Fermée GitHub `completed`; décision produit acceptée le 2026-06-05, `BillingDocument`, endpoints admin/marchand et tests. |
| #362 | Paiement manuel espèces / virement | Paiements admin, consultation marchand/admin, tests. |
| #363 | Relances paiement email + WhatsApp manuel | Relances et traces présentes côté billing. |
| #364 | Suspension douce et réactivation | Fermée GitHub `completed`; blocage soumission sur marchand suspendu, réactivation par paiement, tests. |
| #365 | Import CSV + code-barres | Fermée GitHub `completed`; import CSV backend/front et recherche exacte code-barres. Scan caméra non retenu comme socle API. |
| #366 | Incidents commande | Fondation support acquise. |
| #367 | Backoffice support | Fondation support acquise. |
| #368 | Journal opérationnel marchand complet + vue santé | Fondation ops acquise. |
| #369 | Runbook support terrain | Documentation support acquise. |
| #420 | Écran santé jobs async | Admin ops acquis. |
| #421 | Écran métriques pré-lancement | Admin métriques acquis. |
| #422 | Détail checklist activation supérette | Admin activation acquis. |
| #482 | Feedback contextualisé | Présent comme module de retour terrain ; stabilisations liées #527 déjà suivies. |

## Actif avant lancement

| Sujet | Position |
|---|---|
| Validation terrain billing/support | Vérifier en environnement de démo que documents, paiements manuels, relances, suspension et réactivation sont compris par l'équipe opérationnelle. |
| Nettoyage priorités #527 | Garder uniquement les tâches réellement importantes et lier les issues enfants. |

## Hors périmètre

- Paiement en ligne client.
- Paiement carte marchand.
- Facture fiscale tunisienne complète sans validation comptable.
- Support omnicanal avancé.
- Rebuild des fondations billing/support/ops déjà livrées.

## Critère de sortie

```text
Les fondations billing, paiement manuel, suspension/réactivation, support,
ops et onboarding catalogue sont disponibles et validées en usage terrain.
```

---

# Sprint 16 — Valeur commerciale minimale avant lancement

## Objectif

Donner au marchand et à l'équipe commerciale assez de valeur pour justifier le
lancement, sans modules avancés dépendant de données réelles.

## Acquis livrés

| Issue | Sujet | Preuve documentaire / technique |
|---|---|---|
| #380 | Statistiques marchand | Fermée GitHub `completed`; endpoint `/api/merchant/stores/{storeId}/statistics`, front marchand, tests backend. |
| #382 | Packs produits | Fermée GitHub `completed`; code présent sur `main` (`9f914ff`, correctifs #463), `ProductPack`, endpoints API Platform marchand/client, ajout de pack à la Kadhia et `MerchantProductPackApiTest`. |
| #383 | Suggestions de Kadhia | Fermée GitHub `completed`; PR #484 fusionnée, `KadhiaSuggestionService`, suggestions magasin, favoris, remplacements indisponibles et tests backend/front. |
| #384 | Promotions simples | Fermée GitHub `completed`; champs promotion catalogue, affichage client/marchand, snapshot prix, suivi admin lecture seule extrait #479. |
| #385 | CRM léger marchand | Fermée GitHub `completed`; profils CRM, historique contacts, filtres admin, tests backend/front. |

## Critère de sortie

```text
Le marchand dispose de statistiques, packs, suggestions, promotions simples et
CRM léger pour augmenter la valeur visible avant lancement.
```

---

# Conditionnel — Canaux externes

## WhatsApp semi-manuel

#378 reste ouvert et pré-lancement possible. Il ne doit pas devenir une API
WhatsApp Business automatisée dans le MVP. Le minimum attendu est un lien
contextualisé et traçable.

## Facebook Messenger

| Issue | Sujet | Position |
|---|---|---|
| #490 | Spike Facebook Messenger | Ouverte ; optionnel, à décider après règles Meta. |
| #491 | Préférences de canal et traces d'envoi | Ouverte ; dépend du spike. |
| #492 | Opt-in Messenger client | Ouverte ; dépend du socle préférences. |
| #493 | Provider Facebook Messenger | Ouverte ; best-effort uniquement si validé. |
| #494 | Page Facebook marchand | Ouverte ; post-lancement / option commerciale future. |

Règle : notification in-app = source de vérité. Facebook Messenger = canal
externe optionnel et best-effort, jamais bloquant pour commande ou retrait sans
décision PO explicite.

---

# Gates CTO avant lancement officiel

## Gate mobile

- PWA client installable.
- PWA marchand installable.
- Parcours commande mobile OK.
- Parcours retrait mobile OK.
- Web Push ou notifications in-app opérationnelles selon support navigateur.
- WhatsApp fallback décidé : livré ou explicitement non bloquant.
- Accessibilité minimum vérifiée sur les écrans clés, sans prétendre à une
  conformité WCAG complète.

## Gate business

- Abonnement marchand existant.
- Phase tarifaire claire.
- Document mensuel interne non fiscal disponible.
- Paiement manuel disponible.
- Import catalogue minimum disponible.
- Suspension douce et réactivation possibles.
- Statistiques, packs produits, suggestions de Kadhia, promotions et CRM léger
  disponibles.

## Gate support

- Incident commande traçable.
- Journal marchand consultable.
- Runbook support disponible.
- Vue santé marchand disponible.
- Santé jobs async, métriques et checklist activation visibles dans l'admin.
- Feedback contextualisé utilisable ou explicitement reporté.

## Gate go / no-go

```text
Aucun bug bloquant sur commande.
Aucun bug bloquant sur retrait.
Aucun bug bloquant sur activation supérette.
Aucun bug bloquant sur import catalogue minimum.
Aucun bug bloquant sur abonnement/paiement manuel.
Aucun risque opérationnel non couvert par runbook.
```
