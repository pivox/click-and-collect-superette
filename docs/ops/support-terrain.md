# Runbook support terrain — beta Kadhia

## Objectif

Donner a l'equipe exploitation un process manuel commun pour traiter les cas terrain recurrents pendant la beta Kadhia, sans ajouter d'automatisation hors perimetre.

Ce runbook couvre les situations support liees aux Kadhia, au retrait, aux incidents marchand/client et au suivi d'abonnement marchand. Il s'appuie sur les outils livres :

- Backoffice incidents : `/admin/incidents`
- Fiche marchand admin et journal operationnel : `/admin/marchands`
- Facturation / relances / contact WhatsApp admin : `/admin/facturation`
- Monitoring jobs async : `/admin/ops/messenger`
- Checklist activation supérette : `/api/admin/stores/{storeId}/activation-checklist`

## Principes

- Ne pas promettre de livraison, de paiement en ligne ni de remboursement automatique : le MVP reste click & collect avec paiement hors plateforme.
- Garder le vocabulaire produit : Kadhia, supérette, marchand, client, rendez-vous, retrait.
- Un incident S12 est rattache a une commande existante. Les cas abonnement sans commande ne creent pas d'incident commande ; ils sont suivis via facturation, abonnement, notes admin et journal operationnel.
- Toujours noter l'action support dans l'outil disponible : note incident, note admin marchand, ou trace facturation.

## Statuts et types de reference

### Incidents commande

| Type incident | Usage terrain |
|---|---|
| `customer_absent` | Client absent au rendez-vous ou retrait non finalise cote client. |
| `merchant_late` | Marchand en retard de traitement ou de preparation. |
| `missing_product` | Produit manquant au moment de preparer la Kadhia. |
| `price_error` | Prix catalogue different du prix constate avant validation. |
| `order_not_prepared` | Commande acceptee mais pas prete au moment du retrait. |
| `qr_issue` | QR magasin ou QR de retrait impossible a scanner / verifier. |
| `late_cancellation` | Annulation tardive apres validation ou proche du rendez-vous. |
| `other` | Cas lie a une commande qui ne rentre pas dans les types precedents. |

| Statut incident | Regle d'usage |
|---|---|
| `open` | Incident cree, pas encore pris en charge. |
| `in_progress` | Support en cours de contact ou de resolution. |
| `closed` | Decision prise et note finale ajoutee. |

### Statuts metier utiles

| Domaine | Statuts utiles |
|---|---|
| Commande | `submitted`, `accepted`, `partially_accepted`, `rejected`, `preparing`, `ready`, `pickup_pending`, `completed`, `cancelled` |
| Abonnement marchand | `active`, `payment_due`, `grace_period`, `suspended`, `cancelled` |
| Document mensuel | `draft`, `issued`, `paid`, `overdue`, `cancelled` |

## Triage support

1. Identifier la supérette, le marchand, le client et la Kadhia concernee.
2. Verifier la fiche marchand admin : statut actif, lifecycle abonnement, journal operationnel et derniere activite.
3. Si le cas concerne une commande, ouvrir ou consulter l'incident dans `/admin/incidents`.
4. Si le cas concerne l'abonnement, consulter `/admin/facturation` puis la fiche marchand.
5. Passer l'incident en `in_progress` des que le support contacte un marchand ou un client.
6. Cloturer seulement apres decision explicite, avec une note courte : action faite, canal utilise, resultat.

## Cas terrain

### Client absent au rendez-vous

| Champ | Decision |
|---|---|
| Declencheur | La commande est `ready` ou `pickup_pending`, mais le client ne se presente pas au rendez-vous. |
| Type incident | `customer_absent` |
| Statut initial | `open`, puis `in_progress` pendant le contact. |
| Action recommandee | Contacter le client, confirmer s'il souhaite un nouveau rendez-vous, puis demander au marchand de garder ou d'annuler selon faisabilite terrain. |
| Statut commande | Garder `ready` si retrait replanifie rapidement ; passer a `cancelled` seulement si le marchand confirme l'annulation. |
| Canal | Telephone/WhatsApp manuel si disponible ; sinon email client si connu. |
| Cloture | `closed` avec note : client contacte ou non, nouvelle heure de retrait ou annulation. |

### Marchand en retard de validation ou preparation

| Champ | Decision |
|---|---|
| Declencheur | Kadhia reste `submitted`, `accepted`, `preparing` ou `ready` apres le delai attendu ; le journal marchand signale un retard. |
| Type incident | `merchant_late` si retard de reponse ; `order_not_prepared` si la commande acceptee n'est pas prete au retrait. |
| Statut initial | `open` |
| Action recommandee | Contacter le marchand, verifier disponibilite produits et capacite de preparation, puis demander une decision claire : accepter, refuser, preparer ou annuler. |
| Statut commande | Ne pas forcer `completed`. Laisser le marchand traiter ou annuler si la Kadhia ne peut pas etre servie. |
| Canal | WhatsApp/telephone marchand ; note admin sur la fiche marchand si recidive. |
| Cloture | `closed` quand le marchand a traite la commande ou qu'une annulation est confirmee. |

