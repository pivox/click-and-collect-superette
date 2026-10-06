<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\SocialAuthFlow;
use App\Entity\SocialIdentity;
use App\Service\RefreshTokenManager;
use App\Service\SocialOAuthProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SocialAuthApiTest extends FunctionalApiTestCase
{
    public function testProvidersDisabledAndPrivateMethodsProtected(): void
    {
        $response = $this->requestJson('GET', '/api/auth/social/providers');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['providers' => []], $this->decodeJson($response));
        self::assertSame(401, $this->requestJson('GET', '/api/me/auth-methods')->getStatusCode());
    }

    public function testGoogleFlowCreatesCustomerAndCannotReplay(): void
    {
        $this->configureGoogle();
        $start = $this->requestJson('POST', '/api/auth/social/start', ['provider' => 'google', 'mode' => 'login', 'code_challenge' => $this->challenge()]);
        self::assertSame(200, $start->getStatusCode());
        $state = $this->decodeJson($start)['state'];
        $callback = $this->requestJson('GET', '/api/auth/social/callback/google?state='.$state.'&code=provider-code');
        self::assertSame(302, $callback->getStatusCode());
        parse_str((string) parse_url((string) $callback->headers->get('Location'), \PHP_URL_QUERY), $result);
        self::assertArrayHasKey('code', $result);
        $body = ['code' => $result['code'], 'state' => $state, 'code_verifier' => str_repeat('v', 43)];
        self::assertSame(400, $this->requestJson('POST', '/api/auth/social/exchange', array_replace($body, ['code_verifier' => str_repeat('x', 43)]))->getStatusCode());
        $exchange = $this->requestJson('POST', '/api/auth/social/exchange', $body);
        self::assertSame(200, $exchange->getStatusCode(), (string) $exchange->getContent());
        self::assertArrayHasKey('refresh_token', $this->decodeJson($exchange));
        self::assertSame(400, $this->requestJson('POST', '/api/auth/social/exchange', $body)->getStatusCode());
        $identity = $this->entityManager->getRepository(SocialIdentity::class)->findOneBy(['subject' => 'subject']);
        self::assertNotNull($identity);
        self::assertSame('', $identity->user->getPassword());
        self::assertContains('ROLE_CUSTOMER', $identity->user->getRoles());
        self::assertNotContains('ROLE_MERCHANT', $identity->user->getRoles());
    }

    public function testExistingEmailNeverAutoLinks(): void
    {
        $this->createUser('social@example.test', ['ROLE_MERCHANT']);
        $this->ticket();
        $response = $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody());
        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('SOCIAL_ACCOUNT_LINK_REQUIRED', (string) $response->getContent());
        self::assertCount(0, $this->entityManager->getRepository(SocialIdentity::class)->findAll());
    }

    public function testLinkRequiresSameAuthenticatedUserAndCanLinkWithoutEmail(): void
    {
        $user = $this->createUser('merchant@example.test', ['ROLE_MERCHANT']);
        $other = $this->createUser('other@example.test', ['ROLE_CUSTOMER']);
        $this->ticket($user, email: null);
        self::assertSame(403, $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody())->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody(), $other)->getStatusCode());
        $response = $this->bearerRequest('/api/auth/social/exchange', $this->exchangeBody(), self::getContainer()->get(\Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface::class)->create($user));
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['linked' => true], $this->decodeJson($response));
        $methods = $this->requestJson('GET', '/api/me/auth-methods', user: $user);
        self::assertSame(200, $methods->getStatusCode());
        self::assertSame(['has_password' => true, 'providers' => ['google']], $this->decodeJson($methods));
    }

    public function testExpiredTicketAndMissingEmailAreRejected(): void
    {
        $flow = $this->ticket(email: null);
        self::assertSame(422, $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody())->getStatusCode());
        $flow->expiresAt = new \DateTimeImmutable('-1 minute');
        $this->entityManager->flush();
        self::assertSame(400, $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody())->getStatusCode());
    }

    public function testSuspendedLinkedUserCannotLogin(): void
    {
        $user = $this->createUser('suspended@example.test', ['ROLE_CUSTOMER'])->setActive(false);
        $this->entityManager->persist(new SocialIdentity('google', 'subject', $user));
        $this->ticket();
        self::assertSame(403, $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody())->getStatusCode());
    }

    public function testCustomerPasswordChecksCurrentPasswordAndRevokesRefreshTokens(): void
    {
        $user = $this->createUser('password@example.test', ['ROLE_CUSTOMER']);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'old-password'));
        $tokens = self::getContainer()->get(RefreshTokenManager::class);
        $rawToken = $tokens->issue($user);
        $this->entityManager->flush();
        self::assertSame(422, $this->requestJson('PATCH', '/api/me/password', ['current_password' => 'wrong', 'new_password' => 'new-password'], $user)->getStatusCode());
        self::assertSame(422, $this->requestJson('PATCH', '/api/me/password', ['current_password' => 'old-password', 'new_password' => 'short'], $user)->getStatusCode());
        self::assertSame(204, $this->requestJson('PATCH', '/api/me/password', ['current_password' => 'old-password', 'new_password' => 'new-password'], $user)->getStatusCode());
        self::assertTrue($hasher->isPasswordValid($this->entityManager->find(\App\Entity\User::class, $user->getId()), 'new-password'));
        self::assertTrue($tokens->findByRawToken($rawToken)->isRevoked());
    }

    public function testMalformedStartAndUnauthenticatedLinkAreRejected(): void
    {
        $this->configureGoogle();
        self::assertSame(422, $this->requestJson('POST', '/api/auth/social/start', ['provider' => 'google', 'mode' => 'login', 'code_challenge' => 'invalid'])->getStatusCode());
        self::assertSame(403, $this->requestJson('POST', '/api/auth/social/start', ['provider' => 'google', 'mode' => 'link', 'code_challenge' => $this->challenge()])->getStatusCode());
        self::assertSame(400, $this->requestJson('GET', '/api/auth/social/callback/google?state=unknown&code=x')->getStatusCode());
    }

    public function testMandatoryPasswordChangeAndPasswordlessCustomerAreRejected(): void
    {
        $merchant = $this->createUser('firstlogin@example.test', ['ROLE_MERCHANT'])->setPasswordChangeRequired(true);
        $this->ticket($merchant);
        self::assertSame(403, $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody(), $merchant)->getStatusCode());
        $customer = $this->createUser('passwordless@example.test', ['ROLE_CUSTOMER'])->setPassword('');
        $this->entityManager->flush();
        self::assertSame(422, $this->requestJson('PATCH', '/api/me/password', ['current_password' => 'any', 'new_password' => 'new-password'], $customer)->getStatusCode());
    }

    public function testStartPurgesExpiredFlowsAndCancelledCallbackCannotReplay(): void
    {
        $expired = $this->ticket();
        $expired->expiresAt = new \DateTimeImmutable('-1 minute');
        $this->entityManager->flush();
        $this->configureGoogle();
        $start = $this->requestJson('POST', '/api/auth/social/start', ['provider' => 'google', 'mode' => 'login', 'code_challenge' => $this->challenge()]);
        self::assertSame(200, $start->getStatusCode());
        self::assertSame(1, $this->entityManager->getRepository(SocialAuthFlow::class)->count([]));
        $state = $this->decodeJson($start)['state'];
        $callback = $this->requestJson('GET', '/api/auth/social/callback/google?state='.$state.'&error=access_denied');
        self::assertSame(302, $callback->getStatusCode());
        self::assertStringContainsString('error=SOCIAL_PROVIDER_DENIED', (string) $callback->headers->get('Location'));
        self::assertSame(400, $this->requestJson('GET', '/api/auth/social/callback/google?state='.$state.'&code=provider-code')->getStatusCode());
    }

    public function testLinkRejectsPasswordChangedSinceStart(): void
    {
        $user = $this->createUser('changed@example.test', ['ROLE_CUSTOMER']);
        $this->ticket($user);
        $user->setPassword('changed-hash');
        $this->entityManager->flush();
        self::assertSame(403, $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody(), $user)->getStatusCode());
    }

    private function bearerRequest(string $path, array $body, string $jwt): \Symfony\Component\HttpFoundation\Response
    {
        return self::$kernel->handle(\Symfony\Component\HttpFoundation\Request::create($path, 'POST', server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$jwt], content: json_encode($body, \JSON_THROW_ON_ERROR)));
    }

    public function testJwtFromBeforePasswordResetCannotStartNewLink(): void
    {
        $user = $this->createUser('old-jwt@example.test', ['ROLE_CUSTOMER']);
        $jwt = self::getContainer()->get(\Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface::class)->create($user);
        $user->setPassword('reset-password-hash');
        $this->entityManager->flush();
        $this->configureGoogle();
        $response = $this->bearerRequest('/api/auth/social/start', ['provider' => 'google', 'mode' => 'link', 'code_challenge' => $this->challenge()], $jwt);
        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('SOCIAL_REAUTH_REQUIRED', (string) $response->getContent());
    }

    public function testRevokedMerchantMembershipCannotLoginWithLinkedIdentity(): void
    {
        $user = $this->createUser('revoked@example.test', ['ROLE_MERCHANT']);
        $organization = (new \App\Entity\MerchantOrganization())->setName('Supérette')->setPrimaryAccount($user);
        $membership = (new \App\Entity\MerchantMembership())->setUser($user)->setOrganization($organization)->activate()->revoke($user);
        $this->entityManager->persist($organization);
        $this->entityManager->persist($membership);
        $this->entityManager->persist(new SocialIdentity('google', 'subject', $user));
        $this->ticket();
        self::assertSame(403, $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody())->getStatusCode());
    }

    public function testFacebookWithoutEmailCanLoginAfterExplicitAssociation(): void
    {
        $user = $this->createUser('facebook@example.test', ['ROLE_CUSTOMER']);
        $this->entityManager->persist(new SocialIdentity('facebook', 'subject', $user));
        $flow = $this->ticket(email: null);
        $flow->provider = 'facebook';
        $this->entityManager->flush();
        $response = $this->requestJson('POST', '/api/auth/social/exchange', $this->exchangeBody());
        self::assertSame(200, $response->getStatusCode());
        self::assertArrayHasKey('token', $this->decodeJson($response));
    }

    private function configureGoogle(): void
    {
        self::getContainer()->set(SocialOAuthProvider::class, new SocialOAuthProvider(new MockHttpClient([
            new MockResponse('{"access_token":"server-token"}'),
            new MockResponse('{"sub":"subject","email":"social@example.test","email_verified":true,"name":"Social Client"}'),
        ]), 'id', 'secret', '', '', 'https://api.example.test'));
    }

    private function challenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', str_repeat('v', 43), true)), '+/', '-_'), '=');
    }

    private function ticket(?\App\Entity\User $user = null, ?string $email = 'social@example.test'): SocialAuthFlow
    {
        $flow = new SocialAuthFlow('google', hash('sha256', 'state'), $this->challenge(), $user);
        $flow->ticketHash = hash('sha256', 'ticket');
        $flow->subject = 'subject';
        $flow->email = $email;
        $flow->name = 'Social Client';
        $flow->callbackConsumed = true;
        $this->entityManager->persist($flow);
        $this->entityManager->flush();

        return $flow;
    }

    /** @return array<string, string> */
    private function exchangeBody(): array
    {
        return ['state' => 'state', 'code' => 'ticket', 'code_verifier' => str_repeat('v', 43)];
    }
}
