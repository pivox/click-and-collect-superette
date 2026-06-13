<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FeedbackOutput;
use App\Dto\AdminFeedbackStatusInput;
use App\Provider\AdminFeedbackItemProvider;
use App\Provider\FeedbackOutputFactory;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<mixed, FeedbackOutput>
 */
final readonly class AdminFeedbackStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminFeedbackItemProvider $feedbackProvider,
        private FeedbackOutputFactory $outputFactory,
        private AdminAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FeedbackOutput
    {
        $feedback = $this->feedbackProvider->resolveFeedback((string) ($uriVariables['feedbackId'] ?? ''));
        $previousStatus = $feedback->getStatus()->value;
        $operationName = (string) $operation->getName();
        switch ($operationName) {
            case 'admin_feedback_read':
                $feedback->markRead();
                $auditAction = 'feedback.read';
                break;
            case 'admin_feedback_unread':
                $feedback->markUnread();
                $auditAction = 'feedback.unread';
                break;
            case 'admin_feedback_resolve':
                $feedback->resolve($data instanceof AdminFeedbackStatusInput ? $data->adminNote : null);
                $auditAction = 'feedback.resolve';
                break;
            case 'admin_feedback_reopen':
                $feedback->reopen();
                $auditAction = 'feedback.reopen';
                break;
            default:
                throw new \LogicException('Unsupported feedback status operation.');
        }

        $this->auditLogger->log(
            action: $auditAction,
            resourceType: 'feedback',
            resourceId: $feedback->getId()->toRfc4122(),
            summary: \sprintf('Feedback / Retour %s.', $feedback->getStatus()->value),
            metadata: [
                'previous_status' => $previousStatus,
                'new_status' => $feedback->getStatus()->value,
            ],
        );
        $this->entityManager->flush();

        return $this->outputFactory->fromFeedback($feedback);
    }
}
