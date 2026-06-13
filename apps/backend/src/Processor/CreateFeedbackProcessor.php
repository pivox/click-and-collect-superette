<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FeedbackOutput;
use App\Dto\FeedbackCreateInput;
use App\Entity\FeedbackEntry;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\CustomerShopStatus;
use App\Enum\FeedbackAppArea;
use App\Enum\FeedbackType;
use App\Provider\FeedbackOutputFactory;
use App\Repository\CustomerShopRepository;
use App\Repository\ShopRepository;
use App\Service\FeedbackSettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<FeedbackCreateInput, FeedbackOutput>
 */
final readonly class CreateFeedbackProcessor implements ProcessorInterface
{
    private const int USER_AGENT_MAX_LENGTH = 500;

    public function __construct(
        private FeedbackSettingsManager $feedbackSettingsManager,
        private ShopRepository $shopRepository,
        private CustomerShopRepository $customerShopRepository,
        private FeedbackOutputFactory $outputFactory,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FeedbackOutput
    {
        if (!$data instanceof FeedbackCreateInput) {
            throw new \InvalidArgumentException('FeedbackCreateInput expected.');
        }
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('FEEDBACK_AUTHENTICATED_USER_REQUIRED');
        }

        $appArea = FeedbackAppArea::from((string) $data->appArea);
        if (!$this->feedbackSettingsManager->isEnabledFor($user, $appArea)) {
            throw new AccessDeniedHttpException('FEEDBACK_DISABLED');
        }
        $message = trim((string) $data->message);
        if (mb_strlen($message) < 5 || mb_strlen($message) > 2000) {
            throw new UnprocessableEntityHttpException('FEEDBACK_INVALID_MESSAGE');
        }

        $feedback = new FeedbackEntry(
            type: FeedbackType::from((string) $data->type),
            message: $message,
            appArea: $appArea,
            appSubArea: $this->normalizeNullableString($data->appSubArea, 100),
            userRole: $this->feedbackSettingsManager->userRole($user),
            user: $user,
            shop: $this->resolveShop($data->shopId, $user),
            pageUrl: $this->normalizeNullableString($data->pageUrl, 2048),
            routeName: $this->normalizeNullableString($data->routeName, 150),
            pageTitle: $this->normalizeNullableString($data->pageTitle, 255),
            locale: $this->normalizeNullableString($data->locale, 10),
            viewportWidth: $data->viewportWidth,
            viewportHeight: $data->viewportHeight,
            userAgent: $this->normalizeNullableString($this->requestStack->getCurrentRequest()?->headers->get('User-Agent'), self::USER_AGENT_MAX_LENGTH),
            contactConsent: $data->contactConsent,
        );

        $this->entityManager->persist($feedback);
        $this->entityManager->flush();

        return $this->outputFactory->fromFeedback($feedback);
    }

    private function resolveShop(?string $shopId, User $user): ?Shop
    {
        if (null === $shopId || '' === $shopId) {
            return null;
        }
        $shop = $this->shopRepository->find($shopId);
        if (!$shop instanceof Shop) {
            throw new BadRequestHttpException('FEEDBACK_INVALID_SHOP');
        }
        if (!$this->canAttachShop($shop, $user)) {
            throw new AccessDeniedHttpException('FEEDBACK_SHOP_FORBIDDEN');
        }

        return $shop;
    }

    private function canAttachShop(Shop $shop, User $user): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($this->security->isGranted('ROLE_MERCHANT')) {
            return $shop->getOwner()?->getId()->equals($user->getId()) ?? false;
        }

        if (!$this->security->isGranted('ROLE_CUSTOMER')) {
            return false;
        }

        $relation = $this->customerShopRepository->findOneByCustomerAndShop($user, $shop);

        return null !== $relation && CustomerShopStatus::Active === $relation->getStatus() && $shop->isActive();
    }

    private function normalizeNullableString(?string $value, int $maxLength): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return mb_substr(trim($value), 0, $maxLength);
    }
}
