<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantPickupSlotRuleOutput;
use App\Dto\MerchantPickupSlotRulePatchInput;
use App\Entity\PickupSlotRule;
use App\Repository\PickupSlotRuleRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantPickupSlotRulePatchInput, MerchantPickupSlotRuleOutput>
 */
final readonly class UpdateMerchantPickupSlotRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PickupSlotRuleRepository $pickupSlotRuleRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantPickupSlotRuleOutput
    {
        if (!$data instanceof MerchantPickupSlotRulePatchInput) {
            throw new \InvalidArgumentException('MerchantPickupSlotRulePatchInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $ruleId = (string) ($uriVariables['ruleId'] ?? '');
        if (!Uuid::isValid($storeId) || !Uuid::isValid($ruleId)) {
            throw new NotFoundHttpException('PICKUP_SLOT_RULE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $rule = $this->pickupSlotRuleRepository->findActiveOneForShop($shop, $ruleId);
        if (null === $rule) {
            throw new NotFoundHttpException('PICKUP_SLOT_RULE_NOT_FOUND');
        }

        $weekday = $data->weekday ?? $rule->getWeekday();
        $startTime = null !== $data->startTime ? $this->parseTime($data->startTime, 'PICKUP_SLOT_RULE_INVALID_START_TIME') : $rule->getStartTime();
        $endTime = null !== $data->endTime ? $this->parseTime($data->endTime, 'PICKUP_SLOT_RULE_INVALID_END_TIME') : $rule->getEndTime();

        if ($startTime >= $endTime) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PICKUP_SLOT_RULE_START_TIME_MUST_BE_BEFORE_END_TIME');
        }

        if ($this->pickupSlotRuleRepository->hasActiveDuplicate($shop, $weekday, $startTime, $endTime, $rule)) {
            throw new HttpException(Response::HTTP_CONFLICT, 'PICKUP_SLOT_RULE_ALREADY_EXISTS');
        }

        if (null !== $data->weekday) {
            $rule->setWeekday($data->weekday);
        }
        if (null !== $data->startTime) {
            $rule->setStartTime($startTime);
        }
        if (null !== $data->endTime) {
            $rule->setEndTime($endTime);
        }
        if (null !== $data->capacity) {
            $rule->setCapacity($data->capacity);
        }

        $this->entityManager->flush();

        return $this->toOutput($rule);
    }

    private function parseTime(string $value, string $errorCode): \DateTimeImmutable
    {
        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$time instanceof \DateTimeImmutable || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $errorCode);
        }

        return $time;
    }

    private function toOutput(PickupSlotRule $rule): MerchantPickupSlotRuleOutput
    {
        return new MerchantPickupSlotRuleOutput(
            id: $rule->getId()->toRfc4122(),
            weekday: $rule->getWeekday(),
            startTime: $rule->getStartTime()->format('H:i'),
            endTime: $rule->getEndTime()->format('H:i'),
            capacity: $rule->getCapacity(),
            isActive: $rule->isActive(),
        );
    }
}
