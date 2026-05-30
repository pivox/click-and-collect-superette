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
  timeZone: "Africa/Tunis",
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
  timeZone: "Africa/Tunis",
});

export function formatTime(iso: string): string {
  try {
    return TIME_FR.format(new Date(iso));
  } catch {
    return iso;
  }
}

export function formatSlotRange(startsAt: string, endsAt: string): string {
  return `${formatSlotDate(startsAt)} - ${formatTime(endsAt)}`;
}

const SHORT_DATE_FR = new Intl.DateTimeFormat("fr-FR", {
  day: "numeric",
  month: "short",
  timeZone: "Africa/Tunis",
});

export function formatRelativeDate(iso: string): string {
  try {
    const date = new Date(iso);
    const diffMs = Date.now() - date.getTime();
    const minutes = Math.floor(diffMs / 60_000);
    if (minutes < 1) return "À l'instant";
    if (minutes < 60) return `il y a ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `il y a ${hours}h`;
    const days = Math.floor(hours / 24);
    if (days === 1) return "Hier";
    if (days < 7) return `il y a ${days} j`;
    return SHORT_DATE_FR.format(date);
  } catch {
    return iso;
  }
}
