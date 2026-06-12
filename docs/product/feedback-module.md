# Module Feedback / Retour — Spécification produit

Issue liée : #482 — S15-011 — Module Feedback / Retour activable par rôle et page  
Statut : cadrage produit  
Date : 2026-06-12

---

## 1. Objectif

Le module technique `Feedback` permet à un utilisateur connecté de faire un retour depuis la page qu'il consulte.

Décision wording PO :

```text
Nom technique : Feedback
Libellé visible utilisateur : Retour
Titre formulaire : Votre retour
CTA : Envoyer
Message succès : Merci, votre retour a bien été envoyé.
```

Utilisateurs concernés :

```text
- client ;
- marchand ;
- admin.
```

Le retour doit être contextualisé automatiquement afin que l'équipe produit/support sache depuis quelle page il a été envoyé.

---

## 2. Positionnement

Le module est un outil d'amélioration continue.

Il sert à identifier :

```text
- bugs ;
- incompréhensions ;
- irritants UX ;
- idées d'amélioration ;
- besoins terrain remontés par les marchands.
```

Il ne remplace pas :

```text
- un chat support ;
- un outil de ticketing complet ;
- un outil de suivi développeur ;
- un système de capture vidéo.
```

---

## 3. Parcours utilisateur

### Client

Pages typiques :

```text
- catalogue ;
- Kadhia ;
- choix créneau ;
- suivi commande ;
- retrait ;
- profil.
```

Exemples de retours :

```text
- Je ne comprends pas pourquoi ce produit est indisponible.
- Le choix du créneau n'est pas clair.
- Le bouton de validation est difficile à trouver sur mobile.
```

### Marchand

Pages typiques :

```text
- commandes ;
- détail commande ;
- scan / retrait ;
- catalogue marchand ;
- import catalogue ;
- groupements de produits ;
- promotions ;
- paramètres supérette.
```

Exemples de retours :

```text
- Je ne comprends pas comment publier un produit importé.
- Le filtre produit à compléter n'est pas assez visible.
- Le scan n'est pas pratique sur téléphone.
```

### Admin

Pages typiques :

```text
- dashboard admin ;
- marchands ;
- supérettes ;
- référentiel produit ;
- promotions ;
- audit logs ;
- feedbacks.
```

Exemples de retours :

```text
- Il manque un filtre ville.
- Je veux voir les retours non résolus en priorité.
- Cette colonne n'est pas utile sur mobile.
```

---

## 4. Configuration admin

Le module doit être activable depuis l'administration.

Paramètres MVP :

```text
- activation globale ;
- activation client ;
- activation marchand ;
- activation admin ;
- activation par zone fonctionnelle ;
- mode utilisateurs connectés uniquement.
```

Décision recommandée : démarrer avec un module réservé aux utilisateurs connectés.

---

## 5. Widget frontend

Le widget doit être intégré dans les layouts client, marchand et admin.

Composants techniques recommandés :

```text
FeedbackProvider
FeedbackButton
FeedbackDrawer
FeedbackForm
FeedbackSuccessState
FeedbackErrorState
```

Libellés visibles utilisateur :

```text
Bouton latéral : Retour
Titre formulaire : Votre retour
CTA : Envoyer
Message succès : Merci, votre retour a bien été envoyé.
```

Le bouton :

```text
- est fixe à droite sur desktop ;
- affiche Retour ;
- est orienté verticalement ;
- ouvre un drawer ou une modal légère ;
- s'adapte au mobile sans masquer les actions principales.
```

---

## 6. Formulaire

Champs visibles :

```text
type de retour
message
accord optionnel pour être recontacté
```

Types de retour :

```text
bug
idée
incompréhension
autre
```

Contraintes recommandées :

```text
message minimum : 5 caractères
message maximum : 2000 caractères
```

Contexte automatique :

```text
page
zone applicative
rôle
utilisateur si disponible
supérette si disponible
langue
appareil approximatif
contactConsent
date de création
```

