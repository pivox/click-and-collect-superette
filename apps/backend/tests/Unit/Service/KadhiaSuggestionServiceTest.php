<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use App\Service\KadhiaSuggestionService;
use PHPUnit\Framework\TestCase;

final class KadhiaSuggestionServiceTest extends TestCase
{
    private KadhiaSuggestionService $service;
    private Shop $shop;

    protected function setUp(): void
    {
        $this->service = new KadhiaSuggestionService();
        $this->shop = new Shop();
    }

    // buildFrequentlyBoughtTogether

    public function testCoOccurrenceRanksByFrequency(): void
    {
        $seed = $this->makeProduct('Lait');
        $coffee = $this->makeProduct('Café');
        $sugar = $this->makeProduct('Sucre');

        // Café co-occurs twice with the seed, Sucre once.
        $orders = [
            $this->makeOrder($seed, $coffee, $sugar),
            $this->makeOrder($seed, $coffee),
        ];

        $result = $this->service->buildFrequentlyBoughtTogether($orders, [$this->id($seed)], 10);

        self::assertSame([$this->id($coffee), $this->id($sugar)], array_map($this->id(...), $result));
    }

    public function testCoOccurrenceTieBreaksAlphabetically(): void
    {
        $seed = $this->makeProduct('Lait');
        $zaatar = $this->makeProduct('Zaatar');
        $harissa = $this->makeProduct('Harissa');

        $orders = [$this->makeOrder($seed, $zaatar, $harissa)];

        $result = $this->service->buildFrequentlyBoughtTogether($orders, [$this->id($seed)], 10);

        self::assertSame(['Harissa', 'Zaatar'], array_map(static fn (MerchantProduct $p): string => $p->getDisplayNameFr(), $result));
    }

    public function testCoOccurrenceExcludesSeedProducts(): void
    {
        $seedA = $this->makeProduct('Lait');
        $seedB = $this->makeProduct('Pain');
        $other = $this->makeProduct('Beurre');

        $orders = [$this->makeOrder($seedA, $seedB, $other)];

        $result = $this->service->buildFrequentlyBoughtTogether($orders, [$this->id($seedA), $this->id($seedB)], 10);

        self::assertSame([$this->id($other)], array_map($this->id(...), $result));
    }

    public function testCoOccurrenceIgnoresOrdersWithoutSeed(): void
    {
        $seed = $this->makeProduct('Lait');
        $other = $this->makeProduct('Beurre');

        $orders = [$this->makeOrder($other)];

        self::assertSame([], $this->service->buildFrequentlyBoughtTogether($orders, [$this->id($seed)], 10));
    }

    public function testCoOccurrenceExcludesNonSuggestibleProducts(): void
    {
        $seed = $this->makeProduct('Lait');
        $unavailable = $this->makeProduct('Beurre');
        $unavailable->setAvailable(false);
        $hidden = $this->makeProduct('Œufs');
        $hidden->setVisible(false);
        $ok = $this->makeProduct('Sucre');

        $orders = [$this->makeOrder($seed, $unavailable, $hidden, $ok)];

        $result = $this->service->buildFrequentlyBoughtTogether($orders, [$this->id($seed)], 10);

        self::assertSame([$this->id($ok)], array_map($this->id(...), $result));
    }

    public function testCoOccurrenceRespectsLimit(): void
    {
        $seed = $this->makeProduct('Lait');
        $orders = [$this->makeOrder($seed, $this->makeProduct('A'), $this->makeProduct('B'), $this->makeProduct('C'))];

        self::assertCount(2, $this->service->buildFrequentlyBoughtTogether($orders, [$this->id($seed)], 2));
    }

    public function testCoOccurrenceWithEmptySeedReturnsEmpty(): void
    {
        $orders = [$this->makeOrder($this->makeProduct('Lait'))];

        self::assertSame([], $this->service->buildFrequentlyBoughtTogether($orders, [], 10));
    }

    // buildRecentlyOrdered

    public function testRecentlyOrderedReturnsDistinctProductsNewestFirst(): void
    {
        $milk = $this->makeProduct('Lait');
        $bread = $this->makeProduct('Pain');

        // Orders are provided newest first; Lait appears in both.
        $orders = [
            $this->makeOrder($milk),
            $this->makeOrder($bread, $milk),
        ];

        $result = $this->service->buildRecentlyOrdered($orders, [], 10);

        self::assertSame([$this->id($milk), $this->id($bread)], array_map($this->id(...), $result));
    }

    public function testRecentlyOrderedExcludesKadhiaProducts(): void
    {
        $milk = $this->makeProduct('Lait');
        $bread = $this->makeProduct('Pain');

        $orders = [$this->makeOrder($milk, $bread)];

        $result = $this->service->buildRecentlyOrdered($orders, [$this->id($milk)], 10);

        self::assertSame([$this->id($bread)], array_map($this->id(...), $result));
    }

    public function testRecentlyOrderedExcludesUnavailableProducts(): void
    {
        $unavailable = $this->makeProduct('Lait');
        $unavailable->setAvailable(false);

        $orders = [$this->makeOrder($unavailable)];

        self::assertSame([], $this->service->buildRecentlyOrdered($orders, [], 10));
    }

