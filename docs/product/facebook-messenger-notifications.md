# Facebook Messenger — canal externe des notifications

## Statut

Cadrage documentaire ajouté en suite de Sprint 15.

Issues associées :

```text
#490 — [S15-post][SPIKE] Facebook Messenger comme canal parallèle aux notifications
#491 — [S15-post-001] Notifications externes — préférences de canal et traces d'envoi
#492 — [S15-post-002] Opt-in Messenger client via page Facebook Click & Collect
#493 — [S15-post-003] Provider Facebook Messenger — envoi best-effort des notifications prioritaires
#494 — [Post-lancement] Option page Facebook marchand pour notifications Messenger
```

Ce document ne remplace pas les notifications existantes.

Décision produit :

```text
Notification in-app = source de vérité
Facebook Messenger = canal externe optionnel, best-effort
```

---

## 1. Contexte

Le projet dispose déjà d'un module de notifications in-app pour les clients et les marchands.

Facebook Messenger est étudié comme canal externe supplémentaire pour les événements importants liés aux commandes, au retrait et, plus tard, à certaines communications marchandes.

Il ne faut pas confondre :

```text
Symfony Messenger = bus async backend déjà utilisé
Facebook Messenger = canal de communication Meta avec le client ou le marchand
```

La logique existante doit rester stable : une erreur Facebook Messenger ne doit jamais bloquer une commande, un changement de statut ou un retrait.

---

## 2. Besoin PO

Le besoin retenu :

```text
Si le client a déjà activé la communication Messenger avec la page,
on ne lui redemande pas l'autorisation à chaque notification.

Si le client n'a pas encore activé Messenger,
on garde le fonctionnement actuel : notification in-app + CTA d'activation Messenger.
```

Nuance produit/technique importante :

```text
Suivre la page Facebook n'est pas suffisant comme règle technique.
La règle exploitable côté backend doit être :
- opt-in Messenger actif ;
- identifiant Messenger / PSID connu pour la page concernée ;
- canal non désactivé par le client ;
- règles Meta applicables respectées.
```

---

## 3. Décision MVP proposée

Pour la première version :

```text
Démarrer avec la page Facebook Click & Collect.
Préparer le modèle pour supporter plus tard la page Facebook du marchand.
```

Raison :

```text
La page Click & Collect est plus simple à maîtriser :
- une seule app Meta ;
- une seule page ;
- un seul token de page ;
- moins de support marchand ;
- meilleur contrôle produit.
```

La page Facebook du marchand est gardée pour plus tard.

Option commerciale possible, uniquement après validation technique et Meta :

```text
+5 DT / mois — Notifications Messenger depuis la page Facebook du marchand
```

Cette option ne doit pas être promise tant que l'issue #494 n'est pas validée.

---

## 4. Position roadmap

Ce cadrage est positionné **en suite de Sprint 15**, comme extension contrôlée des communications externes.

Il ne doit pas bloquer :

```text
- la clôture Sprint 15 ;
- les gaps de monétisation ;
- l'import catalogue minimum ;
- la PWA ;
- le WhatsApp semi-manuel #378 ;
- le push Web #376.
```

Ordre recommandé :

```text
1. #490 — Spike Facebook Messenger
2. #491 — Socle préférences de canal et traces d'envoi
3. #492 — Opt-in Messenger client
4. #493 — Provider Facebook Messenger V1
5. #494 — Page Facebook marchand, post-lancement uniquement
```

---

## 5. Architecture cible

Principe :

```text
NotificationService
    ↓
Notification in-app persistée
    ↓
NotificationExternalDispatcher async
    ↓
FacebookMessengerNotificationProvider
    ↓
Meta Messenger API
```

Règles non négociables :

```text
- La notification in-app est toujours créée.
- Messenger est optionnel.
- L'envoi Messenger est best-effort.
- L'échec Messenger ne bloque jamais la commande.
- Les tentatives d'envoi sont tracées.
- Les secrets Meta ne sont jamais logués.
```

---

## 6. Modèle de données cible

### `notification_channel_subscription`

```text
notification_channel_subscription
- id
- user_id
- channel
- provider
- status
- page_scope
- page_id
- external_recipient_id
- consented_at
- revoked_at
- last_error_code
- last_error_message
- metadata
- created_at
- updated_at
```

Valeurs attendues :

```text
channel = facebook_messenger
provider = meta
page_scope = platform_page | merchant_page
status = pending | active | revoked | failed
```

### `notification_delivery_attempt`

```text
notification_delivery_attempt
- id
- notification_id
- channel
- provider
- status
- provider_message_id
- error_code
- error_message
- attempted_at
- delivered_at
- metadata
```

Valeurs attendues :

```text
status = pending | sent | failed | skipped_no_optin | skipped_policy | retrying
```

---

## 7. Événements Messenger V1

Priorité 1 :

```text
- commande prête ;
- rappel retrait 1h avant créneau ;
- nouvelle commande marchand si le Spike valide le cas.
```

Priorité 2 :

```text
- commande acceptée ;
- commande refusée ;
- commande annulée ;
- retrait finalisé ;
- retard paiement marchand plus tard.
```

Les messages doivent rester transactionnels. Les messages marketing ou sponsorisés sont hors périmètre.

---

## 8. Parcours opt-in

Parcours cible :

```text
Client connecté
→ voit un CTA "Recevoir mes notifications sur Messenger"
→ ouvre Messenger / m.me
→ confirme la conversation
→ webhook ou callback associe le client à son identifiant Messenger
→ les prochaines notifications sont envoyées sans redemander
```

Règle :

```text
Si messenger_subscription.status = active,
ne pas redemander l'autorisation à chaque notification.
```

États visibles côté client :

```text
not_configured
pending
active
revoked
failed
```

---

## 9. Fallback et erreurs

Cas sans opt-in :

```text
- créer la notification in-app ;
- ne pas envoyer Messenger ;
- proposer un CTA d'activation.
```

Cas opt-in actif :

```text
- créer la notification in-app ;
- envoyer Messenger en async ;
- tracer notification_delivery_attempt.
```

Cas erreur Meta :

```text
- garder l'in-app ;
- tracer l'erreur ;
- retry limité si pertinent ;
- ne jamais bloquer la commande.
```

---

## 10. Hors périmètre

```text
- chatbot Messenger complet ;
- prise de commande depuis Messenger ;
- paiement via Messenger ;
- campagnes marketing ;
- publicités Click-to-Messenger ;
- inbox omnicanale ;
- IA conversationnelle ;
- automatisation depuis la page Facebook de chaque marchand en V1 ;
- promesse commerciale +5 DT avant validation technique.
```

---

## 11. Risques

```text
- règles Meta plus strictes que prévu ;
- app review nécessaire ;
- opt-in difficile à relier au compte client ;
- confusion entre follower Facebook et opt-in Messenger exploitable ;
- tokens de page à sécuriser ;
- page marchand trop complexe pour la V1 ;
- messages refusés si perçus comme marketing ;
- support difficile si chaque marchand doit connecter sa page ;
- doublon fonctionnel avec Web Push ou WhatsApp semi-manuel.
```

---

## 12. Documentation à compléter après Spike

Selon la décision de #490, mettre à jour :

```text
docs/architecture/api-contract.md
docs/backend/logging.md
docs/product/user-stories/
docs/Sprint14/README.md ou le fichier sprint actif au moment de l'exécution
```

Ne pas réintroduire `docs/roadmap/mvp-roadmap.md`, supprimé pour éviter deux sources de vérité.
