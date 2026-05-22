import { cn } from "@/lib/cn";

/**
 * Stylized QR-code placeholder matching the prototype's CSS look.
 * Swap for a real QR generator (e.g. `qrcode.react`) when the backend
 * exposes the pickup token URL — keep the same wrapper sizing.
 */
export function QrPlaceholder({
  code,
  className,
}: {
  code?: string;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "mx-auto h-[210px] w-[210px] rounded-lg border-[12px] border-white shadow-floating",
        "[background:linear-gradient(90deg,#111_10px,transparent_10px)_0_0/35px_35px,linear-gradient(#111_10px,transparent_10px)_0_0/35px_35px,linear-gradient(90deg,transparent_18px,#111_18px_27px,transparent_27px)_10px_10px/46px_46px,#fff]",
        className,
      )}
      role="img"
      aria-label={code ? `QR code de retrait ${code}` : "QR code de retrait"}
    />
  );
}
