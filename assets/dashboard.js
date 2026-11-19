/* Dashboard navigation only. No tracking, requests, remote scripts or credentials. */
(() => {
  const root = document.querySelector('.sci-dashboard');
  if (!root) return;
  const sync = () => {
    const hash = window.location.hash || '#sci-overview';
    const target = root.querySelector('[id="' + hash.slice(1).replace(/[^a-z0-9-]/gi, '') + '"]');
    if (target && target.tagName === 'DETAILS') target.open = true;
    root.querySelectorAll('.sci-nav a').forEach(link => {
      if (link.hash === hash) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  };
  window.addEventListener('hashchange', sync);
  sync();
})();
