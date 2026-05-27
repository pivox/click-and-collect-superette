<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KadhiaListItemOutput;
use App\ApiResource\KadhiaListOutput;
use App\Entity\Kadhia;
use App\Entity\KadhiaLine;
use App\Entity\User;
use App\Repository\KadhiaRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<KadhiaListOutput>
 */
final readonly class KadhiaCollectionProvider implements ProviderInterface
{
    private const int PER_PAGE = 20;

    public function __construct(
        private KadhiaRepository $kadhiaRepository,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): KadhiaListOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $request = $this->requestStack->getCurrentRequest();
        $status = $request?->query->get('status') ?: null;
        // storeId may come from the URI path (/me/stores/{storeId}/kadhias) or from a query param
        $shopId = isset($uriVariables['storeId']) && \is_string($uriVariables['storeId'])
            ? $uriVariables['storeId']
            : ($request?->query->get('store_id') ?: null);
        $page = max(1, (int) ($request?->query->get('page') ?? 1));

        $kadhias = $this->kadhiaRepository->findByCustomerWithFilters($user, $status, $shopId, $page, self::PER_PAGE);
        $total = $this->kadhiaRepository->countByCustomerWithFilters($user, $status, $shopId);
        $pages = (int) ceil($total / self::PER_PAGE) ?: 1;

        $items = array_map(
            static fn (Kadhia $k): KadhiaListItemOutput => new KadhiaListItemOutput(
                id: $k->getId()->toRfc4122(),
                storeId: $k->getShop()->getId()->toRfc4122(),
                storeName: $k->getShop()->getName(),
                status: $k->getStatus()->value,
                linesCount: $k->getLines()->count(),
                totalTnd: self::computeTotal($k),
                createdAt: $k->getCreatedAt()->format(\DateTimeInterface::ATOM),
                updatedAt: $k->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ),
            $kadhias,
        );

        return new KadhiaListOutput(
            items: $items,
            total: $total,
            page: $page,
            perPage: self::PER_PAGE,
            pages: $pages,
        );
    }

    private static function computeTotal(Kadhia $kadhia): string
    {
        $total = '0.000';
        /** @var KadhiaLine $line */
        foreach ($kadhia->getLines() as $line) {
            $total = bcadd($total, bcmul($line->getUnitPriceTnd(), (string) $line->getQuantity(), 3), 3);
        }

        return $total;
    }
}
