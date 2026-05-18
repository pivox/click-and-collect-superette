<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminCategoryOutput;
use App\Dto\AdminUpdateCategoryInput;
use App\Provider\AdminCategoryItemProvider;
use App\Repository\AdminCategoryRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminUpdateCategoryInput, AdminCategoryOutput>
 */
final readonly class AdminUpdateCategoryProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminCategoryRepository $adminCategoryRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminCategoryOutput
    {
        if (!$data instanceof AdminUpdateCategoryInput) {
            throw new \InvalidArgumentException('AdminUpdateCategoryInput expected.');
        }

        $categoryId = (string) ($uriVariables['categoryId'] ?? '');
        if (!Uuid::isValid($categoryId)) {
            throw new NotFoundHttpException('ADMIN_CATEGORY_NOT_FOUND');
        }

        $category = $this->adminCategoryRepository->findOne($categoryId);
        if (null === $category) {
            throw new NotFoundHttpException('ADMIN_CATEGORY_NOT_FOUND');
        }

        $payload = $this->currentPayload();

        if (\array_key_exists('nameFr', $payload) && null !== $data->nameFr) {
            $nameFr = trim($data->nameFr);
            if ('' === $nameFr) {
                throw new UnprocessableEntityHttpException('ADMIN_CATEGORY_NAME_FR_BLANK');
            }
            $category->setNameFr($nameFr);
        }
        if (\array_key_exists('nameAr', $payload)) {
            $nameAr = null !== $data->nameAr ? trim($data->nameAr) : null;
            $category->setNameAr('' === $nameAr ? null : $nameAr);
        }
        if (\array_key_exists('isActive', $payload) && null !== $data->isActive) {
            $category->setActive((bool) $data->isActive);
        }

        $this->adminCategoryRepository->save($category);

        return AdminCategoryItemProvider::toOutput($category);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || '' === $request->getContent()) {
            return [];
        }

        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
    }
}
