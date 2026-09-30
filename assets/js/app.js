document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');
  const sidebar = document.querySelector('[data-sidebar]');
  const backdrop = document.querySelector('[data-sidebar-close]');

  if (toggle && nav) {
    toggle.addEventListener('click', () => nav.classList.toggle('open'));
  }

  const closeSidebar = () => document.body.classList.remove('sidebar-open');

  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-open');
    });
  }
  if (backdrop) {
    backdrop.addEventListener('click', closeSidebar);
  }
  sidebar?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', closeSidebar);
  });

  // Confirm destructive actions
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      const msg = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // Landing section reveal on scroll
  const revealItems = document.querySelectorAll('[data-reveal]');
  if (revealItems.length) {
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-visible');
              io.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.14, rootMargin: '0px 0px -8% 0px' }
      );
      revealItems.forEach((el) => io.observe(el));
    } else {
      revealItems.forEach((el) => el.classList.add('is-visible'));
    }
  }
});
