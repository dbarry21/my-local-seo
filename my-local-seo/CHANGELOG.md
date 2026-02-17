# My Local SEO — Changelog

## 5.0.0 — 2026-02-16

### New Feature: HTML Excerpt Editor & AI Generation
- **WYSIWYG metabox** for `html_excerpt` post meta on all public post types — full
  TinyMCE editor with Visual/Text tabs, trimmed toolbar (bold, italic, link, lists,
  headings), and live preview.
- **AI Generate button** in the editor metabox — pulls prompt template from plugin
  admin settings (`myls_ai_prompt_html_excerpt`), generates via OpenAI, and inserts
  into the WYSIWYG editor.
- **Admin AI tab → Excerpts subtab** now has 3-column layout:
  - Column 1: Select Posts (shared post selector)
  - Column 2: AI Actions (standard WP `post_excerpt` generation)
  - Column 3: HTML Excerpt Actions (new `html_excerpt` meta generation)
- Each column has its own prompt template, save/reset, and bulk generate buttons.
- New AJAX endpoints: `myls_ai_html_excerpt_generate_single`,
  `myls_ai_html_excerpt_save_prompt`, `myls_ai_html_excerpt_generate_bulk`.

### Updated: service_area_grid Shortcode
- **`show_page_title` attribute** (default: `1`) — renders the current page title as
  an H2 above the grid. Set `show_page_title="0"` to hide.
- **`show_title` fix** — boolean parsing now accepts `0/false/no` reliably (was strict
  `=== '1'` comparison that could fail depending on attribute format).
- Updated shortcode doc header with full attribute reference.

### Updated: service_grid Shortcode
- **Fixed duplicate tagline** — tagline was rendering both above and below the title.
  Removed the above-title `show_tagline` block; tagline now only appears below the
  title via the `subtext` logic. `show_tagline` attribute kept for backward compat.

### Updated: Shortcode Documentation
- Comprehensive shortcode-data.php rewrite covering all 30+ shortcodes across 6
  categories with full attributes, examples, and tips.
- New shortcodes documented: `association_memberships`, `service_faq_page`,
  `service_area_roots_children`, `divi_child_posts`, `custom_service_cards`,
  `myls_card_grid`/`myls_flip_grid`, `channel_list`, `gmb_hours`, `county_name`,
  `acf_field`, `page_title`, `with_transcript`.
- **Interactive Shortcodes tab redesigned** — single-column accordion layout with
  persistent search, category pills, inline copy buttons, and collapsible detail
  sections. Replaces the previous 4-column card grid that was difficult to scan.

## 4.15.8 — 2026-02-16

### New Feature: Association Memberships
Manage professional association memberships (BBB, Chamber of Commerce, trade groups, etc.)
from the plugin admin and display them on the front end with valid structured data.

#### Admin UI (Schema → Organization)
- **Memberships repeater** — add/remove association entries with fields:
  Name (required), Association URL, Your Profile URL, Logo URL, Member Since year, Description.
- **Generate Memberships Page** card — creates/updates a WordPress page with the
  `[association_memberships]` shortcode. Configurable title, slug, and status.
- Data saved to `myls_org_memberships` option.

#### Schema
- **`memberOf` on Organization** — each membership is output as an `Organization` object
  in the `memberOf` array on the existing Organization schema node.
- **`memberOf` on LocalBusiness** — same `memberOf` array is injected into LocalBusiness
  schema via new `myls_lb_build_member_of()` helper.
- **Dedicated Memberships Page provider** (`inc/schema/providers/memberships-page.php`) —
  if the memberships page is not already assigned to Organization schema, outputs a
  lightweight Organization node with `memberOf` in the `@graph`.
- **LocalBusiness auto-assigned** to the generated page.

#### Shortcode: `[association_memberships]`
- Responsive logo grid card layout (2/3/4 columns, mobile-responsive).
- Each card shows: logo (linked), association name, "Member since" badge, description,
  and "View Our Profile" button linking to your profile on their site.
- Attributes: `title`, `columns`, `show_desc`, `show_since`, `link_text`, `card_bg`, `card_border`.
- H1 defaults to current post title (same pattern as `[service_faq_page]`).

#### Content Best Practices for Search & AI
- Descriptions explain what each membership *means* (not just the org name).
- Profile URLs create verifiable two-way link relationships.
- Logos with proper alt text for image search visibility.
- `memberOf` structured data feeds Google Knowledge Graph and AI systems
  (Gemini, ChatGPT, Perplexity) for entity credibility verification.

### Files
- **NEW:** `modules/shortcodes/association-memberships.php`
- **NEW:** `inc/ajax/generate-memberships-page.php`
- **NEW:** `inc/schema/providers/memberships-page.php`
- **Changed:** `admin/tabs/schema/subtab-organization.php` — memberships section + page generator
- **Changed:** `inc/schema/providers/organization.php` — `memberOf` injection
- **Changed:** `inc/schema/providers/localbusiness.php` — `memberOf` injection + helper
- **Changed:** `my-local-seo.php` — new includes

## 4.15.7 — 2026-02-16

### Shortcodes
- **`[service_faq_page]`** — H1 title now defaults to the current page/post title instead of
  a hardcoded "Service FAQs". Whatever you set as the Page Title in the admin card becomes both
  the WP post title and the H1 on the page. You can still override with `title="Custom Text"`
  or hide with `title=""`.

