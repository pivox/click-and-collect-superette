# API Resource Design

Conçois ou révise une ressource API Platform pour le projet.

Lis d'abord `AI_CONTEXT.md`, `README.md` et la documentation métier pertinente.

Produis :

1. Nom de la ressource.
2. Rôle concerné : client, marchand, admin.
3. Opérations API nécessaires.
4. Groupes de sérialisation.
5. DTO d'entrée/sortie si nécessaire.
6. Provider/Processor si nécessaire.
7. Règles de validation.
8. Règles de sécurité.
9. Migration Doctrine éventuelle.
10. Tests à prévoir.
11. Risques MVP.

Contraintes :

- Ne pas ajouter paiement ou livraison sans demande explicite.
- Préserver Kadhia, TND, français/arabe.
- Séparer référentiel produit et offre marchand.
