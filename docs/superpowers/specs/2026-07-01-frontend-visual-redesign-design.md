# Frontend Visual Redesign — Design Spec

**Date:** 2026-07-01
**Status:** Draft — awaiting user review
**Scope:** `it-helpdesk-frontend` (Vue 3 + Vite + TypeScript + Tailwind)

## 1. Context

The IT HelpDesk SPA works well but looks dated: flat white cards, hardcoded
`red-600` accents scattered across files, an unused `primary` blue palette in
`tailwind.config.js`, and system fonts. Dark mode already works via a global
`.dark .<utility>` cascade in `src/assets/main.css`.

This project is a **visual modernization only** — the layout, navigation, and
information architecture stay exactly as they are. We restyle, we do not
rearrange.

### Current relevant facts
- CSS entry: `src/assets/main.css` (`@tailwind base/components/utilities` + a
  `.dark .*` cascade block). This is where the Inter import and
  `@layer components` classes will live.
- Tokens: `tailwind.config.js` (`darkMode: 'class'`, unused `primary` blue).
- Charts: **ApexCharts** (`apexcharts` + `vue3-apexcharts`) — chart colors and
  dark theme must be aligned to the new palette.
- Shell: `src/components/layout/AppLayout.vue` (sidebar + topbar).
- Shared UI so far: `components/ui/` (NotificationBell, ThemeToggle,
  ImageLightbox, AnimatedMapPanel).
- Bilingual EN/中文 via vue-i18n — all restyling must preserve i18n keys.

## 2. Goals & non-goals

**Goals**
- A cohesive, modern "Vibrant & Branded" look across the whole app.
- Centralized design tokens + a small reusable component set so the look is
  consistent and future tweaks live in one place.
- Keep (and adapt) dark mode.
- The reusable components double as a design-system catalog we can later publish
  to Claude Design via `DesignSync`.

**Non-goals (out of scope)**
- No layout / navigation / IA changes.
- No new features or backend changes.
- No logo/brand identity change (SEG red stays the brand).
- No routing or i18n restructuring.

## 3. Locked visual direction

| Decision | Choice |
|---|---|
| Goal | Visual modernization, keep structure |
| Style | Vibrant & Branded |
| Color intensity | Full Gradient (KPI/stat tiles are colored gradients) |
| Shape | Pillowy — 18px card radius, soft glowing shadows |
| Scope | Whole site |
| Dark mode | Keep & adapt |
| Font | **Inter** |

## 4. Design tokens

### 4.1 Color
- **Brand:** SEG red `#dc2626` (hover `#b91c1c`). Rename the unused `primary`
  blue in `tailwind.config.js` to a real `brand` scale mapped to red so
  `red-600` usages have a semantic home. Existing `red-600` utilities remain
  valid (they equal the brand color).
- **Semantic status → gradient** (used by StatCard and StatusBadge):
  | Meaning | Gradient (light) | Solid/tint (badges) |
  |---|---|---|
  | Brand / **Open** | `#dc2626 → #f97316` | red |
  | **In Progress** | `#2563eb → #60a5fa` | blue |
  | **Pending** | `#d97706 → #fbbf24` | amber |
  | **Resolved** | `#16a34a → #4ade80` | green |
  | **Closed** | slate `#64748b → #94a3b8` | slate |
- **Priority mapping:** low→slate, medium→blue, high→amber, urgent→red.

### 4.2 Radius (extend `borderRadius` in tailwind config)
- `pw-card: 18px`, `pw-btn: 14px`, `pw-input: 12px`, chips full pill
  (`rounded-full`).

### 4.3 Shadows (extend `boxShadow`)
- `soft: 0 4px 12px rgba(15,23,42,.08)` — white cards.
- `soft-lg: 0 10px 30px rgba(15,23,42,.10)` — gradient stat cards (neutral, to
  avoid rainbow glows when several colored tiles sit in a row).
- `glow: 0 12px 26px rgba(220,38,38,.30)` — brand/hero elements only.
- Dark mode: shadows are nearly invisible on dark; substitute a subtle border /
  ring instead (extend the existing `.dark .shadow-*` cascade).

### 4.4 Typography
- Add **Inter** via `@fontsource/inter` (npm, self-hosted — no external request,
  no FOUT surprises). Import weights 400/500/600/700/800 in `main.ts` or
  `main.css`.
- `tailwind.config.js` → `fontFamily.sans = ['Inter', <existing system stack>]`
  so Inter applies globally with a graceful fallback.
