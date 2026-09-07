<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="console-config" content="{{ json_encode(['base' => $base, 'pollIntervalMs' => $pollIntervalMs, 'unit' => $unit]) }}">
<title>Alert Engine Console</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
@verbatim
<style>
  :root {
    --bg:            #0F172A;
    --surface:       #1E293B;
    --surface-2:     #172033;
    --muted:         #272F42;
    --border:        #334155;
    --border-strong: #475569;
    --fg:            #F8FAFC;
    --fg-2:          #CBD5E1;
    --fg-muted:      #94A3B8;
    --accent:        #22C55E;
    --accent-dim:    rgba(34, 197, 94, .13);
    --sky:           #38BDF8;
    --sky-dim:       rgba(56, 189, 248, .13);
    --amber:         #FBBF24;
    --amber-dim:     rgba(251, 191, 36, .13);
    --red:           #EF4444;
    --red-dim:       rgba(239, 68, 68, .13);

    --sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;

    --r-card: 10px;
    --r-ctl: 7px;
    color-scheme: dark;
  }

  * { box-sizing: border-box; }
  html { -webkit-text-size-adjust: 100%; }
  /* A display rule on a class would otherwise beat the hidden attribute. */
  [hidden] { display: none !important; }

  body {
    margin: 0;
    background: var(--bg);
    color: var(--fg);
    font-family: var(--sans);
    font-size: 15px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }

  .mono { font-family: var(--mono); font-variant-numeric: tabular-nums; }

  /* ------------------------------------------------------------- header */

  header {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(15, 23, 42, .92);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--border);
  }
  .bar {
    max-width: 1400px;
    margin: 0 auto;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
  }
  .brand { display: flex; align-items: center; gap: 10px; margin-right: auto; }
  .brand svg { width: 20px; height: 20px; color: var(--accent); }
  .brand h1 { font-size: 15px; font-weight: 600; margin: 0; letter-spacing: -0.01em; }
  .brand span { font-size: 12px; color: var(--fg-muted); }

  .status { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--fg-2); }
  .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--fg-muted); flex: none; }
  .dot.live { background: var(--accent); box-shadow: 0 0 0 3px var(--accent-dim); animation: pulse 2s ease-in-out infinite; }
  .dot.warn { background: var(--amber); box-shadow: 0 0 0 3px var(--amber-dim); }
  .dot.down { background: var(--red); box-shadow: 0 0 0 3px var(--red-dim); }
  @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .45; } }

  .price-now { display: flex; align-items: baseline; gap: 10px; }
  .price-now .v { font-family: var(--mono); font-size: 26px; font-weight: 600; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
  .price-now .u { font-size: 12px; color: var(--fg-muted); }
  .price-now .d { font-family: var(--mono); font-size: 13px; font-variant-numeric: tabular-nums; }
  .d.up { color: var(--accent); }
  .d.down { color: var(--red); }
  .d.flat { color: var(--fg-muted); }

  /* --------------------------------------------------------------- grid */

  main {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
    display: grid;
    grid-template-columns: minmax(0, 1.9fr) minmax(320px, 1fr);
    gap: 20px;
    align-items: start;
  }
  .col { display: grid; gap: 20px; align-content: start; min-width: 0; }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-card);
    overflow: hidden;
  }
  .card > header {
    position: static;
    background: none;
    backdrop-filter: none;
    border-bottom: 1px solid var(--border);
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .card > header h2 {
    font-size: 13px;
    font-weight: 600;
    margin: 0;
    letter-spacing: 0.01em;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .card > header h2 svg { width: 15px; height: 15px; color: var(--fg-muted); }
  .card > header .spacer { margin-left: auto; }
  .card .body { padding: 16px; }
  .card .body.flush { padding: 0; }

  /* ------------------------------------------------------------ controls */

  button {
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 500;
    color: var(--fg);
    background: var(--muted);
    border: 1px solid var(--border-strong);
    border-radius: var(--r-ctl);
    padding: 0 14px;
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    cursor: pointer;
    transition: background 160ms ease, border-color 160ms ease, opacity 160ms ease;
  }
  button svg { width: 15px; height: 15px; flex: none; }
  button:hover:not(:disabled) { background: #313B52; border-color: #5A6480; }
  button:active:not(:disabled) { background: #3A4359; }
  button:disabled { opacity: .45; cursor: not-allowed; }
  button.primary { background: var(--accent); border-color: var(--accent); color: #06240F; font-weight: 600; }
  button.primary:hover:not(:disabled) { background: #34D06A; border-color: #34D06A; }
  button.danger { color: var(--red); }
  button.danger:hover:not(:disabled) { background: var(--red-dim); border-color: var(--red); }
  button.on { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
  button.icon { min-width: 38px; padding: 0 9px; }
  button.sm { min-height: 32px; font-size: 12.5px; padding: 0 10px; }

  :is(button, input, select, a):focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
  }

  label { display: block; font-size: 12px; font-weight: 500; color: var(--fg-2); margin-bottom: 6px; }
  .hint { font-size: 11.5px; color: var(--fg-muted); margin-top: 6px; }

  input, select {
    font-family: var(--mono);
    font-size: 13.5px;
    font-variant-numeric: tabular-nums;
    color: var(--fg);
    background: var(--surface-2);
    border: 1px solid var(--border-strong);
    border-radius: var(--r-ctl);
    padding: 0 11px;
    min-height: 38px;
    width: 100%;
    transition: border-color 160ms ease;
  }
  select { font-family: var(--sans); cursor: pointer; }
  input:hover, select:hover { border-color: #5A6480; }
  input::placeholder { color: #64748B; }

  .field + .field { margin-top: 13px; }
  .row { display: flex; gap: 9px; align-items: flex-end; }
  .row > .field { flex: 1; margin-top: 0; }
  .split { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }

  /* --------------------------------------------------------------- chart */

  .chart-wrap { padding: 8px 4px 0; }
  .chart-wrap svg { display: block; width: 100%; height: auto; }
  .chart-legend {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    padding: 6px 16px 14px;
    font-size: 11.5px;
    color: var(--fg-muted);
  }
  .chart-legend span { display: inline-flex; align-items: center; gap: 6px; }
  .swatch { width: 14px; height: 2px; border-radius: 1px; flex: none; }
  .swatch.line { background: var(--accent); }
  .swatch.target { background: var(--sky); height: 0; border-top: 2px dashed var(--sky); }

  .empty {
    padding: 34px 16px;
    text-align: center;
    color: var(--fg-muted);
    font-size: 13px;
  }
  .empty svg { width: 22px; height: 22px; margin-bottom: 8px; opacity: .55; }

  /* --------------------------------------------------------------- table */

  .scroll-x { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { text-align: left; padding: 9px 14px; border-bottom: 1px solid var(--border); white-space: nowrap; }
  th {
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--fg-muted);
    font-weight: 500;
    background: var(--surface-2);
    position: sticky;
    top: 0;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr { transition: background 160ms ease; }
  tbody tr:hover { background: var(--surface-2); }
  td.n { font-family: var(--mono); font-variant-numeric: tabular-nums; }
  td.dim { color: var(--fg-muted); }
  td.act { text-align: right; }

  .tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--mono);
    font-size: 11px;
    font-weight: 500;
    padding: 2px 7px;
    border-radius: 4px;
    border: 1px solid;
  }
  .tag svg { width: 11px; height: 11px; }
  .tag.active  { color: var(--sky);    background: var(--sky-dim);    border-color: rgba(56,189,248,.35); }
  .tag.sending { color: var(--amber);  background: var(--amber-dim);  border-color: rgba(251,191,36,.35); }
  .tag.failed  { color: var(--red);    background: var(--red-dim);    border-color: rgba(239,68,68,.35); }
  .tag.above   { color: var(--accent); background: var(--accent-dim); border-color: rgba(34,197,94,.35); }
  .tag.below   { color: var(--amber);  background: var(--amber-dim);  border-color: rgba(251,191,36,.35); }

  /* --------------------------------------------------------------- stats */

  .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: var(--border); }
  .stat { background: var(--surface); padding: 12px 14px; }
  .stat dt { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--fg-muted); margin: 0 0 4px; }
  .stat dd { margin: 0; font-family: var(--mono); font-size: 19px; font-weight: 600; font-variant-numeric: tabular-nums; }
  .stat dd.zero { color: var(--fg-muted); }
  .stat dd.hot { color: var(--amber); }
  .stat dd.bad { color: var(--red); }

  /* --------------------------------------------------------------- steps */

  .steps { list-style: none; margin: 0; padding: 0; }
  .steps li {
    display: flex;
    gap: 11px;
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    align-items: flex-start;
  }
  .steps li:last-child { border-bottom: none; }
  .steps .mark {
    width: 19px; height: 19px;
    border-radius: 50%;
    border: 1.5px solid var(--border-strong);
    display: grid;
    place-items: center;
    flex: none;
    margin-top: 1px;
    color: var(--fg-muted);
  }
  .steps .mark svg { width: 11px; height: 11px; }
  .steps li[data-status="running"] .mark { border-color: var(--sky); color: var(--sky); animation: spin 1.1s linear infinite; }
  .steps li[data-status="pass"] .mark { border-color: var(--accent); background: var(--accent); color: #06240F; }
  .steps li[data-status="fail"] .mark { border-color: var(--red); background: var(--red); color: #2A0B0B; }
  @keyframes spin { to { transform: rotate(360deg); } }

  .steps .txt { flex: 1; min-width: 0; }
  .steps .label { font-size: 13px; font-weight: 500; }
  .steps li[data-status="pending"] .label { color: var(--fg-muted); }
  .steps .detail { font-family: var(--mono); font-size: 11.5px; color: var(--fg-muted); margin-top: 2px; word-break: break-word; white-space: normal; }
  .steps li[data-status="fail"] .detail { color: var(--red); }
  .steps .ms { font-family: var(--mono); font-size: 11.5px; color: var(--fg-muted); font-variant-numeric: tabular-nums; flex: none; }

  .verdict { padding: 11px 16px; font-size: 13px; font-weight: 500; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
  .verdict svg { width: 15px; height: 15px; }
  .verdict.pass { color: var(--accent); background: var(--accent-dim); }
  .verdict.fail { color: var(--red); background: var(--red-dim); }

  /* --------------------------------------------------------------- inbox */

  .mail { display: flex; gap: 11px; padding: 11px 16px; border-bottom: 1px solid var(--border); align-items: flex-start; }
  .mail:last-child { border-bottom: none; }
  .mail svg { width: 15px; height: 15px; color: var(--accent); flex: none; margin-top: 2px; }
  .mail .s { font-size: 13px; font-weight: 500; }
  .mail .m { font-family: var(--mono); font-size: 11.5px; color: var(--fg-muted); margin-top: 2px; }

  /* -------------------------------------------------------------- toasts */

  .toasts {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 100;
    display: grid;
    gap: 9px;
    max-width: min(380px, calc(100vw - 40px));
  }
  .toast {
    background: var(--surface);
    border: 1px solid var(--border-strong);
    border-left: 3px solid var(--fg-muted);
    border-radius: var(--r-ctl);
    padding: 11px 14px;
    font-size: 13px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .45);
    animation: rise 200ms ease-out;
  }
  .toast.ok { border-left-color: var(--accent); }
  .toast.err { border-left-color: var(--red); }
  .toast .t { font-weight: 600; }
  .toast .b { color: var(--fg-2); margin-top: 2px; font-size: 12.5px; }
  @keyframes rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

  .sr {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
  }

  @media (max-width: 1080px) {
    main { grid-template-columns: minmax(0, 1fr); }
  }
  @media (max-width: 640px) {
    .bar { padding: 10px 16px; gap: 14px; }
    main { padding: 16px; gap: 16px; }
    .price-now .v { font-size: 21px; }
    .stats { grid-template-columns: repeat(2, 1fr); }
    /* Touch targets on small screens: 44px minimum. */
    button, input, select { min-height: 44px; }
    button.sm { min-height: 44px; }
    button.icon { min-width: 44px; }
  }

  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; }
  }
</style>
</head>
<body>

<svg class="sr" aria-hidden="true">
  <defs>
    <g id="i-activity"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></g>
    <g id="i-play"><path d="M6 4l14 8-14 8z"/></g>
    <g id="i-stop"><rect x="6" y="6" width="12" height="12" rx="2"/></g>
    <g id="i-plus"><path d="M12 5v14M5 12h14"/></g>
    <g id="i-trash"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></g>
    <g id="i-mail"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></g>
    <g id="i-check"><path d="M20 6 9 17l-5-5"/></g>
    <g id="i-x"><path d="M18 6 6 18M6 6l12 12"/></g>
    <g id="i-alert"><path d="m10.3 3.9-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></g>
    <g id="i-clock"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></g>
    <g id="i-layers"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 14 9 5 9-5"/></g>
    <g id="i-chart"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></g>
    <g id="i-bell"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></g>
    <g id="i-beaker"><path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-9V3"/><path d="M7 15h10"/></g>
    <g id="i-dot"><circle cx="12" cy="12" r="4"/></g>
  </defs>
</svg>

<header>
  <div class="bar">
    <div class="brand">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-activity"/></svg>
      <div>
        <h1>Alert Engine Console</h1>
        <span>development only</span>
      </div>
    </div>

    <div class="price-now">
      <span class="v" id="price">&mdash;</span>
      <span class="u" id="unit"></span>
      <span class="d flat" id="delta"></span>
    </div>

    <div class="status">
      <span class="dot" id="live-dot"></span>
      <span id="live-text">connecting</span>
    </div>
  </div>
</header>

<main>
  <div class="col">

    <section class="card">
      <header>
        <h2>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-chart"/></svg>
          Price feed
        </h2>
        <span class="spacer"></span>
        <span class="mono" id="tick-count" style="font-size:11.5px;color:var(--fg-muted)"></span>
      </header>
      <div class="body flush">
        <div class="chart-wrap">
          <div id="chart-svg"></div>
          <p class="empty" id="chart-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-clock"/></svg><br>
            Waiting for the first tick. Start the stack with <span class="mono">make up</span>, or push a price below.
          </p>
        </div>
        <div class="chart-legend" id="chart-legend" hidden>
          <span><i class="swatch line"></i> observed price</span>
          <span><i class="swatch target"></i> alert target</span>
          <span id="chart-range"></span>
        </div>
      </div>
    </section>

    <section class="card">
      <header>
        <h2>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-bell"/></svg>
          Alerts
        </h2>
        <span class="spacer"></span>
        <span class="mono" id="alert-count" style="font-size:11.5px;color:var(--fg-muted)"></span>
      </header>
      <div class="body flush">
        <div class="scroll-x">
          <table>
            <caption class="sr">Price alerts with their delivery status</caption>
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Owner</th>
                <th scope="col">Watching</th>
                <th scope="col">Target</th>
                <th scope="col">Status</th>
                <th scope="col">Triggered</th>
                <th scope="col">Tries</th>
                <th scope="col"><span class="sr">Actions</span></th>
              </tr>
            </thead>
            <tbody id="alerts-body"></tbody>
          </table>
        </div>
        <p class="empty" id="alerts-empty">No alerts yet. Create one from the panel on the right.</p>
      </div>
    </section>

    <section class="card">
      <header>
        <h2>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-mail"/></svg>
          Delivered mail
        </h2>
        <span class="spacer"></span>
        <button class="sm" id="clear-inbox" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-trash"/></svg>
          Clear
        </button>
      </header>
      <div class="body flush">
        <div id="inbox"></div>
        <p class="empty" id="inbox-empty">Nothing delivered yet.</p>
      </div>
    </section>

  </div>

  <div class="col">

    <section class="card">
      <header>
        <h2>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-beaker"/></svg>
          End-to-end test
        </h2>
        <span class="spacer"></span>
        <button class="primary" id="run-test" type="button">
          <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><use href="#i-play"/></svg>
          Run
        </button>
      </header>
      <div id="verdict"></div>
      <ol class="steps" id="steps"></ol>
      <div class="body">
        <p class="hint" style="margin:0">
          Creates an alert above the current price, walks the feed across it, and waits for the email &mdash; then
          checks the alert deleted itself and does not fire twice. Needs the watcher and a worker running.
        </p>
      </div>
    </section>

    <section class="card">
      <header>
        <h2>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-layers"/></svg>
          Pipeline
        </h2>
      </header>
      <dl class="stats">
        <div class="stat"><dt>Above</dt><dd id="s-above">0</dd></div>
        <div class="stat"><dt>Below</dt><dd id="s-below">0</dd></div>
        <div class="stat"><dt>In flight</dt><dd id="s-inflight">0</dd></div>
        <div class="stat"><dt>Queued</dt><dd id="s-queued">0</dd></div>
        <div class="stat"><dt>Failed jobs</dt><dd id="s-failed">0</dd></div>
        <div class="stat"><dt>Index</dt><dd id="s-ready" style="font-size:13px">&mdash;</dd></div>
      </dl>
    </section>

    <section class="card">
      <header>
        <h2>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-activity"/></svg>
          Push prices
        </h2>
      </header>
      <div class="body">
        <form id="push-form">
          <div class="field">
            <label for="push-input">Next prices the feed will serve</label>
            <div class="row">
              <div class="field"><input id="push-input" name="prices" inputmode="decimal" placeholder="2690 2701.25" autocomplete="off"></div>
              <button class="primary" type="submit">Push</button>
            </div>
            <p class="hint">Space-separated. Each value is served on one tick, in order, then the walk resumes from the last one.</p>
          </div>
        </form>

        <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">

        <div class="split">
          <div class="field">
            <label for="auto-interval">Auto push every</label>
            <select id="auto-interval">
              <option value="1000">1 second</option>
              <option value="2000" selected>2 seconds</option>
              <option value="5000">5 seconds</option>
            </select>
          </div>
          <div class="field">
            <label for="auto-drift">Drift</label>
            <select id="auto-drift">
              <option value="up">Upward</option>
              <option value="random" selected>Random walk</option>
              <option value="down">Downward</option>
            </select>
          </div>
        </div>
        <div class="field">
          <button id="auto-toggle" type="button" style="width:100%" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><use href="#i-play"/></svg>
            <span>Start auto push</span>
          </button>
          <p class="hint">Moves the price by up to 4.00 a step, so alerts trigger on their own.</p>
        </div>
      </div>
    </section>

    <section class="card">
      <header>
        <h2>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-plus"/></svg>
          New alert
        </h2>
      </header>
      <div class="body">
        <form id="alert-form">
          <div class="split">
            <div class="field">
              <label for="alert-target">Target price</label>
              <input id="alert-target" name="target_price" inputmode="decimal" placeholder="2750.00" autocomplete="off" required>
            </div>
            <div class="field">
              <label for="alert-direction">Direction</label>
              <select id="alert-direction" name="direction">
                <option value="">Infer from price</option>
                <option value="above">Above</option>
                <option value="below">Below</option>
              </select>
            </div>
          </div>
          <div class="field">
            <button class="primary" type="submit" style="width:100%">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-plus"/></svg>
              Create alert
            </button>
            <p class="hint">Created as the demo account. A target equal to the current price is refused as ambiguous.</p>
          </div>
        </form>
      </div>
    </section>

  </div>
</main>

<div class="toasts" id="toasts" role="status" aria-live="polite"></div>

<script>
(function () {
  'use strict';

  var cfg = JSON.parse(document.querySelector('meta[name="console-config"]').content);
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var POLL = Math.max(500, cfg.pollIntervalMs || 1000);
  var BASE = cfg.base || '/dev/';

  var $ = function (id) { return document.getElementById(id); };
  var history = [];          // { price: Number, at: String }
  var lastReceivedAt = null;
  var lastTickSeen = 0;
  var ticks = 0;
  var latest = null;         // last state payload
  var polling = false;
  var autoTimer = null;
  var testRunning = false;

  // ------------------------------------------------------------- helpers

  function fmt(n, dp) {
    return Number(n).toLocaleString('en-US', {
      minimumFractionDigits: dp === undefined ? 2 : dp,
      maximumFractionDigits: dp === undefined ? 2 : dp
    });
  }

  function toast(kind, title, body) {
    var el = document.createElement('div');
    el.className = 'toast ' + kind;
    var t = document.createElement('div');
    t.className = 't';
    t.textContent = title;
    el.appendChild(t);
    if (body) {
      var b = document.createElement('div');
      b.className = 'b';
      b.textContent = body;
      el.appendChild(b);
    }
    $('toasts').appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }

  function api(method, url, body) {
    var opts = {
      method: method,
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
    };
    if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch(url, opts).then(function (res) {
      if (res.status === 204) return null;
      return res.json().catch(function () { return null; }).then(function (data) {
        if (!res.ok) {
          var msg = (data && data.message) || ('Request failed with status ' + res.status);
          if (data && data.errors) {
            msg = Object.keys(data.errors).map(function (k) { return data.errors[k][0]; }).join(' ');
          }
          var err = new Error(msg);
          err.status = res.status;
          throw err;
        }
        return data;
      });
    });
  }

  function icon(id, filled) {
    return '<svg viewBox="0 0 24 24" fill="' + (filled ? 'currentColor' : 'none') +
      '" stroke="' + (filled ? 'none' : 'currentColor') +
      '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#' + id + '"/></svg>';
  }

  // -------------------------------------------------------------- render

  function renderHeader(state) {
    var p = state.price;
    if (!p.available) {
      $('live-dot').className = 'dot down';
      $('live-text').textContent = 'index unreachable';
      return;
    }
    $('unit').textContent = p.unit;

    if (p.price === null) {
      $('price').textContent = '—';
      $('live-dot').className = 'dot warn';
      $('live-text').textContent = 'no price yet';
      return;
    }

    $('price').textContent = fmt(p.price);

    if (p.received_at !== lastReceivedAt) {
      lastReceivedAt = p.received_at;
      lastTickSeen = Date.now();
      ticks++;
      history.push({ price: Number(p.price), at: p.received_at });
      if (history.length > 120) history.shift();
    }

    var prev = history.length > 1 ? history[history.length - 2].price : null;
    var d = $('delta');
    if (prev === null) {
      d.textContent = '';
    } else {
      var diff = Number(p.price) - prev;
      d.className = 'd ' + (diff > 0 ? 'up' : diff < 0 ? 'down' : 'flat');
      d.textContent = (diff > 0 ? '+' : '') + fmt(diff);
    }

    var quiet = Date.now() - lastTickSeen;
    if (quiet < POLL * 3) {
      $('live-dot').className = 'dot live';
      $('live-text').textContent = 'live';
    } else {
      $('live-dot').className = 'dot warn';
      $('live-text').textContent = 'no tick for ' + Math.round(quiet / 1000) + 's';
    }
    $('tick-count').textContent = ticks + ' ticks';
  }

  function renderChart(state) {
    if (history.length < 2) return;

    $('chart-empty').hidden = true;
    $('chart-legend').hidden = false;

    var W = 720, H = 190, PL = 54, PR = 14, PT = 12, PB = 20;
    var targets = (state.alerts || [])
      .filter(function (a) { return a.status === 'active'; })
      .map(function (a) { return { v: Number(a.target_price), dir: a.direction }; })
      .slice(0, 8);

    var values = history.map(function (h) { return h.price; });
    var lo = Math.min.apply(null, values);
    var hi = Math.max.apply(null, values);
    targets.forEach(function (t) { lo = Math.min(lo, t.v); hi = Math.max(hi, t.v); });

    var pad = (hi - lo) * 0.12 || 1;
    lo -= pad; hi += pad;

    var x = function (i) { return PL + (i / (history.length - 1)) * (W - PL - PR); };
    var y = function (v) { return PT + (1 - (v - lo) / (hi - lo)) * (H - PT - PB); };

    var pts = history.map(function (h, i) { return x(i) + ',' + y(h.price); }).join(' ');
    var area = 'M' + x(0) + ',' + (H - PB) + ' L' + pts.split(' ').join(' L') + ' L' + x(history.length - 1) + ',' + (H - PB) + ' Z';

    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Observed gold price over the last ' +
      history.length + ' ticks, currently ' + fmt(values[values.length - 1]) + '">';

    [0, 0.5, 1].forEach(function (f) {
      var v = lo + (hi - lo) * f;
      svg += '<line x1="' + PL + '" y1="' + y(v) + '" x2="' + (W - PR) + '" y2="' + y(v) +
        '" stroke="#334155" stroke-width="1"/>';
      svg += '<text x="' + (PL - 8) + '" y="' + (y(v) + 4) + '" text-anchor="end" fill="#94A3B8" ' +
        'font-family="IBM Plex Mono, monospace" font-size="10">' + fmt(v) + '</text>';
    });

    targets.forEach(function (t) {
      if (t.v < lo || t.v > hi) return;
      svg += '<line x1="' + PL + '" y1="' + y(t.v) + '" x2="' + (W - PR) + '" y2="' + y(t.v) +
        '" stroke="#38BDF8" stroke-width="1.4" stroke-dasharray="5 4" opacity=".85"/>';
      svg += '<text x="' + (W - PR) + '" y="' + (y(t.v) - 5) + '" text-anchor="end" fill="#38BDF8" ' +
        'font-family="IBM Plex Mono, monospace" font-size="10">' + t.dir + ' ' + fmt(t.v) + '</text>';
    });

    svg += '<path d="' + area + '" fill="#22C55E" opacity=".10"/>';
    svg += '<polyline points="' + pts + '" fill="none" stroke="#22C55E" stroke-width="2" ' +
      'stroke-linejoin="round" stroke-linecap="round"/>';
    svg += '<circle cx="' + x(history.length - 1) + '" cy="' + y(values[values.length - 1]) +
      '" r="3.5" fill="#22C55E" stroke="#0F172A" stroke-width="2"/>';
    svg += '</svg>';

    $('chart-svg').innerHTML = svg;
    $('chart-range').textContent = history.length + ' ticks shown';
  }

  function renderAlerts(state) {
    var rows = state.alerts || [];
    $('alert-count').textContent = rows.length ? rows.length + ' shown' : '';
    $('alerts-empty').hidden = rows.length > 0;

    $('alerts-body').innerHTML = rows.map(function (a) {
      var trig = a.triggered_price ? fmt(a.triggered_price) : '—';
      var owner = a.owner ? a.owner.split('@')[0] : '—';
      var statusIcon = a.status === 'failed' ? 'i-alert' : a.status === 'sending' ? 'i-clock' : 'i-dot';
      return '<tr>' +
        '<td class="n dim">' + a.id + '</td>' +
        '<td class="dim">' + owner + '</td>' +
        '<td><span class="tag ' + a.direction + '">' + a.direction + '</span></td>' +
        '<td class="n">' + fmt(a.target_price) + '</td>' +
        '<td><span class="tag ' + a.status + '">' + icon(statusIcon, a.status === 'active') + a.status + '</span></td>' +
        '<td class="n dim">' + trig + '</td>' +
        '<td class="n dim">' + a.attempts + '</td>' +
        '<td class="act"><button class="sm danger icon" data-cancel="' + a.id +
          '" type="button" aria-label="Cancel alert ' + a.id + '">' + icon('i-trash') + '</button></td>' +
        '</tr>';
    }).join('');
  }

  function renderStats(state) {
    var i = state.index, q = state.queue;
    $('s-above').textContent = i.above;
    $('s-below').textContent = i.below;
    $('s-inflight').textContent = i.inflight;
    $('s-queued').textContent = q.pending;
    $('s-failed').textContent = q.failed;

    $('s-inflight').className = i.inflight > 0 ? 'hot' : 'zero';
    $('s-queued').className = q.pending > 0 ? 'hot' : 'zero';
    $('s-failed').className = q.failed > 0 ? 'bad' : 'zero';
    $('s-above').className = i.above > 0 ? '' : 'zero';
    $('s-below').className = i.below > 0 ? '' : 'zero';

    var ready = $('s-ready');
    if (!i.available) { ready.textContent = 'unreachable'; ready.className = 'bad'; }
    else if (i.ready) { ready.textContent = 'ready'; ready.className = ''; }
    else { ready.textContent = 'not built'; ready.className = 'hot'; }
  }

  function renderInbox(state) {
    var box = state.inbox;
    if (!box.available) {
      $('inbox').innerHTML = '';
      $('inbox-empty').hidden = false;
      $('inbox-empty').textContent = 'Mailpit is not reachable. With the Compose stack it runs at localhost:8025.';
      return;
    }
    var msgs = box.messages || [];
    $('inbox-empty').hidden = msgs.length > 0;
    $('inbox-empty').textContent = 'Nothing delivered yet.';
    $('inbox').innerHTML = msgs.map(function (m) {
      var when = m.at ? new Date(m.at).toLocaleTimeString() : '';
      return '<div class="mail">' + icon('i-mail') +
        '<div><div class="s">' + escapeHtml(m.subject) + '</div>' +
        '<div class="m">' + escapeHtml(m.to) + ' · ' + when + '</div></div></div>';
    }).join('');
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  // ---------------------------------------------------------------- poll

  function poll() {
    if (polling || document.hidden) return Promise.resolve(latest);
    polling = true;
    return api('GET', BASE + 'state').then(function (state) {
      latest = state;
      renderHeader(state);
      renderChart(state);
      renderAlerts(state);
      renderStats(state);
      renderInbox(state);
      return state;
    }).catch(function (e) {
      $('live-dot').className = 'dot down';
      $('live-text').textContent = 'console offline';
      if (window.console && console.error) console.error('console poll failed', e);
      return null;
    }).finally(function () { polling = false; });
  }

  // ------------------------------------------------------------- actions

  function pushPrices(values) {
    return api('POST', BASE + 'prices', { prices: values });
  }

  function currentPrice() {
    return latest && latest.price && latest.price.price !== null ? Number(latest.price.price) : null;
  }

  $('push-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var raw = $('push-input').value.trim();
    if (!raw) { toast('err', 'Nothing to push', 'Enter one or more prices, separated by spaces.'); return; }
    var values = raw.split(/[\s,]+/).filter(Boolean);
    var btn = e.target.querySelector('button');
    btn.disabled = true;
    pushPrices(values).then(function (r) {
      toast('ok', 'Queued ' + r.pushed.length + ' price(s)', r.pushed.join(' → '));
      $('push-input').value = '';
    }).catch(function (err) {
      toast('err', 'Could not push', err.message);
    }).finally(function () { btn.disabled = false; });
  });

  $('alert-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var target = $('alert-target').value.trim();
    var dir = $('alert-direction').value;
    var btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    api('POST', BASE + 'alerts', { target_price: target, direction: dir || null }).then(function (r) {
      toast('ok', 'Alert #' + r.alert.id + ' created', 'Watching ' + r.alert.direction + ' ' + fmt(r.alert.target_price));
      $('alert-target').value = '';
      poll();
    }).catch(function (err) {
      toast('err', 'Could not create the alert', err.message);
    }).finally(function () { btn.disabled = false; });
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-cancel]');
    if (!btn) return;
    var id = btn.getAttribute('data-cancel');
    btn.disabled = true;
    api('DELETE', BASE + 'alerts/' + id).then(function () {
      toast('ok', 'Alert #' + id + ' cancelled');
      poll();
    }).catch(function (err) {
      toast('err', 'Could not cancel alert #' + id, err.message);
      btn.disabled = false;
    });
  });

  $('clear-inbox').addEventListener('click', function () {
    var btn = $('clear-inbox');
    btn.disabled = true;
    api('POST', BASE + 'inbox/clear').then(function (r) {
      if (r.cleared) toast('ok', 'Inbox cleared');
      else toast('err', 'Could not clear the inbox', 'Mailpit did not respond.');
      poll();
    }).finally(function () { btn.disabled = false; });
  });

  // ----------------------------------------------------------- auto push

  function autoStep() {
    var base = currentPrice();
    if (base === null) return;
    var drift = $('auto-drift').value;
    var step = Math.random() * 4;
    var move = drift === 'up' ? step : drift === 'down' ? -step : (Math.random() - 0.5) * 2 * step;
    pushPrices([Math.max(1, base + move).toFixed(2)]).catch(function () {});
  }

  $('auto-toggle').addEventListener('click', function () {
    var btn = $('auto-toggle');
    var label = btn.querySelector('span');
    var svg = btn.querySelector('use');
    if (autoTimer) {
      clearInterval(autoTimer);
      autoTimer = null;
      btn.classList.remove('on');
      btn.setAttribute('aria-pressed', 'false');
      label.textContent = 'Start auto push';
      svg.setAttribute('href', '#i-play');
    } else {
      autoTimer = setInterval(autoStep, Number($('auto-interval').value));
      autoStep();
      btn.classList.add('on');
      btn.setAttribute('aria-pressed', 'true');
      label.textContent = 'Stop auto push';
      svg.setAttribute('href', '#i-stop');
    }
  });

  // ------------------------------------------------------------ e2e test

  var STEPS = [
    ['snapshot', 'Read the current price'],
    ['create',   'Create an alert above it'],
    ['push',     'Walk the feed across the target'],
    ['deliver',  'Wait for the email'],
    ['deleted',  'Check the alert deleted itself'],
    ['once',     'Confirm it does not fire twice']
  ];

  function resetSteps() {
    $('verdict').innerHTML = '';
    $('steps').innerHTML = STEPS.map(function (s) {
      return '<li data-status="pending" data-step="' + s[0] + '">' +
        '<span class="mark">' + icon('i-dot', true) + '</span>' +
        '<span class="txt"><span class="label">' + s[1] + '</span>' +
        '<span class="detail"></span></span><span class="ms"></span></li>';
    }).join('');
  }

  function step(name, status, detail, ms) {
    var li = $('steps').querySelector('[data-step="' + name + '"]');
    if (!li) return;
    li.dataset.status = status;
    li.querySelector('.mark').innerHTML =
      status === 'pass' ? icon('i-check') :
      status === 'fail' ? icon('i-x') :
      status === 'running' ? icon('i-clock') : icon('i-dot', true);
    if (detail !== undefined) li.querySelector('.detail').textContent = detail;
    if (ms !== undefined) li.querySelector('.ms').textContent = ms + ' ms';
  }

  function verdict(ok, text) {
    $('verdict').className = 'verdict ' + (ok ? 'pass' : 'fail');
    $('verdict').innerHTML = icon(ok ? 'i-check' : 'i-alert') + '<span>' + text + '</span>';
  }

  function waitFor(check, timeoutMs, label) {
    var started = Date.now();
    return new Promise(function (resolve, reject) {
      (function attempt() {
        poll().then(function (state) {
          if (state && check(state)) return resolve(Date.now() - started);
          if (Date.now() - started > timeoutMs) return reject(new Error(label));
          setTimeout(attempt, 400);
        });
      })();
    });
  }

  $('run-test').addEventListener('click', function () {
    if (testRunning) return;
    testRunning = true;
    var btn = $('run-test');
    btn.disabled = true;
    resetSteps();

    var target, alertId, mailBefore, t0;

    Promise.resolve()
      .then(function () {
        step('snapshot', 'running');
        return poll();
      })
      .then(function (state) {
        var price = currentPrice();
        if (price === null) throw new Error('No price recorded yet — start the stack so price:watch is running.');
        mailBefore = state.inbox.total;
        target = (price + 25).toFixed(2);
        step('snapshot', 'pass', 'price ' + fmt(price) + ', inbox holds ' + mailBefore);

        step('create', 'running');
        return api('POST', BASE + 'alerts', { target_price: target });
      })
      .then(function (r) {
        alertId = r.alert.id;
        step('create', 'pass', 'alert #' + alertId + ' watching ' + r.alert.direction + ' ' + fmt(target));

        step('push', 'running');
        t0 = Date.now();
        return pushPrices([(Number(target) - 10).toFixed(2), (Number(target) + 1).toFixed(2)]);
      })
      .then(function () {
        step('push', 'pass', 'queued ' + fmt(Number(target) - 10) + ' then ' + fmt(Number(target) + 1));

        step('deliver', 'running');
        return waitFor(function (s) {
          return s.inbox.available && s.inbox.total > mailBefore;
        }, 30000, 'No email arrived within 30s — is a queue worker running?');
      })
      .then(function (ms) {
        step('deliver', 'pass', 'email delivered', ms);

        step('deleted', 'running');
        return waitFor(function (s) {
          return !(s.alerts || []).some(function (a) { return a.id === alertId; });
        }, 10000, 'The alert row is still present after delivery.');
      })
      .then(function (ms) {
        step('deleted', 'pass', 'alert #' + alertId + ' removed', ms);

        step('once', 'running');
        return pushPrices([(Number(target) + 5).toFixed(2)]).then(function () {
          return new Promise(function (r) { setTimeout(r, 4000); });
        }).then(poll).then(function (s) {
          if (s.inbox.total > mailBefore + 1) throw new Error('A second email arrived; the alert fired twice.');
          step('once', 'pass', 'one email total, no re-fire');
        });
      })
      .then(function () {
        verdict(true, 'All six checks passed');
        toast('ok', 'End-to-end test passed');
      })
      .catch(function (err) {
        var running = $('steps').querySelector('[data-status="running"]');
        if (running) step(running.dataset.step, 'fail', err.message);
        verdict(false, 'Test failed');
        toast('err', 'End-to-end test failed', err.message);
      })
      .finally(function () {
        testRunning = false;
        btn.disabled = false;
      });
  });

  // ---------------------------------------------------------------- boot

  $('unit').textContent = cfg.unit;
  resetSteps();
  poll();
  setInterval(poll, POLL);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
</script>
@endverbatim
</body>
</html>
