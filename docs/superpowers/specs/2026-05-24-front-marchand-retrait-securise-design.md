# Front marchand — retrait sécurisé

Date : 2026-05-24

## Objectif

Livrer le parcours marchand de retrait sécurisé pour finaliser une Kadhia prête en supérette :

1. le marchand saisit ou colle le token du QR code de retrait présenté par le client ;
2. le backend identifie la `PickupSession` et passe la commande de `ready` à `pickup_pending` ;
3. le marchand vérifie le client et les lignes de Kadhia ;
4. le marchand confirme la remise ;
5. si le client ne confirme pas, le marchand peut forcer la complétion avec une note obligatoire.

Cette tranche complète la PR #135 sans ajouter de scan caméra, paiement, livraison, fidélité ou marketplace multi-marchands.

## Périmètre

Inclus :

- route frontend `/merchant/retrait` ;
- entrée "Retrait" active dans le menu marchand ;
- saisie/collage manuel du token QR opaque ;
- appel `POST /api/merchant/pickup-sessions/scan` ;
- affichage du client, du numéro de commande, du statut et des lignes de Kadhia retournés par le scan ;
- appel `PATCH /api/merchant/pickup-sessions/{id}/confirm` ;
- appel `PATCH /api/merchant/pickup-sessions/{id}/force-complete` avec note obligatoire ;
- messages d'erreur lisibles pour token invalide, session expirée, session déjà utilisée ou action refusée ;
- tests Vitest ciblés sur services et interactions principales.

Exclus :

- scan caméra QR dans le navigateur ;
- notifications temps réel ;
- modification backend ;
- confirmation client côté frontend client ;
- historique complet des retraits ;
- impression ou génération de QR.

## Architecture frontend

### Route

Ajouter `apps/frontend/src/app/merchant/retrait/page.tsx`.

La page est un composant client qui dépend de `MerchantAuthContext` via le layout marchand existant. Elle conserve un état local :

- `token` ;
- `session` après scan ;
- `isScanning` ;
- `isMutating` ;
- `error` ;
- `forceNote` ;
- affichage ou non du formulaire de force completion.

### Navigation

Mettre à jour `MerchantShell` :

- déplacer "Retrait" dans la navigation active ;
- garder "Créneaux", "Catalogue" et "Paramètres" désactivés pour des PRs futures.

### Services

Créer `apps/frontend/src/lib/services/merchant-pickup.service.ts` avec :

- `scanMerchantPickupSession(token: string)` ;
- `confirmMerchantPickupSession(sessionId: string)` ;
- `forceCompleteMerchantPickupSession(sessionId: string, note: string)`.

Les endpoints consommés sont :

```http
POST  /api/merchant/pickup-sessions/scan
PATCH /api/merchant/pickup-sessions/{id}/confirm
PATCH /api/merchant/pickup-sessions/{id}/force-complete
```

La confirmation envoie un corps `{}` pour rester cohérente avec les endpoints Symfony/API Platform utilisant `input: false`.

### Types

Étendre `apps/frontend/src/lib/types/merchant.types.ts` avec :

- `MerchantPickupSessionScanResult` ;
- `MerchantPickupSessionLine` ;
- `MerchantPickupSessionCustomer` ;
- `MerchantPickupSessionActionResult` ;
- `MerchantPickupSessionForceCompleteResult`.

Les clés restent en `snake_case`, comme les réponses backend.

## Comportement UI

### État initial

La page affiche :

- titre "Retrait sécurisé" ;
- aide courte : "Colle le token du QR code présenté par le client." ;
- champ texte pour le token ;
- bouton "Identifier la Kadhia".

Le bouton est désactivé tant que le token est vide. Le format UUID est validé côté UI avant l'appel API pour éviter une requête inutile.

### Après scan réussi

La page affiche :

- numéro de commande ou identifiant ;
- statut retourné par le backend, normalement `pickup_pending` après un scan réussi ;
- heure de scan ;
- informations client disponibles ;
- lignes de Kadhia ;
- bouton "Remettre la Kadhia".

Le formulaire de scan reste disponible via une action "Scanner un autre QR".

### Confirmation marchand

Après `confirm`, la page affiche :

- si `is_completed` vaut `true` : retrait finalisé ;
- sinon : confirmation marchand enregistrée, attente de la confirmation client.

Le bouton de confirmation devient inactif une fois `merchant_confirmed_at` renseigné.

### Force completion

La force completion n'est proposée qu'après confirmation marchand et tant que :

- `is_completed` est `false` ;
- `customer_confirmed_at` est `null`.

La note est obligatoire côté UI, limitée à 500 caractères. Si le backend refuse parce que le délai de 5 minutes n'est pas atteint, le message backend est affiché.

## Gestion des erreurs

La page extrait `response.data.detail` quand disponible. Sinon elle affiche un message générique :

> "L'action n'a pas pu être effectuée. Vérifie le QR code puis réessaie."

Les erreurs ne réinitialisent pas automatiquement la session courante afin que le marchand puisse relire l'état affiché. L'état de confirmation courant n'est effacé qu'après un nouveau scan réussi, pas après un token invalide ou une erreur réseau.

Pendant une confirmation marchand ou une finalisation forcée en cours, les actions "Identifier la Kadhia" et "Scanner un autre QR" sont désactivées pour éviter de mélanger la réponse d'une mutation avec une autre session affichée.

## Tests

Ajouter ou mettre à jour :

- `apps/frontend/src/tests/merchant.pickup.service.test.ts` :
  - scan envoie `{ token }` au bon endpoint ;
  - confirm envoie `PATCH` avec `{}` ;
  - force complete envoie `{ note }`.
- `apps/frontend/src/tests/merchant.retrait.test.tsx` :
  - saisie token + scan réussi affiche la session ;
  - token vide ou invalide bloque l'action ;
  - erreur backend ou réseau au scan affiche le message attendu sans session ;
  - confirmation marchand appelle le service et met à jour l'état ;
  - l'état de confirmation courant reste affiché après un token invalide ;
  - reset revient à l'état initial ;
  - reset et scan sont bloqués pendant une mutation de retrait ;
  - force completion exige une note.

Vérifications attendues :

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.pickup.service.test.ts src/tests/merchant.retrait.test.tsx
npx tsc --noEmit
npm run lint
npm run build
```

## Risques

- Le scan caméra n'est pas inclus : en magasin, le marchand devra coller le token ou utiliser un lecteur QR qui remplit le champ texte.
- La force completion dépend de la règle backend des 5 minutes ; l'UI ne simule pas ce délai et affiche l'erreur backend si l'action est prématurée.
- La confirmation client reste hors de cette PR ; l'état final `completed` peut donc dépendre d'une action client ou d'une force completion.
