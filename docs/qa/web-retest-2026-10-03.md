# Recette web — 3 octobre 2026

## Environnement et périmètre

Branche de travail : `fix/qa12-merchant-api`. Recette du frontend Next.js avec
l’API Symfony réelle : `localhost:3000` / `localhost:8000`, PostgreSQL et Mailpit
locaux. Chrome headless piloté par Playwright, formats bureau et 390 × 844 px.
L’outil navigateur intégré n’était pas exposé dans cette session ; Chrome local
a été utilisé après lecture des instructions du skill navigateur.

Les comptes et supérettes de recette sont dédiés, préfixés `qa.web.auth`,
`qa-web-admin` ou `qa-order`. Aucun nettoyage global ni réinitialisation des
données métier existantes. Les données QA restent en développement pour pouvoir
inspecter le résultat. Les secrets et jetons ne sont pas inclus dans ce rapport.

La recette combine toute la suite automatisée frontend, des parcours réels
client/marchand et un contrôle des pages admin en lecture. Elle ne représente
pas une validation exhaustive de chaque mutation du backoffice ni de chaque
navigateur physique.

## Défauts corrigés

| Zone | Défaut constaté | Correction |
|---|---|---|
| Connexion | Formulaire encore accessible avec une session active | Attente de restauration puis redirection client, marchand et admin ; inscription déjà connectée protégée |
| Sessions | Rôle client/admin insuffisamment contrôlé, navigation marchand doublée, réponses tardives après déconnexion | Validation des sessions et destinations, navigation centralisée, réponses obsolètes ignorées |
| Profil client | Nom absent après reconnexion car absent du JWT | Lecture de `GET /api/me/profile` à la connexion et à la restauration ; réponse serveur utilisée après édition |
| Profil mobile web | Nom/email débordant en français/arabe | Colonne bornée, espacement et retour à la ligne, vérification visuelle RTL |
| Récupération d’accès | Portail perdu lors de la demande d’un nouveau lien ; quota peu explicite | Conservation du portail et messages d’erreur adaptés |
| Règles récurrentes | Premier succès ferme le formulaire, erreurs suivantes masquées, relance recréant des jours déjà réussis | Maintien du lot, relance des seuls jours échoués, verrou contre les doubles envois et relecture séquencée |
| Génération | Action possible sans règle active, bilan incomplet | Règles actives requises, période réelle et compteurs serveur affichés, relecture après échec |
| Rendez-vous | Jour dépendant du fuseau du navigateur ; capacité décimale tronquée | Calendrier de Tunis et capacité entière explicite |
| Fermetures | Saisie 09 h à Los Angeles enregistrée à 17 h Tunis | Saisie et affichage selon Tunis, indépendants du navigateur |
| Admin bêta | HTTP 500 PostgreSQL sur le regroupement par supérette | Projection SQL alignée sur la colonne regroupée, régression PostgreSQL isolée |
| Admin supervision | `NaN min` avec une file vide | Normalisation des champs JSON nullables omis par l’API |
| Retrait | Code technique brut pour un QR inconnu | Message compréhensible en français et arabe |
| Tests photos | Deux attentes dépendantes de l’adresse IP locale | URL API déterministe dans le test |

## Recette réelle

### Client et accès

- Inscription d’un nouveau client, refus d’un email déjà enregistré.
- Connexion, erreur explicite avec l’ancien mot de passe (HTTP 401), accès avec
  le nouveau mot de passe, déconnexion et retour au formulaire.
- Visite de `/login` avec une session : redirection sans formulaire persistant.
- Modification du profil, rechargement, nouvelle connexion : nom conservé côté
  serveur même si le JWT ne contient pas de nom.
- Mot de passe oublié : email réellement reçu dans Mailpit ; lien utilisé dans
  le navigateur ; confirmation différente refusée ; nouveau mot de passe
  accepté ; réutilisation du lien refusée.
- Accès anonymes marchand/admin redirigés vers leurs connexions.
- Affichage du profil en français/arabe à 390 px, RTL et préférence persistante.
- Navigation accueil, supérettes, Kadhia, commandes et notifications ; catalogue
  inexistant : erreur contrôlée avec possibilité de réessayer.

### Administration

Contrôle des 17 routes : dashboard, marchands, supérettes, produits, catégories,
marques, propositions, groupements, abonnements, facturation, promotions,
incidents, audit, feedbacks, métriques bêta, ops et ops/messenger.

Après correction : métriques bêta et référentiel produits relus après réponse
API HTTP 200 ; supervision sans `NaN` ; connexion admin déjà authentifiée
redirigée ; dashboard sans débordement à 390 px. Pas d’exception JavaScript
relevée dans ces contrôles. Les mutations administratives n’ont pas été rejouées
sur les données existantes.

### Kadhia et retrait

Parcours Chrome avec deux comptes dédiés : QR de supérette, catalogue de deux
produits, Kadhia de 3,000 TND, choix d’un rendez-vous, soumission, acceptation
marchand, préparation des deux lignes, commande prête, affichage du QR client,
lecture du jeton par le marchand puis double confirmation. Le client affiche
« Récupérée / Retrait finalisé ». Une caméra physique n’a pas été utilisée.

### Récurrence et profils marchand

Recette Chrome avec fuseau `America/Los_Angeles` : sept règles lundi–dimanche,
09 h–12 h, capacité 5. Résultats réels :

| Génération | Créés | Existants ignorés | Fermetures ignorées |
|---|---:|---:|---:|
| 1 mois | 90 | 1 | 0 |
| 3 mois | 183 | 91 | 0 |
| Relance 3 mois | 0 | 274 | 0 |
| Après fermeture 09 h–10 h Tunis | 0 | 273 | 1 |

