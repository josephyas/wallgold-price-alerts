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
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
@verbatim
<style>
  /*
   * Wallgold's own system, sampled from wallgold.ir: white cards on a near-white
   * page, #171D26 as the action colour, brand gold #B99C49 as an accent (its
   * darker #7D6831 wherever gold has to be read at text size), pill controls and
   * 16px cards, set in Vazirmatn, the open counterpart to their IRANSans.
   */
  :root {
    --page:      #F5F6F7;
    --surface:   #FFFFFF;
    --surface-2: #FBFBFC;
    --inset:     #F5F6F7;
    --line:      #D7DADF;
    --line-2:    #E8EAED;
    --ink:       #171D26;
    --body:      #252525;
    --ink-2:     rgba(0, 0, 0, .6);
    --ink-3:     rgba(0, 0, 0, .58);   /* 4.5:1 on white; .42 failed AA for 11-13px labels */
    --fill:      rgba(0, 0, 0, .05);
    --gold:      #B99C49;
    --gold-ink:  #7D6831;
    --gold-soft: #FBF6E8;
    --ok:        #14804A;
    --ok-soft:   #EFFBF3;
    --warn:      #9A6212;
    --warn-soft: #FDF5E7;
    --bad:       #C0362F;
    --bad-soft:  #FDEDEB;

    --sans: 'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

    --r-card: 16px;
    --r-field: 12px;
    --r-pill: 999px;
    --shadow: 0 1px 2px rgba(23, 29, 38, .05);
    --ease: cubic-bezier(.2, .7, .3, 1);
    color-scheme: light;
  }

  * { box-sizing: border-box; }
  html { -webkit-text-size-adjust: 100%; }
  [hidden] { display: none !important; }

  body {
    margin: 0;
    background: var(--page);
    color: var(--body);
    font-family: var(--sans);
    font-size: 14px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
  }
  .num { font-variant-numeric: tabular-nums; }

  .lbl {
    font-size: 11px;
    font-weight: 500;
    color: var(--ink-3);
  }

  /* ------------------------------------------------------------- header */

  .top {
    position: sticky;
    top: 0;
    z-index: 40;
    background: var(--surface);
    border-bottom: 1px solid var(--line);
  }
  .top-in {
    max-width: 1460px;
    margin: 0 auto;
    padding: 12px 22px;
    display: flex;
    align-items: center;
    gap: 22px;
    flex-wrap: wrap;
  }
  .brand { display: flex; align-items: center; gap: 11px; margin-right: auto; }
  .brand-mark {
    width: 34px; height: 34px;
    border-radius: var(--r-pill);
    display: grid; place-items: center;
    color: #fff;
    background: var(--ink);
    flex: none;
  }
  .brand-mark svg { width: 18px; height: 18px; }
  .brand b { display: block; font-size: 14.5px; font-weight: 700; color: var(--ink); letter-spacing: -.01em; }

  .readout {
    padding: 6px 18px;
    background: var(--gold-soft);
    border: 1px solid #EFE3C4;
    border-radius: var(--r-pill);
    display: flex;
    align-items: baseline;
    gap: 10px;
  }
  .readout .v {
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -.02em;
    font-variant-numeric: tabular-nums;
    color: var(--gold-ink);
  }
  .readout .u { font-size: 11px; color: var(--ink-3); }
  .readout .d { font-size: 12.5px; font-weight: 500; font-variant-numeric: tabular-nums; }
  .d.up { color: var(--ok); }
  .d.down { color: var(--bad); }
  .d.flat { color: var(--ink-3); }

  .status { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--ink-2); }
  .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ink-3); flex: none; }
  .dot.live { background: var(--ok); box-shadow: 0 0 0 3px var(--ok-soft); animation: pulse 2.2s var(--ease) infinite; }
  .dot.warn { background: var(--warn); box-shadow: 0 0 0 3px var(--warn-soft); }
  .dot.down { background: var(--bad); box-shadow: 0 0 0 3px var(--bad-soft); }
  @keyframes pulse { 0%, 100% { opacity: 1 } 50% { opacity: .4 } }

  /* --------------------------------------------------------------- grid */

  main {
    max-width: 1460px;
    margin: 0 auto;
    padding: 22px;
    display: grid;
    grid-template-columns: minmax(0, 1.85fr) minmax(330px, 1fr);
    gap: 18px;
    align-items: start;
  }
  .col { display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; align-content: start; min-width: 0; }

  .panel {
    background: var(--surface);
    border: 1px solid var(--line-2);
    border-radius: var(--r-card);
    box-shadow: var(--shadow);
    min-width: 0;
    overflow: hidden;
  }
  .panel > .head {
    padding: 13px 16px;
    border-bottom: 1px solid var(--line-2);
    display: flex;
    align-items: center;
    gap: 9px;
    background: var(--surface-2);
  }
  .panel > .head h2 { margin: 0; font-size: 13px; font-weight: 600; color: var(--ink); }
  .panel > .head svg.ico { width: 15px; height: 15px; color: var(--gold-ink); flex: none; }
  .panel > .head .sp { margin-left: auto; }
  .panel .pad { padding: 16px; }
  .meta { font-size: 11.5px; color: var(--ink-3); font-variant-numeric: tabular-nums; }

  /* ------------------------------------------------------------ controls */

  button {
    font-family: inherit;
    font-size: 13px;
    font-weight: 500;
    color: var(--body);
    background: var(--fill);
    border: 1px solid transparent;
    border-radius: var(--r-pill);
    padding: 0 16px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    cursor: pointer;
    transition: background 150ms var(--ease), color 150ms var(--ease), border-color 150ms var(--ease);
  }
  button svg { width: 15px; height: 15px; flex: none; }
  button:hover:not(:disabled) { background: rgba(0, 0, 0, .09); }
  button:active:not(:disabled) { background: rgba(0, 0, 0, .13); }
  button:disabled { opacity: .4; cursor: not-allowed; }

  button.key { background: var(--ink); color: #fff; }
  button.key:hover:not(:disabled) { background: #232C39; }
  button.on { background: var(--gold-soft); color: var(--gold-ink); border-color: #E7D6A8; }
  button.warnish { background: var(--warn-soft); color: var(--warn); border-color: #F0DFC0; }
  button.risky { background: transparent; color: var(--bad); border-color: #F2C9C5; }
  button.risky:hover:not(:disabled) { background: var(--bad-soft); }
  button.risky-solid { background: var(--bad); color: #fff; }
  button.risky-solid:hover:not(:disabled) { background: #A82A24; }
  button.sm { min-height: 34px; font-size: 12px; padding: 0 12px; }
  button.ico-only { min-width: 34px; padding: 0 10px; }
  button.wide { width: 100%; }

  :is(button, input, select, a, dialog):focus-visible {
    outline: 2px solid var(--ink);
    outline-offset: 2px;
  }

  label { display: block; margin-bottom: 6px; }
  .hint { font-size: 12px; color: var(--ink-3); margin: 8px 0 0; line-height: 1.5; }

  input, select {
    font-family: inherit;
    font-size: 13.5px;
    font-variant-numeric: tabular-nums;
    color: var(--body);
    background: var(--inset);
    border: 1px solid var(--line);
    border-radius: var(--r-field);
    padding: 0 13px;
    min-height: 44px;
    width: 100%;
    transition: border-color 150ms var(--ease), background 150ms var(--ease);
  }
  select { cursor: pointer; }
  input:hover, select:hover { border-color: #BFC5CD; }
  input:focus, select:focus { background: var(--surface); }
  input::placeholder { color: var(--ink-3); }

  .fld + .fld { margin-top: 14px; }
  .inline { display: flex; gap: 8px; align-items: flex-end; }
  .inline > .fld { flex: 1; margin-top: 0; }
  .two { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; align-items: end; }
  .two > .fld { margin-top: 0; }
  .two > .fld > label { min-height: 1.2em; }
  .two + .fld, .fld + .two, .two + .two,
  form + .two, form + .fld, .two + form, .fld + form { margin-top: 14px; }

  /* --------------------------------------------------------------- chart */

  #chart-svg { display: block; padding: 8px 4px 0; cursor: crosshair; }
  #chart-svg svg { display: block; width: 100%; max-width: 100%; height: auto; }
  .legend {
    display: flex; gap: 16px; flex-wrap: wrap;
    padding: 6px 16px 14px;
    font-size: 11.5px; color: var(--ink-3);
  }
  .legend span { display: inline-flex; align-items: center; gap: 6px; }
  .sw { width: 14px; height: 2px; flex: none; }
  .sw.a { background: var(--gold-ink); }
  .sw.b { border-top: 2px dashed var(--ok); }

  .none {
    padding: 34px 16px;
    text-align: center;
    color: var(--ink-3);
    font-size: 13px;
  }
  .none svg { width: 22px; height: 22px; opacity: .45; margin-bottom: 8px; }

  /* --------------------------------------------------------------- table */

  .xscroll { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line-2); white-space: nowrap; }
  th {
    font-size: 11px; color: var(--ink-3); font-weight: 500;
    background: var(--surface-2); position: sticky; top: 0;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr { transition: background 150ms var(--ease); }
  tbody tr:hover { background: var(--surface-2); }
  td.n { font-variant-numeric: tabular-nums; }
  td.q { color: var(--ink-3); }
  td.r { text-align: right; }

  .chip {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11.5px; font-weight: 500;
    padding: 3px 10px; border-radius: var(--r-pill);
  }
  .chip svg { width: 11px; height: 11px; }
  .chip.active  { color: var(--ok);   background: var(--ok-soft); }
  .chip.sending { color: var(--warn); background: var(--warn-soft); }
  .chip.failed  { color: var(--bad);  background: var(--bad-soft); }
  .chip.dir     { color: var(--ink-2); background: var(--fill); }

  /* --------------------------------------------------------------- gauge */

  .gauges { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: var(--line-2); }
  .gauge { background: var(--surface); padding: 13px 15px; }
  .gauge dt { margin: 0 0 3px; }
  .gauge dd { margin: 0; font-size: 19px; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--ink); letter-spacing: -.01em; }
  .gauge dd.idle { color: var(--ink-3); font-weight: 500; }
  .gauge dd.busy { color: var(--warn); }
  .gauge dd.bad { color: var(--bad); }
  .gauge dd.good { color: var(--ok); }
  .gauge dd.sm { font-size: 13px; font-weight: 600; }

  /* ---------------------------------------------------------- test runner */

  .runbar { display: flex; gap: 8px; padding: 14px 16px; border-bottom: 1px solid var(--line-2); flex-wrap: wrap; }
  .runbar button { flex: 1; min-width: 96px; }

  .tally { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px; background: var(--line-2); border-bottom: 1px solid var(--line-2); }
  .tally div { background: var(--surface); padding: 9px 12px; }
  .tally b { display: block; font-size: 15px; font-weight: 700; font-variant-numeric: tabular-nums; margin-top: 2px; color: var(--ink); }
  .tally b.good { color: var(--ok); }
  .tally b.bad { color: var(--bad); }

  .hist { display: flex; gap: 3px; padding: 12px 16px; flex-wrap: wrap; border-bottom: 1px solid var(--line-2); }
  .hist i { width: 10px; height: 16px; border-radius: 3px; flex: none; background: var(--line); }
  .hist i.p { background: var(--ok); }
  .hist i.f { background: var(--bad); }
  .hist i.r { background: var(--gold-ink); animation: pulse 1s var(--ease) infinite; }

  .loopnote {
    padding: 10px 16px;
    font-size: 12.5px;
    color: var(--warn);
    background: var(--warn-soft);
    border-bottom: 1px solid var(--line-2);
    display: flex; align-items: center; gap: 8px;
  }
  .loopnote svg { width: 14px; height: 14px; flex: none; }

  .steps { list-style: none; margin: 0; padding: 0; }
  .steps li { display: flex; gap: 11px; padding: 11px 16px; border-bottom: 1px solid var(--line-2); align-items: flex-start; }
  .steps li:last-child { border-bottom: none; }
  .mk {
    width: 19px; height: 19px; border-radius: 50%;
    border: 1.5px solid var(--line);
    display: grid; place-items: center; flex: none; margin-top: 1px;
    color: var(--ink-3);
  }
  .mk svg { width: 11px; height: 11px; }
  li[data-s="running"] .mk { border-color: var(--gold-ink); color: var(--gold-ink); animation: spin 1.1s linear infinite; }
  li[data-s="pass"] .mk { border-color: var(--ok); background: var(--ok); color: #fff; }
  li[data-s="fail"] .mk { border-color: var(--bad); background: var(--bad); color: #fff; }
  @keyframes spin { to { transform: rotate(360deg) } }

  .steps .tx { flex: 1; min-width: 0; }
  .steps .nm { font-size: 13px; color: var(--ink); }
  li[data-s="pending"] .nm { color: var(--ink-3); }
  .steps .dt { font-size: 11.5px; color: var(--ink-3); margin-top: 1px; white-space: normal; word-break: break-word; }
  li[data-s="fail"] .dt { color: var(--bad); }
  .steps .ms { font-size: 11.5px; color: var(--ink-3); font-variant-numeric: tabular-nums; flex: none; }

  .verdict { padding: 12px 16px; font-size: 13px; font-weight: 600; border-bottom: 1px solid var(--line-2); display: flex; align-items: center; gap: 8px; }
  .verdict svg { width: 15px; height: 15px; }
  .verdict.pass { color: var(--ok); background: var(--ok-soft); }
  .verdict.fail { color: var(--bad); background: var(--bad-soft); }

  /* --------------------------------------------------------------- inbox */

  .mail { display: flex; gap: 11px; padding: 12px 16px; border-bottom: 1px solid var(--line-2); align-items: flex-start; }
  .mail:last-child { border-bottom: none; }
  .mail svg { width: 15px; height: 15px; color: var(--gold-ink); flex: none; margin-top: 2px; }
  .mail .s { font-size: 13px; color: var(--ink); }
  .mail .m { font-size: 11.5px; color: var(--ink-3); margin-top: 1px; }

  /* ---------------------------------------------------------- danger zone */

  .danger { border-color: #F2C9C5; }
  .danger > .head { background: var(--bad-soft); border-bottom-color: #F2C9C5; }
  .danger > .head h2 { color: var(--bad); }
  .danger > .head svg.ico { color: var(--bad); }

  dialog {
    border: 1px solid var(--line-2);
    border-radius: var(--r-card);
    background: var(--surface);
    color: var(--body);
    padding: 0;
    max-width: 420px;
    width: calc(100vw - 40px);
    box-shadow: 0 24px 60px rgba(23, 29, 38, .18);
  }
  dialog::backdrop { background: rgba(23, 29, 38, .45); }
  dialog h3 { margin: 0 0 8px; font-size: 16px; font-weight: 700; color: var(--ink); }
  dialog .pad { padding: 20px; }
  dialog menu { display: flex; gap: 10px; margin: 18px 0 0; padding: 0; }
  dialog menu button { flex: 1; }

  /* -------------------------------------------------------------- toasts */

  .toasts { position: fixed; right: 20px; bottom: 20px; z-index: 100; display: grid; gap: 10px; max-width: min(380px, calc(100vw - 40px)); }
  .toast {
    background: var(--surface);
    border: 1px solid var(--line-2);
    border-left: 3px solid var(--ink-3);
    border-radius: var(--r-field);
    padding: 12px 15px;
    font-size: 13px;
    box-shadow: 0 8px 28px rgba(23, 29, 38, .12);
    animation: rise 200ms var(--ease);
  }
  .toast.ok { border-left-color: var(--ok); }
  .toast.err { border-left-color: var(--bad); }
  .toast b { display: block; color: var(--ink); }
  .toast span { color: var(--ink-2); font-size: 12.5px; }
  @keyframes rise { from { opacity: 0; transform: translateY(6px) } to { opacity: 1; transform: none } }

  .sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

  @media (max-width: 1080px) { main { grid-template-columns: minmax(0, 1fr) } }
  @media (max-width: 640px) {
    .top-in { padding: 10px 14px; gap: 12px }
    main { padding: 14px; gap: 14px }
    .readout .v { font-size: 20px }
    .gauges { grid-template-columns: repeat(2, 1fr) }
    .tally { grid-template-columns: repeat(2, 1fr) }
    /* Touch targets: the compact buttons grow to the 44px minimum. */
    button.sm { min-height: 44px }
    button.ico-only { min-width: 44px }
  }

  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important }
  }
</style>
</head>
<body>

<svg class="sr" aria-hidden="true"><defs>
  <g id="i-mark"><path d="M3 17l5-6 4 3 5-7 4 4"/><path d="M3 21h18"/></g>
  <g id="i-play"><path d="M6 4l14 8-14 8z"/></g>
  <g id="i-loop"><path d="M17 2l4 4-4 4"/><path d="M3 12v-2a4 4 0 0 1 4-4h14"/><path d="M7 22l-4-4 4-4"/><path d="M21 12v2a4 4 0 0 1-4 4H3"/></g>
  <g id="i-pause"><path d="M9 4v16M15 4v16"/></g>
  <g id="i-stop"><rect x="5" y="5" width="14" height="14" rx="1"/></g>
  <g id="i-plus"><path d="M12 5v14M5 12h14"/></g>
  <g id="i-trash"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></g>
  <g id="i-mail"><rect x="3" y="5" width="18" height="14" rx="1"/><path d="m3 7 9 6 9-6"/></g>
  <g id="i-check"><path d="M20 6 9 17l-5-5"/></g>
  <g id="i-x"><path d="M18 6 6 18M6 6l12 12"/></g>
  <g id="i-warn"><path d="m10.3 3.9-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></g>
  <g id="i-clock"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></g>
  <g id="i-stack"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 14 9 5 9-5"/></g>
  <g id="i-wave"><path d="M2 12h3l3 8 4-16 3 8h7"/></g>
  <g id="i-bell"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></g>
  <g id="i-flask"><path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-9V3"/><path d="M7 15h10"/></g>
  <g id="i-dot"><circle cx="12" cy="12" r="4"/></g>
  <g id="i-up"><path d="M12 19V5M5 12l7-7 7 7"/></g>
  <g id="i-down"><path d="M12 5v14M19 12l-7 7-7-7"/></g>
</defs></svg>

<div class="top">
  <div class="top-in">
    <div class="brand">
      <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-mark"/></svg></span>
      <span>
        <b>Alert Engine</b>
        <span class="lbl">development console</span>
      </span>
    </div>

    <div class="readout">
      <span class="v" id="price">&mdash;&mdash;&mdash;&mdash;</span>
      <span class="u" id="unit"></span>
      <span class="d flat" id="delta"></span>
    </div>

    <div class="status">
      <span class="dot" id="live-dot"></span>
      <span id="live-text">connecting</span>
    </div>
  </div>
</div>

<main>
  <div class="col">

    <section class="panel">
      <div class="head">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-wave"/></svg>
        <h2>Price feed</h2>
        <span class="sp"></span>
        <span class="meta" id="tickmeta"></span>
      </div>
      <div>
        <div id="chart-svg"></div>
        <p class="none" id="chart-none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-clock"/></svg><br>
          Waiting for the first tick. Start the stack, or push a price below.
        </p>
      </div>
      <div class="legend" id="legend" hidden>
        <span><i class="sw a"></i> observed price</span>
        <span><i class="sw b"></i> alert target</span>
        <span id="hoverval"></span>
        <span id="scalenote"></span>
      </div>
    </section>

    <section class="panel">
      <div class="head">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-bell"/></svg>
        <h2>Alerts</h2>
        <span class="sp"></span>
        <span class="meta" id="alertmeta"></span>
      </div>
      <div class="xscroll">
        <table>
          <caption class="sr">Price alerts and their delivery status</caption>
          <thead><tr>
            <th scope="col">ID</th><th scope="col">Owner</th><th scope="col">Watching</th>
            <th scope="col">Target</th><th scope="col">Status</th><th scope="col">Triggered</th>
            <th scope="col">Tries</th><th scope="col"><span class="sr">Actions</span></th>
          </tr></thead>
          <tbody id="alerts"></tbody>
        </table>
      </div>
      <p class="none" id="alerts-none">No alerts. Create one from the panel on the right.</p>
    </section>

    <section class="panel">
      <div class="head">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-mail"/></svg>
        <h2>Delivered mail</h2>
        <span class="sp"></span>
        <button class="sm" id="clear-inbox" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-trash"/></svg>
          Clear
        </button>
      </div>
      <div id="inbox"></div>
      <p class="none" id="inbox-none">Nothing delivered yet.</p>
    </section>

  </div>

  <div class="col">

    <section class="panel">
      <div class="head">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-flask"/></svg>
        <h2>End-to-end test</h2>
        <span class="sp"></span>
        <span class="meta" id="runmeta"></span>
      </div>

      <div class="runbar">
        <button class="key" id="btn-once" type="button">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><use href="#i-play"/></svg>
          Run once
        </button>
        <button id="btn-loop" type="button" aria-pressed="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-loop"/></svg>
          <span>Loop</span>
        </button>
        <button id="btn-stop" type="button" hidden>
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><use href="#i-stop"/></svg>
          Stop
        </button>
      </div>

      <div class="loopnote" id="loopnote" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-pause"/></svg>
        <span id="loopnote-text"></span>
      </div>

      <div class="tally" id="tally" hidden>
        <div><span class="lbl">Runs</span><b id="t-runs">0</b></div>
        <div><span class="lbl">Passed</span><b class="good" id="t-pass">0</b></div>
        <div><span class="lbl">Failed</span><b class="bad" id="t-fail">0</b></div>
        <div><span class="lbl">Avg</span><b id="t-avg">&mdash;</b></div>
      </div>

      <div class="hist" id="hist" hidden></div>
      <div id="verdict"></div>
      <ol class="steps" id="steps"></ol>

      <div class="pad">
        <div class="fld">
          <label class="lbl" for="loop-gap">Gap between looped runs</label>
          <select id="loop-gap">
            <option value="0">None</option>
            <option value="2000" selected>2 seconds</option>
            <option value="5000">5 seconds</option>
          </select>
        </div>
        <p class="hint">
          Each run creates an alert, walks the feed across it, waits for the email, then checks the alert deleted
          itself and did not fire twice. Runs alternate between above and below, so the price oscillates instead of
          drifting away. Pausing takes effect after the run in progress finishes.
        </p>
      </div>
    </section>

    <section class="panel">
      <div class="head">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-stack"/></svg>
        <h2>Pipeline</h2>
      </div>
      <dl class="gauges">
        <div class="gauge"><dt class="lbl">Above</dt><dd id="g-above">0</dd></div>
        <div class="gauge"><dt class="lbl">Below</dt><dd id="g-below">0</dd></div>
        <div class="gauge"><dt class="lbl">In flight</dt><dd id="g-inflight">0</dd></div>
        <div class="gauge"><dt class="lbl">Queued</dt><dd id="g-queued">0</dd></div>
        <div class="gauge"><dt class="lbl">Failed jobs</dt><dd id="g-failed">0</dd></div>
        <div class="gauge"><dt class="lbl">Index</dt><dd class="sm" id="g-ready">&mdash;</dd></div>
      </dl>
    </section>

    <section class="panel">
      <div class="head">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-wave"/></svg>
        <h2>Feed control</h2>
      </div>
      <div class="pad">
        <form id="push-form">
          <div class="fld">
            <label class="lbl" for="push-input">Next prices to serve</label>
            <div class="inline">
              <div class="fld"><input id="push-input" inputmode="decimal" placeholder="2690 2701.25" autocomplete="off"></div>
              <button class="key" type="submit">Push</button>
            </div>
            <p class="hint">
              The fake feed already walks on its own every tick, so the price moves with or without you. Pushing
              serves these exact values first, one per tick, then the walk resumes from the last one.
            </p>
          </div>
        </form>

        <div class="two">
          <div class="fld">
            <label class="lbl" for="auto-int">Auto every</label>
            <select id="auto-int">
              <option value="1000">1 second</option>
              <option value="2000" selected>2 seconds</option>
              <option value="5000">5 seconds</option>
            </select>
          </div>
          <div class="fld">
            <label class="lbl" for="auto-drift">Drift</label>
            <select id="auto-drift">
              <option value="up">Upward</option>
              <option value="random" selected>Random</option>
              <option value="down">Downward</option>
            </select>
          </div>
        </div>
        <div class="fld">
          <button class="wide" id="auto-toggle" type="button" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><use href="#i-play"/></svg>
            <span>Start auto push</span>
          </button>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="head">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-plus"/></svg>
        <h2>New alert</h2>
      </div>
      <div class="pad">
        <form id="alert-form">
          <div class="two">
            <div class="fld">
              <label class="lbl" for="a-target">Target</label>
              <input id="a-target" inputmode="decimal" placeholder="2750.00" autocomplete="off" required>
            </div>
            <div class="fld">
              <label class="lbl" for="a-dir">Direction</label>
              <select id="a-dir">
                <option value="">Infer</option>
                <option value="above">Above</option>
                <option value="below">Below</option>
              </select>
            </div>
          </div>
          <div class="fld">
            <button class="key wide" type="submit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-plus"/></svg>
              Create alert
            </button>
            <p class="hint">Created as the demo account. A target equal to the current price is refused as ambiguous.</p>
          </div>
        </form>
      </div>
    </section>

    <section class="panel danger">
      <div class="head">
        <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-warn"/></svg>
        <h2>Reset</h2>
      </div>
      <div class="pad">
        <button class="risky wide" id="btn-reset" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-trash"/></svg>
          Reset all data
        </button>
        <p class="hint">
          Deletes every alert, empties the queue, the failed jobs, the index and the inbox, and drops any scripted
          prices. The live market price is left alone.
        </p>
      </div>
    </section>

  </div>
</main>

<dialog id="confirm">
  <div class="pad">
    <h3>Reset all data?</h3>
    <p class="hint" style="margin:0">
      This deletes every alert, clears the queue and failed jobs, empties the index and the Mailpit inbox, and drops
      queued scripted prices. It cannot be undone. The live price keeps ticking.
    </p>
    <menu>
      <button id="confirm-no" type="button">Cancel</button>
      <button class="risky-solid" id="confirm-yes" type="button">Reset everything</button>
    </menu>
  </div>
</dialog>

<div class="toasts" id="toasts" role="status" aria-live="polite"></div>

<script>
(function () {
  'use strict';

  var cfg = JSON.parse(document.querySelector('meta[name="console-config"]').content);
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var POLL = Math.max(500, cfg.pollIntervalMs || 1000);
  var BASE = cfg.base || '/dev/';

  var W = 720, H = 190, PL = 56, PR = 16, PT = 12, PB = 20;

  var $ = function (id) { return document.getElementById(id); };
  var series = [];
  var lastAt = null, lastTick = 0, ticks = 0;
  var latest = null, polling = false, hoverIx = null;
  var autoTimer = null;

  var run = { mode: 'idle', paused: false, stop: false, busy: false, n: 0, pass: 0, fail: 0, ms: [], hist: [] };

  function fmt(n, dp) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: dp === undefined ? 2 : dp, maximumFractionDigits: dp === undefined ? 2 : dp });
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  function ico(id, filled) {
    return '<svg viewBox="0 0 24 24" fill="' + (filled ? 'currentColor' : 'none') + '" stroke="' + (filled ? 'none' : 'currentColor') +
      '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#' + id + '"/></svg>';
  }

  function toast(kind, title, body) {
    var el = document.createElement('div');
    el.className = 'toast ' + kind;
    el.innerHTML = '<b>' + esc(title) + '</b>' + (body ? '<span>' + esc(body) + '</span>' : '');
    $('toasts').appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }

  function api(method, path, body) {
    var o = { method: method, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } };
    if (body !== undefined) { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(body); }
    return fetch(BASE + path, o).then(function (res) {
      if (res.status === 204) return null;
      return res.json().catch(function () { return null; }).then(function (data) {
        if (!res.ok) {
          var msg = (data && data.message) || ('Request failed with status ' + res.status);
          if (data && data.errors) msg = Object.keys(data.errors).map(function (k) { return data.errors[k][0]; }).join(' ');
          throw new Error(msg);
        }
        return data;
      });
    });
  }

  function renderTop(s) {
    var p = s.price;
    $('unit').textContent = p.unit;
    if (!p.available) { $('live-dot').className = 'dot down'; $('live-text').textContent = 'index unreachable'; return; }
    if (p.price === null) { $('price').textContent = '————'; $('live-dot').className = 'dot warn'; $('live-text').textContent = 'no price yet'; return; }

    $('price').textContent = fmt(p.price);
    if (p.received_at !== lastAt) {
      lastAt = p.received_at; lastTick = Date.now(); ticks++;
      series.push({ v: Number(p.price), at: p.received_at });
      if (series.length > 120) series.shift();
    }
    var prev = series.length > 1 ? series[series.length - 2].v : null;
    var d = $('delta');
    if (prev === null) { d.textContent = ''; }
    else {
      var diff = Number(p.price) - prev;
      d.className = 'd ' + (diff > 0 ? 'up' : diff < 0 ? 'down' : 'flat');
      d.textContent = (diff > 0 ? '+' : '') + fmt(diff);
    }
    var quiet = Date.now() - lastTick;
    if (quiet < POLL * 3) { $('live-dot').className = 'dot live'; $('live-text').textContent = 'live'; }
    else { $('live-dot').className = 'dot warn'; $('live-text').textContent = 'no tick for ' + Math.round(quiet / 1000) + 's'; }
    $('tickmeta').textContent = (p.source ? p.source + ' feed · ' : '') + ticks + ' ticks';
  }

  function renderChart(s) {
    if (!s || series.length < 2) return;
    $('chart-none').hidden = true;
    $('legend').hidden = false;

    var targets = (s.alerts || []).filter(function (a) { return a.status === 'active'; })
      .map(function (a) { return { v: Number(a.target_price), d: a.direction }; }).slice(0, 8);

    // The scale fits the price first. A target far from the price would
    // otherwise stretch the axis until the walk reads as a flat line, so
    // anything beyond one extra range is pinned to the edge instead.
    var vals = series.map(function (p) { return p.v; });
    var lo = Math.min.apply(null, vals), hi = Math.max.apply(null, vals);
    var spread = hi - lo;
    var pad = Math.max(spread * 0.12, vals[vals.length - 1] * 0.0004);
    lo -= pad; hi += pad;

    // "Near" is measured against the price, not the walk: a band of a few
    // spreads would be only a handful of units wide, which would push ordinary
    // alerts off scale and lose the view of the price approaching one.
    var last = vals[vals.length - 1];
    var reach = Math.max(spread * 3, last * 0.02);
    var near = [], far = [];
    targets.forEach(function (t) {
      if (Math.abs(t.v - last) <= reach) near.push(t); else far.push(t);
    });
    near.forEach(function (t) { lo = Math.min(lo, t.v); hi = Math.max(hi, t.v); });

    var X = function (i) { return PL + (i / (series.length - 1)) * (W - PL - PR); };
    var Y = function (v) { return PT + (1 - (v - lo) / (hi - lo)) * (H - PT - PB); };
    var pts = series.map(function (p, i) { return X(i) + ',' + Y(p.v); });

    var g = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Observed price over the last ' +
      series.length + ' ticks, currently ' + fmt(vals[vals.length - 1]) + '">';

    [0, 0.5, 1].forEach(function (f) {
      var v = lo + (hi - lo) * f;
      g += '<line x1="' + PL + '" y1="' + Y(v) + '" x2="' + (W - PR) + '" y2="' + Y(v) + '" stroke="#E8EAED"/>';
      g += '<text x="' + (PL - 8) + '" y="' + (Y(v) + 4) + '" text-anchor="end" fill="rgba(0,0,0,.45)" font-family="Vazirmatn, sans-serif" font-size="10">' + fmt(v) + '</text>';
    });

    near.forEach(function (t) {
      g += '<line x1="' + PL + '" y1="' + Y(t.v) + '" x2="' + (W - PR) + '" y2="' + Y(t.v) + '" stroke="#14804A" stroke-width="1.3" stroke-dasharray="5 4" opacity=".9"/>';
      g += '<text x="' + (W - PR) + '" y="' + (Y(t.v) - 5) + '" text-anchor="end" fill="#14804A" font-family="Vazirmatn, sans-serif" font-size="10">' + t.d + ' ' + fmt(t.v) + '</text>';
    });

    // Off-scale targets: a marker at the edge they lie beyond, so they stay
    // visible without dictating the axis.
    var up = 0, down = 0;
    far.forEach(function (t) {
      var above = t.v > hi;
      var y = above ? PT + 9 + (up++ * 13) : H - PB - 5 - (down++ * 13);
      var tri = above ? (X(0) + 4) + ',' + (y - 8) + ' ' + (X(0) - 1) + ',' + (y - 2) + ' ' + (X(0) + 9) + ',' + (y - 2)
                      : (X(0) + 4) + ',' + (y - 1) + ' ' + (X(0) - 1) + ',' + (y - 7) + ' ' + (X(0) + 9) + ',' + (y - 7);
      g += '<polygon points="' + tri + '" fill="#14804A" opacity=".7"/>';
      g += '<text x="' + (X(0) + 14) + '" y="' + (y - 1) + '" fill="#14804A" opacity=".85" font-family="Vazirmatn, sans-serif" font-size="9.5">' +
        t.d + ' ' + fmt(t.v) + ' (off scale)</text>';
    });

    g += '<path d="M' + X(0) + ',' + (H - PB) + ' L' + pts.join(' L') + ' L' + X(series.length - 1) + ',' + (H - PB) + ' Z" fill="#B99C49" opacity=".12"/>';
    g += '<polyline points="' + pts.join(' ') + '" fill="none" stroke="#7D6831" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
    g += '<circle cx="' + X(series.length - 1) + '" cy="' + Y(vals[vals.length - 1]) + '" r="3.2" fill="#7D6831" stroke="#FFFFFF" stroke-width="2"/>';

    if (hoverIx !== null && series[hoverIx]) {
      g += '<line x1="' + X(hoverIx) + '" y1="' + PT + '" x2="' + X(hoverIx) + '" y2="' + (H - PB) + '" stroke="rgba(0,0,0,.35)" stroke-width="1" stroke-dasharray="3 3" opacity=".7"/>';
      g += '<circle cx="' + X(hoverIx) + '" cy="' + Y(series[hoverIx].v) + '" r="3" fill="#B99C49"/>';
    }
    g += '</svg>';

    $('chart-svg').innerHTML = g;
    $('hoverval').textContent = hoverIx !== null && series[hoverIx]
      ? 'at cursor: ' + fmt(series[hoverIx].v)
      : series.length + ' ticks shown';
    $('scalenote').textContent = far.length
      ? far.length + ' target' + (far.length > 1 ? 's' : '') + ' off scale'
      : '';
  }

  function renderAlerts(s) {
    var rows = s.alerts || [];
    $('alertmeta').textContent = rows.length ? rows.length + ' shown' : '';
    $('alerts-none').hidden = rows.length > 0;
    $('alerts').innerHTML = rows.map(function (a) {
      var st = a.status === 'failed' ? 'i-warn' : a.status === 'sending' ? 'i-clock' : 'i-dot';
      return '<tr>' +
        '<td class="n q">' + a.id + '</td>' +
        '<td class="q">' + esc((a.owner || '').split('@')[0]) + '</td>' +
        '<td><span class="chip dir">' + ico(a.direction === 'above' ? 'i-up' : 'i-down') + a.direction + '</span></td>' +
        '<td class="n">' + fmt(a.target_price) + '</td>' +
        '<td><span class="chip ' + a.status + '">' + ico(st, a.status === 'active') + a.status + '</span></td>' +
        '<td class="n q">' + (a.triggered_price ? fmt(a.triggered_price) : '—') + '</td>' +
        '<td class="n q">' + a.attempts + '</td>' +
        '<td class="r"><button class="sm risky ico-only" data-cancel="' + a.id + '" type="button" aria-label="Cancel alert ' + a.id + '">' + ico('i-trash') + '</button></td>' +
        '</tr>';
    }).join('');
  }

  function renderGauges(s) {
    var i = s.index, q = s.queue;
    $('g-above').textContent = i.above; $('g-above').className = i.above ? '' : 'idle';
    $('g-below').textContent = i.below; $('g-below').className = i.below ? '' : 'idle';
    $('g-inflight').textContent = i.inflight; $('g-inflight').className = i.inflight ? 'busy' : 'idle';
    $('g-queued').textContent = q.pending; $('g-queued').className = q.pending ? 'busy' : 'idle';
    $('g-failed').textContent = q.failed; $('g-failed').className = q.failed ? 'bad' : 'idle';
    var r = $('g-ready');
    if (!i.available) { r.textContent = 'unreachable'; r.className = 'sm bad'; }
    else if (i.ready) { r.textContent = 'ready'; r.className = 'sm good'; }
    else { r.textContent = 'not built'; r.className = 'sm busy'; }
  }

  function renderInbox(s) {
    var box = s.inbox;
    if (!box.available) {
      $('inbox').innerHTML = '';
      $('inbox-none').hidden = false;
      $('inbox-none').textContent = 'Mailpit is not reachable. In the Compose stack it runs at localhost:8025.';
      return;
    }
    var m = box.messages || [];
    $('inbox-none').hidden = m.length > 0;
    $('inbox-none').textContent = 'Nothing delivered yet.';
    $('inbox').innerHTML = m.map(function (x) {
      return '<div class="mail">' + ico('i-mail') + '<div><div class="s">' + esc(x.subject) + '</div>' +
        '<div class="m">' + esc(x.to) + ' · ' + (x.at ? new Date(x.at).toLocaleTimeString() : '') + '</div></div></div>';
    }).join('');
  }

  function poll() {
    if (polling || document.hidden) return Promise.resolve(latest);
    polling = true;
    return api('GET', 'state').then(function (s) {
      latest = s; renderTop(s); renderChart(s); renderAlerts(s); renderGauges(s); renderInbox(s);
      return s;
    }).catch(function (e) {
      $('live-dot').className = 'dot down';
      $('live-text').textContent = 'console offline';
      if (window.console) console.error('poll failed', e);
      return null;
    }).finally(function () { polling = false; });
  }

  function push(v) { return api('POST', 'prices', { prices: v }); }
  function price() { return latest && latest.price && latest.price.price !== null ? Number(latest.price.price) : null; }

  $('push-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var raw = $('push-input').value.trim();
    if (!raw) { toast('err', 'Nothing to push', 'Enter one or more prices, separated by spaces.'); return; }
    var b = e.target.querySelector('button'); b.disabled = true;
    push(raw.split(/[\s,]+/).filter(Boolean)).then(function (r) {
      toast('ok', 'Queued ' + r.pushed.length + ' price(s)', r.pushed.join(' -> '));
      $('push-input').value = '';
    }).catch(function (err) { toast('err', 'Could not push', err.message); })
      .finally(function () { b.disabled = false; });
  });

  $('alert-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var b = e.target.querySelector('button[type="submit"]'); b.disabled = true;
    api('POST', 'alerts', { target_price: $('a-target').value.trim(), direction: $('a-dir').value || null })
      .then(function (r) {
        toast('ok', 'Alert #' + r.alert.id + ' created', 'Watching ' + r.alert.direction + ' ' + fmt(r.alert.target_price));
        $('a-target').value = ''; poll();
      })
      .catch(function (err) { toast('err', 'Could not create the alert', err.message); })
      .finally(function () { b.disabled = false; });
  });

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cancel]');
    if (!b) return;
    var id = b.getAttribute('data-cancel');
    b.disabled = true;
    api('DELETE', 'alerts/' + id)
      .then(function () { toast('ok', 'Alert #' + id + ' cancelled'); poll(); })
      .catch(function (err) { toast('err', 'Could not cancel alert #' + id, err.message); b.disabled = false; });
  });

  $('clear-inbox').addEventListener('click', function () {
    var b = $('clear-inbox'); b.disabled = true;
    api('POST', 'inbox/clear').then(function (r) {
      toast(r.cleared ? 'ok' : 'err', r.cleared ? 'Inbox cleared' : 'Could not clear the inbox');
      poll();
    }).finally(function () { b.disabled = false; });
  });

  $('chart-svg').addEventListener('mousemove', function (e) {
    if (series.length < 2) return;
    var r = e.currentTarget.getBoundingClientRect();
    var vx = ((e.clientX - r.left) / r.width) * W;
    var i = Math.round(((vx - PL) / (W - PL - PR)) * (series.length - 1));
    hoverIx = Math.max(0, Math.min(series.length - 1, i));
    renderChart(latest);
  });
  $('chart-svg').addEventListener('mouseleave', function () { hoverIx = null; renderChart(latest); });

  $('auto-toggle').addEventListener('click', function () {
    var b = $('auto-toggle'), t = b.querySelector('span'), u = b.querySelector('use');
    if (autoTimer) {
      clearInterval(autoTimer); autoTimer = null;
      b.classList.remove('on'); b.setAttribute('aria-pressed', 'false');
      t.textContent = 'Start auto push'; u.setAttribute('href', '#i-play');
    } else {
      var stepFn = function () {
        var base = price(); if (base === null) return;
        var drift = $('auto-drift').value, step = Math.random() * 4;
        var move = drift === 'up' ? step : drift === 'down' ? -step : (Math.random() - 0.5) * 2 * step;
        push([Math.max(1, base + move).toFixed(2)]).catch(function () {});
      };
      autoTimer = setInterval(stepFn, Number($('auto-int').value));
      stepFn();
      b.classList.add('on'); b.setAttribute('aria-pressed', 'true');
      t.textContent = 'Stop auto push'; u.setAttribute('href', '#i-stop');
    }
  });

  var STEPS = [
    ['snap', 'Read the current price'],
    ['make', 'Create an alert'],
    ['walk', 'Walk the feed across the target'],
    ['mail', 'Wait for the email'],
    ['gone', 'Check the alert deleted itself'],
    ['once', 'Confirm it does not fire twice']
  ];

  function resetSteps() {
    $('verdict').innerHTML = '';
    $('steps').innerHTML = STEPS.map(function (s) {
      return '<li data-s="pending" data-k="' + s[0] + '"><span class="mk">' + ico('i-dot', true) + '</span>' +
        '<span class="tx"><span class="nm">' + s[1] + '</span><span class="dt"></span></span><span class="ms"></span></li>';
    }).join('');
  }

  function mark(k, s, detail, ms) {
    var li = $('steps').querySelector('[data-k="' + k + '"]');
    if (!li) return;
    li.dataset.s = s;
    li.querySelector('.mk').innerHTML = s === 'pass' ? ico('i-check') : s === 'fail' ? ico('i-x') : s === 'running' ? ico('i-clock') : ico('i-dot', true);
    if (detail !== undefined) li.querySelector('.dt').textContent = detail;
    if (ms !== undefined) li.querySelector('.ms').textContent = ms + ' ms';
  }

  function verdict(ok, text) {
    $('verdict').className = 'verdict ' + (ok ? 'pass' : 'fail');
    $('verdict').innerHTML = ico(ok ? 'i-check' : 'i-warn') + '<span>' + esc(text) + '</span>';
  }

  function waitFor(check, timeout, message) {
    var t0 = Date.now();
    return new Promise(function (resolve, reject) {
      (function again() {
        poll().then(function (s) {
          if (s && check(s)) return resolve(Date.now() - t0);
          if (Date.now() - t0 > timeout) return reject(new Error(message));
          setTimeout(again, 400);
        });
      })();
    });
  }

  function renderRunUi() {
    $('tally').hidden = run.n === 0;
    $('hist').hidden = run.hist.length === 0;
    $('t-runs').textContent = run.n;
    $('t-pass').textContent = run.pass;
    $('t-fail').textContent = run.fail;
    $('t-avg').textContent = run.ms.length
      ? fmt(run.ms.reduce(function (a, b) { return a + b; }, 0) / run.ms.length, 0) + ' ms' : '—';
    $('runmeta').textContent = run.mode === 'loop' ? (run.paused ? 'loop paused' : 'looping') : (run.busy ? 'running' : '');
    $('hist').innerHTML = run.hist.slice(-40).map(function (h) {
      return '<i class="' + (h.state === 'pass' ? 'p' : h.state === 'fail' ? 'f' : 'r') + '" title="Run ' + h.n +
        (h.state === 'running' ? ' · in progress' : ' · ' + h.state + (h.ms ? ' · ' + h.ms + ' ms' : '')) + '"></i>';
    }).join('');

    var loop = $('btn-loop'), span = loop.querySelector('span'), use = loop.querySelector('use');
    $('btn-once').disabled = run.busy || run.mode === 'loop';
    $('btn-stop').hidden = run.mode !== 'loop';
    loop.setAttribute('aria-pressed', run.mode === 'loop' ? 'true' : 'false');
    loop.classList.toggle('on', run.mode === 'loop' && !run.paused);
    loop.classList.toggle('warnish', run.paused);
    if (run.mode !== 'loop') { span.textContent = 'Loop'; use.setAttribute('href', '#i-loop'); }
    else if (run.paused) { span.textContent = 'Resume'; use.setAttribute('href', '#i-play'); }
    else { span.textContent = 'Pause'; use.setAttribute('href', '#i-pause'); }

    $('loopnote').hidden = !(run.mode === 'loop' && run.paused);
    $('loopnote-text').textContent = 'Loop paused after run ' + run.n + '. Press Resume to continue.';
  }

  function runOnce() {
    run.busy = true;
    run.n += 1;
    var n = run.n;
    var up = n % 2 === 1;
    run.hist.push({ n: n, state: 'running' });
    resetSteps();
    renderRunUi();

    var target, id, mailBefore, t0 = Date.now();

    return Promise.resolve()
      .then(function () { mark('snap', 'running'); return poll(); })
      .then(function (s) {
        var p = price();
        if (p === null) throw new Error('No price recorded yet — start the stack so price:watch is running.');
        if (!s.inbox.available) throw new Error('Mailpit is unreachable, so delivery cannot be verified.');
        mailBefore = s.inbox.total;
        target = (up ? p + 25 : p - 25).toFixed(2);
        mark('snap', 'pass', 'price ' + fmt(p) + ', inbox holds ' + mailBefore);
        mark('make', 'running');
        return api('POST', 'alerts', { target_price: target });
      })
      .then(function (r) {
        id = r.alert.id;
        mark('make', 'pass', 'alert #' + id + ' watching ' + r.alert.direction + ' ' + fmt(target));
        mark('walk', 'running');
        var near = (up ? Number(target) - 10 : Number(target) + 10).toFixed(2);
        var over = (up ? Number(target) + 1 : Number(target) - 1).toFixed(2);
        return push([near, over]).then(function () {
          mark('walk', 'pass', 'queued ' + fmt(near) + ' then ' + fmt(over));
        });
      })
      .then(function () {
        mark('mail', 'running');
        return waitFor(function (s) { return s.inbox.available && s.inbox.total > mailBefore; },
          30000, 'No email arrived within 30s — is a queue worker running?');
      })
      .then(function (ms) {
        mark('mail', 'pass', 'email delivered', ms);
        mark('gone', 'running');
        return waitFor(function (s) { return !(s.alerts || []).some(function (a) { return a.id === id; }); },
          10000, 'The alert row is still present after delivery.');
      })
      .then(function (ms) {
        mark('gone', 'pass', 'alert #' + id + ' removed', ms);
        mark('once', 'running');
        return push([(up ? Number(target) + 5 : Number(target) - 5).toFixed(2)])
          .then(function () { return sleep(4000); })
          .then(poll)
          .then(function (s) {
            if (s.inbox.total > mailBefore + 1) throw new Error('A second email arrived; the alert fired twice.');
            mark('once', 'pass', 'one email total, no re-fire');
          });
      })
      .then(function () {
        var ms = Date.now() - t0;
        run.pass += 1; run.ms.push(ms);
        run.hist[run.hist.length - 1] = { n: n, state: 'pass', ms: ms };
        verdict(true, 'Run ' + n + ': all six checks passed');
        return true;
      })
      .catch(function (err) {
        var running = $('steps').querySelector('[data-s="running"]');
        if (running) mark(running.dataset.k, 'fail', err.message);
        run.fail += 1;
        run.hist[run.hist.length - 1] = { n: n, state: 'fail' };
        verdict(false, 'Run ' + n + ' failed');
        toast('err', 'Run ' + n + ' failed', err.message);
        return false;
      })
      .finally(function () { run.busy = false; renderRunUi(); });
  }

  $('btn-once').addEventListener('click', function () {
    if (run.busy || run.mode === 'loop') return;
    run.mode = 'once';
    runOnce().finally(function () { run.mode = 'idle'; renderRunUi(); });
  });

  $('btn-loop').addEventListener('click', function () {
    if (run.mode === 'loop') { run.paused = !run.paused; renderRunUi(); return; }
    run.mode = 'loop'; run.paused = false; run.stop = false;
    renderRunUi();
    (function cycle() {
      if (run.stop) { run.mode = 'idle'; run.paused = false; renderRunUi(); return; }
      if (run.paused) { setTimeout(cycle, 300); return; }
      runOnce().then(function () {
        if (run.stop) { run.mode = 'idle'; renderRunUi(); return; }
        setTimeout(cycle, Number($('loop-gap').value));
      });
    })();
  });

  $('btn-stop').addEventListener('click', function () {
    run.stop = true; run.paused = false;
    if (!run.busy) { run.mode = 'idle'; renderRunUi(); }
    toast('ok', 'Loop stopping', run.busy ? 'The run in progress will finish first.' : undefined);
  });

  var dlg = $('confirm');
  $('btn-reset').addEventListener('click', function () { dlg.showModal(); });
  $('confirm-no').addEventListener('click', function () { dlg.close(); });
  $('confirm-yes').addEventListener('click', function () {
    dlg.close();
    var b = $('btn-reset'); b.disabled = true;
    run.stop = true; run.paused = false;
    api('POST', 'reset').then(function (r) {
      series = []; ticks = 0; hoverIx = null; lastAt = null;
      run = { mode: 'idle', paused: false, stop: false, busy: false, n: 0, pass: 0, fail: 0, ms: [], hist: [] };
      resetSteps(); renderRunUi();
      $('chart-svg').innerHTML = ''; $('chart-none').hidden = false; $('legend').hidden = true;
      toast('ok', 'Everything reset', r.alerts + ' alert(s), ' + r.queued + ' queued job(s) and the inbox cleared.');
      poll();
    }).catch(function (err) { toast('err', 'Reset failed', err.message); })
      .finally(function () { b.disabled = false; });
  });

  $('unit').textContent = cfg.unit;
  resetSteps();
  renderRunUi();
  poll();
  setInterval(poll, POLL);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
</script>
@endverbatim
</body>
</html>
