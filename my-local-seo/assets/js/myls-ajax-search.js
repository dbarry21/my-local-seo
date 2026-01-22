/**
 * MYLS – Universal AJAX Search
 *
 * Used by shortcode: [myls_ajax_search]
 *
 * Features:
 * - Live search while typing (debounced)
 * - Restricts search to post types provided via data attributes
 * - Supports multiple instances on the same page
 * - Gracefully hides results on blur / escape
 *
 * Requires localized object:
 *   MYLS_AJAX_SEARCH = {
 *     ajaxurl: "...",
 *     nonce: "..."
 *   }
 */

(function ($) {
  "use strict";

  /* ---------------------------------------------------------
   * Helpers
   * --------------------------------------------------------- */
  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function debounce(fn, wait) {
    let timeout;
    return function () {
      const context = this;
      const args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(function () {
        fn.apply(context, args);
      }, wait);
    };
  }

  function renderResults($box, items, showType) {
    if (!items || !items.length) {
      $box.hide().empty();
      return;
    }

    let html = "";

    items.forEach(function (item) {
      const title = escapeHtml(item.title);
      const url   = escapeHtml(item.url);
      const date  = escapeHtml(item.date || "");
      const type  = escapeHtml(item.type || "");
      const thumb = item.thumb ? escapeHtml(item.thumb) : "";

      html += '<a href="' + url + '" class="list-group-item list-group-item-action">';
      html += '  <div class="d-flex align-items-center gap-2">';

      if (thumb) {
        html += '    <img src="' + thumb + '" class="myls-ajax-search-thumb" alt="" loading="lazy">';
      }

      html += '    <div class="flex-grow-1">';
      html += '      <div class="myls-ajax-search-title">' + title + "</div>";
      html += '      <div class="small text-muted">';
      html += date;
      if (showType && type) {
        html += ' · ' + type;
      }
      html += "</div>";
      html += "    </div>";
      html += "  </div>";
      html += "</a>";
    });

    $box.html(html).show();
  }

  /* ---------------------------------------------------------
   * Init
   * --------------------------------------------------------- */
  $(document).ready(function () {

    if (typeof MYLS_AJAX_SEARCH === "undefined") {
      console.warn("MYLS_AJAX_SEARCH not found – AJAX search disabled.");
      return;
    }

    const transport = MYLS_AJAX_SEARCH;

    $(".myls-ajax-search").each(function () {

      const $wrap    = $(this);
      const $input   = $wrap.find(".myls-ajax-search-input");
      const $results = $wrap.find(".myls-ajax-search-results");

      const postTypes  = String($wrap.data("post-types") || "");
      const max        = parseInt($wrap.data("max") || 5, 10);
      const minChars   = parseInt($wrap.data("min-chars") || 2, 10);
      const debounceMs = parseInt($wrap.data("debounce-ms") || 200, 10);
      const showType   = String($wrap.data("show-type") || "0") === "1";

      const doSearch = debounce(function () {

        const term = ($input.val() || "").trim();

        if (term.length < minChars) {
          $results.hide().empty();
          return;
        }

        $.post(transport.ajaxurl, {
          action: "myls_ajax_search",
          nonce: transport.nonce,
          term: term,
          post_types: postTypes,
          max: max
        })
        .done(function (response) {
          if (!response || !response.success) {
            $results.hide().empty();
            return;
          }
          const items = response.data && response.data.items ? response.data.items : [];
          renderResults($results, items, showType);
        })
        .fail(function () {
          $results.hide().empty();
        });

      }, debounceMs);

      /* Events */
      $input.on("input", doSearch);

      $input.on("keydown", function (e) {
        if (e.key === "Escape") {
          $results.hide();
        }
      });

      // Click outside closes dropdown
      $(document).on("click", function (e) {
        if (!$.contains($wrap[0], e.target)) {
          $results.hide();
        }
      });
    });
  });

})(jQuery);
