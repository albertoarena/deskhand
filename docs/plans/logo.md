# Plan — deskhand logo & cover art

> **Status:** Decisions approved, pending implementation.
> **Scope:** `docs/implementation.md` §16 step 12 — "SVG, echoing the desk/hand
> motif, sibling to envaudit branding" — expanded to a full visual identity: a
> logo set, a README cover hero, and a docs-site re-theme. This document is the
> reference; implement to it.

## 1. Context

deskhand's only remaining deliverable is its visual identity. The brief says
"sibling to envaudit branding." envaudit has **no logo**, but it has an identity
on its docs site — a **green** Starlight accent (`#2d8a4e` light / `#3dba6a`
dark). "Sibling" is therefore interpreted as: **the same system** (clean flat
geometric marks, a Starlight accent-triple palette), with deskhand taking its
**own hue** so the two are one coordinated family yet never confused.

The README cover follows the pattern established in
`albertoarena/filament-event-sourcing`: an `art/` folder of light/dark hero
images referenced from the README via `<picture>`, reused for the GitHub social
preview and blog posts.

## 2. Concept — the service bell

The core mark is a **front-desk service bell** (the dome bell you tap).

- A literal pun on the name: a **desk** bell struck with your **hand**.
- Reads as *"ring and your desk is set up for you"* — exactly what `deskhand up`
  does (provision an isolated, ready-to-work environment on demand).
- A simple closed silhouette that stays legible at 16px and in one colour.

**Construction:** flat, geometric, single visual weight (matches envaudit's clean
style). A **dome** (semicircle) on a **base plate**, a **knob** on top; optional
subtle strike/sound cue that is dropped at favicon size. Solid fills over thin
strokes. Two-tone: dome in brass **accent**, base/knob in **ink**. Drawn on a
**32×32** grid.

## 3. Palette — "Brass & Ink"

Warm amber/brass — the colour of a brass bell, desk-lamp warmth, and a warm
counterpart to envaudit's cool green. Mirrors envaudit's accent-triple structure
so it can theme the docs site.

| Token | Light mode | Dark mode |
|---|---|---|
| accent-low | `#F6E7CE` | `#3A2A12` |
| **accent** | `#BE7B18` | `#E3A24A` |
| accent-high | `#8A5510` | `#F2CF9B` |

Neutrals (both modes): ink `#1F2328`, warm paper `#FBF7F0`, plus Starlight grays.
Monochrome: brass `#BE7B18` on transparent, or pure ink `#1F2328` for one-colour.

Reference — envaudit accents (do **not** reuse): green `#2d8a4e` / `#3dba6a`.

## 4. Typography

- **Wordmark:** the lowercase word `deskhand`, set in **Space Grotesk, medium**.
  The two halves are colour-split — **`desk` in ink, `hand` in brass** — making
  the desk/hand concept literal. Shipped SVGs **outline the text to paths** so
  rendering never depends on an installed font.
- **Cover headings/pills** also use Space Grotesk; body/tagline may use the
  Starlight default (Inter-like) for readability.

## 5. Logo set (icon + wordmark)

**All logo and cover assets live together in a single flat `art/` folder** (no
subfolders) — mirroring the `art/` convention already used across the author's
other repos.

| File | Contents | Primary use |
|---|---|---|
| `art/logo.svg` | bell + wordmark, light backgrounds | docs title (light), inline |
| `art/logo-dark.svg` | bell + wordmark, dark backgrounds | docs title (dark) |
| `art/icon.svg` | bell only, two-tone brass/ink | general icon |
| `art/icon-mono.svg` | bell only, `currentColor` | one-colour contexts |
| `art/favicon.svg` | bell only, simplified for 16–32px | browser tab |

Geometry: icon on a 32×32 viewBox; lockup ≈ 180×48, icon left, optical spacing to
the wordmark.

## 6. README cover hero

A wide banner, **light + dark**, modelled on filament-event-sourcing. Canvas
**1280×640** (2:1 — satisfies the GitHub social-preview minimum and works as the
README hero). Authored as **SVG**, exported to **PNG** for the README and social
preview.

Layout:

- **Left column**
  - Eyebrow: brass dot + `LARAVEL · PARALLEL AGENTS` (uppercase, letter-spaced,
    gray).
  - Wordmark lockup: `deskhand` large — `desk` ink, `hand` brass — with the bell
    icon alongside.
  - Tagline: *"Isolated, test-passing Laravel environments per worktree — for
    parallel AI coding agents."*
  - Three pills: `› Full isolation`  `› Deterministic ports`  `› Verified by Pest`.
- **Right column — terminal mockup** (the CLI's "product shot"): a window with
  three traffic-light dots and a `deskhand — up` titlebar, showing a provisioning
  run in monospace:
  ```
  $ deskhand up feature/billing
  ✓ worktree   .claude/worktrees/feature-billing
  ✓ env        copied .env + fresh APP_KEY
  ✓ database   sqlite (isolated)
  ✓ deps       composer install
  ✓ migrate    3 migrations
  ✓ verify     Pest suite green
  → http://127.0.0.1:8312
  ```
  Check marks in brass/green, paths and the URL in accent.
- **Background:** subtle dot grid at left; a soft brass→paper gradient at top
  right. Dark variant: ink background, paper text, brass accents, darker terminal.

Files (same flat `art/` folder as the logo set):

| File | Use |
|---|---|
| `art/cover-light.svg`, `art/cover-dark.svg` | source |
| `art/cover-light.png`, `art/cover-dark.png` | README `<picture>` hero |
| `art/social-preview.png` (1280×640) | GitHub social preview (light variant) |

## 7. Integration points

1. **README** — add a `<picture>` cover hero at the very top (light/dark via
   `prefers-color-scheme`), above the badge row; keep the tagline text beneath.
2. **Docs site (Starlight)** — **re-theme now**:
   - set `logo` (light/dark) and `favicon` in `website/astro.config.mjs`;
   - copy assets into `website/src/assets/` and `website/public/`;
   - add `website/src/styles/custom.css` overriding `--sl-color-accent*` with the
     Brass & Ink triples (sibling to envaudit's `custom.css`), replacing
     Starlight's default blue.
3. **GitHub social preview** — `art/social-preview.png` uploaded manually in
   **Settings → General → Social preview** (cannot be set via git; PNG only).
   Generated from the cover SVG (e.g. via `sharp`, already a website dependency).

## 8. Rules

- Ship light + dark for cover and logo; monochrome fallback for the icon.
- Icon min 16px; test the favicon at 16px in one colour.
- No envaudit green; no recolouring outside the palette; no stretching lockups.

## 9. Out of scope / later

- Animated (ring/wobble) variant.
- Additional raster sizes/thumbnails beyond the README hero + social preview.
- A dedicated brand page in the docs.

## 10. Decisions (resolved)

- **Concept:** service bell. ✔
- **Palette:** Brass & Ink (warm amber sibling to envaudit green). ✔
- **Form:** icon + wordmark, plus a README cover hero with a terminal mockup. ✔
- **Wordmark typeface:** Space Grotesk, medium; `desk` ink / `hand` brass. ✔
- **Docs re-theme:** yes, in this pass. ✔

## 11. Implementation order

1. Icon + favicon (the atom everything else reuses).
2. Wordmark + logo lockup (light/dark).
3. Cover hero SVGs (light/dark) → PNG exports.
4. Docs re-theme (accent CSS, logo, favicon).
5. README `<picture>` cover.
6. Social-preview PNG (manual upload by maintainer).
