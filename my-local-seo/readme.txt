=== My Local SEO ===
Contributors: davebarry
Tags: local seo, schema, ai, faq, utilities, person schema, linkedin
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 5.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

My Local SEO is a modular local SEO toolkit with schema, AI tools, bulk operations, and shortcode utilities.

== Description ==

This plugin provides a modular admin toolkit for local SEO workflows including schema generation, AI content tools, bulk operations, and shortcode utilities.

**Key Features:**
* Person Schema with E-E-A-T optimization (multi-person, Wikidata/Wikipedia expertise linking)
* LinkedIn Import — AI-powered profile extraction from pasted content
* Fillable PDF export for person profiles
* Organization & LocalBusiness schema with awards and certifications
* AI-powered content generation (meta descriptions, excerpts, FAQs, about areas, geo content)
* /llms.txt for AI discovery
* FAQ accordion with schema markup
* Google Maps integration for service areas
* Divi Builder module support

== Installation ==

1. Upload the `my-local-seo` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Upgrade Notice ==

= 4.15.0 =
Major Person Schema update: LinkedIn AI Import (paste profile content for auto-extraction), fillable PDF export, person labels with live accordion headers. Requires OpenAI API key for LinkedIn import feature.

= 4.12.0 =
New Person Schema subtab with full multi-person support, E-E-A-T optimization, Wikidata/Wikipedia expertise linking, credentials, education, memberships, and per-person page assignment.

= 4.6.32 =
CRITICAL FIX: Increases default max_tokens from 1200 to 4000 so AI FAQ Generator produces full 10-15 FAQs instead of stopping after 1-2. Also adds context-specific token handling.

= 4.6.31 =
Fixes AI FAQ Generator to properly follow prompt templates with {{CITY_STATE}} and {{CONTACT_URL}} variables, ensures variant defaults are respected, and improves city/state detection from post meta.

= 4.6.19 =
Fixes a Utilities → FAQ Quick Editor scoping bug that could crash some installs, and ensures the AI → FAQs Builder LONG variant uses the new multi-block prompt (auto-migrates legacy one-line templates).

= 4.6.18 =
Fixes Utilities → FAQ Quick Editor batch save deletion flag handling.

= 4.6.17 =
Fixes MYLS delete-auto for AI-inserted FAQs by matching hash normalization to the insert routine (whitespace/nbsp handling).

= 4.6.16 =
Fixes AI → FAQs Builder insertion/deletion for the new multi-block FAQ HTML format and fixes the editor “Delete on save” checkbox behavior.

= 4.6.15 =
AI → FAQs Builder now defaults to a longer, AI Overview-friendly FAQ format (multi-block HTML per FAQ: h3 + paragraphs + bullets + “Helpful next step”). Adds LONG/SHORT variant support.

= 4.6.13 =
Adds Awards support on Organization/LocalBusiness schema output.


= 4.6.12 =
Adds Schema → About Us subtab with About page selector and optional overrides to output valid AboutPage schema.

= 4.6.11 =
Adds a search box on AI → FAQs Builder to quickly filter the loaded posts list by title or ID.

= 4.6.10 =
Fixes AI → FAQs Builder batch processing so each selected post is generated and auto-inserted into MYLS FAQs, with per-post appended preview + improved logging.

= 4.6.8 =
Updates the Admin Bar “SEO Stuff” menu: adds Schema.org validator and improves Google index check (live status + site: results link).

