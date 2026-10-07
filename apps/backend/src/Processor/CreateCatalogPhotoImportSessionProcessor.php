<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CatalogPhotoImportSessionOutput;
use App\Dto\CreateCatalogPhotoImportSessionInput;
use App\Entity\CatalogPhotoImportSession;
use App\Entity\User;
use App\Mapper\CatalogPhotoImportSessionMapper;
use App\Service\CatalogPhotoImportSessionAccessResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<CreateCatalogPhotoImportSessionInput, CatalogPhotoImportSessionOutput>
 */
final readonly class CreateCatalogPhotoImportSessionProcessor implements ProcessorInterface
{
    public function __construct(
        private CatalogPhotoImportSessionAccessResolver $accessResolver,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private CatalogPhotoImportSessionMapper $mapper,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CatalogPhotoImportSessionOutput
    {
        if (!$data instanceof CreateCatalogPhotoImportSessionInput) {
            throw new \InvalidArgumentException('CreateCatalogPhotoImportSessionInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $shop = $this->accessResolver->resolveShop($storeId);

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_CATALOG_FORBIDDEN');
        }

        $session = new CatalogPhotoImportSession($shop, $user, $data->mode);
        $session->setCampaignId($data->campaignId);
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $this->mapper->toOutput($session);
    }
}
