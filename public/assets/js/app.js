/* OGES-SHIELD — app JS (Phase 1: interaksi dummy).
   Data nyata menyusul Phase 3+ via /api/*. */

(function () {
  "use strict";

  /* ---------- Environment pill toggle ---------- */
  document.querySelectorAll(".env-pill").forEach(function (pill) {
    pill.addEventListener("click", function () {
      document.cookie = "oges_env=" + pill.dataset.env + ";path=/;max-age=31536000";
      document.querySelectorAll(".env-pill").forEach(function (p) {
        p.classList.toggle("active", p === pill);
      });
    });
  });

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

  /* ---------- Dummy live log generator (Phase 1; diganti polling /api/stream.php Phase 3) ---------- */
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
      var key = term.dataset.terminal;
      setInterval(function () {
        if (!liveLogsOn[key]) return;
        term.appendChild(randomLog());
        if (term.childElementCount > 200) term.removeChild(term.firstChild);
        term.scrollTop = term.scrollHeight;
      }, 1800 + Math.random() * 900);
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
      new Chart(el, {
        type: "line",
        data: {
          labels: d.labels,
          datasets: [
            {
              label: "Passive", data: d.passive, borderColor: "#64748B",
              borderWidth: 1.5, pointRadius: 2, tension: 0.35,
            },
            {
              label: "Active", data: d.active, borderColor: "#22D3EE",
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
      var secs = parseInt(el.dataset.elapsed || "0", 10);
      setInterval(function () {
        secs++;
        var h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60), s = secs % 60;
        el.textContent = (h > 0 ? h + "h " : "") + m + "m " + String(s).padStart(2, "0") + "s";
      }, 1000);
    });
  }

  /* ---------- Boot ---------- */
  function boot() {
    initTabs(document);
    initModals();
    initTerminals();
    initLogFeeds();
    if (window.Chart) { initSparklines(); initDonut(); initTimeline(); }
    initElapsedTimers();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