    public function testRecentlyOrderedRespectsLimit(): void
    {
        $orders = [$this->makeOrder($this->makeProduct('A'), $this->makeProduct('B'), $this->makeProduct('C'))];

        self::assertCount(2, $this->service->buildRecentlyOrdered($orders, [], 2));
    }

    // findReplacementsFor

    public function testReplacementsKeepSameCategoryOnly(): void
    {
        $unavailable = $this->makeProduct('Lait Délice', category: 'laitages');
        $sameCategory = $this->makeProduct('Lait Vitalait', category: 'laitages');
        $otherCategory = $this->makeProduct('Harissa', category: 'epicerie');

        $result = $this->service->findReplacementsFor($unavailable, '2.000', [$sameCategory, $otherCategory], []);

        self::assertSame([$this->id($sameCategory)], array_map($this->id(...), $result));
    }

    public function testReplacementsExcludeSelfAndKadhiaProducts(): void
    {
        $unavailable = $this->makeProduct('Lait Délice', category: 'laitages');
        $inKadhia = $this->makeProduct('Lait Vitalait', category: 'laitages');
        $candidate = $this->makeProduct('Lait Natilait', category: 'laitages');

        $result = $this->service->findReplacementsFor(
            $unavailable,
            '2.000',
            [$unavailable, $inKadhia, $candidate],
            [$this->id($inKadhia)],
        );

        self::assertSame([$this->id($candidate)], array_map($this->id(...), $result));
    }

    public function testReplacementsSortByPriceProximity(): void
    {
        $unavailable = $this->makeProduct('Lait Délice', category: 'laitages');
        $far = $this->makeProduct('Lait Premium', category: 'laitages', price: '5.500');
        $close = $this->makeProduct('Lait Vitalait', category: 'laitages', price: '2.100');

        $result = $this->service->findReplacementsFor($unavailable, '2.000', [$far, $close], []);

        self::assertSame([$this->id($close), $this->id($far)], array_map($this->id(...), $result));
    }

    public function testReplacementsLimitToThreeByDefault(): void
    {
        $unavailable = $this->makeProduct('Lait Délice', category: 'laitages');
        $candidates = [
            $this->makeProduct('A', category: 'laitages'),
            $this->makeProduct('B', category: 'laitages'),
            $this->makeProduct('C', category: 'laitages'),
            $this->makeProduct('D', category: 'laitages'),
        ];

        self::assertCount(3, $this->service->findReplacementsFor($unavailable, '2.000', $candidates, []));
    }

    // isSuggestible

    public function testIsSuggestibleRejectsZeroPriceAndNonApprovedReference(): void
    {
        $zeroPrice = $this->makeProduct('Gratuit', price: '0.000');
        self::assertFalse($this->service->isSuggestible($zeroPrice));

        $pending = $this->makeProduct('En attente');
        $reference = $pending->getProductReference();
        self::assertNotNull($reference);
        $reference->setStatus(ProductReferenceStatus::PendingReview);
        self::assertFalse($this->service->isSuggestible($pending));

        self::assertTrue($this->service->isSuggestible($this->makeProduct('OK')));
    }

    public function testIsListableIgnoresAvailabilityButKeepsCatalogRules(): void
    {
        $unavailable = $this->makeProduct('Rupture');
        $unavailable->setAvailable(false);
        self::assertTrue($this->service->isListable($unavailable));
        self::assertFalse($this->service->isSuggestible($unavailable));

        $hidden = $this->makeProduct('Masqué');
        $hidden->setVisible(false);
        self::assertFalse($this->service->isListable($hidden));

        self::assertFalse($this->service->isListable($this->makeProduct('Gratuit', price: '0.000')));

        $archived = $this->makeProduct('Archivé');
        $archived->getProductReference()?->setStatus(ProductReferenceStatus::Archived);
        self::assertFalse($this->service->isListable($archived));
    }

    // Helpers

    private function makeProduct(string $name, string $category = 'epicerie', string $price = '2.000'): MerchantProduct
    {
        $categoryEntity = (new Category())
            ->setNameFr(ucfirst($category))
            ->setSlug($category);
        $reference = (new ProductReference())
            ->setNameFr($name)
            ->setCategory($categoryEntity)
            ->setStatus(ProductReferenceStatus::Approved);

        return (new MerchantProduct())
            ->setShop($this->shop)
            ->setProductReference($reference)
            ->setPriceTnd($price);
    }

    private function makeOrder(MerchantProduct ...$products): Order
    {
        $order = (new Order())->setShop($this->shop);
        foreach ($products as $product) {
            $line = (new OrderLine())
                ->setMerchantProduct($product)
                ->setQuantity(1)
                ->setUnitPriceTnd($product->getPriceTnd())
                ->setLineTotalTnd($product->getPriceTnd());
            $order->addLine($line);
        }

        return $order;
    }

    private function id(MerchantProduct $product): string
    {
        return $product->getId()->toRfc4122();
    }
}
