# US-076 — Reçu / facture mensuelle marchand

**Epic** : EPIC-017 — Abonnement & monétisation  
**Sprint** : Sprint 11 — Activation commerciale + abonnement  
**Priorité** : Must  
**Statut** : Cadrage produit / technique, à valider fiscalement avant modèle de données définitif

---

## Récit

En tant que **marchand**,
je veux **recevoir chaque mois un document clair pour mon abonnement plateforme**,
afin de **comprendre la période facturée, le montant à payer ou payé, et disposer d'une trace pour ma comptabilité**.

---

## Position MVP

US-076 concerne l'abonnement marchand à la plateforme. Elle ne concerne pas le paiement de la Kadhia par le client.

Le MVP doit d'abord produire un cadrage fiable du document mensuel avant d'implémenter une entité `Invoice`. Les règles fiscales tunisiennes doivent être confirmées par un comptable ou conseiller fiscal avant toute génération de facture officielle.

Tant que cette validation n'existe pas, le produit peut parler de **document mensuel d'abonnement** ou de **reçu / facture à valider fiscalement**, mais ne doit pas prétendre générer une facture fiscale conforme.

---

## Préconditions fonctionnelles

- Un marchand existe et possède au moins une supérette.
- US-074 définit une `Subscription` rattachée au marchand.
- US-075 sépare :
  - la phase tarifaire : `trial`, `promo`, `standard` ;
  - le cycle de vie : `active`, `payment_due`, `grace_period`, `suspended`, `cancelled`.
- US-077 définit l'enregistrement d'un paiement manuel par espèces ou virement.
- Aucun paiement en ligne n'est ajouté.

---

## Scénario nominal cible

1. Le système identifie une période mensuelle d'abonnement à facturer.
2. Il calcule le prix de la période selon la phase tarifaire de la `Subscription`.
3. Il prépare un document mensuel avec numéro lisible, période, marchand, montants et taxes applicables.
4. Le marchand consulte le document dans son espace abonnement.
5. Le marchand paie manuellement, hors application.
6. L'admin enregistre et valide le paiement manuel.
7. Le document passe à `paid` si le montant encaissé couvre le montant dû.
8. La `Subscription` reste ou repasse dans un cycle de vie compatible avec l'usage de la plateforme.

---

## Champs nécessaires au document mensuel

### Identification plateforme

| Champ | Description | Statut |
|---|---|---|
| `platform_legal_name` | Raison sociale ou nom légal de l'émetteur | Obligatoire à valider |
| `platform_commercial_name` | Nom commercial affiché | Recommandé |
| `platform_tax_identifier` | Matricule fiscal de la plateforme | Obligatoire si applicable |
| `platform_address` | Adresse fiscale / siège | Obligatoire à valider |
| `platform_phone` | Contact plateforme | Recommandé |
| `platform_email` | Email de contact facturation | Recommandé |

### Identification marchand

| Champ | Description | Statut |
|---|---|---|
| `merchant_id` | Identifiant interne du marchand | Technique |
| `merchant_legal_name` | Raison sociale ou nom du marchand facturé | Obligatoire |
| `merchant_tax_identifier` | Matricule fiscal marchand, si applicable | À valider selon profil marchand |
| `merchant_address` | Adresse du marchand ou de la supérette facturée | Obligatoire à valider |
| `merchant_email` | Email de réception du document | Recommandé |
| `shop_id` | Supérette concernée si facturation par supérette | Décision ouverte |
| `shop_name` | Nom lisible de la supérette | Recommandé si `shop_id` présent |

### Période et abonnement

| Champ | Description | Statut |
|---|---|---|
| `subscription_id` | Abonnement concerné | Obligatoire |
| `billing_period_start` | Début de période facturée | Obligatoire |
| `billing_period_end` | Fin de période facturée | Obligatoire |
| `billing_month` | Mois lisible, par exemple `2026-06` | Recommandé |
| `pricing_phase` | `trial`, `promo` ou `standard` | Obligatoire |
| `plan_label` | Libellé lisible du plan | Recommandé |

### Numérotation et statut

| Champ | Description | Statut |
|---|---|---|
| `document_number` | Numéro lisible, unique et séquentiel selon la règle fiscale retenue | Obligatoire à valider |
| `document_type` | `invoice`, `receipt`, `monthly_statement` ou autre type validé | Décision bloquante |
| `issued_at` | Date d'émission | Obligatoire |
| `due_at` | Date limite de paiement | Obligatoire si facture à payer |
| `status` | Statut du document | Obligatoire |
| `cancelled_at` | Date d'annulation éventuelle | Recommandé |
| `cancellation_reason` | Motif d'annulation | Recommandé |

Statuts préliminaires, à valider :

