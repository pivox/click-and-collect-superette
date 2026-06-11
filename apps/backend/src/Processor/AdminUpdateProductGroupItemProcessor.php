<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductGroupItemOutput;
use App\Dto\AdminUpdateProductGroupItemInput;
use App\Enum\ProductGroupItemImportance;
use App\Provider\AdminProductGroupItemProvider;
use App\Repository\AdminProductGroupRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminUpdateProductGroupItemInput, AdminProductGroupItemOutput>
 */
final readonly class AdminUpdateProductGroupItemProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductGroupItemProvider $itemProvider,
        private AdminProductGroupRepository $adminProductGroupRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductGroupItemOutput
    {
        if (!$data instanceof AdminUpdateProductGroupItemInput) {
            throw new \InvalidArgumentException('AdminUpdateProductGroupItemInput expected.');
        }

        $group = $this->itemProvider->findGroup((string) ($uriVariables['groupId'] ?? ''));
        $itemId = (string) ($uriVariables['itemId'] ?? '');
        if (!Uuid::isValid($itemId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_GROUP_NOT_FOUND');
        }

        $item = $this->adminProductGroupRepository->findItem($group, $itemId);
        if (null === $item) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_GROUP_NOT_FOUND');
        }

        $payload = $this->currentPayload();
        if (\array_key_exists('sortOrder', $payload) && null !== $data->sortOrder) {
            $item->setSortOrder((int) $data->sortOrder);
        }
        if (\array_key_exists('importance', $payload) && null !== $data->importance) {
            $item->setImportance(ProductGroupItemImportance::from($data->importance));
        }

        $this->adminProductGroupRepository->save($item);

        return AdminProductGroupItemProvider::itemToOutput($item);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || '' === $request->getContent()) {
            return [];
        }

        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
    }
}
