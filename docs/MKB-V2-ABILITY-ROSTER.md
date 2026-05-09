# MKB v2 — Ability Roster

> Drafted 2026-05-09. The substrate roadmap for Mega Kadence Bridge v1.3 → v2.1.
> Companion to `~/kadence-skill/mega-kadence-skill/PROMOTION-AUDIT.md` which sources every entry below from concrete skill recipes.

## Mission

MKB is the Kadence vocabulary. Every Drop skill (Store Drop, future Editorial / Portfolio / Course / Local-Service / Charity Drops) consumes it. Investment in MKB depth compounds across all of them. This roster turns that thesis into a numbered, schema-shaped, contractor-ready scope.

Ranking criterion: **(Store Drop blast radius) × (any-Kadence-site reusability)**. An ability that simplifies five Store Drop recipe sections AND applies to every Kadence site Jon will ever touch ranks higher than one that helps only Store Drop.

Cross-cutting Novamira lessons applied across the whole roster:
- **Per-property error specificity** — every endpoint validates inputs and returns `{code, message, data: {invalid_properties: [{property, reason, expected}]}}` so the agent can self-correct in one round-trip.
- **Idempotency** — every write is safely repeatable; non-idempotent operations carry an explicit `idempotent: false` annotation in the response.
- **Snapshot id in response** — every write echoes back `snapshot_id` so callers can chain rollback without a second `GET /history` call.

---

## v1.3 — Wave 1 (kill the `!important` CSS blobs)

### 1. `POST /palette/apply-mode-overrides`

**Why this matters:** Today the skill's `set-palette.md` recipe and the keystone `deploy-pod-store.md` both inject the same ~600-character CSS blob to make dark-mode Kadence text actually visible. Every dark-mode Kadence site needs this exact override; without it, headings render against `entry-content-wrap` defaults that make body copy invisible. Non-POD-specific. Belongs in MKB.

**Input schema:**
```json
{
  "mode": "light" | "dark",
  "css_overrides_enabled": true
}
```

**Output schema:**
```json
{
  "success": true,
  "mode": "dark",
  "applied_theme_mods": ["site_background", "content_background", "mobile_navigation_color"],
  "applied_css": "body, .entry-content { color: var(--global-palette4) !important; } ...",
  "snapshot_id": 1234
}
```

**Behavior:**
- Light mode: clears `site_background`, `content_background`, sets `mobile_navigation_color` to light-friendly values, no CSS injection.
- Dark mode: sets `site_background = palette8`, `content_background = ""` (the empty-string critical case), `mobile_navigation_color` to dark-friendly values, AND injects the canonical text-visibility CSS via existing `/css`.
- Either path snapshots prior state.

---

### 2. `POST /header/apply-mobile-trigger-style`

**Why this matters:** Mobile trigger button + slide-out drawer always need custom CSS on Kadence — `theme_mods` alone don't reach the right selectors. Skill currently has two ~1KB CSS blobs (light + dark variants) inline in deploy-pod-store.md and build-nav-menus.md, hand-edited with `!important`. Universal Kadence requirement.

**Input schema:**
```json
{
  "mode": "light" | "dark",
  "accent_palette_slot": "palette1",
  "label_palette_slot": "palette9"
}
```

**Output schema:**
```json
{
  "success": true,
  "applied_css": "...",
  "snapshot_id": 1235
}
```

---

## v1.4 — Wave 2 (palette + typography mastery)

### 3. `POST /palette/apply-from-brand`

**Why this matters:** Kadence's 9-slot palette has a doctrine: slot1 = primary CTA, slot3 = headings, slot8 = page background, etc. Skill's `set-palette.md` encodes the doctrine PLUS color-name resolution ("forest green" → `#228B22`), auto-brightening for dark mode (HSL < 40 → shift to 65), and WCAG 4.5:1 contrast verification. All of this is universal Kadence palette mastery.

