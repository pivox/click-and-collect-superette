<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FeedbackAppArea;
use App\Enum\FeedbackStatus;
use App\Enum\FeedbackType;
use App\Repository\FeedbackEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FeedbackEntryRepository::class)]
#[ORM\Table(name: 'feedback_entries')]
#[ORM\Index(name: 'IDX_FEEDBACK_ENTRIES_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_FEEDBACK_ENTRIES_TYPE', columns: ['type'])]
#[ORM\Index(name: 'IDX_FEEDBACK_ENTRIES_AREA', columns: ['app_area'])]
#[ORM\Index(name: 'IDX_FEEDBACK_ENTRIES_USER_ROLE', columns: ['user_role'])]
#[ORM\Index(name: 'IDX_FEEDBACK_ENTRIES_USER', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_FEEDBACK_ENTRIES_SHOP', columns: ['shop_id'])]
#[ORM\Index(name: 'IDX_FEEDBACK_ENTRIES_CREATED_AT', columns: ['created_at'])]
class FeedbackEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 20, enumType: FeedbackStatus::class)]
    private FeedbackStatus $status = FeedbackStatus::Unread;

    #[ORM\Column(length: 20, enumType: FeedbackType::class)]
    private FeedbackType $type;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(name: 'app_area', length: 20, enumType: FeedbackAppArea::class)]
    private FeedbackAppArea $appArea;

    #[ORM\Column(name: 'app_sub_area', length: 100, nullable: true)]
    private ?string $appSubArea;

    #[ORM\Column(name: 'user_role', length: 20)]
    private string $userRole;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shop $shop;

    #[ORM\Column(name: 'page_url', length: 2048, nullable: true)]
    private ?string $pageUrl;

    #[ORM\Column(name: 'route_name', length: 150, nullable: true)]
    private ?string $routeName;

    #[ORM\Column(name: 'page_title', length: 255, nullable: true)]
    private ?string $pageTitle;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $locale;

    #[ORM\Column(name: 'viewport_width', nullable: true)]
    private ?int $viewportWidth;

    #[ORM\Column(name: 'viewport_height', nullable: true)]
    private ?int $viewportHeight;

    #[ORM\Column(name: 'user_agent', length: 500, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(name: 'contact_consent')]
    private bool $contactConsent;

    #[ORM\Column(name: 'admin_note', type: 'text', nullable: true)]
    private ?string $adminNote = null;

    #[ORM\Column(name: 'read_at', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(name: 'resolved_at', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        FeedbackType $type,
        string $message,
        FeedbackAppArea $appArea,
        ?string $appSubArea,
        string $userRole,
        User $user,
        ?Shop $shop,
        ?string $pageUrl,
        ?string $routeName,
        ?string $pageTitle,
        ?string $locale,
        ?int $viewportWidth,
        ?int $viewportHeight,
        ?string $userAgent,
        bool $contactConsent,
    ) {
        $this->id = Uuid::v4();
        $this->type = $type;
        $this->message = $message;
        $this->appArea = $appArea;
        $this->appSubArea = $appSubArea;
        $this->userRole = $userRole;
        $this->user = $user;
        $this->shop = $shop;
        $this->pageUrl = $pageUrl;
        $this->routeName = $routeName;
        $this->pageTitle = $pageTitle;
        $this->locale = $locale;
        $this->viewportWidth = $viewportWidth;
        $this->viewportHeight = $viewportHeight;
        $this->userAgent = $userAgent;
        $this->contactConsent = $contactConsent;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getStatus(): FeedbackStatus
    {
        return $this->status;
    }

    public function getType(): FeedbackType
    {
        return $this->type;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getAppArea(): FeedbackAppArea
    {
        return $this->appArea;
    }

    public function getAppSubArea(): ?string
    {
        return $this->appSubArea;
    }

    public function getUserRole(): string
    {
        return $this->userRole;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function getPageUrl(): ?string
    {
        return $this->pageUrl;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getViewportWidth(): ?int
    {
        return $this->viewportWidth;
    }

    public function getViewportHeight(): ?int
    {
        return $this->viewportHeight;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function hasContactConsent(): bool
    {
        return $this->contactConsent;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markRead(?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();
        $this->status = FeedbackStatus::Read;
        $this->readAt ??= $now;
        $this->updatedAt = $now;
    }

    public function markUnread(?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();
        $this->status = FeedbackStatus::Unread;
        $this->readAt = null;
        $this->resolvedAt = null;
        $this->updatedAt = $now;
    }

    public function resolve(?string $adminNote = null, ?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();
        $this->status = FeedbackStatus::Resolved;
        $this->readAt ??= $now;
        $this->resolvedAt = $now;
        $this->adminNote = $adminNote;
        $this->updatedAt = $now;
    }

    public function reopen(?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();
        $this->status = FeedbackStatus::Read;
        $this->readAt ??= $now;
        $this->resolvedAt = null;
        $this->updatedAt = $now;
    }
}
