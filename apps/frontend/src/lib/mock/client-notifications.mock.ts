export interface ClientNotificationItem {
  id: string;
  order_id: string | null;
  title_fr: string;
  title_ar: string;
  body_fr: string;
  body_ar: string;
  is_read: boolean;
  created_at: string;
}

export const MOCK_CLIENT_NOTIFICATIONS: ClientNotificationItem[] = [
  {
    id: "notif-c-1",
    order_id: "ord-4821",
    title_fr: "Commande prête à retirer",
    title_ar: "الطلب جاهز للاستلام",
    body_fr: "Ta commande CMD-4821 est prête. Présente ton QR code au marchand.",
    body_ar: "طلبك CMD-4821 جاهز. أرِ رمز QR للتاجر.",
    is_read: false,
    created_at: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
  },
  {
    id: "notif-c-2",
    order_id: "ord-partial-1",
    title_fr: "Commande partiellement acceptée",
    title_ar: "قُبل الطلب جزئيًا",
    body_fr: "Le marchand a modifié ta commande CMD-PART-1. Consulte ta Kadhia pour re-soumettre.",
    body_ar: "عدّل التاجر طلبك CMD-PART-1. راجع كذيتك لإعادة الإرسال.",
    is_read: true,
    created_at: new Date(Date.now() - 30 * 60 * 1000).toISOString(),
  },
  {
    id: "notif-c-3",
    order_id: "ord-4821",
    title_fr: "Commande acceptée",
    title_ar: "تم قبول الطلب",
    body_fr: "Ta commande CMD-4821 a été acceptée. Le marchand prépare ta Kadhia.",
    body_ar: "تم قبول طلبك CMD-4821. يقوم التاجر بتجهيز كذيتك.",
    is_read: true,
    created_at: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(),
  },
];
