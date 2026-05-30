<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminMerchantOutput;
use App\Dto\AdminCreateMerchantInput;
use App\Entity\User;
use App\Provider\AdminMerchantItemProvider;
use App\Repository\AdminMerchantRepository;
use App\Repository\UserRepository;
use App\Service\AdminAuditLogger;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<AdminCreateMerchantInput, AdminMerchantOutput>
 */
final readonly class AdminCreateMerchantProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private AdminMerchantRepository $adminMerchantRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AdminAuditLogger $auditLogger,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminMerchantOutput
    {
        if (!$data instanceof AdminCreateMerchantInput) {
            throw new \InvalidArgumentException('AdminCreateMerchantInput expected.');
        }

        $email = strtolower($data->email);
        $emailHash = hash('sha256', $email);

        $this->logger->debug('admin.merchant_create.start', ['email_hash' => $emailHash]);

        // Check email uniqueness before attempting insert
        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            $this->logger->warning('admin.merchant_create.rejected', [
                'reason' => 'ADMIN_MERCHANT_EMAIL_ALREADY_EXISTS',
                'email_hash' => $emailHash,
            ]);
            throw new UnprocessableEntityHttpException('ADMIN_MERCHANT_EMAIL_ALREADY_EXISTS');
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_MERCHANT'])
            ->setFirstName($data->firstName)
            ->setLastName($data->lastName)
            ->setName($data->firstName.' '.$data->lastName)
            ->setPhone($data->phone)
            ->setActive($data->isActive);

        // Generate a temporary password — never exposed in the response
        $temporaryPassword = bin2hex(random_bytes(16));
        $user->setPassword($this->passwordHasher->hashPassword($user, $temporaryPassword));

        try {
            $this->entityManager->persist($user);
            $this->auditLogger->log(
                action: 'merchant.create',
                resourceType: 'merchant',
                resourceId: $user->getId()->toRfc4122(),
                summary: \sprintf('Compte marchand %s créé.', $email),
                metadata: ['email' => $email],
            );
            $this->entityManager->flush();
            $this->logger->info('merchant.created', [
                'merchant_id' => $user->getId()->toRfc4122(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->logger->warning('admin.merchant_create.rejected', [
                'reason' => 'ADMIN_MERCHANT_EMAIL_ALREADY_EXISTS',
                'email_hash' => $emailHash,
            ]);
            throw new UnprocessableEntityHttpException('ADMIN_MERCHANT_EMAIL_ALREADY_EXISTS');
        } catch (\Throwable $e) {
            $this->logger->error('admin.merchant_create.failed', [
                'email_hash' => $emailHash,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return AdminMerchantItemProvider::toOutput($user, $this->adminMerchantRepository->countStores($user));
    }
}
