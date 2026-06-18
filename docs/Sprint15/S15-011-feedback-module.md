# S15-011 — Module Feedback / Retour activable par rôle et page

Issue GitHub : #482  
Statut : cadrage PO / documentation  
Sprint : Sprint 15 — Monétisation, support, exploitation & onboarding catalogue  
Date de cadrage : 2026-06-12  
Rôles concernés : admin, client, marchand

---

## 1. Objectif produit

Créer un module technique `Feedback` transversal, activable depuis l'interface d'administration, pour permettre aux utilisateurs de faire un retour contextualisé sur la page où ils se trouvent.

Décision wording PO :

```text
Nom technique : Feedback
Libellé visible utilisateur : Retour
Titre formulaire : Votre retour
CTA : Envoyer
Message succès : Merci, votre retour a bien été envoyé.
```

`Feedback` reste acceptable uniquement pour le nom technique, les routes, les fichiers ou les entités : `FeedbackEntry`, `FeedbackSetting`, `/api/feedback`.

Le module doit servir à capter rapidement les irritants terrain pendant :

```text
- l'activation des supérettes ;
- l'onboarding catalogue ;
- le parcours client ;
- la préparation et le retrait marchand ;
- l'utilisation du backoffice admin.
```

Le module n'est pas un chat support. C'est un outil de collecte de signaux produit, support et qualité.

---

## 2. Vision UX

### 2.1 Bouton latéral

Sur les pages autorisées, afficher un petit bouton fixe à droite de l'écran.

Comportement desktop :

```text
- position : bord droit de la fenêtre ;
- texte visible : Retour ;
- orientation : rotation 90° ;
- style : discret, visible, non bloquant ;
- ouverture : drawer latéral droit.
```

Comportement mobile :

```text
- bouton flottant adapté ;
- libellé visible : Retour ;
- ne doit pas masquer les CTA principaux ;
- ouverture en bottom sheet ou panneau plein écran ;
- fermeture claire.
```

### 2.2 Formulaire utilisateur

Au clic sur le bouton `Retour`, ouvrir un formulaire simple.

Champs MVP visibles :

```text
- titre : Votre retour ;
- type de retour : Bug, Idée, Incompréhension, Autre ;
- remarque obligatoire ;
- consentement optionnel à être recontacté ;
- CTA : Envoyer.
```

Contexte collecté automatiquement :

```text
- URL courante ;
- route applicative si disponible ;
- zone applicative : client, marchand, admin ;
- rôle utilisateur ;
- utilisateur connecté si disponible ;
- supérette active si disponible ;
- langue courante FR/AR ;
- user agent ;
- largeur/hauteur viewport ;
- consentement contact : contactConsent ;
- date de création.
```

Message succès recommandé :

```text
Merci, votre retour a bien été envoyé.
```

Message erreur recommandé :

```text
Votre retour n'a pas pu être envoyé. Vérifiez votre connexion puis réessayez.
```

---

## 3. User stories

### US-085 — Admin — Activer et paramétrer le module Feedback / Retour

En tant qu'admin plateforme, je veux activer ou désactiver le module technique `Feedback` par rôle et par zone afin de contrôler où les utilisateurs peuvent envoyer un retour.

#### Périmètre inclus

```text
- activation globale ;
- activation par rôle : admin, client, marchand ;
- activation par zone : client, marchand, admin ;
- option MVP : retour réservé aux utilisateurs connectés ;
- lecture de la configuration côté frontend.
```

#### Critères d'acceptation

```text
- Un admin peut activer/désactiver le module globalement.
- Un admin peut choisir les rôles autorisés.
- Un admin peut choisir les zones applicatives autorisées.
- Quand le module est désactivé, aucun bouton Retour n'apparaît.
- Quand le module est activé pour le rôle courant, le bouton Retour apparaît sur les pages autorisées.
```

---

### US-086 — Utilisateur — Envoyer un retour depuis une page

En tant qu'utilisateur connecté, je veux envoyer un retour depuis la page où je me trouve afin de signaler un bug, une incompréhension ou une idée sans quitter mon parcours.

#### Périmètre inclus

