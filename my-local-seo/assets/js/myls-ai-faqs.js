/* ========================================================================
 * MYLS – AI FAQs Subtab JS (v1.3 - FIXED)
 * File: assets/js/myls-ai-faqs.js
 *
 * Features:
 * - Loads posts by type (includes drafts)
 * - Client-side search filter
 * - Generate FAQs (Preview)
 * - Download .docx / .html (enabled when URLs returned)
 * - Batch: Generate + Insert into MYLS FAQs (with optional overwrite)
 * - Batch: Delete auto-generated MYLS FAQs
 * - Local Ctrl+A inside preview panes (doesn't select entire admin page)
 *
 * AJAX actions are injected via window.MYLS_AI_FAQS in subtab-faqs.php.
 * 
 * FIXED v1.3:
 * - Now properly passes max_tokens and temperature parameters for EACH post
 *   in the batch loop, ensuring settings are applied consistently across all
 *   posts instead of falling back to saved option values.
 * ======================================================================== */

(function () {
  "use strict";

  if (!window.MYLS_AI_FAQS) return;
  const CFG = window.MYLS_AI_FAQS;

  // -----------------------------
  // DOM helpers
  // -----------------------------
  const $ = (sel) => document.querySelector(sel);

  const elPT = $("#myls_ai_faqs_pt");
  const elSearch = $("#myls_ai_faqs_search");
  const elPosts = $("#myls_ai_faqs_posts");

  const btnSelectAll = $("#myls_ai_faqs_select_all");
  const btnClear = $("#myls_ai_faqs_clear");
  const btnGenerate = $("#myls_ai_faqs_generate");
  const btnInsert = $("#myls_ai_faqs_insert_acf");
  const btnDeleteAuto = $("#myls_ai_faqs_delete_auto");
  const btnStop = $("#myls_ai_faqs_stop");

  const cbAllowLinks = $("#myls_ai_faqs_allow_links");
  const cbReplaceExisting = $("#myls_ai_faqs_acf_replace");
  const cbSkipExisting = $("#myls_ai_faqs_skip_existing");
  const elVariant = $("#myls_ai_faqs_variant");

  const btnDocx = $("#myls_ai_faqs_docx");
  const btnHtml = $("#myls_ai_faqs_html");

  const elSpinner = $("#myls_ai_faqs_spinner");
  const elStatus = $("#myls_ai_faqs_status");
  const elCount = $("#myls_ai_faqs_count");

  const elPreview = $("#myls_ai_faqs_preview");
  const elOutput = $("#myls_ai_faqs_output");
  const elResults = $("#myls_ai_faqs_results");
  const elLoadedHint = $("#myls_ai_faqs_loaded_hint");

  const elPrompt = $("#myls_ai_faqs_prompt_template");
  const elTokens = $("#myls_ai_faqs_tokens");
  const elTemperature = $("#myls_ai_faqs_temperature");

  // -----------------------------
  // State
  // -----------------------------
  let STOP = false;
  let processed = 0;

  // Full (unfiltered) post list returned from the server for the current post type.
  let allPosts = [];

  // Most recent output URLs (enables download buttons)
  let lastDocxUrl = "";
  let lastHtmlUrl = "";
  let lastHtml = "";

  // -----------------------------
  // UI helpers
  // -----------------------------
  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function setBusy(isBusy, msg) {
    if (elSpinner) elSpinner.style.display = isBusy ? "inline-flex" : "none";
    if (elStatus) elStatus.textContent = msg || "";

    if (btnStop) btnStop.disabled = !isBusy;
    if (btnGenerate) btnGenerate.disabled = isBusy;

    // Downloads
    if (btnDocx) btnDocx.disabled = isBusy || !lastDocxUrl;
    if (btnHtml) btnHtml.disabled = isBusy || !lastHtmlUrl;

    // Batch actions require selection
    const hasSel = !!getSelectedIDs().length;
    if (btnInsert) btnInsert.disabled = isBusy || !hasSel;
    if (btnDeleteAuto) btnDeleteAuto.disabled = isBusy || !hasSel;
  }

  function log(msg) {
    if (!elResults) return;
    const t = new Date().toLocaleTimeString();
    elResults.textContent = `[${t}] ${msg}\n` + elResults.textContent;
  }

  function getSelectedIDs() {
    return Array.from(elPosts?.selectedOptions || [])
      .map((o) => parseInt(o.value, 10))
      .filter(Boolean);
  }

  function getTitleForPost(postId) {
    const opt = Array.from(elPosts?.options || []).find((o) => parseInt(o.value, 10) === postId);
    return opt ? opt.textContent : `#${postId}`;
  }

  function clearPreviewPanes() {
    if (elPreview) elPreview.innerHTML = "";
    if (elOutput) elOutput.textContent = "";
  }

  function appendPreviewBlock(postId, title, html, raw) {
    if (elPreview) {
      const wrap = document.createElement("div");
      wrap.innerHTML = `
        <hr/>
        <p><strong>${escapeHtml(title)} (ID ${postId})</strong></p>
        ${(html || "").trim() || "<p><em>No output returned.</em></p>"}
      `;
      elPreview.appendChild(wrap);
    }

    if (elOutput) {
      const header = `\n\n==============================\n${title} (ID ${postId})\n==============================\n`;
      elOutput.textContent += header + String((raw || "").trim()) + "\n";
    }
  }

  function allowLinks() {
    return !!(cbAllowLinks && cbAllowLinks.checked);
  }

  function currentVariant() {
    const v = (elVariant && elVariant.value ? String(elVariant.value) : String(CFG.defaultVariant || "LONG")).toUpperCase();
    return v === "SHORT" ? "SHORT" : "LONG";
  }

  function currentTokens() {
    if (!elTokens) return null;
    const val = parseInt(elTokens.value, 10);
    return val > 0 ? val : null;
  }

  function currentTemperature() {
    if (!elTemperature) return null;
    const val = parseFloat(elTemperature.value);
    return !isNaN(val) ? val : null;
  }

  // Initialize variant selector to saved default
  if (elVariant && CFG.defaultVariant) {
    elVariant.value = currentVariant();
  }

  // -----------------------------
  // Local Ctrl+A inside preview panes
  // -----------------------------
  function wireLocalSelectAll(el) {
    if (!el) return;

    el.addEventListener("focusin", () => el.classList.add("is-focused"));
    el.addEventListener("focusout", () => el.classList.remove("is-focused"));

    // Make focusable for key events
    if (!el.hasAttribute("tabindex")) el.setAttribute("tabindex", "0");

    el.addEventListener("keydown", (e) => {
      const isMac = (navigator.platform || "").toUpperCase().includes("MAC");
      const mod = isMac ? e.metaKey : e.ctrlKey;
      if (mod && (e.key === "a" || e.key === "A")) {
        e.preventDefault();
        e.stopPropagation();

        const sel = window.getSelection();
        if (!sel) return;

        const range = document.createRange();
        range.selectNodeContents(el);
        sel.removeAllRanges();
        sel.addRange(range);
      }
    });
  }

  wireLocalSelectAll(elPreview);
  wireLocalSelectAll(elOutput);
  wireLocalSelectAll(elResults);

  // -----------------------------
  // AJAX helper
  // -----------------------------
  async function postAJAX(action, data) {
    const body = new URLSearchParams();
    body.set("action", action);
    body.set("_ajax_nonce", CFG.nonce);

    Object.keys(data || {}).forEach((k) => {
      const v = data[k];
      body.set(k, v === undefined || v === null ? "" : String(v));
    });

    const resp = await fetch(CFG.ajaxurl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString(),
    });

    const json = await resp.json().catch(() => null);
    if (!json || typeof json !== "object") throw new Error("Bad JSON response.");
    if (!json.success) throw new Error((json.data && json.data.message) || json.message || "Request failed.");
    return json.data || {};
  }

  // -----------------------------
  // Load + filter posts
  // -----------------------------
  async function loadPostsByType(pt) {
    STOP = false;
    setBusy(true, "Loading posts…");

    try {
      const action = CFG.action_get_posts;
      if (!action) throw new Error("Missing AJAX action for get_posts.");

      const data = await postAJAX(action, { post_type: pt });
      allPosts = Array.isArray(data.posts) ? data.posts : [];

      renderPosts(allPosts);
      if (elLoadedHint) elLoadedHint.textContent = `${allPosts.length} loaded.`;

      // Enable batch buttons if there is a selection
      if (btnInsert) btnInsert.disabled = !getSelectedIDs().length;
      if (btnDeleteAuto) btnDeleteAuto.disabled = !getSelectedIDs().length;
    } catch (e) {
      alert(`Failed to load posts: ${e.message}`);
    }

    setBusy(false, "");
  }

  function renderPosts(posts) {
    if (!elPosts) return;
    elPosts.innerHTML = "";

    (posts || []).forEach((p) => {
      const opt = document.createElement("option");
      opt.value = String(p.id);
      opt.textContent = `${p.title} (#${p.id})`;
      elPosts.appendChild(opt);
    });
  }

  function applySearchFilter() {
    const q = String(elSearch?.value || "").trim().toLowerCase();
    if (!q) {
      renderPosts(allPosts);
      if (elLoadedHint) elLoadedHint.textContent = `${allPosts.length} loaded.`;
      return;
    }

    const filtered = allPosts.filter((p) => {
      const hay = `${p.title} ${p.id}`.toLowerCase();
      return hay.includes(q);
    });

    renderPosts(filtered);
    if (elLoadedHint) elLoadedHint.textContent = `${filtered.length} match(es) (of ${allPosts.length}).`;
  }

  // -----------------------------
  // Generate (Preview only)
  // -----------------------------
  async function generateSelected() {
    const ids = getSelectedIDs();
    if (!ids.length) {
      alert("Select at least one post.");
      return;
    }

    STOP = false;
    processed = 0;
    if (elCount) elCount.textContent = "0";

    clearPreviewPanes();
    setBusy(true, "Generating…");
    log(`Generate queued: ${ids.length} post(s).`);

    const genAction = CFG.action_generate;
    if (!genAction) throw new Error("Missing AJAX action for generate.");

    for (const postId of ids) {
      if (STOP) break;

      const title = getTitleForPost(postId);

      try {
        log(`Generating FAQs for ${title}…`);

        const data = await postAJAX(genAction, {
          post_id: postId,
          allow_links: allowLinks() ? "1" : "0",
          variant: currentVariant(),
          template: elPrompt ? elPrompt.value : "",
          tokens: currentTokens(),
          temperature: currentTemperature(),
        });

        const outHtml = String(data.html || "").trim();
        appendPreviewBlock(postId, title, outHtml, String(data.raw || "").trim());

        // Most recent output gets download buttons
        lastHtml = outHtml;
        lastDocxUrl = String(data.doc_url || "").trim();
        lastHtmlUrl = String(data.html_url || "").trim();

        processed++;
        if (elCount) elCount.textContent = String(processed);
      } catch (e) {
        log(`❌ Generate error for ${title}: ${e.message}`);
      }
    }

    setBusy(false, STOP ? "Stopped." : "Done.");
  }

  // -----------------------------
  // Batch: Generate + Insert into MYLS
  // -----------------------------
  async function insertIntoMYLSBatch() {
    const ids = getSelectedIDs();
    if (!ids.length) {
      alert("Select at least one post.");
      return;
    }

    STOP = false;
    processed = 0;
    if (elCount) elCount.textContent = "0";

    clearPreviewPanes();
    setBusy(true, "Generating + inserting…");
    log(`Auto-insert queued: ${ids.length} post(s).`);

    const replaceExisting = !!(cbReplaceExisting && cbReplaceExisting.checked);
    const skipExisting = !!(cbSkipExisting && cbSkipExisting.checked) && !replaceExisting;

    const genAction = CFG.action_generate;
    const insertAction = CFG.action_insert_myls || CFG.action_insert_acf;
    const checkAction = CFG.action_check_existing_myls;
    if (!genAction) throw new Error("Missing AJAX action for generate.");
    if (!insertAction) throw new Error("Missing AJAX action for insert.");

    for (const postId of ids) {
      if (STOP) break;

      const title = getTitleForPost(postId);

      // Optional: skip if existing MYLS FAQs are present
      if (skipExisting && checkAction) {
        try {
          const chk = await postAJAX(checkAction, { post_id: postId });
          if (chk && chk.has_faqs) {
            log(`⏭ Skipped ${title}: existing MYLS FAQs found (${chk.count}).`);
            processed++;
            if (elCount) elCount.textContent = String(processed);
            continue;
          }
        } catch (e) {
          log(`⚠ Existing-FAQ check failed for ${title}: ${e.message}`);
        }
      }

      try {
        log(`Generating FAQs for ${title}…`);

        const data = await postAJAX(genAction, {
          post_id: postId,
          allow_links: allowLinks() ? "1" : "0",
          variant: currentVariant(),
          template: elPrompt ? elPrompt.value : "",
          tokens: currentTokens(),
          temperature: currentTemperature(),
        });

        const outHtml = String(data.html || "").trim();
        appendPreviewBlock(postId, title, outHtml, String(data.raw || "").trim());

        // Most recent output gets download buttons
        lastHtml = outHtml;
        lastDocxUrl = String(data.doc_url || "").trim();
        lastHtmlUrl = String(data.html_url || "").trim();

        log(`Inserting into MYLS FAQs for ${title}…`);
        const ins = await postAJAX(insertAction, {
          post_id: postId,
          html: outHtml,
          replace_existing: replaceExisting ? "1" : "0",
        });

        const inserted = ins.inserted_count ?? 0;
        const skipped = ins.skipped_count ?? 0;
        const total = ins.total_rows ?? 0;
        log(`✔ ${title}: inserted ${inserted}, skipped ${skipped}, total rows now ${total}.`);

        processed++;
        if (elCount) elCount.textContent = String(processed);
      } catch (e) {
        log(`❌ Auto-insert error for ${title}: ${e.message}`);
      }
    }

    setBusy(false, STOP ? "Stopped." : "Done.");
  }

  // -----------------------------
  // Batch: Delete auto-generated MYLS FAQs
  // -----------------------------
  async function deleteAutoFromMYLS() {
    const ids = getSelectedIDs();
    if (!ids.length) {
      alert("Select at least one post.");
      return;
    }

    if (!confirm("Delete auto-generated MYLS FAQ items for the selected post(s)?")) return;

    STOP = false;
    processed = 0;
    if (elCount) elCount.textContent = "0";

    setBusy(true, "Deleting auto-generated MYLS FAQs…");
    log(`Delete-auto queued: ${ids.length} post(s).`);

    const deleteAction = CFG.action_delete_auto_myls || CFG.action_delete_auto_acf; // back-compat
    if (!deleteAction) throw new Error("Missing AJAX action for delete-auto.");

    for (const postId of ids) {
      if (STOP) break;

      try {
        const data = await postAJAX(deleteAction, { post_id: postId });
        const deleted = data.deleted_count ?? 0;
        const total = data.total_rows ?? 0;
        log(`MYLS delete-auto OK for #${postId}: deleted ${deleted}, rows remaining ${total}.`);
        processed++;
        if (elCount) elCount.textContent = String(processed);
      } catch (e) {
        log(`Delete-auto error for #${postId}: ${e.message}`);
      }
    }

    setBusy(false, STOP ? "Stopped." : "Done.");
  }

  // -----------------------------
  // Downloads
  // -----------------------------
  function openUrl(url) {
    if (!url) return;
    window.open(url, "_blank", "noopener");
  }

  if (btnDocx) btnDocx.addEventListener("click", () => openUrl(lastDocxUrl));
  if (btnHtml) btnHtml.addEventListener("click", () => openUrl(lastHtmlUrl));

  // -----------------------------
  // Events
  // -----------------------------
  if (elPT) {
    elPT.addEventListener("change", () => loadPostsByType(elPT.value));
  }

  if (elSearch) {
    let t = null;
    elSearch.addEventListener("input", () => {
      if (t) window.clearTimeout(t);
      t = window.setTimeout(applySearchFilter, 80);
    });
  }

  if (btnSelectAll) {
    btnSelectAll.addEventListener("click", () => {
      Array.from(elPosts?.options || []).forEach((o) => (o.selected = true));
      setBusy(false, "");
    });
  }

  if (btnClear) {
    btnClear.addEventListener("click", () => {
      Array.from(elPosts?.options || []).forEach((o) => (o.selected = false));
      if (btnInsert) btnInsert.disabled = true;
      if (btnDeleteAuto) btnDeleteAuto.disabled = true;
    });
  }

  if (elPosts) {
    elPosts.addEventListener("change", () => {
      const hasSel = !!getSelectedIDs().length;
      if (btnInsert) btnInsert.disabled = STOP || !hasSel;
      if (btnDeleteAuto) btnDeleteAuto.disabled = STOP || !hasSel;
    });
  }

  if (btnGenerate) btnGenerate.addEventListener("click", generateSelected);
  if (btnInsert) btnInsert.addEventListener("click", insertIntoMYLSBatch);
  if (btnDeleteAuto) btnDeleteAuto.addEventListener("click", deleteAutoFromMYLS);

  if (btnStop) {
    btnStop.addEventListener("click", () => {
      STOP = true;
      log("Stop requested.");
      setBusy(true, "Stopping…");
      setTimeout(() => setBusy(false, "Stopped."), 250);
    });
  }

  // Boot
  loadPostsByType(CFG.defaultType);
})();
