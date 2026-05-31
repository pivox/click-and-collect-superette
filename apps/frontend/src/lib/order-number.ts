interface OrderNumberLike {
  id?: string | null;
  code?: string | null;
  order_number?: number | string | null;
  order_number_display?: string | null;
}

const UUID_PATTERN =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function formatReadableOrderNumber(orderNumber: number | string | null | undefined): string | null {
  if (orderNumber === null || orderNumber === undefined || orderNumber === '') {
    return null;
  }

  if (typeof orderNumber === 'string') {
    const trimmed = orderNumber.trim();
    if (trimmed === '') return null;
    if (trimmed.startsWith('#')) return trimmed;
    if (/^\d+$/.test(trimmed)) {
      const parsed = Number.parseInt(trimmed, 10);
      if (parsed <= 0) return null;

      return `#${String(parsed).padStart(4, '0')}`;
    }

    return trimmed;
  }

  if (!Number.isInteger(orderNumber) || orderNumber < 1) {
    return null;
  }

  return `#${String(orderNumber).padStart(4, '0')}`;
}

export function fallbackOrderCode(orderId: string | null | undefined): string {
  if (!orderId) return '';

  return UUID_PATTERN.test(orderId) ? `CMD-${orderId.slice(0, 8).toUpperCase()}` : orderId;
}

export function displayOrderCode(order: OrderNumberLike): string {
  const display = order.order_number_display?.trim();
  if (display) return display;

  const numberDisplay = formatReadableOrderNumber(order.order_number);
  if (numberDisplay) return numberDisplay;

  const code = order.code?.trim();
  if (code) return code;

  return fallbackOrderCode(order.id);
}
