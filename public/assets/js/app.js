/* OGES-SHIELD — app JS (Phase 1: interaksi dummy).
   Data nyata menyusul Phase 3+ via /api/*. */

(function () {
  "use strict";

  /* ---------- Dropdown (notif + user menu) — Phase 6 ---------- */
    function initSidebarDrawer() {
    // responsive ≤1024px: sidebar jadi drawer (hamburger toggle)
    var sb = document.getElementById("sidebar");
    var bd = document.getElementById("sidebar-backdrop");
    var tg = document.querySelector(".sidebar-toggle");
    if (!sb || !tg || !bd) return;
    var close = function () {
      sb.classList.remove("open");
      bd.classList.remove("show");
      tg.setAttribute("aria-expanded", "false");
    };
    tg.addEventListener("click", function (e) {
      e.stopPropagation();
      var open = sb.classList.toggle("open");
      bd.classList.toggle("show", open);
      tg.setAttribute("aria-expanded", open ? "true" : "false");
    });
    bd.addEventListener("click", close);
    // klik item nav → tutup drawer (mobile UX)
    sb.querySelectorAll(".nav-item").forEach(function (a) {
      a.addEventListener("click", function () {
        if (window.innerWidth <= 1024) close();
      });
    });
    // resize besar lagi → pastikan drawer tertutup
    window.addEventListener("resize", function () {
      if (window.innerWidth > 1024) close();
    });
  }

  function initDropdowns() {
    var btns = document.querySelectorAll("[data-dropdown]");
    btns.forEach(function (btn) {
      var menu = document.getElementById(btn.dataset.dropdown);
      if (!menu) return;
      btn.addEventListener("click", function (e) {
        e.stopPropagation();
        var open = !menu.hidden;
        document.querySelectorAll(".dropdown-menu").forEach(function (m) { m.hidden = true; });
        document.querySelectorAll("[data-dropdown]").forEach(function (b) { b.setAttribute("aria-expanded", "false"); });
        if (!open) { menu.hidden = false; btn.setAttribute("aria-expanded", "true"); }
      });
    });
    document.addEventListener("click", function (e) {
      if (!e.target.closest("[data-dropdown]") && !e.target.closest(".dropdown-menu")) {
        document.querySelectorAll(".dropdown-menu").forEach(function (m) { m.hidden = true; });
        document.querySelectorAll("[data-dropdown]").forEach(function (b) { b.setAttribute("aria-expanded", "false"); });
      }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        document.querySelectorAll(".dropdown-menu").forEach(function (m) { m.hidden = true; });
        document.querySelectorAll("[data-dropdown]").forEach(function (b) { b.setAttribute("aria-expanded", "false"); });
      }
    });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDropdowns);
  } else {
    initDropdowns();
  }

  /* ---------- Tabs ---------- */
  function initTabs(scope) {
    scope.querySelectorAll("[data-tabs]").forEach(function (tabsRoot) {
      var btns = tabsRoot.querySelectorAll(".tab-btn");
      var panes = tabsRoot.querySelectorAll(".tab-pane");
      btns.forEach(function (btn) {
        btn.addEventListener("click", function () {
          var target = btn.dataset.tab;
          btns.forEach(function (b) { b.classList.toggle("active", b === btn); });
          panes.forEach(function (p) { p.classList.toggle("active", p.dataset.pane === target); });
        });
      });
    });
  }

  /* ---------- Modal ---------- */
  function initModals() {
    document.querySelectorAll("[data-modal-open]").forEach(function (trigger) {
      trigger.addEventListener("click", function () {
        var modal = document.getElementById(trigger.dataset.modalOpen);
        if (modal) modal.classList.add("open");
      });
    });
    document.querySelectorAll(".modal-backdrop").forEach(function (backdrop) {
      backdrop.addEventListener("click", function (e) {
        if (e.target === backdrop) backdrop.classList.remove("open");
      });
    });
    document.querySelectorAll("[data-modal-close]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var modal = btn.closest(".modal-backdrop");
        if (modal) modal.classList.remove("open");
      });
    });
  }

  /* ---------- Terminal: auto-scroll + toggle + clear ---------- */
  var liveLogsOn = {};
  function initTerminals() {
    document.querySelectorAll(".terminal[data-terminal]").forEach(function (term) {
      var key = term.dataset.terminal;
      liveLogsOn[key] = true;
      term.scrollTop = term.scrollHeight;
    });
    document.querySelectorAll("[data-term-toggle]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var key = btn.dataset.termToggle;
        liveLogsOn[key] = !liveLogsOn[key];
        btn.textContent = liveLogsOn[key] ? "Live Logs: ON" : "Live Logs: OFF";
        btn.classList.toggle("btn-primary", liveLogsOn[key]);
      });
    });
    document.querySelectorAll("[data-term-clear]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var key = btn.dataset.termClear;
        var term = document.querySelector('.terminal[data-terminal="' + key + '"]');
        if (term) term.innerHTML = "";
      });
    });
  }

  /* ---------- Live log feeds ----------
     Phase 3: kalau ada terminal dengan data-run-id → polling /api/stream.php
     (event asli dari orchestrator). Terminal tanpa data-run-id tetap dummy. */
  var LOG_SOURCES = [
    ["INFO",  "orchestrator.loop",    "phase=vulnerability_assessment iter=6 tool_pool=3"],
    ["INFO",  "nuclei_runner",        "template=default/cves/2021/CVE-2021-23017.yaml status=matched host=www.acme-corp.com"],
    ["WARN",  "nuclei_runner",        "template=exposed-backup-file slow_response=8.4s"],
    ["INFO",  "httpx_runner",         "probe 203.0.113.25:22 banner=SSH-2.0-OpenSSH_8.9"],
    ["INFO",  "ai.client",            "chat glm-5.3 tokens_in=1832 tokens_out=141 tool_call=mark_phase_complete?no"],
    ["INFO",  "subfinder_runner",     "source=hackertarget new_sub=dev.acme-corp.com"],
    ["ERROR", "sqli_probe",           "target param not found: POST /login password=*** (masked)"],
    ["INFO",  "orchestrator.loop",    "tool_selected=nuclei args_severity=high,critical"],
    ["INFO",  "scope_guard",          "target=acme-corp.com in_scope=true authorized=eng-0417"],
    ["WARN",  "rate_limiter",         "backoff 2.0s after 429 from dns-resolver"],
  ];
  function randomLog() {
    var src = LOG_SOURCES[Math.floor(Math.random() * LOG_SOURCES.length)];
    var t = new Date();
    var hh = String(t.getHours()).padStart(2, "0");
    var mm = String(t.getMinutes()).padStart(2, "0");
    var ss = String(t.getSeconds()).padStart(2, "0");
    var line = document.createElement("div");
    line.className = "term-line";
    line.innerHTML =
      '<span class="term-time">' + hh + ":" + mm + ":" + ss + "</span>" +
      '<span class="term-lv lv-' + src[0].toLowerCase() + '">' + src[0] + "</span>" +
      '<span class="term-msg">' + src[1] + " :: " + src[2] + "</span>";
    return line;
  }
  function initLogFeeds() {
    document.querySelectorAll(".terminal[data-terminal]").forEach(function (term) {
      if (term.dataset.runId) return; // terminal live — ditangani initRunStream
      var key = term.dataset.terminal;
      setInterval(function () {
        if (!liveLogsOn[key]) return;
        term.appendChild(randomLog());
        if (term.childElementCount > 200) term.removeChild(term.firstChild);
        term.scrollTop = term.scrollHeight;
      }, 1800 + Math.random() * 900);
    });
  }

  /* ---------- Run stream (Phase 3: event asli via /api/stream.php) ---------- */
  var LV_FOR = {
    tool_started: "info", tool_completed: "ok", tool_failed: "error",
    tool_output: "info", tool_skipped: "warn",
    ai_summary: "info", ai_decision: "info",
    phase_started: "info", phase_complete: "ok", run_started: "ok", run_completed: "ok",
    run_stopped: "warn", run_failed: "error",
    confirmation_required: "warn", confirmation_result: "info",
    security_event: "warn", error: "error",
    finding_recorded: "ok", report_generated: "ok",
  };
  var ACTIVE_RUN_ID = ""; // di-set initRunStream utk link download report
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function termLine(time, lv, msg) {
    var line = document.createElement("div");
    line.className = "term-line";
    // msg dari output tool (bisa berisi HTML dari target) — selalu di-escape
    line.innerHTML =
      '<span class="term-time">' + esc(time) + "</span>" +
      '<span class="term-lv lv-' + esc(lv) + '">' + esc(lv.toUpperCase()) + "</span>" +
      '<span class="term-msg">' + esc(msg) + "</span>";
    return line;
  }
  function fmtEvent(e) {
    var p = e.payload || {};
    var tool = p.tool || "";
    switch (e.type) {
      case "run_started":   return "run dimulai · template=" + (p.template || "?") + " target=" + (p.target || "?");
      case "phase_started": return "── phase '" + e.phase + "' dimulai ──";
      case "tool_started":  return tool + " :: start " + (p.binary || "") + " " + ((p.args || []).join(" ")) + " target=" + (p.target || "?");
      case "tool_completed":return tool + " :: selesai rc=" + p.returncode + " records=" + (p.record_count != null ? p.record_count : "?");
      case "tool_failed":   return tool + " :: GAGAL " + (p.error || ("rc=" + p.returncode)) + (p.stderr_tail ? " — " + String(p.stderr_tail).slice(0, 120) : "");
      case "tool_output":   return tool + " :: " + (typeof p.line === "string" ? p.line.slice(0, 160) : JSON.stringify(p).slice(0, 160));
      case "tool_skipped":  return tool + " :: DILEWATI — " + (p.reason || "user menolak");
      case "phase_complete":return "── phase '" + e.phase + "' selesai — " + (p.reason || "ok") + " ──";
      case "run_completed": return "run SELESAI";
      case "run_stopped":   return "run dihentikan";
      case "ai_summary":    return "AI :: " + String(p.summary || JSON.stringify(p)).slice(0, 240);
      case "ai_decision":   return "AI :: pilih " + (p.tool || "?") + " — " + String(p.reason || "").slice(0, 160);
      case "confirmation_required": return "⚠ KONFIRMASI DIBUTUHKAN :: tool '" + (p.tool || "?") + "' destructive — " + (p.message || "tunggu konfirmasi user");
      case "confirmation_result":   return "KONFIRMASI :: user " + (p.confirmed ? "SETUJU" : "MENOLAK");
      case "security_event":return "SECURITY :: " + JSON.stringify(p).slice(0, 200);
      case "finding_recorded": return "FINDINGS :: " + (p.message || (p.count + " finding dari " + p.tool));
      case "report_generated": return "REPORT :: " + (p.message || ("PDF dibuat " + (p.file || ""))) + " — tombol 'Report PDF' di atas untuk download";
      case "error":         return "ERROR :: " + String(p.error || "").slice(0, 200);
      default:              return e.type + " :: " + JSON.stringify(p).slice(0, 200);
    }
  }
  function moduleCardUpdate(e) {
    var p = e.payload || {};
    var card = document.querySelector('[data-module="' + (p.tool || "") + '"]');
    if (!card) return;
    var statusEl = card.querySelector("[data-mod-status]") || card.querySelector(".module-status");
    var extraEl = card.querySelector("[data-mod-extra]");
    var barEl = card.querySelector("[data-mod-bar]");
    if (!statusEl) return;
    var states = { tool_started: "running", tool_completed: "done", tool_failed: "failed" };
    var st = states[e.type];
    if (!st) return;
    // hapus state lama, set baru
    ["is-running", "is-done", "is-failed"].forEach(function (c) { card.classList.remove(c); });
    card.classList.add("is-" + st);
    statusEl.className = "module-status " + st;
    statusEl.textContent = st === "running" ? "RUNNING" : st === "done" ? "DONE" : "FAILED";
    if (e.type === "tool_started") {
      if (extraEl) extraEl.textContent = "running…";
      if (barEl) barEl.style.width = "0%";
    } else if (e.type === "tool_completed") {
      var secs = p.elapsed_ms != null ? (p.elapsed_ms / 1000).toFixed(1) + "s" : "?";
      var recs = p.record_count != null ? " · " + p.record_count + " rec" : "";
      if (extraEl) extraEl.textContent = secs + recs;
      if (barEl) barEl.style.width = "100%";
    } else if (e.type === "tool_failed") {
      if (extraEl) extraEl.textContent = (p.error || "failed").slice(0, 40);
      if (barEl) barEl.style.width = "100%";
    }
    // fallback elemen lama (module-row: durasi)
    var durEl = card.querySelector(".module-duration");
    if (durEl && e.type === "tool_completed" && p.elapsed_ms != null) {
      durEl.textContent = (p.elapsed_ms / 1000).toFixed(1) + "s";
    }
  }
  /* stepper halaman run: fase aktif highlight + durasi + status text */
  var phaseStartTs = {}; // phase -> timestamp event phase_started (ms)
  function fmtDur(ms) {
    var s = Math.max(0, Math.round(ms / 1000));
    var m = Math.floor(s / 60);
    return m + "m " + String(s % 60).padStart(2, "0") + "s";
  }
  function stepperUpdate(e) {
    var steps = document.querySelectorAll(".stepper .step[data-phase]");
    if (!steps.length) return;
    var ts = e.created_at ? new Date(e.created_at + "Z").getTime() : NaN;
    if (isNaN(ts) || Math.abs(ts - Date.now()) > 86400000) ts = Date.now(); // toleransi 1 hari (TZ mismatch)
    if (e.type === "phase_started") {
      phaseStartTs[e.phase] = ts;
      var idx = -1;
      steps.forEach(function (s, i) {
        if (s.dataset.phase === e.phase) idx = i;
      });
      steps.forEach(function (s, i) {
        if (i < idx) {
          s.classList.remove("running", "pending");
          s.classList.add("completed");
          var stt = s.querySelector("[data-step-status]");
          if (stt && stt.textContent === "PENDING") stt.textContent = "✓ COMPLETED";
          var tim = s.querySelector("[data-step-time]");
          if (tim && tim.textContent === "--") tim.textContent = "skipped";
        } else if (i === idx) {
          s.classList.remove("completed", "pending");
          s.classList.add("running");
          var st2 = s.querySelector("[data-step-status]");
          if (st2) st2.textContent = "◌ RUNNING";
        }
      });
    } else if (e.type === "phase_complete") {
      steps.forEach(function (s) {
        if (s.dataset.phase === e.phase) {
          s.classList.remove("running", "pending");
          s.classList.add("completed");
          var st = s.querySelector("[data-step-status]");
          if (st) st.textContent = "✓ COMPLETED";
          var tim = s.querySelector("[data-step-time]");
          if (tim) {
            var started = phaseStartTs[e.phase];
            tim.textContent = started ? fmtDur(ts - started) : "--";
          }
        }
      });
    } else if (e.type === "run_completed" || e.type === "run_stopped") {
      steps.forEach(function (s) {
        if (s.classList.contains("running")) {
          s.classList.remove("running");
          s.classList.add("pending");
          var st = s.querySelector("[data-step-status]");
          if (st) st.textContent = "PENDING";
        }
      });
    }
  }
  /* ops-meta halaman run: progress %, current module, elapsed, tasks */
  var runMeta = null;
  function initRunMeta(runId) {
    runMeta = {
      phasesTotal: document.querySelectorAll(".stepper .step[data-phase]").length,
      phasesDone: 0,
      toolDone: 0,
      toolTotal: 0,
      currentTool: "--",
      startTs: null,
      timerEl: document.querySelector("[data-elapsed]"),
      finalSecs: NaN,
    };
    if (runMeta.timerEl) {
      var fs = parseInt(runMeta.timerEl.dataset.elapsedFinal || "", 10);
      if (!isNaN(fs)) runMeta.finalSecs = fs;
    }
  }
  function opsMetaUpdate(e) {
    if (!runMeta) return;
    var ts = e.created_at ? new Date(e.created_at + "Z").getTime() : NaN;
    if (isNaN(ts) || Math.abs(ts - Date.now()) > 86400000) ts = Date.now(); // toleransi 1 hari (TZ mismatch)
    if (e.type === "run_started") runMeta.startTs = ts;
    if (e.type === "phase_complete") runMeta.phasesDone++;
    if (e.type === "tool_started") { runMeta.toolTotal++; runMeta.currentTool = (e.payload || {}).tool || runMeta.currentTool; }
    if (e.type === "tool_completed" || e.type === "tool_failed" || e.type === "tool_skipped") runMeta.toolDone++;

    var bar = document.querySelector("[data-progress-bar]");
    var pct = document.querySelector("[data-progress-pct]");
    if (bar && pct && runMeta.phasesTotal) {
      var p = Math.round((runMeta.phasesDone / runMeta.phasesTotal) * 100);
      bar.style.width = p + "%";
      pct.textContent = p + "%";
    }
    var cur = document.querySelector("[data-current-module]");
    if (cur && runMeta.currentTool) cur.textContent = runMeta.currentTool;
    var td = document.querySelector("[data-tasks-done]");
    if (td) td.textContent = runMeta.toolDone + " / " + runMeta.toolTotal;
  }
  var elapsedTimer = null;
  function fmtElapsedSecs(s) {
    var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), ss = s % 60;
    return (h > 0 ? h + "h " : "") + m + "m " + String(ss).padStart(2, "0") + "s";
  }
  function startElapsed() {
    if (elapsedTimer || !runMeta || !runMeta.startTs || !runMeta.timerEl) return;
    // run sudah selesai saat halaman dibuka → elapsed final dari server, timer mati
    if (!isNaN(runMeta.finalSecs)) {
      runMeta.timerEl.textContent = fmtElapsedSecs(runMeta.finalSecs);
      return;
    }
    var el = runMeta.timerEl;
    // lanjutkan dari nilai server-render (bukan mulai 0)
    var baseSecs = Math.max(0, Math.floor((Date.now() - runMeta.startTs) / 1000));
    elapsedTimer = setInterval(function () {
      var s = Math.max(0, Math.floor((Date.now() - runMeta.startTs) / 1000));
      if (s < baseSecs) s = baseSecs;
      el.textContent = fmtElapsedSecs(s);
    }, 1000);
  }
  function stopElapsed() {
    if (elapsedTimer) { clearInterval(elapsedTimer); elapsedTimer = null; }
    // bekukan nilai final saat run berakhir (bukan 0m 00s)
    if (runMeta && runMeta.timerEl) {
      var s = NaN;
      if (!isNaN(runMeta.finalSecs)) {
        s = runMeta.finalSecs;
      } else if (runMeta.startTs) {
        s = Math.max(0, Math.floor((Date.now() - runMeta.startTs) / 1000));
      }
      if (!isNaN(s)) runMeta.timerEl.textContent = fmtElapsedSecs(s);
    }
  }
  /* badge status di halaman run */
  function statusBadgeUpdate(e) {
    var badge = document.querySelector(".page-head .sev");
    if (!badge) return;
    var map = { run_started: ["RUNNING", "sev-info"], phase_started: ["RUNNING", "sev-info"],
                confirmation_required: ["AWAITING CONFIRMATION", "sev-medium"],
                run_completed: ["COMPLETED", "sev-info"], run_stopped: ["STOPPED", "sev-medium"],
                run_failed: ["FAILED", "sev-critical"] };
    var m = map[e.type];
    if (m) { badge.textContent = m[0]; badge.className = "sev " + m[1]; }
    runActionsUpdate(e.type);
  }

  /* tombol aksi run (Stop/Delete/Report/Resume) update live tanpa reload */
  function runActionsUpdate(eventType) {
    var wrap = document.querySelector("[data-run-actions]");
    if (!wrap) return;
    var status = wrap.dataset.runStatus;
    var transitions = {
      run_completed: "completed", run_stopped: "stopped", run_failed: "failed",
      confirmation_required: "awaiting_confirmation",
      run_resumed: "running",
    };
    if (transitions[eventType]) status = transitions[eventType];
    wrap.dataset.runStatus = status;

    var csrf = wrap.dataset.csrf || "";
    var runId = wrap.dataset.runId || "";
    // hapus tombol stop/resume lama
    wrap.querySelectorAll('form[action="/runs/stop"], form[action="/runs/resume"]').forEach(function (f) { f.remove(); });
    var delBtn = wrap.querySelector('form[action="/runs/delete"]');
    var rptLink = wrap.querySelector('a[href*="report/download"]');

    if (status === "completed" || status === "stopped" || status === "failed") {
      // Stop hilang, Delete muncul
      if (!delBtn) {
        var f = document.createElement("form");
        f.method = "post"; f.action = "/runs/delete";
        f.setAttribute("data-confirm", "Hapus run ini? Semua event, findings, dan report milik run ikut terhapus. Tidak bisa dibatalkan.");
        f.innerHTML = '<input type="hidden" name="csrf" value="' + esc(csrf) + '">' +
                      '<input type="hidden" name="run_id" value="' + esc(runId) + '">' +
                      '<button type="submit" class="btn btn-danger">Delete Run</button>';
        wrap.appendChild(f);
        f.addEventListener("submit", function (e) {
          if (!window.confirm(f.dataset.confirm)) e.preventDefault();
        });
      }
    } else if (status === "awaiting_confirmation") {
      if (!wrap.querySelector('form[action="/runs/resume"]')) {
        var fr = document.createElement("form");
        fr.method = "post"; fr.action = "/runs/resume";
        fr.innerHTML = '<input type="hidden" name="csrf" value="' + esc(csrf) + '">' +
                       '<input type="hidden" name="run_id" value="' + esc(runId) + '">' +
                       '<button type="submit" class="btn btn-primary">Resume</button>';
        wrap.insertBefore(fr, wrap.querySelector('a[href*="report/download"]') || null);
      }
    }
    if (status === "completed" && !rptLink) {
      var a = document.createElement("a");
      a.className = "btn";
      a.href = "/report/download?id=" + encodeURIComponent(runId);
      a.title = "Download report.pdf";
      a.textContent = "\u2b07 Report PDF";
      wrap.appendChild(a);
    }
  }
  function initRunStream() {
    document.querySelectorAll(".terminal[data-run-id]").forEach(function (term) {
      var runId = term.dataset.runId;
      ACTIVE_RUN_ID = runId;
      var lastEventId = 0;
      var pollMs = 2000; // polling 2s (PROMPT.md: 1-2s cukup utk demo)
      if (document.querySelector("[data-progress-bar]")) initRunMeta(runId);

      function tick() {
        fetch("/api/stream.php?run_id=" + encodeURIComponent(runId) + "&after=" + lastEventId)
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
            if (!data || !data.events) return;
            data.events.forEach(function (e) {
              if (e.id <= lastEventId) return;
              lastEventId = e.id;
              var time = (e.created_at || "").slice(11, 19);
              var lv = LV_FOR[e.type] || "info";
              term.appendChild(termLine(time, lv, fmtEvent(e)));
              moduleCardUpdate(e);
              stepperUpdate(e);
              statusBadgeUpdate(e);
              opsMetaUpdate(e);
              startElapsed();
              if (e.type === "confirmation_required") {
                window.dispatchEvent(new CustomEvent("oges:confirmation_required", { detail: e }));
              }
              if (e.type === "run_completed" || e.type === "run_stopped") {
                window.dispatchEvent(new CustomEvent("oges:" + e.type, { detail: e }));
                stopElapsed();
              }
            });
            if (term.childElementCount > 300) {
              while (term.childElementCount > 200) term.removeChild(term.firstChild);
            }
            if (liveLogsOn[term.dataset.terminal] !== false) term.scrollTop = term.scrollHeight;
          })
          .catch(function () { /* orchestrator down — coba lagi tick berikutnya */ });
      }
      tick();
      setInterval(tick, pollMs);
    });
  }

  /* ---------- Sparklines & charts (Chart.js) ---------- */
  var SEV_COLORS = {
    critical: "#F43F5E", high: "#EC4899", medium: "#F59E0B",
    low: "#22D3EE", info: "#64748B",
  };
  function initSparklines() {
    document.querySelectorAll("[data-spark]").forEach(function (el) {
      var data = JSON.parse(el.dataset.spark);
      var tone = el.dataset.sparkTone || "cyan";
      var color = tone === "critical" ? "#F43F5E" : tone === "high" ? "#EC4899" : "#22D3EE";
      new Chart(el.getContext("2d"), {
        type: "line",
        data: {
          labels: data.map(function (_, i) { return i; }),
          datasets: [{
            data: data,
            borderColor: color,
            borderWidth: 1.5,
            pointRadius: 0,
            tension: 0.35,
            fill: true,
            backgroundColor: function (ctx) {
              var g = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height || 28);
              g.addColorStop(0, color + "44");
              g.addColorStop(1, color + "00");
              return g;
            },
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { enabled: false } },
          scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
          animation: false,
        },
      });
    });
  }

  function initDonut() {
    document.querySelectorAll("[data-donut]").forEach(function (el) {
      var rows = JSON.parse(el.dataset.donut);
      new Chart(el, {
        type: "doughnut",
        data: {
          labels: rows.map(function (r) { return r.label; }),
          datasets: [{
            data: rows.map(function (r) { return r.value; }),
            backgroundColor: rows.map(function (r) { return SEV_COLORS[r.color] || "#64748B"; }),
            borderColor: "#0A0E18",
            borderWidth: 3,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "72%",
          plugins: { legend: { display: false } },
        },
      });
    });
  }

  function initTimeline() {
    document.querySelectorAll("[data-timeline]").forEach(function (el) {
      var d = JSON.parse(el.dataset.timeline);
      var labels = d.labels && d.labels.length ? d.labels : d.passive.map(function (_, i) { return "T" + (i + 1); });
      var passive = d.passive || [];
      var active = d.active || [];
      new Chart(el, {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Passive", data: passive, borderColor: "#64748B",
              borderWidth: 1.5, pointRadius: 2, tension: 0.35,
            },
            {
              label: "Active", data: active, borderColor: "#22D3EE",
              borderWidth: 1.5, pointRadius: 2, tension: 0.35,
              backgroundColor: "rgba(34,211,238,0.10)", fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { labels: { color: "#94A3B8", boxWidth: 10, font: { size: 10 } } },
          },
          scales: {
            x: { ticks: { color: "#64748B", font: { size: 9 } }, grid: { color: "rgba(26,34,54,0.6)" } },
            y: { ticks: { color: "#64748B", font: { size: 9 } }, grid: { color: "rgba(26,34,54,0.6)" }, beginAtZero: true },
          },
        },
      });
    });
  }

  /* ---------- Simulated elapsed timer (dummy run aktif) ---------- */
  function initElapsedTimers() {
    document.querySelectorAll("[data-elapsed]").forEach(function (el) {
      // elemen run selesai punya elapsedFinal — nilainya frozen (jangan di-timer)
      var fs = parseInt(el.dataset.elapsedFinal || "", 10);
      if (!isNaN(fs)) { el.textContent = fmtElapsedSecs(fs); return; }
      var secs = parseInt(el.dataset.elapsed || "0", 10);
      setInterval(function () {
        secs++;
        el.textContent = fmtElapsedSecs(secs);
      }, 1000);
    });
  }

  /* ---------- AI Orchestration Log live (Phase 4: data run_events asli) ---------- */
  function aiLogEntry(time, tag, tool, text) {
    var entry = document.createElement("div");
    entry.className = "ailog-entry";
    // semua field dari event payload (bisa berisi HTML dari target) — escape
    entry.innerHTML =
      '<span class="ailog-time">' + esc(time) + "</span>" +
      '<div class="ailog-body">' +
      '<span class="ailog-tag ' + esc(tag) + '">' + (tag === "ai_decision" ? "AI DECISION" : "TOOL DONE") + "</span>" +
      '<span class="ailog-tool">' + esc(tool) + "</span>" +
      '<p class="ailog-text">' + esc(text) + "</p>" +
      "</div>";
    return entry;
  }
  function initAiLogStream() {
    var box = document.querySelector("[data-ai-log-run]");
    if (!box) return; // dashboard lama tanpa atribut → dummy tetap
    var runId = box.dataset.aiLogRun;
    var lastEventId = 0;
    function tick() {
      fetch("/api/stream.php?run_id=" + encodeURIComponent(runId) + "&after=" + lastEventId)
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data || !data.events) return;
          data.events.forEach(function (e) {
            if (e.id <= lastEventId) return;
            if (e.type === "ai_decision") {
              var p = e.payload || {};
              box.appendChild(aiLogEntry((e.created_at || "").slice(11, 19), "ai_decision",
                p.tool || "?", String(p.reason || "").slice(0, 300)));
            } else if (e.type === "ai_summary") {
              var p2 = e.payload || {};
              box.appendChild(aiLogEntry((e.created_at || "").slice(11, 19), "tool_completed",
                p2.tool || "?", String(p2.summary || "").slice(0, 400)));
            }
            lastEventId = e.id;
          });
          while (box.childElementCount > 30) box.removeChild(box.firstChild);
          box.scrollTop = box.scrollHeight;
        })
        .catch(function () {});
    }
    tick();
    setInterval(tick, 3000);
  }

  /* ---------- SPA navigation (anti full-reload antar menu) ---------- */
  function spaNavigable(url) {
    if (!url || url.charAt(0) !== "/") return false;
    // halaman auth & endpoint khusus biarkan full load
    if (/^\/(login|logout|register|pending-approval)\b/.test(url)) return false;
    if (/^\/(admin\/|api\/)/.test(url)) return false; // POST/download/admin biarkan normal
    if (/^\/(report\/download|audit\/export)/.test(url)) return false;
    return true;
  }
  function spaSwap(url, push) {
    var main = document.getElementById("main-content");
    if (!main) return false;
    // skeleton loading state (PROMPT 4.11: bukan layar putih)
    main.style.opacity = "0.45";
    fetch(url, { headers: { "X-Requested-With": "oges-spa" }, credentials: "same-origin" })
      .then(function (r) {
        if (!r.ok) throw new Error(r.status);
        if (r.redirected && /login/.test(r.url)) { window.location = r.url; return null; }
        return r.text();
      })
      .then(function (html) {
        if (!html) return;
        // parse dokumen penuh, ambil konten main + title
        var doc = new DOMParser().parseFromString(html, "text/html");
        var newMain = doc.getElementById("main-content");
        if (!newMain) { window.location = url; return; }
        main.replaceWith(newMain);
        if (push !== false) history.pushState({ ogesSpa: true, url: url }, "", url);
        document.title = doc.title || document.title;
        // re-init semua komponen JS di konten baru
        boot();
        // sidebar active state ikut halaman baru
        var key = doc.querySelector(".sidebar .nav-item.active");
        document.querySelectorAll(".sidebar .nav-item").forEach(function (a) {
          a.classList.toggle("active", key ? a.getAttribute("data-nav") === key.getAttribute("data-nav") : a.getAttribute("href") === url);
        });
        newMain.style.opacity = "";
        // scroll ke atas konten
        window.scrollTo(0, 0);
        main = null;
      })
      .catch(function () { window.location = url; }); // fallback full load
    return true;
  }
  function initSpaNav() {
    document.addEventListener("click", function (e) {
      var a = e.target.closest ? e.target.closest("a") : null;
      if (!a || a.target || a.hasAttribute("download") || a.protocol.indexOf("http") === 0 && a.host !== window.location.host) return;
      var href = a.getAttribute("href");
      if (!href || href.charAt(0) === "#" || href.indexOf("javascript:") === 0) return;
      // link dalam konten hasil swap (report download dll) biarkan default
      if (/report\/download|audit\/export|^\/logout/.test(href)) return;
      if (!spaNavigable(href)) return;
      e.preventDefault();
      spaSwap(href, true);
    });
    window.addEventListener("popstate", function (e) {
      if (e.state && e.state.ogesSpa) spaSwap(e.state.url, false);
      else if (location.pathname !== "/login") spaSwap(location.pathname + location.search, false);
    });
  }

  /* ---------- Boot ---------- */
  function boot() {
    initTabs(document);
    initModals();
    initTerminals();
    initLogFeeds();
    initRunStream();
    initAiLogStream();
    if (window.Chart) { initSparklines(); initDonut(); initTimeline(); }
    initElapsedTimers();
  }
  function bootOnce() {
    boot();
    initSpaNav();
    initSidebarDrawer();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootOnce);
  } else {
    bootOnce();
  }
})();
