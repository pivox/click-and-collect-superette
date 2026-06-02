import { apiClient } from "@/lib/api";
import { MOCK_ONBOARDING, type OnboardingStatus } from "@/lib/mock/merchant-onboarding.mock";
import { USE_MOCKS, mockDelay } from "./index";

export type { OnboardingStatus };
export type { OnboardingStep } from "@/lib/mock/merchant-onboarding.mock";

// ─── Backend API shape ────────────────────────────────────────────────────────

interface ApiOnboardingStep {
  key: string;
  label: string;
  completed: boolean;
}
interface ApiOnboardingStatus {
  user_id: string;
  completed: boolean;
  completed_at: string | null;
  steps: ApiOnboardingStep[];
}

function mapApiToStatus(api: ApiOnboardingStatus): OnboardingStatus {
  const doneCount = api.steps.filter((s) => s.completed).length;
  const total = api.steps.length;
  return {
    is_complete: api.completed,
    completion_percentage: total > 0 ? Math.round((doneCount / total) * 100) : 0,
    steps: api.steps.map((s) => ({ key: s.key, label: s.label, done: s.completed })),
  };
}

// ─── Mock state ───────────────────────────────────────────────────────────────

let mockStatus: OnboardingStatus = { ...MOCK_ONBOARDING, steps: [...MOCK_ONBOARDING.steps] };

// ─── Public API ───────────────────────────────────────────────────────────────

export async function getMerchantOnboarding(): Promise<OnboardingStatus> {
  if (USE_MOCKS) return mockDelay({ ...mockStatus, steps: [...mockStatus.steps] });
  const { data } = await apiClient.get<ApiOnboardingStatus>("/api/merchant/onboarding");
  return mapApiToStatus(data);
}

export async function completeMerchantOnboarding(): Promise<void> {
  if (USE_MOCKS) {
    mockStatus = { ...mockStatus, is_complete: true, completion_percentage: 100 };
    return mockDelay(undefined);
  }
  await apiClient.patch("/api/merchant/onboarding/complete", {});
}