**Input schema:**
```json
{
  "primary": "#FF5500" | "forest green" | null,
  "mode": "light" | "dark",
  "auto_brighten": true,
  "contrast_check": true,
  "fallback_default": "auto"
}
```

`primary: null` → uses the mode-default (light: `#1B4F8A`; dark: `#FF5500`).

**Output schema:**
```json
{
  "success": true,
  "resolved_primary": "#4A8FD4",
  "resolved_palette": [
    {"slug": "palette1", "color": "#4A8FD4", "name": "Primary CTA"},
    ... 8 more ...
  ],
  "auto_brightened": true,
  "warnings": [
    {"slot": "palette4", "issue": "contrast_below_threshold", "ratio": 4.21, "required": 4.5}
  ],
  "snapshot_id": 1240
}
```

**Behavior:**
- Resolve color name → hex (built-in dictionary; falls back to a sensible match for descriptive input).
- Auto-brighten if dark mode + lightness < 40.
- Fill slots 3-8 from mode defaults baked into MKB.
- Run WCAG contrast verification across the standard pairs (palette1/8, palette3/8, palette4/8) and emit warnings (don't fail) for anything below 4.5:1.
- Write via existing `/palette` endpoint.

---

### 4a. `POST /typography/apply-by-tone`

**Why this matters:** Skill maintains a 10-tone × font-pair lookup. Universal Kadence typography mastery.

**Input schema:**
```json
{
  "tone": "Bold & Rebellious" | "Modern & Minimal" | ... | "auto",
  "niche_hint": "funny golden retriever shirts",
  "scale": "compact" | "standard" | "spacious"
}
```

`tone: "auto"` + `niche_hint` → infers tone from niche.

**Output schema:** resolved tone, heading_font, body_font, applied size scale, snapshot id.

### 4b. `POST /typography/apply-pair`

For agents that already know what fonts they want.

**Input schema:**
```json
{
  "heading": {"family": "Anton", "weight": "400", "variant": "regular", "google": true},
  "body":    {"family": "Inter", "weight": "400", "variant": "400",     "google": true},
  "scale":   "standard"
}
```

`scale` controls H1/H2/H3 sizes; `standard` = the 32/28/22 desktop scale baked into the skill today.

---

## v1.5 — Wave 3 (header / footer / meta / menus / identity)

### 5a. `POST /header/configure`

**Input:**
```json
{
  "layout": "logo-left-nav-right" | "logo-left-nav-right-with-cart" | "logo-center-nav-below" | "custom",
  "sticky": true,
  "transparent": false,
  "height": {"desktop": 68, "tablet": 60, "mobile": 51},
  "mode": "light" | "dark"
}
```

Internally emits correct `header_desktop_items` / `header_mobile_items` / `header_main_height` / `header_main_background` / `header_sticky_background`. Hides the Builder JSON foot-gun.

### 5b. `POST /header/builder/set-slots`

Low-level escape hatch for unusual layouts. Validates slot names against the known list and returns per-property errors for typos like `left` vs `main_left` or `mobile-trigger` vs `popup-toggle`.

### 5c. `POST /header/transparent`

```json
{
  "enable": true,
  "pages": [42, 43, 44],
  "post_types": ["page"],
  "navigation_color": {"color": "palette9", "hover": "palette1"},
  "site_title_color": {"color": "palette9"}
}
```

Sets the global `transparent_header_*` theme_mods AND the per-page `_kad_post_transparent` meta in one call.

### 5d. `POST /branding/logo`

```json
{
  "image_id": 123,
  "title_only": false,
  "width": {"desktop": 280, "tablet": 140, "mobile": 120}
}
```

Emits the famously-gnarly `logo_layout` object correctly. Caller specifies image-or-title; MKB does the JSON.

### 6. `POST /footer/configure`

```json
{
  "layout": "brand-nav-social" | "brand-nav" | "centered-stack" | "custom",
  "brand": {"name": "CuteMerch", "tagline": "...", "copyright_text": "{copyright} {year} {brand}. All rights reserved."},
  "menu_id": 7,
  "social": [{"platform": "instagram", "url": "https://instagram.com/cutemerch"}],
  "mode": "light" | "dark"
}
```

Hides `footer_items` slot map + column-count + spacing.

### 7. `POST /posts/{id}/kadence-meta`

```json
{
  "hide_title": true,
  "hide_featured_image": true,
  "layout": "fullwidth",
  "vertical_padding": "disable",
  "transparent_header": "enable"
}
```

Internally writes the `_kad_*` post meta family. Friendly names; no key memorization.

### 8. `POST /menus/assign-locations`

```json
{
  "primary": 5,
  "footer": 7
}
```

Merges with current `nav_menu_locations` instead of replacing. Solves the "setting one location clears the others" foot-gun.

### 10. `POST /site/identity`

```json
{
  "name": "CuteMerch",
  "tagline": "Adorable designs for everyday life",
  "favicon_id": 99,
  "default_og_image_id": 100,
  "footer_credit_text": "Built with MKB"
}
```

One atomic call writes `blogname`, `blogdescription`, `site_icon`, custom default-OG option, and Kadence's footer credit override.

---

## v2.0 — Wave 4 (the pattern library)

### 9a. `GET /patterns`

Returns the catalog of MKB-shipped Kadence patterns:

```json
{
  "patterns": [
    {
      "name": "hero",
      "label": "Hero (full-width, centered, dark overlay)",
      "parameters": {
        "headline":    {"type": "string", "max_words": 7,  "required": true},
        "subheadline": {"type": "string", "max_words": 18, "required": false},
        "cta_label":   {"type": "string", "max_words": 3,  "required": true},
        "cta_url":     {"type": "string", "format": "uri", "required": true},
        "background_palette_slot": {"type": "string", "default": "palette8"}
      }
    },
    { "name": "trust-row",         ... },
    { "name": "brand-story-2col",  ... },
    { "name": "cta-band",          ... },
    { "name": "newsletter",        ... },
    { "name": "featured-products", ... }
  ]
}
```

### 9b. `POST /pages/insert-pattern`

```json
{
  "post_id": 42,
  "position": "append" | "prepend" | 0,
  "pattern": "hero",
  "parameters": {
    "headline": "Designs That Make Retrievers Famous",
    "subheadline": "Original art for golden retriever lovers — designed with love, printed on demand.",
    "cta_label": "Shop Now",
    "cta_url": "/shop/",
    "background_palette_slot": "palette8"
  }
}
```

**Behavior:**
- Validates parameters against the pattern schema; returns per-property errors for bad input.
- Emits valid Kadence block markup with `kbVersion:2`, palette tokens (no inline hex), correct `maxWidth:1290` containment.
- Inserts at position into post content.
- Calls existing `/posts/{id}/normalize-blocks`.

This single endpoint replaces hundreds of lines of hand-authored block markup in skill recipes.

---

## v2.1 — Wave 5 (the diagnostics audit)

### 11. `GET /diagnostics`

Audits the current site for Kadence-best-practice violations.

**Output schema:**
```json
{
  "violations": [
    {"rule": "no_inline_hex_in_custom_css", "severity": "warning", "location": "/css", "instances": 3, "suggestion": "Replace #FF5500 with var(--global-palette1)"},
    {"rule": "raw_html_where_kadence_block_exists", "severity": "info", "location": "post:42", "instances": 1, "suggestion": "Three-column section uses <div>; convert to kadence/rowlayout columns:3"},
    {"rule": "kbversion_missing", "severity": "error", "location": "post:42", "instances": 5, "suggestion": "Block does not have kbVersion:2 — Kadence will fall back to legacy rendering"},
    {"rule": "missing_alt_text", "severity": "warning", "location": "media:88", "instances": 1},
    {"rule": "palette_slot_used_but_not_defined", "severity": "error", "location": "/css", "instances": 1, "suggestion": "var(--global-palette10) referenced — Kadence palette only has slots 1-9"}
  ],
  "summary": {"errors": 6, "warnings": 4, "info": 1}
}
```

Rule set is filterable via `mkb_diagnostic_rules` so layered skills can add their own.

---

## Cross-cutting v1.3 → v2.1 deliverables

These ship inside Wave 1 (small lift, big footprint):

### A. Per-property error envelope (Novamira port #4 applied)

Convert all existing endpoints from generic `WP_Error` to the structured form:

```json
{
  "code": "mkb_validation_failed",
  "message": "Two input properties failed validation.",
  "data": {
    "status": 400,
    "invalid_properties": [
      {"property": "primary", "reason": "color_name_unresolved", "value": "fuschia", "expected": "hex format #RRGGBB or one of: forest green, deep red, hot pink, royal blue, burnt orange, rose gold, teal, lavender, charcoal, gold"},
      {"property": "mode", "reason": "value_not_in_enum", "value": "automatic", "expected": "light or dark"}
    ]
  }
}
```

Match Novamira's principle: tell the agent EXACTLY which property failed and how to fix it. Self-correction in one round-trip beats round-trips for "it didn't work, what did I do wrong?"

### B. Snapshot-id-on-response

Every write endpoint adds `snapshot_id` to its response payload AND a `X-MKB-Snapshot-Id` HTTP header. Agents performing multi-step changes capture the first snapshot id and `/rollback/{id}` to revert atomically without `/history` lookup.

### C. JSON Schema input validation

Every endpoint declares its input schema (the input_schema field, similar to MCP tool schemas) and validates incoming requests at the controller layer. Schema-level errors get the structured envelope from (A).

### D. Capabilities advertises new endpoints

Already in v1.2 — `/capabilities` lists the endpoint inventory. As new endpoints land, the inventory updates, and the Kadence-instructions doctrine is updated to reference the new capability ("when applying a brand color, prefer `/palette/apply-from-brand` over hand-rolling palette JSON").

---

## Out of scope (deliberate)

- **Arbitrary PHP execution.** The existing `/wp-eval` route is in tension with the doctrine and should be deprecated or gated behind an off-by-default `mkb_enable_eval` option in v1.3. Tracked but not part of the new ability roster.
- **Filesystem write.** That's Novamira's domain. MKB's bet is opinionated REST, not raw primitives. If a task genuinely requires filesystem write, the operator runs Novamira alongside MKB.
- **Image generation.** Different layer entirely. Stays in MEGA.

## Estimated scope

| Wave | Endpoints | Code size estimate | Test surface |
|---|---|---|---|
| v1.3 | 2 | ~200 LOC | mode-override CSS rendering |
| v1.4 | 3 (incl. apply-pair as separate endpoint) | ~500 LOC | color-name resolver, brightening, contrast |
| v1.5 | 7 | ~700 LOC | Builder JSON correctness, foot-gun coverage |
| v2.0 | 2 (catalog + insert) + 6 patterns shipped | ~1,200 LOC + pattern library | every pattern's variable substitution |
| v2.1 | 1 + ruleset framework | ~600 LOC + per-rule modules | diagnostic accuracy |
| Cross | error envelope, snapshot-id, JSON Schema | ~400 LOC across existing files | regression on every existing endpoint |

Total v1.3 → v2.0: ~3,000 LOC of careful endpoint work, plus the cross-cutting refactor. This is exactly the shape of work to hand to the Upwork contractor pre-Mega-launch (memory: `project_mega_release_safety`).

## Definition of done per ability

For each new endpoint:
1. PHP class registered, route bound, permission_callback set to `MKB_REST_Controller::check_permission`.
2. Input schema defined; validation returns the structured per-property error envelope.
3. Success path snapshots prior state via `MKB_History`.
4. Response includes `snapshot_id`.
5. README "API Overview" table updated.
6. `/capabilities` endpoint inventory updated.
7. Manual smoke test: activate v1.x on a clean Hostinger Kadence site, hit the endpoint with valid + invalid input, confirm responses match schema.
8. Doctrine string in `class-instructions.php` updated to reference the new capability where applicable.
