'use client';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { createStore, updateStore } from '@/lib/services/admin/stores.service';
import { listMerchants } from '@/lib/services/admin/merchants.service';
import type { Store } from '@/lib/types/admin/stores.types';
import type { Merchant } from '@/lib/types/admin/merchants.types';

interface StoreDrawerProps {
  open: boolean;
  onClose: () => void;
  store: Store | null;
  onSaved: () => void;
}

export function StoreDrawer({ open, onClose, store, onSaved }: StoreDrawerProps) {
  const [name, setName] = useState('');
  const [merchantId, setMerchantId] = useState('');
  const [description, setDescription] = useState('');
  const [address, setAddress] = useState('');
  const [city, setCity] = useState('');
  const [logoUrl, setLogoUrl] = useState('');
  const [coverUrl, setCoverUrl] = useState('');
  const [merchants, setMerchants] = useState<Merchant[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    void listMerchants(1, 100).then((data) => setMerchants(data.items)).catch(() => {
      setError('Impossible de charger la liste des marchands.');
    });
  }, []);

  useEffect(() => {
    if (store) {
      setName(store.name);
      setMerchantId(store.merchant_id);
      setDescription(store.description ?? '');
      setAddress(store.address ?? '');
      setCity(store.city ?? '');
      setLogoUrl(store.logo_url ?? '');
      setCoverUrl(store.cover_url ?? '');
    } else {
      setName('');
      setMerchantId('');
      setDescription('');
      setAddress('');
      setCity('');
      setLogoUrl('');
      setCoverUrl('');
    }
    setError(null);
  }, [store, open]);

  const handleSubmit = async () => {
    if (!name.trim()) { setError('Le nom est obligatoire.'); return; }
    if (!store && !merchantId) { setError('Le marchand est obligatoire.'); return; }

    setIsSubmitting(true);
    setError(null);
    try {
      if (store) {
        await updateStore(store.id, {
          name: name.trim(),
          description: description.trim() || undefined,
          address: address.trim() || undefined,
          city: city.trim() || undefined,
          logoUrl: logoUrl.trim() || undefined,
          coverUrl: coverUrl.trim() || undefined,
        });
      } else {
        await createStore({
          name: name.trim(),
          merchantId,
          description: description.trim() || undefined,
          address: address.trim() || undefined,
          city: city.trim() || undefined,
          logoUrl: logoUrl.trim() || undefined,
          coverUrl: coverUrl.trim() || undefined,
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

  const inputClass =
    'w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';

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
        {!store && (
          <div>
            <label className="mb-1 block text-sm font-semibold">Marchand *</label>
            <select value={merchantId} onChange={(e) => setMerchantId(e.target.value)} className={inputClass}>
              <option value="">Choisir un marchand…</option>
              {merchants.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.first_name} {m.last_name} ({m.email})
                </option>
              ))}
            </select>
          </div>
        )}
        <div>
          <label className="mb-1 block text-sm font-semibold">Description</label>
          <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} maxLength={1000} className={inputClass} />
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
      </div>
    </AdminDrawer>
  );
}
