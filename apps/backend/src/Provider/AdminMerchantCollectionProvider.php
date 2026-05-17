<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminMerchantListOutput;
use App\Repository\AdminMerchantRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProviderInterface<AdminMerchantListOutput>
 */
final readonly class AdminMerchantCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private AdminMerchantRepository $adminMerchantRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminMerchantListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_MERCHANT_INVALID_PAGE');
        $limit = $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_MERCHANT_INVALID_LIMIT');
        $limit = min(self::MAX_LIMIT, $limit);
        $offset = ($page - 1) * $limit;

        $merchants = $this->adminMerchantRepository->findPaginated($limit, $offset);
        $items = array_map(
            fn ($merchant) => AdminMerchantItemProvider::toOutput(
                $merchant,
                $this->adminMerchantRepository->countStores($merchant),
            ),
            $merchants,
        );

        return new AdminMerchantListOutput(
            id: 'admin-merchants',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->adminMerchantRepository->countAll(),
        );
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }

        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new UnprocessableEntityHttpException($errorCode);
        }

        $value = (int) $raw;
        if ($value < 1) {
            throw new UnprocessableEntityHttpException($errorCode);
        }

        return $value;
    }
}
