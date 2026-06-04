/**
 * Resolve a media path returned by the API into a usable URL.
 *
 * The backend returns image variant paths that are relative by default
 * (e.g. "/uploads/products/{id}/400.webp"); they must be resolved against the
 * API origin. Absolute URLs (CDN / object storage) are returned untouched.
 */
const API_ORIGIN =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export function mediaUrl(path: string | null | undefined): string | null {
  if (!path) {
    return null;
  }
  if (/^https?:\/\//i.test(path) || path.startsWith("data:")) {
    return path;
  }
  return `${API_ORIGIN.replace(/\/$/, "")}/${path.replace(/^\//, "")}`;
}
