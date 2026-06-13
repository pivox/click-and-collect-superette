<?php

declare(strict_types=1);

namespace App\Provider;

use App\ApiResource\FeedbackOutput;
use App\ApiResource\FeedbackShopOutput;
use App\ApiResource\FeedbackUserOutput;
use App\Entity\FeedbackEntry;

final readonly class FeedbackOutputFactory
{
    public function fromFeedback(FeedbackEntry $feedback): FeedbackOutput
    {
        $shop = $feedback->getShop();

        return new FeedbackOutput(
            id: $feedback->getId()->toRfc4122(),
            status: $feedback->getStatus()->value,
            type: $feedback->getType()->value,
            message: $feedback->getMessage(),
            messageExcerpt: $this->excerpt($feedback->getMessage()),
            appArea: $feedback->getAppArea()->value,
            appSubArea: $feedback->getAppSubArea(),
            userRole: $feedback->getUserRole(),
            user: new FeedbackUserOutput(
                id: $feedback->getUser()->getId()->toRfc4122(),
                email: $feedback->getUser()->getEmail(),
                name: $feedback->getUser()->getName(),
            ),
            shop: null === $shop ? null : new FeedbackShopOutput(
                id: $shop->getId()->toRfc4122(),
                name: $shop->getName(),
            ),
            pageUrl: $feedback->getPageUrl(),
            routeName: $feedback->getRouteName(),
            pageTitle: $feedback->getPageTitle(),
            locale: $feedback->getLocale(),
            viewportWidth: $feedback->getViewportWidth(),
            viewportHeight: $feedback->getViewportHeight(),
            userAgent: $feedback->getUserAgent(),
            contactConsent: $feedback->hasContactConsent(),
            adminNote: $feedback->getAdminNote(),
            readAt: $feedback->getReadAt()?->format(\DateTimeInterface::ATOM),
            resolvedAt: $feedback->getResolvedAt()?->format(\DateTimeInterface::ATOM),
            createdAt: $feedback->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $feedback->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    private function excerpt(string $message): string
    {
        if (mb_strlen($message) <= 140) {
            return $message;
        }

        return mb_substr($message, 0, 137).'...';
    }
}
