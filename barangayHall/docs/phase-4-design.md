# Phase 4 — Design System & Page Designs

> **Role speaking: FRONTEND DESIGNER**

Goal: a calm, trustworthy health-center look that BHWs (many of them older, some using tablets in the
field) can read and operate easily, built on shadcn/ui with as few edits to `components/ui` as possible.

**See it live:** log in locally and open **`/dev/components`** — every component below, with sample
data, in light and dark mode (Settings → Appearance). The route only exists when `APP_ENV=local`.

---

## 1. Design principles

1. **Readable first.** 17 px base size, a legibility font, high contrast, plain words ("Register resident", not "Create entity").
2. **One primary action per screen**, top-right in the page header; destructive actions are red and always confirmed.
3. **Never color alone.** Every badge and vital-sign warning has a text label ("High", "Overdue", "Senior").
4. **Privacy by design.** Sensitive values are masked by default, locked fields explain _why_, health tabs load separately.
5. **Fast on a modest PC.** Server-side tables, deferred heavy props, skeletons instead of spinners, no CDN (offline LAN).

## 2. Theme (shadcn CSS variables — `resources/css/app.css`)

### 2.1 Palette

| Token                                      | Light                            | Dark                    | Use                                                                       |
| ------------------------------------------ | -------------------------------- | ----------------------- | ------------------------------------------------------------------------- |
| `--primary`                                | deep teal `oklch(0.47 0.09 187)` | `oklch(0.75 0.11 182)`  | Main buttons, active nav, links, focus ring                               |
| `--secondary` / `--accent`                 | pale teal                        | dark teal               | Secondary buttons, hover rows, menu highlight                             |
| `--background`                             | off-white with a hint of teal    | deep slate-blue         | Page                                                                      |
| `--card` / `--popover`                     | white                            | slate                   | Cards, tables, dialogs                                                    |
| `--muted-foreground`                       | slate `oklch(0.47 0.025 240)`    | `oklch(0.75 0.02 220)`  | Hints, secondary text                                                     |
| `--destructive`                            | red `oklch(0.53 0.2 27)`         | `oklch(0.66 0.19 25)`   | Archive, delete, errors                                                   |
| `--success` (+`-soft`, `-soft-foreground`) | green                            | green                   | Given vaccines, Pregnant flag, Active                                     |
| `--warning` (+…)                           | amber                            | amber                   | Due vaccines, 4Ps, Moved                                                  |
| `--info` (+…)                              | blue                             | blue                    | Seniors, informational alerts                                             |
| `--danger-soft` (+…)                       | pale red                         | dark red                | Overdue, high BP, fever chips                                             |
| `--input`                                  | `oklch(0.62 0.025 225)`          | `oklch(0.55 0.025 220)` | **Darker than shadcn default** so input borders are visible (WCAG 1.4.11) |
| `--chart-1…5`                              | teal, blue, amber, rose, violet  | brighter variants       | Charts (Male = chart-2, Female = chart-4)                                 |
| `--radius`                                 | `0.75rem`                        |                         | Softer, friendlier corners                                                |

New Tailwind utilities from `@theme`: `bg-success`, `bg-success-soft text-success-soft-foreground`, `bg-warning-soft`, `bg-info-soft`, `bg-danger-soft`, etc.

### 2.2 Contrast — verified (WCAG 2.1 AA)

Computed from the OKLCH values (script converts to sRGB and applies the WCAG formula); **all 60 pairs pass in both modes**:

| Pair                                            | Light                 | Dark                  | Needed       |
| ----------------------------------------------- | --------------------- | --------------------- | ------------ |
| Body text on background                         | 16.6:1                | 17.0:1                | 4.5          |
| Hint text (muted) on card                       | 6.8:1                 | 8.0:1                 | 4.5          |
| Primary button text                             | 6.3:1                 | 8.5:1                 | 4.5          |
| Primary links on card                           | 6.4:1                 | 8.3:1                 | 4.5          |
| Destructive text on card                        | 5.9:1                 | 5.2:1                 | 4.5          |
| Soft badges (success / warning / info / danger) | 7.1 / 9.1 / 6.4 / 6.1 | 8.3 / 9.5 / 8.1 / 7.6 | 4.5          |
| Input border on card                            | 3.6:1                 | 3.7:1                 | 3 (non-text) |
| Focus ring on card                              | 4.6:1                 | 7.0:1                 | 3 (non-text) |

### 2.3 Typography

