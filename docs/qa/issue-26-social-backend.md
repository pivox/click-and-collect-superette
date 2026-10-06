# Connexion sociale mobile — backend #26

## Contrat et configuration

Le navigateur système ouvre l’URL retournée par `POST /api/auth/social/start`.
Le mobile génère un vérificateur PKCE aléatoire et transmet uniquement son SHA-256
encodé base64url sans padding (`code_challenge`). `mode` vaut `login` ou `link`.
L’association exige un client ou marchand connecté, au départ et à l’échange.

Configurer uniquement sur le serveur : `SOCIAL_GOOGLE_CLIENT_ID`,
`SOCIAL_GOOGLE_CLIENT_SECRET`, `SOCIAL_FACEBOOK_CLIENT_ID`,
`SOCIAL_FACEBOOK_CLIENT_SECRET`, `SOCIAL_CALLBACK_ORIGIN` (origine HTTPS).
Chaque fournisseur sans ses deux identifiants reste désactivé et absent de
`GET /api/auth/social/providers` (`{"providers":[]}` par défaut).

Enregistrer exactement les callbacks HTTPS chez les fournisseurs :
`https://<origine-api>/api/auth/social/callback/google` et
`https://<origine-api>/api/auth/social/callback/facebook`.
La cible mobile est fixe : `kadhia://social-auth`; aucune URL de redirection
fournie par le client n’est acceptée.

`start` renvoie `{authorization_url,state}`. Le callback redirige vers
`kadhia://social-auth?code=...&state=...` après vérification serveur.
Le mobile vérifie le state conservé localement, puis appelle
`POST /api/auth/social/exchange` avec `{code,state,code_verifier}`.
Résultat login : `{token,refresh_token,expires_in,password_change_required}`.
Résultat association : `{linked:true}`; la session courante est conservée.

`GET /api/me/auth-methods` accepte client/marchand et renvoie
`{has_password,providers}`. `PATCH /api/me/password` est réservé au client,
reçoit `{current_password,new_password}` et renvoie 204. Les refresh tokens
sont révoqués après changement; le JWT stateless déjà émis expire naturellement,
comme pour le changement de mot de passe marchand existant.

## Sécurité et décisions

- `state` et code mobile aléatoires de 256 bits, conservés uniquement hachés.
- State valable 10 minutes, code mobile 2 minutes après callback; consommation
  conditionnelle atomique en base, liaison S256 au vérificateur mobile.
- Identité persistée par `(provider,subject)` unique et un compte par fournisseur
  et utilisateur. Aucune association implicite par email.
- Google : échange serveur du code puis `userinfo` avec bearer serveur; email
  requis et `email_verified=true` pour créer un nouveau client.
- Facebook : identité via code serveur puis `/me`; email jamais considéré comme
  preuve autonome de propriété. Un nouveau compte Facebook passe par inscription
  locale puis association explicite; les comptes déjà associés se reconnectent.
- Nom fournisseur absent : inscription locale nécessaire; aucun nom inventé.
- Pas de création de marchand ou admin par connexion sociale.
- Compte inactif, supprimé ou soumis au changement de mot de passe obligatoire :
  refus. Vérification renouvelée à l’échange. Membership marchand révoquée ou
  organisation inactive : refus, fallback historique sans membership conservé.
- Association : JWT avec preuve HMAC des identifiants courants obligatoire; un JWT
  ancien après reset ne peut pas démarrer une nouvelle association.
  `SOCIAL_REAUTH_REQUIRED` (403) impose une reconnexion.
- Nettoyage borné de 1 000 transactions expirées au maximum à chaque démarrage.
- Les paramètres callback sont masqués dans les logs applicatifs Symfony. Ne pas
  activer profiler/debug en production; configurer les proxies externes pour
  omettre la query callback dans leurs journaux.
- Aucun jeton fournisseur conservé ou transmis au mobile, aucune nouvelle dépendance.
- `SOCIAL_ACCOUNT_LINK_REQUIRED` (409) : utiliser la connexion locale puis associer.
- `SOCIAL_REGISTRATION_REQUIRES_EMAIL` (422) : terminer l’inscription locale puis associer.
- Compte sans mot de passe : `has_password=false`, changement direct refusé;
  le reset par email existant peut établir un mot de passe après preuve email.

## Références et limites terrain

Documentation [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect)
consultée pendant l’implémentation. Documentation officielle
[Meta login flow](https://developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow/)
et [Meta User](https://developers.facebook.com/docs/graph-api/reference/user/)
contactée mais accès HTTP 429 depuis l’outil de recherche.

La validation réelle Google/Meta exige les identifiants serveur, les callbacks
HTTPS enregistrés et un build mobile installable qui ouvre le schéma Kadhia.
Les tests locaux utilisent des réponses HTTP fournisseur contrôlées; ils ne
prouvent pas la configuration des consoles ni le consentement sur appareil.

## Vérifications exécutées le 3 octobre 2026

- `SocialAuthApiTest` : 14 tests, 61 assertions, verts (après ajout du fingerprint refresh).
- Unitaires fournisseur, preuve JWT et masquage logs : 6 tests, 8 assertions, verts.
- `lint:container` et PHPStan sur les classes sociales : verts.
- CS Fixer ciblé : 0 différence sur 16 fichiers.
- Mapping Doctrine : valide (`doctrine:schema:validate --skip-sync`).
- Migration `Version20261003120000` : `up` puis `down` réellement exécutés avec
  PostgreSQL dans un schéma temporaire isolé, supprimé ensuite; aucune migration
  appliquée au schéma applicatif de développement.
- Réel Google/Meta et deep link sur appareil : non exécutés, faute de credentials
  et de build installé configuré dans cette session.

## Activation ultérieure et distribution iOS

L’utilisateur a confirmé le 3 octobre 2026 que les applications Google Cloud/Meta
et l’origine HTTPS ne sont pas encore configurées. Les fournisseurs restent donc
absents de la liste publique et leurs boutons masqués. Les migrations sociales et
refresh ont été appliquées au PostgreSQL de développement pendant l’intégration.
La configuration Nginx du dépôt exclut les callbacks sociaux de l’access log ;
les reverse proxies externes doivent appliquer la même exclusion des paramètres.

Avant distribution App Store avec Google/Facebook activés, traiter la règle
[Apple 4.8 — Login Services](https://developer.apple.com/app-store/review/guidelines/#login-services) :
un service équivalent doit permettre notamment de masquer son e-mail, sauf
exception applicable. Kadhia client ne semble pas relever des exceptions listées
(inférence pour le cadrage de distribution). Sign in with Apple n’a pas été demandé
ni implémenté dans ce lot : l’activation iOS publique reste à cadrer avant soumission.
Consulter également les exigences de déconnexion/révocation du fournisseur en 5.1.1.

Les API utilisées côté mobile sont documentées dans
[Expo 57 WebBrowser](https://docs.expo.dev/versions/v57.0.0/sdk/webbrowser/) et
[Expo 57 Crypto](https://docs.expo.dev/versions/v57.0.0/sdk/crypto/).
La recette doit inclure le retour Android où `dismiss` précède parfois le lien
natif, une interruption complète de l’application, le refus du consentement,
le compte fournisseur déjà associé et la reconnexion après changement de mot de passe.