```text
- bouton vertical Retour ;
- ouverture drawer/modal ;
- formulaire Votre retour ;
- remarque obligatoire ;
- type de retour ;
- consentement contact optionnel persisté ;
- contexte automatique de page ;
- message succès/erreur.
```

#### Critères d'acceptation

```text
- Le bouton affiche Retour avec orientation verticale/rotation 90° sur desktop.
- Au clic, le formulaire Votre retour s'ouvre sans changer de page.
- Le champ remarque est obligatoire.
- Le type est obligatoire ou possède une valeur par défaut autre.
- Le CTA d'envoi est Envoyer.
- Le consentement optionnel à être recontacté est persisté via contactConsent.
- L'utilisateur peut envoyer un retour en moins de 30 secondes.
- Le feedback technique envoyé est persisté et visible dans le backoffice admin.
- Le formulaire ne capture aucun secret ni contenu sensible.
```

---

### US-087 — Admin — Lire, filtrer et traiter les feedbacks

En tant qu'admin support/produit, je veux consulter les feedbacks avec leur contexte, les marquer comme lus ou résolus et conserver une trace de traitement.

#### Périmètre inclus

```text
- liste admin paginée ;
- filtres : lu/non lu, résolu/non résolu, rôle, type, zone, période ;
- tri par date décroissante ;
- détail feedback ;
- affichage du consentement contact ;
- action marquer comme lu ;
- action marquer comme non lu ;
- action résoudre ;
- action réouvrir ;
- note admin interne optionnelle.
```

#### Critères d'acceptation

```text
- La liste admin affiche les feedbacks récents en premier.
- L'admin peut filtrer les feedbacks non lus.
- L'admin peut filtrer les feedbacks non résolus.
- L'admin peut ouvrir le détail d'un feedback.
- Le détail affiche le message complet, le contexte de page et contactConsent.
- L'admin peut marquer un feedback comme lu.
- L'admin peut marquer un feedback comme résolu.
- Lu et résolu restent deux notions distinctes.
- L'admin peut réouvrir un feedback résolu.
```

---

## 4. Règles métier

### Règle 1 — Activation centralisée

Le module doit être piloté par une configuration admin.

```text
is_enabled = false
→ aucun bouton Retour ne doit être visible.
```

### Règle 2 — Rôle utilisateur

La visibilité du bouton dépend du rôle courant.

```text
ROLE_CUSTOMER  → pages client autorisées
ROLE_MERCHANT  → pages marchand autorisées
ROLE_ADMIN     → pages admin autorisées
```

Un utilisateur multi-rôle doit être évalué selon la zone applicative active.

### Règle 3 — Zone applicative

La zone applicative doit être capturée pour faciliter le tri support.

Valeurs recommandées :

```text
client
merchant
admin
```

Sous-zones optionnelles :

```text
catalog
cart
checkout
orders
pickup
merchant_catalog
merchant_orders
admin_catalog
admin_feedback
admin_billing
```

### Règle 4 — Statuts de traitement

Le module doit distinguer la lecture et la résolution.

```text
unread → read
read → resolved
resolved → read si réouvert
```

Un feedback peut être :

```text
- non lu et non résolu ;
- lu mais non résolu ;
- lu et résolu.
```

### Règle 5 — Consentement contact

Si le formulaire affiche une case pour être recontacté, le choix doit être persisté.

```text
contactConsent = true  → l'utilisateur accepte d'être recontacté
contactConsent = false → l'utilisateur n'a pas donné cet accord
```

L'admin doit voir cette information dans le détail avant de contacter l'utilisateur.

### Règle 6 — Données sensibles

Le module ne doit jamais capturer automatiquement :

```text
- mot de passe ;
- token JWT ;
- secret technique ;
- donnée bancaire ;
- contenu complet de formulaire sensible ;
- screenshot ou session replay dans le MVP.
```

---

## 5. Modèle fonctionnel cible

### FeedbackSetting

```text
id
is_enabled boolean
enabled_for_admin boolean
enabled_for_customer boolean
enabled_for_merchant boolean
enabled_zones json array
require_authenticated_user boolean
created_at
updated_at
```

### FeedbackEntry

