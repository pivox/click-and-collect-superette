<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\OrderWhatsappContactOutput;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\AdminAuditLogger;
use App\Service\PickupSlotDisplayTime;
use App\Service\WhatsappContactLinkFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Prepares a customer→merchant WhatsApp contact for an order (S14-005).
 * No message is sent automatically: the API returns a prefilled wa.me link
 * and records an admin-readable audit trail entry.
 *
 * @implements ProcessorInterface<null, OrderWhatsappContactOutput>
 */
final readonly class CustomerOrderWhatsappContactProcessor implements ProcessorInterface
{
    public function __construct(
        private OrderRepository $orderRepository,
        private Security $security,
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
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $orderId = (string) ($uriVariables['orderId'] ?? '');
        if (!Uuid::isValid($orderId)) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $order = $this->orderRepository->findOneReadableByCustomerAndId($user, $orderId);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $phone = $this->whatsappContactLinkFactory->normalizePhone($order->getShop()->getPhone());
        if (null === $phone) {
            throw new ConflictHttpException('ORDER_WHATSAPP_SHOP_PHONE_MISSING');
        }

        $orderNumber = $order->getOrderNumberDisplay() ?? $order->getId()->toRfc4122();
        $message = $this->buildMessage($order, $orderNumber);
        $whatsappUrl = $this->whatsappContactLinkFactory->buildUrl($phone, $message);

        $this->auditLogger->log(
            action: 'order.whatsapp_contact_prepared',
            resourceType: 'order',
            resourceId: $order->getId()->toRfc4122(),
            summary: \sprintf('Contact WhatsApp client→marchand préparé pour la commande %s.', $orderNumber),
            metadata: [
                'direction' => 'customer_to_merchant',
                'actor_role' => 'customer',
                'phone' => $phone,
                'shop_id' => $order->getShop()->getId()->toRfc4122(),
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

    private function buildMessage(Order $order, string $orderNumber): string
    {
        $message = \sprintf(
            'Bonjour, je vous contacte au sujet de ma commande %s chez %s.',
            $orderNumber,
            $order->getShop()->getName(),
        );

        $slot = $order->getPickupSlot();
        if (null !== $slot) {
            $startsAt = PickupSlotDisplayTime::fromStoredLocalClock($slot->getStartsAt());
            $endsAt = PickupSlotDisplayTime::fromStoredLocalClock($slot->getEndsAt());
            $message .= \sprintf(
                ' Retrait prévu le %s entre %s et %s.',
                $startsAt->format('d/m/Y'),
                $startsAt->format('H:i'),
                $endsAt->format('H:i'),
            );
        }

        return $message;
    }
}