- Weight usage: 700–800 for numbers & headings, 500–600 for labels/buttons,
  400 for body.

### 4.5 Spacing
- More generous card padding (`p-5`/`p-6`), consistent `gap-4`/`gap-6` grids.

## 5. Component system

Built in `src/components/ui/`. These are the reusable primitives; everything
else uses token classes. Each is dark-mode aware.

1. **`StatCard.vue`** — gradient KPI tile.
   Props: `variant` (`open|progress|pending|resolved|closed|brand`), `value`,
   `label`, `icon?`, `trend?`. White text on gradient, 800-weight number,
   `rounded-pw-card`, `shadow-soft-lg`.
2. **`BaseButton.vue`** — Props: `variant` (`primary|secondary|ghost`),
   `size?`, `as?` (button/router-link). Primary = **solid** brand red
   (`#dc2626`, hover `#b91c1c`) — gradients reserved for stat tiles so buttons
   stay legible. `rounded-pw-btn`.
3. **`StatusBadge.vue`** — Props: `kind` (`status|priority`), `value`. Maps to
   the tinted pill colors above; localized label via i18n. Replaces the ad-hoc
   `bg-*-50` badges currently inline in ticket views.
4. **`BaseCard.vue`** — pillowy white panel. Slots: default + optional `header`.
   `bg-white dark:bg-slate-800`, `rounded-pw-card`, `shadow-soft`, subtle
   border.

Recurring non-component patterns (inputs, tables, filter chips, section
headings) get semantic classes in an `@layer components` block in `main.css`:
`.pw-input`, `.pw-table`, `.pw-chip`, `.pw-section-title`, etc.

## 6. Dark mode strategy

Extend the existing `.dark .*` cascade approach in `main.css` (don't fight it):
- Canvas `#0f172a`, card surfaces `#1e293b` (already mapped for `.bg-white`).
- Gradient tiles: add darker/desaturated `dark:` gradient stops; keep white text.
- Replace shadows with subtle borders/rings in dark.
- Align ApexCharts to a dark theme (grid, labels, tooltip) when `.dark` is
  active; feed the new palette as the chart color array in both themes.

## 7. Page-by-page application

Order chosen so shared foundations land first and each page inherits them.

1. **Tokens & foundation** — `tailwind.config.js` (colors, radius, shadow,
   fontFamily), `main.css` (Inter import, `@layer components`), install
   `@fontsource/inter`.
2. **Shell** (`AppLayout.vue`) — active nav pill refined, spacing/typography via
   Inter, topbar polish. Structure unchanged.
3. **Core components** — StatCard, BaseButton, StatusBadge, BaseCard.
4. **Dashboard** — KPI row → `StatCard`s; charts wrapped in `BaseCard`; Apex
   charts recolored; SLA & IT-workload sections restyled.
5. **Tickets** — list (filter pill chips, `BaseCard` container, `StatusBadge`
   for status + priority, styled pagination); detail (header/description cards,
   comment thread bubbles, history timeline, internal-note styling, actions via
   `BaseButton`); **My Tasks** reuses the list treatment.
6. **Assets** — list, detail, and `AssetForm` on `BaseCard` + `.pw-input`.
7. **Admin** — users, departments, SLA, approval-levels, inventory
   (categories/locations/manufacturers): consistent `.pw-table` + form styling.
8. **Login / auth** — modern gradient-accented login with SEG logo + Inter.
9. **Dark-mode pass** — sweep every page in dark, fix contrast/gradient issues.

## 8. Verification

- Run the app (run-it-helpdesk skill) and screenshot **every** page in **light
  and dark**; compare against this spec.
- `npx vue-tsc --noEmit` passes.
- Confirm i18n keys intact (EN/中文 toggle still renders both).
- Confirm no layout/nav regressions (structure unchanged).

## 9. Risks & mitigations
- **Large sweep, many files** → tokens + components first so pages inherit a
  consistent look; sweep is then mostly mechanical.
- **Gradient legibility in dark mode** → dedicated dark gradient stops + a dark
  pass with screenshots.
- **Scattered hardcoded `red-600`** → mostly fine (equals brand); migrate to
  `brand`/component classes opportunistically, not as a blocking task.
- **ApexCharts theming** → recolor via chart options; verify tooltips/legends in
  both themes.

## 10. Open questions
- None blocking. (Inter confirmed; solid-red buttons vs gradient buttons decided
  in favor of solid for legibility — flag if you want gradient CTAs.)
