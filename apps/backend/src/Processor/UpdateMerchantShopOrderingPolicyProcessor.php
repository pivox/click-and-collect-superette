<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ShopOrderingPolicyOutput;
use App\Dto\ShopOrderingPolicyPatchInput;
use App\Entity\ShopOrderingPolicy;
use App\Entity\User;
use App\Repository\ShopOrderingPolicyRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Upserts the per-shop ordering policy (ORDER-LEAD-001).
 *
 * Last write wins; the unique constraint on shop_id guarantees a single row
 * per shop. Validation runs against the raw payload so every invalid shape
 * (missing field, wrong type, out of range, unknown field) returns a stable
 * 422 code.
 *
 * @implements ProcessorInterface<ShopOrderingPolicyPatchInput, ShopOrderingPolicyOutput>
 */
final readonly class UpdateMerchantShopOrderingPolicyProcessor implements ProcessorInterface
{
    private const FIELD = 'minimum_pickup_lead_time_minutes';

    public function __construct(
        private ShopRepository $shopRepository,
        private ShopOrderingPolicyRepository $shopOrderingPolicyRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private Security $security,
        #[Autowire(service: 'monolog.logger.order')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ShopOrderingPolicyOutput
    {
        if (!$data instanceof ShopOrderingPolicyPatchInput) {
            throw new \InvalidArgumentException('ShopOrderingPolicyPatchInput expected.');
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

        $minutes = $this->validatedLeadTimeMinutes($this->currentPayload());

        $policy = $this->shopOrderingPolicyRepository->findOneByShop($shop);
        $oldMinutes = null === $policy
            ? ShopOrderingPolicy::DEFAULT_MINIMUM_PICKUP_LEAD_TIME_MINUTES
            : $policy->getMinimumPickupLeadTimeMinutes();

        if (null === $policy) {
            $policy = (new ShopOrderingPolicy())->setShop($shop);
            $shop->setOrderingPolicy($policy);
            $this->entityManager->persist($policy);
        }

        $policy->setMinimumPickupLeadTimeMinutes($minutes);

        $this->entityManager->flush();

        if ($oldMinutes !== $minutes) {
            $actor = $this->security->getUser();
            $this->logger->info('shop.ordering_policy.minimum_lead_time.update', [
                'shop_id' => $shop->getId()->toRfc4122(),
                'actor_user_id' => $actor instanceof User ? $actor->getId()->toRfc4122() : null,
                'old_value' => $oldMinutes,
                'new_value' => $minutes,
            ]);
        }

        return ShopOrderingPolicyOutput::fromShop($shop, $policy);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatedLeadTimeMinutes(array $payload): int
    {
        foreach (array_keys($payload) as $key) {
            if (self::FIELD !== $key) {
                throw new UnprocessableEntityHttpException('SHOP_ORDERING_POLICY_UNKNOWN_FIELD');
            }
        }

        if (!\array_key_exists(self::FIELD, $payload)) {
            throw new UnprocessableEntityHttpException('SHOP_ORDERING_POLICY_INVALID_LEAD_TIME');
        }

        $value = $payload[self::FIELD];
        if (!\is_int($value)
            || $value < 0
            || $value > ShopOrderingPolicy::MAX_MINIMUM_PICKUP_LEAD_TIME_MINUTES
        ) {
            throw new UnprocessableEntityHttpException('SHOP_ORDERING_POLICY_INVALID_LEAD_TIME');
        }

        return $value;
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
