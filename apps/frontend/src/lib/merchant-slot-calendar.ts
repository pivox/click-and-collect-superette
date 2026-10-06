const TUNIS_CALENDAR = new Intl.DateTimeFormat('en', {
  timeZone: 'Africa/Tunis', year: 'numeric', month: 'numeric', day: 'numeric',
});

/** A local Date used only as a calendar selection, with the Tunis year/month/day. */
export function tunisCalendarDate(instant: Date): Date {
  const parts = TUNIS_CALENDAR.formatToParts(instant);
  const value = (type: string) => Number(parts.find((part) => part.type === type)?.value);
  return new Date(value('year'), value('month') - 1, value('day'));
}

/** Convert a calendar selection to the real midnight boundaries in Tunis. */
export function tunisDayBounds(date: Date): { start: Date; end: Date } {
  const pad = (value: number) => String(value).padStart(2, '0');
  const day = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  const start = new Date(`${day}T00:00:00+01:00`);
  return { start, end: new Date(start.getTime() + 86_400_000) };
}
