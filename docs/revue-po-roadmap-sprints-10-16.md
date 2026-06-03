# Revue PO — Roadmap Sprints 10→16

Revue faite, manette en main et code à l'appui. Verdict d'abord, puis le détail.

---

## Verdict PO

La roadmap est **solide sur la structure** (phasage bêta → business → exploitation → scale → mobile → natif) et la logique « ne plus empiler des features mais rendre le produit exploitable/vendable » est la bonne. C'est une roadmap de mise sur le marché, pas une roadmap MVP — ça, c'est juste.

**Mais** elle s'appuie sur une photo de l'état du produit qui s'arrête au Sprint 5/7. Or le code est aujourd'hui au-delà du **Sprint 9** (PRs jusqu'à #346 sur `main`). Plusieurs « risques à traiter » sont déjà résolus, et plusieurs briques supposées « à construire » existent déjà. Résultat : Sprint 10 et Sprint 13 sont surdimensionnés, et il manque trois angles morts produit sérieux.

---

## 1. Corrections factuelles (roadmap vs code réel)

| Affirmation roadmap | Réalité dans le repo | Impact |
|---|---|---|
| « Messenger `sync://` = risque majeur prod » (10.1) | **Faux / périmé.** `messenger.yaml` utilise `doctrine://default?auto_setup=0` avec `use_notify`, `retry_strategy` (max_retries 3, backoff x2) et `failure_transport: failed`. Résolu en S7-009. | Sprint 10.1 n'est plus « mettre en place un worker », c'est **vérifier + écrire le fichier Supervisor** (introuvable dans le repo) + ajouter monitoring/alerting. ~70 % du point est déjà fait. |
| « enrichissement IA existe en partie » (13.3) | **Vrai et même plus que ça** : `ProductAiEnrichmentRunner`, `…OpenAiClient`, `…Planner`, `…PayloadFactory`, `…ResultApplier` sont déjà en place. | 13.3 est du câblage UI sur une infra existante, pas un chantier IA. À requalifier. |
| Socle « catalogue marchand » non listé | **Sprint 8 livré** (#200–#204) : `MerchantLocalProduct`, **bulk create multi-format**, `ProductFamily`, `pack_quantity`, merge proposition→référence. | Sprint 13 « catalogue scalable » démarre **bien plus haut** que ce que la roadmap suppose. Le bulk multi-format existe déjà. |
| Slug unique « à faire » (10.3) | **Confirmé manquant** : `Shop::$slug` est un `string` nu, aucune contrainte UNIQUE. | 10.3 valide, garder. |
| QR imprimable (10.2) | **Confirmé** : backend QR présent (`AdminStoreQrOutput`, providers, régénération token), pas de génération PNG/PDF. | 10.2 valide, garder. |
| Abonnement / facture / incident | **Confirmé greenfield** : aucune entité `Subscription`, `Invoice`, `Payment`, `Incident`. | Sprints 11/12 sont bien du neuf. |

**Action PO** : réécrire la section « 0. Socle déjà livré » jusqu'au Sprint 9 + correctifs (#220–#231) avant de présenter cette roadmap. Sinon on planifie du travail déjà payé.

---

## 2. Angles morts produit (les vrais sujets)

### 🔴 Arabe / RTL — absent de toute la roadmap
`AI_CONTEXT.md` pose **FR + AR avec RTL** comme exigence produit, pas comme option. L'i18n AR câblé est « reporté post-Sprint 7 »… et n'apparaît **dans aucun** des Sprints 10→16. Pour un marché tunisien grand public côté client, c'est non négociable. Il doit atterrir **au plus tard en Sprint 14**, et la question « bêta terrain FR-only acceptable ? » doit être tranchée **avant le Sprint 10**.

### 🔴 Le canal de relance arrive après les relances
Sprint 11.4 définit un échéancier de dunning (J-7 → J+21) et Sprint 11.5 la suspension. Mais l'email transactionnel (14.4) et WhatsApp (14.5) n'arrivent qu'au **Sprint 14**. Une relance de paiement en notification in-app uniquement = un marchand qui ne la voit jamais → suspension subie → churn. **Le dunning sans canal sortant fiable ne marche pas.** Il faut remonter au moins les emails de facturation/relance dans le Sprint 11.

### 🟠 Conformité fiscale facture (Tunisie)
Sprint 11.2 modélise une facture « simple » (numéro, période, montant). En Tunisie une facture a des obligations (matricule fiscal, TVA, mentions légales, timbre). Soit on assume « reçu interne non fiscal » explicitement, soit on cadre la conformité. À trancher — ça change le modèle de données.

### 🟠 Instrumentation / métriques : trop tard
Sprint 16 dit « avant le natif, prouver que les clients commandent ». Mais l'analytics usage n'arrive qu'au Sprint 15 (stats marchand). **On ne peut pas décider d'une bêta (sortie Sprint 10) ni d'un go natif sans mesurer dès le Sprint 10.** Il faut un socle d'événements/KPI minimal dès la bêta (commandes complétées, taux d'activation supérette, taux d'acceptation), sinon chaque « sortie de sprint » est déclarée au doigt mouillé.

---

## 3. Séquencement — deux tensions

1. **Catalogue scalable (13) après monétisation (11).** Le levier qui fait *convertir* un essai gratuit en payant, c'est la rapidité d'onboarding catalogue (import CSV, scan code-barres). Le mettre après la facturation = on facture des marchands frustrés par la saisie. **Recommandation : remonter 13.1 (import CSV/modèle) + 13.2 (scan code-barres) dans/avant le Sprint 11**, garder l'import photo IA et la déduplication plus tard.

2. **Support (12) après facturation (11).** On aura des marchands payants et des incidents terrain *avant* d'avoir l'outillage support. Au minimum, un process manuel + le journal opérationnel marchand (12.3) devraient exister dès la bêta (Sprint 10).

---

## 4. Note de modélisation (Sprint 11)

Les statuts d'abonnement mélangent **cycle de vie** et **phase tarifaire** : `trial` / `promo` / `active` décrivent le prix, `payment_due` / `grace_period` / `suspended` / `cancelled` décrivent l'état de paiement. Sépare-les en deux axes :
- **lifecycle** : `active` / `payment_due` / `grace_period` / `suspended` / `cancelled`
- **plan/phase tarifaire** : `trial` → `promo` → `standard` (avec dates et `currentPrice`/`nextPrice`)

Sinon tu ne pourras pas représenter « en promo ET en retard de paiement ». Cohérent aussi avec la règle du repo « séparer référentiel et offre » : un état ≠ un prix.

Question business à valider : saut **10 → 50 DT (x5)** — est-ce testé, ou faut-il un palier intermédiaire ?

---

## 5. Ce que je garde tel quel (validé)

- Phasage global et la décision finale (bêta → business → exploitation → scale → mobile → natif).
- 10.2 (QR PNG/PDF), 10.3 (slug unique), 10.4 (checklist activation) — tous justes et confirmés par le code.
- Ordre natif « Android marchand d'abord » — bon réflexe terrain.
- Suspension douce (11.5) — excellente règle produit, à garder telle quelle.

⚠️ Une réserve sur 14.3 (push) : le push PWA **iOS** n'existe que sur Safari 16.4+ et uniquement si la PWA est ajoutée à l'écran d'accueil. Pour un marchand, ça peut justifier l'app native iOS plus tôt que le Sprint 16. À documenter comme limite connue.

---

## Reformulation que je proposerais

- **Sprint 10** → renommer « Durcissement bêta + observabilité » : *vérifier* le worker Doctrine déjà en place + Supervisor + **socle métriques/KPI** + QR imprimable + slug unique + checklist + journal opérationnel minimal. **Trancher la question FR-only vs AR pour la bêta.**
- **Sprint 11** → abonnement/facturation **+ emails de facturation/relance** (canal sortant indispensable au dunning) + **import CSV/scan code-barres** (levier d'activation) + cadrage fiscal facture.
- **Sprint 13** → recentré sur import photo IA (infra déjà là) + déduplication + score qualité.
- **Sprint 14** → PWA + push + **i18n AR/RTL** (dette MVP) + WhatsApp + WCAG min.

---

**Vérifications effectuées** : lecture `messenger.yaml` + `.env`, `Shop::$slug`, présence des services `ProductAiEnrichment*`, absence d'entités `Subscription/Invoice/Incident/Payment`, `git log` (état `main` ≈ #346).
**Hypothèses** : « pas de paiement en ligne MVP » concerne le paiement *client de la commande*, pas l'abonnement *plateforme marchand* — donc Sprint 11 ne viole pas la règle MVP (à confirmer explicitement quand même).
**Risques/next steps** : transformer cette revue en (1) section « Socle livré » corrigée jusqu'au Sprint 9, et (2) issues GitHub par sprint avec les re-priorisations ci-dessus.
