<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

final readonly class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function notifyCustomerOrderAccepted(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia acceptée',
            'تم قبول القاضية',
            'Votre commande a été acceptée par la supérette.',
            'تم قبول طلبكم من طرف العطار.',
        );
    }

    public function notifyCustomerOrderRejected(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia refusée',
            'تم رفض القاضية',
            'Votre commande a été refusée par la supérette.',
            'تم رفض طلبكم من طرف العطار.',
        );
    }

    public function notifyCustomerOrderPartiallyAccepted(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia partiellement acceptée',
            'تم قبول جزء من القاضية',
            'Certains produits ne sont pas disponibles. Merci de vérifier votre Kadhia.',
            'بعض المنتجات غير متوفرة. يرجى مراجعة القاضية.',
        );
    }

    public function notifyCustomerOrderPreparing(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia en préparation',
            'القاضية في التحضير',
            'Votre commande est en cours de préparation.',
            'طلبكم في طور التحضير.',
        );
    }

    public function notifyCustomerOrderReady(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia prête',
            'القاضية واجدة',
            'Votre commande est prête à être retirée. Présentez votre QR code en supérette.',
            'طلبكم واجد للاستلام. أظهروا رمز QR في العطار.',
        );
    }

    public function notifyCustomerOrderCompleted(Order $order): void
    {
        $this->persistForCustomer(
            $order,
            'Kadhia retirée',
            'تم استلام القاضية',
            'Votre commande a été retirée avec succès.',
            'تم استلام طلبكم بنجاح.',
        );
    }

    public function notifyMerchantOrderSubmitted(Order $order): void
    {
        $this->persistForMerchant(
            $order,
            'Nouvelle commande',
            'طلب جديد',
            'Une nouvelle Kadhia a été soumise.',
            'تم إرسال قاضية جديدة.',
        );
    }

    public function notifyMerchantOrderCancelled(Order $order): void
    {
        $this->persistForMerchant(
            $order,
            'Commande annulée',
            'تم إلغاء الطلب',
            'Le client a annulé sa commande.',
            'قام الحريف بإلغاء الطلب.',
        );
    }

    public function notifyMerchantPickupCompleted(Order $order): void
    {
        $this->persistForMerchant(
            $order,
            'Retrait finalisé',
            'تم إتمام الاستلام',
            'Le retrait de la commande est finalisé.',
            'تم إتمام استلام الطلب.',
        );
    }

    private function persistForCustomer(
        Order $order,
        string $titleFr,
        string $titleAr,
        string $bodyFr,
        string $bodyAr,
    ): void {
        $notification = new Notification(
            user: $order->getCustomer(),
            titleFr: $titleFr,
            titleAr: $titleAr,
            bodyFr: $bodyFr,
            bodyAr: $bodyAr,
            order: $order,
        );
        $this->entityManager->persist($notification);
    }

    private function persistForMerchant(
        Order $order,
        string $titleFr,
        string $titleAr,
        string $bodyFr,
        string $bodyAr,
    ): void {
        $owner = $order->getShop()->getOwner();
        if (null === $owner) {
            return;
        }
        $notification = new Notification(
            user: $owner,
            titleFr: $titleFr,
            titleAr: $titleAr,
            bodyFr: $bodyFr,
            bodyAr: $bodyAr,
            order: $order,
        );
        $this->entityManager->persist($notification);
    }
}
