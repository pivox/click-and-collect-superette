# Spike — Facebook Messenger comme canal parallèle aux notifications (S15-013)

> Issue #490 · Spike réalisé le 30 septembre 2026 (recherche sur l'état réel de
> la Messenger Platform de Meta à cette date). Décision de base inchangée :
> notification in-app = source de vérité ; tout canal externe = best-effort.

## Verdict : NO-GO pour le MVP — reporter #491–#494

Le mécanisme sur lequel reposait tout le cas d'usage (notifications
transactionnelles hors fenêtre de 24 h via les *message tags*) **a été retiré
par Meta en 2026**. Construire #491–#494 aujourd'hui reviendrait à bâtir sur un
socle supprimé. Le besoin « canal externe » est couvert par le push natif
(#620, livré) et WhatsApp semi-manuel (#378, livré).

## Réponses aux 9 questions du spike

**1. Peut-on envoyer des notifications transactionnelles Messenger pour les
événements Click & Collect ?**
Plus de façon fiable. Les *message tags* `POST_PURCHASE_UPDATE`,
`CONFIRMED_EVENT_UPDATE` et `ACCOUNT_UPDATE` — exactement nos cas (commande
acceptée/prête, rappel de retrait) — sont rejetés par l'API (erreur 100)
depuis le **27 avril 2026**. Il ne reste que la fenêtre standard de 24 h après
le dernier message de l'utilisateur : inutilisable pour « commande prête » ou
« rappel retrait », qui tombent souvent bien après la dernière interaction.

**2. Quelles règles Meta s'appliquent ?**
- Fenêtre 24 h : seule base pérenne ; réponse possible (promo incluse) dans les
  24 h suivant un message entrant de l'utilisateur.
- Tags : supprimés (cf. Q1).
- Recurring Notifications : dépréciées le 07/01/2026, **coupées mondialement le
  10/02/2026 sauf Australie, UE, Japon, Corée du Sud et Royaume-Uni — la
  Tunisie n'en fait pas partie**.
- One-Time Notifications : de fait obsolètes.
- Remplaçants Meta : *Marketing Messages* (promotionnel, opt-in dédié) et
  *Utility Messages* (non-promotionnel, annoncés mais sans disponibilité
  régionale ni documentation stable communiquées à date).
- Toujours requis : page Facebook, app Meta, *business verification*, app
  review (`pages_messaging` + `pages_read_engagement`), token de page, webhook.

**3. Page Click & Collect ou page du marchand ?**
Si le canal redevient viable : démarrer avec la **page Click & Collect
centrale** (une seule app review, un seul webhook, PSID par page donc un seul
opt-in mutualisé), le modèle de données gardant `page_id` pour supporter plus
tard la page du marchand (#494). C'était la proposition PO — elle reste la
bonne, mais elle est suspendue au retour d'un mécanisme d'envoi autorisé.

**4. Comment stocker l'opt-in sans redemander la permission ?**
Modèle cible (à ne créer que si GO futur) : table `messenger_optins`
(user, page_id, psid, opted_in_at, revoked_at, source) alimentée par le
webhook `messaging_optins`/référence `m.me` ; la règle exploitable est bien
« opt-in actif + PSID connu pour la page + canal non désactivé », jamais le
simple fait de suivre la page.

**5. Comment tracer les tentatives d'envoi ?**
Comme le push natif (#620) : envoi via le bus Messenger (Symfony) en
best-effort post-flush, corrélé à `notification_id`, journalisé (canal Monolog
`notification`), échecs définitifs marquant l'opt-in `revoked`.

**6. Événements prioritaires ?**
Les mêmes que push V1 (`docs/mobile/push-and-links.md` §5) : acceptée /
partiellement acceptée / refusée / prête / rappel retrait côté client ;
nouvelle commande / annulée côté marchand.

**7. Si Messenger échoue ?**
Rien ne change : l'in-app reste la source de vérité, aucun blocage métier,
retry borné puis abandon journalisé (même politique que #620).

**8. Endpoints et écrans nécessaires (si GO futur) ?**
Webhook `POST /api/webhooks/messenger` (vérification signature), CTA d'opt-in
(lien `m.me/<page>?ref=<user>`), préférence de canal dans le profil client et
marchand, écran admin de suivi des opt-ins. Couvert par #491–#493 — **à ne pas
ouvrir tant que les Utility Messages ne sont pas disponibles pour la Tunisie**.

**9. Documentation à mettre à jour ?**
Ce rapport ; `AI_CONTEXT.md` (statut Messenger : reporté, motif Meta) ;
`docs/mobile/push-and-links.md` inchangé (le push natif reste le canal V1).

## Conditions de réouverture

Rouvrir la piste si et seulement si : (a) Meta documente les **Utility
Messages** avec une disponibilité couvrant la Tunisie, (b) le coût éventuel par
message est acceptable pour le modèle supérette, (c) l'app review
`pages_messaging` est obtenue sur la page Click & Collect. Réévaluation
suggérée : T1 2027.

## Sources (consultées le 30/09/2026)

- Changelog / Send API Messenger Platform (developers.facebook.com) — retrait
  des tags au 27/04/2026, fenêtre 24 h.
- Annonces relayées : dépréciation Recurring Notifications (07/01/2026) et
  coupure mondiale hors AU/UE/JP/KR/UK (10/02/2026) ; remplacement par
  Marketing Messages / Utility Messages (disponibilité non communiquée).
- Exigences app review : `pages_messaging`, business verification, page
  Facebook requise, PSID par page.
