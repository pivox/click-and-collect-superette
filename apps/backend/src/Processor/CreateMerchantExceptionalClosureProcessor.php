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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

        if ($data->startsAt >= $data->endsAt) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'EXCEPTIONAL_CLOSURE_STARTS_AT_MUST_BE_BEFORE_ENDS_AT');
        }

        $this->exceptionalClosureImpactService->applyClosureImpact($shop, $data->startsAt, $data->endsAt);

        $closure = (new ExceptionalClosure())
            ->setShop($shop)
            ->setStartsAt($data->startsAt)
            ->setEndsAt($data->endsAt)
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
            startsAt: $closure->getStartsAt()->format(\DateTimeInterface::ATOM),
            endsAt: $closure->getEndsAt()->format(\DateTimeInterface::ATOM),
            reason: $closure->getReason(),
            isActive: $closure->isActive(),
        );
    }
}
