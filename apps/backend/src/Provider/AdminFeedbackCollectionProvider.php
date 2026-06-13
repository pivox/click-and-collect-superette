<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminFeedbackListOutput;
use App\Entity\Shop;
use App\Enum\FeedbackAppArea;
use App\Enum\FeedbackStatus;
use App\Enum\FeedbackType;
use App\Repository\FeedbackEntryRepository;
use App\Repository\ShopRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminFeedbackListOutput>
 */
final readonly class AdminFeedbackCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 50;

    public function __construct(
        private FeedbackEntryRepository $feedbackEntryRepository,
        private ShopRepository $shopRepository,
        private FeedbackOutputFactory $outputFactory,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminFeedbackListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->parsePositiveInt($request?->query->get('page'), self::DEFAULT_PAGE, 'ADMIN_FEEDBACK_INVALID_PAGE');
        $limit = min(self::MAX_LIMIT, $this->parsePositiveInt($request?->query->get('limit'), self::DEFAULT_LIMIT, 'ADMIN_FEEDBACK_INVALID_LIMIT'));
        $status = $this->parseEnum($request?->query->get('status'), FeedbackStatus::class, 'ADMIN_FEEDBACK_INVALID_STATUS');
        $userRole = $this->parseUserRole($request?->query->get('userRole'));
        $type = $this->parseEnum($request?->query->get('type'), FeedbackType::class, 'ADMIN_FEEDBACK_INVALID_TYPE');
        $appArea = $this->parseEnum($request?->query->get('appArea'), FeedbackAppArea::class, 'ADMIN_FEEDBACK_INVALID_APP_AREA');
        $shop = $this->resolveShop($request?->query->get('shop'));
        $contactConsent = $this->parseNullableBool($request?->query->get('contactConsent'));
        $createdFrom = $this->parseNullableDate($request?->query->get('createdFrom'), 'ADMIN_FEEDBACK_INVALID_CREATED_FROM');
        $createdTo = $this->parseNullableDate($request?->query->get('createdTo'), 'ADMIN_FEEDBACK_INVALID_CREATED_TO');
        $offset = ($page - 1) * $limit;

        $items = array_map(
            fn ($feedback) => $this->outputFactory->fromFeedback($feedback),
            $this->feedbackEntryRepository->findAdminPaginated($limit, $offset, $status, $userRole, $type, $appArea, $shop, $contactConsent, $createdFrom, $createdTo),
        );

        return new AdminFeedbackListOutput(
            id: 'admin-feedbacks',
            items: $items,
            page: $page,
            limit: $limit,
            total: $this->feedbackEntryRepository->countAdmin($status, $userRole, $type, $appArea, $shop, $contactConsent, $createdFrom, $createdTo),
        );
    }

    private function parsePositiveInt(mixed $raw, int $default, string $errorCode): int
    {
        if (null === $raw || '' === $raw) {
            return $default;
        }
        if (false === filter_var($raw, \FILTER_VALIDATE_INT)) {
            throw new BadRequestHttpException($errorCode);
        }
        $value = (int) $raw;
        if ($value < 1) {
            throw new BadRequestHttpException($errorCode);
        }

        return $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return T|null
     */
    private function parseEnum(mixed $raw, string $enumClass, string $errorCode): ?\BackedEnum
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        $value = $enumClass::tryFrom((string) $raw);
        if (null === $value) {
            throw new BadRequestHttpException($errorCode);
        }

        return $value;
    }

    private function parseUserRole(mixed $raw): ?string
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        $role = (string) $raw;
        if (!\in_array($role, ['client', 'merchant', 'admin'], true)) {
            throw new BadRequestHttpException('ADMIN_FEEDBACK_INVALID_USER_ROLE');
        }

        return $role;
    }

    private function resolveShop(mixed $raw): ?Shop
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        $value = (string) $raw;
        if (!Uuid::isValid($value)) {
            throw new BadRequestHttpException('ADMIN_FEEDBACK_INVALID_SHOP');
        }
        $shop = $this->shopRepository->find($value);
        if (!$shop instanceof Shop) {
            throw new BadRequestHttpException('ADMIN_FEEDBACK_INVALID_SHOP');
        }

        return $shop;
    }

    private function parseNullableBool(mixed $raw): ?bool
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        $value = filter_var($raw, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if (null === $value) {
            throw new BadRequestHttpException('ADMIN_FEEDBACK_INVALID_CONTACT_CONSENT');
        }

        return $value;
    }

    private function parseNullableDate(mixed $raw, string $errorCode): ?\DateTimeImmutable
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        try {
            return new \DateTimeImmutable((string) $raw);
        } catch (\Exception) {
            throw new BadRequestHttpException($errorCode);
        }
    }
}
