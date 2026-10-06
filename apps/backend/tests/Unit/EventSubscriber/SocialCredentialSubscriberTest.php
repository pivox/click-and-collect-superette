<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\SocialCredentialSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\TestCase;

final class SocialCredentialSubscriberTest extends TestCase
{
    public function testCredentialProofChangesWhenPasswordChanges(): void
    {
        $user = (new User())->setPassword('old-hash');
        $subscriber = new SocialCredentialSubscriber('test-secret');
        $event = new JWTCreatedEvent([], $user);
        $subscriber->onCreated($event);
        $proof = $event->getData()['credential_version'];
        self::assertSame($subscriber->proof($user), $proof);
        $user->setPassword('new-hash');
        self::assertNotSame($subscriber->proof($user), $proof);
    }
}
