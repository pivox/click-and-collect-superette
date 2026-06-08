# Sprint 14 — Redéfinition des sprints à partir de 14

Date de cadrage : 2026-06-07  
Rôles de cadrage : PO + Tech Lead  
Point de départ : Sprint 13 reste le sprint catalogue/référentiel. La redéfinition stratégique commence ici, à Sprint 14.

## 1. Décision PO / Tech Lead

À partir de Sprint 14, la roadmap ne vise plus une bêta publique.

Nouvelle intention :

```text
Préparer une V1 de lancement officiel complète, mobile-first, monétisable et exploitable.
```

Règle de structure :

```text
Sprints 10 à 13 : ne pas les renommer dans cette passe.
Sprint 13 : finir le référentiel restant, sans replanifier les items déjà livrés.
À partir de Sprint 14 : redéfinir l'ordre et l'intention des sprints restants.
```

## 2. Vision PO

Le PO veut éviter une sortie publique fragile.

Avant lancement officiel, il faut :

- une expérience mobile installable ;
- un support minimum ;
- une monétisation prête ;
- un onboarding catalogue minimum ;
- des outils admin suffisants ;
- un catalogue fiable ;
- une valeur commerciale minimale pour le marchand.

## 3. Vision Tech Lead

Le Tech Lead impose :

- réutiliser `apps/frontend/` pour PWA client et marchand ;
- ne pas lancer les apps natives avant preuve d'usage ;
- ne pas complexifier le push si PWA + WhatsApp + notifications in-app suffisent au lancement ;
- documenter toute nouvelle route API ;
- garder des PR atomiques ;
- ne pas construire de recommandations avancées sans données réelles ;
- ne pas replanifier les issues déjà livrées.

## 4. Nouveau découpage à partir de Sprint 14

```text
Sprint 14 — Mobile Launch Readiness
Sprint 15 — Monétisation, support et exploitation avant lancement
Sprint 16 — Valeur commerciale minimale avant lancement
Post-lancement — Apps natives et growth avancée
```

Cette redéfinition garde les anciens numéros d'issues pour l'historique, mais change l'ordre d'exécution et le périmètre cible.

---

# Sprint 14 — Mobile Launch Readiness

## Objectif

Transformer l'expérience web responsive en expérience mobile installable et utilisable en conditions terrain.

## Acquis déjà livrés

```text
S14-004 — #377 — Arabe / RTL câblé dans l'application
```

Cet item reste une dépendance de qualité mobile, mais ne doit pas être replanifié comme travail restant.

## Issues actives

```text
S14-001 — #374 — PWA client
S14-002 — #375 — PWA marchand
S14-006 — #379 — Accessibilité minimum
S14-005 — #378 — WhatsApp semi-manuel
S14-post — #402 — Server Components i18n restants
S14-post — #403 — Dates localisées selon langue active
S14-post — #404 — aria-label notifications client
S14-003 — #376 — Push notifications
```

## Ordre recommandé

```text
1. PWA client
2. PWA marchand
3. Accessibilité minimum
4. WhatsApp semi-manuel
5. Finitions i18n/accessibilité
6. Push notifications
```

## Décision PO

La priorité est que client et marchand puissent utiliser l'application comme une app mobile installable.

Le push est utile, mais ne doit pas bloquer le lancement si les notifications in-app et WhatsApp couvrent les cas terrain minimum.

## Décision Tech Lead

PWA client et marchand doivent rester dans `apps/frontend/` et réutiliser l'API existante.

Le push Web doit être traité comme une amélioration contrôlée, avec limites iOS documentées.

## Hors périmètre

- Apps Android/iOS natives.
- Refonte mobile complète.
- Push natif APNs/FCM.
- Mode offline complet.

## Critère de sortie

```text
Le client et le marchand peuvent installer la PWA, commander, traiter une commande, suivre le retrait et contacter l'autre partie via WhatsApp si nécessaire.
```

---

# Sprint 15 — Monétisation, support et exploitation avant lancement

## Objectif

Regrouper les capacités nécessaires pour lancer officiellement sans perdre le contrôle opérationnel, commercial et onboarding marchand.

## Acquis déjà livrés / prérequis à ne pas replanifier

```text
#359 — Module abonnement marchand
#360 — Statuts abonnement lifecycle / phase tarifaire
#362 — Paiement manuel espèces / virement
#363 — Relances paiement email + WhatsApp manuel
#366 — Incidents commande
#367 — Backoffice support
#368 — Journal opérationnel marchand complet + vue santé
#369 — Runbook support terrain
#420 — Écran santé jobs async
#421 — Écran métriques pré-lancement
#422 — Détail checklist activation supérette
```

Ces éléments servent de fondation au Sprint 15 redéfini, mais ne doivent pas être replanifiés comme travail actif si les sources courantes les marquent livrés.

## Issues actives — Monétisation

```text
#361 — Reçu / facture mensuelle à cadrer fiscalement
#364 — Suspension douce et réactivation
```

