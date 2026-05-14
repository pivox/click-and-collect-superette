# US-061 — Exporter les données de commandes (marchand)

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Could Have

---

## Récit

En tant que **marchand**,
je veux **exporter l'historique de mes commandes au format CSV**,
afin de **faire ma comptabilité, analyser mes ventes ou partager les données avec mon comptable**.

---

## Préconditions

- Le marchand est connecté et propriétaire de la supérette.
- Des commandes existent dans l'historique de la supérette.

---

## Scénario nominal

1. Le marchand accède à l'historique de ses commandes.
2. Il clique sur « Exporter en CSV ».
3. Il peut optionnellement filtrer par période (date début / date fin) avant l'export.
4. Le fichier CSV est téléchargé immédiatement.
5. Le fichier contient les commandes avec leur détail (voir colonnes ci-dessous).

---

## Scénario alternatif — Grande période

1. Le marchand sélectionne une période de plus de 90 jours.
2. Un message l'avertit : « L'export peut prendre quelques secondes. »
3. Le fichier est généré côté serveur et proposé en téléchargement.

---

## Format du CSV

**Fichier :** `commandes-{slug-superette}-{from}-{to}.csv`
**Encodage :** UTF-8 avec BOM (pour compatibilité Excel).
**Séparateur :** virgule (`,`).

**Colonnes :**

| Colonne | Description | Exemple |
|---|---|---|
| `numero_commande` | Numéro lisible | `#0042` |
| `date_soumission` | ISO 8601 | `2026-05-14T10:30:00+01:00` |
| `statut` | Statut final | `completed` |
| `nom_client` | Nom complet | `Fatima Ben Ali` |
| `creneau_retrait` | Date et heure | `2026-05-14 14:00` |
| `produit` | Nom FR du produit | `Lait Vitalait 1L` |
| `marque` | Marque | `Vitalait` |
| `quantite` | Quantité commandée | `2` |
| `prix_unitaire_tnd` | Prix TND | `2.800` |
| `total_ligne_tnd` | Total ligne TND | `5.600` |
| `total_commande_tnd` | Total commande TND | `12.500` |

Une ligne par ligne de commande (une commande de 3 produits = 3 lignes dans le CSV, avec le numéro de commande répété).

---

## Règles métier

- Le marchand ne peut exporter que les commandes de ses propres supérettes.
- La période maximale d'un export est de 12 mois.
- Les données client exportées sont limitées au nom — pas de téléphone ni d'email dans l'export CSV (conformité minimale RGPD).
- L'export inclut tous les statuts (completed, cancelled, rejected, etc.).
- La période par défaut est les 30 derniers jours si non précisée.

---

## Critères d'acceptation

- [ ] Le marchand peut déclencher un export CSV depuis l'historique.
- [ ] Le fichier est correctement encodé UTF-8 avec BOM.
- [ ] Les colonnes correspondent au format défini.
- [ ] La période de filtrage est fonctionnelle.
- [ ] Le nom du fichier inclut le slug de la supérette et la période.
- [ ] L'export est limité aux commandes de la supérette du marchand.
- [ ] La période maximale de 12 mois est respectée.

---

## Notes techniques

**Endpoint :**
```http
GET /api/merchant/stores/{storeId}/orders/export.csv?from=2026-01-01&to=2026-05-31
```

**Headers de réponse :**
```
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="commandes-superette-ezzahra-2026-01-01-2026-05-31.csv"
```

**Génération du CSV dans le controller/processor :**
```php
use Symfony\Component\HttpFoundation\StreamedResponse;

$response = new StreamedResponse(function () use ($orders) {
    $handle = fopen('php://output', 'w');
    // BOM UTF-8
    fwrite($handle, "\xEF\xBB\xBF");
    // En-têtes
    fputcsv($handle, ['numero_commande', 'date_soumission', ...]);
    foreach ($orders as $order) {
        foreach ($order->getLines() as $line) {
            fputcsv($handle, [
                '#' . str_pad($order->getOrderNumber(), 4, '0', STR_PAD_LEFT),
                $order->getSubmittedAt()?->format('c'),
                // ...
            ]);
        }
    }
    fclose($handle);
});
$response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
```

**Sécurité :** `MerchantShopAccessChecker::denyUnlessMerchantOwnsShop()` sur l'endpoint.

**Validation de la période :**
- `from` et `to` au format `Y-m-d`.
- Si `to - from > 365 jours` → HTTP 422 `EXPORT_PERIOD_TOO_LARGE`.

**Pas de nouvelle entité** — requête directe sur `Order` + `OrderLine`.
