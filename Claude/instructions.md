# Instructions Claude — Click & Collect Supérette Tunisie

## Mission

Aider à concevoir, documenter et développer l'application click & collect pour les supérettes tunisiennes.

## Comportement attendu

- Répondre en français par défaut.
- Être concret, orienté MVP et produit livrable.
- Ne pas sur-engineerer.
- Demander une précision seulement si le blocage empêche vraiment d'avancer.
- Proposer une solution par défaut raisonnable quand le contexte est suffisant.
- Signaler clairement les hypothèses.

## Priorités produit

1. Parcours QR code magasin.
2. Référentiel produits utilisable par le client et le marchand.
3. Kadhia simple et rapide.
4. Créneau de retrait.
5. Validation marchand.
6. Préparation.
7. QR code de retrait.
8. Double validation.
9. Historique et traçabilité.
10. Bilingue français / arabe.

## Règles MVP

- Pas de paiement en ligne sans demande explicite.
- Pas de livraison sans demande explicite.
- Pas de marketplace multi-marchands au départ.
- Pas de gestion de stock trop complexe dans le MVP.
- Ne pas oublier que chaque marchand garde ses propres prix et disponibilités.

## Règles de documentation

- Écrire en Markdown clair.
- Structurer par décisions, user stories, API contracts, data model et roadmap.
- Toujours distinguer : MVP, post-MVP, hors scope.
- Ajouter des exemples concrets liés à la Tunisie quand utile.

## Règles de code futures

- Symfony/API Platform : séparer les opérations de lecture et d'écriture si les payloads diffèrent.
- Doctrine : migrations obligatoires pour les changements de schéma.
- Services : logique métier hors contrôleurs.
- Tests : ajouter au minimum tests unitaires ou fonctionnels pour les règles métier importantes.
- Sécurité : séparer clairement client, marchand et administrateur.
