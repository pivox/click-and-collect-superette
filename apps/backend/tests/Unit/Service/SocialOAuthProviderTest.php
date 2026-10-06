<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SocialOAuthProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SocialOAuthProviderTest extends TestCase
{
    public function testProvidersAreDisabledWithoutServerCredentials(): void
    {
        self::assertSame([], (new SocialOAuthProvider(new MockHttpClient(), '', '', '', '', ''))->available());
    }

    public function testGoogleUsesServerCodeExchangeAndVerifiedUserinfo(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"access_token":"server-token"}'),
            new MockResponse('{"sub":"google-subject","email":"client@example.test","email_verified":true,"name":"Client"}'),
        ]);
        $provider = new SocialOAuthProvider($http, 'google-id', 'google-secret', '', '', 'https://api.example.test');
        self::assertSame(['google'], $provider->available());
        self::assertSame(['subject' => 'google-subject', 'email' => 'client@example.test', 'name' => 'Client'], $provider->identity('google', 'provider-code'));
    }

    public function testUnverifiedEmailCannotRegister(): void
    {
        $provider = new SocialOAuthProvider(new MockHttpClient([
            new MockResponse('{"access_token":"server-token"}'),
            new MockResponse('{"sub":"google-subject","email":"client@example.test","email_verified":false}'),
        ]), 'id', 'secret', '', '', 'https://api.example.test');
        self::assertNull($provider->identity('google', 'code')['email']);
    }

    public function testFacebookEmailDoesNotImplyVerifiedOwnership(): void
    {
        $provider = new SocialOAuthProvider(new MockHttpClient([
            new MockResponse('{"access_token":"server-token"}'),
            new MockResponse('{"id":"fb-subject","email":"client@example.test","name":"Client"}'),
        ]), '', '', 'id', 'secret', 'https://api.example.test');
        self::assertSame(['subject' => 'fb-subject', 'email' => null, 'name' => 'Client'], $provider->identity('facebook', 'code'));
    }
}
