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

// MS-001 (MVP-1): unified merchant settings entry point.
// Shortcuts route to existing pages. "Profil supérette" (MS-002), "Compte"
// (MS-003) and "Langue" (MS-004) are shown as disabled placeholders until
// their dedicated lots land.

type Shortcut = {
  href: string;
  title: string;
  description: string;
  icon: typeof CalendarClock;
};

const SHORTCUTS: Shortcut[] = [
  {
    href: '/merchant/creneaux',
    title: 'Horaires & créneaux',
    description: 'Créneaux de retrait, règles hebdomadaires et fermetures exceptionnelles.',
    icon: CalendarClock,
  },
  {
    href: '/merchant/apparence',
    title: 'Apparence',
    description: 'Couleurs et police de votre supérette, avec contrôle de contraste.',
    icon: Palette,
  },
  {
    href: '/merchant/qr-code',
    title: 'QR code magasin',
    description: 'Affichez, imprimez ou téléchargez le QR code de votre supérette.',
    icon: Printer,
  },
  {
    href: '/merchant/notifications',
    title: 'Notifications',
    description: 'Historique des notifications liées à vos commandes et retraits.',
    icon: Bell,
  },
];

type Upcoming = {
  title: string;
  description: string;
  icon: typeof Store;
};

const UPCOMING: Upcoming[] = [
  {
    title: 'Profil de la supérette',
    description: 'Nom, adresse, téléphone, logo et image de couverture vus par le client.',
    icon: Store,
  },
  {
    title: 'Compte',
    description: 'Email, nom affiché et changement de mot de passe.',
    icon: Lock,
  },
  {
    title: 'Langue de l’interface',
    description: 'Basculer entre français et arabe (RTL).',
    icon: Languages,
  },
];

export default function MerchantSettingsPage() {
  return (
    <div className="mx-auto w-full max-w-3xl space-y-8">
      <header className="space-y-1">
        <h1 className="text-xl font-black text-ink">Paramètres</h1>
        <p className="text-sm text-muted">
          Configurez votre supérette et votre compte marchand.
        </p>
      </header>

      <section className="space-y-3" aria-labelledby="settings-config">
        <h2 id="settings-config" className="text-sm font-bold uppercase tracking-wide text-muted">
          Configuration de la supérette
        </h2>
        <div className="grid gap-3 sm:grid-cols-2">
          {SHORTCUTS.map((item) => {
            const Icon = item.icon;
            return (
              <Link key={item.href} href={item.href} className="group block">
                <Card className="flex items-start gap-3 transition-colors group-hover:border-primary">
                  <span className="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-soft text-primary">
                    <Icon className="h-5 w-5" aria-hidden="true" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-1 font-bold text-ink">
                      {item.title}
                      <ChevronRight
                        className="h-4 w-4 text-muted transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                      />
                    </span>
                    <span className="mt-0.5 block text-sm text-muted">{item.description}</span>
                  </span>
                </Card>
              </Link>
            );
          })}
        </div>
      </section>

      <section className="space-y-3" aria-labelledby="settings-upcoming">
        <h2 id="settings-upcoming" className="text-sm font-bold uppercase tracking-wide text-muted">
          Compte & profil
        </h2>
        <div className="grid gap-3 sm:grid-cols-2">
          {UPCOMING.map((item) => {
            const Icon = item.icon;
            return (
              <Card
                key={item.title}
                className="flex items-start gap-3 opacity-60"
              >
                <span className="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-soft text-muted">
                  <Icon className="h-5 w-5" aria-hidden="true" />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="flex flex-wrap items-center gap-2 font-bold text-ink">
                    {item.title}
                    <span className="rounded-full bg-soft px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-muted">
                      Bientôt disponible
                    </span>
                  </span>
                  <span className="mt-0.5 block text-sm text-muted">{item.description}</span>
                </span>
              </Card>
            );
          })}
        </div>
      </section>
    </div>
  );
}
