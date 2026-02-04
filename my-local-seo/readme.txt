=== My Local SEO ===
Contributors: davebarry
Tags: local seo, schema, ai, faq, utilities
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 4.6.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

My Local SEO is a modular local SEO toolkit with schema, AI tools, bulk operations, and shortcode utilities.

== Description ==

This plugin provides a modular admin toolkit for local SEO workflows including schema generation, AI content tools, bulk operations, and shortcode utilities.

== Installation ==

1. Upload the `my-local-seo` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Upgrade Notice ==

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

