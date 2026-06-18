<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminMerchantInvitationOutput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AdminAuditLogger;
use App\Service\MerchantInvitationSenderInterface;
use App\Service\MerchantInvitationTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminMerchantInvitationOutput>
 */
final readonly class AdminSendMerchantInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private MerchantInvitationTokenManager $tokenManager,
        private MerchantInvitationSenderInterface $invitationSender,
        private AdminAuditLogger $auditLogger,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminMerchantInvitationOutput
    {
        $admin = $this->security->getUser();
        if (!$admin instanceof User) {
            throw new AccessDeniedHttpException('ADMIN_ACCESS_REQUIRED');
        }

        $merchant = $this->resolveMerchant((string) ($uriVariables['merchantId'] ?? ''));
        $isResend = true === ($operation->getExtraProperties()['resend'] ?? false);
        $created = $this->tokenManager->createForMerchant($merchant, $admin);
        $token = $created['token'];

        $this->auditLogger->log(
            action: $isResend ? 'merchant.invitation.resend' : 'merchant.invitation.create',
            resourceType: 'merchant',
            resourceId: $merchant->getId()->toRfc4122(),
            summary: \sprintf('Invitation email marchand %s %s.', $merchant->getEmail(), $isResend ? 'renvoyée' : 'créée'),
            metadata: [
                'email' => $merchant->getEmail(),
                'expires_at' => $token->getExpiresAt()->format(\DateTimeInterface::ATOM),
            ],
        );

        $this->entityManager->flush();
        $this->invitationSender->send($merchant, $created['rawToken'], $token->getExpiresAt());

        $this->logger->info('merchant.invitation_sent', [
            'merchant_id' => $merchant->getId()->toRfc4122(),
            'resend' => $isResend,
        ]);

        return AdminMerchantInvitationOutput::sent($token);
    }

    private function resolveMerchant(string $merchantId): User
    {
        if (!Uuid::isValid($merchantId)) {
            throw new NotFoundHttpException('ADMIN_MERCHANT_NOT_FOUND');
        }

        $merchant = $this->userRepository->find($merchantId);
        if (!$merchant instanceof User) {
            throw new NotFoundHttpException('ADMIN_MERCHANT_NOT_FOUND');
        }

        if (!\in_array('ROLE_MERCHANT', $merchant->getRoles(), true)) {
            throw new UnprocessableEntityHttpException('ADMIN_MERCHANT_INVITATION_TARGET_NOT_MERCHANT');
        }

        return $merchant;
    }
}
