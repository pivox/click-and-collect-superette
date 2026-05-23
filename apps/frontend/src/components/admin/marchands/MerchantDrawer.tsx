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
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (merchant) {
      setFirstName(merchant.first_name);
      setLastName(merchant.last_name);
      setEmail(merchant.email);
      setPassword('');
    } else {
      setFirstName('');
      setLastName('');
      setEmail('');
      setPassword('');
    }
    setError(null);
  }, [merchant, open]);

  const handleSubmit = async () => {
    if (!firstName.trim()) { setError('Le prénom est obligatoire.'); return; }
    if (!lastName.trim()) { setError('Le nom est obligatoire.'); return; }
    if (!email.trim()) { setError("L'email est obligatoire."); return; }
    if (!merchant && !password.trim()) { setError('Le mot de passe est obligatoire.'); return; }

    setIsSubmitting(true);
    setError(null);
    try {
      if (merchant) {
        await updateMerchant(merchant.id, {
          firstName: firstName.trim(),
          lastName: lastName.trim(),
          email: email.trim(),
        });
      } else {
        await createMerchant({
          firstName: firstName.trim(),
          lastName: lastName.trim(),
          email: email.trim(),
          password: password.trim(),
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
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold">Nom *</label>
            <input
              type="text"
              value={lastName}
              onChange={(e) => setLastName(e.target.value)}
              maxLength={100}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Email *</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            maxLength={200}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        {!merchant && (
          <div>
            <label className="mb-1 block text-sm font-semibold">Mot de passe *</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              maxLength={200}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
        )}
      </div>
    </AdminDrawer>
  );
}
