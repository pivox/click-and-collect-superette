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
        stack: event.error instanceof Error ? event.error.stack : undefined,
      });
    };

    const handleUnhandledRejection = (event: PromiseRejectionEvent) => {
      const reason = event.reason;
      const message =
        reason instanceof Error
          ? reason.message
          : String(reason ?? 'Unhandled promise rejection');
      clientLog('error', 'front_unhandled_promise_rejection', message, {
        errorName: reason instanceof Error ? reason.name : undefined,
        stack: reason instanceof Error ? reason.stack : undefined,
      });
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