Décision importante : si la case `accord optionnel pour être recontacté` est affichée, le choix doit être persisté via `contactConsent` pour que l'admin sache s'il peut recontacter l'utilisateur.

---

## 7. Backoffice admin

Entrées recommandées :

```text
Admin > Feedbacks > Liste
Admin > Feedbacks > Détail
Admin > Paramètres > Feedback
```

KPI utiles :

```text
- feedbacks non lus ;
- feedbacks non résolus ;
- feedbacks marchands ;
- feedbacks clients ;
- feedbacks reçus cette semaine.
```

Filtres prioritaires :

```text
- statut ;
- rôle ;
- type ;
- zone ;
- période ;
- supérette.
```

Colonnes de liste :

```text
- lu ;
- résolu ;
- type ;
- rôle ;
- zone ;
- page ;
- utilisateur ;
- supérette ;
- consentement contact ;
- extrait message ;
- date.
```

---

## 8. Statuts

Statuts recommandés :

```text
unread
read
resolved
```

Transitions :

```text
unread → read
read → unread
read → resolved
resolved → read
```

Signification :

```text
unread   = retour non encore consulté
read     = retour lu mais pas forcément traité
resolved = retour traité, classé ou corrigé
```

Décision PO importante : `lu` et `résolu` ne doivent pas être fusionnés.

---

## 9. Confidentialité

Le MVP doit rester sobre.

Autorisé :

```text
- message saisi volontairement ;
- type de retour ;
- consentement contact ;
- page ;
- rôle ;
- utilisateur connecté ;
- supérette active si applicable ;
- appareil approximatif ;
- langue ;
- date.
```

Non prévu dans le MVP :

```text
- capture d'écran automatique ;
- pièce jointe ;
- enregistrement vidéo ;
- historique complet de navigation ;
- capture automatique du contenu des formulaires.
```

---

## 10. Backlog technique recommandé

Backend :

```text
- configuration feedback ;
- entité feedback avec contact_consent ;
- création feedback ;
- lecture configuration courante ;
- liste admin ;
- détail admin ;
- actions lu / non lu / résolu / réouvert ;
- tests de droits.
```

Frontend :

```text
- bouton Retour ;
- drawer formulaire Votre retour ;
- CTA Envoyer ;
- intégration layouts ;
- page admin liste ;
- page admin détail ;
- page admin paramètres ;
- adaptation mobile ;
- libellés FR/AR.
```

QA :

```text
- module désactivé : aucun bouton ;
- module activé client : bouton Retour visible côté client ;
- module activé marchand : bouton Retour visible côté marchand ;
- module activé admin : bouton Retour visible côté admin ;
- rôle non autorisé : pas de bouton ;
- message vide refusé ;
- contactConsent persisté si la case est affichée ;
- feedback visible admin après envoi ;
- mark read fonctionne ;
- resolve fonctionne ;
- reopen fonctionne ;
- mobile : bouton non bloquant.
```

---

## 11. Maquettes PO de référence

Les écrans à produire ou conserver comme référence design :

```text
1. Admin — activation du module Feedback.
2. Client — bouton latéral Retour sur une page.
3. Marchand — formulaire Votre retour en drawer.
4. Admin — liste des feedbacks.
5. Admin — détail, lu/résolu, note interne, consentement contact.
6. Mobile — bouton Retour + formulaire adapté.
```

---

## 12. Décisions MVP

```text
- Le module est activable par l'admin.
- Le bouton visible est Retour.
- Le formulaire visible est Votre retour.
- Le CTA visible est Envoyer.
- Le feedback technique reste le nom interne.
- Le retour est contextualisé automatiquement.
- Le consentement contact est persisté via contactConsent.
- Le MVP ne capture pas d'écran automatiquement.
- Le MVP ne crée pas d'issue automatiquement.
- Lu et résolu sont deux notions distinctes.
- Le retour est un signal produit/support, pas un chat temps réel.
```