```text
id
status unread|read|resolved
feedback_type bug|idea|confusing|other
message text
contact_consent boolean default false
role string
user_id nullable
store_id nullable
page_url string
route_name nullable
page_title nullable
app_area client|merchant|admin
app_sub_area nullable
locale fr|ar
user_agent nullable
viewport_width nullable
viewport_height nullable
admin_note nullable
read_at nullable
read_by nullable
resolved_at nullable
resolved_by nullable
created_at
updated_at
```

### FeedbackStatusHistory optionnel

À prévoir si l'équipe veut un historique détaillé sans dépendre uniquement de l'audit trail général.

```text
id
feedback_id
from_status nullable
to_status
changed_by
note nullable
created_at
```

---

## 6. Contrats API cibles

### Configuration courante frontend

```http
GET /api/feedback/settings/current?appArea=merchant&appSubArea=merchant_orders
```

Réponse :

```json
{
  "enabled": true,
  "appArea": "merchant",
  "appSubArea": "merchant_orders",
  "allowedFeedbackTypes": ["bug", "idea", "confusing", "other"],
  "displayLabels": {
    "button": "Retour",
    "formTitle": "Votre retour",
    "submit": "Envoyer",
    "success": "Merci, votre retour a bien été envoyé."
  },
  "requireAuthenticatedUser": true
}
```

Règles :

```text
- Route accessible à l'utilisateur courant.
- La réponse est déjà filtrée selon le rôle.
- Aucun détail interne de configuration admin inutile n'est exposé.
```

### Création feedback

```http
POST /api/feedback
```

Payload :

```json
{
  "type": "bug",
  "message": "Le bouton valider n'est pas clair sur cette page.",
  "contactConsent": true,
  "pageUrl": "https://app.clickcollect.tn/merchant/orders/123",
  "routeName": "merchant.orders.detail",
  "pageTitle": "Détail commande",
  "appArea": "merchant",
  "appSubArea": "merchant_orders",
  "storeId": "uuid-nullable",
  "locale": "fr",
  "viewport": {
    "width": 390,
    "height": 844
  }
}
```

Réponse `201` :

```json
{
  "id": "feedback-uuid",
  "status": "unread",
  "contactConsent": true,
  "createdAt": "2026-06-12T12:00:00+02:00"
}
```

Erreurs attendues :

```text
400 FEEDBACK_MESSAGE_REQUIRED
403 FEEDBACK_DISABLED_FOR_ROLE
403 FEEDBACK_DISABLED_FOR_AREA
422 FEEDBACK_MESSAGE_TOO_LONG
```

### Admin — configuration

```http
GET /api/admin/feedback/settings
PUT /api/admin/feedback/settings
```

Payload `PUT` :

```json
{
  "isEnabled": true,
  "enabledForAdmin": true,
  "enabledForCustomer": true,
  "enabledForMerchant": true,
  "enabledZones": ["client", "merchant", "admin"],
  "requireAuthenticatedUser": true
}
```

### Admin — feedbacks

```http
GET   /api/admin/feedbacks?page=1&limit=20&status=unread&role=ROLE_MERCHANT&appArea=merchant
GET   /api/admin/feedbacks/{feedbackId}
PATCH /api/admin/feedbacks/{feedbackId}/read
PATCH /api/admin/feedbacks/{feedbackId}/unread
PATCH /api/admin/feedbacks/{feedbackId}/resolve
PATCH /api/admin/feedbacks/{feedbackId}/reopen
```

Payload `resolve` :

```json
{
  "adminNote": "Pris en compte dans le backlog support."
}
```

---

## 7. Interfaces attendues

### 7.1 Admin — Paramètres feedback

Écran :

```text
Admin > Paramètres > Feedback
```

Sections :

```text
- Activation globale ;
- Rôles autorisés ;
- Zones autorisées ;
- Mode connecté uniquement ;
- Aperçu du bouton Retour.
```

### 7.2 Utilisateur — Bouton + formulaire

Le bouton doit apparaître dans le layout global des zones activées.

Structure recommandée :

```text
FeedbackButton      # composant technique
→ FeedbackDrawer    # composant technique
  → FeedbackTypeSelect
  → MessageTextarea
  → ContactConsentCheckbox
  → SubmitButton
```

