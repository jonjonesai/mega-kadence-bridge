# Dev Log — Mega Kadence Bridge & Kadence Skill Project

---

## Session 5 — 2026-05-09 (v1.2.0: Novamira-inspired hardening + thesis broadening)

**Branch:** main (1 commit ahead of origin pre-session). New work uncommitted.
**Trigger:** Strategic readout on novamira.ai (use-novamira/novamira on GitHub) revealed nine portable patterns. Jon authorized integration of relevant ones and broadened MKB's positioning from "AI-built POD stores" to "AI mastery of Kadence, full stop — POD stores are one application."

### What Novamira is, in one sentence

A WP plugin (~6k lines) that registers ten abilities on the WordPress Abilities API + MCP Adapter — nine raw primitives (PHP eval, file CRUD, list-directory, upload-link) plus a discovery tool. Stack-agnostic by design. Their Kadence "support" is marketing — the repo contains zero Kadence code; the agent figures Kadence out from PHP eval + filesystem.

That's the inverse of MKB's bet. MKB encodes Kadence-fluent operations as opinionated REST endpoints. Different layer entirely. Novamira is a primitives substrate; MKB is a Kadence vocabulary.

### Ports landed in v1.2.0

1. **Domain-locking** (`MKB_LOCKED_DOMAIN_OPTION`, `MKB_Activator::lock_to_current_domain`, `MKB_REST_Controller::check_domain_lock`). On activation MKB records the site host. Every REST request checks the host matches; mismatch returns `403 mkb_domain_mismatch` with locked/current values in the error data so the agent (or operator) sees exactly what happened. Pre-1.2.0 installs with no lock recorded fail open and adopt the current domain on next activation cycle. Direct port of Novamira's `helpers.php:189-223` pattern, adapted for REST instead of MCP.

