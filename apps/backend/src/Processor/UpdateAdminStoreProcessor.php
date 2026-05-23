<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminStoreOutput;
use App\ApiResource\AdminStoreOutputFactory;
use App\Dto\AdminStoreUpdateInput;
use App\Entity\User;
use App\Repository\AdminMerchantRepository;
use App\Repository\AdminStoreRepository;
use App\Service\AdminAuditLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminStoreUpdateInput, AdminStoreOutput>
 */
final readonly class UpdateAdminStoreProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminStoreRepository $adminStoreRepository,
        private AdminStoreOutputFactory $adminStoreOutputFactory,
        private AdminMerchantRepository $adminMerchantRepository,
        private RequestStack $requestStack,
        private AdminAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminStoreOutput
    {
        if (!$data instanceof AdminStoreUpdateInput) {
            throw new \InvalidArgumentException('AdminStoreUpdateInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        $shop = $this->adminStoreRepository->findOne($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        $payload = $this->currentPayload();

        if (\array_key_exists('name', $payload) && null !== $data->name) {
            $name = $this->normalizeRequiredString($data->name);
            if ('' === $name) {
                throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('ADMIN_STORE_NAME_BLANK');
            }
            $shop->setName($name);
        }
        if (\array_key_exists('address', $payload)) {
            $shop->setAddress($this->normalizeNullableString($data->address));
        }
        if (\array_key_exists('city', $payload)) {
            $shop->setCity($this->normalizeNullableString($data->city));
        }
        if (\array_key_exists('phone', $payload)) {
            $shop->setPhone($this->normalizeNullableString($data->phone));
        }
        if (\array_key_exists('isActive', $payload) && null !== $data->isActive) {
            if (null !== $shop->getArchivedAt()) {
                throw new ConflictHttpException('ADMIN_STORE_ARCHIVED');
            }
            $shop->setActive((bool) $data->isActive);
        }
        if (\array_key_exists('ownerId', $payload)) {
            $shop->setOwner($this->resolveMerchantOwner($data->ownerId));
        }
        if (\array_key_exists('logoUrl', $payload)) {
            $shop->setLogoUrl($this->normalizeNullableString($data->logoUrl));
        }
        if (\array_key_exists('coverUrl', $payload)) {
            $shop->setCoverUrl($this->normalizeNullableString($data->coverUrl));
        }

        $this->auditLogger->log(
            action: 'store.update',
            resourceType: 'store',
            resourceId: $shop->getId()->toRfc4122(),
            summary: \sprintf('Supérette "%s" modifiée.', $shop->getName()),
            metadata: ['name' => $shop->getName()],
        );
        $this->adminStoreRepository->save($shop);

        return $this->adminStoreOutputFactory->create(
            shop: $shop,
            productsCount: $this->adminStoreRepository->countProducts($shop),
            exceptionalClosuresCount: $this->adminStoreRepository->countActiveExceptionalClosures($shop),
            pickupRulesCount: $this->adminStoreRepository->countActivePickupRules($shop),
        );
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

    private function resolveMerchantOwner(?string $ownerId): ?User
    {
        if (null === $ownerId) {
            return null;
        }

        $owner = $this->adminMerchantRepository->findOne($ownerId);
        if (!$owner instanceof User) {
            throw new NotFoundHttpException('ADMIN_STORE_OWNER_NOT_FOUND');
        }

        return $owner;
    }

    private function normalizeRequiredString(string $value): string
    {
        return trim($value);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
