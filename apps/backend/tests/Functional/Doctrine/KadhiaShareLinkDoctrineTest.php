<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\KadhiaShareLink;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class KadhiaShareLinkDoctrineTest extends FunctionalApiTestCase
{
    public function testActiveLinkUniqueConstraintIsDeclaredInMetadata(): void
    {
        $metadata = $this->entityManager->getClassMetadata(KadhiaShareLink::class);

        $constraint = $metadata->table['uniqueConstraints']['UNIQ_KADHIA_SHARE_LINKS_ACTIVE_KADHIA'] ?? null;

        self::assertNotNull($constraint);
        self::assertSame(['kadhia_id'], $constraint['columns']);
        self::assertSame('active = true', $constraint['options']['where'] ?? null);
    }

    public function testCreatedByIndexIsDeclaredInMetadata(): void
    {
        $metadata = $this->entityManager->getClassMetadata(KadhiaShareLink::class);

        $index = $metadata->table['indexes']['IDX_KADHIA_SHARE_LINKS_CREATED_BY'] ?? null;

        self::assertNotNull($index);
        self::assertSame(['created_by_id'], $index['columns']);
    }
}
