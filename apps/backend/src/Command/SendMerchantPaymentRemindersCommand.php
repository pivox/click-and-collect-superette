<?php

declare(strict_types=1);

namespace App\Command;

use App\Billing\SubscriptionPaymentReminderDispatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:billing:send-payment-reminders',
    description: 'Dispatches merchant platform subscription payment reminder emails for due subscriptions.',
)]
final class SendMerchantPaymentRemindersCommand extends Command
{
    public function __construct(
        private readonly SubscriptionPaymentReminderDispatcher $dispatcher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dispatched = $this->dispatcher->dispatchDueReminders();

        $output->writeln(\sprintf('dispatched_payment_reminders: %d', $dispatched));
        $this->logger->info('billing.payment_reminders_dispatched', ['count' => $dispatched]);

        return Command::SUCCESS;
    }
}
