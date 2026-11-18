<style>
:root {
  --bg:#f4f5fa; --surface:#fff; --text:#1b1f2a; --muted:#667085; --border:#e3e6ee; --accent:#3b4cca; --accent-hover:#2f3fb3;
  --accent-soft:#eceefc; --danger:#b42318; --danger-soft:#fdecea; --ok:#17692f; --ok-soft:#e3f6ea; --warn:#8a5a00; --warn-soft:#fff3d6;
  --side:#161a2b; --side-text:#c9cfe6; --radius:10px;
  font-family: system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; color:var(--text); background:var(--bg);
}
:root[data-theme="dark"] {
  --bg:#11141f; --surface:#1a1f30; --text:#e8eaf3; --muted:#9aa3bd; --border:#2b3148; --accent:#7c8cff; --accent-hover:#95a2ff;
  --accent-soft:#232a4d; --danger:#ff8a80; --danger-soft:#3a1f22; --ok:#7fe3a1; --ok-soft:#173a26; --warn:#ffd27a; --warn-soft:#3a2f12; --side:#0c0f19;
}
* { box-sizing:border-box; }
body { margin:0; background:var(--bg); min-height:100vh; }
h1,h2,h3 { margin:0; line-height:1.25; }
h1 { font-size:1.55rem; } h2 { font-size:1.05rem; }
a { color:var(--accent); }
.muted { color:var(--muted); } .small { font-size:.82rem; }
:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }

