import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./src/pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        // Surface
        bg: "var(--bg)",
        card: "var(--card)",
        ink: "var(--ink)",
        muted: "var(--muted)",
        line: "var(--line)",
        soft: "var(--soft)",

        // Brand
        primary: {
          DEFAULT: "var(--primary)",
          dark: "var(--primary-dark)",
        },
        secondary: "var(--secondary)",
        danger: "var(--danger)",

        // Status (order statuses — foreground + background)
        status: {
          wait: "var(--status-wait)",
          "wait-bg": "var(--status-wait-bg)",
          prep: "var(--status-prep)",
          "prep-bg": "var(--status-prep-bg)",
          ready: "var(--status-ready)",
          "ready-bg": "var(--status-ready-bg)",
          cancel: "var(--status-cancel)",
          "cancel-bg": "var(--status-cancel-bg)",
        },

        // Legacy tokens kept for parity with the bare Next template
        background: "var(--bg)",
        foreground: "var(--ink)",
      },
      borderRadius: {
        xs: "8px",
        sm: "12px",
        md: "16px",
        lg: "22px",
        xl: "28px",
        "2xl": "36px",
      },
      boxShadow: {
        soft: "0 12px 26px rgba(18, 30, 20, .07)",
        card: "0 10px 22px rgba(18, 30, 20, .06)",
        floating: "0 16px 35px rgba(20, 35, 24, .11)",
        cta: "0 13px 24px rgba(31, 122, 77, .25)",
        cta_lg: "0 18px 42px rgba(20, 35, 24, .10)",
      },
      fontFamily: {
        sans: [
          "Inter",
          "ui-sans-serif",
          "system-ui",
          "-apple-system",
          "BlinkMacSystemFont",
          "Segoe UI",
          "sans-serif",
        ],
      },
      fontSize: {
        // Slightly tightened scale to match the prototype's compact mobile UI
        "display-lg": ["46px", { lineHeight: "1.02", letterSpacing: "-1.8px" }],
        "display": ["34px", { lineHeight: "1.05", letterSpacing: "-1.2px" }],
        "h1": ["29px", { lineHeight: "1.06", letterSpacing: "-1px" }],
        "h2": ["24px", { lineHeight: "1.15" }],
        "h3": ["18px", { lineHeight: "1.25" }],
      },
      backgroundImage: {
        "hero-emerald":
          "linear-gradient(145deg, var(--primary), var(--primary-dark))",
        "soft-radial":
          "radial-gradient(circle at top left, rgba(31, 122, 77, .16), transparent 35%), linear-gradient(135deg, #fbfbf7, var(--bg))",
        "product-tile":
          "linear-gradient(145deg, #e7f3e4, #fff7da)",
      },
    },
  },
  plugins: [],
};
export default config;
