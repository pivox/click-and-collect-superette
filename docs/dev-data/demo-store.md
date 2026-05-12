# Supérette demo

Cette commande prépare une supérette exploitable pour tester le parcours client complet du Sprint 2 : catalogue, Kadhia, créneaux de retrait et soumission de commande.

## Commandes

Petit catalogue de démonstration :

```bash
make seed-demo-store
```

Tout le référentiel approuvé :

```bash
make seed-demo-store-all
```

Commandes Symfony équivalentes :

```bash
php bin/console app:dev:seed-demo-store --catalog=demo
php bin/console app:dev:seed-demo-store --catalog=all
```

## Données créées ou réutilisées

Marchand demo :

- email : `merchant.demo@kadhia.local`
- rôle : `ROLE_MERCHANT`
- actif : oui

Supérette demo :

- nom : `Supérette El Amen`
- slug : `superette-el-amen`
- ville : `Tunis`
- pays : `TN`
- QR token : `demo-superette-el-amen`
- active : oui

## Catalogue

`--catalog=demo` rattache uniquement une sélection courte de produits utiles pour tester le catalogue public.

`--catalog=all` rattache toutes les `ProductReference` avec le statut `Approved`.

Les produits catalogue sont créés ou mis à jour avec :

- `isVisible = true`
- `isAvailable = true`
- un prix TND à 3 décimales

La commande est idempotente : elle peut être relancée sans créer de doublons.

## Créneaux de retrait

La commande crée 3 créneaux de retrait futurs, nécessaires pour tester US-004 et US-021 :

| Créneau | Plage horaire | Capacité |
| --- | --- | --- |
| Créneau 1 | J+1, 10h00–10h30 | 5 |
| Créneau 2 | J+1, 14h00–14h30 | 5 |
| Créneau 3 | J+2, 10h00–10h30 | 5 |

Timezone : `Africa/Tunis` (UTC+1, pas de changement d'heure).

Les créneaux sont idempotents : la commande les recrée uniquement s'ils n'existent pas déjà ou s'ils sont passés.

## Vérification

La commande affiche l'identifiant de la supérette et les URLs publiques :

```http
GET /api/stores/{storeId}/catalog
GET /api/stores/{storeId}/catalog?query=lait
GET /api/stores/{storeId}/catalog?category=lait
GET /api/stores/{storeId}/pickup-slots?from=today&available=true
```
