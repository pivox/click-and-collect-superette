# Rapport E2E live — validation chef de projet du 30 septembre 2026 (v2)

> Seconde passe E2E de la journée, après le merge de l'intégralité des épics
> ORDER-LEAD (#569), MERCHANT-TEAM (#568), PRODUCT-IMAGE (#580), de S14-005
> WhatsApp (#378) et du cadrage MOBILE (#561). Navigateur réel (Playwright)
> sur l'API réelle (`NEXT_PUBLIC_USE_MOCKS=0`), base dev migrée
> (`Version20260930090000` → `160000`), backfill organisations + audit OK.
> Complète `docs/qa/e2e-live-report-2026-09-30.md` (passe v1, parcours cœur #0005).

## Suites automatisées (préalable, sur `main`)

| Suite | Résultat |
|---|---|
| Backend `tests/Unit` | **262 tests / 824 assertions — 0 échec** |
| Backend `tests/Functional` (hors `ApiDocsExposureTest`) | **1503 tests / 7959 assertions — 0 échec** |
| Backend `ApiDocsExposureTest` (isolé, gotcha mémoire) | **2 tests / 6 assertions — 0 échec** |
| Frontend vitest | **722 tests / 111 fichiers — 0 échec** |
| PHPStan (niveau 6) / CS Fixer / lint:container | 0 erreur / 0 diff / OK |

## Scénarios navigateur validés

### 1. Délai minimal avant retrait (ORDER-LEAD, #569)
- Marchand : `/merchant/parametres/delai-retrait` → préréglage « 2 heures » → « Réglage enregistré ». ✅
- API publique : `booking_policy {minimum_pickup_lead_time_minutes: 120, earliest_bookable_at: 18:24+01:00}` exposé, créneaux du jour filtrés. ✅
- Client : écran créneaux → « Cette supérette demande au moins 2 h pour préparer une commande » + état vide expliqué pour Aujourd'hui ; créneaux de Demain proposés. ✅
- Remis à 0 en fin de session (état seed neutre).

### 2. Écran Équipe (MERCHANT-TEAM, #568)
- Invitation d'un compte secondaire → « Invitation en attente », quota 2/10. ✅
- Échec d'envoi d'email signalé sans casser la création (best-effort, pas de SMTP en dev). ✅
- Correctifs de review vérifiés en réel : bouton contextuel **« Annuler l'invitation »** (pas « Révoquer l'accès ») et **focus porté sur l'alertdialog** à l'ouverture, avec titre/avertissement spécifiques. ✅
- Annulation confirmée → l'invitation disparaît de la liste. ✅

### 3. Photo produit local (PRODUCT-IMAGE-003, #583)
- Création d'un produit local « Fromage artisanal E2E » (8,500 TND) depuis le catalogue marchand. ✅
- Bloc photo : règles de prise de vue affichées, aperçu local (`blob:`) avant envoi, input `accept=jpeg/png/webp` + `capture=environment`. ✅
- **Deux anomalies détectées et corrigées en session** (voir §Anomalies).
- Après correctifs : photo affichée dans le drawer marchand (variante 400 webp chargée) **et** dans le catalogue client (fallback JPEG du pipeline responsive #391). ✅

### 4. WhatsApp semi-manuel (S14-005, #378)
- Boutons présents : « Contacter la supérette sur WhatsApp » (client, suivi commande) et « Contacter le client sur WhatsApp » (marchand, détail commande). ✅
- Téléphone de supérette absent → **409 `ORDER_WHATSAPP_SHOP_PHONE_MISSING`** propre (contrat d'erreur stable). ✅
- Après renseignement du téléphone : lien `wa.me/21620123456` (normalisation 216 ✅) avec message contextualisé « … commande #0006 chez Supérette El Amen. Retrait prévu le 01/10/2026 entre 10:00 et 11:00. » ✅
- Trace `order.whatsapp_contact_prepared` présente dans `admin_audit_logs`. ✅

### 5. Parcours cœur complet (commande #0006)
QR supérette → catalogue → recherche → Kadhia (1 article) → note marchand →
créneau (délai 2 h respecté : Demain 10:00–11:00) → soumission → marchand :
accepter → préparation **ligne par ligne** (« Commande prête » verrouillé tant
que toutes les lignes ne sont pas cochées) → Prête → client : QR + code 4
chiffres (2418) affichés → marchand : scan token → « Remettre la Kadhia »
(confirmation marchand) → client : « J'ai récupéré ma Kadhia » → **Récupérée**.

Historique en base (avec **auteur des transitions**, MERCHANT-TEAM-005) :

```text
submitted(customer) → accepted(merchant) → preparing(merchant)
→ ready(merchant) → pickup_pending(merchant) → completed(customer)
```

La double validation est restée obligatoire de bout en bout. ✅

## Anomalies détectées pendant la passe

| # | Anomalie | Nature | Résolution |
|---|---|---|---|
| 1 | Upload photo marchand → **500** `mkdir(): Permission denied` sur `public/uploads/products` | **Environnement local** : le volume monté n'avait pas de répertoire `uploads` accessible en écriture au worker PHP | `mkdir -p` + droits d'écriture côté hôte ; à documenter dans le setup dev (et vérifier les droits du volume en production) |
| 2 | Photo affichée en **404** (`:3000/uploads/...`) après upload | **Bug frontend** (#583) : `MerchantLocalProductPhotoSection` rendait `card_url` relative sans `mediaUrl()` | **PR #624 mergée** (hotfix + tests verrouillant l'URL absolue) |

Observations mineures (non bloquantes) :
- La recherche du catalogue client ne se déclenche pas sur des événements DOM
  synthétiques quand on vise la barre globale de recherche de supérettes —
  confusion de champ pendant le test, pas un bug produit (le bon champ
  « Rechercher un produit » fonctionne).
- Le seed ne renseigne pas `shops.phone` : le 409 WhatsApp est donc l'état
  par défaut en dev. Renseigné manuellement pour la passe (+216 20 123 456).
- Comme en v1 : les clics souris CDP ne déclenchent pas certains handlers React
  — `element.click()` via `browser_evaluate` reste la méthode fiable.

## Verdict chef de projet

**GO.** Le cœur MVP reste vert de bout en bout après la journée de merges
(11 PRs de code + 6 PRs de docs), les quatre nouveautés du jour (délai minimal,
multi-comptes/Équipe, photo produit local, WhatsApp) fonctionnent en conditions
réelles, et les deux anomalies détectées sont résolues (1 env local documentée,
1 hotfix mergé #624). Reste à planifier : issues backend du cadrage mobile
(#616, #617, #618, #620, #622) et décisions humaines DH-1→DH-10
(`docs/mobile/release-strategy.md`).