- **Atkinson Hyperlegible** (Braille Institute) — designed for low vision; clearly distinguishes `1 l I` and `0 O`, which matters when reading resident IDs like `KII-2026-00010`. Self-hosted at build time by the Laravel fonts plugin (no internet needed at runtime).
- **Base size 17 px** (`html { font-size: 106.25% }`) — every rem-based shadcn size scales up ~6%.
- Tabular numbers on (`tnum`) so figures line up in tables and stat cards.
- Scale: page title `text-2xl bold` · card title `text-lg` · body `text-base` · table/meta `text-sm` · captions `text-xs` (only for secondary info).

### 2.4 Layout, spacing, touch

- Sidebar layout (collapsible, `variant="inset"`), content padding `p-4 md:p-6`, sections `gap-6`, form grids `md:grid-cols-2`.
- **Touch targets ≥ 44 px**: inputs, selects, date picker and combobox are `h-11`; icon buttons `size-9`+.
- Breakpoints: phone < 640 (single column; table hides secondary columns), tablet 768–1024 (sidebar collapses to icons; two-column forms), desktop ≥ 1024.

### 2.5 Accessibility built into the theme

`:focus-visible` outline on everything · `prefers-reduced-motion` disables animations · print stylesheet hides sidebar/header/filters/pagination (`data-print="hide"`) · dark mode via existing Appearance setting.

## 3. Reusable components (`resources/js/components/app`)

