'use client';

import { useEffect } from 'react';

import { clientLog } from '@/lib/logger/clientLogger';

export default function GlobalErrorCapture() {
  useEffect(() => {
    const handleError = (event: ErrorEvent) => {
      clientLog('error', 'front_runtime_error', event.message || 'Unknown JavaScript error', {
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
      });
    };

    const handleUnhandledRejection = (event: PromiseRejectionEvent) => {
      const message =
        event.reason instanceof Error
          ? event.reason.message
          : String(event.reason ?? 'Unhandled promise rejection');
      clientLog('error', 'front_unhandled_promise_rejection', message, {});
    };

    window.addEventListener('error', handleError);
    window.addEventListener('unhandledrejection', handleUnhandledRejection);

    return () => {
      window.removeEventListener('error', handleError);
      window.removeEventListener('unhandledrejection', handleUnhandledRejection);
    };
  }, []);

  return null;
}
