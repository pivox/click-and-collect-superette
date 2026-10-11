<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\SubscriptionOutput;
use App\Dto\AdminSubscriptionPricingInput;
use App\Entity\Subscription;
use App\Enum\SubscriptionLifecycle;
use App\Provider\SubscriptionOutputFactory;
use App\Repository\MerchantMembershipRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\AdminAuditLogger;
use App\Service\SubscriptionPricingSchedule;
use App\Service\SubscriptionResolver;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** @implements ProcessorInterface<AdminSubscriptionPricingInput, SubscriptionOutput> */
final readonly class AdminSubscriptionPricingProcessor implements ProcessorInterface
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private UserRepository $users,
        private MerchantMembershipRepository $memberships,
        private SubscriptionResolver $resolver,
        private SubscriptionPricingSchedule $schedule,
        private SubscriptionOutputFactory $output,
        private AdminAuditLogger $audit,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SubscriptionOutput
    {
        if (!$data instanceof AdminSubscriptionPricingInput) {
            throw new \InvalidArgumentException('AdminSubscriptionPricingInput expected.');
        }
        $now = new \DateTimeImmutable();
        $preview = str_ends_with((string) $operation->getName(), '_preview');
        $creation = !isset($uriVariables['subscriptionId']);
        if ($creation) {
            if (null === $data->merchantId) {
                throw new UnprocessableEntityHttpException('SUBSCRIPTION_MERCHANT_REQUIRED');
            }
            $merchant = $this->users->find($data->merchantId);
            if (null === $merchant || !\in_array('ROLE_MERCHANT', $merchant->getRoles(), true)) {
                throw new NotFoundHttpException('MERCHANT_NOT_FOUND');
            }
            $organization = $this->memberships->findOneActiveByUser($merchant)?->getOrganization();
            $contact = $organization?->getPrimaryAccount() ?? $merchant;
            if (null !== $this->resolver->forMerchantUser($merchant) || null !== $this->resolver->forMerchantUser($contact)) {
                throw new ConflictHttpException('SUBSCRIPTION_ALREADY_EXISTS');
            }
            $subscription = Subscription::startTrial($contact, $now, $organization);
            $effectiveAt = $now;
        } else {
            $subscription = $this->subscriptions->find($uriVariables['subscriptionId']);
            if (null === $subscription) {
                throw new NotFoundHttpException('SUBSCRIPTION_NOT_FOUND');
            }
            if (SubscriptionLifecycle::Cancelled === $subscription->getLifecycle()) {
                throw new ConflictHttpException('SUBSCRIPTION_CANCELLED');
            }
            $effectiveAt = $this->schedule->forSubscription($subscription, $now)->currentPeriodEndsAt;
        }
        $previous = $subscription->getPricingVersions();
        if ($preview) {
            $subscription = clone $subscription;
        }
        $terms = $data->terms($effectiveAt);
        $subscription->schedulePricing($terms, $now);
        if ($preview) {
            return $this->output->fromSubscription($subscription, $effectiveAt);
        }
        $this->schedule->refresh($subscription, $now);
        $this->entityManager->persist($subscription);
        $this->audit->log(action: $creation ? 'subscription.created' : 'subscription.pricing_scheduled', resourceType: 'subscription', resourceId: (string) $subscription->getId(), summary: trim($data->reason), metadata: ['previous_pricing_versions' => $previous, 'pricing_terms' => $terms, 'reason' => trim($data->reason)]);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('SUBSCRIPTION_ALREADY_EXISTS');
        }

        return $this->output->fromSubscription($subscription, $now);
    }
}
