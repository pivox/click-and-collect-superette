import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Kadhia · Click & Collect Supérette",
  description:
    "Prépare ta Kadhia depuis ta supérette de quartier — scan, catalogue, créneau de retrait et QR code.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fr">
      <body className="antialiased">{children}</body>
    </html>
  );
}