Lecture API : 274 plages uniques ; créneau ponctuel initial conservé avec sa
capacité 10. Capacité 2,5 refusée ; capacité 7 sauvegardée puis relue.
Fermeture saisie à 09 h–10 h : payload `+01:00`, persistance et affichage Tunis
vérifiés ; créneau désactivé et non réactivé par la génération suivante.

Profil supérette (nom, adresse, ville, téléphone) et compte marchand (prénom,
nom, téléphone) modifiés puis relus après rechargement. Aucune exception
JavaScript ni réponse API HTTP ≥ 400 durant cette recette de créneaux/profils.

Contrôle complémentaire en lecture de 13 pages marchand : dashboard, catalogue,
notifications, paramètres, profil, compte, langue, équipe, délai de retrait,
abonnement, QR de supérette, apparence et statistiques. Aucune exception
JavaScript relevée. Le retour à la connexion avec une session active redirige
vers le dashboard.

La fixture sans organisation reçoit le refus attendu sur la gestion d’équipe ;
une lecture avec le compte principal de démonstration confirme le chargement
correct de l’équipe existante. Aucun abonnement n’existe pour les comptes
consultés : `/api/merchant/subscription` renvoie 404, comme prévu par le provider,
et la page affiche un message de chargement impossible. Le parcours avec un
abonnement renseigné n’a donc pas été validé en navigateur.

Deux observations ont été investiguées sans correction : « Client non renseigné »
dans l’historique résulte du masquage volontaire des coordonnées après clôture ;
les boutons « Produits à compléter » et « Réessayer » du catalogue sont deux
actions indépendantes (filtre et actualisation), pas une erreur de chargement.
Leur formulation peut être clarifiée ultérieurement.

## Fichiers concernés

- `apps/frontend/src/app/` : login des trois rôles, inscription, récupération,
  profil client, layout marchand, pages créneaux et retrait.
- `apps/frontend/src/lib/auth/` et `src/lib/services/auth.service.ts` : sessions,
  destinations autorisées et profil serveur.
- `apps/frontend/src/components/merchant/creneaux/` et
  `src/lib/merchant-slot-calendar.ts` : règles, génération, calendrier,
  capacité et fermetures.
- `apps/frontend/src/lib/services/admin/ops.service.ts` : réponse de supervision.
- `apps/frontend/src/tests/` : régressions associées et test photo déterministe.
- `apps/backend/src/Provider/AdminBetaMetricsProvider.php` et
  `apps/backend/tests/Functional/PostgreSQL/AdminBetaMetricsPostgreSqlTest.php`.

## Vérifications automatisées finales

| Vérification exécutée | Résultat |
|---|---|
| `docker compose exec -T frontend npm run test:run -- --maxWorkers=2` | **767 tests réussis, 121 fichiers**, après les derniers correctifs |
| `make lint-frontend` | ESLint et TypeScript réussis |
| `npm run build` dans Docker, copie isolée du frontend | Compilation de production réussie, pages et service worker générés |
| Tests de fermetures/calendrier sous `TZ=America/Los_Angeles` | 49 tests ciblés réussis |
| Régression API bêta et PostgreSQL | 6 tests réussis, 43 assertions |
| PHPStan et CS Fixer ciblés sur le correctif bêta | Réussis |
| Revue indépendante du diff final | Aucun défaut important identifié ; revue statique |
| `git diff --check` ciblé et liens des captures | Propres, six liens présents |

La première exécution frontend avait 720 succès et deux échecs sur l’URL photo
codée en dur. Les tests de régression ont également reproduit leurs défauts
avant correction. Les journaux de la suite contiennent encore des avertissements
React `act(...)` et des messages d’erreurs simulées par les tests négatifs ; ils
ne sont pas présentés comme des erreurs de recette Chrome.

Le build a été effectué dans `/tmp/kadhia-web-build.CMmEih` **dans le conteneur**,
avec copie des sources, configurations, fichiers publics et worker, et un lien
vers les dépendances déjà installées. Il n’a pas remplacé le `.next` du serveur
de développement pendant les parcours navigateur.

## Captures de contrôle

- [Profil client arabe à 390 px](evidence/web-2026-10-03/profile-ar.png).
- [Retrait finalisé côté client](evidence/web-2026-10-03/client-completed.png).
- [Fermeture et bilan de génération](evidence/web-2026-10-03/slots-closure.png).
- [Compte marchand relu](evidence/web-2026-10-03/merchant-account.png).
- [Métriques bêta réparées](evidence/web-2026-10-03/admin-beta-metrics.png).
- [Supervision avec file vide](evidence/web-2026-10-03/admin-ops.png).

## Limites et suites

- Google/Facebook ne sont **pas intégrés au front web**. La configuration
  Google Cloud/Meta et le domaine HTTPS de callback ne sont pas encore prêts.
  Les travaux OAuth précédents concernent le mobile ; aucun succès OAuth web
  n’est revendiqué ici.
- La génération récurrente fonctionne à la demande sur 1 ou 3 mois ; aucune
  planification automatique en arrière-plan n’a été ajoutée.
- Chrome local ne valide pas Safari/iOS, une caméra physique, l’installation
  PWA, les notifications push sur un appareil ni le fonctionnement hors ligne.
- Acceptation partielle, refus, annulation, clôture forcée, retrait par code à
  quatre chiffres et envois WhatsApp/SMS non rejoués dans cette recette Chrome ;
  les suites automatisées existantes correspondantes ont été exécutées quand
  présentes. Aucune revendication de couverture exhaustive de ces variantes.
- La supervision révèle trois messages Messenger déjà en échec dans
  l’environnement local. Leur traitement opérationnel n’a pas été modifié.
- Pas de nouvelle dépendance de production, ni migration pour ces corrections
  web. Aucun commit, push ou déploiement effectué.
