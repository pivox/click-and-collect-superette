<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\Subscription;
use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPricingPhase;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class RefreshSubscriptionPricingPhasesCommandTest extends FunctionalApiTestCase
{
    public function testRefreshesDuePricingPhaseWithoutChangingLifecycle(): void
    {
        $merchant = $this->createUser('merchant-pricing-refresh-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $subscription->setLifecycle(SubscriptionLifecycle::PaymentDue);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $tester = $this->runCommand(['--now' => '2026-09-01T00:00:00+01:00']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('refreshed_subscription_pricing_phases: 1', $tester->getDisplay());

        $this->entityManager->refresh($subscription);
        self::assertSame(SubscriptionLifecycle::PaymentDue, $subscription->getLifecycle());
        self::assertSame(SubscriptionPricingPhase::Promo, $subscription->getPricingPhase());
        self::assertSame('10.000', $subscription->getMonthlyPriceTnd());
        self::assertSame('2026-12-01', $subscription->getNextPhaseChangeAt()?->format('Y-m-d'));
    }

    public function testRefreshesOnlySubscriptionsWhoseNextPhaseChangeIsDue(): void
    {
        $dueMerchant = $this->createUser('merchant-pricing-due-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $freshMerchant = $this->createUser('merchant-pricing-fresh-'.Uuid::v4().'@example.test', ['ROLE_MERCHANT']);
        $dueSubscription = Subscription::startTrial($dueMerchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $freshSubscription = Subscription::startTrial($freshMerchant, new \DateTimeImmutable('2026-10-01T00:00:00+01:00'));
        $this->entityManager->persist($dueSubscription);
        $this->entityManager->persist($freshSubscription);
        $this->entityManager->flush();

        $tester = $this->runCommand(['--now' => '2026-12-01T00:00:00+01:00']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('refreshed_subscription_pricing_phases: 1', $tester->getDisplay());

        $this->entityManager->refresh($dueSubscription);
        $this->entityManager->refresh($freshSubscription);
        self::assertSame(SubscriptionPricingPhase::Standard, $dueSubscription->getPricingPhase());
        self::assertSame('50.000', $dueSubscription->getMonthlyPriceTnd());
        self::assertNull($dueSubscription->getNextPhaseChangeAt());
        self::assertSame(SubscriptionPricingPhase::Trial, $freshSubscription->getPricingPhase());
        self::assertSame('0.000', $freshSubscription->getMonthlyPriceTnd());
        self::assertSame('2027-01-01', $freshSubscription->getNextPhaseChangeAt()?->format('Y-m-d'));
    }

    /**
     * @param array<string, string> $input
     */
    private function runCommand(array $input): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:subscriptions:refresh-pricing-phases');
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }
}
