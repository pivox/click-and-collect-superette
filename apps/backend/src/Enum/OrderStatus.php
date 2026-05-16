<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case PickupPending = 'pickup_pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function labelFr(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Submitted => 'Commande envoyée',
            self::Accepted => 'Commande acceptée',
            self::PartiallyAccepted => 'Commande partiellement acceptée',
            self::Rejected => 'Commande refusée',
            self::Preparing => 'En préparation',
            self::Ready => 'Prête à retirer',
            self::PickupPending => 'Retrait en cours',
            self::Completed => 'Commande retirée',
            self::Cancelled => 'Commande annulée',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Submitted => 'تم إرسال الطلب',
            self::Accepted => 'تم قبول الطلب',
            self::PartiallyAccepted => 'تم قبول جزء من الطلب',
            self::Rejected => 'تم رفض الطلب',
            self::Preparing => 'قيد التحضير',
            self::Ready => 'جاهزة للاستلام',
            self::PickupPending => 'الاستلام قيد التنفيذ',
            self::Completed => 'تم استلام الطلب',
            self::Cancelled => 'تم إلغاء الطلب',
        };
    }
}