- `draft` : préparé mais non émis ;
- `issued` : émis au marchand ;
- `payment_pending` : paiement attendu ;
- `partially_paid` : paiement manuel partiel validé ;
- `paid` : paiement validé intégralement ;
- `overdue` : échéance dépassée ;
- `cancelled` : document annulé.

### Montants

Tous les montants sont en TND et doivent conserver la précision en millimes.

| Champ | Description | Statut |
|---|---|---|
| `currency` | Devise, `TND` | Obligatoire |
| `amount_ht` | Montant hors TVA / base hors taxes | Obligatoire |
| `vat_rate` | Taux TVA appliqué, si applicable | À valider fiscalement |
| `vat_amount` | Montant TVA, si applicable | À valider fiscalement |
| `stamp_duty_amount` | Timbre fiscal, si applicable | À valider fiscalement |
| `amount_ttc` | Montant toutes taxes comprises avant paiement | Obligatoire |
| `amount_paid` | Montant encaissé et validé | Obligatoire si paiement lié |
| `amount_due` | Reste à payer | Obligatoire |

### Ligne de document

Le MVP peut démarrer avec une seule ligne mensuelle d'abonnement.

| Champ | Description | Exemple |
|---|---|---|
| `description` | Libellé lisible du service | `Abonnement plateforme — phase promo` |
| `period_start` | Début de période de la ligne | `2026-06-01` |
| `period_end` | Fin de période de la ligne | `2026-06-30` |
| `quantity` | Quantité | `1` |
| `unit_price_ht` | Prix unitaire hors TVA / base hors taxes | `10.000` |
| `line_amount_ht` | Total ligne hors TVA / base hors taxes | `10.000` |
| `line_vat_amount` | TVA ligne si applicable | À valider |
| `line_amount_ttc` | Total ligne TTC | À valider |

---

## Modèle de données préliminaire, à valider fiscalement

Ce modèle sert de base de discussion technique. Il ne doit pas être implémenté comme entité `Invoice` avant validation fiscale.

### `BillingDocument`

Document mensuel rattaché à une `Subscription`.

Champs pressentis :

- `id`
- `subscription_id`
- `merchant_id`
- `shop_id` nullable, selon décision facturation par marchand ou par supérette
- `document_type`
- `document_number`
- `status`
- `currency`
- `billing_period_start`
- `billing_period_end`
- `issued_at`
- `due_at`
- `platform_legal_name`
- `platform_tax_identifier`
- `platform_address`
- `merchant_legal_name`
- `merchant_tax_identifier` nullable
- `merchant_address`
- `amount_ht`
- `vat_rate` nullable
- `vat_amount` nullable
- `stamp_duty_amount` nullable
- `amount_ttc`
- `amount_paid`
- `amount_due`
- `cancelled_at` nullable
- `cancellation_reason` nullable
- `created_at`
- `updated_at`

### `BillingDocumentLine`

Ligne de détail du document. Une seule ligne suffit pour le MVP, mais la table permet de garder une structure explicite.

Champs pressentis :

- `id`
- `billing_document_id`
- `description`
- `period_start`
- `period_end`
- `quantity`
- `unit_price_ht`
- `amount_ht`
- `vat_rate` nullable
- `vat_amount` nullable
- `amount_ttc`

### `ManualPayment`

Paiement manuel saisi et validé par l'admin dans US-077.

Champs pressentis :

- `id`
- `billing_document_id`
- `merchant_id`
- `method` : `cash` ou `bank_transfer`
- `amount`
- `currency`
- `paid_at`
- `validated_at`
- `validated_by_admin_id`
- `reference` nullable, par exemple référence de virement
- `note` nullable
- `status` : `pending_validation`, `validated`, `rejected`, `cancelled`
- `created_at`
- `updated_at`

---

## Décisions à prendre avant modèle de données

1. **Nature du document** : facture officielle, reçu après paiement, état mensuel, proforma ou combinaison facture + reçu ?
2. **Émetteur fiscal** : la plateforme émet-elle directement la facture au marchand, avec sa propre identité fiscale ?
3. **Assujettissement TVA de la plateforme** : la prestation d'abonnement est-elle soumise à TVA, et à quel taux ?
4. **Timbre fiscal** : le timbre s'applique-t-il à ce document, à ce mode d'encaissement et à ce format ?
5. **Facture électronique** : la plateforme doit-elle passer par un système agréé ou produire un format électronique spécifique ?
6. **Numérotation** : série unique globale, série annuelle, série par établissement, préfixe autorisé, gestion des trous et annulations ?
7. **Marchands sans matricule fiscal** : peuvent-ils recevoir un document sans matricule ? Quel libellé et quelles données minimales ?
8. **Facturation par marchand ou par supérette** : un marchand multi-supérettes reçoit-il une facture globale ou une facture par supérette ?
9. **Période gratuite** : faut-il émettre un document à 0 TND pendant `trial` ou seulement démarrer à la phase payante ?
10. **Avoirs et corrections** : comment traiter une erreur sur un document déjà émis ?
11. **Paiement partiel** : est-il autorisé dans le MVP ou doit-il être refusé côté admin ?
12. **Arrondis** : règle d'arrondi TND / millimes entre HT, TVA, timbre et TTC.
13. **Archivage** : durée de conservation des documents, paiements et justificatifs.
14. **Libellés bilingues** : faut-il produire le document en français seulement, arabe seulement ou bilingue FR/AR ?

