/**
 * Formatters scoped to the Tunisian dinar (TND) — the prototype always
 * shows prices with 3 decimal places ("3.000 TND", "10.550 TND"), which
 * matches Tunisian retail conventions.
 */

const TND = new Intl.NumberFormat("fr-FR", {
  minimumFractionDigits: 3,
  maximumFractionDigits: 3,
});

export function formatTnd(amount: number | string): string {
  const n = typeof amount === "string" ? parseFloat(amount) : amount;
  if (!Number.isFinite(n)) return "0.000 TND";
  return `${TND.format(n)} TND`;
}

const DATE_FR = new Intl.DateTimeFormat("fr-FR", {
  weekday: "long",
  hour: "2-digit",
  minute: "2-digit",
});

export function formatSlotDate(iso: string): string {
  try {
    return DATE_FR.format(new Date(iso));
  } catch {
    return iso;
  }
}

const TIME_FR = new Intl.DateTimeFormat("fr-FR", {
  hour: "2-digit",
  minute: "2-digit",
});

export function formatTime(iso: string): string {
  try {
    return TIME_FR.format(new Date(iso));
  } catch {
    return iso;
  }
}
