<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\OrderWhatsappContactOutput;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\AdminAuditLogger;
use App\Service\WhatsappContactLinkFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Prepares a merchant→customer WhatsApp contact for an order (S14-005).
 * No message is sent automatically: the API returns a prefilled wa.me link
 * and records an admin-readable audit trail entry.
 *
 * @implements ProcessorInterface<null, OrderWhatsappContactOutput>
 */
final readonly class MerchantOrderWhatsappContactProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private WhatsappContactLinkFactory $whatsappContactLinkFactory,
        private AdminAuditLogger $auditLogger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderWhatsappContactOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $orderId = (string) ($uriVariables['orderId'] ?? '');
        if (!Uuid::isValid($orderId)) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $order = $this->orderRepository->findOneByShopAndId($shop, $orderId);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $phone = $this->whatsappContactLinkFactory->normalizePhone($order->getCustomer()->getPhone());
        if (null === $phone) {
            throw new ConflictHttpException('ORDER_WHATSAPP_CUSTOMER_PHONE_MISSING');
        }

        $orderNumber = $order->getOrderNumberDisplay() ?? $order->getId()->toRfc4122();
        $message = \sprintf(
            'Bonjour, ici %s au sujet de votre commande %s.',
            $shop->getName(),
            $orderNumber,
        );
        $whatsappUrl = $this->whatsappContactLinkFactory->buildUrl($phone, $message);

        $this->auditLogger->log(
            action: 'order.whatsapp_contact_prepared',
            resourceType: 'order',
            resourceId: $order->getId()->toRfc4122(),
            summary: \sprintf('Contact WhatsApp marchand→client préparé pour la commande %s.', $orderNumber),
            metadata: [
                'direction' => 'merchant_to_customer',
                'phone' => $phone,
                'shop_id' => $shop->getId()->toRfc4122(),
            ],
        );
        $this->entityManager->flush();

        return new OrderWhatsappContactOutput(
            id: $order->getId()->toRfc4122().'-whatsapp',
            orderId: $order->getId()->toRfc4122(),
            phone: $phone,
            message: $message,
            whatsappUrl: $whatsappUrl,
        );
    }
}
