# Parcours client — Documentation frontend

Date de réalignement : 2026-06-04

## Objectif

Décrire l'état courant du parcours client Kadhia dans le frontend Next.js : accès supérette, catalogue, Kadhia, rendez-vous, commande, notifications, retrait et i18n FR/AR avec RTL.

## Routes principales

```text
(client)/                                  → accueil
(client)/login                            → connexion client
(client)/register                         → inscription client
(client)/forgot-password                  → demande reset password
(client)/reset-password                   → confirmation reset password
(client)/profile                          → profil client
(client)/stores                           → liste / recherche supérettes
(client)/stores/by-qr/[qrToken]           → résolution QR magasin
(client)/stores/by-qr-scan                → saisie / scan assisté du token QR
(client)/stores/[shopId]                  → détail supérette
(client)/stores/[shopId]/catalog          → catalogue
(client)/kadhia                           → liste des Kadhias
(client)/kadhia/[kadhiaId]                → détail Kadhia
(client)/kadhia/slot                      → choix de rendez-vous
(client)/orders                           → commandes client
(client)/orders/[orderId]                 → suivi commande
(client)/orders/[orderId]/pickup          → QR / code de retrait et confirmation client
(client)/notifications                    → notifications client
```

> `(client)` est un groupe de layout Next.js. Il n'apparaît pas dans les URLs réelles.

## Flux livré

1. Le client ouvre l'app, recherche une supérette ou utilise un QR magasin.
2. La supérette est sélectionnée dans `SelectedStoreContext` et le thème public est synchronisé.
3. Le client consulte le catalogue paginé, filtré par recherche et catégorie.
4. Le client démarre ou reprend une Kadhia active pour la supérette.
5. Il ajoute, modifie ou retire des produits, puis choisit un rendez-vous de retrait.
6. Il soumet la Kadhia ; une commande est créée et suivie depuis `/orders/[orderId]`.
7. Le client consulte les notifications in-app.
8. Quand la commande est `ready` ou `pickup_pending`, il affiche le QR/code de retrait.
9. Après scan marchand, il confirme la réception côté client ; la commande peut passer à `completed`.

## Services consommés

- `auth.service.ts` : inscription, login, reset password.
- `stores.service.ts`, `store-search.service.ts`, `store-theme.service.ts` : supérettes, QR et thème.
- `catalog.service.ts` : catalogue public.
- `kadhia.service.ts` : Kadhia active, lignes, soumission.
- `slots.service.ts` : rendez-vous disponibles.
- `orders.service.ts` : commandes, statut, pickup session, confirmation client.
- `client-notifications.service.ts` : notifications client.

## i18n et localisation

S14-004 / #401 a livré l'i18n client :

- `ClientLocaleProvider` ;
- `LanguageToggle` ;
- persistance `client:lang` ;
- `dir="rtl"` sur l'espace client quand la langue arabe est sélectionnée ;
- messages `fr.json` et `ar.json`.

Le vocabulaire métier reste conservé : Kadhia, supérette, marchand, client, rendez-vous, retrait. Les montants restent en TND.

## Limites ouvertes

- PWA client installable et stratégie offline : #374 ouverte.
- Push notifications : #376 ouverte.
- Accessibilité minimum WCAG : #379 ouverte.
- Scan caméra QR réel : à confirmer dans le périmètre PWA ; la résolution QR par token existe.
- Notifications temps réel, SMS, email et paiement en ligne restent hors MVP strict.

## Hors périmètre

- Paiement en ligne.
- Livraison.
- Programme de fidélité.
- Panier multi-marchand / marketplace partagée.
