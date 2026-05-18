<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PickupReminderSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:orders:send-pickup-reminders',
    description: 'Sends in-app pickup reminder notifications to customers whose order slot starts within 1 hour.',
)]
final class SendPickupRemindersCommand extends Command
{
    public function __construct(private readonly PickupReminderSender $sender)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sent = $this->sender->sendDueReminders();

        $output->writeln(\sprintf('sent_reminders: %d', $sent));

        return Command::SUCCESS;
    }
}