2. **`/capabilities` discovery endpoint** (`includes/endpoints/class-capabilities-endpoints.php`). Modeled after Novamira's `discover-abilities` override. Returns:
   - `bridge`: MKB version, namespace, lock state.
   - `site`: name, URLs, WP/PHP version, locale, timezone, HTTPS, multisite.
   - `stack`: theme detection (Kadence + child-theme aware), Kadence Blocks/Pro/Blocks-Pro/Starter/Conversions/Woo-Extras, Iconic, WooCommerce, ACF/ACF Pro, WPML/Polylang/TranslatePress, Yoast/RankMath. Each present plugin returns file/name/version/active.
   - `multilingual`: detected translation system + language codes.
   - `endpoints`: hand-curated, agent-readable inventory grouped by category (discovery / theme_identity / kadence / content / media / commerce / plugins / cache / reversibility). Commerce hidden when Woo inactive.
   - `kadence_instructions`: the operating doctrine (see #3).

3. **Kadence-mastery operating instructions** (`includes/class-instructions.php`, returned via `MKB_Instructions::build()`, filterable through `mkb_kadence_instructions`). Ten doctrines:
   1. Discover before you act.
   2. Identity lives in tokens, not HTML (palette, theme_mods over inline CSS).
   3. Layout lives in Kadence Blocks (no raw HTML where a block exists).
   4. Headers/footers/global elements use Kadence builders, not template forks.
   5. Per-page overrides use `_kad_*` post meta.
   6. Content models use WordPress-native primitives (CPT, taxonomies, ACF, Woo).
   7. Performance is a feature — `/cache/flush` after changes; `/render` to verify.
   8. Reversibility is your safety net — capture history-id before multi-step changes.
   9. Plugin awareness — branch on what `/capabilities` reports.
   10. What MKB is and is not — REST surface for Kadence, NOT arbitrary PHP eval (intentional non-goal). If a needed primitive isn't present, name the gap.

   This is the most strategically valuable port. It's how MKB teaches every AI agent — not just Claude — how to drive Kadence correctly without each operator having to re-explain the doctrine.

### Ports deferred (with reason)

- **MCP-result unwrap shim** — N/A. MKB is direct REST + WP_Error, not MCP-Adapter-wrapped. The `WP_Error` envelope already produces correct HTTP status codes. The *spirit* (embed actionable detail for self-correction) is a refactor opportunity tracked under "per-property error specificity audit" — separate punch list, not this PR.
- **Sandbox + crash-recovery loader** — N/A. MKB does not write arbitrary PHP to the site. If MKB ever grows mu-plugin/theme-functions write capability, port it then.
- **Empty-properties JSON-Schema patch** — N/A unless MKB adopts JSON Schema validation on inputs. Defer.
- **App-Passwords three-state taxonomy** — already covered by `MKB_Activator::ensure_app_passwords_enabled()` at activation time. No runtime endpoint needed today.
- **MCP server name = site domain** — N/A, MKB is not MCP.
- **Mago / strict_types / static analysis hygiene** — separate hardening pass, scope before contractor lands. Tracked under MEGA release safety stack memory.

### Files changed

- `mega-kadence-bridge.php` — version bump 1.1.0 → 1.2.0, broadened Description, added `MKB_LOCKED_DOMAIN_OPTION` constant, required new `class-instructions.php` and `class-capabilities-endpoints.php`.
- `includes/class-activator.php` — added `lock_to_current_domain()` and call from `activate()`.
- `includes/class-rest-controller.php` — added `check_domain_lock()`, called from `check_permission()`; registered new capabilities endpoint.
- `includes/class-instructions.php` — NEW. Kadence-mastery doctrine string, filterable.
- `includes/endpoints/class-capabilities-endpoints.php` — NEW. `/capabilities` discovery endpoint.
- `uninstall.php` — clean up `mkb_locked_domain` option.
- `README.md` — broadened lede beyond POD; added `/capabilities` to API table; added Domain Locking section under Security.

### Strategic delineation locked this session

- **MKB** = the Kadence vocabulary. Stack-specific, outcome-agnostic. The substrate every "Drop" skill consumes. This is the moat.
- **Store Drop skill** (currently `mega-kadence-skill`, rename pending) = ONE outcome (7-page POD store) built on MKB. The first proof, not the only product.
- Future Drop skills (Editorial / Portfolio / Course / Local-Service / Charity) compose on the same MKB. Investment in MKB depth compounds across all of them.
- Skill rename to `store-drop-skill` (or similar) authorized 2026-05-09. Tracked in next-session work.

### Untouched but worth noting

- `class-core-endpoints.php` registers a `POST /wp-eval` route. Kept as-is for this session; flagged for review — its existence is in tension with doctrine #10. Decision: keep as expert escape hatch but consider gating behind an explicit `mkb_enable_eval` option (off by default) in v1.3.

### Verification still owed

- Activate v1.2.0 on a test Hostinger site, hit `/capabilities` with the bot creds, confirm payload shape and instructions string render.
- Restore a snapshot to a different domain, confirm `mkb_domain_mismatch` fires with the right locked/current values.
- Confirm pre-1.2.0 → 1.2.0 upgrade path: existing site has no `mkb_locked_domain` option; first authenticated request should succeed (fail-open); next activation cycle should record the domain.



> A comprehensive hand-off document. If you are a future Claude instance picking up this project and you have no prior context, read this file end-to-end before doing anything else. It contains the mission, decisions, architecture, build history, and next steps.

**Session 1:** 2026-04-11 (foundation)
**Author of record:** Jon Jones (jonjonesai)
**Primary Claude session:** Opus 4.6 (1M context), 2026-04-11

---

## NEW SESSION ORIENTATION — READ FIRST

If you are a Claude instance picking up this project in a new session, do this in order:

1. **Read this entire file.** It takes ~10 minutes and will save you 2 hours of re-discovery.
2. **Read `CLAUDE.md`** at `/home/userjjai/kadence-skill/CLAUDE.md` — project root guidance.
3. **Read `SOP-INVENTORY.md`** at `/home/userjjai/kadence-skill/SOP-INVENTORY.md` — the master list of SOPs to write.
4. **Check the current state of the bridge plugin** at `/home/userjjai/kadence-skill/bridge/mega-kadence-bridge/` and its remote at https://github.com/jonjonesai/mega-kadence-bridge.
5. **Read the sample SOP** at `/home/userjjai/kadence-skill/references/kadence-sop/02-page-settings/disable-page-title.md` — this is the template for every other SOP.
6. **Run `npm run parse` in `scripts/`** to regenerate the settings catalog if you need fresh data. It takes <5 seconds.
7. **Then ask the user where they want to resume.**

Do NOT make destructive changes (deleting files, force-pushing, destructive git operations) without explicit permission from the user. Do NOT re-decide things already decided in the "Locked-In Decisions" section below — those are settled and re-litigating them wastes everyone's time.

---

## THE MISSION

Build the world's foremost operator of Kadence.

Not "a Claude that knows Kadence." **The world's foremost operator.** A Claude instance that wields Kadence WordPress Theme and Kadence Blocks with absolute, complete, indisputable expertise — every setting, every block, every gotcha, every workflow. When a user says the slightest thing about Kadence, Claude knows exactly what to do.

This is delivered as two coupled products:

1. **Mega Kadence Bridge** — a WordPress plugin that exposes a private REST API Claude Code uses to control a Kadence-powered site. (This repo.)
2. **Kadence Skill** — a Claude Code skill package built on a comprehensive SOP (Standard Operating Procedure) index that teaches Claude how to wield Kadence. (Lives in the parent `kadence-skill` working directory, will become its own repo later.)

Together, they let anyone running Claude Code operate a Kadence site end-to-end through natural language — build pages, apply brands, configure stores, hide page titles, swap fonts, enable sticky carts — by just describing what they want.

---

## CONTEXT: THE MEGA POD ECOSYSTEM

This project is not standalone. It is one module of a larger ecosystem called MEGA, run by Jon Jones.

### The MEGA Apps

| App | URL | Purpose | Audience |
|---|---|---|---|
| **MEGA Wholesale** | app.mega.management | SaaS POD automation tool — text idea → AI artwork → mockups → WooCommerce product → Printful sync, in 2 minutes | B2B sellers building POD brands |
| **MEGA Retail** | retail.mega.management (in dev) | Consumer storefront — design your dream shirt with AI in 60 seconds, buy it | B2C end customers |
| **Mockup Studio** | mockups.mega.management | AI lifestyle mockup generator — replaces white-shirt-on-white with branded flat-lays | Both B2B and B2C |
| **Brand Site** | mega.management | WordPress sales/marketing page (VSL, content, funnel) | Lead capture |

### The MEGA Community

A paid community wrapping all of the above: courses, Discord, done-for-you onboarding for people who want to start their own POD brand using MEGA as the engine. The community is the primary user of the Kadence Bridge + Skill — specifically the **Tier 1 audience** described below.

### The POD Workflow MEGA Automates

- **Image generation:** 6 AI models in parallel (Flux 2 Pro, Ideogram V3, Seedream 5 Lite, Nano Banana 2, GPT-Image 1.5) via Replicate + kie.ai
- **Background removal:** removal.ai for sticker-mode products
- **Image processing:** PIL for FIT scaling (apparel) or COVER scaling (full-bleed)
- **Image hosting:** Cloudinary
- **Mockup generation:** Printful mockup generator API
- **Compression:** Tinify + PIL fallback
- **Product publishing:** WooCommerce REST API
- **Fulfillment:** Printful API (primary) + Merchize (backup for copyright-sensitive designs)
- **45 product types** currently supported (apparel, drinkware, wall art, phone cases, etc.)

---

## WHY THIS PROJECT EXISTS

POD competitors teach students to build stores on Shopify. Shopify has fast servers and seems simple, but:

- Base plan is $40/month minimum
- Reviews, gift certificates, blog comments, shipping plugins all require paid apps → monthly cost climbs
- Shopify takes 1% of bottom-line revenue on top of the 3% card processing fee

Hostinger is **$1.99/month** for basic, $2.99/month for business. WordPress + Kadence is free. The economics are a 20x advantage in favor of our path.

The only reason students don't take the cheaper path today is that **WordPress has a technical skill barrier**. Our thesis: if Claude Code can operate a Kadence site through natural language, that barrier drops to zero. Students buy a $2.99/mo Hostinger account, install our plugin, talk to Claude, and launch a branded store in a weekend.

### The Tagline: "Weekend Business: Friday, Saturday, Sunday"

Students come with a logo and a color palette (content provided by the community). They leave Sunday night with:

- A fully branded Kadence-powered WordPress site
- 50-100 POD products synced to Printful
- Full WooCommerce + payment setup
- A working store ready to sell

All at a hosting cost of ~$36/year instead of ~$480/year.

### Target Audience Tiers

| Tier | Who | Their Tech Level | Needs From Us |
|---|---|---|---|
| **Tier 1** | POD beginners from zero | Never opened a terminal. Don't know what WordPress is. | Everything done for them. One-click install, copy-paste credentials, talk to Claude. |
| **Tier 2** | Etsy/Shopify migrants | Comfortable clicking around but not technical. Can follow a tutorial. | Mostly done for them. Small on-ramp. |
| **Tier 3** | Developers/designers | Can SSH, read code, debug. | Skip the hand-holding. Use MEGA directly as a catalog builder. |

**The Mega Kadence Bridge + Kadence Skill targets Tiers 1 and 2.** Tier 3 uses MEGA directly.

---

## THE PROBLEM THIS IS ALSO SOLVING: THE KADENCE DOCS GAP

Beyond the POD use case, there is a secondary business opportunity here that the user has identified:

**Kadence's official documentation is poor.** It exists, but it's not comprehensive, not LLM-optimized, and does not teach you how to become an expert. Google "Kadence docs" and the results are thin. The user has submitted support tickets asking Kadence to improve their docs and make them LLM-readable. No change.

This means **nobody has built a comprehensive, machine-verified, LLM-readable Kadence reference.** The SOP index we are building (see Phase A below) is that reference. Once complete, it is a standalone knowledge asset — valuable in its own right, independent of MEGA. Any AI agent + our SOPs = an expert Kadence operator.

The user has called this "my passion and what I'm focused on" alongside MEGA itself. It is explicitly a business opportunity and not just an internal tool for MEGA. Plan the SOP index accordingly: it must be high-quality, comprehensive, and reusable.

---

## THE TWO-PART PRODUCT ARCHITECTURE

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Student's Local Computer                         │
│                                                                          │
│  VSCode + Claude Code CLI                                                │
│     ↓ reads                                                              │
│  .env file (BRIDGE_URL, BRIDGE_USER, BRIDGE_PASS)                        │
│     ↓ loads                                                              │
│  .claude/skills/kadence/SKILL.md (+ SOP index)                           │
│                                                                          │
└─────────────────────────────┬──────────────────────────────────────────┘
                              │ HTTPS + HTTP Basic Auth
                              │ (Application Password)
                              ▼
┌────────────────────────────────────────────────────────────────────────┐
│                         Hostinger Server                                │
│                                                                          │
│  WordPress + Kadence Theme + Kadence Blocks + WooCommerce                │
│                              ↑                                           │
│                              │ hooks into                                │
│                  Mega Kadence Bridge Plugin                              │
│                  (this repo's code)                                      │
│                              ↓ exposes                                   │
│  /wp-json/mega-kadence-bridge/v1/                                        │
│    /info       /render         /cache/flush                              │
│    /theme-mod  /palette        /css                                      │
│    /posts      /pages/ensure   /menus                                    │
│    /media      /media/upload-from-url                                    │
│    /blocks     /kadence-pro/config                                       │
│    /woo/*      /history        /rollback                                 │
│                                                                          │
└────────────────────────────────────────────────────────────────────────┘
```

**Component responsibilities:**

- **Claude Code** — Runs in VSCode on the student's laptop. Reads the skill + SOPs. Interprets natural-language requests. Makes API calls to the bridge.
- **Kadence Skill** (Phase A — not yet written) — Markdown-based instruction set with 200+ SOPs teaching Claude how to wield Kadence. Loaded into Claude Code's context via the skill system.
- **Mega Kadence Bridge** (this repo, Phase C, complete) — WordPress plugin that exposes the REST API. Handles auth, performs operations, tracks history for rollback.
- **WordPress + Kadence** — The actual site. Settings, content, blocks, WooCommerce.
- **Hostinger** — The hosting. LiteSpeed caching, SSH port 65002, WP-CLI at `/usr/local/bin/wp`.

---

## THE GOLDEN RULES

These are non-negotiable. Every operation the bridge performs follows these.

1. **Cache purge after EVERY change.** LiteSpeed Cache on Hostinger (and other page caches) will serve stale content if you don't explicitly purge. No exceptions.
2. **Never guess, always verify.** Use the `/render` endpoint to fetch cache-bypassed HTML and confirm the change is live before reporting success to the user.
3. **Theme mods via Bridge, NOT `wp theme mod set`.** The WP-CLI command serializes values as JSON strings, which is NOT the format Kadence expects. Always use `set_theme_mod()` via the bridge or via `wp eval`.
4. **READ before CHANGE.** Always inspect current state first. Snapshot it for rollback. Then modify.

### The Change Workflow

```
READ → PLAN → CHANGE → PURGE → VERIFY → REPORT
```

Every write operation follows this. The bridge captures a snapshot during CHANGE so the operation is reversible. VERIFY happens by calling `/render` and grepping for the expected change. REPORT only happens after VERIFY succeeds.

---

## LOCKED-IN DECISIONS

These are settled. Do not re-litigate them without strong new evidence.

### Product / Branding

- **Plugin name:** Mega Kadence Bridge
- **Plugin slug:** `mega-kadence-bridge`
- **GitHub repo:** https://github.com/jonjonesai/mega-kadence-bridge
- **License:** GPL v2 or later
- **Version strategy:** Semantic versioning. v1.0.0 released 2026-04-11.
- **Update mechanism:** `plugin-update-checker` library + GitHub Releases. The GitHub Actions workflow downloads the library during release build so we don't vendor 100KB of third-party code.

### Auth Model

- **Bot user:** A dedicated WordPress user called `claude-bot` (administrator role).
- **Visibility:** Visible in the Users list. Friendly bio framing: "Your personal Kadence wizard..."
- **Password:** WordPress Application Password, generated by the activator. Not a custom API key.
- **Transport:** HTTP Basic Authentication (native to WP 5.6+).
- **Storage:** Plain-text password stored in `wp_options['mkb_credentials']` for the Settings page to display. Also written to `wp-content/.claude-bridge/credentials.json` with `.htaccess` + `index.php` + 0600 permissions as a backup for SSH users.

### Install Flow for Tier 1 Users

The primary install path (no terminal required):

1. Student buys Hostinger + runs Hostinger's one-click WordPress install
2. Downloads plugin ZIP from GitHub Releases
3. WP Admin → Plugins → Add New → Upload Plugin → install → activate
4. Settings → Mega Kadence Bridge → click "Copy as .env"
5. Pastes the .env block into their local project folder
6. Installs Claude Code CLI (one-time)
7. Opens VSCode, starts talking to Claude

**Do NOT add required SSH steps to the default path.** SSH is a fallback for Tier 2+ users who want automation.

### Legal / Redistribution

- **Do NOT bundle Kadence Pro or Kadence Blocks Pro into the plugin.** The user has a lifetime license but redistribution rights in Envato-style lifetime deals typically do not include commercial bundling. Until the user confirms otherwise with Kadence directly, the plugin ships as free-compatible only.
- Jon provides Pro plugins to community members as a 1-to-1 personal gift, outside the plugin's scope.
- Plugin code assumes Pro MAY be present and gracefully degrades when it isn't. Feature detection, not hard dependency.

### Design Standards (The MVF Bar)

These are the non-negotiable design defaults for every site Claude deploys.

**Layout:**
- Max content width: **1290px**
- Content edge padding: 1.5rem
- Section vertical margins: **5rem desktop / 3rem tablet / 2rem mobile**
- Hero padding: **60px top / 60px bottom** (tight, above-fold)
- Page layout default: **Normal** (never Fullwidth)

**Typography:**
- H1: 32px
- H2: 28px
- Body: 17px
- Line height: 1.6
- **Never tiny text. Err larger.**

**Colors:**
- 9-slot palette with light and dark mode presets
- Auto-brightening for brand colors in dark mode (if luminance < 0.4, shift toward HSL lightness 65%)
- Accent colors always readable (light on dark / dark on light)
- All text passes WCAG 2.1 AA contrast minimums

**Fonts (10 curated tone-based pairings):**

| Tone | Heading | Body |
|---|---|---|
| Bold & Rebellious | Anton | Inter |
| Warm & Friendly | Fraunces | Nunito |
| Premium & Refined | Playfair Display | Source Sans 3 |
| Playful & Quirky | Bricolage Grotesque | DM Sans |
| Technical & Expert | Space Grotesk | Inter |
| Earthy & Artisan | Cormorant Garamond | Lora |
| Urban & Street | Archivo Black | Archivo |
| Modern & Minimal | Manrope | Manrope |
| Vintage & Heritage | Abril Fatface | Libre Baskerville |
| Sporty & Energetic | Oswald | Roboto |

Claude auto-pairs based on the student's "tone" answer during onboarding, with escape hatch for manual selection.

**Header:**
- Logo left (275px max width per `logo_max_width` setting)
- Navigation right: About Us, Contact, Blog, Shop
- Cart icon far right (when WooCommerce is active)
- Mobile: logo + cart + hamburger (WCAG 44×44px tap target)
- Transparent header on Home/About/Contact. Solid on Shop/Product/Legal pages.

**Accessibility:**
- WCAG 2.1 AA minimum on every page
- Contrast ratio ≥ 4.5:1 for body text
- All images get alt text (Claude auto-generates from context if missing)
- Visible focus states on all interactive elements
- Semantic HTML, proper heading order (h1 → h2 → h3)
- Skip-to-content link in header

**Per-Page Defaults:**
- Page title: **disabled** by default (unless used as overlay on a hero image)
- Content vertical spacing: **disabled**
- Featured image: **disabled**

### The 7 Canonical Homepage Sections

Every deployed homepage MUST have these in this order:

1. **Hero** — Transparent header over full-width bg image with 0.45 dark overlay, centered white text (H1 tagline + H2 subheadline), primary CTA button. Min-height 60vh, padding 60px top/bottom.
2. **Featured Products** — 4 desktop / 3 tablet / **1 mobile**. Pulls from WC "featured" flag. If no products yet, shows 4 auto-created placeholder products (tee, hoodie, hat, tote) so the section isn't broken.
3. **Brand Story** — Two-column (50/50 desktop, stacked mobile). Image + AI-generated copy based on USP/niche/tone from onboarding.
4. **Trust Row** — Three info boxes (REPLACES traditional testimonials, which look broken with zero reviews on a new store):
   - **Satisfaction Guaranteed** — Icon: shield-check. Body: "Something wrong? Tell us. We'll make it right — every time."
   - **Fast, Free Shipping Over ${threshold}** — Icon: truck-fast. Body: "Orders over ${threshold} ship free. Tracked, reliable, to your door."
   - **Original Art, Original Designs** — Icon: sparkles. Body: "Every piece is designed by ${brand_name} — not resold, not templated."
   - Icon 80px, heading 22px, body 18px, all center-aligned. Variables auto-fill from brand vars + `cart_pop_free_shipping_price` theme_mod.
5. **Secondary CTA Band** — Full-width accent-color band with H2 + "Shop the Collection" button.
6. **Newsletter Placeholder (Phase 1)** — Info box: "Join the club for first access to drops and discounts — coming soon." **Phase 2** adds real FluentForms + reCAPTCHA.
7. **Footer** — Three-column: brand name + tagline / quick links (Shop, About, Blog, Contact, Privacy, Terms, Returns) / social icons. Dark bg if site is light mode and vice versa.

### Auto-Generated Legal Pages

4 pages, hardcoded starter copy with brand-name substitution:

1. Privacy Policy
2. Terms of Service
3. **Returns & Refunds** (required — user emphasized this)
4. Cookie Policy (optional based on jurisdiction)

Legal text is NOT AI-generated (liability). Template boilerplate with `{brand_name}`, `{email}`, `{year}` placeholders.

### Copy Strategy: Hybrid

- **Legal pages:** hardcoded boilerplate templates with variable substitution
- **Marketing pages:** AI-generated at deploy time, based on onboarding answers (name, niche, ICP, tone, USP)
- **Fallback:** generic starter copy if generation fails — never empty fields
- **Word limits:** Hero headline ≤ 7 words. Subheadline ≤ 18 words. Brand story paragraphs ≤ 60 words, max 3 paragraphs. Info box titles ≤ 4 words. Button text ≤ 3 words.

### Onboarding Questionnaire (13 questions, ~5 minutes)

Claude asks these one at a time when a student runs Claude in a fresh project. Conversational, not a web form.

**Brand Identity:**
1. Brand name
2. Tagline
3. Primary color (hex, name, or "pick for me based on niche")
4. Secondary color
5. Logo (file path, URL, or "I'll add it later")

**Business Foundation:**
6. Niche specifics ("vintage motocross-inspired streetwear" not "apparel")
7. ICP (ideal customer)
8. Tone of voice (pick from the 10 font-pairing tones)
9. What you sell (main 2-3 product categories)

**Contact & Trust:**
10. Business email
11. Physical location (optional)
12. Social handles (optional)

**Promise / USP:**
13. Why you vs competitors (one sentence — becomes homepage subheadline + About Us hook)

**Plus question 14:** Light or Dark mode?

### Image Set (Phase D — not yet built)

**12 stock images × 2 modes (light + dark) = 24 total images.**

Breakdown:
- Homepage hero (1)
- Homepage brand story (1)
- Homepage secondary CTA background (1)
- About page hero overlay (1)
- About page founder/story (1)
- About page mission/values (1)
- Contact page hero (1)
- Contact page body (1)
- 4 placeholder products (tee, hoodie, hat, tote) (4)

**Source:** Option A — generate with Flux 2 Pro (`black-forest-labs/flux-2-pro` on Replicate), host on Cloudinary (`mega-kadence-bridge/stock-images/` folder), compress via Tinify. Credentials are in `/home/userjjai/kadence-skill/.env` (gitignored). Cost: ~$1 in Replicate credits for 24 images.

### Technical

- **`.env` format:** bash-style (`KEY=value`), not JSON or YAML.
- **No SQLite.** Ring-buffer history in `wp_options` is sufficient. YAGNI.
- **One skill, not 200 tiny skills.** The Kadence Skill is a single skill containing an indexed markdown SOP library. Progressive disclosure via YAML front matter in the index + individual files loaded on demand.
- **Directory structure is flat** (no numeric prefixes like `01-customizer/`). Descriptive names only.
- **Bundle the Anthropic frontend-design skill** from https://github.com/anthropics/skills alongside our skill. It's the "what good design looks like" layer; ours is the "how to do it in Kadence" layer.

---

## WHAT'S BEEN BUILT

### Phase B — Kadence Settings Parser ✅ COMPLETE

**Location:** `/home/userjjai/kadence-skill/scripts/` (in the parent working directory, NOT in the plugin repo)

**Purpose:** Machine-verified inventory of every setting in Kadence (theme + Pro + blocks + blocks Pro). This is the foundation for the SOP system. Without it, we would be writing SOPs from memory and guessing at keys/defaults.

**Files:**
```
scripts/
├── README.md                    How to run, what it does
├── package.json                 npm deps (just php-parser)
├── parse-kadence.mjs            Main orchestrator
└── lib/
    ├── ast-utils.mjs            PHP AST extraction helpers
    ├── parse-customizer.mjs     theme_mod extractor
    ├── parse-post-meta.mjs      post_meta extractor
    └── parse-blocks.mjs         block.json reader
```

**How to run:**
```bash
cd /home/userjjai/kadence-skill/scripts
npm install     # first time only
npm run parse
```

**Output:**
```
references/kadence-sop/_generated/
├── settings-catalog.json        ~2 MB — complete machine-readable inventory
├── settings-summary.md          Human-readable reference organized by section
└── stats.json                   Counts + metadata
```

**Numbers it produced (against Kadence 1.4.3 / Blocks 3.6.6 / Pro 1.1.16 / Blocks Pro 2.8.9):**

| Category | Count |
|---|---|
| Theme mods — free Kadence theme | **1,327** |
| Theme mods — Kadence Pro theme | **398** |
| **Total theme mods** | **1,725** |
| Post meta keys | 11 |
| Free Kadence blocks | 35 |
| Pro Kadence blocks | 29 |
| **Total blocks** | **64** |
| Total block attributes | **3,557** |
| Parse errors | **0** |
| Source files coverage | **100%** |

**The real Kadence surface area is 1,725 theme_mods — far more than earlier estimates (641). This is why the SOP project is a real body of work.**

**Parser robustness:** Handles three PHP patterns Kadence uses to register settings:

1. Direct inline: `Theme_Customizer::add_settings(array('key' => ...))`
2. Named variable: `$settings = array(...); Theme_Customizer::add_settings($settings);`
3. Custom variable name: `$kadence_post_settings = array(...); Theme_Customizer::add_settings($kadence_post_settings);`

Also handles:
- PHP `namespace` declarations
- `esc_html__('Label', 'kadence')` i18n wrappers (unwraps to just the string)
- `kadence()->default('key')` dynamic helpers (marks as `<dynamic:default(key)>`)
- Enum extraction from `input_attrs.layout` (pulls `choices: [...]`)
- Mixed-type array literals

**When Kadence releases a new version:** Replace the version folders in `kadence-plugins/`, re-run `npm run parse`, diff the new catalog against the old one to see what changed.

### Phase C — Mega Kadence Bridge Plugin v1.0.0 ✅ COMPLETE + RELEASED

**Location:** `/home/userjjai/kadence-skill/bridge/mega-kadence-bridge/` (this repo)

**GitHub:** https://github.com/jonjonesai/mega-kadence-bridge

**Release:** https://github.com/jonjonesai/mega-kadence-bridge/releases/tag/v1.0.0

**Direct ZIP download:** https://github.com/jonjonesai/mega-kadence-bridge/releases/download/v1.0.0/mega-kadence-bridge.zip

**Stats:** 15 PHP files, ~3,069 lines of code, 50+ REST endpoints. The released ZIP is 195.9 KB (435.7 KB uncompressed, 149 files including the bundled plugin-update-checker library).

**File structure:**

```
mega-kadence-bridge/
├── mega-kadence-bridge.php          Plugin header + bootstrap
├── README.md                         GitHub-facing docs
├── readme.txt                        WordPress plugin-directory format
├── LICENSE                           GPL v2+
├── .gitignore                        Git exclusions
├── .distignore                       Release ZIP exclusions
├── uninstall.php                     Clean removal (user + credentials)
├── .github/workflows/
│   └── release.yml                   Auto-build + publish ZIP on tag push
├── assets/
│   ├── admin.css                     Settings page styling
│   └── admin.js                      Copy-to-clipboard logic
├── docs/
│   └── DEVLOG.md                     This file
└── includes/
    ├── class-plugin.php              Singleton bootstrap
    ├── class-activator.php           Creates claude-bot + app password + creds file
    ├── class-deactivator.php         Rewrite flush
    ├── class-admin-page.php          Settings → Mega Kadence Bridge UI
    ├── class-rest-controller.php     REST route registration + shared helpers
    ├── class-history.php             Snapshot ring buffer (last 50 changes)
    └── endpoints/
        ├── class-core-endpoints.php         /info, /render, /cache/flush, /plugins, /wp-eval
        ├── class-theme-endpoints.php        /theme-mod, /option, /palette, /css, /settings
        ├── class-content-endpoints.php      /posts, /pages/ensure, /posts/find, /menus
        ├── class-media-endpoints.php        /media, /media/upload-from-url
        ├── class-kadence-endpoints.php      /blocks, /kadence-pro/config, /kadence-pro/preset/pod
        ├── class-woo-endpoints.php          /woo/* (registered only when WC is active)
        └── class-history-endpoints.php      /history, /rollback/{id}
```

**REST endpoints — full list:**

Core:
- `GET /info` — site, theme, plugin, PHP versions
- `GET /render?url=...` — cache-bypassed HTML
- `POST /cache/flush` — clear all cache layers
- `GET /plugins` — list installed plugins
- `POST /wp-eval` — execute PHP (audit-logged, guarded)

Theme:
- `GET|POST /theme-mod/{key}` — single theme_mod
- `POST /theme-mods/batch` — multiple theme_mods
- `GET|POST /option/{key}` — WP option
- `GET|POST /palette` — Kadence global palette
- `GET|POST /css` — site-wide custom CSS
- `GET /settings` — all Kadence-prefixed theme_mods
- `GET /settings/all` — every theme_mod

Content:
- `GET /posts` — list posts with filters
- `GET|POST /posts/{id}` — single post read/update
- `POST /posts/create` — create post
- `GET /posts/find?slug=...` — idempotency helper
- `POST /pages/ensure` — create-or-return
- `GET /menus` — list nav menus
- `POST /menus/create` — new menu
- `POST /menus/{id}/items` — add menu item

Media:
- `GET /media` — list media library
- `POST /media/upload-from-url` — sideload remote image

Kadence:
- `GET /blocks` — list all registered Kadence blocks
- `GET|POST /kadence-pro/config` — Pro feature flags
- `POST /kadence-pro/preset/pod` — enable POD-recommended Pro modules in one call
- `GET /header` — header config snapshot
- `GET /footer` — footer config snapshot

WooCommerce (only when WC is active):
- `GET /woo/status`
- `GET|POST /woo/settings`
- `GET /woo/products`, `GET|POST /woo/products/{id}`, `POST /woo/products/create`
- `GET /woo/categories`, `POST /woo/categories/create`
- `GET /woo/orders`

History:
- `GET /history` — list snapshots
- `GET /history/{id}` — specific snapshot
- `POST /rollback/{id}` — revert a change

**Activation behavior:**

On activation, the plugin:

1. Creates user `claude-bot` with administrator role, friendly bio, random email
2. Generates Application Password via `WP_Application_Passwords::create_new_application_password()`
3. Captures the plain-text password at creation time (it's only returned once)
4. Stores it in `wp_options['mkb_credentials']` for the Settings page to display
5. Writes it to `wp-content/.claude-bridge/credentials.json` with:
   - `.htaccess` `Deny from all` (Apache/LiteSpeed)
   - Empty `index.php` fallback
   - File permissions 0600
6. Sets `wp_options['mkb_activation_completed']` timestamp
7. Flushes rewrite rules

**Settings page flow:**

1. User navigates to Settings → Mega Kadence Bridge
2. Sees the `.env` block with `BRIDGE_URL`, `BRIDGE_USER=claude-bot`, `BRIDGE_PASS`, `BRIDGE_SITE`
3. Clicks "Copy as .env" button (clipboard API, with fallback)
4. Sees system status table (WordPress version, Kadence detected, WC detected, etc.)
5. Has a "Regenerate Credentials" button for rotation
6. Links to GitHub repo and docs

**Snapshot/rollback:**

Every write operation records a snapshot to `wp_options['mkb_history']` as a ring buffer (last 50 entries). Structure:

```json
{
  "id": "mkb_<timestamp>_<random>",
  "timestamp": "2026-04-11 10:30:00",
  "operation": "theme_mod_set",
  "target": "header_main_layout",
  "previous": "standard",
  "new": "fullwidth",
  "context": { ... }
}
```

Supported rollback operations: `theme_mod_set`, `option_set`, `palette_set`, `css_set`, `post_update`, `kadence_pro_config_set`, `kadence_pro_preset_pod`.

Unsupported (returns 400): `wp_eval`, `theme_mods_batch_set`, `media_upload`, `product_create`, etc. These require manual restoration.

**Release workflow (GitHub Actions):**

Triggered by pushing a tag matching `v*.*.*`. Steps:

1. Checkout code
2. Verify plugin header version matches tag
3. Download `plugin-update-checker` v5.6 from YahnisElsts
4. Build a clean ZIP with `rsync` (respecting `.distignore`)
5. Publish GitHub Release with the ZIP attached

**The library is NOT committed to source control.** It's downloaded fresh at release time so we can bump versions cleanly.

### Phase A — SOPs ⬜ NOT STARTED

The comprehensive Kadence reference. Total scope: ~200 items, ~270K words. Broken into three tranches:

**Tranche 1 — POD Critical (45 items)** — Without these, Claude can't deploy a POD store. Listed in detail in `SOP-INVENTORY.md`.

**Tranche 2 — Common Customization (75 items)** — "Student launched, now wants to tweak things" phase.

**Tranche 3 — Advanced / Edge Cases (80+ items)** — Deep expertise for comprehensive mastery.

**Format:** Markdown files with YAML front matter for progressive disclosure. Each file:
- Gets metadata (name, slug, category, scope, storage_type, meta_key, admin_location, pro_only, common_requests, related, tags, source_reference)
- Has sections: What It Does / Admin Location / Storage Details / Valid Values / How To Change Via Bridge API / How To Verify / Common Requests / Related / Gotchas / Full Workflow Example / Rollback
- Target 1,000–1,500 words per file
- Every fact verified against Kadence source code

**Sample SOP already written:** `/home/userjjai/kadence-skill/references/kadence-sop/02-page-settings/disable-page-title.md`

This is the template for every other SOP. Future Claude: read this file to understand the shape.

**Build order within Tranche 1:**

1. `deploy-pod-store.md` (the keystone recipe — references everything else)
2. WooCommerce settings (highest revenue impact)
3. Header/Footer customizer
4. Page-level settings (the gotchas)
5. Blocks (deepest technical reference)

**How to start:** After dogfooding on mega.management, read `SOP-INVENTORY.md` in the project root. Pick an item from Tranche 1. Read `_generated/settings-catalog.json` for that setting's actual key, default, section, choices. Write the SOP file using the sample as a template. Verify facts against the Kadence source in `kadence-plugins/`.

---

## KEY FILES & LOCATIONS

### On Disk (user's WSL2 Ubuntu environment)

```
/home/userjjai/kadence-skill/                       Parent project root
├── CLAUDE.md                                       Project guidance
├── SKILL.md                                        Skill instructions (early draft)
├── PREFLIGHT.md                                    Pre-deployment checklist
├── SOP-INVENTORY.md                                Master SOP list (~200 items)
├── CREDENTIALS.template.env                        Sample .env template
├── .env                                            Real API keys (GITIGNORED)
├── .env.template                                   Empty template
├── .gitignore                                      Root gitignore
├── kadence-research.md                             Original research file
├── bridge/
│   └── mega-kadence-bridge/                        ← THE PLUGIN REPO (this repo)
│       └── (full plugin source)
├── scripts/                                        Parser + build tools
│   └── (parser source)
├── references/
│   ├── kadence-blocks-reference.md
│   ├── hostinger-playbook.md
│   └── kadence-sop/
│       ├── _generated/                             Parser output
│       │   ├── settings-catalog.json
│       │   ├── settings-summary.md
│       │   └── stats.json
│       └── 02-page-settings/                       (will move to flat structure)
│           └── disable-page-title.md               ← Sample SOP template
├── kadence-blocks/                                 Free Kadence Blocks source (cloned from GitHub)
└── kadence-plugins/                                Vendored source for reference
    ├── kadence.1.4.3/                              Free Kadence theme source
    ├── kadence-pro.1.1.16/                         Pro theme addons
    ├── kadence-blocks/                             Free blocks (mirrors the other folder)
    └── kadence-blocks-pro.2.8.9/                   Pro blocks source
```

### On GitHub

- https://github.com/jonjonesai/mega-kadence-bridge — This repo. Plugin source + this dev log.
- https://github.com/jonjonesai/mega-kadence-bridge/releases/tag/v1.0.0 — First release.

### Reference repos (NOT to modify, only to study)

- https://github.com/jonjonesai/mega-stack-skill — Precursor stack skill from the same author
- https://github.com/jonjonesai/mega-agent-bridge — Precursor bridge plugin pattern (simpler than ours)
- https://github.com/jonjonesai/wordpress-kadence-skill — Early incomplete draft of the kadence skill
- https://github.com/stellarwp/kadence-blocks — Free Kadence Blocks official source
- https://github.com/YahnisElsts/plugin-update-checker — Used for GitHub-based plugin updates
- https://github.com/anthropics/skills — Anthropic's skills repo (contains `frontend-design` skill we bundle alongside ours)

---

## CREDENTIALS & ENVIRONMENT

### Dogfood target: mega.management

- **SSH:** `ssh -p 65002 u616193506@77.37.88.129`
- **IP:** 77.37.88.129
- **Port:** 65002 (Hostinger standard)
- **User:** u616193506
- **SSH key:** ed25519, labeled `broshark-mega`. The public key is in Hostinger's authorized_keys already. The private key lives on the user's local machine.
- **WordPress root:** Will be at `/home/u616193506/domains/mega.management/public_html` (standard Hostinger layout)
- **WP-CLI:** `/usr/local/bin/wp`

**Status as of 2026-04-11 (during this session):** Mega.management is being set up. The user is configuring Google Workspace (for branded Gmail), Google Cloud Console project (OAuth for MEGA Retail), and Stripe. Plugin install on mega.management is expected within a few hours of this session ending.

### API keys (in `/home/userjjai/kadence-skill/.env`, gitignored)

- **REPLICATE_API_TOKEN** — for image generation (Flux 2 Pro model)
- **CLOUDINARY_CLOUD_NAME / API_KEY / API_SECRET** — for hosting generated images
- **TINIFY_API_KEY** — for image compression
- **FIRECRAWL_API_KEY** — optional, for web scraping during planning

These are for the Phase D image asset generation step. Students never need them — they're build-time only.

### Git config

```
user.name:  jonjonesai
user.email: contact@jonjones.ai
```

Already set globally. Commits will use this identity.

---

## TERMINOLOGY GLOSSARY

| Term | Meaning |
|---|---|
| **SOP** | Standard Operating Procedure — a documented recipe for one Kadence task |
| **Recipe** | Task-oriented SOP ("how do I hide the page title") |
| **Settings SOP** | Setting-oriented SOP ("what does page_title do") |
| **Tranche** | A batch of SOPs prioritized by value (1 = critical, 2 = common, 3 = advanced) |
| **Trust Row** | The three-info-box USP band replacing traditional testimonials |
| **claude-bot** | The dedicated WordPress user the bridge creates |
| **MVF** | Minimum Viable Fashion (user's term) — the design bar for what counts as a "good" deployed site |
| **Golden Rule** | Non-negotiable operational practice (cache purge, verify, etc.) |
| **Dogfood** | Test our own product on a real site (mega.management) |
| **Tier 1** | Zero-tech-skill students — the primary audience |
| **POD** | Print on demand |
| **Bridge** | The plugin that exposes the REST API |
| **Skill** | The Claude Code skill package containing SKILL.md + SOP index |
| **Progressive disclosure** | Loading only the SOP files needed for a specific task rather than all 200 |

---

## SESSION 1 CHRONOLOGY (2026-04-11)

Rough log of what was built in order, for context:

1. Explored existing `kadence-research.md` research file
2. Cloned Kadence Blocks source from GitHub
3. Fetched + studied reference repos (mega-stack-skill, mega-agent-bridge, wordpress-kadence-skill)
4. User uploaded Kadence Pro + Kadence Blocks Pro source to `kadence-plugins/`
5. Extensive planning phase — user provided full MEGA POD context (4 apps, 45 products, Hostinger, community, "weekend business" framing, tiers, voice-dictated future)
6. Agreed on build order: Phase B (parser) → Phase C (bridge) → Phase A (SOPs). Reverses naive order because dogfooding reveals what SOPs are actually needed.
7. Built parser in Node.js using `php-parser` npm package. 3 revisions to handle Kadence's three different registration patterns. Final: **1,725 theme_mods from 100% of source files, zero errors.**
8. Discovered "Flux 2 Pro" doesn't exist as a Replicate slug — then user corrected me with direct link showing it does. Lesson: verify URLs directly, don't trust listing pages.
9. Extensive design spec conversation — user articulated concerns about Claude's past tendency to create bloated layouts, tiny text, full-width templates, and unreadable accents. Locked in tight defaults (60/60 hero, 1290px max width, 32px H1, 17px body, normal layout, WCAG 2.1 AA).
10. User introduced the "Trust Row" concept to replace testimonials (which look broken for brand new stores with zero reviews). Locked in 3 items: Satisfaction Guaranteed / Fast Free Shipping / Original Art.
11. Found Anthropic's `frontend-design` skill at https://github.com/anthropics/skills — designed to break Claude out of "AI slop" patterns. Planned to bundle alongside our skill.
12. Wrote sample SOP file (`disable-page-title.md`) as the template for the 199 others. Includes YAML front matter for progressive disclosure, complete workflow example, rollback instructions, and source-verified gotchas.
13. User pushed back on SOP scope estimate (30+20 was too low). Re-scoped to ~200 items (~270K words) in three tranches. Produced full inventory as `SOP-INVENTORY.md`.
14. User confirmed bridge plugin build (Phase C): regular WordPress plugin (not mu-plugin), claude-bot user + Application Password auth, Settings page with Copy-as-.env button, GitHub-based auto-updates via plugin-update-checker.
15. Built the plugin: 15 PHP files, ~3,069 LOC, 50+ REST endpoints across 7 category classes. All files pass php-parser syntax validation (since PHP isn't installed locally, used the existing npm php-parser dep).
16. Initialized git repo in `bridge/mega-kadence-bridge/`. First commit authored as `jonjonesai <contact@jonjones.ai>` from the user's global git config.
17. User authorized push. Pushed to https://github.com/jonjonesai/mega-kadence-bridge.
18. Tagged v1.0.0 and pushed tag.
19. GitHub Actions release workflow ran successfully: downloaded plugin-update-checker v5.6, built ZIP, published release at https://github.com/jonjonesai/mega-kadence-bridge/releases/tag/v1.0.0.
20. Downloaded the released ZIP and verified contents. 195.9 KB, 149 files including the bundled update-checker library, correct top-level folder structure.
21. User asked for this dev log.

---

## WHAT'S NEXT

### Immediately After This Dev Log

**Dogfood on mega.management** (awaiting user to finish setting up Google Workspace + Cloud Console + Stripe configuration, expected within a few hours).

Steps:
1. Download v1.0.0 ZIP from GitHub Releases
2. WP Admin → Plugins → Add New → Upload Plugin → activate
3. Check: claude-bot user was created
4. Check: Settings → Mega Kadence Bridge shows credentials
5. Check: `wp-content/.claude-bridge/credentials.json` exists and is protected
6. Test: hit `/info` with Basic Auth and see JSON response
7. Test: hit `/settings` and verify theme_mods are returned
8. Test: write a theme_mod, flush cache, render, verify

**Likely issues to watch:**

1. **LiteSpeed cache purge.** On Hostinger, LiteSpeed may not be installed as a plugin (it's the server itself). Our `/cache/flush` endpoint handles both cases but might need a fallback that hits LiteSpeed's server-level purge endpoint directly.
2. **HTTP Basic Auth header stripping.** Some Apache/LiteSpeed configs strip the `Authorization` header before it reaches PHP. If the bridge returns 401s on valid credentials, the fix is to add `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` to `.htaccess`. If we hit this, patch the activator to write this to `.htaccess` automatically.
3. **File permissions on `wp-content/.claude-bridge/`.** The activator writes with 0600. If Hostinger's user/group setup prevents this, fall back to writing with WP's `wp_mkdir_p` + default perms.

**If any issue hits:** Patch the plugin → bump to v1.0.1 → re-tag → re-release. Workflow handles it all.

### After Dogfooding

**Phase A — Tranche 1 SOPs.**

Start with `deploy-pod-store.md` (the keystone recipe that orchestrates everything). Read `SOP-INVENTORY.md` for the full list. Use `disable-page-title.md` as the template. Reference `_generated/settings-catalog.json` for facts.

Target: 5-10 SOPs per session. Tranche 1 takes ~5-9 sessions. User has said they're not in a rush — "comprehensive perfection" is the goal, not speed.

### After Tranche 1

Validation loop: deploy the real POD scenarios through Claude + the SOPs. Every failure or follow-up question → a new SOP. Iterate until Claude can deploy a POD site end-to-end with zero failures.

Then Tranche 2. Then Tranche 3. Approximately 20-40 SOP-writing sessions total, spread across 2-4 weeks.

### After All Tranches

Public launch. Package the skill + plugin + docs for the MEGA community. Produce install videos. Iterate based on student feedback.

---

## IMPORTANT GOTCHAS & LEARNINGS FROM SESSION 1

These are the things I had to discover the hard way. Future Claude: don't re-discover these.

### 1. Kadence's Three Customizer Registration Patterns

Kadence customizer options files use three different PHP patterns for registering settings. If you're writing a parser or analysis tool, handle ALL THREE:

```php
// Pattern A — direct inline
Theme_Customizer::add_settings(array(
    'key' => array('control_type' => '...', 'section' => '...', ...),
));

// Pattern B — named variable
$settings = array(
    'key' => array('control_type' => '...', 'section' => '...', ...),
);
Theme_Customizer::add_settings($settings);

// Pattern C — custom variable name (several files use this)
$kadence_post_settings = array(
    'key' => array('control_type' => '...', 'section' => '...', ...),
);
Theme_Customizer::add_settings($kadence_post_settings);
```

My original parser only handled B. Adding A brought it from 58/81 → 77/81 files. Handling C with dynamic variable resolution got it to 81/81.

### 2. The Real Kadence Surface Area is 1,725 Settings

Earlier estimates said 641. The real number is **1,725 theme_mods** (1,327 free + 398 Pro). This is why the SOP project is significant work. Don't underestimate the scope.

### 3. `wp theme mod set` Corrupts Kadence Values

Kadence stores many theme_mods as PHP arrays or objects. WP-CLI's `theme mod set` serializes values as JSON strings, which break Kadence's reads. Always use:

- Bridge API `/theme-mod/{key}` endpoint, OR
- `wp eval "set_theme_mod('key', ['array', 'here']);"`, OR
- Direct `set_theme_mod()` call from PHP

Never `wp theme mod set`.

### 4. `_kad_post_title` is a String Enum, Not a Boolean

The per-page "Display Title" setting is stored as post_meta `_kad_post_title` with these values:

- `''` (empty string) — inherit from global `page_title` theme_mod
- `'default'` — explicit default
- `'normal'` — force show, in-content layout
- `'above'` — force show, hero overlay layout
- `'hide'` — force hide

Sending `false`, `0`, or `null` does NOT work. Always send one of the string values.

### 5. Application Password Plaintext is Only Returned Once

`WP_Application_Passwords::create_new_application_password()` returns `[$plain_password, $item_data]`. The plain password is only accessible at creation time — after that, WordPress only stores the hash. The activator must capture the plain text at creation time and store it for the Settings page to display.

### 6. `wp-content/.claude-bridge/` Needs Triple Protection

We're storing credentials in a web-accessible directory. Protection:

1. `.htaccess` with `Deny from all` (Apache/LiteSpeed)
2. Empty `index.php` fallback (for servers that ignore .htaccess)
3. File permissions `0600` on the credentials file itself

All three, not just one.

### 7. `kadence()->default('key')` is Resolved at Runtime

Most Kadence defaults are set via `kadence()->default('key')`, a dynamic helper that reads from a registered defaults array. You can't statically resolve these. In the parser, we mark them as `<dynamic:default(key)>`. In SOPs, we say "default is resolved dynamically — to see the actual runtime value, query `/theme-mod/{key}` on a fresh install."

### 8. Hostinger Uses LiteSpeed Server, Not Just Plugin

Hostinger's default hosting is LiteSpeed at the server level. The LiteSpeed Cache plugin is separate. Our `/cache/flush` endpoint handles both the plugin (`LiteSpeed_Cache_API` class) and the action hook (`litespeed_purge_all`). If neither works, we may need a direct curl-based purge to LiteSpeed's server endpoint.

### 9. The Frontend Design Skill Exists — Use It

Anthropic's `frontend-design` skill at https://github.com/anthropics/skills/tree/main/skills/frontend-design is specifically designed to break Claude out of "AI slop" patterns (tiny text, generic fonts, muted accents). Bundle it alongside the Kadence skill. It provides the "what good design looks like" layer; ours provides the "how to do it in Kadence" layer.

### 10. SSH Port 65002, Not 22

Hostinger uses port 65002 for SSH. Standard port 22 is blocked. Every SSH command needs `-p 65002`.

---

## THE TONE / WRITING STYLE FOR THIS PROJECT

Notes for future Claude on how to match the voice the user and I established during session 1:

- **Declarative and direct.** "This is the #1 fix for X." Not "You might want to try..."
- **No hedging.** "Always", "never", "must" — not "usually", "probably", "might".
- **Technical but not dry.** Include reasoning for why gotchas exist, not just what they are.
- **Short paragraphs.** Dense information, plain sentences.
- **No filler.** Go straight to the point. Skip preamble.
- **No emoji unless explicitly requested.** The user did not ask for them and does not want AI-slop aesthetic tells.
- **Tables over bullet lists** when the data has structure.
- **Code blocks for every shell command.** Copy-pasteable, nothing hand-wavy.
- **Always finish with a clear next-step question** so the user knows what to decide next.

### The Voice For Student-Facing Content

For the Settings page, welcome notices, error messages, and any text students will read: friendlier, less terse. User-facing copy should feel warm, inviting, and confidence-building. "Your personal Kadence wizard..." is the vibe. This is different from the dev-facing voice. Do not conflate them.

---

## OPEN QUESTIONS WAITING ON USER INPUT

As of session 1 end, these are the decisions the user still owes:

1. **When to push the dev log to GitHub.** Push on completion (default) or wait for review? (User asked for it pushed, so default is push.)
2. **Whether to start a separate `jonjonesai/kadence-skill` repo** for the parent project (research, parser, SOPs) or keep it local until later. Current decision: local. Can revisit.
3. **Exact Cloudinary folder path** for the stock image set when Phase D happens. Default: `mega-kadence-bridge/stock-images/`. Confirmed in `.env`.
4. **Whether to add Kadence Conversions + Kadence Shop Kit to the vendored source.** User owns them. If added, the plugin should gracefully integrate. Not blocking.
5. **What to do about Lindua icon set.** User owns a licensed set from icomoon.io and wants Claude to know about it. For now, use Kadence's native icon set (IonIcons); revisit when we have the Lindua assets.
6. **Whether to remove the `wp-eval` endpoint for the public release.** It's a security smell but useful for debugging. Currently: included but audit-logged.
7. **Staging vs production decision for mega.management.** User said no staging needed (high risk tolerance, Hostinger backups exist). Install directly on mega.management.

---

## HOW TO RESUME THIS PROJECT (CHECKLIST FOR FUTURE CLAUDE)

If you are a new Claude session picking this up:

- [ ] Read this entire dev log (you just did)
- [ ] Read `/home/userjjai/kadence-skill/CLAUDE.md`
- [ ] Read `/home/userjjai/kadence-skill/SOP-INVENTORY.md`
- [ ] Read `/home/userjjai/kadence-skill/references/kadence-sop/02-page-settings/disable-page-title.md` (the SOP template)
- [ ] Run `cd /home/userjjai/kadence-skill/scripts && npm run parse` to regenerate the settings catalog
- [ ] Browse `_generated/settings-summary.md` to get a feel for the Kadence surface area
- [ ] Check git status in `/home/userjjai/kadence-skill/bridge/mega-kadence-bridge/`
- [ ] Verify the v1.0.0 release is still the latest at https://github.com/jonjonesai/mega-kadence-bridge/releases
- [ ] Ask the user: "Where do we pick up? Are we dogfooding on mega.management, or starting SOPs?"
- [ ] DO NOT make destructive changes without explicit permission

---

## SIGN-OFF

Session 1 closed on 2026-04-11 with:

- ✅ Complete settings catalog (1,725 theme_mods parsed, machine-verified)
- ✅ Mega Kadence Bridge v1.0.0 released to GitHub
- ✅ This dev log published
- ⬜ Dogfooding on mega.management pending
- ⬜ Tranche 1 SOPs (45 items) not started
- ⬜ Tranche 2 SOPs (75 items) not started
- ⬜ Tranche 3 SOPs (80+ items) not started

The foundation is in place. The building can now happen in parallel by any number of Claude sessions pointed at this dev log. **The hard part — deciding what to build and how — is done.** What remains is execution.

The mission, restated for the last time:

> **Build the world's foremost operator of Kadence.**
>
> Not a Claude that knows Kadence. The world's foremost operator. A Claude instance that wields Kadence with absolute, complete, indisputable expertise — every setting, every block, every gotcha, every workflow. When a user says the slightest thing about Kadence, Claude knows exactly what to do.

— End of Session 1 dev log.

---
---

# SESSION 2: First Deployment & Dogfooding (2026-04-12 → 2026-04-13)

**Continuation of Session 1 context** (same Claude instance, ~47% context used at session boundary).

## What Happened

### mega.management Stood Up

The user set up mega.management as the dogfood target:
- Fresh Hostinger account, one-click WordPress install
- Google Workspace configured for branded email
- Google Cloud Console project set up (OAuth for MEGA Retail)
- Stripe configuration in progress for payments

### Plugin Deployed to mega.management

Installed via WP Admin → Plugins → Upload Plugin → activate. Also installed:
- Kadence Theme (free, v1.4.5 — latest)
- Kadence Blocks (free, v3.6.7 — latest)
- WooCommerce (v10.6.2 — latest)
- Mega Kadence Bridge (v1.0.0 — our plugin)

### Three Issues Discovered During Dogfooding

#### Issue 1: Hostinger Bloatware Disables Application Passwords

**Severity:** Blocker
**Root cause:** Hostinger pre-installs 4 plugins (Hostinger AI, Hostinger Easy Onboarding, Hostinger Reach, Hostinger Tools). The **Hostinger Tools** plugin has a setting that **disables WordPress Application Passwords by default**. Since our bridge relies on Application Passwords for auth, the plugin activates and creates the `claude-bot` user, but the generated Application Password is unusable.

**How we discovered it:** Bridge returned 401 on every authenticated request. Even WordPress's native REST endpoint (`/wp/v2/users/me`) returned "rest_not_logged_in" with valid credentials. User found the toggle in hPanel → WordPress → Tools → "Disable application passwords" was ON.

**Fix applied:** User manually toggled "Disable application passwords" OFF in Hostinger Tools, then deactivated + reactivated Mega Kadence Bridge to regenerate the Application Password.

**Fix needed for v1.0.1:**
1. Detect if Application Passwords are disabled during activation → show a clear admin error with instructions
2. Consider programmatically re-enabling Application Passwords by removing whatever filter Hostinger Tools uses (investigate the filter name)
3. Add to install docs: "If you're on Hostinger, disable the 'Disable application passwords' setting in Hostinger Tools before installing Mega Kadence Bridge"
4. Add to student onboarding flow as a required step

**Hostinger bloatware inventory (for documentation):**

| Plugin | Needed? | Action |
|---|---|---|
| Hostinger AI (3.0.33) | No | Deactivate + Delete |
| Hostinger Easy Onboarding (2.1.15) | No | Deactivate + Delete |
| Hostinger Reach (1.4.5) | No | Deactivate + Delete |
| Hostinger Tools (3.0.62) | Keep for now | Has the app password toggle |
| LiteSpeed Cache (7.8.1) | Yes | Keep — essential for caching |

#### Issue 2: LiteSpeed Strips Authorization Header

**Severity:** Blocker
**Root cause:** Hostinger's LiteSpeed server strips the HTTP `Authorization` header before it reaches PHP. This is a known issue on many shared hosting providers that use CGI/FastCGI to run PHP.

**How we discovered it:** Even after fixing the Application Password toggle, auth still failed. Testing against WP's native REST API also failed, proving it was server-level, not plugin-level.

**Fixes attempted:**
1. `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` in `.htaccess` — **DID NOT WORK** on Hostinger's LiteSpeed
2. `CGIPassAuth On` in `.htaccess` — **WORKED** (LiteSpeed-native directive)

Both lines are now in `.htaccess` on mega.management (belt and suspenders). The fix that actually resolved it was `CGIPassAuth On`.

**Fix needed for v1.0.1:**
- Auto-write `CGIPassAuth On` to `.htaccess` during plugin activation using WordPress's `insert_with_markers()` function
- Check if the line already exists before adding
- This is standard practice (Wordfence, LiteSpeed Cache plugin, WP Super Cache all modify `.htaccess`)

**The `.htaccess` on mega.management now looks like:**
```apache
CGIPassAuth On
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
# BEGIN LSCACHE
...
```

#### Issue 3: /blocks Endpoint Returns 0 Due to Caching

**Severity:** Minor (self-resolving)
**Root cause:** The `/blocks` endpoint was first called BEFORE Kadence Blocks was installed. The REST API response was cached (by LiteSpeed or WP object cache). After installing Kadence Blocks and flushing the cache, the endpoint correctly returned 59 blocks.

**Fix:** Already handled — just needed a cache flush. No code change required. But this is a good reminder that `/cache/flush` should be called after installing any new plugin.

### Smoke Test Results — ALL PASS

After all three issues were resolved, full smoke test passed:

| Test | Endpoint | Result |
|---|---|---|
| Site info | `GET /info` | ✅ WP 6.9.4, PHP 8.3.30, Kadence 1.4.5, Bridge 1.0.0 |
| Theme settings | `GET /settings` | ✅ Accessible (4 on fresh install) |
| Page render | `GET /render?url=/` | ✅ 200 OK, 45,861 chars, Kadence markup |
| Cache flush | `POST /cache/flush` | ✅ wp_object_cache, litespeed_hook, transients |
| Kadence blocks | `GET /blocks` | ✅ **59 blocks** registered |
| WooCommerce | `GET /woo/status` | ✅ WC 10.6.2, USD, active |
| Plugin list | `GET /plugins` | ✅ 8 plugins reporting correctly |
| PHP eval | `POST /wp-eval` | ✅ Executes and returns |

### Live Site Configuration (as of Session 2 end)

**mega.management current state:**
- WordPress 6.9.4, PHP 8.3.30
- Kadence Theme 1.4.5 (free, active)
- Kadence Blocks 3.6.7 (free, active) — 59 blocks registered
- WooCommerce 10.6.2 (active, setup wizard skipped)
- Mega Kadence Bridge 1.0.0 (active, all endpoints operational)
- LiteSpeed Cache 7.8.1 (active)
- Hostinger Tools 3.0.62 (active, app passwords now enabled)
- Hostinger AI, Easy Onboarding, Reach (deactivated by user, recommended for deletion)
- Kadence Pro: NOT installed yet (user has lifetime license, will add later)
- Kadence Blocks Pro: NOT installed yet
- No pages created yet (default WP "Hello World" post only)
- WooCommerce not configured (no products, no payment gateways)

**Bridge credentials for mega.management:**
```
BRIDGE_URL=https://mega.management/wp-json/mega-kadence-bridge/v1
BRIDGE_USER=claude-bot
BRIDGE_PASS=xuBOHA946XfwbxBRKJWd6MW3
BRIDGE_SITE=https://mega.management
```

**SSH access (unchanged from Session 1):**
```
ssh -p 65002 u616193506@77.37.88.129
```

**`.htaccess` modifications applied:**
- Line 1: `CGIPassAuth On`
- Line 2: `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`

## v1.0.1 Patch TODO

Based on dogfooding discoveries, v1.0.1 needs:

1. **Auto-detect disabled Application Passwords** → clear admin error during activation
2. **Auto-write `CGIPassAuth On` to `.htaccess`** during activation (using `insert_with_markers()`)
3. **Programmatically re-enable Application Passwords** if disabled by Hostinger Tools (investigate the filter)
4. **Update README** with Hostinger-specific instructions
5. **Update onboarding flow** to include "disable Hostinger bloatware" step

## What's Next

The bridge is fully operational on mega.management. The next step is:

**Option A (recommended by Claude): Run the 14-question onboarding questionnaire and build out mega.management** — full 7-section homepage, About page, Contact page, Shop configuration, all through the bridge. This is the real-world test of "the world's foremost operator of Kadence" doing its thing.

**The user confirmed Option A.** Building out mega.management is the next action.

## Session 2 Status

- ✅ Mega Kadence Bridge deployed to mega.management
- ✅ Three deployment issues discovered and resolved (app passwords, .htaccess, cache)
- ✅ All 8 smoke tests passing
- ✅ Bridge fully operational — Claude can read and write to mega.management
- ⬜ v1.0.1 patch (3 fixes from dogfooding) — queued
- ⬜ mega.management site buildout — NEXT
- ⬜ Tranche 1 SOPs — after buildout
- ⬜ Tranche 2 + 3 SOPs — future sessions

— End of Session 2 dev log.

---

# SESSION 2 ADDENDUM: Live Site Build + 11 Gotchas Discovered (2026-04-13)

## mega.management Homepage Built Through Bridge

Built a full 5-section dark-mode homepage for mega.management entirely through the bridge API:
- Hero section (headline + pain-point eliminator + 2 CTA buttons)
- Features row (6 AI Models / 45 Products / Auto-Published)
- How It Works (3-step process: Describe → Pick → Publish)
- CTA Band ("Eliminate Tedium. Dominate Your Niche.")
- Logo uploaded via Cloudinary → bridge sideload pipeline

**Brand identity applied:**
- Name: MEGA (Mass e-Commerce Product Engine)
- Palette: Molten Lava Orange (#FF5500) + Fire Spark Yellow (#FFB800) on Black Char (#0A0A0A)
- Font: Anton (headings) + Inter (body)
- Dark mode with edge-to-edge section backgrounds
- Logo: metallic chrome + fire PNG, 275px max width

## 11 Deployment Gotchas Discovered and Documented

Full reference: `/home/userjjai/kadence-skill/references/kadence-sop/recipes/dark-mode-site-deploy-gotchas.md`

1. `logo_layout` must be array, not string
2. Content background + boxed layout creates white border
3. Hero `minHeight` with `vh` creates massive gaps — use padding only
4. Sticky header defaults to white background — must override
5. Mobile nav text defaults to black — invisible on dark themes
6. Footer shows Kadence credit by default — must replace
7. White flash during scroll from wrapper element backgrounds
8. Unicode escapes render as literal text in block content
9. Homepage needs fullwidth layout + maxWidth on rows (1290px)
10. Block validation errors require "Attempt Recovery" in editor
11. Kadence palette stored as JSON string, not PHP array

**All 11 gotchas have bridge-level fixes documented with exact curl commands.**

**23-step dark mode deploy checklist created** — the definitive procedure for deploying a dark Kadence site through the bridge.

## Current mega.management State

- ✅ Homepage live with 5 sections, all rendering correctly
- ✅ Dark palette applied globally
- ✅ Logo showing in header with sticky shrink effect
- ✅ Sticky header stays dark on scroll
- ✅ Mobile nav visible (white on dark)
- ✅ Footer shows "© 2026 MEGA"
- ✅ No white flash during scroll
- ✅ Text contained at 1290px, backgrounds edge-to-edge
- ⬜ About page — not yet created
- ⬜ Contact page — not yet created
- ⬜ Shop configuration — not yet done
- ⬜ Blog setup — not yet done
- ⬜ Legal pages — not yet created

— End of Session 2 addendum.

---

# SESSION 2 FINAL: kbVersion Discovery + Site Complete (2026-04-14)

## Gotcha #12: kbVersion:2 (THE Most Critical Discovery)

**This is the single most important thing learned in the entire project.**

Every `kadence/rowlayout` and `kadence/column` block created via the REST API MUST include `"kbVersion":2` in its block comment attributes. Without it, Kadence's PHP renderer falls back to legacy output that generates bare `<div>` elements instead of the proper `kb-row-layout-wrap` + `kt-row-column-wrap` structure. ALL Kadence CSS (max-width, padding, grid, responsive) targets `.kt-row-column-wrap` — so without `kbVersion:2`, no styling applies. Pages render with no padding, no centering, text edge-to-edge.

**How it was discovered:** Homepage worked because the user had clicked "Attempt Recovery" in the editor (which re-saves blocks through Kadence's `save()` function, adding `kbVersion:2`). About and Contact pages were never opened in the editor, so they kept our raw versionless markup. Result: homepage looked great, About/Contact looked broken.

**The fix is one attribute:** `"kbVersion":2` in every row and column block comment.

**This must be in every SOP, every recipe, every template. Non-negotiable.**

## Spacing Standard Locked In

### The Rule of 80-60-40
- **80px** — first section top padding below header ("the header gap")
- **60px** — all other section padding top/bottom ("the section gap")
- **40px** — horizontal padding + mobile vertical ("the breathing room")

### Element Spacing
- H1: margin 0 top, 20px bottom
- H2: 10px top, 20px bottom
- H3: 10px top, 15px bottom
- Body paragraph: 0 top, 25px bottom
- CTA button: 30px top margin

## Additional Fixes Applied
- Removed "Pricing" from primary nav (re-added later — MEGA does need it, but standard student deploys don't)
- Fixed About page first section: 80px top padding for header breathing room
- Fixed About page H3 headings: added top margin for breathing from preceding content
- Fixed Contact page first section: same 80px treatment
- Added `kbVersion:2` to ALL rows and columns on About + Contact pages

## Total Gotcha Count: 12

The full list (updated from 11):
1. `logo_layout` must be array, not string
2. Content background + boxed layout creates white border
3. Hero `minHeight` with `vh` creates massive gaps
4. Sticky header defaults to white background
5. Mobile nav text defaults to black
6. Footer shows Kadence credit by default
7. White flash during scroll from wrapper backgrounds
8. Unicode escapes render as literal text
9. Homepage needs fullwidth + maxWidth on rows
10. Block validation "Attempt Recovery" in editor
11. Kadence palette stored as JSON string
12. **kbVersion:2 required on every block created via API** (THE critical one)

## mega.management Final State

All pages built and rendering correctly:
- ✅ Home (5 sections, dark mode, responsive, logo, sticky header)
- ✅ About (hero + story + 3 values + CTA)
- ✅ Contact (hero + two-column info + form placeholder)
- ✅ Blog (posts page configured)
- ✅ Shop (WooCommerce configured, 4-column grid)
- ✅ Privacy Policy (published)
- ✅ Terms of Service (created)
- ✅ Returns & Refunds (created)
- ✅ Cart/Checkout/My Account (WooCommerce auto-created)

Nav: About, Blog, Contact, Shop
Footer: © 2026 MEGA. All rights reserved.

## What's Next

1. **Footer links** — Add Privacy, Terms, Returns links to footer
2. **fluffy.love** — POD site test (will validate and further refine the deploy process)
3. **SOPs** — Write Tranche 1 (45 items) in fresh context sessions. The kbVersion:2 discovery and all 12 gotchas become permanent SOP entries.
4. **v1.0.1 patch** — Auto-fix .htaccess, detect disabled app passwords, fix bridge palette endpoint to use JSON string format

## For Future Claude: Critical Rules Summary

When creating ANY Kadence block via the bridge:
1. Always include `"kbVersion":2` in rowlayout AND column blocks
2. Always use actual UTF-8 characters, never `\uXXXX` escapes
3. Always set `maxWidth:1290` on fullwidth page rows
4. Always use padding for spacing, never minHeight with vh
5. First section: 80px top padding. All others: 60px.
6. Store palette as JSON string with `"active":"palette"` field
7. Flush cache after every write operation
8. Set all wrapper/header/footer backgrounds explicitly for dark mode

— End of Session 2 final entry.

---
---

# SESSION 3: Mega Kadence Skill v1.0 — Built, Tested, Shipped (2026-05-01)

**Author of record:** Jon Jones (jonjonesai)
**Primary Claude session:** Opus 4.6 (1M context), 2026-05-01

## What Happened

Built and shipped the **mega-kadence-skill** repo — Phase A from this DEVLOG. The skill is now a standalone public repo at https://github.com/jonjonesai/mega-kadence-skill containing 22 files that teach Claude how to deploy a fully branded POD store end-to-end.

### Build Brief

Jon provided `mega-skill-build-brief.md` — a self-contained handoff document specifying the target repo structure, what to port from the precursor `mega-stack-skill`, what to rewrite for the MKB v1.0.0 API surface, and what to construct fresh. The brief also specified 5 acceptance criteria and required live testing on cutemerch.love before commit.

### Precursor Ported

Cloned `mega-stack-skill` from GitHub. Ported:
- SKILL.md change workflow (READ → PLAN → CHANGE → PURGE → VERIFY → REPORT)
- STUDENT-INTAKE.md 6-question wizard
- `mega-homepage.php` block markup patterns (converted from PHP eval-file to bridge API calls)
- `set-palette.php` palette system (converted to bridge `/palette` endpoint)
- Hostinger gotchas table

All API calls rewritten from old `mega-bridge/v1/` namespace + `X-Mega-Bridge-Key` auth to `mega-kadence-bridge/v1/` + HTTP Basic Auth.

### Files Shipped

```
mega-kadence-skill/
├── SKILL.md                        Main skill (what Claude loads)
├── INTAKE.md                       6-question student intake wizard
├── deploy-pod-store.md             End-to-end 16-step orchestrator
├── README.md                       Public-facing intro + install
├── LICENSE                         GPL v2+
├── .gitignore
├── recipes/
│   ├── deploy-homepage.md          6-section homepage builder
│   ├── deploy-about.md             About page builder
│   ├── deploy-contact.md           Contact page with Fluent Forms
│   ├── deploy-legal-pages.md       Privacy, Terms, Returns
│   ├── set-palette.md              9-slot palette, light/dark mode
│   ├── set-fonts-by-tone.md        10 tone-based font pairings
│   ├── build-nav-menus.md          Header + footer + nav + logo
│   └── verify-deployment.md        Render-and-grep verification
├── boilerplate/
│   ├── privacy-policy.md
│   ├── terms-of-service.md
│   ├── returns-and-refunds.md
│   └── cookie-policy.md
└── references/
    ├── mkb-api-reference.md        MKB v1.0.2 endpoint cheat sheet
    ├── kadence-block-patterns.md   Block markup + save() rules
    ├── tone-font-pairings.md       10 tone → font matrix
    └── hostinger-gotchas.md        Hosting-specific fixes
```

### Bridge v1.0.2 Released

One-line fix: `set_palette()` in `class-theme-endpoints.php` was passing a PHP array to `update_option()`. Kadence's `normalize_palette_option` filter calls `json_decode()` on the value, expecting a string. Fix: `wp_json_encode($palette)` before storing.

Tagged v1.0.2, pushed to GitHub. GitHub Actions built the release ZIP. Updated cutemerch.love via SCP.

### cutemerch.love Deployed

Full end-to-end deployment tested on cutemerch.love (production site). Brand: CuteMerch, niche: cute animal illustrations, light mode, #FF1493 hot pink, Fraunces/Nunito fonts.

**Final site state:**

| Element | Details |
|---|---|
| Header | Placeholder logo (450x150 PNG) left, nav right (About / Contact / Shop), transparent on Home/About/Contact, solid on Shop/Legal, sticky on scroll |
| Homepage | 6 sections: Hero → Trust Row (3 cols) → Brand Story (2-col) → Featured Products (Kadence productcarousel, 4 placeholder products) → CTA Band → Newsletter (Fluent Forms) |
| About | Hero → Brand Story (2-col) → 3 Values → CTA |
| Contact | Hero → 2-col (info + Fluent Forms contact form) → CTA |
| Legal | Privacy Policy, Terms of Service, Returns & Refunds |
| Footer | 2-column: brand + tagline + copyright (left), legal nav links in accent color (right) |
| Mobile | popup-toggle hamburger (pink bg, white icon), white drawer with dark nav text |
| Products | 4 placeholders: "Sample Tee/Hoodie/Mug/Tote -- Replace with MEGA" |
| Block validity | 0 "Attempt Block Recovery" warnings on all pages |

## Gotchas Discovered (Session 3) — Total: 21

Adding to the 12 from Session 2:

### 13. `/palette` endpoint must wp_json_encode() before storing
Kadence's `normalize_palette_option` filter on `kadence_global_palette` calls `json_decode()`. The bridge was passing a PHP array directly to `update_option()`, causing a TypeError crash. Fixed in bridge v1.0.2.

### 14. `/pages/ensure` doesn't publish existing draft pages
WordPress auto-creates a Privacy Policy page as a draft. `/pages/ensure` finds it by slug and returns it without updating status. Always follow `/pages/ensure` with `POST /posts/{id}` setting `status: publish`.

### 15. Product create field names differ from WC REST API
Bridge uses `name` (not `title`), `categories` as flat array `[16]` (not `[{"id":16}]`), and doesn't support `featured` flag. Set featured via `POST /posts/{id}` with `meta: {"_featured":"yes"}` after creation.

### 16. `header_desktop_items` keys need row prefixes
Kadence checks `$elements[$row][$row . '_' . $column]` — so `main_left`, `main_right`, NOT `left`, `right`. Using bare direction names renders an empty header.

### 17. `footer_items` keys need row prefixes
Same pattern: `middle_1`, `middle_2`, `bottom_1`, NOT `1`, `2`, `3`. Using bare numbers renders an empty footer.

### 18. `logo_layout` is an object, not an array
Must be `{"include":{"desktop":"logo"},"layout":{"desktop":"standard"}}`. Setting `["logo_only"]` silently fails — logo area renders with an empty `<a>` tag, no image.

### 19. Mobile header component names differ from desktop
The hamburger trigger is `popup-toggle` (NOT `mobile-trigger`). The mobile logo is `mobile-logo` (NOT `logo`). The popup nav is `mobile-navigation` (NOT `navigation`). These map directly to template filenames in `template-parts/header/`.

### 20. `kadence/infobox` save() is too complex to reproduce from API
The save.js generates complex HTML with icon SVGs, RichText content, and specific class patterns. Reproducing this exactly from API-created content is fragile. Use `kadence/advancedheading` + `wp:paragraph` blocks inside columns instead — these are simpler to match and produce identical results.

### 21. `kadence/advancedheading` requires `data-kb-block` attribute
The save() output includes `data-kb-block="kb-adv-heading${uniqueID}"` on the HTML element. Missing this attribute causes block validation failure ("Attempt Block Recovery" prompt in editor). Column blocks require `class="wp-block-kadence-column kadence-column${uniqueID}"` — NOT `inner-column-1`.

## Key Architecture Decisions

### Block validity strategy
All Kadence blocks used in the skill fall into two categories:
- **Dynamic blocks** (PHP-rendered): `kadence/rowlayout`, `kadence/singlebtn`, `kadence/infobox`, `kadence/productcarousel`. Inner HTML is ignored by WordPress validation. Can use empty inner HTML: `<!-- /wp:kadence/singlebtn -->`.
- **Static blocks** (save.js validated): `kadence/column`, `kadence/advancedheading`, `kadence/advancedbtn`, `wp:paragraph`. Inner HTML MUST match save() output exactly.

The skill avoids `kadence/infobox` entirely for API-created content (gotcha #20) and uses `kadence/advancedheading` + `wp:paragraph` instead.

### Product display
Requires Kadence Blocks Pro. The `kadence/productcarousel` block is dynamic (PHP-rendered, no block recovery issues) and supports featured product filtering. The WooCommerce core `woocommerce/product-collection` block also works but has more complex markup requirements.

### Forms
Uses Fluent Forms (free plugin). Two forms created during deploy: Subscription Form (ID varies per site) for the homepage newsletter row, Contact Form for the contact page. Embedded via `<!-- wp:shortcode -->[fluentform id="X"]<!-- /wp:shortcode -->`.

## Plugin Requirements (Updated)

| Plugin | Required | Notes |
|---|---|---|
| Kadence Theme (free) | Yes | Base theme |
| Kadence Theme Pro | Recommended | Transparent header, header/footer builder features |
| Kadence Blocks (free) | Yes | Core blocks: rowlayout, column, advancedheading, advancedbtn, singlebtn |
| Kadence Blocks Pro | Yes | `kadence/productcarousel` for Featured Products section |
| WooCommerce | Yes | Products, shop page, cart |
| Mega Kadence Bridge | Yes | v1.0.2+ (palette fix) |
| Fluent Forms | Yes | Newsletter + contact forms |
| LiteSpeed Cache | Yes | Cache management on Hostinger |

## For Future Claude: Updated Critical Rules

When creating ANY page via the bridge:

1. Always include `"kbVersion":2` in rowlayout AND column blocks
2. Always use actual UTF-8 characters, never `\uXXXX` escapes
3. Always set `maxWidth:1290` on fullwidth page rows
4. Always use padding arrays for spacing: `"padding":["80","60","60","60"]`
5. First section: 80px top padding. All others: 60px.
6. Store palette as JSON string via `/palette` endpoint (bridge v1.0.2+ handles encoding)
7. Flush cache after every write operation
8. Column class MUST be `kadence-column${uniqueID}` — no `inner-column-N`
9. Headings MUST include `data-kb-block="kb-adv-heading${uniqueID}"`
10. Header items: `main_left`, `main_right` — NOT `left`, `right`
11. Footer items: `middle_1`, `middle_2` — NOT `1`, `2`
12. Logo layout: `{"include":{"desktop":"logo"},"layout":{"desktop":"standard"}}` — NOT array
13. Mobile trigger: `popup-toggle` — NOT `mobile-trigger`
14. Mobile logo: `mobile-logo` — NOT `logo`
15. Mobile popup nav: `mobile-navigation` — NOT `navigation`
16. Never use `kadence/infobox` from API — use heading + paragraph blocks
17. Always `POST /posts/{id}` with `status:publish` after `/pages/ensure` (draft pages)
18. Products: `name` not `title`, flat category array, set `_featured` via separate meta call

## Session 3 Status

- ✅ Mega Kadence Skill v1.0 shipped to https://github.com/jonjonesai/mega-kadence-skill
- ✅ Bridge v1.0.2 shipped (palette fix)
- ✅ cutemerch.love fully deployed — 7 pages, header, footer, products, forms
- ✅ All 5 acceptance criteria pass
- ✅ Zero block recovery warnings
- ⬜ Reproducibility test (wipe + rebuild from skill) — NEXT
- ⬜ Update skill files in mega-kadence-skill repo with session 3 discoveries
- ⬜ Tranche 2/3 SOPs — future sessions

## What's Next

**Reproducibility test:** Wipe cutemerch.love content (pages, products, categories, menus, theme mods). Then have a fresh Claude instance read the skill and deploy the same site. If the result matches, the skill is proven. If it doesn't, the gap becomes the next fix.

After that: course filming on cutemerch.love, then student rollout.

— End of Session 3 dev log.
