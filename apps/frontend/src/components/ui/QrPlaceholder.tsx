import QRCode from "react-qr-code";
import { cn } from "@/lib/cn";

export function QrPlaceholder({
  code,
  className,
}: {
  code: string;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "mx-auto flex h-[282px] w-[282px] items-center justify-center rounded-lg border-[16px] border-white bg-white shadow-floating",
        className,
      )}
      role="img"
      aria-label={`QR code de retrait ${code}`}
    >
      <QRCode
        value={code}
        size={250}
        bgColor="#ffffff"
        fgColor="#111111"
        level="M"
      />
    </div>
  );
}
