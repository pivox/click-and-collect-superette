import type { Metadata } from "next";
import "./globals.css";
import GlobalErrorCapture from "./GlobalErrorCapture";
import { defaultViewport } from "@/lib/config/viewport";

export const metadata: Metadata = {
  title: "Kadhia · Click & Collect Supérette",
  description:
    "Prépare ta Kadhia depuis ta supérette de quartier — scan, catalogue, créneau de retrait et QR code.",
  manifest: "/manifest.webmanifest",
  appleWebApp: {
    capable: true,
    statusBarStyle: "default",
    title: "Kadhia",
  },
  icons: [
    { rel: "icon", url: "/icons/icon-192.png", sizes: "192x192", type: "image/png" },
    { rel: "icon", url: "/icons/icon-512.png", sizes: "512x512", type: "image/png" },
    { rel: "apple-touch-icon", url: "/icons/apple-touch-icon.png", sizes: "180x180" },
  ],
  keywords: ["kadhia", "click and collect", "supérette", "tunisia"],
  formatDetection: {
    telephone: false,
  },
};

export const viewport = defaultViewport;

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
