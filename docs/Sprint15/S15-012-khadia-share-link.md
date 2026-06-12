# S15-012 — Rendre une khadia partageable via lien

Issue GitHub : #487  
Statut : cadrage PO / documentation  
Sprint : Sprint 15 — Monétisation, support, exploitation & onboarding catalogue  
Date de cadrage : 2026-06-12  
Rôles concernés : client

---

## 1. Objectif produit

Permettre à un utilisateur de partager une même khadia avec une autre personne via un lien unique.

Exemple métier : une femme prépare une khadia depuis son compte, génère un lien de partage et l'envoie à son mari. Lorsque le mari ouvre le lien, la khadia devient visible dans son espace également. Les deux utilisateurs travaillent ensuite sur la même khadia.

Le partage ne doit pas créer une copie indépendante. La khadia reste une entité commune, visible et modifiable par tous les utilisateurs rattachés.

---

## 2. Vision fonctionnelle

### 2.1 Création du lien

Depuis une khadia existante, l'utilisateur peut déclencher une action de partage.

Le système génère un lien unique rattaché à cette khadia.

Comportement attendu :

```text
- l'utilisateur ouvre une khadia ;
- il clique sur une action de partage ;
- le système génère un lien ;
- l'utilisateur peut copier ou transmettre ce lien.
```

### 2.2 Ouverture du lien

Lorsqu'un autre utilisateur ouvre le lien :

```text
- si l'utilisateur est connecté, la khadia est rattachée à son compte ;
- si l'utilisateur n'est pas connecté, il est redirigé vers la connexion ou la création de compte ;
- après authentification, le rattachement à la khadia est finalisé ;
- la khadia apparaît dans son espace personnel.
```

### 2.3 Collaboration

Une khadia partagée reste commune.

Les modifications faites par un utilisateur doivent être visibles par les autres utilisateurs rattachés :

```text
- ajout d'un produit ;
- modification d'une quantité ;
- suppression d'un produit ;
- mise à jour globale de la khadia ;
- validation selon les règles existantes du parcours.
```

---

## 3. Règles métier

- Une khadia peut être partagée via un lien unique.
- Une khadia partagée reste une seule entité commune.
- Le lien permet à un autre utilisateur de rejoindre la khadia.
- L'ouverture du lien ne doit pas dupliquer la khadia.
- Une même personne ne doit pas pouvoir rattacher plusieurs fois la même khadia à son compte.
- Les utilisateurs rattachés à la khadia ont les mêmes droits dans le MVP.
- Les modifications sont communes à tous les membres de la khadia.
- Un lien invalide ou expiré doit afficher un message clair.
- Un utilisateur non connecté doit être redirigé vers l'authentification avant rattachement.

---

## 4. Droits MVP

Pour le MVP, il n'y a pas de gestion fine des permissions.

Tous les membres rattachés à une khadia partagée disposent des mêmes droits fonctionnels.

Rôle possible côté modèle :

```text
editor
```

Le créateur initial peut aussi être modélisé comme membre de la khadia afin d'unifier les règles d'accès.

---

## 5. Proposition de modèle technique

Modèle possible :

```text
Khadia
User
KhadiaMember
KhadiaShareToken
```

### 5.1 KhadiaMember

Table de liaison entre un utilisateur et une khadia.

Champs possibles :

```text
id
khadia_id
user_id
role
joined_at
created_at
updated_at
```

Contrainte attendue :

```text
unique(khadia_id, user_id)
```

Cette contrainte évite qu'un utilisateur rejoigne plusieurs fois la même khadia.

### 5.2 KhadiaShareToken

Token de partage rattaché à une khadia.

Champs possibles :

```text
id
khadia_id
token
created_by
created_at
expires_at
used_at nullable
revoked_at nullable
```

Contraintes attendues :

```text
unique(token)
index(khadia_id)
index(expires_at)
```

---

## 6. API attendue

### 6.1 Générer un lien de partage

Endpoint possible :

```text
POST /api/khadias/{id}/share-links
```

Réponse possible :

```json
{
  "shareUrl": "https://example.com/khadia/share/{token}",
  "expiresAt": "2026-06-19T23:59:59+02:00"
}
```

