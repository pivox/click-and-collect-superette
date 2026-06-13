<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\FeedbackOutput;
use App\Entity\FeedbackEntry;
use App\Repository\FeedbackEntryRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<FeedbackOutput>
 */
final readonly class AdminFeedbackItemProvider implements ProviderInterface
{
    public function __construct(
        private FeedbackEntryRepository $feedbackEntryRepository,
        private FeedbackOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FeedbackOutput
    {
        return $this->outputFactory->fromFeedback($this->resolveFeedback((string) ($uriVariables['feedbackId'] ?? '')));
    }

    public function resolveFeedback(string $feedbackId): FeedbackEntry
    {
        if (!Uuid::isValid($feedbackId)) {
            throw new NotFoundHttpException('ADMIN_FEEDBACK_NOT_FOUND');
        }
        $feedback = $this->feedbackEntryRepository->find($feedbackId);
        if (!$feedback instanceof FeedbackEntry) {
            throw new NotFoundHttpException('ADMIN_FEEDBACK_NOT_FOUND');
        }

        return $feedback;
    }
}
