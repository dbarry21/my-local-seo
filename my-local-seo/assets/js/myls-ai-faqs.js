/* ========================================================================
 * MYLS – AI FAQs Subtab JS (v1.1)
 * File: assets/js/myls-ai-faqs.js
 *
 * - Loads posts by type
 * - Generate FAQs (Preview)
 * - Download .docx / .html (enabled when URLs returned)
 * - Insert generated FAQs into MYLS FAQs repeater (optional replace)
 * - Delete auto-generated FAQs from ACF (bulk)
 * - Local Ctrl+A inside preview panes (doesn't select entire admin page)
 *
 * Verified against:
 *  - inc/ajax/ai-faqs.php actions:
 *      myls_ai_faqs_get_posts_v1
 *      myls_ai_faqs_generate_v1
 *      myls_ai_faqs_insert_acf_v1   (expects html + replace_existing)
 *      myls_ai_faqs_delete_auto_acf_v1
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

  // IMPORTANT: Correct ID (matches subtab)
  const cbReplaceExisting = $("#myls_ai_faqs_acf_replace");
  const cbSkipExisting = $("#myls_ai_faqs_skip_existing");

  const btnInsertACF = $("#myls_ai_faqs_insert_acf");
  const btnDeleteAuto = $("#myls_ai_faqs_delete_auto");
  const btnStop = $("#myls_ai_faqs_stop");

  const cbAllowLinks = $("#myls_ai_faqs_allow_links");
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

  let STOP = false;
  let processed = 0;

  // Full (unfiltered) post list returned from the server for the current post type.
  // We filter client-side for speed and to avoid extra AJAX calls.
  let allPosts = [];

  // Last returned download URLs (for enabling buttons)
  let lastDocxUrl = "";
  let lastHtmlUrl = "";
  let lastPostId = 0;
  let lastHtml = "";

  // -----------------------------
  // UI state
  // -----------------------------
  function setBusy(isBusy, msg) {
    if (elSpinner) elSpinner.style.display = isBusy ? "inline-flex" : "none";
    if (elStatus) elStatus.textContent = msg || "";

    if (btnStop) btnStop.disabled = !isBusy;
    if (btnGenerate) btnGenerate.disabled = isBusy;

    // Downloads
    if (btnDocx) btnDocx.disabled = isBusy || !lastDocxUrl;
    if (btnHtml) btnHtml.disabled = isBusy || !lastHtmlUrl;

    // Insert can run as a batch (generate+insert) as long as something is selected
    if (btnInsertACF) btnInsertACF.disabled = isBusy || !getSelectedIDs().length;
    if (btnDeleteAuto) btnDeleteAuto.disabled = isBusy || !getSelectedIDs().length;
  }

  function log(msg) {
    const now = new Date();
    const t = now.toLocaleTimeString();
    if (!elResults) return;
    elResults.textContent = `[${t}] ${msg}\n` + elResults.textContent;
  }

  function getTitleForPost(postId) {
    const opt = Array.from(elPosts?.options || []).find((o) => parseInt(o.value, 10) === postId);
    return opt ? opt.textContent : `#${postId}`;
  }

  function appendPreviewBlock(postId, title, html, raw) {
    // Preview: append (do not replace) during batch
    if (elPreview) {
      const header = `<p><strong>${escapeHtml(title)} (ID ${postId})</strong></p>`;
      const block = `<div class="myls-faqs-block">${header}${(html || "").trim() || "<p><em>No output returned.</em></p>"}</div>`;
      elPreview.insertAdjacentHTML("beforeend", (elPreview.innerHTML ? "<hr/>" : "") + block);
    }

    if (elOutput) {
      const h = `\n=== ${title} (ID ${postId}) ===\n`;
      elOutput.textContent = (elOutput.textContent || "") + h + String((raw || "").trim()) + "\n";
    }
  }

  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function getSelectedIDs() {
    return Array.from(elPosts?.selectedOptions || [])
      .map((o) => parseInt(o.value, 10))
      .filter(Boolean);
  }

  function clearPreviewPanes() {
    if (elPreview) elPreview.innerHTML = "";
    if (elOutput) elOutput.textContent = "";
  }

  function appendPreviewBlock(postId, title, html, raw) {
    // Preview pane (HTML)
    if (elPreview) {
      const wrap = document.createElement("div");
      wrap.innerHTML = `
        <hr/>
        <p><strong>${escapeHtml(title)} (ID ${postId})</strong></p>
        ${html || "<p><em>No output returned.</em></p>"}
      `;
      elPreview.appendChild(wrap);
    }

    // Raw pane (text)
    if (elOutput) {
      const header = `\n\n==============================\n${title} (ID ${postId})\n==============================\n`;
      elOutput.textContent += header + (raw || "") + "\n";
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function allowLinks() {
    return !!(cbAllowLinks && cbAllowLinks.checked);
  }

  function currentVariant() {
    const v = (elVariant && elVariant.value ? String(elVariant.value) : (CFG.defaultVariant || "LONG")).toUpperCase();
    return v === "SHORT" ? "SHORT" : "LONG";
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

    // Visual focus
    el.addEventListener("focusin", () => el.classList.add("is-focused"));
    el.addEventListener("focusout", () => el.classList.remove("is-focused"));

    // Make focusable for key events
    if (!el.hasAttribute("tabindex")) el.setAttribute("tabindex", "0");

    el.addEventListener("keydown", (e) => {
      const isMac = navigator.platform.toUpperCase().indexOf("MAC") >= 0;
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
      // Ensure undefined/null don't become "undefined"/"null"
      const v = data[k];
      body.set(k, v === undefined || v === null ? "" : String(v));
    });

    const resp = await fetch(CFG.ajaxurl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString()
    });

    const json = await resp.json().catch(() => null);

    // Improve debugging if server returns HTML (fatal error) or bad JSON
    if (!json) {
      throw new Error(`AJAX error: invalid JSON response (HTTP ${resp.status}). Check PHP error logs.`);
    }

    if (json.success !== true) {
      const msg =
        (json.data && typeof json.data.message === "string" && json.data.message) ||
        (typeof json.message === "string" && json.message) ||
        "AJAX error";
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
      allPosts = Array.isArray(data.posts) ? data.posts : [];

      // Reset search each time the post type changes
      if (elSearch) elSearch.value = "";

      renderPosts(allPosts);

      elLoadedHint.textContent = `Loaded ${allPosts.length} post(s).`;
      log(`Loaded ${allPosts.length} post(s) for post type "${pt}".`);

      // Update ACF delete button state after reload
      setBusy(false, "");
    } catch (e) {
      elLoadedHint.textContent = "Error loading posts.";
      log(`Error loading posts: ${e.message}`);
    }
  }

  // -----------------------------
  // Client-side post list filtering
  // -----------------------------
  function renderPosts(items) {
    if (!elPosts) return;

    // Preserve current selection across re-renders
    const selected = new Set(getSelectedIDs().map((n) => String(n)));
    elPosts.innerHTML = "";

    (items || []).forEach((p) => {
      const opt = document.createElement("option");
      opt.value = String(p.id);
      opt.textContent = `${p.title} (#${p.id})`;
      if (selected.has(opt.value)) opt.selected = true;
      elPosts.appendChild(opt);
    });

    // Button states depend on selection
    setBusy(false, "");
  }

  function applySearchFilter() {
    if (!elSearch) return;
    const q = String(elSearch.value || "").trim().toLowerCase();
    if (!q) {
      renderPosts(allPosts);
      return;
    }

    const filtered = (allPosts || []).filter((p) => {
      const title = String(p.title || "").toLowerCase();
      const id = String(p.id || "");
      return title.includes(q) || id.includes(q);
    });

    renderPosts(filtered);
    if (elLoadedHint) elLoadedHint.textContent = `Showing ${filtered.length} of ${allPosts.length}.`;
  }

  // -----------------------------
  // Generate (Preview + downloads)
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

    // Reset output state each run
    lastDocxUrl = "";
    lastHtmlUrl = "";
    lastHtml = "";
    lastPostId = 0;

    if (btnDocx) btnDocx.disabled = true;
    if (btnHtml) btnHtml.disabled = true;
    if (btnInsertACF) btnInsertACF.disabled = true;

    // Clear panes and append results per post
    if (elPreview) elPreview.innerHTML = "";
    if (elOutput) elOutput.textContent = "";

    setBusy(true, "Generating…");
    log(`Generate queued: ${ids.length} post(s).`);

    for (const postId of ids) {
      if (STOP) break;

      try {
        const title = getTitleForPost(postId);
        log(`Generating FAQs for ${title}…`);

        const data = await postAJAX(CFG.action_generate, {
          post_id: postId,
          allow_links: allowLinks() ? "1" : "0",
          variant: currentVariant(),
          // Send current prompt to prevent "missing_template" if option is blank
          template: elPrompt ? elPrompt.value : ""
        });

        // Preview (append)
        const outHtml = (data.html || "").trim();
        appendPreviewBlock(postId, title, outHtml, (data.raw || "").trim());

        // Track last output for MYLS insertion
        lastHtml = outHtml;
        lastPostId = postId;

        // Downloads (URLs returned by PHP save_* helpers)
        lastDocxUrl = (data.doc_url || "").trim();
        lastHtmlUrl = (data.html_url || "").trim();

        // Update buttons (last file URLs reflect most recent post)
        setBusy(true, (lastDocxUrl || lastHtmlUrl) ? "Generated • Files ready" : "Generated");

        processed++;
        if (elCount) elCount.textContent = String(processed);

        // Enable downloads + MYLS insert now that we have output
        setBusy(false, "Ready.");
      } catch (e) {
        log(`Generate error for #${postId}: ${e.message}`);
      }
    }

    setBusy(false, STOP ? "Stopped." : "Done.");
  }

  // -----------------------------
  // Download actions
  // -----------------------------
  function openUrl(url) {
    if (!url) return;
    window.open(url, "_blank", "noopener");
  }

  if (btnDocx) btnDocx.addEventListener("click", () => openUrl(lastDocxUrl));
  if (btnHtml) btnHtml.addEventListener("click", () => openUrl(lastHtmlUrl));

  // -----------------------------
  // ACF actions (Insert / Delete Auto)
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

    // Clear panes and append results per post
    if (elPreview) elPreview.innerHTML = "";
    if (elOutput) elOutput.textContent = "";

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
          // If check fails, fall through and attempt generate/insert (better than blocking)
          log(`⚠ Existing-FAQ check failed for ${title}: ${e.message}`);
        }
      }

      try {
        log(`Generating FAQs for ${title}…`);

        const data = await postAJAX(genAction, {
          post_id: postId,
          allow_links: allowLinks() ? "1" : "0",
          variant: currentVariant(),
          template: elPrompt ? elPrompt.value : ""
        });

        const outHtml = (data.html || "").trim();
        appendPreviewBlock(postId, title, outHtml, (data.raw || "").trim());

        // Track last output and file URLs (most recent)
        lastHtml = outHtml;
        lastPostId = postId;
        lastDocxUrl = (data.doc_url || "").trim();
        lastHtmlUrl = (data.html_url || "").trim();

        // Insert into MYLS for this post
        log(`Inserting into MYLS FAQs for ${title}…`);
        const ins = await postAJAX(insertAction, {
          post_id: postId,
          html: outHtml,
          replace_existing: replaceExisting ? "1" : "0"
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

  async function deleteAutoFromMYLS() {
    const ids = getSelectedIDs();
    if (!ids.length) {
      alert("Select at least one post.");
      return;
    }

    if (!confirm("Delete auto-generated MYLS FAQ items for the selected post(s)??")) return;

    STOP = false;
    processed = 0;
    if (elCount) elCount.textContent = "0";

    setBusy(true, "Deleting auto-generated MYLS FAQs…");
    log(`Delete-auto queued: ${ids.length} post(s).`);

    for (const postId of ids) {
      if (STOP) break;

      try {
        const deleteAction = CFG.action_delete_auto_myls || CFG.action_delete_auto_acf; // back-compat
        if (!deleteAction) throw new Error("Missing AJAX action for delete-auto.");

        const data = await postAJAX(deleteAction, { post_id: postId });

        // PHP returns deleted_count, total_rows
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
  // Events
  // -----------------------------
  if (elPT) {
    elPT.addEventListener("change", () => loadPostsByType(elPT.value));
  }

  // Live search filter (client-side)
  if (elSearch) {
    let t = null;
    elSearch.addEventListener("input", () => {
      // tiny debounce so we don't re-render on every keystroke at high speed
      if (t) window.clearTimeout(t);
      t = window.setTimeout(applySearchFilter, 80);
    });
  }

  if (btnSelectAll) {
    btnSelectAll.addEventListener("click", () => {
      Array.from(elPosts?.options || []).forEach((o) => (o.selected = true));
      if (btnDeleteAuto) btnDeleteAuto.disabled = !getSelectedIDs().length;
      if (btnInsertACF) btnInsertACF.disabled = !getSelectedIDs().length;
    });
  }

  if (btnClear) {
    btnClear.addEventListener("click", () => {
      Array.from(elPosts?.options || []).forEach((o) => (o.selected = false));
      if (btnDeleteAuto) btnDeleteAuto.disabled = true;
      if (btnInsertACF) btnInsertACF.disabled = true;
    });
  }

  if (elPosts) {
    elPosts.addEventListener("change", () => {
      if (btnDeleteAuto) btnDeleteAuto.disabled = STOP || !getSelectedIDs().length;
      if (btnInsertACF) btnInsertACF.disabled = STOP || !getSelectedIDs().length;
    });
  }

  if (btnGenerate) btnGenerate.addEventListener("click", generateSelected);
  if (btnInsertACF) btnInsertACF.addEventListener("click", insertIntoMYLSBatch);
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
