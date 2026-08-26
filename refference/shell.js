// ───────────────────────────────────────────────────────────────
// admin/shell.js
// Reusable shell (sidebar + topbar) injected into every admin page.
// Wires up sidebar toggle, active-link highlight, mobile drawer.
// ───────────────────────────────────────────────────────────────

const SIDEBAR_HTML = `
<aside class="sidebar" aria-label="Main navigation">
  <div class="sb-brand">
    <svg class="z-mark" viewBox="0 0 64 64" fill="none" aria-hidden="true">
      <path d="M 44 14 L 54 14 L 20 52 L 10 52 Z" fill="currentColor"/>
      <rect x="10" y="14" width="44" height="10" fill="currentColor"/>
      <rect x="10" y="42" width="44" height="10" fill="currentColor"/>
    </svg>
    <span class="brand-text">Zephryx CRM</span>
  </div>

  <nav class="sb-nav" id="sb-nav">
    <a class="sb-link" data-key="dashboard" href="dashboard_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg></span>
      <span class="sb-label">Dashboard</span>
    </a>
    <a class="sb-link" data-key="clients" href="clients_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      <span class="sb-label">Clients</span>
    </a>
    <a class="sb-link" data-key="employees" href="employees_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
      <span class="sb-label">Employees</span>
    </a>
    <a class="sb-link" data-key="team" href="#">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      <span class="sb-label">Team</span>
    </a>
    <a class="sb-link" data-key="projects" href="projects_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></span>
      <span class="sb-label">Projects</span>
    </a>
    <a class="sb-link" data-key="tasks" href="tasks_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12l2 2 4-4"/></svg></span>
      <span class="sb-label">Tasks</span>
    </a>
    <a class="sb-link" data-key="leads" href="leads_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9-6-18-3 9H2"/></svg></span>
      <span class="sb-label">Leads</span>
    </a>
    <a class="sb-link" data-key="tickets" href="tickets_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10V8a2 2 0 0 0-2-2h-4l-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/><path d="M21 10a2 2 0 0 0 0 4"/></svg></span>
      <span class="sb-label">Tickets</span>
    </a>
    <a class="sb-link" data-key="invoices" href="invoices_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg></span>
      <span class="sb-label">Invoices</span>
    </a>
    <a class="sb-link" data-key="salary" href="salary_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/></svg></span>
      <span class="sb-label">Salary</span>
    </a>
    <a class="sb-link" data-key="attendance" href="attendance_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 7v5l3 2"/></svg></span>
      <span class="sb-label">Attendance</span>
    </a>
    <a class="sb-link" data-key="leave" href="#">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h2l1 3 2-5 1 2h2"/></svg></span>
      <span class="sb-label">Leave Requests</span>
    </a>
    <a class="sb-link" data-key="meetings" href="meetings_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></span>
      <span class="sb-label">Meetings</span>
    </a>
    <a class="sb-link" data-key="calendar" href="#">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
      <span class="sb-label">Calendar</span>
    </a>
    <a class="sb-link" data-key="reports" href="finance_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 3 5-6"/></svg></span>
      <span class="sb-label">Reports</span>
    </a>
    <a class="sb-link" data-key="announcements" href="announcements_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-8v18l-18-8z"/><path d="M11 14v4a2 2 0 0 0 4 0v-2"/></svg></span>
      <span class="sb-label">Announcements</span>
    </a>
    <a class="sb-link" data-key="profile" href="profile_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
      <span class="sb-label">My Profile</span>
    </a>
    <a class="sb-link" data-key="settings" href="settings_Superadmin.html">
      <span class="sb-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.11-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1.04H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.65 8.6a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H9a1.7 1.7 0 0 0 1-1.56V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9a1.7 1.7 0 0 0 1.56 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.56 1z"/></svg></span>
      <span class="sb-label">Settings</span>
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

const TOPBAR_HTML = `
<header class="topbar">
  <button class="tb-menu" id="tb-menu" aria-label="Toggle sidebar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
      <line x1="3" y1="6" x2="21" y2="6"/>
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>

  <div class="tb-search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="7"/>
      <line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input type="search" placeholder="Search anything..." />
  </div>

  <div class="tb-right">
    <div class="notif-wrap" id="notif-wrap" data-open="false">
      <button class="tb-bell" id="tb-bell" aria-label="Notifications" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="badge" id="notif-badge">0</span>
      </button>

      <div class="notif-pop" role="menu" aria-label="Recent notifications">
        <div class="notif-hd">
          <strong>Notifications</strong>
          <span class="notif-count" id="notif-count">0 new</span>
        </div>
        <div class="notif-list" id="notif-list">
          <div class="notif-empty">No recent activity</div>
        </div>
        <div class="notif-foot">
          <a href="#">View All Activity</a>
        </div>
      </div>
    </div>
    <div class="tb-user">
      <div class="tb-avatar">SK</div>
      <div class="tb-user-info">
        <strong>Santanu</strong>
        <span>Super Admin</span>
      </div>
    </div>
  </div>
