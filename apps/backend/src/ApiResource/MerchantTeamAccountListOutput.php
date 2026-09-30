<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantTeamInvitationInput;
use App\Processor\InviteMerchantTeamAccountProcessor;
use App\Processor\ResendMerchantTeamInvitationProcessor;
use App\Processor\RevokeMerchantTeamAccountProcessor;
use App\Provider\MerchantTeamAccountCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Team of a merchant organization, managed by its primary account
 * (MERCHANT-TEAM-004). Invitations reuse the existing merchant invitation
 * tokens, email and first-login journey — no temporary password is ever shown.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/stores/{storeId}/accounts',
            formats: ['json' => ['application/json']],
            provider: MerchantTeamAccountCollectionProvider::class,
            normalizationContext: ['groups' => ['merchant_team:read']],
            security: "is_granted('ROLE_MERCHANT')",
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/account-invitations',
            formats: ['json' => ['application/json']],
            input: MerchantTeamInvitationInput::class,
            output: MerchantTeamAccountOutput::class,
            read: false,
            processor: InviteMerchantTeamAccountProcessor::class,
            normalizationContext: ['groups' => ['merchant_team:read']],
            security: "is_granted('ROLE_MERCHANT')",
            status: 201,
            validate: true,
        ),
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/accounts/{accountId}/resend-invitation',
            formats: ['json' => ['application/json']],
            input: false,
            output: MerchantTeamAccountOutput::class,
            read: false,
            processor: ResendMerchantTeamInvitationProcessor::class,
            normalizationContext: ['groups' => ['merchant_team:read']],
            security: "is_granted('ROLE_MERCHANT')",
            status: 200,
        ),
        new Delete(
            uriTemplate: '/merchant/stores/{storeId}/accounts/{accountId}',
            formats: ['json' => ['application/json']],
            read: false,
            output: false,
            processor: RevokeMerchantTeamAccountProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantTeamAccountListOutput
{
    /**
     * @param list<MerchantTeamAccountOutput> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_team:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['merchant_team:read'])]
        #[SerializedName('organization_id')]
        public string $organizationId,
        #[Groups(['merchant_team:read'])]
        public int $limit,
        #[Groups(['merchant_team:read'])]
        #[SerializedName('active_or_invited_count')]
        public int $activeOrInvitedCount,
        #[Groups(['merchant_team:read'])]
        public array $items,
    ) {
    }
}
