<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantExceptionalClosureOutput;
use App\Dto\MerchantExceptionalClosurePatchInput;
use App\Entity\ExceptionalClosure;
use App\Repository\ExceptionalClosureRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\ExceptionalClosureImpactService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantExceptionalClosurePatchInput, MerchantExceptionalClosureOutput>
 */
final readonly class UpdateMerchantExceptionalClosureProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ExceptionalClosureRepository $exceptionalClosureRepository,
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
        if (!$data instanceof MerchantExceptionalClosurePatchInput) {
            throw new \InvalidArgumentException('MerchantExceptionalClosurePatchInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $closureId = (string) ($uriVariables['closureId'] ?? '');
        if (!Uuid::isValid($storeId) || !Uuid::isValid($closureId)) {
            throw new NotFoundHttpException('EXCEPTIONAL_CLOSURE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $closure = $this->exceptionalClosureRepository->findActiveOneForShop($shop, $closureId);
        if (null === $closure) {
            throw new NotFoundHttpException('EXCEPTIONAL_CLOSURE_NOT_FOUND');
        }

        $startsAt = $data->startsAt ?? $closure->getStartsAt();
        $endsAt = $data->endsAt ?? $closure->getEndsAt();
        if ($startsAt >= $endsAt) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'EXCEPTIONAL_CLOSURE_STARTS_AT_MUST_BE_BEFORE_ENDS_AT');
        }

        $this->exceptionalClosureImpactService->applyClosureImpact($shop, $startsAt, $endsAt);

        if (null !== $data->startsAt) {
            $closure->setStartsAt($data->startsAt);
        }
        if (null !== $data->endsAt) {
            $closure->setEndsAt($data->endsAt);
        }
        if (null !== $data->reason) {
            $closure->setReason($data->reason);
        }

        $this->entityManager->flush();

        return $this->toOutput($closure);
    }

    private function toOutput(ExceptionalClosure $closure): MerchantExceptionalClosureOutput
    {
        return new MerchantExceptionalClosureOutput(
            id: $closure->getId()->toRfc4122(),
            startsAt: $closure->getStartsAt()->format(\DateTimeInterface::ATOM),
            endsAt: $closure->getEndsAt()->format(\DateTimeInterface::ATOM),
            reason: $closure->getReason(),
            isActive: $closure->isActive(),
        );
    }
}
