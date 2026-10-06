<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SocialOAuthProvider
{
    public function __construct(
        private HttpClientInterface $http,
        #[Autowire(env: 'SOCIAL_GOOGLE_CLIENT_ID')] private string $googleId,
        #[Autowire(env: 'SOCIAL_GOOGLE_CLIENT_SECRET')] private string $googleSecret,
        #[Autowire(env: 'SOCIAL_FACEBOOK_CLIENT_ID')] private string $facebookId,
        #[Autowire(env: 'SOCIAL_FACEBOOK_CLIENT_SECRET')] private string $facebookSecret,
        #[Autowire(env: 'SOCIAL_CALLBACK_ORIGIN')] private string $callbackOrigin,
    ) {
    }

    /** @return list<string> */
    public function available(): array
    {
        if (!str_starts_with($this->callbackOrigin, 'https://') || false === filter_var($this->callbackOrigin, \FILTER_VALIDATE_URL)) {
            return [];
        }
        $providers = [];
        if ('' !== $this->googleId && '' !== $this->googleSecret) {
            $providers[] = 'google';
        }
        if ('' !== $this->facebookId && '' !== $this->facebookSecret) {
            $providers[] = 'facebook';
        }

        return $providers;
    }

    public function authorizationUrl(string $provider, string $state): string
    {
        $this->assertAvailable($provider);

        return ('google' === $provider ? 'https://accounts.google.com/o/oauth2/v2/auth' : 'https://www.facebook.com/v23.0/dialog/oauth').'?'.http_build_query([
            'client_id' => 'google' === $provider ? $this->googleId : $this->facebookId,
            'redirect_uri' => $this->callback($provider),
            'response_type' => 'code',
            'scope' => 'google' === $provider ? 'openid email profile' : 'public_profile,email',
            'state' => $state,
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    /** @return array{subject: string, email: ?string, name: string} */
    public function identity(string $provider, string $code): array
    {
        $this->assertAvailable($provider);
        $google = 'google' === $provider;
        // Only authorization codes received at the fixed server callback are accepted.
        $token = $this->http->request('POST', $google ? 'https://oauth2.googleapis.com/token' : 'https://graph.facebook.com/v23.0/oauth/access_token', [
            'body' => [
                'client_id' => $google ? $this->googleId : $this->facebookId,
                'client_secret' => $google ? $this->googleSecret : $this->facebookSecret,
                'redirect_uri' => $this->callback($provider),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ],
            'timeout' => 10,
            'max_redirects' => 0,
        ])->toArray();
        $accessToken = $token['access_token'] ?? null;
        if (!\is_string($accessToken) || '' === $accessToken) {
            throw new BadRequestHttpException('SOCIAL_AUTH_FAILED');
        }
        $options = ['auth_bearer' => $accessToken, 'timeout' => 10, 'max_redirects' => 0];
        if (!$google) {
            $options['query'] = ['fields' => 'id,name,email', 'appsecret_proof' => hash_hmac('sha256', $accessToken, $this->facebookSecret)];
        }
        $profile = $this->http->request('GET', $google ? 'https://openidconnect.googleapis.com/v1/userinfo' : 'https://graph.facebook.com/v23.0/me', $options)->toArray();
        $subject = $profile[$google ? 'sub' : 'id'] ?? null;
        if (!\is_string($subject) || '' === $subject || \strlen($subject) > 255) {
            throw new BadRequestHttpException('SOCIAL_AUTH_FAILED');
        }
        // Meta does not provide an email_verified assertion: explicit linking only.
        $email = $google && true === ($profile['email_verified'] ?? false) ? ($profile['email'] ?? null) : null;
        $email = \is_string($email) && \strlen($email) <= 180 && filter_var($email, \FILTER_VALIDATE_EMAIL) ? mb_strtolower($email) : null;
        $name = \is_string($profile['name'] ?? null) ? mb_substr(trim($profile['name']), 0, 100) : '';

        return ['subject' => $subject, 'email' => $email, 'name' => $name];
    }

    private function callback(string $provider): string
    {
        return rtrim($this->callbackOrigin, '/').'/api/auth/social/callback/'.$provider;
    }

    private function assertAvailable(string $provider): void
    {
        if (!\in_array($provider, $this->available(), true)) {
            throw new BadRequestHttpException('SOCIAL_PROVIDER_UNAVAILABLE');
        }
    }
}
