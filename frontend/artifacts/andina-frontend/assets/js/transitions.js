(function () {
  // Crear overlay que cubre el flash blanco al cargar
  const overlay = document.createElement('div');
  overlay.id = 'page-overlay';
  document.documentElement.appendChild(overlay);

  // Fade-in al terminar de cargar
  function revealPage() {
    document.body.classList.add('page-enter');
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        overlay.classList.add('hidden');
        setTimeout(() => overlay.remove(), 300);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', revealPage);
  } else {
    revealPage();
  }

  // Fade-out al navegar a otra página
  document.addEventListener('click', function (e) {
    const link = e.target.closest('a[href]');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href) return;

    // Ignorar: anclas, javascript:, target=_blank, externas
    if (
      href.startsWith('#') ||
      href.startsWith('javascript') ||
      href.startsWith('mailto') ||
      href.startsWith('tel') ||
      link.target === '_blank' ||
      link.hasAttribute('data-bs-toggle') ||
      link.hasAttribute('data-bs-dismiss') ||
      e.ctrlKey || e.metaKey || e.shiftKey
    ) return;

    // Solo para links internos (relativos o mismo origen)
    try {
      const url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) return;
      if (url.pathname === window.location.pathname && !url.search) return;
    } catch (_) { return; }

    e.preventDefault();
    const dest = link.href;

    // Crear nuevo overlay para la salida
    const exitOverlay = document.createElement('div');
    exitOverlay.id = 'page-overlay';
    exitOverlay.style.opacity = '0';
    document.documentElement.appendChild(exitOverlay);

    document.body.classList.add('page-exit');

    // Fade overlay oscuro sobre la página actual
    requestAnimationFrame(() => {
      exitOverlay.style.transition = 'opacity 0.18s ease';
      exitOverlay.style.opacity = '1';
    });

    setTimeout(() => {
      window.location.href = dest;
    }, 180);
  });
})();
