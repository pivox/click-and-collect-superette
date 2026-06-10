'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import QRCode from 'react-qr-code';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import {
  downloadMerchantQrAsset,
  getMerchantQrCode,
  triggerQrDownload,
} from '@/lib/services/merchant-qr.service';
import type { MerchantQrCode } from '@/lib/services/merchant-qr.service';
import { Button } from '@/components/ui/Button';

function buildQrUrl(targetUrl: string): string {
  if (/^https?:\/\//.test(targetUrl)) return targetUrl;
  const path = targetUrl.replace(/^\/api\/stores\/by-qr\//, '/stores/by-qr/');
  if (typeof window === 'undefined') return path;
  const base =
    process.env.NEXT_PUBLIC_APP_URL?.replace(/\/$/, '') ?? window.location.origin;
  return `${base}${path}`;
}

export default function MerchantQrCodePage() {
  const { merchant } = useMerchantAuth();
  const [qrData, setQrData] = useState<MerchantQrCode | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [downloading, setDownloading] = useState<'png' | 'pdf' | null>(null);
  const [error, setError] = useState<string | null>(null);
  const qrRef = useRef<HTMLDivElement>(null);

  const load = useCallback(async () => {
    if (!merchant) return;
    setIsLoading(true);
    setError(null);
    try {
      setQrData(await getMerchantQrCode(merchant.store.id));
    } catch {
      setError('Impossible de charger le QR code de votre supérette.');
    } finally {
      setIsLoading(false);
    }
  }, [merchant]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleDownload = async (format: 'png' | 'pdf') => {
    if (!merchant) return;
    setDownloading(format);
    setError(null);
    try {
      const blob = await downloadMerchantQrAsset(merchant.store.id, format);
      triggerQrDownload(blob, `qr-${qrData?.slug ?? 'superette'}.${format}`);
    } catch {
      setError('Impossible de télécharger le QR code de votre supérette.');
    } finally {
      setDownloading(null);
    }
  };

  const qrUrl = qrData ? buildQrUrl(qrData.target_url) : '';

  return (
    <div>
      <style>{`
        @media print {
          body > * { visibility: hidden; }
          #qr-print-zone, #qr-print-zone * { visibility: visible; }
          #qr-print-zone {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
          }
        }
      `}</style>

      <div className="mb-6">
        <h1 className="text-h1 font-black">Mon QR code</h1>
        <p className="mt-1 text-sm text-muted">
          Imprimez ce QR code et affichez-le à l&apos;entrée de votre supérette.
        </p>
      </div>

      {error && (
        <div role="alert" aria-atomic="true" className="mb-4 flex items-center justify-between rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          <span>{error}</span>
          <button
            type="button"
            onClick={() => void load()}
            className="ml-4 text-sm font-bold underline"
          >
            Réessayer
          </button>
        </div>
      )}

      {isLoading && (
        <div className="rounded-md bg-card p-8 shadow-card text-center text-sm text-muted">
          Chargement du QR code…
        </div>
      )}

      {!isLoading && qrData && (
        <div className="max-w-md space-y-6">
          <div
            id="qr-print-zone"
            ref={qrRef}
            className="flex flex-col items-center gap-4 rounded-md bg-card p-8 shadow-card"
          >
            <div className="rounded-lg border-[16px] border-white bg-white shadow-floating">
              <QRCode
                value={qrUrl}
                size={220}
                bgColor="#ffffff"
                fgColor="#111111"
                level="M"
              />
            </div>
            <p className="text-center text-sm font-bold text-ink">
              {merchant?.store.name}
            </p>
            <p className="break-all text-center text-xs text-muted">{qrUrl}</p>
          </div>

          <div className="flex gap-3">
            <Button
              variant="primary"
              size="md"
              onClick={() => void handleDownload('png')}
              disabled={downloading !== null}
              className="flex-1"
            >
              {downloading === 'png' ? 'Téléchargement…' : 'Télécharger PNG'}
            </Button>
            <Button
              variant="ghost"
              size="md"
              onClick={() => void handleDownload('pdf')}
              disabled={downloading !== null}
              className="flex-1"
            >
              {downloading === 'pdf' ? 'Téléchargement…' : 'Télécharger PDF'}
            </Button>
          </div>

          <div className="rounded-md border border-line bg-soft p-4 text-sm text-muted">
            <strong className="block text-ink">Comment utiliser ce QR code ?</strong>
            <p className="mt-1">
              Imprimez-le et affichez-le à l&apos;entrée de votre supérette. Vos clients
              n&apos;ont qu&apos;à le scanner pour accéder à votre catalogue et préparer
              leur Kadhia.
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
