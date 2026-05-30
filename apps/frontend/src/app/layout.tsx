import type { Metadata } from "next";
import "./globals.css";
import GlobalErrorCapture from "./GlobalErrorCapture";

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
      <body className="antialiased">
        <GlobalErrorCapture />
        {children}
      </body>
    </html>
  );
}
