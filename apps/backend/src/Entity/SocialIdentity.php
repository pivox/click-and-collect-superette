<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'social_identities')]
#[ORM\UniqueConstraint(name: 'UNIQ_SOCIAL_PROVIDER_SUBJECT', columns: ['provider', 'subject'])]
#[ORM\UniqueConstraint(name: 'UNIQ_SOCIAL_USER_PROVIDER', columns: ['user_id', 'provider'])]
class SocialIdentity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public Uuid $id;

    public function __construct(
        #[ORM\Column(length: 20)] public string $provider,
        #[ORM\Column(length: 255)] public string $subject,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public User $user,
    ) {
        $this->id = Uuid::v4();
    }
}
