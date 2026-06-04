'use client';

import Link from 'next/link';
import {
  Bell,
  CalendarClock,
  ChevronRight,
  Languages,
  Lock,
  Palette,
  Printer,
  Store,
} from 'lucide-react';
import { Card } from '@/components/ui/Card';
import { useMerchantLocale } from '@/lib/i18n/MerchantLocaleContext';

// MS-001 (MVP-1): unified merchant settings entry point.
// MS-002 (MVP-2): the "Profil de la supérette" shortcut is now live.
// MS-003 (MVP-3): the "Compte" shortcut is now live.
// MS-004 (MVP-4): the "Langue" shortcut is now live and the hub labels are i18n-driven.

type Shortcut = {
  href: string;
  titleKey: string;
  descriptionKey: string;
  icon: typeof CalendarClock;
};

const CONFIG_SHORTCUTS: Shortcut[] = [
  {
    href: '/merchant/creneaux',
    titleKey: 'merchant.settings.slots.title',
    descriptionKey: 'merchant.settings.slots.description',
    icon: CalendarClock,
  },
  {
    href: '/merchant/apparence',
    titleKey: 'merchant.settings.appearance.title',
    descriptionKey: 'merchant.settings.appearance.description',
    icon: Palette,
  },
  {
    href: '/merchant/qr-code',
    titleKey: 'merchant.settings.qr.title',
    descriptionKey: 'merchant.settings.qr.description',
    icon: Printer,
  },
  {
    href: '/merchant/notifications',
    titleKey: 'merchant.settings.notifications.title',
    descriptionKey: 'merchant.settings.notifications.description',
    icon: Bell,
  },
];

const ACCOUNT_SHORTCUTS: Shortcut[] = [
  {
    href: '/merchant/parametres/profil',
    titleKey: 'merchant.settings.storeProfile.title',
    descriptionKey: 'merchant.settings.storeProfile.description',
    icon: Store,
  },
  {
    href: '/merchant/parametres/compte',
    titleKey: 'merchant.settings.account.title',
    descriptionKey: 'merchant.settings.account.description',
    icon: Lock,
  },
  {
    href: '/merchant/parametres/langue',
    titleKey: 'merchant.settings.language.title',
    descriptionKey: 'merchant.settings.language.description',
    icon: Languages,
  },
];

function ShortcutCard({ item }: { item: Shortcut }) {
  const { t } = useMerchantLocale();
  const Icon = item.icon;
  return (
    <Link href={item.href} className="group block">
      <Card className="flex items-start gap-3 transition-colors group-hover:border-primary">
        <span className="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-soft text-primary">
          <Icon className="h-5 w-5" aria-hidden="true" />
        </span>
        <span className="min-w-0 flex-1">
          <span className="flex items-center gap-1 font-bold text-ink">
            {t(item.titleKey)}
            <ChevronRight
              className="h-4 w-4 text-muted transition-transform group-hover:translate-x-0.5"
              aria-hidden="true"
            />
          </span>
          <span className="mt-0.5 block text-sm text-muted">{t(item.descriptionKey)}</span>
        </span>
      </Card>
    </Link>
  );
}

export default function MerchantSettingsPage() {
  const { t } = useMerchantLocale();

  return (
    <div className="mx-auto w-full max-w-3xl space-y-8">
      <header className="space-y-1">
        <h1 className="text-xl font-black text-ink">{t('merchant.settings.title')}</h1>
        <p className="text-sm text-muted">{t('merchant.settings.subtitle')}</p>
      </header>

      <section className="space-y-3" aria-labelledby="settings-config">
        <h2 id="settings-config" className="text-sm font-bold uppercase tracking-wide text-muted">
          {t('merchant.settings.sectionConfig')}
        </h2>
        <div className="grid gap-3 sm:grid-cols-2">
          {CONFIG_SHORTCUTS.map((item) => (
            <ShortcutCard key={item.href} item={item} />
          ))}
        </div>
      </section>

      <section className="space-y-3" aria-labelledby="settings-account">
        <h2 id="settings-account" className="text-sm font-bold uppercase tracking-wide text-muted">
          {t('merchant.settings.sectionAccount')}
        </h2>
        <div className="grid gap-3 sm:grid-cols-2">
          {ACCOUNT_SHORTCUTS.map((item) => (
            <ShortcutCard key={item.href} item={item} />
          ))}
        </div>
      </section>
    </div>
  );
}