Libellés visibles attendus :

```text
Bouton latéral : Retour
Titre formulaire : Votre retour
CTA : Envoyer
Message succès : Merci, votre retour a bien été envoyé.
```

### 7.3 Admin — Liste feedbacks

Colonnes recommandées :

```text
- statut lu ;
- statut résolu ;
- type ;
- rôle ;
- zone ;
- page ;
- utilisateur ;
- supérette ;
- consentement contact ;
- extrait message ;
- date création.
```

Actions rapides :

```text
- ouvrir ;
- marquer lu ;
- résoudre ;
- réouvrir.
```

### 7.4 Admin — Détail feedback

Blocs :

```text
- message complet ;
- contexte page ;
- contexte utilisateur ;
- consentement contact ;
- contexte device ;
- historique traitement ;
- note admin ;
- actions lu/résolu/réouvert.
```

---

## 8. Accessibilité et i18n

### Accessibilité

```text
- bouton atteignable au clavier ;
- libellé accessible : Envoyer un retour ;
- focus piégé dans le drawer/modal ;
- fermeture via Escape ;
- aria-expanded / aria-controls si drawer ;
- contraste suffisant ;
- ne pas masquer les CTA principaux sur mobile.
```

### i18n

Libellés FR MVP :

```text
Retour
Votre retour
Type de retour
Bug
Idée
Je ne comprends pas
Autre
Votre remarque
J'accepte d'être recontacté à propos de ce retour
Envoyer
Merci, votre retour a bien été envoyé.
```

Libellés AR à prévoir dans le système i18n existant, avec respect du RTL.

---

## 9. Hors périmètre

```text
- screenshot automatique ;
- upload de fichiers ;
- enregistrement vidéo/session replay ;
- chat support ;
- SLA support ;
- création automatique d'issue GitHub ;
- classification IA ;
- feedback anonyme public sans connexion ;
- notifications push admin.
```

---

## 10. Dépendances

```text
- Auth JWT existante ;
- rôles ROLE_CUSTOMER, ROLE_MERCHANT, ROLE_ADMIN ;
- layouts frontend client/marchand/admin ;
- backoffice admin ;
- audit trail admin si disponible ;
- i18n FR/AR ;
- logs frontend existants pour corrélation éventuelle.
```

---

## 11. Risques et garde-fous

### Risque : bouton intrusif

Garde-fou : ne pas masquer les CTA critiques, en particulier commande, retrait, scan et actions marchand.

### Risque : bruit support

Garde-fou : type obligatoire, contexte automatique, filtres admin.

### Risque : données sensibles

Garde-fou : pas de capture formulaire, pas de screenshot, pas de session replay.

### Risque : confusion entre lu et résolu

Garde-fou : deux états/action séparés dans l'interface admin.

### Risque : feedback sans contexte exploitable

Garde-fou : capturer automatiquement page, zone, rôle, utilisateur, supérette et device approximatif.

### Risque : wording trop technique

Garde-fou : `Feedback` reste technique ; l'UI affiche `Retour`, `Votre retour`, `Envoyer`.

---

## 12. Critère de sortie S15-011

```text
Un admin peut activer le module Feedback.
Un client, marchand ou admin autorisé voit le bouton Retour sur les pages activées.
L'utilisateur peut envoyer une remarque contextualisée depuis le formulaire Votre retour.
Le choix contactConsent est persisté si la case de contact est affichée.
L'admin peut consulter, filtrer, marquer comme lu, résoudre et réouvrir les feedbacks.
Le module respecte les garde-fous de confidentialité et ne capture aucun secret.
```

---

## 13. Ordre recommandé d'exécution

```text
1. Modèle + configuration admin FeedbackSetting.
2. Modèle FeedbackEntry avec contact_consent + endpoint POST /api/feedback.
3. Endpoint GET /api/feedback/settings/current.
4. Backoffice admin liste/détail/statuts.
5. Bouton Retour global + drawer frontend client/marchand/admin.
6. Tests sécurité, rôle, visibilité, wording et accessibilité.
7. Documentation API consolidée si l'implémentation retient ces endpoints.
```
