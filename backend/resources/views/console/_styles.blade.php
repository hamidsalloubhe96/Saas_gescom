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
  // Œil « afficher / masquer » sur tous les champs mot de passe (amélioration progressive : sans script, le champ reste utilisable).
  document.addEventListener('DOMContentLoaded', function () {
    var eye = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></svg>';
    var eyeOff = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]" aria-hidden="true"><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.5 0 8.4 2.8 9.94 6.65a1 1 0 0 1 0 .7 10.8 10.8 0 0 1-1.65 2.7M6.61 6.61A10.75 10.75 0 0 0 2.06 11.65a1 1 0 0 0 0 .7 10.75 10.75 0 0 0 13.4 5.9M2 2l20 20M9.88 9.88a3 3 0 1 0 4.24 4.24"/></svg>';
    document.querySelectorAll('input[type=password]').forEach(function (input) {
      var wrap = document.createElement('span');
      wrap.className = 'relative block';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
      input.style.paddingRight = '2.75rem';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'absolute right-1 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-md text-muted transition-colors hover:bg-accent-soft hover:text-accent';
      btn.setAttribute('aria-label', 'Afficher le mot de passe');
      btn.setAttribute('aria-pressed', 'false');
      btn.innerHTML = eye;
      btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        btn.innerHTML = show ? eyeOff : eye;
      });
      wrap.appendChild(btn);
    });
  });
</script>
