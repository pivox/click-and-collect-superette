# Roadmap V1 — Launch Readiness sans bêta publique

Dernière décision : ne pas lancer de bêta publique.

Dernière issue considérée livrée : **S13-003 — Score de qualité des références produit**.

Cette roadmap remplace la logique “bêta terrain” par une logique **V1 de lancement** : produit complet, préproduction contrôlée, puis lancement officiel.

## Décision PO + Tech Lead

### Position PO

L'objectif n'est plus de tester une bêta fragile avec des marchands. L'objectif est de lancer une V1 directement exploitable, monétisable et crédible.

La V1 doit être :

- installable sur mobile via PWA ;
- exploitable par l'équipe support ;
- monétisable par abonnement marchand ;
- administrable sans intervention en base ;
- solide sur le référentiel produit ;
- suffisamment différenciante côté catalogue.

### Position Tech Lead

On évite un tunnel infini. Le natif et les modules avancés restent hors pré-lancement tant que la PWA et la V1 web ne sont pas validées.

Les développements restants doivent être découpés par gates :

1. catalogue et référentiel ;
2. préproduction technique ;
3. monétisation ;
4. support ;
5. mobile PWA ;
6. growth léger ;
7. lancement ;
8. natif après lancement uniquement.

---

## Nouvelle séquence des sprints restants

## LR-1 — Finalisation catalogue & référentiel

Sprint source : **Sprint 13 restant**.

Issues concernées :

- #373 — S13-004 Gouvernance du référentiel ;
- #391 — S13-005 Images produits web/mobile ;
- #444 — S13-006 UX traitement rapide admin ;
- #445 — S13-007 Création inline marques/catégories ;
- #446 — S13-008 Actions en masse et file de priorisation.

### Objectif

Fermer le risque catalogue avant lancement.

Un marchand doit pouvoir importer et enrichir son catalogue rapidement. L'admin doit pouvoir maintenir le référentiel sans travailler ligne par ligne.

### Priorité

1. #373 — Gouvernance référentiel.
2. #391 — Images produits.
3. #444 — Traitement rapide admin.
4. #445 — Création inline marques/catégories.
5. #446 — Actions en masse.

### Gate de sortie

- Les règles de gouvernance sont documentées.
- Les images produits sont servies proprement avec fallback.
- L'admin peut corriger vite les références.
- Les actions en masse sont explicites, confirmées et traçables.
- Aucune validation IA ou fusion automatique non maîtrisée.

---

## LR-2 — Préproduction technique, monétisation et support

Sprints sources : **Sprint 10**, **Sprint 11**, **Sprint 12**.

Ces sprints ne doivent plus être cadrés comme “bêta terrain”. Ils deviennent les blocs de **pré-lancement V1**.

### Objectif

Le produit doit pouvoir tourner en production, être facturé et être supporté.

### Sous-blocs

#### Préproduction technique

- worker async actif et supervisé ;
- monitoring jobs ;
- QR magasin imprimable ;
- checklist d'activation supérette ;
- KPI minimum de lancement.

#### Monétisation

- abonnement marchand ;
- phase gratuite / promo / standard ;
- paiement manuel ;
- relances ;
- suspension douce ;
- réactivation.

#### Support

- incidents commande ;
- backoffice support ;
- journal opérationnel marchand ;
- vue santé ;
- runbook support.

### Gate de sortie

- Une supérette peut être activée par checklist.
- Un marchand peut être facturé ou relancé.
- Un incident peut être enregistré, suivi et clôturé.
- L'équipe peut diagnostiquer sans requête directe en base.

---

## LR-3 — Mobile launch readiness

Sprint source : **Sprint 14** + tickets S14-post.

Issues concernées :

- #374 — S14-001 PWA client ;
- #375 — S14-002 PWA marchand ;
- #376 — S14-003 Push notifications ;
- #378 — S14-005 WhatsApp semi-manuel ;
- #379 — S14-006 Accessibilité minimum ;
- #402 — S14-post Server Components i18n ;
- #403 — S14-post dates localisées ;
- #404 — S14-post aria-label notifications.

### Objectif

Rendre l'application utilisable comme une app mobile installable pour client et marchand.

### Priorité