### Produit manquant

| Champ | Decision |
|---|---|
| Declencheur | Le marchand indique qu'un produit de la Kadhia n'est plus disponible. |
| Type incident | `missing_product` |
| Statut initial | `open` |
| Action recommandee | Demander au marchand de proposer une acceptation partielle si le parcours le permet, ou de refuser la commande avec motif clair. |
| Statut commande | `partially_accepted` si le client peut ajuster ; `rejected` si aucun remplacement acceptable ; `cancelled` seulement si la commande etait deja engagee puis abandonnee. |
| Canal | Contact marchand d'abord, client ensuite si decision impacte la Kadhia. |
| Cloture | `closed` apres acceptation partielle, refus motive ou annulation notee. |

### Probleme QR

| Champ | Decision |
|---|---|
| Declencheur | QR magasin illisible, QR de retrait non reconnu, ou scan impossible en magasin. |
| Type incident | `qr_issue` |
| Statut initial | `open` |
| Action recommandee | Identifier le QR concerne : magasin ou retrait. Pour QR magasin, verifier le contrat QR et la checklist activation supérette. Pour QR de retrait, verifier la commande et la session de retrait. |
| Statut commande | Ne pas finaliser sans double validation. Si le client est present et l'identite est confirmee hors outil, demander au marchand de suivre le fallback metier disponible. |
| Canal | Marchand par WhatsApp/telephone ; client si le QR de retrait est cote client. |
| Cloture | `closed` apres regeneration/verification du QR magasin ou resolution du retrait. |

### Annulation tardive

| Champ | Decision |
|---|---|
| Declencheur | Client ou marchand demande une annulation apres validation, proche du rendez-vous ou pendant la preparation. |
| Type incident | `late_cancellation` |
| Statut initial | `open` |
| Action recommandee | Identifier qui annule et pourquoi. Confirmer avec l'autre partie si la Kadhia a deja ete preparee. |
| Statut commande | `cancelled` si l'annulation est acceptee ; garder le statut courant tant que la decision n'est pas confirmee. |
| Canal | Telephone/WhatsApp pour eviter une attente en magasin ; note incident obligatoire. |
| Cloture | `closed` avec cause : client indisponible, marchand indisponible, produit manquant, autre. |

### Marchand en retard de paiement

| Champ | Decision |
|---|---|
| Declencheur | Document mensuel `overdue`, abonnement `payment_due` ou `grace_period`, relance email/WhatsApp a effectuer. |
| Type incident | Non applicable si aucun ordre n'est touche ; fallback `other` seulement si une Kadhia precise subit un blocage support. |
| Statut support | Suivi facturation en cours ; ne pas creer d'incident commande artificiel. |
| Action recommandee | Consulter `/admin/facturation`, verifier le document mensuel, preparer le contact WhatsApp si l'action est disponible, puis noter la relance. |
| Statut abonnement | Conserver `payment_due` ou `grace_period` selon l'etat courant ; ne passer en `suspended` que si la regle de suspension douce est applicable. |
| Canal | Email de relance si deja emis ; WhatsApp manuel via l'ecran facturation pour contact contextualise. |
| Cloture | Cas clos quand paiement manuel est recu/valide ou quand suspension douce est appliquee. |

### Reactivation marchand apres paiement

| Champ | Decision |
|---|---|
| Declencheur | Marchand suspendu ou en grace period annonce un paiement manuel. |
| Type incident | Non applicable par defaut ; fallback `other` si une commande precise est bloquee et doit etre suivie. |
| Statut support | En cours jusqu'a validation admin du paiement. |
| Action recommandee | Verifier le document mensuel, enregistrer/valider le paiement manuel dans l'admin, puis confirmer que le lifecycle abonnement redevient operationnel. |
| Statut abonnement | `active` apres validation de paiement et reactivation ; `suspended` tant que le paiement n'est pas valide. |
| Canal | WhatsApp/telephone marchand pour confirmer reception ; note admin si decalage entre paiement annonce et paiement valide. |
| Cloture | Cas clos apres validation paiement, reactivation visible et note dans le journal marchand. |

## Escalade

Escalader au responsable produit ou technique si :

- un incident reste `open` plus d'une demi-journee ouvrable sans prise en charge ;
- un marchand cumule plusieurs retards ou annulations sur la meme semaine ;
- un QR magasin imprime renvoie vers une mauvaise supérette ;
- une suspension abonnement bloque des Kadhia deja soumises ;
- un job async attendu ne passe pas et `/admin/ops/messenger` indique `degraded`.

## Notes de coherence MVP

- Les relances de paiement restent celles du module abonnement/facturation ; ce runbook ne cree pas d'automatisation nouvelle.
- La suspension douce conserve catalogue, historique, images produits et commandes existantes.
- Les statistiques commerciales riches, CA, top produits et CRM marchand restent hors Sprint 12.
- Toute decision support doit rester lisible depuis les outils admin : incidents, facturation ou fiche marchand.
