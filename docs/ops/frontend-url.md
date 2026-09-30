# FRONTEND_URL — comportement par environnement (#543)

`FRONTEND_URL` est l'origine absolue du frontend, utilisée par le backend pour
générer des URLs sortantes. Une valeur erronée produit des QR imprimés et des
liens envoyés par email qui pointent vers la mauvaise origine : cette variable
doit être maîtrisée par environnement.

## Consommateurs

| Usage | Code |
|---|---|
| URL cible du QR magasin (`target_url`, PNG/PDF imprimés) | `MerchantStoreQrTargetUrlFactory` |
| Liens de partage Kadhia (`share_url`) | `KadhiaShareLinkService` |
| Lien d'activation des invitations marchand (email) | `MerchantInvitationEmailSender` |
| CORS (indirect : `CORS_ALLOW_ORIGIN` doit couvrir la même origine) | `docker-compose.yml` |

Tous normalisent la valeur avec `rtrim('/')` : un slash final est toléré.

## Par environnement

### Tests backend (PHPUnit)

**Toujours `http://localhost:3000`, quel que soit l'environnement local.**
`apps/backend/phpunit.dist.xml` force la valeur (`<env name="FRONTEND_URL"
value="http://localhost:3000" force="true"/>` + `<server ... force="true"/>`,
livré par #540). Un override local (`docker-compose.yml`, `.env.local`, shell)
n'affecte donc jamais les assertions des tests `MerchantStoreQrApiTest` et
`KadhiaApiTest`, qui restent **strictes** sur cette origine — ne pas les
assouplir vers « n'importe quelle URL ».

### Développement local (Docker Compose)

Valeur par défaut versionnée : `http://localhost:3000`.

Override local courant pour tester depuis un mobile sur le réseau
local : remplacer par `http://<ip-lan>:3000` (et élargir `CORS_ALLOW_ORIGIN`
en conséquence) dans `docker-compose.yml` **sans committer** cette
modification. Idem pour un tunnel ngrok (`scripts/ngrok-frontend.sh`).
Conséquence assumée : les QR et liens générés pendant la session pointent vers
cette origine temporaire.

### Démo / production

`FRONTEND_URL` doit être une **URL absolue `https://` du domaine public**,
sans chemin ni slash final requis (le slash final est toléré). À vérifier au
déploiement :

1. la variable est définie dans l'environnement du backend **et** du worker
   Messenger (les emails d'invitation partent aussi du worker) ;
2. `CORS_ALLOW_ORIGIN` couvre la même origine ;
3. contrôle rapide : `GET /api/merchant/stores/{storeId}/qr-code` doit
   retourner un `target_url` commençant par l'origine publique.

Un QR magasin est **imprimé** : toute erreur d'origine en production est
coûteuse à corriger. Vérifier `target_url` avant toute impression en série.

## Décision (clôture #543)

- Déterminisme des tests : porté par `phpunit.dist.xml` (`force="true"`),
  livré par #540 et verrouillé par les tests stricts existants ainsi que
  `MerchantStoreQrTargetUrlFactoryTest` (origine exacte, normalisation du
  slash, encodage du token).
- Les overrides locaux (LAN, ngrok) restent supportés pour la QA mobile et
  n'impactent ni les tests ni les autres environnements.
- Aucune validation bloquante au boot n'a été ajoutée : une valeur absente
  fait échouer la compilation du conteneur (paramètre `%env(string:FRONTEND_URL)%`
  requis), ce qui suffit comme garde-fou.
