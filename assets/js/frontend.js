/**
 * Edifice — Front-end navigation
 */
(function () {
  'use strict';

  var SECTIONS = ['dashboard', 'crm', 'projects', 'time', 'revenue', 'products', 'prospects', 'network', 'hosting'];

  function closeDrawer() {
    document.body.classList.remove('lh-sidebar-open');
    var btn = document.querySelector('.lh-hamburger');
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }
  function openDrawer() {
    document.body.classList.add('lh-sidebar-open');
    var btn = document.querySelector('.lh-hamburger');
    if (btn) btn.setAttribute('aria-expanded', 'true');
  }
  function toggleDrawer() {
    if (document.body.classList.contains('lh-sidebar-open')) closeDrawer();
    else openDrawer();
  }

  function showSection(id) {
    if (SECTIONS.indexOf(id) === -1) id = 'dashboard';

    // Toggle sections
    SECTIONS.forEach(function (s) {
      var el = document.getElementById('section-' + s);
      if (el) el.classList.toggle('lh-hidden', s !== id);
    });

    // Toggle nav active state
    document.querySelectorAll('.lh-nav-link').forEach(function (a) {
      a.classList.toggle('active', a.dataset.section === id);
    });

    // Update URL hash without scrolling
    history.replaceState(null, '', '#' + id);

    // Scroll main back to top
    var main = document.querySelector('.lh-main');
    if (main) main.scrollTop = 0;
    window.scrollTo(0, 0);

    // Lukk mobil-drawer
    closeDrawer();
  }

  // Sidebar nav clicks
  document.querySelectorAll('.lh-nav-link').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      showSection(this.dataset.section);
    });
  });

  // Internal "Se alle →" links from dashboard (class lh-section-link)
  document.addEventListener('click', function (e) {
    var link = e.target.closest('.lh-section-link');
    if (!link) return;
    e.preventDefault();
    showSection(link.dataset.section);
  });

  // Hamburger + backdrop + Escape
  var hamburger = document.querySelector('.lh-hamburger');
  if (hamburger) hamburger.addEventListener('click', toggleDrawer);

  var backdrop = document.querySelector('.lh-sidebar-backdrop');
  if (backdrop) backdrop.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDrawer();
  });

  // Honour URL hash on initial load
  var hash = (window.location.hash || '').replace('#', '').trim();
  if (hash) showSection(hash);

})();
