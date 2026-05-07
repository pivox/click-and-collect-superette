# US-031 — Voir les informations de la supérette

**Epic** : EPIC-001 — Onboarding par QR code
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Should Have

---

## Récit

En tant que **client**,
je veux **consulter les informations de la supérette** (nom, adresse, horaires, contact),
afin de **savoir si elle est ouverte, où elle se trouve et comment la contacter**.

---

## Préconditions

- Le client a accédé à la supérette (via QR code ou lien direct).
- La supérette est active sur la plateforme.

---

## Scénario nominal

1. Le client arrive sur la page d'accueil de la supérette.
2. Il accède à la fiche d'information de la supérette (lien ou onglet dédié).
3. L'écran affiche :
   - Nom de la supérette.
   - Logo ou photo de façade (si disponible).
   - Adresse complète.
   - Horaires d'ouverture (par jour de la semaine).
   - Numéro de téléphone (optionnel).
4. Le client peut revenir au catalogue depuis cet écran.

---

## Scénarios alternatifs

**Informations incomplètes** :
- Les champs non renseignés (ex : pas de téléphone) ne sont pas affichés.

**Supérette fermée à l'heure actuelle** :
- Un bandeau indique : « Fermé — Ouvre [jour] à [heure] ».
- Le client peut quand même consulter le catalogue et préparer une Kadhia.

---

## Règles métier

- Les horaires d'ouverture sont définis par le marchand lors de l'onboarding.
- Les horaires sont affichés dans le fuseau horaire `Africa/Tunis` (UTC+1).
- Les informations de contact ne permettent pas de passer commande directement (canal hors périmètre MVP).

---

## Critères d'acceptation

- [ ] Le nom, l'adresse et les horaires sont affichés.
- [ ] Les horaires indiquent clairement si la supérette est actuellement ouverte ou fermée.
- [ ] Les informations sont lisibles sur mobile (portrait, 360 px minimum).
- [ ] Les champs vides ne génèrent pas de lignes vides ou de libellés orphelins.

---

## Notes techniques

- Endpoint : `GET /api/stores/{slug}` retourne les champs `name`, `address`, `phone`, `openingHours`, `logoUrl`.
- Les horaires sont stockés sous forme de plages par jour (`openingHours[monday] = { open: "08:00", close: "20:00" }`).
- L'état ouvert/fermé est calculé côté client à partir des horaires et de l'heure locale.
