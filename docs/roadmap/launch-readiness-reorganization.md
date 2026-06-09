# Réorganisation stratégique — à partir de Sprint 14

Date de cadrage : 2026-06-07  
Rôles de cadrage : PO + Tech Lead  
Point de départ technique : Sprint 13 reste centré sur le catalogue et le référentiel. Les éléments déjà livrés ne doivent pas être replanifiés.

## 1. Décision

Cette réorganisation commence à partir de **Sprint 14**.

Le document sprint de référence est :

```text
docs/Sprint14/README.md
```

Sprint 13 conserve son propre cadrage dans :

```text
docs/Sprint13/README.md
```

Décision :

```text
Ne pas lancer de bêta publique.
Redéfinir les sprints à partir de 14 pour préparer une V1 de lancement officiel.
```

## 2. Règle de gouvernance documentaire

```text
Sprint 13 = catalogue / référentiel restant, sans replanifier les acquis livrés.
À partir de Sprint 14 = redéfinition stratégique de lancement officiel.
Aucun dossier SprintLaunchReadiness séparé ne doit être utilisé.
Les anciens numéros d'issues restent conservés pour l'historique.
Les issues déjà livrées sont listées comme acquis, pas comme backlog actif.
```

## 3. Lecture PO

Le PO valide :

- ne pas exposer clients et marchands à une bêta fragile ;
- commencer la redéfinition à Sprint 14 ;
- rendre l'expérience mobile installable avant lancement ;
- regrouper monétisation, support, exploitation et onboarding catalogue avant ouverture ;
- limiter les modules growth avant lancement à ce qui apporte une valeur immédiate ;
- repousser le natif après usage réel.

## 4. Lecture Tech Lead

Le Tech Lead valide :

- PWA client/marchand dans `apps/frontend/` ;
- API backend existante réutilisée ;
- push Web non bloquant si PWA + notifications in-app + WhatsApp sont stables ;
- pas de paiement carte ni facture fiscale complète sans cadrage ;
- pas d'algorithme de suggestion avancé sans données réelles ;
- apps natives post-lancement uniquement ;
- aucune issue déjà livrée ne doit être remise dans une liste d'exécution.

## 5. Nouveau découpage à partir de Sprint 14

### Sprint 14 — Mobile Launch Readiness

Acquis : #377 — Arabe / RTL câblé dans l'application.

Issues actives : #374, #375, #379, #378, #402, #403, #404, #376.

Priorité :

```text
1. PWA client
2. PWA marchand
3. Accessibilité minimum
4. WhatsApp semi-manuel
5. Finitions i18n/accessibilité
6. Push notifications
```

Critère de sortie :

```text
Client et marchand peuvent installer et utiliser l'application mobile web en conditions terrain.
```

### Sprint 15 — Monétisation, support et exploitation avant lancement

Acquis / fondations à ne pas replanifier : #359, #360, #362, #363, #366, #367, #368, #369, #420, #421, #422.

Issues actives monétisation : #361, #364.  
Issue active onboarding catalogue : #365.

Critère de sortie :

```text
Le Sprint 15 actif se limite aux vrais gaps avant lancement : cadrage reçu/facture, suspension douce/réactivation et import catalogue minimum.
Les fondations billing, support et ops déjà livrées restent des acquis de lancement à vérifier, pas des chantiers à refaire.
```

### Sprint 16 — Valeur commerciale minimale avant lancement

Avant lancement : #380, #384, #385.

Après lancement : #382, #383.

Critère de sortie :

```text
Le marchand voit une valeur de pilotage minimale et l'équipe commerciale peut suivre la relation marchand.
```

### Post-lancement — Apps natives et growth avancée

Issues : #386, #387, #388, #389, #382, #383.

Critère de déclenchement :

```text
Usage réel prouvé, limites PWA constatées, facturation opérationnelle, support stable, besoin terrain confirmé.
```

### Post-lancement — Écosystème standalone, agent IA externe, apps natives & infra production

Cette section formalise la structuration en repos standalones autour de la plateforme centrale. Voir
`docs/architecture/standalone-ecosystem.md`, `docs/architecture/dockerized-platform.md` et
`docs/adr/ADR-standalone-ecosystem-and-dockerized-platform.md`.

- Créer `click-and-collect-ai-agent` comme **nouveau repo IA standalone** (distinct de la brique
  catalogue-photo interne au backend ; périmètre fonctionnel exact à cadrer dans une issue dédiée).
  Invariants : consomme l'API, sortie JSON + score de confiance, pas d'écriture directe en base, l'API
  reste responsable du commit métier. Gate : besoin terrain confirmé.
- Créer `click-and-collect-infra` pour orchestrer staging/prod à partir d'images Docker versionnées
  (backup PostgreSQL, logs, monitoring, SSL ; secrets injectés, jamais dupliqués dans les repos).
- Apps natives (#386–#389) déclenchées par le gate terrain de l'ADR-0005, Android marchand prioritaire.

## 6. Gates CTO avant lancement officiel

### Gate mobile

- PWA client installable.
- PWA marchand installable.
- Parcours commande mobile OK.
- Parcours retrait mobile OK.
- WhatsApp fallback OK.
- i18n/accessibilité visible OK.

### Gate business

- Abonnement marchand existant.
- Phase tarifaire claire.
- Import catalogue minimum disponible.
- Paiement manuel disponible comme fondation acquise.
- Relance disponible comme fondation acquise.
- Suspension douce possible.
- Réactivation possible.

### Gate support

- Incident commande traçable via fondation acquise.
- Journal marchand consultable via fondation acquise.
- Runbook support disponible via fondation acquise.
- Vue santé marchand disponible via fondation acquise.
- Santé jobs async, métriques et checklist activation déjà visibles dans l'admin.

### Gate go / no-go

```text
Aucun bug bloquant sur commande.
Aucun bug bloquant sur retrait.
Aucun bug bloquant sur activation supérette.
Aucun bug bloquant sur import catalogue minimum.
Aucun bug bloquant sur abonnement/paiement manuel.
Aucun risque opérationnel non couvert par runbook.
```
