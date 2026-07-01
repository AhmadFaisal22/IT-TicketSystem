/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // SEG brand — red is the hero color across the app.
        brand: {
          50: '#fef2f2',
          100: '#fee2e2',
          200: '#fecaca',
          300: '#fca5a5',
          400: '#f87171',
          500: '#ef4444',
          600: '#dc2626', // DEFAULT brand red
          700: '#b91c1c', // hover
          800: '#991b1b',
          900: '#7f1d1d',
          DEFAULT: '#dc2626',
        },
        // Kept for backward-compat with any existing `primary-*` usages.
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          900: '#1e3a8a',
        },
      },
      fontFamily: {
        sans: [
          'Inter',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'Helvetica',
          'Arial',
          'sans-serif',
        ],
      },
      borderRadius: {
        card: '18px',
        btn: '14px',
        input: '12px',
      },
      boxShadow: {
        soft: '0 4px 12px rgba(15,23,42,0.08)',
        'soft-lg': '0 10px 30px rgba(15,23,42,0.10)',
        glow: '0 12px 26px rgba(220,38,38,0.30)',
      },
      backgroundImage: {
        // Status/semantic gradients used by StatCard and hero elements.
        'grad-open': 'linear-gradient(135deg,#dc2626,#f97316)',
        'grad-brand': 'linear-gradient(135deg,#dc2626,#f97316)',
        'grad-progress': 'linear-gradient(135deg,#2563eb,#60a5fa)',
        'grad-pending': 'linear-gradient(135deg,#d97706,#fbbf24)',
        'grad-resolved': 'linear-gradient(135deg,#16a34a,#4ade80)',
        'grad-closed': 'linear-gradient(135deg,#64748b,#94a3b8)',
      },
    },
  },
  plugins: [],
}
