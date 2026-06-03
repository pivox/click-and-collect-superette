# Roadmap produit — Click & Collect Supérette Tunisie

> Roadmap **post-Sprint 9** (v2, intégrant la revue PO). Le cœur MVP (parcours commande,
> QR retrait, double validation, créneaux, admin, référentiel, catalogue marchand, Kadhia multi)
> est **déjà livré** sur `main`. Cette roadmap couvre la mise sur le marché : pré-commercial,
> production, monétisation, support, mobile, croissance.

## Vision

- **Client** : choisir une supérette, préparer sa Kadhia, choisir un créneau, retirer.
- **Marchand** : gérer commandes, catalogue, créneaux, retraits.
- **Admin** : gérer marchands, supérettes, référentiel, incidents, facturation.
- **Plateforme** : monétiser via abonnement marchand.

## Phasage

| Phase | Sprint | Objectif |
|-------|--------|----------|
| 1 — Bêta terrain | **Sprint 10** | Bêta fiable avec 3–5 supérettes |
| 2 — Business | **Sprint 11** | Monétisation (abonnement, facturation, relance) |
| 3 — Exploitation | **Sprint 12** | Gérer les problèmes terrain |
| 4 — Catalogue scalable | **Sprint 13** | Onboarding produit rapide |
| 5 — Mobile PWA | **Sprint 14** | Vraie expérience mobile + arabe |
| 6 — Croissance | **Sprint 15** | Augmenter usage et revenus |
| 7 — Natif | **Sprint 16** | Industrialiser après preuve terrain |

---

## Sprint 10 — Durcissement bêta + observabilité

**Objectif :** rendre l'application fiable pour une bêta avec quelques supérettes réelles.

