'use client';

import { useEffect, useRef, type ReactNode } from 'react';

export function SubscriptionDialog({ title, children, onClose, busy = false }: { title: string; children: ReactNode; onClose: () => void; busy?: boolean }) {
  const panel = useRef<HTMLElement>(null);
  useEffect(() => {
    const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    panel.current?.focus();
    return () => { previous?.focus(); };
  }, []);

  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-3 sm:p-4">
    <section ref={panel} role="dialog" aria-modal="true" aria-label={title} tabIndex={-1}
      className="max-h-[90dvh] w-full max-w-3xl overflow-y-auto rounded-xl bg-card p-4 shadow-xl sm:p-6"
      onKeyDown={(event) => {
        if (event.key === 'Escape') { event.stopPropagation(); if (!busy) onClose(); }
        if (event.key !== 'Tab') return;
        const elements = Array.from(panel.current?.querySelectorAll<HTMLElement>('a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), summary, [tabindex="0"]') ?? []).filter((el) => !el.closest('fieldset:disabled') && el.tabIndex >= 0);
        if (elements.length === 0) { event.preventDefault(); panel.current?.focus(); return; }
        const first = elements[0];
        const last = elements[elements.length - 1];
        if (event.shiftKey && (document.activeElement === first || document.activeElement === panel.current)) { event.preventDefault(); last?.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
      }}>
      <div className="mb-5 flex items-start justify-between gap-4">
        <h2 className="text-lg font-black">{title}</h2>
        <button type="button" disabled={busy} className="min-h-[44px] px-2 text-sm font-semibold text-muted disabled:opacity-50" onClick={onClose}>Fermer</button>
      </div>
      {children}
    </section>
  </div>;
}
