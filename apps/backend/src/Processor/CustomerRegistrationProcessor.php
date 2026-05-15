<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CustomerRegistrationOutput;
use App\Dto\CustomerRegistrationInput;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<CustomerRegistrationInput, CustomerRegistrationOutput>
 */
final readonly class CustomerRegistrationProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtTokenManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerRegistrationOutput
    {
        if (!$data instanceof CustomerRegistrationInput) {
            throw new \InvalidArgumentException('CustomerRegistrationInput expected.');
        }

        $email = strtolower($data->email);
        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            throw new ConflictHttpException('AUTH_EMAIL_ALREADY_EXISTS');
        }

        [$firstName, $lastName, $name] = $this->resolveCustomerName($data);

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_CUSTOMER'])
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setName($name)
            ->setPhone($data->phone)
            ->setActive(true);

        $user->setPassword($this->passwordHasher->hashPassword($user, $data->password));

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('AUTH_EMAIL_ALREADY_EXISTS');
        }

        return new CustomerRegistrationOutput(
            $user->getId()->toRfc4122(),
            $this->jwtTokenManager->create($user),
            [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'name' => $user->getName(),
                'phone' => $user->getPhone(),
            ],
        );
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: string}
     */
    private function resolveCustomerName(CustomerRegistrationInput $data): array
    {
        if (null !== $data->name) {
            return [$data->firstName, $data->lastName, $data->name];
        }

        if (null === $data->firstName || null === $data->lastName) {
            throw new \InvalidArgumentException('Customer first and last names are required when name is not provided.');
        }

        return [$data->firstName, $data->lastName, $data->firstName.' '.$data->lastName];
    }
}
