<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SocialAuthExchangeInput;
use App\Dto\SocialAuthStartInput;
use App\Entity\SocialAuthFlow;
use App\Entity\SocialIdentity;
use App\Entity\User;
use App\EventSubscriber\SocialCredentialSubscriber;
use App\Repository\MerchantMembershipRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\Token\JWTPostAuthenticationToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class SocialAuthManager
{
    public function __construct(
        private SocialOAuthProvider $provider,
        private EntityManagerInterface $em,
        private RefreshTokenManager $refreshTokens,
        private JWTTokenManagerInterface $jwt,
        private MerchantMembershipRepository $memberships,
        private SocialCredentialSubscriber $credentialProof,
        private TokenStorageInterface $tokenStorage,
        #[Autowire(param: 'lexik_jwt_authentication.token_ttl')] private int $jwtTtl,
    ) {
    }

    /** @return array{authorization_url: string, state: string} */
    public function start(SocialAuthStartInput $input, ?User $user): array
    {
        if ('link' === $input->mode) {
            $this->assertEligible($user);
            $this->assertRecentCredentials($user);
        }
        $state = RefreshTokenManager::generateRawToken();
        $url = $this->provider->authorizationUrl($input->provider, $state);
        $this->em->getConnection()->executeStatement('DELETE FROM social_auth_flows WHERE id IN (SELECT id FROM social_auth_flows WHERE expires_at <= :now LIMIT 1000)', ['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        $this->em->persist(new SocialAuthFlow($input->provider, hash('sha256', $state), $input->codeChallenge, 'link' === $input->mode ? $user : null));
        $this->em->flush();

        return ['authorization_url' => $url, 'state' => $state];
    }

    public function callback(string $provider, string $state, string $code, bool $denied): string
    {
        $flow = $this->em->getRepository(SocialAuthFlow::class)->findOneBy(['stateHash' => hash('sha256', $state), 'provider' => $provider]);
        if (null === $flow || !$this->claim($flow, 'callback_consumed')) {
            throw new BadRequestHttpException('SOCIAL_AUTH_FAILED');
        }
        if ($denied || '' === $code) {
            return $this->redirect(['error' => 'SOCIAL_PROVIDER_DENIED', 'state' => $state]);
        }
        try {
            $identity = $this->provider->identity($provider, $code);
        } catch (\Throwable) {
            // Provider payloads and tokens must never enter error responses or logs.
            return $this->redirect(['error' => 'SOCIAL_AUTH_FAILED', 'state' => $state]);
        }
        $ticket = RefreshTokenManager::generateRawToken();
        $flow->ticketHash = hash('sha256', $ticket);
        $flow->subject = $identity['subject'];
        $flow->email = $identity['email'];
        $flow->name = $identity['name'];
        $flow->expiresAt = new \DateTimeImmutable('+2 minutes');
        $this->em->flush();

        return $this->redirect(['code' => $ticket, 'state' => $state]);
    }

    /** @return array<string, mixed> */
    public function exchange(SocialAuthExchangeInput $input, ?User $currentUser): array
    {
        $flow = $this->em->getRepository(SocialAuthFlow::class)->findOneBy(['ticketHash' => hash('sha256', $input->code), 'stateHash' => hash('sha256', $input->state)]);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $input->codeVerifier, true)), '+/', '-_'), '=');
        if (null === $flow || null === $flow->subject || !hash_equals($flow->codeChallenge, $challenge) || $flow->expiresAt <= new \DateTimeImmutable()) {
            throw new BadRequestHttpException('SOCIAL_AUTH_FAILED');
        }
        if (null !== $flow->linkUser) {
            $this->assertEligible($currentUser);
            $this->assertRecentCredentials($currentUser);
            if (!$flow->linkUser->getId()->equals($currentUser->getId()) || !hash_equals((string) $flow->credentialHash, hash('sha256', $currentUser->getPassword()))) {
                throw new AccessDeniedHttpException('SOCIAL_AUTH_FAILED');
            }
        }
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            if (!$this->claim($flow, 'exchange_consumed')) {
                throw new BadRequestHttpException('SOCIAL_AUTH_FAILED');
            }
            $identity = $this->em->getRepository(SocialIdentity::class)->findOneBy(['provider' => $flow->provider, 'subject' => $flow->subject]);
            if (null !== $flow->linkUser) {
                $user = $flow->linkUser;
                if (null !== $identity && !$identity->user->getId()->equals($user->getId())) {
                    throw new ConflictHttpException('SOCIAL_ACCOUNT_LINK_REQUIRED');
                }
            } elseif (null !== $identity) {
                $user = $identity->user;
            } else {
                if (null === $flow->email || '' === $flow->name) {
                    throw new UnprocessableEntityHttpException('SOCIAL_REGISTRATION_REQUIRES_EMAIL');
                }
                // Never merge based on provider email, even when it is verified.
                $existing = $this->em->createQueryBuilder()->select('u')->from(User::class, 'u')->where('LOWER(u.email) = :email')->setParameter('email', mb_strtolower($flow->email))->getQuery()->getOneOrNullResult();
                if (null !== $existing) {
                    throw new ConflictHttpException('SOCIAL_ACCOUNT_LINK_REQUIRED');
                }
                $user = (new User())->setEmail($flow->email)->setName($flow->name)->setPassword('')->setRoles(['ROLE_CUSTOMER']);
                $this->em->persist($user);
            }
            $this->assertEligible($user);
            if (null === $identity) {
                $existingProvider = $this->em->getRepository(SocialIdentity::class)->findOneBy(['user' => $user, 'provider' => $flow->provider]);
                if (null !== $existingProvider) {
                    throw new ConflictHttpException('SOCIAL_ACCOUNT_LINK_REQUIRED');
                }
                $this->em->persist(new SocialIdentity($flow->provider, $flow->subject, $user));
            }
            $result = ['linked' => true];
            if (null === $flow->linkUser) {
                $user->setLastLoginAt(new \DateTimeImmutable());
                $result = ['token' => $this->jwt->create($user), 'refresh_token' => $this->refreshTokens->issue($user), 'expires_in' => $this->jwtTtl, 'password_change_required' => false];
            }
            $this->em->flush();
            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            if ($exception instanceof UniqueConstraintViolationException) {
                throw new ConflictHttpException('SOCIAL_ACCOUNT_LINK_REQUIRED');
            }
            throw $exception;
        }
    }

    /** @return array{has_password: bool, providers: list<string>} */
    public function methods(?User $user): array
    {
        $this->assertEligible($user);
        $identities = $this->em->getRepository(SocialIdentity::class)->findBy(['user' => $user], ['provider' => 'ASC']);

        return ['has_password' => '' !== $user->getPassword(), 'providers' => array_map(static fn (SocialIdentity $identity): string => $identity->provider, $identities)];
    }

    private function assertRecentCredentials(User $user): void
    {
        $token = $this->tokenStorage->getToken();
        $payload = $token instanceof JWTPostAuthenticationToken ? $this->jwt->decode($token) : false;
        $version = \is_array($payload) ? ($payload['credential_version'] ?? null) : null;
        if (!\is_string($version) || !hash_equals($this->credentialProof->proof($user), $version)) {
            throw new AccessDeniedHttpException('SOCIAL_REAUTH_REQUIRED');
        }
    }

    private function assertEligible(?User $user): void
    {
        if (null === $user || !$user->isActive() || null !== $user->getDeletedAt() || $user->isPasswordChangeRequired() || (!\in_array('ROLE_CUSTOMER', $user->getRoles(), true) && !\in_array('ROLE_MERCHANT', $user->getRoles(), true)) || \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw new AccessDeniedHttpException('SOCIAL_AUTH_FAILED');
        }
        if (\in_array('ROLE_MERCHANT', $user->getRoles(), true) && [] !== $this->memberships->findByUser($user)) {
            $membership = $this->memberships->findOneActiveByUser($user);
            if (null === $membership || true !== $membership->getOrganization()?->isActive()) {
                throw new AccessDeniedHttpException('SOCIAL_AUTH_FAILED');
            }
        }
    }

    private function claim(SocialAuthFlow $flow, string $column): bool
    {
        // Column is an internal constant at both call sites, never request input.
        return 1 === $this->em->getConnection()->executeStatement(
            'UPDATE social_auth_flows SET '.$column.' = TRUE WHERE id = :id AND '.$column.' = FALSE AND expires_at > :now',
            ['id' => $flow->id, 'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => 'uuid'],
        );
    }

    /** @param array<string, string> $parameters */
    private function redirect(array $parameters): string
    {
        return 'kadhia://social-auth?'.http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986);
    }
}