## 4.15.6 — 2026-02-16

### Bug Fix
- **FIX: AI FAQ Generator "Permission Denied" in post editor** — The AI FAQ Generator metabox
  was sending the `myls_ai_faq_gen` nonce to the generate endpoint (`myls_ai_faqs_generate_v1`),
  but that endpoint validates against `myls_ai_ops` via the shared `myls_ai_check_nonce()` helper.
  The metabox now creates and sends the correct `myls_ai_ops` nonce for the generate call, while
  continuing to use `myls_ai_faq_gen` for the save and clear endpoints (which verify it directly).
- File changed: `inc/metaboxes/ai-faq-generator.php`.

## 4.15.5 — 2026-02-16

### Schema → FAQ (Critical Fix)
- **FIX: FAQPage JSON-LD now outputs correctly** — Previous versions attempted to hook `wp_head`
  from inside the shortcode, but shortcodes execute during `the_content` (after `wp_head` has
  already fired), so schema was never output. Replaced with a dedicated schema provider at
  `inc/schema/providers/service-faq-page.php` that hooks `myls_schema_graph` and runs during
  `wp_head` via `registry.php` (priority 90).
- **FAQPage schema validates** — outputs `@type: FAQPage` with `@id`, `url`, `name`, and
  `mainEntity` array of `Question`/`Answer` pairs. All FAQ items are deduplicated (case-insensitive).
  Validates at schema.org and Google Rich Results Test.
- **LocalBusiness schema auto-assigned** — the generated Service FAQ Page is automatically assigned
  to LocalBusiness location #1 (via `_myls_lb_assigned` / `_myls_lb_loc_index` post meta), so
  both FAQPage and LocalBusiness JSON-LD appear in `<head>` on the same page.
- **No duplicate schema** — `providers/faq.php` guard skips the Service FAQ Page (provider handles
  its own FAQPage node); shortcode no longer attempts schema output.
- Shortcode `schema` attribute removed (no longer needed; provider handles it).

### Files
- **NEW:** `inc/schema/providers/service-faq-page.php` — dedicated FAQPage schema provider.
- **Changed:** `modules/shortcodes/service-faq-page.php` — HTML rendering only, schema removed.
- **Changed:** `inc/ajax/generate-service-faq-page.php` — auto-assigns LocalBusiness meta on page create/update.
- **Changed:** `my-local-seo.php` — includes new provider.

## 4.15.4 — 2026-02-16

### Schema → FAQ
- **FAQPage JSON-LD Schema** — the generated Service FAQ Page now outputs a valid `FAQPage` JSON-LD
  `<script type="application/ld+json">` block in `<head>` containing all aggregated, deduplicated FAQ items.
  Validates against Google's Rich Results Test / Schema.org spec. Includes `@context`, `@type`, `name`, `url`,
  and `mainEntity` array of `Question`/`Answer` pairs.
- **Deduplication** — duplicate questions across services are automatically removed (case-insensitive,
  first occurrence wins). Stats card now shows raw count, deduped count, and duplicates removed.
- **Page Slug field** — configurable slug/permalink for the generated page (default: `service-faqs`).
  Live preview of the full URL updates as you type.
- **Schema conflict guard** — `providers/faq.php` now explicitly skips the Service FAQ Page
  (shortcode handles its own FAQPage schema), preventing duplicate JSON-LD output.
- Admin card description updated to mention JSON-LD output and dedup behavior.
- AJAX response now returns `page_slug`, `dupes_removed` count, and updates the slug field with
  the actual saved slug (WordPress may sanitize/suffix it).

### Shortcodes
- **`[service_faq_page]`** — added `schema="1|0"` attribute to control JSON-LD output.
  Added `myls_collect_post_faqs()` and `myls_dedupe_faqs()` helper functions.

## 4.15.3 — 2026-02-16

### Schema → FAQ
- **NEW: Generate Service FAQ Page** — card added to the FAQ subtab under Schema.
  - Creates (or updates) a WordPress page that aggregates FAQs from all published Service posts.
  - Uses the dynamic `[service_faq_page]` shortcode — page always reflects current FAQ data without regeneration.
  - Configurable page title (default: "Service FAQs") and status (Published / Draft).
  - Shows live FAQ stats: total services, services with FAQs, total FAQ items.
  - View/Edit page links appear once the page exists.
  - AJAX-powered generation with spinner and success/error feedback.
  - New AJAX endpoint: `wp_ajax_myls_generate_service_faq_page` (file: `inc/ajax/generate-service-faq-page.php`).

### Shortcodes
- **NEW: `[service_faq_page]`** — renders all Service post FAQs on a single page.
  - H3 heading per service, Bootstrap 5 accordion for each service's FAQs.
  - Services ordered by menu_order (ASC) by default.
  - Shows "No FAQs available" message for services without FAQ items.
  - Supports per-instance color overrides: `btn_bg`, `btn_color`, `heading_color`.
  - Supports `orderby`, `order`, `show_empty`, `empty_message` attributes.
  - Reuses plugin's existing accordion CSS (`myls-accordion.min.css`).
  - Falls back to legacy ACF repeater fields when native MYLS FAQ meta is empty.
  - File: `modules/shortcodes/service-faq-page.php`.

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