/* Cadre */
.shell { display:grid; grid-template-columns:250px 1fr; min-height:100vh; }
.sidebar { background:var(--side); color:var(--side-text); padding:1.4rem 1rem; display:flex; flex-direction:column; gap:1.2rem; position:sticky; top:0; height:100vh; }
.brand { display:flex; align-items:center; gap:.6rem; font-weight:700; font-size:1.1rem; color:#fff; padding:0 .5rem; }
.brand-mark { display:grid; place-items:center; width:32px; height:32px; border-radius:8px; background:var(--accent); color:#fff; font-weight:800; }
.brand small { font-weight:500; opacity:.7; font-size:.7rem; display:block; letter-spacing:.06em; text-transform:uppercase; }
.nav-group { font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; color:#7f89ad; padding:.4rem .75rem 0; }
.sidebar nav { display:flex; flex-direction:column; gap:.15rem; }
.sidebar nav a { color:var(--side-text); text-decoration:none; padding:.55rem .75rem; border-radius:8px; font-size:.93rem; }
.sidebar nav a:hover { background:#20263d; }
.sidebar nav a.active { background:var(--accent); color:#fff; }
.side-foot { margin-top:auto; padding:0 .25rem; font-size:.88rem; }
.side-foot strong { display:block; color:#fff; } .side-foot span { opacity:.7; font-size:.8rem; }
.side-foot .links { display:flex; gap:.5rem; margin:.6rem 0; }
.btn-ghost { background:transparent; border:1px solid #3a4160; color:var(--side-text); width:100%; margin-top:.4rem; }
.content { padding:2rem 2.4rem; min-width:0; max-width:1250px; }
.page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; margin-bottom:1.4rem; }
.page-head p { margin:.3rem 0 0; }

/* Composants */
.btn { display:inline-block; padding:.6rem 1rem; border:0; border-radius:8px; cursor:pointer; background:var(--accent); color:#fff; font:inherit; font-weight:600; text-decoration:none; text-align:center; }
.btn:hover { background:var(--accent-hover); }
.btn-secondary { background:transparent; color:var(--text); border:1px solid var(--border); } .btn-secondary:hover { background:var(--accent-soft); }
.btn-danger { background:transparent; color:var(--danger); border:1px solid var(--danger); } .btn-danger:hover { background:var(--danger-soft); }
.btn-sm { padding:.35rem .7rem; font-size:.85rem; }
.panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:1.2rem; margin-bottom:1rem; }
.panel h2 { margin-bottom:.8rem; }
.grid { display:grid; gap:1rem; } .grid-2 { grid-template-columns:repeat(auto-fit,minmax(340px,1fr)); }
.kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:.85rem; margin-bottom:1rem; }
.kpi { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:1rem 1.1rem; }
.kpi .l { font-size:.76rem; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; display:block; }
.kpi .v { font-size:1.7rem; font-weight:700; font-variant-numeric:tabular-nums; }
.kpi .s { font-size:.8rem; color:var(--muted); }
.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:.92rem; }
th { text-align:left; font-size:.74rem; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); }
th,td { padding:.7rem .9rem; border-bottom:1px solid var(--border); vertical-align:top; }
tbody tr:last-child td { border-bottom:0; } tbody tr:hover { background:var(--accent-soft); }
.num { text-align:right; font-variant-numeric:tabular-nums; }
.pill { display:inline-block; font-size:.74rem; font-weight:600; padding:.15rem .55rem; border-radius:999px; background:var(--border); color:var(--muted); }
.pill-ok { background:var(--ok-soft); color:var(--ok); } .pill-warn { background:var(--warn-soft); color:var(--warn); }
.pill-bad { background:var(--danger-soft); color:var(--danger); } .pill-info { background:var(--accent-soft); color:var(--accent); }
.toolbar { display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:1rem; }
input,select,textarea { font:inherit; color:var(--text); }
.toolbar input,.toolbar select { padding:.55rem .75rem; border:1px solid var(--border); border-radius:8px; background:var(--surface); }
.toolbar input[type=search] { flex:1; min-width:200px; }
.field { margin-bottom:.9rem; }
.field label { display:block; font-size:.86rem; font-weight:600; margin-bottom:.3rem; }
.field input,.field select,.field textarea { width:100%; padding:.62rem .75rem; border:1px solid var(--border); border-radius:8px; background:var(--bg); }
.field input:focus,.field select:focus,.field textarea:focus { border-color:var(--accent); outline:none; box-shadow:0 0 0 3px var(--accent-soft); }
.field .hint { font-size:.8rem; color:var(--muted); margin:.25rem 0 0; } .field .err { font-size:.82rem; color:var(--danger); margin:.25rem 0 0; }
.row { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:.9rem; }
.alert { padding:.7rem .9rem; border-radius:8px; margin-bottom:1rem; font-size:.92rem; }
.alert-ok { background:var(--ok-soft); color:var(--ok); } .alert-bad { background:var(--danger-soft); color:var(--danger); } .alert-warn { background:var(--warn-soft); color:var(--warn); }
.secret { font-family:ui-monospace,Consolas,monospace; font-size:1.15rem; letter-spacing:.08em; background:var(--bg); border:1px dashed var(--border); border-radius:8px; padding:.7rem .9rem; word-break:break-all; user-select:all; }
.kv { display:grid; grid-template-columns:150px 1fr; gap:.4rem .9rem; margin:0; } .kv dt { color:var(--muted); } .kv dd { margin:0; }
.actions-row { display:flex; gap:.6rem; flex-wrap:wrap; align-items:flex-start; }
details.danger { border:1px solid var(--danger); border-radius:8px; padding:.7rem .9rem; background:var(--danger-soft); }
details summary { cursor:pointer; font-weight:600; }
.empty { text-align:center; color:var(--muted); padding:2rem 1rem; }
.pager { display:flex; justify-content:center; gap:.4rem; margin-top:1rem; flex-wrap:wrap; }
.pager a,.pager span { padding:.4rem .75rem; border:1px solid var(--border); border-radius:8px; text-decoration:none; background:var(--surface); color:var(--text); font-size:.88rem; }
.pager .cur { background:var(--accent); color:#fff; border-color:var(--accent); } .pager .dis { opacity:.45; }

/* Pages d'invité (connexion) */
.guest { min-height:100vh; display:grid; place-items:center; padding:1.5rem; }
.guest-card { width:100%; max-width:420px; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:2rem; }
.guest-card .brand { color:var(--text); padding:0; margin-bottom:1.2rem; }
.guest-card h1 { font-size:1.3rem; margin-bottom:.3rem; }

@media (max-width:860px) {
  .shell { grid-template-columns:1fr; padding-bottom:64px; }
  .sidebar { position:fixed; bottom:0; left:0; right:0; top:auto; height:auto; flex-direction:row; padding:.4rem; z-index:10; overflow-x:auto; gap:.2rem; }
  .sidebar .brand,.sidebar .nav-group,.side-foot { display:none; }
  .sidebar nav { flex-direction:row; width:100%; justify-content:space-around; }
  .sidebar nav a { padding:.55rem .6rem; font-size:.78rem; white-space:nowrap; }
  .content { padding:1.2rem 1rem; }
  .mobile-top { display:flex !important; }
  .kv { grid-template-columns:1fr; }
}
.mobile-top { display:none; justify-content:space-between; align-items:center; margin-bottom:1rem; font-size:.88rem; }
</style>
<script>
  (function () {
    try {
      var t = localStorage.getItem('console-theme');
      if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', t);
    } catch (e) {}
  })();
  function toggleTheme() {
    var t = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem('console-theme', t); } catch (e) {}
  }
</script>
<style>
.nav-badge { margin-left:.45rem; background:var(--accent); color:#fff; border-radius:999px; font-size:.7rem; font-weight:700; padding:.05rem .45rem; }
.bar { height:8px; background:var(--border); border-radius:999px; overflow:hidden; min-width:90px; }
.bar > span { display:block; height:100%; background:var(--accent); }
.bar.warning > span { background:#d89b00; } .bar.critical > span, .bar.reached > span { background:var(--danger); }
.two-col { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:1rem; }
@media (max-width:860px) { .sidebar nav .nav-group { display:none; } }
/* Animations simples, désactivées si l'utilisateur préfère moins de mouvement */
@media (prefers-reduced-motion: no-preference) {
  @keyframes c-rise { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
  @keyframes c-drop { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
  main > *, .guest-card { animation:c-rise .35s ease both; }
  main > *:nth-child(2) { animation-delay:.05s; } main > *:nth-child(3) { animation-delay:.1s; }
  .flash, .alert { animation:c-drop .3s ease both; }
  .btn, .pill, .sidebar nav a, .panel, tbody tr { transition:background-color .15s ease, color .15s ease, border-color .15s ease, transform .15s ease, box-shadow .15s ease; }
  .btn:hover { transform:translateY(-1px); box-shadow:0 3px 10px rgba(20,30,60,.15); }
  .btn:active { transform:scale(.98); box-shadow:none; }
  .sidebar nav a:hover { transform:translateX(3px); }
  .panel:hover { box-shadow:0 4px 14px rgba(20,30,60,.08); }
  .bar > span { animation:c-grow .8s ease both; transform-origin:left; }
  @keyframes c-grow { from { transform:scaleX(0); } to { transform:scaleX(1); } }
}
</style>
