# Architecture — Import photo IA catalogue / référentiel

Date : 2026-10-02  
Statut : architecture cible pour #637 à #649.

## 1. Contexte existant

Le dépôt contient déjà :

- `OpenAiMerchantCatalogPhotoImportExtractor` ;
- `MerchantCatalogPhotoImportPreviewer` ;
- `MerchantCatalogPhotoImportCommitter` ;
- `MerchantCatalogWizard` ;
- les routes historiques synchrones de preview/commit ;
- le référentiel `ProductReference` ;
- le catalogue `MerchantProduct` ;
- les produits locaux marchand ;
- un backoffice référentiel avec score qualité, doublons et enrichissement IA.

La cible ne repart pas de zéro : elle transforme l’import photo historique en traitement persistant, multi-fournisseur pendant le pilote, puis mono-fournisseur configurable.

## 2. Séparation des domaines

Conserver deux agrégats métier :

### Import catalogue marchand

```text
MerchantCatalogPhotoImportSession
  ├── Shop
  ├── quota marchand
  ├── images privées
  ├── propositions catalogue
  └── validation → MerchantProduct
```

### Enrichissement référentiel admin

```text
AdminReferenceImageImportSession
  ├── admin auteur
  ├── images privées
  ├── target ProductReference optionnelle
  ├── propositions référentiel
  └── validation → ProductReference
```

Partager les briques techniques, pas les règles métier de quota ou de validation.

## 3. Socle technique partagé

```text
Image uploadée
  → validation
  → normalisation/orientation
  → suppression EXIF/GPS
  → stockage privé
  → manifeste versionné
  → orchestration Messenger
  → Provider Adapter
  → ExtractionResult immuable
  → MatchingEngine
  → contexte métier
```

Briques partagées proposées :

- `PrivateImageStorage` ;
- `ImagePreprocessor` ;
- `AiVisionProviderInterface` ;
- registre `qwen/mistral/openai/gemini` ;
- `ProductReferenceMatchingService` ;
- `AiUsageRecorder` ;
- politique de retry ;
- garde budgétaire ;
- purge/rétention ;
- observabilité.

## 4. Modèle de données cible

Les noms exacts doivent suivre les conventions Doctrine du dépôt.

### Marchand

```text
CatalogPhotoImportSession
- id
- shop_id
- created_by_user_id
- status
- mode
- campaign_id nullable
- configuration_version
- version
- created_at
- updated_at
- cancelled_at nullable

CatalogPhotoImportImage
- id
- session_id
- shop_id
- source_hash
- private_storage_key
- preprocessing_version
- status
- created_at

CatalogPhotoQuotaGrant
- id
- shop_id
- allowance
- source
- validity nullable
- granted_by nullable
- created_at

CatalogPhotoQuotaEntry
- id
- grant_id
- import_image_id
- operation
- quantity
- idempotency_key
- reason
- created_at
```

### Admin

```text
ReferenceImageImportSession
- id
- created_by_admin_id
- status
- purpose
- target_reference_id nullable
- configuration_version
- version
- created_at
- updated_at

ReferenceImageImportAsset
- id
- session_id
- source_hash
- private_storage_key
- preprocessing_version
- status
- created_at
```

Aucun `CatalogPhotoQuotaEntry` pour l’admin.

### Exécutions fournisseurs

```text
CatalogPhotoProviderRun
- id
- import_context
- image_id / asset_id
- provider
- configuration_version
- state
- selected_for_user
- extraction_result_id nullable
- matching_snapshot_id nullable
- started_at nullable
- finished_at nullable

CatalogPhotoProviderAttempt
- id
- run_id
- attempt_number
- idempotency_key
- provider_request_id nullable
- state
- error_code nullable
- started_at
- finished_at nullable
```

### Extraction

```text
ExtractionResult
- provider
- requested_model
- returned_model nullable
- region
- request_id nullable
- prompt_version
- schema_version
- preprocessing_version
- status
- truncated
- usage
- duration_ms
- response_hash
- private_raw_response_reference nullable
```

