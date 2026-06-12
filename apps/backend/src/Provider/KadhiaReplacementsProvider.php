<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KadhiaReplacementLineOutput;
use App\ApiResource\KadhiaReplacementsOutput;
use App\Entity\User;
use App\Mapper\StoreCatalogProductBatchMapper;
use App\Repository\KadhiaRepository;
use App\Repository\MerchantProductRepository;
use App\Service\KadhiaSuggestionService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<KadhiaReplacementsOutput>
 */
final readonly class KadhiaReplacementsProvider implements ProviderInterface
{
    public function __construct(
        private KadhiaRepository $kadhiaRepository,
        private MerchantProductRepository $merchantProductRepository,
        private KadhiaSuggestionService $kadhiaSuggestionService,
        private StoreCatalogProductBatchMapper $storeCatalogProductBatchMapper,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): KadhiaReplacementsOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $kadhiaId = (string) ($uriVariables['kadhiaId'] ?? '');
        if (!Uuid::isValid($kadhiaId)) {
            throw new NotFoundHttpException('KADHIA_NOT_FOUND');
        }

        $kadhia = $this->kadhiaRepository->findByIdAndCustomer($kadhiaId, $user);
        if (null === $kadhia) {
            throw new NotFoundHttpException('KADHIA_NOT_FOUND');
        }

        $kadhiaProductIds = [];
        foreach ($kadhia->getLines() as $line) {
            $kadhiaProductIds[] = $line->getMerchantProduct()->getId()->toRfc4122();
        }

        // Orderable products of the shop (public catalog rules), fetched once
        // and shared as the candidate pool for every unavailable line.
        $candidates = null;

        $items = [];
        foreach ($kadhia->getLines() as $line) {
            $merchantProduct = $line->getMerchantProduct();
            if ($this->kadhiaSuggestionService->isSuggestible($merchantProduct)) {
                continue;
            }

            $candidates ??= $this->merchantProductRepository->findPublicCatalogForShop($kadhia->getShop());

            $alternatives = $this->kadhiaSuggestionService->findReplacementsFor(
                $merchantProduct,
                $line->getUnitPriceTnd(),
                $candidates,
                $kadhiaProductIds,
            );

            $items[] = new KadhiaReplacementLineOutput(
                lineId: $line->getId()->toRfc4122(),
                merchantProductId: $merchantProduct->getId()->toRfc4122(),
                productName: $merchantProduct->getDisplayNameFr(),
                quantity: $line->getQuantity(),
                alternatives: $this->storeCatalogProductBatchMapper->toOutputs($alternatives),
            );
        }

        return new KadhiaReplacementsOutput(
            id: $kadhia->getId()->toRfc4122(),
            items: $items,
        );
    }
}
