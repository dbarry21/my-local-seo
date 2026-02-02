/* =====================================================================
 * MYLS – FAQ Metabox JS
 * assets/js/myls-faq-metabox.js
 * ===================================================================== */

(function () {
  'use strict';

  function qs(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function qsa(sel, ctx) {
    return Array.from((ctx || document).querySelectorAll(sel));
  }

  function isRowEmpty(row) {
    const q = qs('.myls-faq-q', row);
    const del = qs('.myls-faq-del', row);
    if (!q || !del) return true;

    const qv = (q.value || '').trim();

    // Answer is a wp_editor textarea. Even if TinyMCE is active, WP keeps a textarea.
    const ta = qs('textarea[name^="myls_faq["]', row);
    const av = (ta && ta.value ? ta.value : '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

    return (qv === '' && av === '');
  }

  function uncheckDelete(row) {
    const del = qs('.myls-faq-del', row);
    if (del && del.checked) del.checked = false;
  }

  function wireRow(row) {
    if (!row || row.__mylsWired) return;
    row.__mylsWired = true;

    // If user starts typing anywhere in the row, uncheck delete.
    row.addEventListener('input', function () {
      if (!isRowEmpty(row)) uncheckDelete(row);
    });
    row.addEventListener('change', function () {
      if (!isRowEmpty(row)) uncheckDelete(row);
    });

    // TinyMCE doesn't always bubble input events. Poll lightly while the row is visible.
    let last = '';
    const tick = function () {
      if (!document.body.contains(row)) return;
      if (row.style.display === 'none') return;

      const ta = qs('textarea[name^="myls_faq["]', row);
      const now = ta ? String(ta.value || '') : '';
      if (now !== last) {
        last = now;
        if (!isRowEmpty(row)) uncheckDelete(row);
      }
      window.requestAnimationFrame(tick);
    };
    window.requestAnimationFrame(tick);
  }

  function revealNextBlankRow(root) {
    const blanks = qsa('.myls-faq-row-blank', root).filter(function (r) {
      return r.style.display === 'none' || getComputedStyle(r).display === 'none';
    });

    if (!blanks.length) {
      window.alert('No more blank rows are preloaded. Save the post and you will get more blanks.');
      return;
    }

    const row = blanks[0];
    row.style.display = '';
    wireRow(row);

    const q = qs('.myls-faq-q', row);
    if (q) {
      q.focus();
      q.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const root = qs('#myls-faq-metabox-root');
    if (!root) return;

    // Wire existing visible rows.
    qsa('.myls-faq-row', root).forEach(wireRow);

    const addBtn = qs('#myls-faq-add-row');
    if (addBtn) {
      addBtn.addEventListener('click', function (e) {
        e.preventDefault();
        revealNextBlankRow(root);
      });
    }
  });
})();
