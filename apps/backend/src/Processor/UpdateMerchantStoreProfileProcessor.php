<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantStoreProfileOutput;
use App\Dto\MerchantStoreProfileUpdateInput;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantStoreProfileUpdateInput, MerchantStoreProfileOutput>
 */
final readonly class UpdateMerchantStoreProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantStoreProfileOutput
    {
        if (!$data instanceof MerchantStoreProfileUpdateInput) {
            throw new \InvalidArgumentException('MerchantStoreProfileUpdateInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $payload = $this->currentPayload();

        if (\array_key_exists('name', $payload) && null !== $data->name) {
            $name = trim($data->name);
            if ('' === $name) {
                throw new UnprocessableEntityHttpException('MERCHANT_STORE_NAME_BLANK');
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
        if (\array_key_exists('logoUrl', $payload)) {
            $shop->setLogoUrl($this->normalizeNullableString($data->logoUrl));
        }
        if (\array_key_exists('coverUrl', $payload)) {
            $shop->setCoverUrl($this->normalizeNullableString($data->coverUrl));
        }

        $this->entityManager->flush();

        return MerchantStoreProfileOutput::fromShop($shop);
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

    private function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
