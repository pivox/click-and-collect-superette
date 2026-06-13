<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\FeedbackCurrentSettingsOutput;
use App\Entity\User;
use App\Enum\FeedbackAppArea;
use App\Service\FeedbackSettingsManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<FeedbackCurrentSettingsOutput>
 */
final readonly class FeedbackCurrentSettingsProvider implements ProviderInterface
{
    public function __construct(
        private FeedbackSettingsManager $feedbackSettingsManager,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FeedbackCurrentSettingsOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $appArea = FeedbackAppArea::tryFrom((string) $request?->query->get('appArea', ''));
        if (null === $appArea) {
            throw new BadRequestHttpException('FEEDBACK_INVALID_APP_AREA');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('FeedbackCurrentSettingsProvider called without authenticated User.');
        }

        $setting = $this->feedbackSettingsManager->current();

        return new FeedbackCurrentSettingsOutput(
            id: 'feedback-current-settings',
            enabled: $setting->isEnabledFor($this->feedbackSettingsManager->userRole($user), $appArea),
            appArea: $appArea->value,
            appSubArea: $this->normalizeNullableString($request?->query->get('appSubArea')),
            requireAuthenticatedUser: $setting->requiresAuthenticatedUser(),
        );
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return mb_substr(trim($value), 0, 100);
    }
}