1. #374 — PWA client.
2. #375 — PWA marchand.
3. #379 — Accessibilité minimum.
4. #378 — WhatsApp semi-manuel.
5. #402, #403, #404 — Finitions i18n/accessibilité visibles.
6. #376 — Push notifications.

### Décision PO

Le push est utile, mais il ne bloque pas le lancement si :

- PWA client OK ;
- PWA marchand OK ;
- notifications in-app OK ;
- WhatsApp semi-manuel OK ;
- accessibilité minimum OK.

### Gate de sortie

- La PWA client est installable et le parcours commande fonctionne.
- La PWA marchand est installable et le retrait reste fiable.
- Les erreurs réseau ne donnent pas de page blanche.
- Les textes visibles FR/AR ne sont pas incohérents.
- Les boutons, labels et messages d'erreur sont utilisables sur mobile.

---

## LR-4 — Growth léger avant lancement

Sprint source : **Sprint 15**.

Issues concernées :

- #380 — S15-001 Statistiques marchand avancées ;
- #382 — S15-002 Packs produits ;
- #383 — S15-003 Suggestions de Kadhia ;
- #384 — S15-004 Promotions simples ;
- #385 — S15-005 CRM léger marchands.

### Décision PO

Sprint 15 est validé, mais il est scindé.

### À faire avant lancement

- #380 en version simple : commandes, CA estimé, top produits si données disponibles, annulations ;
- #384 promotions simples uniquement si le modèle reste stable ;
- #385 CRM léger pour suivre les marchands.

### À repousser après lancement

- #382 packs produits complexes ;
- #383 suggestions intelligentes ;
- analytics avancées nécessitant beaucoup d'historique ;
- automatisations commerciales avancées.

### Gate de sortie

- Le marchand voit une valeur minimale dans son dashboard.
- L'équipe commerciale peut suivre les marchands.
- Aucun module growth ne retarde le lancement si le coeur commande/catalogue/paiement/support n'est pas terminé.

---

## LR-5 — Natif post-lancement uniquement

Sprint source : **Sprint 16**.

Issues concernées :

- #386 — S16-001 Android marchand ;
- #387 — S16-002 Android client ;
- #388 — S16-003 iOS client ;
- #389 — S16-004 iOS marchand.

### Décision CTO

Sprint 16 ne fait pas partie du pré-lancement.

Il est déclenché seulement après :

- usage réel marchand ;
- usage réel client ;
- limites PWA constatées ;
- facturation opérationnelle ;
- besoin terrain confirmé.

### Ordre si déclenché

1. Android marchand.
2. Android client.
3. iOS client.
4. iOS marchand seulement si besoin confirmé.

---

# Gates CTO avant lancement officiel

## Gate technique

- Worker async actif.
- Monitoring jobs disponible.
- Logs exploitables.
- Healthcheck OK.
- Aucune file critique bloquée.

## Gate catalogue

- Référentiel gouverné.
- Images produits disponibles ou fallback propre.
- Admin capable de corriger rapidement.
- Import catalogue utilisable.

## Gate marchand

- Supérette activable par checklist.
- QR imprimable.
- Horaires et créneaux configurés.
- Catalogue minimum prêt.
- Commande test passée.
- Retrait test validé.

## Gate business

- Abonnement marchand créé.
- Phase tarifaire claire.
- Paiement manuel enregistrable.
- Relance possible.
- Suspension douce possible.
- Réactivation possible.

## Gate support

- Incident traçable.
- Journal marchand consultable.
- Vue santé disponible.
- Runbook support disponible.

## Gate mobile

- PWA client installable.
- PWA marchand installable.
- Parcours client mobile fluide.
- Parcours marchand mobile fluide.
- Accessibilité minimum respectée.
- WhatsApp semi-manuel disponible.

---

# Règle de travail sur les issues

À partir de cette décision :

- ne plus parler de bêta publique dans les issues restantes ;
- remplacer par “pré-lancement V1”, “préproduction contrôlée” ou “launch readiness” ;
- garder S13-003 comme dernier point livré ;
- ne pas déplacer S16 avant lancement ;
- ne pas laisser S15 avancé bloquer la sortie ;
- chaque issue restante doit indiquer si elle est :
  - obligatoire avant lancement ;
  - souhaitable avant lancement ;
  - reportée après lancement.
