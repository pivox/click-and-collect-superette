<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MerchantOrganizationAuditor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:merchant-organizations:audit',
    description: 'Detects anomalies in the merchant organization/membership model. Read-only: never repairs data.',
)]
final class MerchantOrganizationsAuditCommand extends Command
{
    public function __construct(
        private readonly MerchantOrganizationAuditor $auditor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $anomalies = $this->auditor->audit();

        if ([] === $anomalies) {
            $output->writeln('merchant_organizations_audit: OK');

            return Command::SUCCESS;
        }

        $output->writeln(\sprintf('merchant_organizations_audit: %d anomaly(ies)', \count($anomalies)));
        foreach ($anomalies as $anomaly) {
            $output->writeln($anomaly);
        }

        return Command::FAILURE;
    }
}
