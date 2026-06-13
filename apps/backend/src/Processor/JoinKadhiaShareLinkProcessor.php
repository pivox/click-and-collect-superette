<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\KadhiaShareJoinOutput;
use App\Entity\User;
use App\Enum\KadhiaStatus;
use App\Service\KadhiaMembershipService;
use App\Service\KadhiaShareLinkService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<null, KadhiaShareJoinOutput>
 */
final readonly class JoinKadhiaShareLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private KadhiaShareLinkService $kadhiaShareLinkService,
        private KadhiaMembershipService $kadhiaMembershipService,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): KadhiaShareJoinOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $token = (string) ($uriVariables['token'] ?? $this->requestStack->getCurrentRequest()?->attributes->get('token') ?? '');
        $link = '' !== $token ? $this->kadhiaShareLinkService->findActiveByToken(rawurldecode($token)) : null;
        if (null === $link) {
            throw new NotFoundHttpException('KADHIA_SHARE_LINK_NOT_FOUND');
        }

        $kadhia = $link->getKadhia();
        if (KadhiaStatus::Draft !== $kadhia->getStatus()) {
            throw new UnprocessableEntityHttpException('KADHIA_NOT_SHAREABLE');
        }

        $kadhiaId = $kadhia->getId()->toRfc4122();
        $storeId = $kadhia->getShop()->getId()->toRfc4122();
        $joined = $this->kadhiaMembershipService->ensureEditor($kadhia, $user);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->entityManager->clear();
            $joined = false;
        }

        return new KadhiaShareJoinOutput(
            token: $token,
            kadhiaId: $kadhiaId,
            storeId: $storeId,
            joined: $joined,
        );
    }
}