</header>
`;

// Populate notifications dropdown from the page's .act-list (Recent Activity).
// Bell hover OR click opens the panel; mouseleave or outside-click closes it.
function wireNotifications() {
  const wrap = document.getElementById('notif-wrap');
  const bell = document.getElementById('tb-bell');
  const list = document.getElementById('notif-list');
  const badge = document.getElementById('notif-badge');
  const count = document.getElementById('notif-count');
  if (!wrap || !list) return;

  // Source rows from the page's Recent Activity list
  const srcRows = Array.from(document.querySelectorAll('.act-list .act-row'));
  if (srcRows.length === 0) {
    list.innerHTML = '<div class="notif-empty">No recent activity yet</div>';
    if (badge) badge.style.display = 'none';
    if (count) count.textContent = '0 new';
    return;
  }

  list.innerHTML = '';
  srcRows.forEach((row) => {
    const clone = row.cloneNode(true);
    clone.classList.remove('act-row');
    clone.classList.add('notif-item');
    list.appendChild(clone);
  });

  const n = srcRows.length;
  if (badge) badge.textContent = String(n);
  if (count) count.textContent = n + ' new';

  let hoverTimer = null;
  const setOpen = (open) => {
    wrap.dataset.open = open ? 'true' : 'false';
    bell.setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  const isOpen = () => wrap.dataset.open === 'true';

  // Hover-to-open (with short delay so a brush past doesn't fire)
  wrap.addEventListener('mouseenter', () => {
    clearTimeout(hoverTimer);
    hoverTimer = setTimeout(() => setOpen(true), 120);
  });
  wrap.addEventListener('mouseleave', () => {
    clearTimeout(hoverTimer);
    hoverTimer = setTimeout(() => setOpen(false), 220);
  });

  // Click toggle (also handles touch / keyboard)
  bell.addEventListener('click', (e) => {
    e.stopPropagation();
    setOpen(!isOpen());
  });

  // Outside click closes
  document.addEventListener('click', (e) => {
    if (!wrap.contains(e.target)) setOpen(false);
  });
  // Esc closes
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') setOpen(false);
  });
}

function bootShell() {
  const app = document.querySelector('.app');
  const main = document.querySelector('.main');
  if (!app || !main) return;

  // Inject sidebar before .main, topbar inside .main
  main.insertAdjacentHTML('beforebegin', SIDEBAR_HTML);
  main.insertAdjacentHTML('afterbegin', TOPBAR_HTML);

  // Highlight active link
  const activeKey = document.body.dataset.active || 'dashboard';
  document.querySelectorAll('.sb-link').forEach((l) => {
    if (l.dataset.key === activeKey) l.classList.add('active');
  });

  // Sidebar toggle (desktop collapse / mobile drawer)
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

  // Mobile scrim
  if (!document.querySelector('.mobile-scrim')) {
    const scrim = document.createElement('div');
    scrim.className = 'mobile-scrim';
    document.body.appendChild(scrim);
    scrim.addEventListener('click', () => {
      document.body.dataset.mobileNav = 'closed';
    });
  }

  // Notifications (populated from .act-list on the host page)
  wireNotifications();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootShell);
} else {
  bootShell();
}
