import { apiClient } from "@/lib/api";
import { MOCK_OPENING_HOURS } from "@/lib/mock/merchant-opening-hours.mock";
import type { OpeningHours, OpeningHoursDay } from "@/lib/types/merchant-slots.types";
import { USE_MOCKS, mockDelay } from "./index";

// ─── Backend API shape ────────────────────────────────────────────────────────

interface ApiDayRange { start: string; end: string }
interface ApiOpeningHours {
  timezone: string;
  weekly: Record<string, ApiDayRange[]>;
}
interface ApiOpeningHoursResponse {
  store_id: string;
  opening_hours: ApiOpeningHours | null;
}

const TIMEZONE = "Africa/Tunis";

// 1=Monday … 7=Sunday
const DAY_KEYS: (keyof OpeningHours)[] = [
  "monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday",
];

// ─── Translators ─────────────────────────────────────────────────────────────

function apiToUi(api: ApiOpeningHours | null): OpeningHours {
  const fallback: OpeningHoursDay = { open: null, close: null, closed: true };
  if (!api?.weekly) {
    return DAY_KEYS.reduce(
      (acc, key) => ({ ...acc, [key]: { ...fallback } }),
      {} as OpeningHours,
    );
  }
  return DAY_KEYS.reduce((acc, key, idx) => {
    const ranges = api.weekly[String(idx + 1)] ?? [];
    const first = ranges[0];
    return {
      ...acc,
      [key]: first
        ? { open: first.start, close: first.end, closed: false }
        : { open: null, close: null, closed: true },
    };
  }, {} as OpeningHours);
}

function uiToApi(ui: OpeningHours): ApiOpeningHours {
  const weekly: Record<string, ApiDayRange[]> = {};
  DAY_KEYS.forEach((key, idx) => {
    const day = ui[key];
    weekly[String(idx + 1)] =
      day.closed || !day.open || !day.close
        ? []
        : [{ start: day.open, end: day.close }];
  });
  return { timezone: TIMEZONE, weekly };
}

// ─── Mock state ───────────────────────────────────────────────────────────────

let mockHours: OpeningHours = { ...MOCK_OPENING_HOURS };

// ─── Public API ───────────────────────────────────────────────────────────────

export async function getMerchantOpeningHours(storeId: string): Promise<OpeningHours> {
  if (USE_MOCKS) return mockDelay({ ...mockHours });
  const { data } = await apiClient.get<ApiOpeningHoursResponse>(
    `/api/merchant/stores/${storeId}/opening-hours`,
  );
  return apiToUi(data.opening_hours);
}

export async function patchMerchantOpeningHours(
  storeId: string,
  payload: OpeningHours,
): Promise<OpeningHours> {
  if (USE_MOCKS) {
    mockHours = { ...payload };
    return mockDelay({ ...mockHours });
  }
  const { data } = await apiClient.patch<ApiOpeningHoursResponse>(
    `/api/merchant/stores/${storeId}/opening-hours`,
    { opening_hours: uiToApi(payload) },
  );
  return apiToUi(data.opening_hours);
}
