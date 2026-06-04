<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantAccountOutput;
use App\Dto\MerchantMeUpdateInput;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<MerchantMeUpdateInput, MerchantAccountOutput>
 */
final readonly class UpdateMerchantAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private JWTTokenManagerInterface $jwtTokenManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantAccountOutput
    {
        if (!$data instanceof MerchantMeUpdateInput) {
            throw new \InvalidArgumentException('MerchantMeUpdateInput expected.');
        }

        $merchant = $this->currentMerchant();
        $payload = $this->currentPayload();

        if (\array_key_exists('name', $payload) && null !== $data->name) {
            $name = trim($data->name);
            if ('' === $name) {
                throw new UnprocessableEntityHttpException('MERCHANT_ACCOUNT_NAME_BLANK');
            }
            $merchant->setName($name);
        }

        $emailChanged = false;
        if (\array_key_exists('email', $payload) && null !== $data->email) {
            $email = strtolower(trim($data->email));
            if ('' === $email) {
                throw new UnprocessableEntityHttpException('MERCHANT_ACCOUNT_EMAIL_BLANK');
            }
            if ($email !== $merchant->getEmail()) {
                if (null !== $this->userRepository->findOneBy(['email' => $email])) {
                    throw new ConflictHttpException('MERCHANT_EMAIL_ALREADY_EXISTS');
                }
                $merchant->setEmail($email);
                $emailChanged = true;
            }
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('MERCHANT_EMAIL_ALREADY_EXISTS');
        }

        // The JWT identity claim is the email (see lexik config + security.yaml
        // provider). After an email change the current token can no longer
        // resolve the user, so re-issue one and return it for the client to swap.
        $token = $emailChanged ? $this->jwtTokenManager->create($merchant) : null;

        return MerchantAccountOutput::fromUser($merchant, $token);
    }

    private function currentMerchant(): User
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }
        if (!$merchant->isActive()) {
            throw new AccessDeniedHttpException('MERCHANT_ACCOUNT_INACTIVE');
        }

        return $merchant;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || '' === $request->getContent()) {
            return [];
        }

        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
    }
}
