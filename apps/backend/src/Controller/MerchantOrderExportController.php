<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\PickupSlot;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\PickupSlotDisplayTime;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class MerchantOrderExportController extends AbstractController
{
    private const int MAX_RANGE_DAYS = 92;

    public function __construct(
        private readonly ShopRepository $shopRepository,
        private readonly OrderRepository $orderRepository,
        private readonly MerchantShopAccessChecker $merchantShopAccessChecker,
        #[Autowire(service: 'monolog.logger.order')]
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/merchant/stores/{storeId}/orders/export.csv', name: 'merchant_order_export_csv', methods: ['GET'])]
    public function export(string $storeId, Request $request): StreamedResponse
    {
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $dateFrom = $this->requireDate($request->query->get('date_from'), false, 'ORDER_EXPORT_MISSING_DATE_FROM', 'ORDER_EXPORT_INVALID_DATE_FROM');
        $dateTo = $this->requireDate($request->query->get('date_to'), true, 'ORDER_EXPORT_MISSING_DATE_TO', 'ORDER_EXPORT_INVALID_DATE_TO');

        if ($dateFrom > $dateTo) {
            throw new BadRequestHttpException('ORDER_EXPORT_INVALID_DATE_RANGE');
        }

        $diffDays = (int) $dateFrom->diff($dateTo)->days;
        if ($diffDays > self::MAX_RANGE_DAYS) {
            throw new BadRequestHttpException('ORDER_EXPORT_RANGE_TOO_LARGE');
        }

        $status = $this->parseStatus($request->query->get('status'));
        $orders = $this->orderRepository->findForExport($shop, $status, $dateFrom, $dateTo);

        $rowCount = \count($orders);
        $dateFromStr = $dateFrom->format('Y-m-d');
        $dateToStr = (clone $dateTo)->setTime(0, 0)->format('Y-m-d');

        $this->logger->info('merchant.orders_exported', [
            'store_id' => $storeId,
            'date_from' => $dateFromStr,
            'date_to' => $dateToStr,
            'row_count' => $rowCount,
            'status_filter' => $status?->value,
        ]);

        $filename = \sprintf('commandes-%s-%s-%s.csv', $storeId, $dateFromStr, $dateToStr);

        $response = new StreamedResponse(function () use ($orders): void {
            $stream = fopen('php://output', 'w');
            if (false === $stream) {
                throw new \RuntimeException('CSV_STREAM_OPEN_FAILED');
            }

            // BOM for Excel FR/TN UTF-8 recognition (Arabic names, accented chars)
            fwrite($stream, "\xEF\xBB\xBF");

            // RFC 4180 compliant: $escape='' uses "" doubling, not backslash-escaping
            fputcsv($stream, [
                'order_id',
                'status',
                'customer_name',
                'customer_phone',
                'total_tnd',
                'pickup_starts_at',
                'pickup_ends_at',
                'created_at',
                'updated_at',
            ], ';', '"', '');

            foreach ($orders as $order) {
                fputcsv($stream, $this->toRow($order), ';', '"', '');
            }

            fclose($stream);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    private function requireDate(?string $raw, bool $endOfDay, string $missingCode, string $invalidCode): \DateTimeImmutable
    {
        $raw = trim((string) $raw);
        if ('' === $raw) {
            throw new BadRequestHttpException($missingCode);
        }

        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            throw new BadRequestHttpException($invalidCode);
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new \DateTimeZone('Africa/Tunis'));
        if (false === $date || $date->format('Y-m-d') !== $raw) {
            throw new BadRequestHttpException($invalidCode);
        }

        return $endOfDay ? $date->setTime(23, 59, 59, 999999) : $date->setTime(0, 0);
    }

    private function parseStatus(?string $raw): ?OrderStatus
    {
        $raw = trim((string) $raw);
        if ('' === $raw) {
            return null;
        }

        $status = OrderStatus::tryFrom($raw);
        if (null === $status || OrderStatus::Draft === $status) {
            throw new BadRequestHttpException('ORDER_EXPORT_INVALID_STATUS');
        }

        return $status;
    }

    /**
     * @return list<string>
     */
    private function toRow(Order $order): array
    {
        $customer = $order->getCustomer();
        $slot = $order->getPickupSlot();

        $firstName = $customer->getFirstName() ?? '';
        $lastName = $customer->getLastName() ?? '';
        $customerName = trim($firstName.' '.$lastName);
        if ('' === $customerName) {
            $customerName = $customer->getName();
        }

        return [
            $order->getId()->toRfc4122(),
            $order->getStatus()->value,
            $this->neutralizeFormula($customerName),
            $this->neutralizeFormula($customer->getPhone() ?? ''),
            $order->getTotalTnd(),
            $slot instanceof PickupSlot ? PickupSlotDisplayTime::toLocalAtom($slot->getStartsAt()) : '',
            $slot instanceof PickupSlot ? PickupSlotDisplayTime::toLocalAtom($slot->getEndsAt()) : '',
            $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function neutralizeFormula(string $value): string
    {
        if ('' !== $value && \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
