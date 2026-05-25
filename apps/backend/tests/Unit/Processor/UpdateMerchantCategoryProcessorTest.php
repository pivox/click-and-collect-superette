<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use ApiPlatform\Metadata\Patch;
use App\Dto\MerchantCategoryUpdateInput;
use App\Entity\MerchantCategory;
use App\Entity\Shop;
use App\Entity\User;
use App\Mapper\MerchantCategoryMapper;
use App\Processor\UpdateMerchantCategoryProcessor;
use App\Repository\MerchantCategoryRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateMerchantCategoryProcessorTest extends TestCase
{
    public function testItRejectsMalformedParentIdBeforeComparingUuids(): void
    {
        $owner = (new User())
            ->setEmail('merchant-category-processor@example.test')
            ->setPassword('password')
            ->setName('Marchand')
            ->setRoles(['ROLE_MERCHANT']);
        $shop = (new Shop())
            ->setName('Supérette Centrale')
            ->setSlug('superette-centrale')
            ->setQrCodeToken('processor-token')
            ->setOwner($owner);
        $merchantCategory = (new MerchantCategory())
            ->setShop($shop)
            ->setNameFr('Rayon frais')
            ->setSlug('rayon-frais');
        $input = new MerchantCategoryUpdateInput();
        $input->setParentId('not-a-uuid');

        $repository = $this->merchantCategoryRepositoryFinding($merchantCategory);
        $security = $this->createMock(Security::class);
        $security
            ->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_MERCHANT')
            ->willReturn(true);
        $security
            ->expects(self::once())
            ->method('getUser')
            ->willReturn($owner);

        $processor = new UpdateMerchantCategoryProcessor(
            $repository,
            new MerchantShopAccessChecker($security),
            new MerchantCategoryMapper(),
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('MERCHANT_CATEGORY_PARENT_NOT_FOUND');

        $processor->process(
            $input,
            new Patch(),
            ['merchantCategoryId' => $merchantCategory->getId()->toRfc4122()],
        );
    }

    /**
     * @return MerchantCategoryRepository&MockObject
     */
    private function merchantCategoryRepositoryFinding(MerchantCategory $merchantCategory): MerchantCategoryRepository
    {
        $repository = $this->createMock(MerchantCategoryRepository::class);
        $repository
            ->expects(self::once())
            ->method('find')
            ->with($merchantCategory->getId()->toRfc4122())
            ->willReturn($merchantCategory);

        return $repository;
    }
}
