/**
 * Edifice — Front-end navigation
 */
(function () {
  'use strict';

  var SECTIONS = ['dashboard', 'crm', 'projects', 'time', 'revenue', 'products'];

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

  // Honour URL hash on initial load
  var hash = (window.location.hash || '').replace('#', '').trim();
  if (hash) showSection(hash);

})();
