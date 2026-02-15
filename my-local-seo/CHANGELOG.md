# My Local SEO — Changelog

## 4.15.0 — 2026-02-15

### Schema → Person
- **NEW: LinkedIn Import** — AI-powered profile extraction from pasted LinkedIn content.
  - Paste visible text (Ctrl+A → Ctrl+C from the profile page) for quick import.
  - Advanced toggle: paste raw HTML page source for richer structured data extraction (JSON-LD, OG tags, noscript content).
  - AI extracts: name, job title, bio, education, credentials, expertise topics (with Wikidata/Wikipedia linking), memberships, awards, languages, and social profile URLs.
  - Target selector to populate any existing person card.
  - Uses the plugin's existing OpenAI integration — no additional API keys needed.
  - New AJAX endpoint: `wp_ajax_myls_person_import_linkedin` (file: `inc/ajax/ai-person-linkedin.php`).
- **NEW: Person Label** — display-only label field at the top of each person accordion (e.g. "Owner", "Dr. Smith").
  - Live-updates the accordion header as you type.
  - Stored in database but NOT included in schema output.
  - Accordion header now shows: Label (primary) + "Full Name · Job Title · X page(s)" (meta line).
- **NEW: Export to Fillable PDF** — generates a branded, fillable PDF form from any person profile.
  - Client-side generation via pdf-lib (lazy-loaded from CDN on first click).
  - Fillable text fields for all identity, bio, social, and URL fields.
  - Fillable checkbox for schema enabled/disabled status.
  - Composite sections (expertise, credentials, education, memberships) render as multi-column fillable grids (3 rows each).
  - Repeater sections (sameAs, awards, languages) render as numbered fillable rows (5 rows each).
  - Multi-line fillable textarea for bio/description.
  - Branded purple header bar with profile label badge.
  - Footer on every page with plugin name, generation date, and page numbers.
  - Page assignments excluded from PDF output.
  - Pre-populates with current form data — empty fields remain fillable blanks.
  - Downloads as `person-profile-{name}.pdf`.

### Internal
- Added `inc/ajax/ai-person-linkedin.php` — AJAX handler with HTML content extraction and AI-powered structured parsing.
- `myls_linkedin_extract_from_html()` — extracts OG tags, meta description, JSON-LD, noscript content, and visible text from pasted LinkedIn HTML.
- `myls_linkedin_sanitize_profile()` — sanitizes all AI-returned fields with WordPress sanitization functions.
- PDF export logic inlined in the Person subtab `<script>` block for reliable loading (no external JS dependency).
- Version bumped to 4.15.0 across plugin header and MYLS_VERSION constant.

---

## 4.12.0 — 2026-02-14

### Schema → Person
- **NEW: Person Schema Subtab** — full multi-person support with per-person page assignment.
  - Multi-person accordion UI with add/clone/remove functionality.
  - Per-person fields: name, job title, honorific prefix, bio, email, phone, photo, profile URL.
  - Social profiles (sameAs) repeater — LinkedIn, Facebook, X/Twitter, YouTube, Wikipedia, Wikidata, Crunchbase.
  - Areas of Expertise (knowsAbout) — composite repeater with Wikidata Q-ID and Wikipedia URL linking for AI citation.
  - Credentials & Licenses (hasCredential) — composite repeater with credential name, abbreviation, issuing org, issuer URL.
  - Education (alumniOf) — composite repeater with institution name and URL.
  - Memberships (memberOf) — composite repeater with organization name and URL.
  - Awards and Languages simple repeaters.
  - Per-person page assignment with checkbox list (pages, posts, services, service areas).
  - Per-person enable/disable toggle with visual Active/Disabled badge in accordion header.
  - Stored as `myls_person_profiles` option array.
  - JSON-LD output on assigned pages via schema graph.
  - worksFor automatically linked to Organization schema.
  - Pro Tips sidebar with E-E-A-T best practices.

---

## 4.6.32
- **CRITICAL FIX**: Increased default max_tokens from 1200 to 4000 - was causing FAQ generator to only produce 1-2 FAQs instead of the intended 10-15
- Fixed: Added context-specific token handling in OpenAI integration (`myls_openai_complete`) for 'faqs_generate' context with 4000 token fallback
- Improved: Added helpful UI guidance about token requirements (4000+ for LONG variant, 2500+ for SHORT variant)
- Improved: Better system prompt for FAQ generation context: "You are an expert local SEO copywriter. Generate clean, structured HTML for FAQ content."

## 4.6.31
- Fix: AI FAQ Generator now properly replaces `{{CITY_STATE}}` and `{{CONTACT_URL}}` template variables in prompts.
- Fix: City/state detection improved with multiple fallback meta keys (`_myls_city`, `city`, `_city`, `_myls_state`, `state`, `_state`).
- Fix: Temperature default now consistently uses 0.5 from saved options instead of hardcoded 0.4.
- Fix: Added `<ol>` tag support for ordered lists in generated FAQ HTML output.
- Improvement: Added filter hook `myls_ai_faqs_city_state` for custom city/state detection logic.

## 4.6.25
- Fix: Video ItemList JSON-LD now validates cleanly in schema.org by wrapping `ItemList` inside a `CollectionPage` and moving `publisher` + `dateModified` onto the page entity (where those properties are valid).

## 4.6.24
- Fix: Removed a legacy Utilities migration stub file that echoed HTML at include-time, which could bleed into the admin header area.

## 4.6.23
- Fix: YouTube Channel List per-page ItemList schema now reliably outputs `uploadDate` for each VideoObject.
- Improvement: Normalizes helper date keys (`date`/`publishedAt`/`uploadDate`) and adds safe fallbacks (local post meta → YouTube API cached → WP post date).

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
