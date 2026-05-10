# US-031 — Voir les informations de la supérette

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-001 — Onboarding par QR code.

## Objectif produit

Permettre au client de comprendre immédiatement dans quelle supérette il se trouve après avoir scanné le QR code ou ouvert un lien public.

Cette story évite toute ambiguïté : avant de consulter le catalogue ou de créer une Kadhia, le client doit voir clairement le nom du magasin, sa ville, son état d'ouverture fonctionnel et les informations publiques utiles au retrait.

## Récit utilisateur

En tant que client,
je veux voir les informations principales de la supérette,
afin de vérifier que je commande bien dans le bon magasin.

## Acteurs

- Client final.
- Supérette.
- Plateforme Click & Collect.

## Préconditions

- Le client a ouvert l'espace public d'une supérette.
- La supérette existe et est active.
- Les informations publiques de base sont disponibles côté backend.

## Informations à afficher

Informations minimales MVP :

- nom de la supérette ;
- ville ;
- pays ;
- état actif ou indisponible ;
- éventuellement slug public ;
- indication que les prix sont en TND ;
- accès au catalogue de la supérette.

Informations possibles plus tard, hors MVP immédiat :

- adresse complète ;
- téléphone ;
- horaires d'ouverture ;
- temps moyen de préparation ;
- message personnalisé du marchand.

## Parcours nominal

1. Le client accède à la page publique de la supérette.
2. Le frontend charge les informations publiques du magasin.
3. Le client voit le nom de la supérette en haut de page.
4. Le client voit la ville et le contexte pays `TN`.
5. Le client peut continuer vers le catalogue.

## Règles métier

- Les informations affichées doivent être publiques uniquement.
- Aucune donnée privée du marchand ne doit être exposée.
- L'email du marchand, ses rôles, son identifiant interne utilisateur ou ses informations admin ne doivent pas être retournés.
- Une supérette inactive doit être présentée comme indisponible ou non accessible.
- Les données affichées doivent être cohérentes avec le thème actif de la supérette.

## API cible

Option recommandée : enrichir la résolution du QR code ou prévoir un endpoint public dédié.

```http
GET /api/stores/{storeId}
```

Réponse attendue minimale :

```json
{
  "id": "uuid",
  "name": "Supérette El Amen",
  "slug": "superette-el-amen",
  "city": "Tunis",
  "country": "TN",
  "is_active": true
}
```

## Critères d'acceptation

### Affichage magasin valide

Étant donné une supérette active,
quand le client ouvre sa page,
alors le nom, la ville et le pays de la supérette sont affichés.

### Données privées non exposées

Étant donné une supérette liée à un marchand,
quand le frontend récupère les informations publiques,
alors l'API ne retourne pas l'email, les rôles ou les données privées du marchand.

### Supérette inactive

Étant donné une supérette inactive,
quand le client tente d'afficher ses informations,
alors le système indique que la supérette n'est pas disponible.

### Continuité parcours

Étant donné une supérette active,
quand ses informations sont affichées,
alors le client peut accéder au catalogue de cette même supérette.

## Tests attendus

- Test fonctionnel de lecture d'une supérette active.
- Test fonctionnel d'une supérette inactive.
- Test de non-exposition des champs sensibles.
- Test de cohérence entre `store_id` et catalogue public.

## Hors périmètre

- Modification des informations magasin.
- Back-office marchand.
- Onboarding marchand.
- Gestion des horaires avancés.
- Avis clients.

## Dépendances

- Entité `Shop`.
- Résolution QR code ou routing storefront.
- Endpoint catalogue public.

## Définition de fini

La story est terminée lorsque le client identifie clairement la supérette consultée, sans authentification, sans exposition de données privées, et peut continuer vers le catalogue du même magasin.