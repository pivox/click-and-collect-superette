<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class SocialCredentialSubscriber implements EventSubscriberInterface
{
    public function __construct(#[Autowire(param: 'kernel.secret')] private string $secret)
    {
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [Events::JWT_CREATED => 'onCreated'];
    }

    public function onCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $event->setData(array_replace($event->getData(), ['credential_version' => $this->proof($user)]));
        }
    }

    public function proof(User $user): string
    {
        return hash_hmac('sha256', $user->getId()->toRfc4122().'|'.$user->getPassword(), $this->secret);
    }
}
