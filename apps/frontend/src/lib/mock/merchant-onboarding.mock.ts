export interface OnboardingStep {
  key: string;
  label: string;
  done: boolean;
}

export interface OnboardingStatus {
  is_complete: boolean;
  completion_percentage: number;
  steps: OnboardingStep[];
}

export const MOCK_ONBOARDING: OnboardingStatus = {
  is_complete: false,
  completion_percentage: 40,
  steps: [
    { key: "store_profile",  label: "Compléter le profil supérette",    done: true  },
    { key: "qr_code",        label: "Accéder au QR code",               done: true  },
    { key: "catalog",        label: "Ajouter des produits",              done: false },
    { key: "opening_hours",  label: "Configurer les horaires d'ouverture", done: false },
    { key: "pickup_slots",   label: "Créer des créneaux de retrait",     done: false },
  ],
};
