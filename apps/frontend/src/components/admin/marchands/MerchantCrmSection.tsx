'use client';
import { useState, useEffect } from 'react';
import { updateMerchantCrm, addMerchantCrmContact } from '@/lib/services/admin/merchants.service';
import type {
  MerchantCrm,
  MerchantCrmContact,
  MerchantCrmContactChannel,
  MerchantCrmStatus,
} from '@/lib/types/admin/merchants.types';

interface MerchantCrmSectionProps {
  merchantId: string;
  initialCrm: MerchantCrm | null;
  // Notifies the parent so the merchants list reflects CRM changes (badge, filters)
  onCrmChanged?: () => void;
}

const CHANNEL_LABELS: Record<MerchantCrmContactChannel, string> = {
  phone: 'Téléphone',
  whatsapp: 'WhatsApp',
  visit: 'Visite',
  email: 'Email',
  other: 'Autre',
};

function toDatetimeLocalValue(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('fr-TN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}

export function MerchantCrmSection({ merchantId, initialCrm, onCrmChanged }: MerchantCrmSectionProps) {
  const [crm, setCrm] = useState<MerchantCrm | null>(initialCrm);
  const [owner, setOwner] = useState('');
  const [status, setStatus] = useState<MerchantCrmStatus>('prospect');
  const [nextActionAt, setNextActionAt] = useState('');
  const [nextActionNote, setNextActionNote] = useState('');
  const [commercialNote, setCommercialNote] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saveSuccess, setSaveSuccess] = useState(false);

  const [contactChannel, setContactChannel] = useState<MerchantCrmContactChannel>('phone');
  const [contactNote, setContactNote] = useState('');
  const [isAddingContact, setIsAddingContact] = useState(false);
  const [contactError, setContactError] = useState<string | null>(null);

  useEffect(() => {
    setCrm(initialCrm);
    setOwner(initialCrm?.commercial_owner ?? '');
    setStatus(initialCrm?.status ?? 'prospect');
    setNextActionAt(toDatetimeLocalValue(initialCrm?.next_action_at ?? null));
    setNextActionNote(initialCrm?.next_action_note ?? '');
    setCommercialNote(initialCrm?.commercial_note ?? '');
    setSaveError(null);
    setSaveSuccess(false);
    setContactChannel('phone');
    setContactNote('');
    setContactError(null);
  }, [merchantId, initialCrm]);

  const handleSaveCrm = async () => {
    setIsSaving(true);
    setSaveError(null);
    setSaveSuccess(false);
    try {
      const updated = await updateMerchantCrm(merchantId, {
        commercial_owner: owner.trim() || null,
        status,
        next_action_at: nextActionAt ? new Date(nextActionAt).toISOString() : null,
        next_action_note: nextActionNote.trim() || null,
        commercial_note: commercialNote.trim() || null,
      });
      setCrm(updated.crm ?? null);
      setSaveSuccess(true);
      onCrmChanged?.();
    } catch (err) {
      console.error('[marchands] updateMerchantCrm failed', err);
      setSaveError('Impossible d’enregistrer le suivi commercial.');
    } finally {
      setIsSaving(false);
    }
  };

  const handleAddContact = async () => {
    if (!contactNote.trim()) {
      setContactError('La note de contact est obligatoire.');
      return;
    }
    setIsAddingContact(true);
    setContactError(null);
    try {
      const updated = await addMerchantCrmContact(merchantId, {
        channel: contactChannel,
        note: contactNote.trim(),
      });
      setCrm(updated.crm ?? null);
      setContactNote('');
      onCrmChanged?.();
    } catch (err) {
      console.error('[marchands] addMerchantCrmContact failed', err);
      setContactError('Impossible d’ajouter le contact.');
    } finally {
      setIsAddingContact(false);
    }
  };

  const inputClass =
    'w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';
  const contacts: MerchantCrmContact[] = crm?.contacts ?? [];

  return (
    <section className="rounded-md border border-line bg-soft px-4 py-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-sm font-black text-ink">Suivi commercial</h3>
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-bold ${
            crm?.status === 'client' ? 'bg-green-100 text-green-700' : 'bg-line text-muted'
          }`}
        >
          {crm?.status === 'client' ? 'Client' : 'Prospect'}
        </span>
      </div>

      {saveError && (
        <div className="mt-3 rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
          {saveError}
        </div>
      )}
      {saveSuccess && (
        <div className="mt-3 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
          Suivi commercial enregistré.
        </div>
      )}

      <div className="mt-3 grid grid-cols-2 gap-3">
        <div>
          <label htmlFor="crm-owner" className="mb-1 block text-sm font-semibold">
            Responsable commercial
          </label>
          <input
            id="crm-owner"
            type="text"
            value={owner}
            onChange={(e) => setOwner(e.target.value)}
            maxLength={120}
            className={inputClass}
          />
        </div>
        <div>
          <label htmlFor="crm-status" className="mb-1 block text-sm font-semibold">
            Statut
          </label>
          <select
            id="crm-status"
            value={status}
            onChange={(e) => setStatus(e.target.value as MerchantCrmStatus)}
            className={inputClass}
          >
            <option value="prospect">Prospect</option>
            <option value="client">Client</option>
          </select>
        </div>
        <div>
          <label htmlFor="crm-next-action-at" className="mb-1 block text-sm font-semibold">
            Prochaine action
          </label>
          <input
            id="crm-next-action-at"
            type="datetime-local"
            value={nextActionAt}
            onChange={(e) => setNextActionAt(e.target.value)}
            className={inputClass}
          />
        </div>
        <div>
          <label htmlFor="crm-next-action-note" className="mb-1 block text-sm font-semibold">
            Détail action
          </label>
          <input
            id="crm-next-action-note"
            type="text"
            value={nextActionNote}
            onChange={(e) => setNextActionNote(e.target.value)}
            maxLength={255}
            className={inputClass}
          />
        </div>
      </div>
      <div className="mt-3">
        <label htmlFor="crm-note" className="mb-1 block text-sm font-semibold">
          Note commerciale
        </label>
        <textarea
          id="crm-note"
          value={commercialNote}
          onChange={(e) => setCommercialNote(e.target.value)}
          maxLength={5000}
          rows={3}
          className={inputClass}
        />
      </div>
      <div className="mt-3 flex items-center justify-between">
        <span className="text-xs text-muted">
          Dernière relance : {formatDate(crm?.last_contact_at ?? null)}
        </span>
        <button
          type="button"
          onClick={() => { void handleSaveCrm(); }}
          disabled={isSaving}
          className="rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60"
        >
          {isSaving ? 'Enregistrement…' : 'Enregistrer le suivi'}
        </button>
      </div>

      <div className="mt-4 border-t border-line pt-3">
        <h4 className="text-xs font-black uppercase text-muted">Historique de contact</h4>
        {contactError && (
          <div className="mt-2 rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {contactError}
          </div>
        )}
        <div className="mt-2 flex flex-wrap items-end gap-2">
          <div>
            <label htmlFor="crm-contact-channel" className="mb-1 block text-xs font-semibold">
              Canal
            </label>
            <select
              id="crm-contact-channel"
              value={contactChannel}
              onChange={(e) => setContactChannel(e.target.value as MerchantCrmContactChannel)}
              className="rounded-md border border-line px-2 py-1.5 text-sm outline-none focus:border-primary"
            >
              {(Object.keys(CHANNEL_LABELS) as MerchantCrmContactChannel[]).map((channel) => (
                <option key={channel} value={channel}>
                  {CHANNEL_LABELS[channel]}
                </option>
              ))}
            </select>
          </div>
          <div className="min-w-40 flex-1">
            <label htmlFor="crm-contact-note" className="mb-1 block text-xs font-semibold">
              Note de contact
            </label>
            <input
              id="crm-contact-note"
              type="text"
              value={contactNote}
              onChange={(e) => setContactNote(e.target.value)}
              maxLength={2000}
              placeholder="Ex. relance WhatsApp pour les créneaux"
              className={inputClass}
            />
          </div>
          <button
            type="button"
            onClick={() => { void handleAddContact(); }}
            disabled={isAddingContact}
            className="rounded-md border border-primary px-3 py-1.5 text-xs font-semibold text-primary disabled:opacity-60"
          >
            {isAddingContact ? 'Ajout…' : '+ Ajouter'}
          </button>
        </div>
        {contacts.length === 0 ? (
          <p className="mt-3 text-sm text-muted">Aucun contact enregistré.</p>
        ) : (
          <ul className="mt-3 space-y-2">
            {contacts.map((contact) => (
              <li key={contact.id} className="rounded-md border border-line bg-white px-3 py-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="text-xs font-semibold text-ink">
                    {CHANNEL_LABELS[contact.channel] ?? contact.channel}
                  </span>
                  <span className="text-xs text-muted">{formatDate(contact.contacted_at)}</span>
                </div>
                <p className="mt-1 text-sm text-ink">{contact.note}</p>
                <p className="mt-1 text-xs text-muted">par {contact.author_email}</p>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  );
}
