'use client';
import { useState, useEffect } from 'react';
import axios from 'axios';
import QRCode from 'react-qr-code';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { createStore, updateStore, getStoreQrCode, regenerateStoreQrCode } from '@/lib/services/admin/stores.service';
import { listMerchants } from '@/lib/services/admin/merchants.service';
import type { Store, StoreQrCode } from '@/lib/types/admin/stores.types';
import type { Merchant } from '@/lib/types/admin/merchants.types';

interface StoreDrawerProps {
  open: boolean;
  onClose: () => void;
  store: Store | null;
  onSaved: () => void;
}

function resolveAppBaseUrl(): string {
  const configuredUrl = process.env.NEXT_PUBLIC_APP_URL?.replace(/\/$/, '');
  if (configuredUrl) return configuredUrl;
  if (typeof window !== 'undefined') return window.location.origin;
  return '';
}

function toFrontendQrPath(targetUrl: string): string {
  return targetUrl.replace(/^\/api\/stores\/by-qr\//, '/stores/by-qr/');
}

export function StoreDrawer({ open, onClose, store, onSaved }: StoreDrawerProps) {
  const [name, setName] = useState('');
  const [ownerId, setOwnerId] = useState('');
  const [address, setAddress] = useState('');
  const [city, setCity] = useState('');
  const [phone, setPhone] = useState('');
  const [logoUrl, setLogoUrl] = useState('');
  const [coverUrl, setCoverUrl] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [merchants, setMerchants] = useState<Merchant[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [qrData, setQrData] = useState<StoreQrCode | null>(null);
  const [isLoadingQr, setIsLoadingQr] = useState(false);
  const [qrError, setQrError] = useState<string | null>(null);
  const [isRegenerateOpen, setIsRegenerateOpen] = useState(false);
  const [isRegenerating, setIsRegenerating] = useState(false);

  useEffect(() => {
    void listMerchants(1, 100).then((data) => setMerchants(data.items)).catch(() => {
      setError('Impossible de charger la liste des marchands.');
    });
  }, []);

  useEffect(() => {
    if (store) {
      setName(store.name);
      setOwnerId(store.owner?.id ?? '');
      setAddress(store.address ?? '');
      setCity(store.city ?? '');
      setPhone(store.phone ?? '');
      setLogoUrl(store.logo_url ?? '');
      setCoverUrl(store.cover_url ?? '');
      setIsActive(store.is_active);
    } else {
      setName('');
      setOwnerId('');
      setAddress('');
      setCity('');
      setPhone('');
      setLogoUrl('');
      setCoverUrl('');
      setIsActive(true);
    }
    setError(null);
  }, [store, open]);

  const storeId = store?.id;
  useEffect(() => {
    if (!open || !storeId) {
      setQrData(null);
      setQrError(null);
      return;
    }
    let cancelled = false;
    setIsLoadingQr(true);
    setQrError(null);
    void getStoreQrCode(storeId)
      .then((data) => { if (!cancelled) setQrData(data); })
      .catch(() => { if (!cancelled) setQrError('Impossible de charger le QR code.'); })
      .finally(() => { if (!cancelled) setIsLoadingQr(false); });
    return () => { cancelled = true; };
  }, [open, storeId]);

  const handleSubmit = async () => {
    if (!name.trim()) { setError('Le nom est obligatoire.'); return; }
    if (!store && !ownerId) { setError('Le marchand est obligatoire.'); return; }

    setIsSubmitting(true);
    setError(null);
    try {
      if (store) {
        await updateStore(store.id, {
          name: name.trim(),
          address: address.trim() || undefined,
          city: city.trim() || undefined,
          phone: phone.trim() || undefined,
          ownerId: ownerId || undefined,
          // send null to clear existing URL, undefined to leave unchanged
          logoUrl: logoUrl.trim() !== '' ? logoUrl.trim() : null,
          coverUrl: coverUrl.trim() !== '' ? coverUrl.trim() : null,
          isActive,
        });
      } else {
        // no logoUrl/coverUrl in AdminStoreCreateInput
        await createStore({
          name: name.trim(),
          ownerId,
          address: address.trim() || undefined,
          city: city.trim() || undefined,
          phone: phone.trim() || undefined,
        });
      }
      onSaved();
    } catch (e) {
      setError(
        axios.isAxiosError(e) && e.response?.status === 409
          ? 'Une supérette avec ce nom existe déjà pour ce marchand.'
          : 'Une erreur est survenue. Réessayez.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleRegenerateQr = async () => {
    if (!store) return;
    setIsRegenerating(true);
    setQrError(null);
    try {
      const newQr = await regenerateStoreQrCode(store.id);
      setQrData(newQr);
      setIsRegenerateOpen(false);
    } catch {
      setQrError('Impossible de régénérer le QR code.');
    } finally {
      setIsRegenerating(false);
    }
  };

  const inputClass =
    'w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';

  const appBaseUrl = resolveAppBaseUrl();
  const sharePath = qrData ? toFrontendQrPath(qrData.target_url) : '';
  const shareUrl = qrData ? `${appBaseUrl}${sharePath}` : '';

  return (
    <AdminDrawer
      open={open}
      onClose={onClose}
      title={store ? 'Modifier la supérette' : 'Nouvelle supérette'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
      size="lg"
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div>
          <label className="mb-1 block text-sm font-semibold">Nom *</label>
          <input type="text" value={name} onChange={(e) => setName(e.target.value)} maxLength={255} className={inputClass} />
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Marchand {!store && '*'}</label>
          <select value={ownerId} onChange={(e) => setOwnerId(e.target.value)} className={inputClass}>
            <option value="">Choisir un marchand…</option>
            {merchants.map((m) => (
              <option key={m.id} value={m.id}>
                {m.first_name ?? ''} {m.last_name ?? ''} ({m.email})
              </option>
            ))}
          </select>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-semibold">Adresse</label>
            <input type="text" value={address} onChange={(e) => setAddress(e.target.value)} maxLength={255} className={inputClass} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold">Ville</label>
            <input type="text" value={city} onChange={(e) => setCity(e.target.value)} maxLength={100} className={inputClass} />
          </div>
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Téléphone</label>
          <input type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} maxLength={30} placeholder="+216 XX XXX XXX" className={inputClass} />
        </div>
        {store && (
          <>
            <div>
              <label className="mb-1 block text-sm font-semibold">
                URL logo <span className="font-normal text-muted">(max 2048 car.)</span>
              </label>
              <input type="url" value={logoUrl} onChange={(e) => setLogoUrl(e.target.value)} maxLength={2048} placeholder="https://…" className={inputClass} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-semibold">
                URL cover <span className="font-normal text-muted">(max 2048 car.)</span>
              </label>
              <input type="url" value={coverUrl} onChange={(e) => setCoverUrl(e.target.value)} maxLength={2048} placeholder="https://…" className={inputClass} />
            </div>
            <div className="flex items-center gap-3">
              <input
                type="checkbox"
                id="store-is-active"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                disabled={!!store.archived_at}
                className="h-4 w-4 rounded border-line accent-primary"
              />
              <label htmlFor="store-is-active" className="text-sm font-semibold">
                Supérette active
                {store.archived_at && (
                  <span className="ml-2 font-normal text-muted">(archivée — non modifiable)</span>
                )}
              </label>
            </div>
          </>
        )}
      </div>

      {store && (
        <div className="mt-6 border-t border-line pt-5">
          <h3 className="mb-3 text-sm font-semibold text-ink">QR code de la supérette</h3>

          {isLoadingQr && (
            <div className="flex h-24 items-center justify-center text-sm text-muted">
              Chargement…
            </div>
          )}

          {qrError && (
            <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
              {qrError}
            </div>
          )}

          {!isLoadingQr && qrData && (
            <div className="flex flex-col items-center gap-4">
              <div className="rounded-lg border border-line bg-white p-3">
                <QRCode value={shareUrl || qrData.qr_code_token} size={180} />
              </div>

              <div className="w-full space-y-2">
                <div>
                  <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">
                    Lien de partage
                  </span>
                  <div className="flex items-center gap-2">
                    <span className="flex-1 truncate rounded bg-gray-50 px-2 py-1 text-xs text-ink">
                      {shareUrl || sharePath}
                    </span>
                    <button
                      type="button"
                      onClick={() => void navigator.clipboard.writeText(shareUrl || sharePath)}
                      className="shrink-0 rounded px-2 py-1 text-xs text-muted hover:text-ink"
                    >
                      Copier
                    </button>
                  </div>
                </div>

                <div>
                  <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">
                    Token
                  </span>
                  <div className="flex items-center gap-2">
                    <span className="flex-1 truncate rounded bg-gray-50 px-2 py-1 font-mono text-xs text-ink">
                      {qrData.qr_code_token}
                    </span>
                    <button
                      type="button"
                      onClick={() => void navigator.clipboard.writeText(qrData.qr_code_token)}
                      className="shrink-0 rounded px-2 py-1 text-xs text-muted hover:text-ink"
                    >
                      Copier
                    </button>
                  </div>
                </div>
              </div>

              <button
                type="button"
                onClick={() => setIsRegenerateOpen(true)}
                className="text-sm text-muted underline hover:text-danger"
              >
                Régénérer le QR
              </button>
            </div>
          )}
        </div>
      )}

      <AdminConfirmDialog
        open={isRegenerateOpen}
        onClose={() => setIsRegenerateOpen(false)}
        onConfirm={() => void handleRegenerateQr()}
        title="Régénérer le QR ?"
        message="L'ancien QR imprimé ne fonctionnera plus. Cette action est irréversible."
        confirmLabel={isRegenerating ? 'Régénération…' : 'Régénérer'}
        confirmDisabled={isRegenerating}
        variant="warning"
      />
    </AdminDrawer>
  );
}