| Component                                                 | Built from (shadcn)                                      | Purpose / key props                                                                                                                                                                                     |
| --------------------------------------------------------- | -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `PageHeader`                                              | Button                                                   | Back link, the page's only `<h1>`, description, meta badges, right-aligned actions                                                                                                                      |
| `StatCard` / `StatCardSkeleton`                           | Card, Skeleton                                           | Icon + label + big number + hint; optional `href` to the filtered list; tones primary/success/warning/info/danger                                                                                       |
| `DataTable` / `DataTablePagination` / `DataTableSkeleton` | Table, Select, Button + **TanStack Table (manual mode)** | Renders one server page; sort headers (`meta.sortKey`, `aria-sort`), "Showing 1–15 of 230", rows-per-page, first/prev/next/last, `rowHref` row click, `hideOnMobile` columns, `aria-busy` while loading |
| `useTableQuery` (hook)                                    | Inertia `router.get`                                     | Filters/sort/page **in the URL**, `preserveState` + `preserveScroll` + `replace`, **300 ms debounced search**, partial reload via `only`, `processing` flag                                             |
| `SearchFilters`                                           | InputGroup, Select, Label                                | Search box + filter selects ("All puroks"…), extra slot (e.g. _Show archived_ Switch), _Clear filters_; `role="search"`                                                                                 |
| `ConfirmDialog`                                           | AlertDialog                                              | Title, explanation, optional extra fields (archive reason), destructive styling, stays open while processing                                                                                            |
| `HealthTimeline` + `VitalChips`                           | Badge-like StatusBadge, Empty                            | Visits grouped by year, newest first; type + maternal badges; complaint/diagnosis/notes or a 🔒 "hidden for your role" line; vital chips flag **High BP** (≥140/90) and **Fever** (≥37.5 °C) in text    |
| `StatusBadge` / `ResidentBadges`                          | (Badge styling)                                          | Senior, Infant, PWD, 4Ps, Pregnant, Moved, Deceased, Archived — with icons + text                                                                                                                       |
| `FormField` + `describedBy()`                             | Field, FieldLabel, FieldDescription, FieldError          | Label (+ "*" and sr-only "required"), hint, Laravel error under the field with `role="alert"`, ids wired for `aria-describedby`                                                                         |
| `DatePicker`                                              | Popover, Calendar (react-day-picker)                     | Month/year dropdowns (1900 → today), `disableFuture`, hidden input for Inertia `<Form>`                                                                                                                 |
| `Combobox`                                                | Popover, Command (cmdk)                                  | Searchable select; server search (debounced) or local filter; second-line description; footer slot ("+ Create new household"); hidden input                                                             |
| `SensitiveValue`                                          | Button, Tooltip                                          | Masked by default with Show/Hide toggle; lock + explanation when the server withheld the value                                                                                                          |
| `EmptyState`                                              | Empty                                                    | Icon, what's missing, what to do next, optional action                                                                                                                                                  |
| `PurokChart`, `AgeSexPyramid`                             | Chart (Recharts)                                         | Stacked bars per purok; population pyramid; each includes an **sr-only data table** (charts alone aren't accessible)                                                                                    |
| `lib/format.ts`                                           | —                                                        | `formatDate` ("Oct 30, 2026", no timezone shift), `formatDateTime` (Asia/Manila), `formatRelative`, `formatAge` (wk/mo for babies), `formatNumber`, `formatBytes`                                       |

shadcn components added this phase: table, tabs, alert-dialog, popover, calendar, command, radio-group, textarea, chart, pagination, scroll-area, switch, field, empty, input-group (+ `@tanstack/react-table`, `recharts`, `react-day-picker`, `date-fns`, `cmdk`). Only one edit to `components/ui`: `DialogContent` gained the upstream `showCloseButton` prop (needed by `command.tsx`).

## 4. Page designs (built in Phase 5)

Legend: **[shadcn]** components used · _(custom)_ app components.

### 4.1 Login — shadcn `login-02` block (split screen)

```
┌───────────────────────────────┬──────────────────────────────────┐
│ [logo] Barangay Health Center │                                  │
│ Kalawag II · Isulan           │   Teal panel with health-center   │
│                               │   illustration / barangay seal,   │
│ Log in                        │   "Resident Health Profiling      │
│ Username [___________]        │    System" tagline                │
│ Password [___________] 👁     │   (hidden on tablet portrait)     │
│ [ Log in              ]       │                                  │
│ ── Data Privacy Notice ──     │                                  │
│ ⓘ Health information is       │                                  │
│ protected under RA 10173 …    │                                  │
│ [Read full notice] (Dialog)   │                                  │
└───────────────────────────────┴──────────────────────────────────┘
```

[Card, Input, Label, Button, Alert, Dialog] · _(PasswordInput)_ · lockout/deactivated messages as field errors.
Change-password page (done) uses the same auth layout.

### 4.2 App shell — shadcn `sidebar-07` block (collapsible to icons)

Sidebar groups by role: **Health center** (Dashboard, Residents, Households, Reports) · **Administration** (Users, Audit logs, Backups). Header: SidebarTrigger, Breadcrumbs. Footer: user dropdown (name, role, **My account**, **Log out**). [Sidebar, Breadcrumb, DropdownMenu, Avatar, Tooltip].

### 4.3 Dashboard — shadcn `dashboard-01` block, adapted

```
PageHeader "Magandang araw, Analiza"   [Register resident] [Record visit]
┌StatCard┐┌StatCard──┐┌StatCard┐┌StatCard──┐┌StatCard───────────┐
│Residents││Households││Seniors ││Pregnant  ││Children due (warn)│   ← deferred, StatCardSkeleton
└────────┘└──────────┘└────────┘└──────────┘└───────────────────┘
┌Card: Residents per purok (PurokChart)──┐┌Card: Recent activity────────┐
│                                        ││• Analiza registered KII-…   │
└────────────────────────────────────────┘│• Cristina recorded a visit  │
┌Card: Age & sex (AgeSexPyramid)─────────┐│  (health workers only)      │
└────────────────────────────────────────┘└─────────────────────────────┘
```

Officials see the same cards and charts (aggregates) without Recent activity or quick actions. Stat cards link to the filtered resident list / report.

### 4.4 Residents list

PageHeader (Register resident) → _SearchFilters_ [search · Purok · Sex · Age group · Status · Switch "Show archived"] → _DataTable_:
Name (link + _ResidentBadges_) · Resident ID (mono) · Age · Sex · Household · Purok (hideOnMobile) · ⋯ **DropdownMenu** (View, Edit, Archive → _ConfirmDialog_ with Status RadioGroup + Reason Textarea). Archived view: Restore / Delete permanently (Admin, after retention). _EmptyState_ with "Clear filters" / "Register resident".

### 4.5 Register / Edit resident — Card sections (one page, three sections)

1. **Household** — _Combobox_ (search; "+ Create new household" opens Dialog with purok Select + address) · Relationship to head (Select).
2. **Personal information** — Last/First/Middle (Input), Suffix (Select), Sex (RadioGroup), Birthdate (_DatePicker_), Birthplace, Civil status (Select), Contact no. (Input, `inputMode="tel"`), PhilHealth no. (Input, masked hint), PWD / 4Ps (Checkbox).
3. **Consent (RA 10173)** — Checkbox "The resident (or parent/guardian) gave consent", date, purpose (Textarea, prefilled). Create only.

**Duplicate warning:** as soon as last name + first name + birthdate are filled, the live check runs; matches show in an **Alert** above the form with links to the existing profiles and a Checkbox "This is a different person". Sticky footer: Cancel · Save. Errors under each field via _FormField_.

### 4.6 Resident profile

```
PageHeader: Villanueva, Lourdes Pascual  [Senior][PWD]      [Record visit ▾] [Edit] [⋯]
KII-2026-00005 · 81 yrs · Female · Purok 1 · HH-0001
Tabs: Personal Info | Household | Health Timeline | Immunizations | Maternal | History
```

- **Personal Info**: description list in Card; PhilHealth via _SensitiveValue_; Consent history table + "Record new consent"/"Withdraw".
- **Household**: household card + members Table (links).
- **Health Timeline** (deferred → Skeleton): _HealthTimeline_; "Record visit" opens a **Sheet** (right side; full-screen on phones) with visit type, date, complaint, diagnosis, notes, vitals grid, and prenatal/postnatal fields when the resident has a pregnancy record.
- **Immunizations**: EPI schedule Table — vaccine/dose, due date, status badge (Given ✓ / Due / **Overdue** / Upcoming), date given; "Record dose" Dialog. Conditions list with status badges.
- **Maternal** (female 10–55): current pregnancy Card (EDD, AOG weeks, G/P, risk badge), prenatal visit list, Add/Update pregnancy Dialog.
- **History** (deferred): audit entries (who changed what, old → new).
  Health tabs are absent for roles without access; the page never shows an error, just fewer tabs.

### 4.7 Households

List: _SearchFilters_ (search, Purok) + _DataTable_ (Household no., Head, Purok, Address, Members). Detail: header + members Table + "Add member" (→ register form with household preselected) + Edit Dialog.

### 4.8 Reports

```
PageHeader "Reports"                        [Print] [PDF] [Excel] [CSV]   ← plain <a href> downloads
Filter bar: Date from (DatePicker) · Date to (DatePicker) · Purok (Select) · [Apply]
StatCards row (totals) → Card: PurokChart → Card: AgeSexPyramid
Cards with Tables: Common conditions · Visits by type · Immunizations given
(Health workers only) Tabs: Children due for immunization | Pregnant women — Tables with links
```

Print uses the print stylesheet (sidebar/filters hidden). Officials see no named lists.

### 4.9 Admin pages

- **Users**: _DataTable_ (Name, Username, Role badge, Status: Active / Inactive / 🔒 Locked, Last login) · "Add user" **Dialog** form · row menu: Edit, Reset password (_ConfirmDialog_), Unlock · temporary-password **Dialog** shown once with a copy button and "write this down" note.
- **Audit logs**: read-only _DataTable_ + filters (user, action, record type, date range); row opens a **Sheet** showing old → new values side by side.
- **Backups**: Card "Last successful backup 3 hours ago" + **Back up now** (_ConfirmDialog_) · schedule/retention info · Table (file, type, status badge, size, by, date, Download link).

### 4.10 Feedback patterns

Sonner toasts from Laravel flash (`toast`) — success green, error red, top-right, 5 s · Skeletons for deferred props · _EmptyState_ everywhere a list can be empty · Inertia progress bar in primary teal · Buttons show a Spinner and disable while submitting.

## 5. Accessibility checklist (applied to every page in Phase 5)

- One `<h1>` per page (PageHeader); sections use `<h2>`/`<h3>`.
- Every input has a visible `<Label>`; hints and errors linked with `aria-describedby`; errors use `role="alert"`.
- Keyboard: all actions reachable by Tab; dialogs trap focus and return it; DataTable rows also contain real links.
- Tables have captions (sr-only) and `aria-sort` on sorted headers; pagination is a labelled `<nav>`.
- Charts have equivalent data tables for screen readers.
- Status is never shown by color alone.
- Contrast verified (§2.2); focus outline visible in both themes.

## 6. Notes

- **Bundle size:** the gallery page is the largest chunk (575 KB, includes Recharts) and loads only on that page. Recharts will load only on Dashboard and Reports; other pages share a 343 KB vendor chunk.
- **shadcn CLI quirk:** this CLI version rewrote `@/lib/utils` imports to a bare `"cn"` package and added that package to `package.json`. Both were corrected; if you add components later, check for `from "cn"` and remove the `cn` dependency.
- **Visual review:** I verified build, types, lint and contrast programmatically but could not view the pages in a browser from here — please open `/dev/components` (light and dark) and tell me what to adjust before Phase 5.
