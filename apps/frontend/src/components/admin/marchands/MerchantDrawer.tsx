'use client';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { createMerchant, updateMerchant } from '@/lib/services/admin/merchants.service';
import type { Merchant } from '@/lib/types/admin/merchants.types';

interface MerchantDrawerProps {
  open: boolean;
  onClose: () => void;
  merchant: Merchant | null;
  onSaved: () => void;
}

export function MerchantDrawer({ open, onClose, merchant, onSaved }: MerchantDrawerProps) {
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (merchant) {
      setFirstName(merchant.first_name ?? '');
      setLastName(merchant.last_name ?? '');
      setEmail(merchant.email);
      setPhone(merchant.phone ?? '');
    } else {
      setFirstName('');
      setLastName('');
      setEmail('');
      setPhone('');
    }
    setError(null);
  }, [merchant, open]);

  const handleSubmit = async () => {
    if (!firstName.trim()) { setError('Le prénom est obligatoire.'); return; }
    if (!lastName.trim()) { setError('Le nom est obligatoire.'); return; }
    if (!merchant && !email.trim()) { setError("L'email est obligatoire."); return; }

    setIsSubmitting(true);
    setError(null);
    try {
      if (merchant) {
        // email not updatable (not in AdminUpdateMerchantInput)
        await updateMerchant(merchant.id, {
          first_name: firstName.trim(),
          last_name: lastName.trim(),
          phone: phone.trim() || undefined,
        });
      } else {
        // @SerializedName on backend DTO → snake_case keys in payload
        await createMerchant({
          email: email.trim(),
          first_name: firstName.trim(),
          last_name: lastName.trim(),
          phone: phone.trim() || undefined,
        });
      }
      onSaved();
    } catch (e) {
      setError(
        axios.isAxiosError(e) && e.response?.status === 409
          ? 'Un compte avec cet email existe déjà.'
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
      title={merchant ? 'Modifier le marchand' : 'Nouveau marchand'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-semibold">Prénom *</label>
            <input
              type="text"
              value={firstName}
              onChange={(e) => setFirstName(e.target.value)}
              maxLength={100}
              className={inputClass}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold">Nom *</label>
            <input
              type="text"
              value={lastName}
              onChange={(e) => setLastName(e.target.value)}
              maxLength={100}
              className={inputClass}
            />
          </div>
        </div>
        {!merchant && (
          <div>
            <label className="mb-1 block text-sm font-semibold">Email *</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              maxLength={200}
              className={inputClass}
            />
          </div>
        )}
        {merchant && (
          <div>
            <label className="mb-1 block text-sm font-semibold text-muted">Email</label>
            <p className="rounded-md border border-line bg-soft px-3 py-2 text-sm text-muted">
              {merchant.email}
            </p>
          </div>
        )}
        <div>
          <label className="mb-1 block text-sm font-semibold">Téléphone</label>
          <input
            type="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            maxLength={30}
            placeholder="+216 XX XXX XXX"
            className={inputClass}
          />
        </div>
      </div>
    </AdminDrawer>
  );
}
