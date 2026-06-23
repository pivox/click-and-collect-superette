# Réorganisation stratégique — préparation lancement

Date de cadrage : 2026-06-07
Dernière révision documentaire : 2026-06-23
Rôles de cadrage : PO + Tech Lead

## 1. Décision

La roadmap active commence à partir de Sprint 14 et prépare une V1 de lancement
officiel, pas une bêta publique fragile.

Document de référence :

```text
docs/Sprint14/README.md
```

Point d'entrée et précédence documentaire :

```text
docs/project/source-of-truth.md
```

`docs/product/mvp-roadmap.md` reste un index court. Ne pas recréer
`docs/roadmap/mvp-roadmap.md`.

## 2. Gouvernance

```text
Sprint 13 = catalogue / référentiel historique, sans replanifier les acquis.
Sprint 14+ = roadmap active de lancement officiel.
Les audits datés restent des photographies historiques.
Les issues fermées et techniquement constatées sont des acquis, pas du backlog actif.
```

## 3. Lecture PO

Le lancement officiel exige :

- une expérience mobile installable ;
- une monétisation marchand compréhensible ;
- un support terrain minimal ;
- un onboarding catalogue rapide ;
- une valeur commerciale visible pour le marchand ;
- des gates go/no-go explicites.

## 4. Lecture Tech Lead

- PWA client/marchand dans `apps/frontend/`.
- API backend existante réutilisée.
- Web Push et notifications in-app comme socle ; WhatsApp semi-manuel possible
  comme fallback terrain.
- Facebook Messenger optionnel, best-effort et dépendant d'un spike Meta.
- Pas d'app native avant preuve d'usage et limites PWA constatées.
- Pas de paiement en ligne ni facture fiscale complète sans décision explicite.

## 5. Acquis structurants

### Mobile

#374 PWA client, #375 PWA marchand, #376 Web Push, #377 FR/AR RTL et #379
accessibilité minimum sont fermées côté GitHub et disposent de preuves locales
dans le code ou les tests. Elles ne doivent plus être replanifiées comme sujets
actifs, même si une validation terrain reste utile.

### Business / exploitation

#361 document mensuel interne non fiscal, #364 suspension/réactivation et #365
import CSV + recherche code-barres sont fermées et techniquement représentées
dans le dépôt. Les fondations paiement manuel, relances, support, journal santé,
ops et activation restent des acquis de lancement à vérifier en démo.

### Valeur commerciale

#380 statistiques marchand, #384 promotions simples et #385 CRM léger sont
fermées et représentées par endpoints, UI et tests ciblés. Les packs et
suggestions restent post-lancement.

## 6. Gaps réels avant lancement

- Garder #527 comme issue mère ouverte et maintenue.
- Traiter ou explicitement reporter #378 WhatsApp semi-manuel.
- Traiter #543 `FRONTEND_URL` comme fiabilisation P2 QR/share, non bloquante
  pour les sujets MVP plus urgents.
- Rejouer les parcours terrain client, marchand et admin en environnement de
  démo avant go/no-go.
- Confirmer que les documents billing/support sont compris opérationnellement.

## 7. Canaux externes

WhatsApp semi-manuel (#378) peut aider le terrain, mais il ne doit pas devenir
une automatisation WhatsApp Business dans le MVP.

Facebook Messenger (#490 à #494) reste conditionnel :

- #490 spike Meta ouvert ;
- #491 préférences de canal et traces ouvert ;
- #492 opt-in Messenger ouvert ;
- #493 provider Messenger ouvert ;
- #494 page Facebook marchand ouverte et post-lancement.

Règle : l'in-app reste la source de vérité. Messenger ne bloque jamais une
commande ou un retrait sans décision PO explicite.

## 8. Gates CTO

### Gate mobile

- PWA client et marchand installables.
- Parcours commande et retrait OK en mobile.
- Web Push ou notifications in-app opérationnelles selon support navigateur.
- WhatsApp fallback décidé.
- Accessibilité minimum vérifiée sur écrans clés.

### Gate business

- Abonnement, phase tarifaire, document mensuel interne, paiement manuel,
  suspension/réactivation et import catalogue disponibles.
- Valeur marchand visible : statistiques, promotions, CRM léger.

### Gate support

- Incidents, journal marchand, runbook, santé jobs, métriques, checklist
  activation et feedback terrain utilisables ou explicitement reportés.

### Gate go / no-go

```text
Aucun bug bloquant sur commande.
Aucun bug bloquant sur retrait.
Aucun bug bloquant sur activation supérette.
Aucun bug bloquant sur import catalogue minimum.
Aucun bug bloquant sur abonnement/paiement manuel.
Aucun risque opérationnel non couvert par runbook.
```