Les produits détectés conservent les observations d’origine. Ils ne portent jamais directement un `ProductReferenceId` inventé par le modèle.

### Proposition admin

```text
ProductReferenceAiProposal
- id
- source_session_id
- target_reference_id nullable
- proposal_type
- status
- reference_snapshot_version nullable
- provider/configuration versions
- created_by_admin_id
- created_at

ProductReferenceAiProposalField
- proposal_id
- field_name
- current_value
- proposed_value
- evidence_asset_ids
- evidence_text
- decision
- decided_by
- decided_at
```

## 5. Adaptateurs IA

Contrat logique :

```text
extract(ImageDescriptor, ExtractionConfig)
  → ExtractionResult
```

Chaque adaptateur gère :

- URL API ;
- authentification ;
- format image ;
- schéma JSON ;
- paramètres supportés ;
- parsing ;
- erreurs ;
- compteurs usage ;
- request ID ;
- modèle réellement retourné si disponible.

Le contrat métier ne suppose pas que toutes les API sont compatibles OpenAI.

## 6. Orchestration

Symfony Messenger doit utiliser un transport persistant.

Les messages portent uniquement des identifiants :

```text
ProcessCatalogImageMessage(imageId, providerRunId)
```

Jamais les octets de l’image.

Priorités :

1. commandes / notifications métier critiques ;
2. import marchand interactif ;
3. traitements admin massifs.

Un import admin massif ne doit pas saturer le traitement des commandes.

## 7. Modes fournisseur

```text
off
single
benchmark
```

- `benchmark` : quatre fournisseurs, uniquement pour les campagnes explicitement autorisées ;
- `single` : fournisseur principal ;
- `off` : aucun nouvel appel externe.

Le pilote marchand est limité à 10 `Shop`.

Les imports admin ne rejoignent jamais automatiquement cette campagne.

## 8. Matching

Le matching est commun à tous les fournisseurs et aux deux contextes.

Ordre :

1. code-barres exact validé ;
2. identité structurée ;
3. recherche de candidats ;
4. règles de variantes/format/pack ;
5. résultat explicite.

Sortie :

```text
identification:
  matched_reference
  ambiguous
  absent_from_reference
  unidentifiable

catalog_presence:
  not_present
  already_present
  needs_check
```

Conserver la liste des candidats et la version des règles.

## 9. Écriture marchand

Le résultat IA est transformé en brouillon.

À la validation :

- revalider accès `Shop` ;
- relire `ProductReference` ;
- vérifier statut approuvé/non archivé ;
- vérifier si `MerchantProduct` existe déjà ;
- ne pas écraser prix/visibilité/disponibilité existants par défaut ;
- créer seulement les lignes validées ;
- prix valide obligatoire pour mise au catalogue ;
- idempotency key ;
- transaction ;
- rapport par ligne.

## 10. Écriture admin

L’extraction + matching crée une proposition, jamais une écriture directe.

À l’application :

- recharger la référence ;
- comparer à son snapshot ;
- détecter conflit concurrent ;
- appliquer uniquement les champs acceptés ;
- respecter gouvernance, fusion et archivage ;
- écrire l’audit ;
- recalculer le score qualité si nécessaire ;
- conserver provenance.

Les champs d’identité critiques ne sont pas bulk-acceptables par défaut.

## 11. Quota marchand

Le quota de 10 photos est un journal métier par `Shop`.

États :

```text
grant
reserve
consume
release
admin_adjustment
```

Le solde disponible tient compte des réservations.

Quatre fournisseurs sur une photo consomment **un seul crédit marchand**.

## 12. Admin sans quota photo

Le contexte admin ne lit ni n’écrit le quota marchand.

Les limites restent :

- taille/fichier ;
- pixels ;
- images/requête ;
- concurrence ;
- tentatives ;
- recadrages ;
- output maximal ;
- budget.

Le budget appartient à l’exploitation, pas au modèle commercial admin.

## 13. Coûts

Chaque tentative externe produit un `AiUsageEntry`.

Dimensions :

