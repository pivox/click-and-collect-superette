<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ProductAiEnrichmentRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:products:ai-enrichment:run',
    description: 'Plan, submit and apply periodic OpenAI product enrichment batches',
)]
final class ProductAiEnrichmentRunCommand extends Command
{
    public function __construct(
        private readonly ProductAiEnrichmentRunner $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of incomplete products to plan', '100')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Maximum number of pending jobs to submit in one OpenAI batch', '100')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'OpenAI model for Responses API structured outputs', $this->env('OPENAI_PRODUCT_ENRICHMENT_MODEL') ?? 'gpt-4o-mini')
            ->addOption('max-active-batches', null, InputOption::VALUE_REQUIRED, 'Maximum active OpenAI batches at a time', $this->env('OPENAI_PRODUCT_MAX_ACTIVE_BATCHES') ?? '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->runner->run(
            apiKey: $this->env('OPENAI_API_KEY') ?? '',
            model: (string) $input->getOption('model'),
            limit: max(1, (int) $input->getOption('limit')),
            batchSize: max(1, (int) $input->getOption('batch-size')),
            maxActiveBatches: max(1, (int) $input->getOption('max-active-batches')),
        );

        if ($result->openAiSkipped) {
            $io->warning('OPENAI_API_KEY is missing. Jobs were planned but no OpenAI batch was submitted.');
        } else {
            $io->success('Product AI enrichment run complete.');
        }

        $io->definitionList(
            ['jobs_created' => (string) $result->jobsCreated],
            ['jobs_submitted' => (string) $result->jobsSubmitted],
            ['jobs_applied_total' => (string) $result->jobsApplied],
            ['jobs_failed_total' => (string) $result->jobsFailed],
            ['active_batches_checked' => (string) $result->activeBatchesChecked],
        );

        return Command::SUCCESS;
    }

    private function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if (false === $value || '' === trim((string) $value)) {
            return null;
        }

        return (string) $value;
    }
}
