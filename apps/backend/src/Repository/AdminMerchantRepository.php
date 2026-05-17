<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

final readonly class AdminMerchantRepository
{
    public function __construct(
        private UserRepository $userRepository,
        private ShopRepository $shopRepository,
    ) {
    }

    /**
     * @return list<User>
     */
    public function findPaginated(int $limit, int $offset): array
    {
        return \array_slice($this->findSorted(), $offset, $limit);
    }

    public function countAll(): int
    {
        return \count($this->findSorted());
    }

    public function findOne(string $id): ?User
    {
        $user = $this->userRepository->find($id);
        if (!$user instanceof User || !$this->isMerchant($user)) {
            return null;
        }

        return $user;
    }

    public function countStores(User $merchant): int
    {
        return (int) $this->shopRepository->count(['owner' => $merchant]);
    }

    /**
     * @return list<User>
     */
    private function findSorted(): array
    {
        $users = $this->userRepository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);

        return array_values(array_filter($users, $this->isMerchant(...)));
    }

    private function isMerchant(User $user): bool
    {
        return \in_array('ROLE_MERCHANT', $user->getRoles(), true);
    }
}
