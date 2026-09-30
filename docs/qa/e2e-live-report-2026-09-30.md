# Rapport E2E live — parcours client + marchand sur API réelle

Date : 2026-09-30
Contexte : prérequis go/no-go de l'audit MVP #527 (« Rejouer un scénario E2E
live client + marchand »), exécuté au navigateur (Playwright) contre l'API
réelle, données seedées, **aucun mock**.

## Environnement

- Branche : `main` (`6012c9e`), 4 migrations appliquées sur la base dev locale
  (les 3 en attente de juin + `Version20260930140000`, colonne additive sans
  effet sur `main`).
- Backend : Docker Compose (`cc_backend` + `cc_postgres`), `GET /api/health`
  → `{"status":"ok","checks":{"database":"ok"}}`.
- Frontend : Next.js dev dans `cc_frontend`, **`NEXT_PUBLIC_USE_MOCKS=0`**
  (via `docker-compose.override.yml` local non commité) — toutes les requêtes
  observées partent vers l'API réelle (`http://192.168.1.48:8000`, override
  LAN local du poste).
- Données : `app:dev:seed-demo-store` — Supérette El Amen
  (`superette-el-amen`), client `client.demo@kadhia.local`, marchand
  `merchant@test.com`, 3 créneaux (le jour même étant sans créneau, ceux du
  lendemain ont servi), catalogue visible de 5017 produits.
- Deux onglets du même navigateur : portail client (`jwt_token`) et portail
  marchand (`merchant_token`) — la séparation des tokens par portail
  fonctionne, aucune interférence observée.

## Parcours nominal exécuté (bout en bout ✅)

| # | Étape | Résultat observé |
|---|---|---|
| 1 | Entrée par QR magasin `/stores/by-qr/demo-superette-el-amen` | Redirection vers le catalogue de Supérette El Amen, supérette activée dans l'espace client |
| 2 | Connexion client (`client.demo@kadhia.local`) | OK, retour à l'app connectée |
| 3 | Catalogue : recherche « lait demi », filtres catégorie affichés | Cartes produits réelles avec prix TND, disponibilité, favoris |
| 4 | Ajout « Lait demi-écrémé 1 L » (9,113 TND) à la Kadhia | Kadhia 3 articles, total estimé **22,675 TND** (une Kadhia draft résiduelle de 2 articles existait — reprise correcte) |
| 5 | Choix du créneau : « Aujourd'hui » vide (« Aucun créneau ce jour ») → « Demain » | 2 créneaux proposés (10:00–11:00 matin, 14:00–15:00 après-midi), groupés par période |
| 6 | Note au marchand pré-remplie + « Envoyer la commande » | Commande **#0005** créée, statut « Soumise », prix et créneau verrouillés, timeline de suivi affichée |
| 7 | Marchand : dashboard | Connecté sur Supérette El Amen, **6 notifications non lues** (dont la nouvelle commande) ; compteurs « du jour » à 0 — attendu, le retrait est demain |
| 8 | Commandes actives : #0005 en tête (« Soumise », 3 produits, 10:00) | Détail : client, note client transmise, 3 lignes, montants exacts |
| 9 | « Accepter » | `POST .../accept` → 200 ; passage automatique en « **En préparation** » (accepted → preparing) |
| 10 | Préparation ligne par ligne (3 cases « Ligne préparée ») | « Commande prête » désactivé tant que tout n'est pas coché, puis actif → statut « **Prête** » |
| 11 | Client : page commande actualisée | « Commande prête », **code de retrait 4 chiffres (0447)**, lien « Afficher le QR retrait » |
| 12 | QR retrait client | QR affiché + token de session (`ecd409f9-…`), infos supérette/créneau |
| 13 | Marchand : « Retrait sécurisé », mode QR, collage du token (simulation du scan) | Session identifiée : commande #0005, client, 3 lignes, 22,675 TND, « QR scanné. Retrait en attente de confirmation marchand. » |
| 14 | « Remettre la Kadhia » (confirmation marchand) | « Confirmation marchand enregistrée. En attente de confirmation client. » — bouton désactivé, option « Forcer la finalisation » présente |
| 15 | Client : « J'ai récupéré ma Kadhia » (confirmation client) | Statut final « **Récupérée** », timeline complète, « Retrait finalisé » |

**Verdict : le parcours cœur MVP passe de bout en bout sur API réelle, double
validation de retrait incluse.** Chaque transition est revenue avec les
montants, notes et statuts cohérents entre les deux portails.

## Constats et observations

1. **[Automatisation, à vérifier à la main]** Les clics souris synthétiques
   (CDP) sur certains boutons du portail marchand (« Accepter ») n'ont pas
   déclenché le handler React (2 tentatives, aucune requête émise, aucune
   erreur) ; le clic DOM (`element.click()`) a fonctionné immédiatement
   (POST → 200). Probable artefact d'outillage (overlay dev/rendu), non
   reproduit sur les autres écrans ; à confirmer par un clic humain avant de
   qualifier en bug.
2. **[Bénin]** 2 erreurs console `401` sur `GET /api/me/stores/{id}/kadhias`
   en visiteur anonyme sur le catalogue (avant login) — comportement attendu,
   mais un guard « ne pas appeler /api/me/* sans token » éviterait le bruit.
3. **[Mineur]** Warning navigateur : `<meta name="apple-mobile-web-app-capable">`
   dépréciée, ajouter `<meta name="mobile-web-app-capable">` (PWA).
4. Les compteurs du dashboard marchand sont strictement « du jour » : une
   commande pour demain n'y apparaît pas (elle est bien dans Commandes
   actives). Comportement conforme, mais à garder en tête pour la démo.

## Non couvert par cette passe (couvert par les tests fonctionnels)

- Annulation client, refus et acceptation partielle, resoumission ;
- retrait par code 4 chiffres et validation manuelle (le code 0447 était
  affiché ; seule la voie QR + double validation a été déroulée) ;
- scan caméra réel depuis un mobile (le token QR a été collé) ;
- AR/RTL (parcours joué en FR ; bascule de langue visible et non testée ici) ;
- emails (reset/invitation) et worker Messenger sur délais réels ;
- délai minimal avant retrait (#569) : politique à 0 par défaut, PRs #586–#589
  non mergées au moment du test.

## Reproduction

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate
docker compose exec backend php bin/console app:dev:seed-demo-store
# docker-compose.override.yml : frontend.environment.NEXT_PUBLIC_USE_MOCKS: "0"
docker compose up -d frontend
# Client : http://localhost:3000/stores/by-qr/demo-superette-el-amen
# Marchand : http://localhost:3000/merchant/login
```