## Issues actives — Onboarding catalogue marchand

```text
#365 — Import CSV + scan code-barres
```

Décision PO : #365 reste un prérequis de lancement, car le marchand doit pouvoir remplir son catalogue sans saisie produit par produit.

## Décision PO

Avant lancement officiel, il faut pouvoir :

- cadrer proprement reçu / facture avant toute promesse fiscale ;
- suspendre doucement et réactiver vite un marchand ;
- importer rapidement un catalogue marchand minimum ;
- s'appuyer sur les fondations acquises pour paiement manuel, relances, incidents, support, journal santé et métriques.

## Décision Tech Lead

Le paiement carte, les factures fiscales complètes et les automatisations complexes restent hors périmètre tant que le cadrage fiscal et opérationnel n'est pas validé.

Les écrans support et billing déjà présents doivent être réutilisés. Le Sprint 15 ne doit pas reconstruire les flux déjà livrés : paiement manuel, relances, incidents, backoffice support, journal santé, runbook et écrans ops.

## Hors périmètre

- Paiement en ligne client.
- Paiement carte marchand.
- Facturation fiscale complète non cadrée.
- Support omnicanal avancé.
- Rebuild des fondations abonnement déjà livrées.
- Rebuild des flux paiement manuel / relance déjà livrés.
- Rebuild des écrans support déjà livrés pour incidents, journal santé et runbook.
- Rebuild des écrans admin déjà livrés pour santé jobs, métriques et checklist activation.

## Critère de sortie

```text
Le Sprint 15 actif se limite aux vrais gaps avant lancement : cadrage reçu/facture, suspension douce/réactivation et import catalogue minimum.
Les fondations billing, support et ops déjà livrées restent des acquis de lancement à vérifier, pas des chantiers à refaire.
```

---

# Sprint 16 — Valeur commerciale minimale avant lancement

## Objectif

Donner au marchand et à l'équipe commerciale assez de valeur pour justifier le lancement, sans construire des modules avancés qui nécessitent déjà beaucoup de données réelles.

## Issues avant lancement

```text
#380 — S15-001 — Statistiques marchand simples
#384 — S15-004 — Promotions simples
#385 — S15-005 — CRM léger marchand
```

## Issues repoussées après lancement

```text
#382 — S15-002 — Packs produits
#383 — S15-003 — Suggestions de Kadhia
```

## Décision PO

Avant lancement, on livre :

- statistiques simples ;
- promotions simples ;
- suivi commercial léger.

Les packs et suggestions avancées attendent les premières données réelles.

## Décision Tech Lead

Les statistiques doivent commencer simple : cartes, tableaux, filtres de base. Pas de BI complexe, pas d'algorithme de recommandation sans données.

## Hors périmètre

- Suggestions intelligentes avancées.
- Recommandations sponsorisées.
- Packs complexes.
- Tableaux de bord BI.

## Critère de sortie

```text
Le marchand voit une valeur de pilotage minimale et l'équipe commerciale peut suivre la relation marchand.
```

---

# Post-lancement — Apps natives et growth avancée

## Issues post-lancement

```text
#386 — S16-001 — App native Android marchand
#387 — S16-002 — App native Android client
#388 — S16-003 — App native iOS client
#389 — S16-004 — App native iOS marchand
#382 — Packs produits avancés
#383 — Suggestions de Kadhia avancées
```

## Gate de déclenchement

```text
Usage réel prouvé
Limites PWA constatées
Facturation opérationnelle
Support stable
Besoin terrain confirmé
```

## Décision PO

Pas d'app native avant preuve terrain. Android marchand sera prioritaire si la PWA montre ses limites au comptoir.

## Décision Tech Lead

Les apps natives devront réutiliser l'API backend existante et ne pas dupliquer la logique métier.

---

# Gates CTO avant lancement officiel

## Gate mobile

- PWA client installable.
- PWA marchand installable.
- Parcours commande mobile OK.
- Parcours retrait mobile OK.
- WhatsApp fallback OK.
- i18n/accessibilité visible OK.

## Gate business

- Abonnement marchand existant.
- Phase tarifaire claire.
- Import catalogue minimum disponible.
- Paiement manuel disponible comme fondation acquise.
- Relance disponible comme fondation acquise.
- Suspension douce possible.
- Réactivation possible.

## Gate support

- Incident commande traçable via fondation acquise.
- Journal marchand consultable via fondation acquise.
- Runbook support disponible via fondation acquise.
- Vue santé marchand disponible via fondation acquise.
- Santé jobs async, métriques et checklist activation déjà visibles dans l'admin.

## Gate go / no-go

```text
Aucun bug bloquant sur commande.
Aucun bug bloquant sur retrait.
Aucun bug bloquant sur activation supérette.
Aucun bug bloquant sur import catalogue minimum.
Aucun bug bloquant sur abonnement/paiement manuel.
Aucun risque opérationnel non couvert par runbook.
```
