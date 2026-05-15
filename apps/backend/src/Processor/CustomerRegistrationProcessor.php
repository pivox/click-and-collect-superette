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

        $email = strtolower(trim($data->email));
        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            throw new ConflictHttpException('AUTH_EMAIL_ALREADY_EXISTS');
        }

        $firstName = trim($data->firstName);
        $lastName = trim($data->lastName);
        $phone = null !== $data->phone ? trim($data->phone) : null;
        if ('' === $phone) {
            $phone = null;
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_CUSTOMER'])
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setName(trim($firstName.' '.$lastName))
            ->setPhone($phone)
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
            $user->getEmail(),
            ['ROLE_CUSTOMER'],
            $user->getFirstName() ?? '',
            $user->getLastName() ?? '',
            $user->getPhone(),
        );
    }
}