---

## Questions bloquantes fiscalité / comptabilité

- Quel est le régime fiscal exact de l'entité plateforme en Tunisie ?
- La prestation SaaS / abonnement plateforme marchand est-elle assujettie à TVA ?
- Si TVA applicable, quel taux doit être utilisé pour cette prestation ?
- Le timbre fiscal est-il obligatoire pour une facture d'abonnement plateforme payée en espèces ? par virement ? dans les deux cas ?
- Une facture mensuelle doit-elle être émise même si le marchand n'a pas encore payé ?
- Un reçu distinct doit-il être émis après validation du paiement manuel ?
- La facture doit-elle inclure le matricule fiscal du marchand dans tous les cas, ou seulement pour certains profils ?
- Quelle règle de numérotation est acceptable : préfixe `INV-YYYY-000001`, série continue globale, série par année ?
- Comment annuler ou corriger un document déjà émis : annulation simple, avoir, note de crédit ?
- L'obligation de facture électronique s'applique-t-elle à la plateforme dès le lancement bêta ?
- Le document doit-il être signé, cacheté, transmis ou conservé dans un format spécifique ?
- Le document doit-il mentionner une retenue à la source, une exonération ou une mention spécifique si le marchand est sous un régime particulier ?

---

## Articulation avec `Subscription`

`Subscription` est la source du cycle commercial :

- marchand concerné ;
- phase tarifaire courante ;
- prix mensuel attendu ;
- prochaine période à facturer ;
- cycle de vie opérationnel.

US-076 ne décide pas à elle seule si le marchand peut utiliser la plateforme. Elle produit le document mensuel et son statut comptable. Le statut opérationnel reste porté par `Subscription`.

Exemple :

- `Subscription.lifecycle = active`
- `Subscription.pricing_phase = promo`
- document mensuel de juin émis à 10 TND, statut `payment_pending`
- après échéance dépassée : document `overdue`, puis `Subscription.lifecycle = grace_period`
- si le retard dépasse la règle US-079 : `Subscription.lifecycle = suspended`

---

## Articulation avec paiement manuel

US-077 doit créer un paiement manuel rattaché au document US-076.

Règle MVP proposée :

- le marchand paie hors application ;
- l'admin saisit le paiement avec méthode, montant, date, référence éventuelle et note ;
- le paiement démarre en `pending_validation` si un workflow de contrôle est nécessaire, ou directement en `validated` si l'admin saisisseur fait foi ;
- un paiement validé met à jour `amount_paid` et `amount_due` ;
- si `amount_due = 0`, le document passe à `paid` ;
- si la `Subscription` était en `payment_due` ou `grace_period`, elle peut repasser à `active` ;
- si elle était `suspended`, US-079 décide les règles de réactivation.

Aucun bouton de paiement en ligne, aucune intégration PSP et aucun paiement client de Kadhia ne sont ajoutés.

---

## Critères d'acceptation de cadrage

- [ ] Les champs nécessaires sont listés pour l'identité plateforme, marchand, période, montants, TVA, timbre fiscal, statut et numéro lisible.
- [ ] Les décisions fiscales bloquantes sont identifiées avant toute entité `Invoice`.
- [ ] Le modèle de données préliminaire est marqué comme à valider fiscalement.
- [ ] L'articulation avec `Subscription` et paiement manuel est claire.
- [ ] Le périmètre exclut explicitement le paiement en ligne.

---

## Notes de prudence

Les sources publiques du Ministère des Finances rappellent notamment que les assujettis TVA doivent utiliser des factures numérotées dans une série ininterrompue, mentionner l'identification fiscale, la désignation du service, le prix hors TVA, les taux et montants de TVA si applicable.

Ces éléments confirment les champs à cadrer, mais ne suffisent pas à choisir le régime exact de la plateforme, le taux applicable, le timbre fiscal ni l'obligation éventuelle de facturation électronique.

Sources consultées :

- https://www.finances.gov.tn/fr/node/75
- https://www.finances.gov.tn/fr/node/952
- https://www.finances.gov.tn/fr/apercu-general-sur-la-fiscalite