### 6.2 Rejoindre une khadia via token

Endpoint possible :

```text
POST /api/khadia-share-links/{token}/join
```

Réponse possible :

```json
{
  "khadiaId": "uuid",
  "joined": true
}
```

---

## 7. UX attendue

### 7.1 Depuis la khadia

Ajouter une action visible :

```text
Partager la khadia
```

Au clic :

```text
- générer ou récupérer le lien actif ;
- afficher le lien ;
- permettre la copie ;
- afficher un message de confirmation.
```

### 7.2 Depuis le lien reçu

Lorsqu'un utilisateur ouvre le lien :

```text
- afficher une page de confirmation ;
- indiquer qu'il va rejoindre une khadia partagée ;
- demander connexion si nécessaire ;
- finaliser le rattachement ;
- rediriger vers la khadia.
```

Messages possibles :

```text
Vous avez rejoint cette khadia partagée.
Ce lien de partage est invalide ou expiré.
Connectez-vous pour rejoindre cette khadia.
```

---

## 8. Critères d'acceptation

### 8.1 Génération du lien

- [ ] Depuis une khadia existante, l'utilisateur peut générer un lien de partage.
- [ ] Le lien généré est unique.
- [ ] Le lien est rattaché à la khadia concernée.
- [ ] Le lien peut être copié ou transmis.

### 8.2 Ouverture du lien

- [ ] Un utilisateur connecté peut ouvrir un lien de partage valide.
- [ ] La khadia est ajoutée à son espace personnel.
- [ ] La khadia apparaît dans la liste de ses khadias.
- [ ] La khadia n'est pas dupliquée si l'utilisateur ouvre plusieurs fois le même lien.

### 8.3 Utilisateur non connecté

- [ ] Un utilisateur non connecté qui ouvre un lien est redirigé vers la connexion.
- [ ] Après connexion, le rattachement à la khadia est finalisé.
- [ ] Après rattachement, l'utilisateur est redirigé vers la khadia.

### 8.4 Collaboration

- [ ] Les deux utilisateurs voient la même khadia.
- [ ] Un produit ajouté par l'un est visible par l'autre.
- [ ] Une quantité modifiée par l'un est visible par l'autre.
- [ ] Un produit supprimé par l'un est supprimé pour l'autre.
- [ ] Les deux utilisateurs ont les mêmes droits fonctionnels sur la khadia dans le MVP.

### 8.5 Gestion des erreurs

- [ ] Un lien invalide affiche un message clair.
- [ ] Un lien expiré affiche un message clair.
- [ ] Un utilisateur déjà rattaché ne crée pas de doublon.
- [ ] Un utilisateur sans droit ne peut pas générer un lien pour une khadia inaccessible.

---

## 9. Tâches techniques

- [ ] Créer ou adapter le modèle de liaison `KhadiaMember`.
- [ ] Créer le modèle `KhadiaShareToken`.
- [ ] Ajouter la migration de base de données.
- [ ] Ajouter l'endpoint de génération du lien.
- [ ] Ajouter l'endpoint de rattachement via token.
- [ ] Adapter la récupération des khadias pour inclure les khadias partagées.
- [ ] Adapter les règles d'accès pour utiliser l'appartenance à la khadia.
- [ ] Gérer le parcours utilisateur non connecté.
- [ ] Ajouter les messages UX d'erreur et de confirmation.
- [ ] Ajouter les tests fonctionnels API.
- [ ] Ajouter les tests de sécurité.

---

## 10. Hors périmètre MVP

- Droits avancés par utilisateur.
- Mode lecture seule.
- Historique détaillé des modifications.
- Notifications temps réel.
- Chat ou commentaires entre membres.
- Révocation avancée membre par membre.

---

## 11. Points d'attention PO

Le mot `kadhia` / `khadia` doit être harmonisé dans l'interface et dans le modèle technique.

La fonctionnalité doit rester simple pour le MVP : partager, rejoindre, collaborer avec droits équivalents.

Les permissions avancées pourront être traitées dans une issue séparée si nécessaire.
