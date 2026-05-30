<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantExceptionalClosureOutput;
use App\Dto\MerchantExceptionalClosureCreateInput;
use App\Entity\ExceptionalClosure;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\ExceptionalClosureImpactService;
use App\Service\PickupSlotDisplayTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantExceptionalClosureCreateInput, MerchantExceptionalClosureOutput>
 */
final readonly class CreateMerchantExceptionalClosureProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private ExceptionalClosureImpactService $exceptionalClosureImpactService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantExceptionalClosureOutput
    {
        if (!$data instanceof MerchantExceptionalClosureCreateInput) {
            throw new \InvalidArgumentException('MerchantExceptionalClosureCreateInput expected.');
        }
        if (null === $data->startsAt || null === $data->endsAt) {
            throw new \InvalidArgumentException('Validated merchant exceptional closure payload expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $startsAt = PickupSlotDisplayTime::fromPayloadInstant($data->startsAt);
        $endsAt = PickupSlotDisplayTime::fromPayloadInstant($data->endsAt);

        $this->exceptionalClosureImpactService->applyClosureImpact($shop, $startsAt, $endsAt);

        $closure = (new ExceptionalClosure())
            ->setShop($shop)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setReason($data->reason)
            ->setActive(true);

        $this->entityManager->persist($closure);
        $this->entityManager->flush();

        return $this->toOutput($closure);
    }

    private function toOutput(ExceptionalClosure $closure): MerchantExceptionalClosureOutput
    {
        return new MerchantExceptionalClosureOutput(
            id: $closure->getId()->toRfc4122(),
            startsAt: PickupSlotDisplayTime::toLocalAtom($closure->getStartsAt()),
            endsAt: PickupSlotDisplayTime::toLocalAtom($closure->getEndsAt()),
            reason: $closure->getReason(),
            isActive: $closure->isActive(),
        );
    }
}
