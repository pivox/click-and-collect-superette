import type { Metadata, Viewport } from "next";
import "./globals.css";
import GlobalErrorCapture from "./GlobalErrorCapture";

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
  icons: {
    icon: "/icons/icon-192.png",
    apple: "/icons/apple-touch-icon.png",
    other: [
      {
        rel: "icon",
        url: "/icons/icon-512.png",
        sizes: "512x512",
        type: "image/png",
      },
      {
        rel: "icon",
        url: "/icons/icon-maskable-512.png",
        sizes: "512x512",
        type: "image/png",
        media: "(prefers-color-scheme: dark)",
      },
    ],
  },
  keywords: ["kadhia", "click and collect", "supérette", "tunisia"],
  formatDetection: {
    telephone: false,
  },
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  maximumScale: 5,
  userScalable: true,
  themeColor: "#1f7a4d",
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
