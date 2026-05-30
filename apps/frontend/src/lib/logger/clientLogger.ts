export type LogLevel = 'debug' | 'info' | 'warning' | 'error';

export type UserRole = 'client' | 'merchant' | 'admin' | 'anonymous';

export interface LogContext {
  route?: string;
  userRole?: UserRole;
  userId?: string;
  merchantId?: string;
  orderId?: string | null;
  requestId?: string;
  statusCode?: number;
  durationMs?: number;
  [key: string]: unknown;
}

interface LogPayload {
  level: LogLevel;
  event: string;
  message: string;
  context: LogContext;
  appVersion: string;
  environment: string;
  url: string;
  createdAt: string;
}

const FORBIDDEN_KEYS = new Set([
  'password',
  'token',
  'jwt',
  'secret',
  'otp',
  'authorization',
  'refreshtoken',
  'refresh_token',
  'apikey',
  'api_key',
]);

const MAX_CONTEXT_KEYS = 20;
const MAX_PAYLOAD_BYTES = 8192;

function sanitizeContext(context: LogContext): LogContext {
  const sanitized: LogContext = {};
  let count = 0;

  for (const [key, value] of Object.entries(context)) {
    if (count >= MAX_CONTEXT_KEYS) break;
    if (FORBIDDEN_KEYS.has(key.toLowerCase())) continue;
    sanitized[key] = value;
    count++;
  }

  return sanitized;
}

function getEnvironment(): string {
  return process.env.NEXT_PUBLIC_ENVIRONMENT ?? process.env.NODE_ENV ?? 'development';
}

function isProductionMode(): boolean {
  return getEnvironment() === 'production';
}

function getApiUrl(): string {
  return process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
}

function sendToBackend(payload: LogPayload): void {
  const body = JSON.stringify(payload);

  // Drop oversized payloads to prevent abuse
  if (new TextEncoder().encode(body).length > MAX_PAYLOAD_BYTES) {
    return;
  }

  // Fire-and-forget — keepalive ensures delivery even on page unload
  void fetch(`${getApiUrl()}/api/client-logs`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body,
    keepalive: true,
  }).catch(() => {
    // Logging must never throw or cascade errors
  });
}

export function clientLog(
  level: LogLevel,
  event: string,
  message: string,
  context: LogContext = {},
): void {
  const isProd = isProductionMode();
  const env = getEnvironment();

  if (!isProd) {
    const logFn =
      level === 'error'
        ? console.error
        : level === 'warning'
          ? console.warn
          : console.info;
    logFn(`[${event}]`, message, context);
    return;
  }

  if (level === 'debug' || level === 'info') {
    console.info(`[${event}]`, message, context);
    return;
  }

  const payload: LogPayload = {
    level,
    event,
    message,
    context: sanitizeContext(context),
    appVersion: process.env.NEXT_PUBLIC_APP_VERSION ?? '0.1.0',
    environment: env,
    url: typeof window !== 'undefined' ? window.location.pathname : '',
    createdAt: new Date().toISOString(),
  };

  sendToBackend(payload);
}
