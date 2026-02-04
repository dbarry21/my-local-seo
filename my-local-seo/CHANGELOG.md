## 4.6.20
- Fix: AI generation wrapper incorrectly called `myls_openai_complete()` directly (it's a filter callback), which could return the prompt unchanged and result in only 1 FAQ being inserted.
- Fix: Route AI requests through the `myls_ai_complete` filter so max_tokens/temperature/model settings are honored and LONG/SHORT variants generate properly.

## 4.6.19
- Fix: Utilities → FAQ Quick Editor file scoping bug that could fatally error on some installs (docx helper functions were accidentally wrapped inside the sanitize_items conditional).
- Fix: AI → FAQs Builder LONG variant now reliably uses the v2 multi-block prompt template (auto-migrates legacy one-line templates and auto-upgrades at generation time when LONG is selected).

## 4.6.18
- Fix Utilities → FAQ Quick Editor batch save deletion flag handling and JSON unslashing.

## 4.6.17 — 2026-02-04
- Fix: MYLS delete-auto now normalizes question/answer text exactly like the insert routine (whitespace + nbsp handling), so stored AI hashes match and rows actually delete.

## 4.6.16 — 2026-02-04
- Fix: FAQ editor metabox delete checkbox can now be intentionally checked (even on non-empty rows).
- Fix: AI → FAQs Builder now correctly parses the new multi-block FAQ output (h3 + paragraphs + bullets) and inserts into MYLS FAQs.
- Fix: Auto-delete of AI-inserted FAQs now uses the same hashing strategy as insert (requires the 4.6.17 normalization patch for whitespace edge cases).

## 4.6.15 — 2026-02-04
- AI → FAQs Builder: upgraded the default FAQ prompt to produce longer, more complete answers tuned for AI Overviews.
- Adds LONG/SHORT variants with a Variant selector, and supports the {{VARIANT}} placeholder in prompt templates.
- Answers now follow a multi-block structure per FAQ (h3 + paragraphs + bullets + “Helpful next step”) for better scannability and completeness.

## 4.6.14 — 2026-02-03
- Add Certifications list to Organization schema UI and output as hasCertification on Organization and LocalBusiness.
- Fix Organization provider to output award and hasCertification.

# My Local SEO — Changelog


## 4.6.13 — 2026-02-03

### Schema → Organization
- Added **Awards** list to the Organization tab.
- Outputs awards on **Organization** and **LocalBusiness** schema as Schema.org-valid `award` strings.


## 4.6.12 — 2026-02-03

### Schema → About Us
- Added a new **About Us** schema subtab under **Schema**.
- Includes an **About page selector** (outputs only on the selected page).
- Added optional overrides: **Headline/Name**, **Description**, **Primary Image URL**.
- Outputs **AboutPage + WebSite** JSON-LD via the schema graph (validates with Schema.org).


## 4.6.11 — 2026-02-03

### AI → FAQs Builder
- Added a **Search** input under the Post Type dropdown to filter the loaded posts list (client-side by title or ID).


## 4.6.10 — 2026-02-03

### AI → FAQs Builder
- Fixed version mismatch in plugin header/constants.
- Fixed batch workflow so **each selected post** can be processed in sequence with **auto-insert into MYLS FAQs**.
- Preview window now **appends** output per post and starts each section with the **post title + ID**.
- Added **Skip posts with existing MYLS FAQs** checkbox (pre-checks before generating to avoid wasting AI calls).


## 4.6.8 — 2026-02-03

### Admin Bar
- Updated **SEO Stuff** admin-bar menu: added **Schema.org Validator** link and improved **Google index check** (live status dot + Google site: results link).

## 4.6.7 — 2026-02-02

### AI Discovery
- Enhanced **/llms.txt** to be more comprehensive:
  - Added **Business details** section (prefers Organization settings, falls back to LocalBusiness location #1)
  - Master FAQ links now point to **page + stable anchor** (e.g. `#faq-3`)

### Shortcodes
- **FAQ Accordion**: Added stable per-question anchors (`#faq-1`, `#faq-2`, ...) so other systems can deep-link to specific questions.

### Utilities
- **Utilities → llms.txt**: Added toggle for including Business details.

## 4.6.6 — 2026-02-02

### AI Discovery
- Expanded **/llms.txt** with high-signal sections:
  - Primary services (from the `service` CPT)
  - Service areas (from the `service_area` CPT)
  - Master FAQ links (from MYLS FAQ post meta `_myls_faq_items`)

### Utilities
- Added **Utilities → llms.txt** settings subtab:
  - Enable/disable endpoint
  - Toggle sections (services, service areas, FAQs)
  - Per-section limits

## 4.6.5 — 2026-02-02

### AI Discovery
- Added first-pass support for serving **/llms.txt** at the site root (Markdown, plain text) via rewrite + template redirect.
- Includes basic, high-value links (Home, Contact if found, Sitemap, Robots) and exposes a filter (`myls_llms_txt_content`) for future expansion.

## 4.6.4 — 2026-01-29

### Divi
- Fixed Divi Builder preview showing raw `<script>` contents as visible text by stripping scripts **only in builder contexts** (front-end output unchanged).

## 4.6.3 — 2026-01-29

### Divi
- Restored Divi Builder module: **FAQ Schema Accordion** (`modules/divi/faq-accordion.php`).
- Updated loader so the module registers reliably in the Divi Builder (Visual Builder + backend).

## 4.6.2 — 2026-01-29

### Docs
- Added **Docs → Release Notes** tab (renders `CHANGELOG.md` inside the plugin).
- Added optional **Append Release Notes** form (writes to `CHANGELOG.md` when writable, otherwise queues entries in WP options).

### Updates
- Added `readme.txt` with **Upgrade Notice** section so update systems can display upgrade notes.

## 4.6.1 — 2026-01-29

### Utilities → FAQ Quick Editor
- Added true **batch save** for multi-selected posts (edit multiple posts’ FAQs on one screen, then save all at once).
- Switched Answer inputs to WordPress **WYSIWYG** (TinyMCE/Quicktags via `wp.editor.initialize`).
- Added batch `.docx` export for all selected posts (combined document).

## 4.6.0 — 2026-01-29

### Utilities
- Added Utilities subtabs (auto-discovered from `admin/tabs/utilities/subtab-*.php`).
- Moved the existing ACF → MYLS FAQ migration/cleanup screen into **Utilities → FAQ Migration**.
- Added **Utilities → FAQ Quick Editor**:
  - All public post types supported.
  - Post type selector + search filter + post multi-select.
  - MYLS FAQ repeater editor (native `_myls_faq_items`) with Add Row + Save.
  - Export current post’s FAQs to `.docx` (title + formatted Q/A list).

### AI → FAQs Builder
- Updated insert success UX to display a count (example: `14 items inserted`).

---

## 4.5.10
- Baseline version (uploaded working build).
