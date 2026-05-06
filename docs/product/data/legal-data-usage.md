# Usage des données produit

## Objectif

Définir une position prudente pour constituer le référentiel produit sans dépendre abusivement de contenus propriétaires.

Ce document ne remplace pas un avis juridique. Il sert à cadrer les choix produit et développement du MVP.

## Position produit

Le référentiel produit vise à stocker des informations factuelles et minimales :

- nom du produit ;
- marque ;
- volume ;
- unité ;
- variante ;
- catégorie ;
- pays ou marché cible ;
- code-barres si disponible.

Le MVP ne vise pas à copier des catalogues complets, des images propriétaires ou des descriptions marketing issues d'autres sites.

## Usage en développement

En environnement de développement, des données collectées depuis plusieurs sources peuvent servir de base temporaire pour tester :

- la recherche ;
- la normalisation ;
- l'import CSV ;
- le parcours marchand ;
- le parcours client.

Avant usage en production, les données doivent être nettoyées, revues et normalisées.

## Données autorisées en priorité

- Données créées manuellement par l'équipe.
- Données fournies par les marchands.
- Données fournies par les marques ou fournisseurs avec autorisation.
- Données factuelles minimales nécessaires à l'identification du produit.
- Données issues d'un code-barres lorsque l'usage est compatible avec les droits applicables.

## Données à éviter sans autorisation

- Images produits copiées depuis des sites tiers.
- Descriptions marketing longues.
- Contenus éditoriaux.
- Catalogues complets de concurrents.
- Données tarifaires reprises massivement sans contrôle.

## Règles de prudence MVP

- Ne pas dépendre d'une seule source externe.
- Ne pas reprendre l'identité visuelle ou le contenu éditorial d'un tiers.
- Documenter les sources utilisées pour le seed initial.
- Nettoyer les libellés et ne conserver que les champs nécessaires.
- Prévoir une procédure de correction ou retrait d'un produit.

## Décision MVP

Le référentiel produit démarre avec une base minimale validée et peut être enrichi progressivement via les marchands et l'administration plateforme.
