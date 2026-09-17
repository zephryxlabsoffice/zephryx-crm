// ───────────────────────────────────────────────────────────────
// admin/client-shell.js
// Client-role shell (sidebar + topbar) for the Client portal pages.
// ───────────────────────────────────────────────────────────────

const C_SIDEBAR_HTML = `
<aside class="sidebar" aria-label="Main navigation">
  <div class="sb-brand">
    <svg class="z-mark" viewBox="0 0 64 64" fill="none" aria-hidden="true">
      <path d="M 44 14 L 54 14 L 20 52 L 10 52 Z" fill="currentColor"/>
      <rect x="10" y="14" width="44" height="10" fill="currentColor"/>
      <rect x="10" y="42" width="44" height="10" fill="currentColor"/>
    </svg>
    <span class="brand-text">Zephryx CRM</span>
  </div>
  <nav class="sb-nav">
    <a class="sb-link" data-key="dashboard" href="clientDashboard.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg></span>
      <span class="sb-label">Dashboard</span>
    </a>
    <a class="sb-link" data-key="projects" href="clientProjects.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></span>
      <span class="sb-label">Projects</span>
    </a>
    <a class="sb-link" data-key="invoices" href="clientInvoices.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg></span>
      <span class="sb-label">Invoices</span>
    </a>
    <a class="sb-link" data-key="tickets" href="clientTickets.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14v-2a9 9 0 0 1 18 0v2"/><rect x="2" y="14" width="5" height="7" rx="2"/><rect x="17" y="14" width="5" height="7" rx="2"/></svg></span>
      <span class="sb-label">Support Tickets</span>
    </a>
    <a class="sb-link" data-key="meetings" href="clientMeetings.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
      <span class="sb-label">Meetings</span>
    </a>
    <a class="sb-link" data-key="profile" href="clientProfile.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
      <span class="sb-label">My Profile</span>
    </a>
  </nav>
  <div class="sb-foot">
    <a class="sb-link" href="../Login Page.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></span>
      <span class="sb-label">Logout</span>
    </a>
  </div>
</aside>
`;

const C_TOPBAR_HTML = `
<header class="topbar">
  <button class="tb-menu" id="tb-menu" aria-label="Toggle sidebar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <div class="tb-search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" placeholder="Search anything..." />
  </div>
  <div class="tb-right">
    <div class="notif-wrap" id="notif-wrap" data-open="false">
      <button class="tb-bell" id="tb-bell" aria-label="Notifications" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="badge" id="notif-badge">0</span>
      </button>
      <div class="notif-pop" role="menu" aria-label="Recent notifications">
        <div class="notif-hd"><strong>Notifications</strong><span class="notif-count" id="notif-count">0 new</span></div>
        <div class="notif-list" id="notif-list"><div class="notif-empty">No recent activity</div></div>
        <div class="notif-foot"><a href="#">View All Activity</a></div>
      </div>
    </div>
    <div class="tb-user">
      <div class="tb-avatar">D</div>
      <div class="tb-user-info"><strong>DGL International School</strong><span>Client</span></div>
    </div>
  </div>
</header>
`;

function bootClientShell() {
  const main = document.querySelector('.main');
  if (!main) return;
  main.insertAdjacentHTML('beforebegin', C_SIDEBAR_HTML);
  main.insertAdjacentHTML('afterbegin', C_TOPBAR_HTML);

  const activeKey = document.body.dataset.active || 'dashboard';
  document.querySelectorAll('.sb-link').forEach((l) => {
    if (l.dataset.key === activeKey) l.classList.add('active');
  });

  const menuBtn = document.getElementById('tb-menu');
  if (menuBtn) {
    menuBtn.addEventListener('click', () => {
      const isMobile = window.matchMedia('(max-width: 760px)').matches;
      if (isMobile) {
        const open = document.body.dataset.mobileNav === 'open';
        document.body.dataset.mobileNav = open ? 'closed' : 'open';
      } else {
        const collapsed = document.body.dataset.sidebar === 'collapsed';
        document.body.dataset.sidebar = collapsed ? 'expanded' : 'collapsed';
      }
    });
  }

  if (!document.querySelector('.mobile-scrim')) {
    const scrim = document.createElement('div');
    scrim.className = 'mobile-scrim';
    document.body.appendChild(scrim);
    scrim.addEventListener('click', () => { document.body.dataset.mobileNav = 'closed'; });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootClientShell);
} else {
  bootClientShell();
}
