<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminStoreOutput;
use App\ApiResource\AdminStoreOutputFactory;
use App\Dto\AdminStoreCreateInput;
use App\Entity\Shop;
use App\Entity\User;
use App\Repository\AdminMerchantRepository;
use App\Repository\AdminStoreRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminStoreCreateInput, AdminStoreOutput>
 */
final readonly class CreateAdminStoreProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminStoreRepository $adminStoreRepository,
        private AdminStoreOutputFactory $adminStoreOutputFactory,
        private AdminMerchantRepository $adminMerchantRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminStoreOutput
    {
        if (!$data instanceof AdminStoreCreateInput) {
            throw new \InvalidArgumentException('AdminStoreCreateInput expected.');
        }

        $name = $this->normalizeRequiredString((string) $data->name);
        if ('' === $name) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('ADMIN_STORE_NAME_BLANK');
        }

        $shop = (new Shop())
            ->setName($name)
            ->setSlug($this->generateUniqueSlug($name))
            ->setAddress($this->normalizeNullableString($data->address))
            ->setCity($this->normalizeNullableString($data->city))
            ->setPhone($this->normalizeNullableString($data->phone))
            ->setQrCodeToken(Uuid::v4()->toRfc4122())
            ->setOwner($this->resolveMerchantOwner($data->ownerId))
            ->setActive(true);

        $this->adminStoreRepository->save($shop);

        return $this->adminStoreOutputFactory->create($shop, productsCount: 0);
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $suffix = 2;

        while (null !== $this->adminStoreRepository->findOneBySlug($slug)) {
            $suffixText = '-'.$suffix;
            $slug = mb_substr($base, 0, 180 - mb_strlen($suffixText)).$suffixText;
            ++$suffix;
        }

        return $slug;
    }

    private function slugify(string $name): string
    {
        $slug = (new AsciiSlugger('fr'))->slug($name)->lower()->toString();

        return '' === $slug ? 'store' : mb_substr($slug, 0, 180);
    }

    private function resolveMerchantOwner(?string $ownerId): ?User
    {
        if (null === $ownerId) {
            return null;
        }

        $owner = $this->adminMerchantRepository->findOne($ownerId);
        if (!$owner instanceof User) {
            throw new NotFoundHttpException('ADMIN_STORE_OWNER_NOT_FOUND');
        }

        return $owner;
    }

    private function normalizeRequiredString(string $value): string
    {
        return trim($value);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