- contexte ;
- session ;
- image ;
- fournisseur ;
- modèle ;
- région ;
- compteur entrée/sortie/cache ;
- prix versionné ;
- coût estimé ;
- coût réconcilié ;
- état inconnu éventuel.

Ne pas enregistrer une consommation inconnue à zéro.

## 14. Stockage

- objets privés ;
- clés opaques ;
- aucun chemin contrôlé par le client ;
- pas de publication directe ;
- liens signés courts si nécessaires ;
- purge idempotente ;
- orientation appliquée avant suppression EXIF ;
- rétention documentée.

## 15. Sécurité

Marchand :

- `ROLE_MERCHANT` ;
- `MerchantShopAccessChecker` ;
- membership active ;
- isolation `Shop`.

Admin :

- `ROLE_ADMIN`.

Tous contextes :

- aucune clé fournisseur frontend ;
- pas de SSRF par URL d’image ;
- limites fichier ;
- validation contenu réel ;
- schéma strict de sortie ;
- texte présent dans l’image traité comme donnée non fiable ;
- aucune capacité outil/écriture donnée au modèle.

## 16. Contrats API cibles

Ces routes sont **cibles** et ne doivent pas être considérées comme livrées tant que le code et le contrat API officiel ne sont pas mis à jour.

### Marchand

```http
GET  /api/merchant/stores/{storeId}/catalog/photo-import/quota
GET  /api/merchant/stores/{storeId}/catalog/photo-import/sessions
POST /api/merchant/stores/{storeId}/catalog/photo-import/sessions
GET  /api/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}
POST /api/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}/images
POST /api/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}/run
POST /api/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}/cancel
GET  /api/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}/proposals
PUT  /api/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}/draft
POST /api/merchant/stores/{storeId}/catalog/photo-import/sessions/{sessionId}/commit
```

### Admin

```http
GET  /api/admin/product-reference-image-imports
POST /api/admin/product-reference-image-imports
GET  /api/admin/product-reference-image-imports/{sessionId}
POST /api/admin/product-reference-image-imports/{sessionId}/images
POST /api/admin/product-reference-image-imports/{sessionId}/run
POST /api/admin/product-reference-image-imports/{sessionId}/cancel

GET   /api/admin/product-reference-ai-proposals
GET   /api/admin/product-reference-ai-proposals/{proposalId}
PATCH /api/admin/product-reference-ai-proposals/{proposalId}
POST  /api/admin/product-reference-ai-proposals/{proposalId}/apply
POST  /api/admin/product-reference-ai-proposals/{proposalId}/reject
```

## 17. Compatibilité avec l’existant

Les routes historiques :

```http
POST /api/merchant/stores/{storeId}/catalog/photo-import/preview
POST /api/merchant/stores/{storeId}/catalog/photo-import/commit
```

existent actuellement.

La nouvelle API session-based ne doit pas modifier silencieusement leur contrat.

Options de migration :

1. conserver les routes historiques comme legacy interne jusqu’à suppression planifiée ;
2. faire évoluer le frontend vers les sessions, puis déprécier explicitement ;
3. empêcher les anciennes routes de contourner quota/budget lorsque la nouvelle fonctionnalité est activée.

## 18. Observabilité

Mesurer :

- sessions ;
- fichiers acceptés/rejetés ;
- temps de file ;
- temps fournisseur ;
- erreurs ;
- retries ;
- coût ;
- propositions ;
- taux de matching ;
- corrections humaines ;
- conflits ;
- créations et mises à jour finales.

Logs sans images, secrets, réponses brutes ou données personnelles inutiles.

## 19. Tests

### Backend

- transitions de session ;
- quota concurrent ;
- idempotence ;
- quatre adaptateurs avec fixtures ;
- retry ciblé ;
- crash worker ;
- budget ;
- matching ;
- commit marchand ;
- proposition admin ;
- conflits ;
- bulk sûr ;
- ownership ;
- `ROLE_ADMIN` ;
- purge.

### Frontend

Voir `docs/product/catalog-photo-import-ui.md`.

### Tests réels

Les appels réels fournisseurs restent hors CI par défaut et exigent clés, budget et données autorisées.
