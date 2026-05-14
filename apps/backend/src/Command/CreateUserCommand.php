<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a user account (intended for local dev)',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'User email', 'dev@example.com')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Plain-text password', 'password')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Display name', 'Dev User')
            ->addOption('role', null, InputOption::VALUE_OPTIONAL, 'Extra role: ROLE_USER, ROLE_CUSTOMER, ROLE_MERCHANT, ROLE_ADMIN', 'ROLE_MERCHANT');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create user');

        $email = (string) ($input->getOption('email') ?? 'dev@example.com');
        $plainPassword = (string) ($input->getOption('password') ?? 'password');
        $name = (string) ($input->getOption('name') ?? 'Dev User');
        $role = (string) ($input->getOption('role') ?? 'ROLE_MERCHANT');

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null !== $existing) {
            $io->warning(\sprintf('User "%s" already exists — skipping.', $email));

            return Command::SUCCESS;
        }

        $allowedRoles = ['ROLE_USER', 'ROLE_CUSTOMER', 'ROLE_MERCHANT', 'ROLE_ADMIN'];
        if (!\in_array($role, $allowedRoles, true)) {
            $io->error(\sprintf('Invalid role "%s". Allowed: %s', $role, implode(', ', $allowedRoles)));

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email)
            ->setName($name)
            ->setRoles([$role])
            ->setPassword($this->passwordHasher->hashPassword($user, $plainPassword))
            ->setActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf(
            'User created — email: %s | roles: %s',
            $user->getEmail(),
            implode(', ', $user->getRoles()),
        ));

        return Command::SUCCESS;
    }
}
