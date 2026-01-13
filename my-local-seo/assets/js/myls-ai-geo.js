/* ========================================================================
 * MYLS – AI GEO Subtab JS (v4)
 * File: assets/js/myls-ai-geo.js
 *
 * Fixes:
 * - Download buttons (DOCX/HTML) now enable properly after Analyze
 * - setBusy() no longer disables downloads when finishing
 * - Generates downloadable .html from GEO preview content (client-side)
 * - Always sends template + tokens + temperature (prevents missing_template)
 * ======================================================================== */

(function () {
  "use strict";

  if (!window.MYLS_AI_GEO) return;

  const CFG = window.MYLS_AI_GEO;

  // -----------------------------
  // DOM helpers
  // -----------------------------
  const $ = (sel) => document.querySelector(sel);

  const elPT = $("#myls_ai_geo_pt");
  const elPosts = $("#myls_ai_geo_posts");

  const btnSelectAll = $("#myls_ai_geo_select_all");
  const btnClear = $("#myls_ai_geo_clear");
  const btnAnalyze = $("#myls_ai_geo_analyze");
  const btnConvert = $("#myls_ai_geo_convert");
  const btnDuplicate = $("#myls_ai_geo_duplicate");
  const btnStop = $("#myls_ai_geo_stop");

  // Optional DOCX UI
  const btnDocx = $("#myls_ai_geo_docx");
  const elDocxLink = $("#myls_ai_geo_docx_link");

  // Optional HTML UI
  const btnHtml = $("#myls_ai_geo_html");
  const elHtmlLink = $("#myls_ai_geo_html_link");

  const elSpinner = $("#myls_ai_geo_spinner");
  const elStatus = $("#myls_ai_geo_status");
  const elCount = $("#myls_ai_geo_count");

  const elPreview = $("#myls_ai_geo_preview");
  const elDiff = $("#myls_ai_geo_diff");
  const elOutput = $("#myls_ai_geo_output");
  const elResults = $("#myls_ai_geo_results");
  const elLoadedHint = $("#myls_ai_geo_loaded_hint");

  let STOP = false;
  let processed = 0;

  // Keep latest payload so download buttons can use it
  let LAST = {
    postId: 0,
    title: "",
    url: "",
    geoHtml: "",
    docUrl: "",
    htmlBlobUrl: ""
  };

  // -----------------------------
  // Pane selection improvements
  // -----------------------------
  function enablePaneSelection(el) {
    if (!el) return;
    el.style.userSelect = "text";
    el.addEventListener("mousedown", (e) => e.stopPropagation(), true);
    el.addEventListener("mouseup", (e) => e.stopPropagation(), true);
    el.addEventListener("click", (e) => e.stopPropagation(), true);
  }

  enablePaneSelection(elPreview);
  enablePaneSelection(elDiff);
  enablePaneSelection(elOutput);
  enablePaneSelection(elResults);

  // -----------------------------
  // UI state
  // -----------------------------
  function setBusy(isBusy, msg) {
    if (elSpinner) elSpinner.style.display = isBusy ? "inline-flex" : "none";
    if (elStatus && typeof msg === "string") elStatus.textContent = msg;

    if (btnStop) btnStop.disabled = !isBusy;

    if (btnAnalyze) btnAnalyze.disabled = isBusy;
    if (btnConvert) btnConvert.disabled = isBusy;
    if (btnDuplicate) btnDuplicate.disabled = isBusy;

    // IMPORTANT:
    // Do NOT disable download buttons when finishing.
    // Only disable them while we are actively processing.
    if (isBusy) {
      if (btnDocx) btnDocx.disabled = true;
      if (btnHtml) btnHtml.disabled = true;
    }
  }

  function log(msg) {
    if (!elResults) return;
    const now = new Date();
    const t = now.toLocaleTimeString();
    elResults.textContent = `[${t}] ${msg}\n` + elResults.textContent;
  }

  function getSelectedIDs() {
    if (!elPosts) return [];
    return Array.from(elPosts.selectedOptions || [])
      .map((o) => parseInt(o.value, 10))
      .filter(Boolean);
  }

  function getMode() {
    const radios = document.querySelectorAll('input[name="myls_ai_geo_mode"]');
    for (const r of radios) if (r.checked) return r.value;
    return "partial";
  }

  function withAnchors() {
    const cb = $("#myls_ai_geo_with_anchors");
    return !!(cb && cb.checked);
  }

  // -----------------------------
  // Prompt + params
  // -----------------------------
  function getTemplate() {
    const ta = document.querySelector('textarea[name="myls_ai_geo_prompt_template"]');
    return ta ? String(ta.value || "").trim() : "";
  }

  function getTokens() {
    const inp = document.querySelector('input[name="myls_ai_geo_tokens"]');
    const n = inp ? parseInt(inp.value, 10) : NaN;
    return Number.isFinite(n) && n > 0 ? String(n) : "";
  }

  function getTemperature() {
    const inp = document.querySelector('input[name="myls_ai_geo_temperature"]');
    const n = inp ? parseFloat(inp.value) : NaN;
    return Number.isFinite(n) ? String(n) : "";
  }

  function requireTemplateOrAlert() {
    const tpl = getTemplate();
    if (!tpl) {
      alert('Your GEO prompt template is empty.\n\nPaste the default prompt into the template box and click "Save Template & Params", then run Analyze again.');
      return "";
    }
    return tpl;
  }

  // -----------------------------
  // AJAX helper
  // -----------------------------
  async function postAJAX(action, data) {
    const body = new URLSearchParams();
    body.set("action", action);
    body.set("_ajax_nonce", CFG.nonce);

    Object.keys(data || {}).forEach((k) => {
      if (data[k] === undefined || data[k] === null) return;
      body.set(k, data[k]);
    });

    const resp = await fetch(CFG.ajaxurl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString()
    });

    let json = null;
    try {
      json = await resp.json();
    } catch (e) {
      throw new Error("Bad JSON response from server.");
    }

    if (!json || json.success !== true) {
      const msg = (json && json.data && json.data.message) ? json.data.message : "AJAX error";
      throw new Error(msg);
    }

    return json.data;
  }

  // -----------------------------
  // Load posts by type
  // -----------------------------
  async function loadPostsByType(pt) {
    if (!elLoadedHint || !elPosts) return;

    elLoadedHint.textContent = "Loading…";
    elPosts.innerHTML = "";

    try {
      const data = await postAJAX(CFG.action_get_posts, { post_type: pt });
      const items = data.posts || [];

      items.forEach((p) => {
        const opt = document.createElement("option");
        opt.value = String(p.id);
        opt.textContent = `${p.title} (#${p.id})`;
        elPosts.appendChild(opt);
      });

      elLoadedHint.textContent = `Loaded ${items.length} post(s).`;
      log(`Loaded ${items.length} post(s) for post type "${pt}".`);
    } catch (e) {
      elLoadedHint.textContent = "Error loading posts.";
      log(`Error loading posts: ${e.message}`);
    }
  }

  // -----------------------------
  // Diff helper (fallback)
  // -----------------------------
  function buildQuickDiff(originalText, geoHtml) {
    const origLen = (originalText || "").length;
    const geoLen = (geoHtml || "").length;

    const ids = [];
    (geoHtml || "").replace(/<(h2|h3)\s+[^>]*id="([^"]+)"[^>]*>/gi, (m, tag, id) => {
      ids.push(`${tag.toUpperCase()}: #${id}`);
      return m;
    });

    return `
      <p><strong>Original text length:</strong> ${origLen.toLocaleString()} chars</p>
      <p><strong>GEO output length:</strong> ${geoLen.toLocaleString()} chars</p>
      <p><strong>Anchors found:</strong></p>
      <ul>${ids.slice(0, 30).map(x => `<li>${x}</li>`).join("") || "<li>(none)</li>"}</ul>
    `;
  }

  // -----------------------------
  // Download helpers
  // -----------------------------
  function resetDownloads() {
    // DOCX
    if (btnDocx) btnDocx.disabled = true;
    if (elDocxLink) {
      elDocxLink.style.display = "none";
      elDocxLink.href = "#";
      elDocxLink.textContent = "";
    }

    // HTML
    if (btnHtml) btnHtml.disabled = true;
    if (elHtmlLink) {
      elHtmlLink.style.display = "none";
      elHtmlLink.href = "#";
      elHtmlLink.textContent = "";
    }

    // revoke old blob
    if (LAST.htmlBlobUrl) {
      try { URL.revokeObjectURL(LAST.htmlBlobUrl); } catch (e) {}
    }

    LAST.docUrl = "";
    LAST.geoHtml = "";
    LAST.htmlBlobUrl = "";
  }

  function setDocxUrl(docUrl) {
    LAST.docUrl = docUrl || "";

    if (!btnDocx || !elDocxLink) return;

    if (docUrl) {
      elDocxLink.href = docUrl;
      elDocxLink.textContent = "Download .docx";
      elDocxLink.style.display = "inline-block";
      btnDocx.disabled = false;

      btnDocx.onclick = function () {
        elDocxLink.click();
      };
    } else {
      btnDocx.disabled = true;
      elDocxLink.style.display = "none";
      elDocxLink.href = "#";
      elDocxLink.textContent = "";
      btnDocx.onclick = null;
    }
  }

  function buildHtmlDownload(title, permalink, html) {
    const safeTitle = (title || "geo-output").replace(/[^\w\-]+/g, "-").replace(/\-+/g, "-").replace(/^\-|\-$/g, "");
    const filename = `${safeTitle || "geo-output"}.html`;

    const doc = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>${escapeHtml(title || "GEO Output")}</title>
</head>
<body>
<!-- Page Title: ${escapeHtml(title || "")} -->
<!-- Permalink: ${escapeHtml(permalink || "")} -->
${html || ""}
</body>
</html>`;

    const blob = new Blob([doc], { type: "text/html;charset=utf-8" });
    const url = URL.createObjectURL(blob);

    // store & clean old
    if (LAST.htmlBlobUrl) {
      try { URL.revokeObjectURL(LAST.htmlBlobUrl); } catch (e) {}
    }
    LAST.htmlBlobUrl = url;

    if (btnHtml && elHtmlLink) {
      elHtmlLink.href = url;
      elHtmlLink.download = filename;
      elHtmlLink.textContent = "Download .html";
      elHtmlLink.style.display = "inline-block";
      btnHtml.disabled = false;

      btnHtml.onclick = function () {
        elHtmlLink.click();
      };
    }
  }

  function escapeHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // -----------------------------
  // Analyze
  // -----------------------------
  async function analyzeSelected() {
    const ids = getSelectedIDs();
    if (!ids.length) {
      alert("Select at least one post.");
      return;
    }

    const template = requireTemplateOrAlert();
    if (!template) return;

    STOP = false;
    processed = 0;
    if (elCount) elCount.textContent = "0";

    // clear panes + downloads
    if (elPreview) elPreview.innerHTML = "";
    if (elDiff) elDiff.innerHTML = "";
    if (elOutput) elOutput.textContent = "";
    resetDownloads();

    setBusy(true, "Analyzing…");
    log(`Analyze queued: ${ids.length} post(s).`);

    for (const postId of ids) {
      if (STOP) break;

      try {
        log(`Analyzing post #${postId}…`);

        const data = await postAJAX(CFG.action_analyze, {
          post_id: postId,
          mode: getMode(),
          with_anchors: withAnchors() ? "1" : "0",
          template: template,
          tokens: getTokens(),
          temperature: getTemperature()
        });

        const title = data.title || "";
        const url = data.url || "";
        const rawCombined = data.raw_combined || data.combined || data.raw || "";
        const geoHtml = data.geo_html || data.geoHtml || data.html || "";

        // stash latest
        LAST.postId = postId;
        LAST.title = title;
        LAST.url = url;
        LAST.geoHtml = geoHtml;

        if (elOutput) elOutput.textContent = rawCombined;

        if (elPreview) {
          elPreview.innerHTML = geoHtml ? geoHtml : "<p><em>No GEO output returned.</em></p>";
        }

        if (elDiff) {
          if (data.diff_html) {
            elDiff.innerHTML = data.diff_html;
          } else {
            elDiff.innerHTML = buildQuickDiff(data.page_text || "", geoHtml);
          }
        }

        // Enable HTML download if we have any HTML
        if (geoHtml) {
          buildHtmlDownload(title, url, geoHtml);
        }

        // Enable DOCX if returned
        if (data.doc_url) {
          setDocxUrl(data.doc_url);
          if (elStatus) {
            elStatus.innerHTML = `<a href="${data.doc_url}" target="_blank" rel="noopener">Download .docx</a>`;
          }
          log(`DOCX ready: ${data.doc_url}`);
        } else {
          setDocxUrl("");
        }

        processed++;
        if (elCount) elCount.textContent = String(processed);
      } catch (e) {
        log(`Analyze error for #${postId}: ${e.message}`);
      }
    }

    setBusy(false, STOP ? "Stopped." : "Done.");
  }

  // -----------------------------
  // Convert to GEO Draft
  // -----------------------------
  async function convertToDraft() {
    const ids = getSelectedIDs();
    if (!ids.length) {
      alert("Select at least one post.");
      return;
    }

    const template = requireTemplateOrAlert();
    if (!template) return;

    STOP = false;
    processed = 0;
    if (elCount) elCount.textContent = "0";

    setBusy(true, "Converting…");
    log(`Convert queued: ${ids.length} post(s).`);

    for (const postId of ids) {
      if (STOP) break;

      try {
        log(`Converting post #${postId}…`);

        const data = await postAJAX(CFG.action_convert, {
          post_id: postId,
          mode: getMode(),
          with_anchors: withAnchors() ? "1" : "0",
          template: template,
          tokens: getTokens(),
          temperature: getTemperature()
        });

        const newId = data.new_post_id || data.draft_id || data.new_id || "";
        const editUrl = data.edit_url || data.edit_link || "";
        const previewUrl = data.preview_url || "";

        log(`Created draft #${newId || "(id not returned)"} from #${postId}.`);

        if (elStatus) {
          if (editUrl) {
            elStatus.innerHTML =
              `<a href="${editUrl}" target="_blank" rel="noopener">Open Draft #${newId || ""}</a>` +
              (previewUrl ? ` &nbsp;|&nbsp; <a href="${previewUrl}" target="_blank" rel="noopener">Preview</a>` : "");
          } else {
            elStatus.textContent = newId ? `Draft #${newId} created.` : "Draft created.";
          }
        }

        processed++;
        if (elCount) elCount.textContent = String(processed);
      } catch (e) {
        log(`Convert error for #${postId}: ${e.message}`);
      }
    }

    setBusy(false, STOP ? "Stopped." : "Done.");
  }

  // -----------------------------
  // Duplicate to draft
  // -----------------------------
  async function duplicateNoRewrite() {
    const ids = getSelectedIDs();
    if (!ids.length) {
      alert("Select at least one post.");
      return;
    }

    STOP = false;
    processed = 0;
    if (elCount) elCount.textContent = "0";

    setBusy(true, "Duplicating…");
    log(`Duplicate queued: ${ids.length} post(s).`);

    for (const postId of ids) {
      if (STOP) break;

      try {
        const data = await postAJAX(CFG.action_duplicate, { post_id: postId });
        const newId = data.new_post_id || data.draft_id || data.new_id || "";
        log(`Duplicated #${postId} → draft #${newId || "(id not returned)"}.`);

        processed++;
        if (elCount) elCount.textContent = String(processed);
      } catch (e) {
        log(`Duplicate error for #${postId}: ${e.message}`);
      }
    }

    setBusy(false, STOP ? "Stopped." : "Done.");
  }

  // -----------------------------
  // Events
  // -----------------------------
  if (elPT) {
    elPT.addEventListener("change", () => loadPostsByType(elPT.value));
  }

  if (btnSelectAll && elPosts) btnSelectAll.addEventListener("click", () => {
    Array.from(elPosts.options).forEach(o => (o.selected = true));
  });

  if (btnClear && elPosts) btnClear.addEventListener("click", () => {
    Array.from(elPosts.options).forEach(o => (o.selected = false));
  });

  if (btnAnalyze) btnAnalyze.addEventListener("click", analyzeSelected);
  if (btnConvert) btnConvert.addEventListener("click", convertToDraft);
  if (btnDuplicate) btnDuplicate.addEventListener("click", duplicateNoRewrite);

  if (btnStop) btnStop.addEventListener("click", () => {
    STOP = true;
    log("Stop requested.");
    setBusy(true, "Stopping…");
    setTimeout(() => setBusy(false, "Stopped."), 250);
  });

  // Boot
  resetDownloads();
  loadPostsByType(CFG.defaultType);

})();