= 4.6.7 =
Enhances /llms.txt with a Business details section (Organization/LocalBusiness), and upgrades FAQ links to use stable anchors (#faq-1, #faq-2, ...).

= 4.6.6 =
Expands /llms.txt with Primary Services, Service Areas, and a master FAQ list, plus Utilities toggles to control output.

= 4.6.5 =
Adds a basic /llms.txt endpoint (served at the site root) so LLMs and AI tools can discover key site links.

= 4.6.4 =
Fixes Divi Builder preview showing raw script contents as visible text (front-end output unchanged).

= 4.6.3 =
Restores the Divi FAQ Accordion module so FAQs render correctly inside the Divi Builder (Visual Builder + backend).

= 4.6.2 =
Adds a Docs → Release Notes tab and introduces upgrade notice + changelog tracking. If your host blocks plugin-file writes, release note entries will be queued inside WP instead.

= 4.6.1 =
FAQ Quick Editor now supports multi-post batch save and WYSIWYG answers.

= 4.6.0 =
Utilities now includes the FAQ Quick Editor and reorganized FAQ migration tools.

== Changelog ==

= 4.15.0 =
* NEW: LinkedIn Import — paste profile content (text or HTML source), AI extracts structured person data
* NEW: Person Label — display-only label for each person accordion header (not in schema output)
* NEW: Fillable PDF Export — branded fillable form with text fields, checkboxes, multi-column grids
* PDF uses pdf-lib (client-side, CDN lazy-loaded) with proper form field appearances
* LinkedIn import supports both plain text paste and advanced HTML source paste
* AI extracts: name, title, bio, education, credentials, expertise (with Wikidata/Wikipedia), memberships, awards, languages
* Added inc/ajax/ai-person-linkedin.php AJAX endpoint
* Version bumped across plugin header and constants

= 4.12.0 =
* NEW: Person Schema subtab — full multi-person support with accordion UI
* Per-person: identity, bio, social profiles, expertise (knowsAbout with Wikidata), credentials, education, memberships, awards, languages
* Per-person page assignment and enable/disable toggle
* JSON-LD output on assigned pages, worksFor linked to Organization schema
* Pro Tips sidebar with E-E-A-T best practices

= 4.6.32 =
* CRITICAL FIX: Increased default max_tokens from 1200 to 4000 - was causing FAQ generator to only produce 1-2 FAQs instead of 10-15
* Fixed: Added context-specific token handling in OpenAI integration for 'faqs_generate' context
* Improved: Added helpful UI guidance about token requirements (4000+ for LONG, 2500+ for SHORT)
* Improved: Better system prompt for FAQ generation context

= 4.6.31 =
* Fixed: AI FAQ Generator now properly replaces {{CITY_STATE}} and {{CONTACT_URL}} template variables
* Fixed: City/state detection improved with multiple fallback meta keys (_myls_city, city, _city, etc.)
* Fixed: Temperature default now consistently uses 0.5 from saved options
* Fixed: Added <ol> tag support for ordered lists in generated HTML
* Improved: Added filter hook 'myls_ai_faqs_city_state' for custom city/state detection logic

= 4.6.15 =
* AI → FAQs Builder: Upgraded default FAQ prompt to produce longer, more complete homeowner answers.
* Adds LONG/SHORT variants, AI Overview-tuned structure, and subtle “helpful next step” phrasing.
* Adds {{VARIANT}} placeholder support and a Variant selector in the builder UI.

= 4.6.7 =
* /llms.txt: Added Business details section (Organization → LocalBusiness → site defaults)
* FAQs: Added stable anchors (#faq-1, #faq-2, ...) to the MYLS FAQ accordion output
* /llms.txt: Master FAQ list now links to page + stable anchor

= 4.6.6 =
* /llms.txt: Added Primary services (service CPT) and Service areas (service_area CPT)
* /llms.txt: Added master FAQ link list from MYLS FAQ post meta (_myls_faq_items)
* Utilities: New llms.txt subtab with enable switch, section toggles, and per-section limits

= 4.6.5 =
* Added first-pass support for serving /llms.txt at the site root (Markdown, plain text) via rewrite + template redirect

= 4.6.4 =
* Divi Builder: strip <script> tags in builder context to prevent raw script text from appearing in previews

= 4.6.3 =
* Restored Divi module: FAQ Schema Accordion (modules/divi/faq-accordion.php)
* Fixed module loader timing so it registers reliably in Divi Builder

= 4.6.2 =
* Added Docs → Release Notes tab
* Added optional release-notes append helper (queues when filesystem is not writable)
* Added Upgrade Notice section

= 4.6.1 =
* Added multi-post batch save for FAQ Quick Editor
* Answers use WYSIWYG editor
* Batch DOCX export for selected posts

= 4.6.0 =
* Added FAQ Quick Editor
* Added Utilities subtabs
* AI FAQ insert targets MYLS FAQ structure

