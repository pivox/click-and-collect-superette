<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SocialAuthExchangeInput;
use App\Dto\SocialAuthStartInput;
use App\Entity\User;
use App\Service\SocialAuthManager;
use App\Service\SocialOAuthProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class SocialAuthController extends AbstractController
{
    #[Route('/api/auth/social/providers', methods: ['GET'])]
    public function providers(SocialOAuthProvider $provider): JsonResponse
    {
        return $this->response(['providers' => $provider->available()]);
    }

    #[Route('/api/auth/social/start', methods: ['POST'], format: 'json')]
    public function start(#[MapRequestPayload] SocialAuthStartInput $input, SocialAuthManager $manager): JsonResponse
    {
        return $this->response($manager->start($input, $this->currentUser()));
    }

    #[Route('/api/auth/social/callback/{provider}', requirements: ['provider' => 'google|facebook'], methods: ['GET'])]
    public function callback(string $provider, Request $request, SocialAuthManager $manager): RedirectResponse
    {
        return new RedirectResponse($manager->callback($provider, $request->query->getString('state'), $request->query->getString('code'), $request->query->has('error')), headers: ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    #[Route('/api/auth/social/exchange', methods: ['POST'], format: 'json')]
    public function exchange(#[MapRequestPayload] SocialAuthExchangeInput $input, SocialAuthManager $manager): JsonResponse
    {
        return $this->response($manager->exchange($input, $this->currentUser()));
    }

    #[Route('/api/me/auth-methods', methods: ['GET'])]
    public function methods(SocialAuthManager $manager): JsonResponse
    {
        return $this->response($manager->methods($this->currentUser()));
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    /** @param array<string, mixed> $data */
    private function response(array $data): JsonResponse
    {
        return new JsonResponse($data, headers: ['Cache-Control' => 'no-store']);
    }
}
