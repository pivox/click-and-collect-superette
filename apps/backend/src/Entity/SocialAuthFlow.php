<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'social_auth_flows')]
#[ORM\Index(name: 'IDX_SOCIAL_FLOW_EXPIRY', columns: ['expires_at'])]
class SocialAuthFlow
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public Uuid $id;

    #[ORM\Column]
    public \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    public bool $callbackConsumed = false;

    #[ORM\Column]
    public bool $exchangeConsumed = false;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    public ?string $ticketHash = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $subject = null;

    #[ORM\Column(length: 180, nullable: true)]
    public ?string $email = null;

    #[ORM\Column(length: 100)]
    public string $name = '';

    #[ORM\Column(length: 64, nullable: true)]
    public ?string $credentialHash = null;

    public function __construct(
        #[ORM\Column(length: 20)] public string $provider,
        #[ORM\Column(length: 64, unique: true)] public string $stateHash,
        #[ORM\Column(length: 43)] public string $codeChallenge,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        public ?User $linkUser = null,
    ) {
        $this->id = Uuid::v4();
        $this->expiresAt = new \DateTimeImmutable('+10 minutes');
        $this->credentialHash = null === $linkUser ? null : hash('sha256', $linkUser->getPassword());
    }
}
