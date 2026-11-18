{{-- CSS compilé par Tailwind (cd frontend && npm run build:console) + bascule clair/sombre mémorisée --}}
<link rel="stylesheet" href="{{ asset('console.css') }}@if(is_file(public_path('console.css')))?v={{ filemtime(public_path('console.css')) }}@endif">
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
  function toggleDrawer(open) {
    var s = document.getElementById('console-sidebar'), o = document.getElementById('console-overlay');
    if (!s || !o) return;
    s.classList.toggle('-translate-x-full', !open); s.classList.toggle('translate-x-0', open);
    o.classList.toggle('hidden', !open);
    var b = document.getElementById('console-menu-btn');
    if (b) b.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggleDrawer(false); });
</script>
