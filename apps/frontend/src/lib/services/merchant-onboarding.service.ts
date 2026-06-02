import { apiClient } from "@/lib/api";
import { MOCK_ONBOARDING, type OnboardingStatus } from "@/lib/mock/merchant-onboarding.mock";
import { USE_MOCKS, mockDelay } from "./index";

export type { OnboardingStatus };
export type { OnboardingStep } from "@/lib/mock/merchant-onboarding.mock";

let mockStatus: OnboardingStatus = { ...MOCK_ONBOARDING, steps: [...MOCK_ONBOARDING.steps] };

export async function getMerchantOnboarding(): Promise<OnboardingStatus> {
  if (USE_MOCKS) return mockDelay({ ...mockStatus, steps: [...mockStatus.steps] });
  const { data } = await apiClient.get<OnboardingStatus>("/api/merchant/onboarding");
  return data;
}

export async function completeMerchantOnboarding(): Promise<void> {
  if (USE_MOCKS) {
    mockStatus = { ...mockStatus, is_complete: true, completion_percentage: 100 };
    return mockDelay(undefined);
  }
  await apiClient.patch("/api/merchant/onboarding/complete", {});
}