| Issue | Item |
|-------|------|
| [#352](https://github.com/pivox/click-and-collect-superette/issues/352) | Valider le worker async existant en production (Supervisor, runbook) |
| [#353](https://github.com/pivox/click-and-collect-superette/issues/353) | Monitoring des jobs asynchrones (santé worker + file) |
| [#354](https://github.com/pivox/click-and-collect-superette/issues/354) | Métriques bêta — instrumentation des KPI terrain |
| [#355](https://github.com/pivox/click-and-collect-superette/issues/355) | QR magasin imprimable (PNG / PDF) |
| [#356](https://github.com/pivox/click-and-collect-superette/issues/356) | Checklist d'activation supérette |
| [#357](https://github.com/pivox/click-and-collect-superette/issues/357) | Journal opérationnel marchand (vue minimale) |
| [#358](https://github.com/pivox/click-and-collect-superette/issues/358) | Décision produit : bêta FR-only vs FR+AR |

> 💡 Le transport Messenger `sync://` n'est **plus** un risque (résolu S7-009 : `doctrine://`
> persistant + retry + failure transport). #352 = **validation** prod, pas recréation.

---

## Sprint 11 — Activation commerciale + abonnement

**Objectif :** transformer l'application en produit monétisable.
**Modèle commercial :** 3 mois gratuits → 3 mois à 10 DT/mois → 50 DT/mois (saut ×5 à valider).

| Issue | Item |
|-------|------|
| [#359](https://github.com/pivox/click-and-collect-superette/issues/359) | Module abonnement marchand |
| [#360](https://github.com/pivox/click-and-collect-superette/issues/360) | Statuts : séparer lifecycle et phase tarifaire |
| [#361](https://github.com/pivox/click-and-collect-superette/issues/361) | Reçu / facture mensuelle (à cadrer fiscalement) |
| [#362](https://github.com/pivox/click-and-collect-superette/issues/362) | Paiement manuel (espèces / virement) + validation admin |
| [#363](https://github.com/pivox/click-and-collect-superette/issues/363) | Relances paiement (email + WhatsApp manuel) — **incl. infra email** |
| [#364](https://github.com/pivox/click-and-collect-superette/issues/364) | Suspension douce et réactivation |
| [#365](https://github.com/pivox/click-and-collect-superette/issues/365) | Import CSV + scan code-barres (onboarding catalogue minimum) |

> ⚠️ **Point dur** (#363) : l'email actuel = `@mail()` natif, `symfony/mailer` non installé.
> Le dunning email implique de monter cette infra → coût assumé dans ce sprint.
> #365 est **remonté** du Sprint 13 : c'est le levier de conversion essai → payant.

---

## Sprint 12 — Support & exploitation terrain

**Objectif :** donner à l'admin les outils pour gérer les problèmes réels.

| Issue | Item |
|-------|------|
| [#366](https://github.com/pivox/click-and-collect-superette/issues/366) | Incidents commande (module structuré) |
| [#367](https://github.com/pivox/click-and-collect-superette/issues/367) | Backoffice support (consultation / traitement) |
| [#368](https://github.com/pivox/click-and-collect-superette/issues/368) | Journal opérationnel marchand complet + vue santé |
| [#369](https://github.com/pivox/click-and-collect-superette/issues/369) | Process manuel d'exploitation terrain (runbook) |

---

## Sprint 13 — Catalogue intelligent & qualité

**Objectif :** accélérer l'onboarding produit (au-delà du CSV/scan déjà livré en S11).
*Socle déjà présent : bulk multi-format (S8), infra IA `ProductAiEnrichment*`, merge proposition→référence (PR #203).*

| Issue | Item |
|-------|------|
| [#370](https://github.com/pivox/click-and-collect-superette/issues/370) | Import catalogue par photo assisté IA |
| [#371](https://github.com/pivox/click-and-collect-superette/issues/371) | Déduplication du référentiel (workflow admin) |
| [#372](https://github.com/pivox/click-and-collect-superette/issues/372) | Score de qualité des références produit |
| [#373](https://github.com/pivox/click-and-collect-superette/issues/373) | Gouvernance du référentiel (rôles, workflow, droits) |

---

## Sprint 14 — PWA + arabe/RTL + notifications

**Objectif :** transformer le web responsive en vraie expérience mobile installable.
*PWA (US-059), WCAG (US-060) et i18n AR (US-008) étaient reportés post-Sprint 7.*

| Issue | Item |
|-------|------|
| [#374](https://github.com/pivox/click-and-collect-superette/issues/374) | PWA client (installable, mobile-first) |
| [#375](https://github.com/pivox/click-and-collect-superette/issues/375) | PWA marchand (installable, terrain) |
| [#376](https://github.com/pivox/click-and-collect-superette/issues/376) | Push notifications (client + marchand) |
| [#377](https://github.com/pivox/click-and-collect-superette/issues/377) | **Arabe / RTL câblé** (dette MVP) |
| [#378](https://github.com/pivox/click-and-collect-superette/issues/378) | WhatsApp semi-manuel (client + marchand) |
| [#379](https://github.com/pivox/click-and-collect-superette/issues/379) | Accessibilité minimum (WCAG de base) |

> ⚠️ Limite connue (#376) : Web Push **iOS** seulement sur Safari 16.4+ et PWA installée
> → peut justifier l'iOS natif plus tôt (Sprint 16).

---

## Sprint 15 — Croissance commerciale

**Objectif :** augmenter usage, rétention et valeur pour les marchands.

| Issue | Item |
|-------|------|
| [#380](https://github.com/pivox/click-and-collect-superette/issues/380) | Statistiques marchand avancées |
| [#382](https://github.com/pivox/click-and-collect-superette/issues/382) | Packs produits |
| [#383](https://github.com/pivox/click-and-collect-superette/issues/383) | Suggestions de Kadhia (souvent achetés / récents / favoris) |
| [#384](https://github.com/pivox/click-and-collect-superette/issues/384) | Promotions simples (prix barré, échéance) |
| [#385](https://github.com/pivox/click-and-collect-superette/issues/385) | Suivi commercial (CRM léger des marchands) |

---

## Sprint 16 — Apps natives

**Objectif :** industrialiser **après preuve terrain** (clients commandent, marchands utilisent,
catalogue gérable, facturation OK, limites PWA constatées). Ordre : Android marchand → Android
client → iOS client → iOS marchand (conditionnel).

| Issue | Item |
|-------|------|
| [#386](https://github.com/pivox/click-and-collect-superette/issues/386) | App native Android marchand |
| [#387](https://github.com/pivox/click-and-collect-superette/issues/387) | App native Android client |
| [#388](https://github.com/pivox/click-and-collect-superette/issues/388) | App native iOS client |
| [#389](https://github.com/pivox/click-and-collect-superette/issues/389) | App native iOS marchand (si besoin confirmé) |

---

## Points d'attention transverses (revue PO)

1. **Email = chantier réel** (S11/#363) : `symfony/mailer` à installer ; sans canal sortant, le
   dunning est inefficace.
2. **Sprint 10 dense** (7 items) : la décision FR/AR (#358) est un arbitrage, pas un build.
3. **Slug unique supérette** : non repris dans la liste v2 mais reste une dette (`Shop::$slug`
   sans contrainte UNIQUE) — à arbitrer pour la bêta.
4. **Pricing 10→50 DT (×5)** : non validé, question business ouverte (#360).
5. **Fiscalité facture** (#361) : prérequis bloquant du modèle de données (matricule, TVA, timbre).
6. **Métriques dès la bêta** (#354) : indispensables pour décider des phases suivantes (dont natif).
