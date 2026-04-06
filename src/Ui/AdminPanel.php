<?php

declare(strict_types=1);

namespace Kunlare\PhpApiEngine\Ui;

use Kunlare\PhpApiEngine\Config\Config;

/**
 * Admin panel SPA served as a single HTML page.
 * Uses Bootstrap 5.3, Bootstrap Icons, and vanilla JS with fetch API.
 */
class AdminPanel
{
    /** @var Config Configuration */
    private Config $config;

    /**
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Render and output the admin panel HTML.
     */
    public function render(): void
    {
        $apiBase = rtrim($this->config->getString('API_BASE_PATH', '/api'), '/')
            . '/' . $this->config->getString('API_VERSION', 'v1');

        header('Content-Type: text/html; charset=utf-8');
        echo $this->getHtml($apiBase);
    }

    /**
     * Generate the complete admin panel HTML.
     *
     * @param string $apiBase API base path
     * @return string Full HTML document
     */
    private function getHtml(string $apiBase): string
    {
        $css = $this->getCss();
        $js = $this->getJs();

        return <<<HTML
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHP CRUD API - Admin Panel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>{$css}</style>
</head>
<body>
<!-- Login Screen -->
<div id="login-screen" class="d-none">
  <div class="login-wrapper">
    <div class="login-card card shadow-lg border-0">
      <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
          <div class="login-icon mb-3"><i class="bi bi-database-gear"></i></div>
          <h3 class="fw-bold">PHP CRUD API</h3>
          <p class="text-body-secondary">Admin Panel</p>
        </div>
        <div id="login-alert" class="alert alert-danger d-none"></div>
        <form id="login-form">
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-envelope me-1"></i>Email</label>
            <input type="text" id="login-email" class="form-control form-control-lg" placeholder="admin@example.com" required>
          </div>
          <div class="mb-4">
            <label class="form-label"><i class="bi bi-lock me-1"></i>Password</label>
            <input type="password" id="login-password" class="form-control form-control-lg" placeholder="Password" required>
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-100" id="login-btn">
            <span class="spinner-border spinner-border-sm d-none me-2" id="login-spinner"></span>
            Sign In
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- App Shell -->
<div id="app-shell" class="d-none">
  <!-- Sidebar -->
  <nav id="sidebar" class="sidebar">
    <div class="sidebar-header">
      <i class="bi bi-database-gear me-2"></i>
      <span class="sidebar-brand">CRUD API</span>
      <button class="btn btn-link text-white ms-auto d-md-none p-0" id="sidebar-close">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <ul class="sidebar-nav">
      <li><a href="#dashboard" class="sidebar-link active" data-page="dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
      <li><a href="#tables" class="sidebar-link" data-page="tables"><i class="bi bi-table"></i><span>Tables</span></a></li>
      <li data-role="admin"><a href="#users" class="sidebar-link" data-page="users"><i class="bi bi-people"></i><span>Users</span></a></li>
      <li data-role="admin,developer"><a href="#apikeys" class="sidebar-link" data-page="apikeys"><i class="bi bi-key"></i><span>API Keys</span></a></li>
      <li data-role="admin,developer"><a href="#queues" class="sidebar-link" data-page="queues"><i class="bi bi-stack"></i><span>Queues</span></a></li>
      <li data-role="admin,developer"><a href="#apiexplorer" class="sidebar-link" data-page="apiexplorer"><i class="bi bi-braces"></i><span>API Explorer</span></a></li>
      <li><a href="#profile" class="sidebar-link" data-page="profile"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>
    </ul>
    <div class="sidebar-footer">
      <div class="d-flex align-items-center px-3 py-2">
        <div class="flex-grow-1 small">
          <div id="sidebar-user" class="fw-semibold text-white text-truncate"></div>
          <div id="sidebar-role" class="text-white-50 text-truncate"></div>
        </div>
        <button class="btn btn-sm btn-outline-light ms-2" id="btn-theme" title="Toggle theme"><i class="bi bi-moon-stars"></i></button>
      </div>
      <a href="#" class="sidebar-link text-danger" id="btn-logout"><i class="bi bi-box-arrow-left"></i><span>Logout</span></a>
    </div>
  </nav>
  <div id="sidebar-overlay" class="sidebar-overlay d-none"></div>

  <!-- Main Content -->
  <main id="main-content" class="main-content">
    <header class="main-header">
      <button class="btn btn-link text-body p-0 me-3 d-md-none" id="sidebar-toggle"><i class="bi bi-list fs-4"></i></button>
      <h5 class="mb-0 fw-semibold" id="page-title">Dashboard</h5>
    </header>
    <div class="content-body" id="content-area">
      <!-- Dynamic content rendered here -->
    </div>
  </main>
</div>

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>

<!-- Modals -->
<div class="modal fade" id="app-modal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="modal-body"></div>
  <div class="modal-footer" id="modal-footer"></div>
</div></div></div>

<!-- Confirm Modal -->
<div class="modal fade" id="confirm-modal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
  <div class="modal-body text-center py-4">
    <i class="bi bi-exclamation-triangle text-warning fs-1 d-block mb-3"></i>
    <p class="mb-0 fw-semibold" id="confirm-message">Are you sure?</p>
  </div>
  <div class="modal-footer justify-content-center border-0 pt-0">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-danger" id="confirm-ok">Confirm</button>
  </div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API_BASE = '{$apiBase}';
{$js}
</script>
</body>
</html>
HTML;
    }

    /**
     * Get custom CSS styles.
     */
    private function getCss(): string
    {
        return <<<'CSS'
:root{--sidebar-w:250px;--header-h:56px}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;overflow-x:hidden}

/* Login */
.login-wrapper{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:1rem}
.login-card{max-width:420px;width:100%;border-radius:1rem}
.login-icon{font-size:3rem;color:#667eea}
[data-bs-theme=dark] .login-icon{color:#a78bfa}

/* Sidebar */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:#1e293b;color:#fff;display:flex;flex-direction:column;z-index:1040;transition:transform .25s ease}
.sidebar-header{display:flex;align-items:center;padding:1rem 1.25rem;font-size:1.2rem;font-weight:700;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-brand{white-space:nowrap}
.sidebar-nav{list-style:none;padding:.5rem 0;margin:0;flex:1;overflow-y:auto}
.sidebar-link{display:flex;align-items:center;gap:.75rem;padding:.65rem 1.25rem;color:rgba(255,255,255,.7);text-decoration:none;transition:all .15s;font-size:.9rem}
.sidebar-link:hover,.sidebar-link.active{color:#fff;background:rgba(255,255,255,.1)}
.sidebar-link.active{border-left:3px solid #667eea}
.sidebar-link i{font-size:1.1rem;width:20px;text-align:center}
.sidebar-footer{border-top:1px solid rgba(255,255,255,.1)}
.sidebar-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1035}

/* Main */
.main-content{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column;transition:margin .25s ease}
.main-header{height:var(--header-h);display:flex;align-items:center;padding:0 1.5rem;border-bottom:1px solid var(--bs-border-color);background:var(--bs-body-bg);position:sticky;top:0;z-index:1020}
.content-body{flex:1;padding:1.5rem}

/* Mobile */
@media(max-width:767.98px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .main-content{margin-left:0}
}

/* Cards & Tables */
.stat-card{border:none;border-radius:.75rem;transition:transform .15s,box-shadow .15s}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 .5rem 1rem rgba(0,0,0,.1)!important}
.stat-icon{width:48px;height:48px;border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.4rem}
.table-actions .btn{padding:.25rem .5rem;font-size:.8rem}
.badge-col{display:inline-block;padding:.2rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:500;font-family:monospace}
.type-int{background:#dbeafe;color:#1e40af}
.type-varchar,.type-char,.type-text,.type-mediumtext,.type-longtext{background:#d1fae5;color:#065f46}
.type-decimal,.type-float,.type-double{background:#fef3c7;color:#92400e}
.type-datetime,.type-timestamp,.type-date,.type-time{background:#ede9fe;color:#5b21b6}
.type-enum,.type-set{background:#fce7f3;color:#9d174d}
.type-json{background:#ffedd5;color:#9a3412}
.type-tinyint{background:#e0e7ff;color:#3730a3}
[data-bs-theme=dark] .type-int{background:#1e3a5f;color:#93c5fd}
[data-bs-theme=dark] .type-varchar,[data-bs-theme=dark] .type-char,[data-bs-theme=dark] .type-text,[data-bs-theme=dark] .type-mediumtext,[data-bs-theme=dark] .type-longtext{background:#064e3b;color:#6ee7b7}
[data-bs-theme=dark] .type-decimal,[data-bs-theme=dark] .type-float,[data-bs-theme=dark] .type-double{background:#78350f;color:#fcd34d}
[data-bs-theme=dark] .type-datetime,[data-bs-theme=dark] .type-timestamp,[data-bs-theme=dark] .type-date,[data-bs-theme=dark] .type-time{background:#4c1d95;color:#c4b5fd}
[data-bs-theme=dark] .type-enum,[data-bs-theme=dark] .type-set{background:#831843;color:#f9a8d4}
[data-bs-theme=dark] .type-json{background:#7c2d12;color:#fdba74}
[data-bs-theme=dark] .type-tinyint{background:#312e81;color:#a5b4fc}

.pointer{cursor:pointer}
.truncate-cell{max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.loading-center{display:flex;align-items:center;justify-content:center;min-height:200px}
.empty-state{text-align:center;padding:3rem 1rem;color:var(--bs-secondary)}
.empty-state i{font-size:3rem;display:block;margin-bottom:1rem;opacity:.4}

/* API Explorer */
.api-method{display:inline-block;font-weight:700;font-size:.75rem;width:60px;text-align:center;padding:3px 0;border-radius:4px;color:#fff;font-family:monospace}
.api-method.get{background:#61affe}.api-method.post{background:#49cc90}.api-method.patch{background:#fca130}.api-method.delete{background:#f93e3e}
.api-endpoint{cursor:pointer;border:1px solid var(--bs-border-color);border-radius:8px;margin-bottom:8px;transition:box-shadow .15s}
.api-endpoint:hover{box-shadow:0 2px 8px rgba(0,0,0,.1)}
.api-endpoint.border-get{border-left:4px solid #61affe}.api-endpoint.border-post{border-left:4px solid #49cc90}
.api-endpoint.border-patch{border-left:4px solid #fca130}.api-endpoint.border-delete{border-left:4px solid #f93e3e}
.api-endpoint-header{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer}
.api-endpoint-header .api-path{font-family:monospace;font-size:.9rem;font-weight:500;flex:1}
.api-endpoint-header .api-desc{color:var(--bs-secondary);font-size:.85rem}
.api-endpoint-body{display:none;padding:0 14px 14px;border-top:1px solid var(--bs-border-color)}
.api-endpoint.open .api-endpoint-body{display:block}
.api-response-area{background:var(--bs-tertiary-bg);border:1px solid var(--bs-border-color);border-radius:6px;padding:12px;font-family:monospace;font-size:.82rem;max-height:400px;overflow:auto;white-space:pre-wrap;word-break:break-word}
.api-param-table td,.api-param-table th{font-size:.85rem;padding:6px 10px}
CSS;
    }

    /**
     * Get SPA JavaScript.
     */
    private function getJs(): string
    {
        return <<<'JS'
/* ========================================================
   API Client
   ======================================================== */
const Api = {
  token: localStorage.getItem('crud_token') || '',
  refreshToken: localStorage.getItem('crud_refresh') || '',

  setAuth(data) {
    this.token = data.token || '';
    this.refreshToken = data.refresh_token || '';
    localStorage.setItem('crud_token', this.token);
    localStorage.setItem('crud_refresh', this.refreshToken);
  },

  clearAuth() {
    this.token = ''; this.refreshToken = '';
    localStorage.removeItem('crud_token');
    localStorage.removeItem('crud_refresh');
    localStorage.removeItem('crud_user');
  },

  async request(method, path, body = null) {
    const opts = {
      method,
      headers: { 'Content-Type': 'application/json' }
    };
    if (this.token) opts.headers['Authorization'] = 'Bearer ' + this.token;
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(API_BASE + path, opts);
    if (res.status === 204) return { success: true, data: null };
    const json = await res.json();
    if (res.status === 401 && this.refreshToken) {
      const refreshed = await this.tryRefresh();
      if (refreshed) return this.request(method, path, body);
      App.logout(); throw new Error('Session expired');
    }
    if (!json.success) throw new Error(json.errors?.[0]?.message || 'Request failed');
    return json;
  },

  async tryRefresh() {
    try {
      const res = await fetch(API_BASE + '/auth/refresh', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: this.refreshToken })
      });
      const json = await res.json();
      if (json.success) { this.token = json.data.token; localStorage.setItem('crud_token', this.token); return true; }
    } catch(e) {}
    return false;
  },

  get(p) { return this.request('GET', p); },
  post(p, b) { return this.request('POST', p, b); },
  patch(p, b) { return this.request('PATCH', p, b); },
  del(p) { return this.request('DELETE', p); },
};

/* ========================================================
   UI Utilities
   ======================================================== */
const UI = {
  $(id) { return document.getElementById(id); },
  show(el) { if (typeof el === 'string') el = this.$(el); el?.classList.remove('d-none'); },
  hide(el) { if (typeof el === 'string') el = this.$(el); el?.classList.add('d-none'); },

  toast(msg, type = 'success') {
    const id = 'toast-' + Date.now();
    const icon = type === 'success' ? 'check-circle-fill' : type === 'danger' ? 'exclamation-triangle-fill' : 'info-circle-fill';
    document.getElementById('toast-container').insertAdjacentHTML('beforeend',
      `<div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert">
        <div class="d-flex"><div class="toast-body"><i class="bi bi-${icon} me-2"></i>${this.esc(msg)}</div>
        <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);
    const t = new bootstrap.Toast(document.getElementById(id), { delay: 3500 });
    t.show();
    document.getElementById(id).addEventListener('hidden.bs.toast', () => document.getElementById(id)?.remove());
  },

  confirm(msg) {
    return new Promise(resolve => {
      document.getElementById('confirm-message').textContent = msg;
      const m = new bootstrap.Modal(document.getElementById('confirm-modal'));
      const ok = document.getElementById('confirm-ok');
      const handler = () => { resolve(true); m.hide(); ok.removeEventListener('click', handler); };
      ok.addEventListener('click', handler);
      document.getElementById('confirm-modal').addEventListener('hidden.bs.modal', () => { resolve(false); ok.removeEventListener('click', handler); }, { once: true });
      m.show();
    });
  },

  modal(title, bodyHtml, footerHtml = '') {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = bodyHtml;
    document.getElementById('modal-footer').innerHTML = footerHtml;
    const m = new bootstrap.Modal(document.getElementById('app-modal'));
    m.show();
    return m;
  },

  closeModal() { bootstrap.Modal.getInstance(document.getElementById('app-modal'))?.hide(); },

  loading() { return '<div class="loading-center"><div class="spinner-border text-primary"></div></div>'; },

  esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; },

  typeBadge(type) {
    const t = (type || '').toLowerCase().replace(/\(.*/, '');
    return `<span class="badge-col type-${t}">${this.esc(type)}</span>`;
  },

  columnTypes: ['INT','BIGINT','TINYINT','SMALLINT','VARCHAR','CHAR','TEXT','MEDIUMTEXT','LONGTEXT','DECIMAL','FLOAT','DOUBLE','DATETIME','TIMESTAMP','DATE','TIME','BOOLEAN','ENUM','SET','JSON'],

  typeOptions(selected = '') {
    return this.columnTypes.map(t => `<option value="${t}" ${t===selected?'selected':''}>${t}</option>`).join('');
  },

  pagination(meta, onClick) {
    if (!meta || meta.total_pages <= 1) return '';
    let h = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
    for (let i = 1; i <= meta.total_pages; i++) {
      h += `<li class="page-item ${i===meta.page?'active':''}"><a class="page-link pointer" data-page="${i}">${i}</a></li>`;
    }
    h += '</ul></nav>';
    return h;
  }
};

/* ========================================================
   App Controller
   ======================================================== */
const App = {
  user: JSON.parse(localStorage.getItem('crud_user') || 'null'),
  currentPage: '',

  init() {
    // Theme
    const saved = localStorage.getItem('crud_theme');
    if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
    UI.$('btn-theme')?.addEventListener('click', () => {
      const t = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', t);
      localStorage.setItem('crud_theme', t);
      UI.$('btn-theme').innerHTML = t === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
    });
    if (saved === 'dark') UI.$('btn-theme').innerHTML = '<i class="bi bi-sun"></i>';

    // Sidebar mobile
    UI.$('sidebar-toggle')?.addEventListener('click', () => { UI.$('sidebar').classList.add('open'); UI.show('sidebar-overlay'); });
    UI.$('sidebar-close')?.addEventListener('click', () => this.closeSidebar());
    UI.$('sidebar-overlay')?.addEventListener('click', () => this.closeSidebar());
    document.querySelectorAll('.sidebar-link[data-page]').forEach(a => a.addEventListener('click', () => this.closeSidebar()));

    // Logout
    UI.$('btn-logout')?.addEventListener('click', e => { e.preventDefault(); this.logout(); });

    // Login form
    UI.$('login-form')?.addEventListener('submit', e => { e.preventDefault(); this.doLogin(); });

    // Hash routing
    window.addEventListener('hashchange', () => this.route());

    // Boot
    if (Api.token && this.user) { this.showApp(); this.route(); }
    else this.showLogin();
  },

  closeSidebar() { UI.$('sidebar').classList.remove('open'); UI.hide('sidebar-overlay'); },

  showLogin() { UI.show('login-screen'); UI.hide('app-shell'); },
  isAdmin() { return this.user?.role === 'admin'; },

  showApp() {
    UI.hide('login-screen'); UI.show('app-shell');
    if (this.user) {
      UI.$('sidebar-user').textContent = this.user.username || this.user.email;
      UI.$('sidebar-role').textContent = this.user.role || '';
    }
    // Role-based sidebar visibility
    const role = this.user?.role || 'user';
    document.querySelectorAll('[data-role]').forEach(el => {
      const allowed = el.dataset.role.split(',');
      el.style.display = allowed.includes(role) ? '' : 'none';
    });
    if (!location.hash || location.hash === '#') location.hash = '#dashboard';
  },

  async doLogin() {
    const email = UI.$('login-email').value.trim();
    const password = UI.$('login-password').value;
    UI.hide('login-alert'); UI.show('login-spinner'); UI.$('login-btn').disabled = true;
    try {
      const res = await fetch(API_BASE + '/auth/login', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.errors?.[0]?.message || 'Login failed');
      Api.setAuth(json.data);
      this.user = json.data.user;
      localStorage.setItem('crud_user', JSON.stringify(this.user));
      this.showApp(); this.route();
    } catch (err) {
      UI.show('login-alert'); UI.$('login-alert').textContent = err.message;
    } finally { UI.hide('login-spinner'); UI.$('login-btn').disabled = false; }
  },

  logout() { Api.clearAuth(); this.user = null; localStorage.removeItem('crud_user'); this.showLogin(); },

  route() {
    const hash = (location.hash || '#dashboard').slice(1);
    const [page, ...rest] = hash.split('/');
    this.currentPage = page;

    // Highlight sidebar
    document.querySelectorAll('.sidebar-link[data-page]').forEach(a => {
      a.classList.toggle('active', a.dataset.page === page);
    });
    UI.$('page-title').textContent = { dashboard: 'Dashboard', tables: 'Tables', users: 'Users', apikeys: 'API Keys', queues: 'Queues', apiexplorer: 'API Explorer', profile: 'Profile' }[page] || page;

    // Block access based on role
    const role = this.user?.role || 'user';
    const pageRoles = { users: ['admin'], apikeys: ['admin', 'developer'], queues: ['admin', 'developer'], apiexplorer: ['admin', 'developer'] };
    if (pageRoles[page] && !pageRoles[page].includes(role)) {
      location.hash = '#dashboard';
      return;
    }

    const area = UI.$('content-area');
    area.innerHTML = UI.loading();

    switch (page) {
      case 'dashboard': Views.dashboard(); break;
      case 'tables': Views.tables(); break;
      case 'table': Views.tableDetail(rest[0], rest[1] || 'structure'); break;
      case 'users': Views.users(); break;
      case 'apikeys': Views.apiKeys(); break;
      case 'queues': Views.queues(); break;
      case 'queue': Views.queueDetail(rest[0]); break;
      case 'apiexplorer': Views.apiExplorer(); break;
      case 'profile': Views.profile(); break;
      default: area.innerHTML = '<div class="empty-state"><i class="bi bi-question-circle"></i>Page not found</div>';
    }
  }
};

/* ========================================================
   Views
   ======================================================== */
const Views = {
  SYSTEM_TABLES: ['users', 'api_keys', 'queues', 'queue_messages'],

  /* ---------- Dashboard ---------- */
  async dashboard() {
    const area = UI.$('content-area');
    try {
      const promises = [
        Api.get('/schema/tables').catch(() => ({ data: [] })),
      ];
      // Only admin can fetch user count
      if (App.isAdmin()) {
        promises.push(Api.get('/users?per_page=1').catch(() => ({ data: [], meta: { total: 0 } })));
      }
      const [tablesRes, usersRes] = await Promise.all(promises);
      const allTables = tablesRes.data || [];
      const dataTables = allTables.filter(t => !this.SYSTEM_TABLES.includes(t.TABLE_NAME));
      const tableCount = dataTables.length;
      const userCount = usersRes ? (usersRes.meta?.total ?? usersRes.data?.length ?? 0) : '—';
      area.innerHTML = `
        <div class="row g-4 mb-4">
          <div class="col-sm-6 col-lg-3"><div class="card stat-card shadow-sm"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-table"></i></div>
            <div><div class="fs-4 fw-bold">${tableCount}</div><div class="text-body-secondary small">Tables</div></div>
          </div></div></div>
          <div class="col-sm-6 col-lg-3"><div class="card stat-card shadow-sm"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people"></i></div>
            <div><div class="fs-4 fw-bold">${userCount}</div><div class="text-body-secondary small">Users</div></div>
          </div></div></div>
          <div class="col-sm-6 col-lg-3"><div class="card stat-card shadow-sm"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-shield-lock"></i></div>
            <div><div class="fs-4 fw-bold">${UI.esc(App.user?.role)}</div><div class="text-body-secondary small">Your Role</div></div>
          </div></div></div>
          <div class="col-sm-6 col-lg-3"><div class="card stat-card shadow-sm"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-link-45deg"></i></div>
            <div><div class="fs-5 fw-bold font-monospace">${UI.esc(API_BASE)}</div><div class="text-body-secondary small">API Endpoint</div></div>
          </div></div></div>
        </div>
        <div class="row g-4">
          <div class="col-lg-8"><div class="card shadow-sm"><div class="card-header fw-semibold"><i class="bi bi-table me-2"></i>Tables</div>
            <div class="card-body p-0" id="dash-tables">${UI.loading()}</div></div></div>
          <div class="col-lg-4"><div class="card shadow-sm"><div class="card-header fw-semibold"><i class="bi bi-terminal me-2"></i>Quick Actions</div>
            <div class="card-body">
              <a href="#tables" class="btn btn-outline-primary w-100 mb-2"><i class="bi bi-${App.isAdmin() ? 'plus-lg' : 'table'} me-2"></i>${App.isAdmin() ? 'Create Table' : 'Browse Tables'}</a>
              ${App.isAdmin() ? '<a href="#users" class="btn btn-outline-success w-100 mb-2"><i class="bi bi-person-plus me-2"></i>Manage Users</a>' : ''}
              ${App.isAdmin() || App.user?.role === 'developer' ? '<a href="#apikeys" class="btn btn-outline-warning w-100 mb-2"><i class="bi bi-key me-2"></i>API Keys</a>' : ''}
              <a href="#profile" class="btn btn-outline-secondary w-100"><i class="bi bi-person-circle me-2"></i>Profile</a>
            </div>
          </div></div>
        </div>`;
      // Fill tables mini-list (exclude system tables)
      const dt = document.getElementById('dash-tables');
      if (dataTables.length) {
        dt.innerHTML = '<div class="list-group list-group-flush">' + dataTables.map(t =>
          `<a href="#table/${UI.esc(t.TABLE_NAME)}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span><i class="bi bi-table me-2 text-primary"></i>${UI.esc(t.TABLE_NAME)}</span>
            <span class="badge bg-secondary rounded-pill">${t.TABLE_ROWS ?? '?'} rows</span></a>`
        ).join('') + '</div>';
      } else dt.innerHTML = '<div class="empty-state py-4"><i class="bi bi-inbox"></i>No tables yet</div>';
    } catch (err) { area.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  /* ---------- Tables List ---------- */
  async tables() {
    const area = UI.$('content-area');
    try {
      const res = await Api.get('/schema/tables');
      const tables = (res.data || []).filter(t => !this.SYSTEM_TABLES.includes(t.TABLE_NAME));
      area.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="text-body-secondary">${tables.length} table(s)</span>
          ${App.isAdmin() ? '<button class="btn btn-primary" id="btn-new-table"><i class="bi bi-plus-lg me-1"></i>New Table</button>' : ''}
        </div>
        ${tables.length ? `<div class="row g-3">${tables.map(t => `
          <div class="col-sm-6 col-lg-4">
            <div class="card shadow-sm h-100 pointer" onclick="location.hash='#table/${UI.esc(t.TABLE_NAME)}'">
              <div class="card-body">
                <h6 class="fw-bold mb-1"><i class="bi bi-table me-2 text-primary"></i>${UI.esc(t.TABLE_NAME)}</h6>
                <div class="text-body-secondary small">Engine: ${UI.esc(t.ENGINE)} &middot; Rows: ${t.TABLE_ROWS ?? '?'}</div>
              </div>
            </div>
          </div>`).join('')}</div>`
        : '<div class="empty-state"><i class="bi bi-inbox"></i>No tables yet. Create one to get started.</div>'}`;
      UI.$('btn-new-table')?.addEventListener('click', () => this.showCreateTableModal());
    } catch (err) { area.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  showCreateTableModal() {
    const body = `
      <div class="mb-3"><label class="form-label fw-semibold">Table Name</label>
        <input type="text" id="new-tbl-name" class="form-control" placeholder="e.g. products" pattern="[a-zA-Z_][a-zA-Z0-9_]*" required></div>
      <div class="fw-semibold mb-2">Columns</div>
      <div id="new-tbl-cols"></div>
      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btn-add-col-row"><i class="bi bi-plus me-1"></i>Add Column</button>
      <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="new-tbl-auto-id" checked>
        <label class="form-check-label" for="new-tbl-auto-id">Auto-add <code>id</code> (INT AUTO_INCREMENT PRIMARY KEY)</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="new-tbl-timestamps" checked>
        <label class="form-check-label" for="new-tbl-timestamps">Auto-add <code>created_at</code> / <code>updated_at</code></label></div>`;
    const footer = '<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="btn-create-table"><i class="bi bi-plus-lg me-1"></i>Create</button>';
    UI.modal('Create New Table', body, footer);

    const addColRow = () => {
      document.getElementById('new-tbl-cols').insertAdjacentHTML('beforeend', `
        <div class="row g-2 mb-2 col-row">
          <div class="col-3"><input type="text" class="form-control form-control-sm col-name" placeholder="Column name"></div>
          <div class="col-3"><select class="form-select form-select-sm col-type">${UI.typeOptions('VARCHAR')}</select></div>
          <div class="col-2"><input type="number" class="form-control form-control-sm col-len" placeholder="Length" value="255"></div>
          <div class="col-2"><select class="form-select form-select-sm col-null"><option value="true">Nullable</option><option value="false">NOT NULL</option></select></div>
          <div class="col-2 d-flex gap-1">
            <input type="text" class="form-control form-control-sm col-def" placeholder="Default">
            <button type="button" class="btn btn-sm btn-outline-danger col-rm"><i class="bi bi-x"></i></button>
          </div>
        </div>`);
    };
    addColRow();
    document.getElementById('btn-add-col-row').addEventListener('click', addColRow);
    document.getElementById('new-tbl-cols').addEventListener('click', e => {
      if (e.target.closest('.col-rm')) e.target.closest('.col-row').remove();
    });
    document.getElementById('btn-create-table').addEventListener('click', async () => {
      const name = document.getElementById('new-tbl-name').value.trim();
      if (!name) { UI.toast('Table name is required', 'danger'); return; }
      const columns = {};
      if (document.getElementById('new-tbl-auto-id').checked) {
        columns['id'] = { type: 'INT', auto_increment: true, primary: true };
      }
      document.querySelectorAll('#new-tbl-cols .col-row').forEach(row => {
        const n = row.querySelector('.col-name').value.trim();
        if (!n) return;
        const def = { type: row.querySelector('.col-type').value };
        const len = row.querySelector('.col-len').value;
        if (len && ['VARCHAR','CHAR'].includes(def.type)) def.length = parseInt(len);
        if (len && def.type === 'DECIMAL') { def.precision = parseInt(len); def.scale = 2; }
        if (row.querySelector('.col-null').value === 'false') def.nullable = false;
        const dv = row.querySelector('.col-def').value.trim();
        if (dv) def.default = dv;
        columns[n] = def;
      });
      if (document.getElementById('new-tbl-timestamps').checked) {
        columns['created_at'] = { type: 'TIMESTAMP', default: 'CURRENT_TIMESTAMP' };
        columns['updated_at'] = { type: 'TIMESTAMP', default: 'CURRENT_TIMESTAMP', on_update: 'CURRENT_TIMESTAMP' };
      }
      try {
        await Api.post('/schema/tables', { table: name, columns });
        UI.closeModal(); UI.toast(`Table '${name}' created`);
        location.hash = '#table/' + name;
      } catch (err) { UI.toast(err.message, 'danger'); }
    });
  },

  /* ---------- Table Detail ---------- */
  async tableDetail(table, tab) {
    if (!table) { location.hash = '#tables'; return; }
    const canEdit = App.isAdmin();
    UI.$('page-title').textContent = table;
    const area = UI.$('content-area');
    area.innerHTML = `
      <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
        <a href="#tables" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Tables</a>
        <ul class="nav nav-pills ms-auto" id="table-tabs">
          ${canEdit ? `<li class="nav-item"><a class="nav-link ${tab==='structure'?'active':''} pointer" data-tab="structure"><i class="bi bi-columns-gap me-1"></i>Structure</a></li>` : ''}
          <li class="nav-item"><a class="nav-link ${tab==='data'||!canEdit?'active':''} pointer" data-tab="data"><i class="bi bi-card-list me-1"></i>Data</a></li>
        </ul>
        ${canEdit ? '<button class="btn btn-sm btn-outline-danger" id="btn-drop-table"><i class="bi bi-trash me-1"></i>Drop Table</button>' : ''}
      </div>
      <div id="table-tab-content">${UI.loading()}</div>`;

    document.querySelectorAll('#table-tabs .nav-link').forEach(a => a.addEventListener('click', () => {
      location.hash = `#table/${table}/${a.dataset.tab}`;
    }));
    UI.$('btn-drop-table')?.addEventListener('click', async () => {
      if (await UI.confirm(`Drop table "${table}"? This cannot be undone.`)) {
        try { await Api.del('/schema/tables/' + table); UI.toast(`Table '${table}' dropped`); location.hash = '#tables'; }
        catch (err) { UI.toast(err.message, 'danger'); }
      }
    });

    // Non-admin always goes to data tab
    if (!canEdit) tab = 'data';

    if (tab === 'data') this.tableData(table);
    else this.tableStructure(table);
  },

  async tableStructure(table) {
    const ct = document.getElementById('table-tab-content');
    try {
      const res = await Api.get('/schema/tables/' + table);
      const cols = res.data?.columns || [];
      ct.innerHTML = `
        <div class="card shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-columns-gap me-2"></i>Columns (${cols.length})</span>
            <button class="btn btn-sm btn-primary" id="btn-add-col"><i class="bi bi-plus-lg me-1"></i>Add Column</button>
          </div>
          <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Name</th><th>Type</th><th>Nullable</th><th>Default</th><th>Key</th><th>Extra</th><th width="120"></th></tr></thead>
            <tbody>${cols.map(c => `<tr>
              <td class="fw-semibold font-monospace">${UI.esc(c.COLUMN_NAME)}</td>
              <td>${UI.typeBadge(c.COLUMN_TYPE)}</td>
              <td>${c.IS_NULLABLE==='YES'?'<span class="text-success">Yes</span>':'<span class="text-danger">No</span>'}</td>
              <td class="text-body-secondary font-monospace small">${UI.esc(c.COLUMN_DEFAULT ?? 'NULL')}</td>
              <td>${c.COLUMN_KEY ? `<span class="badge bg-info">${UI.esc(c.COLUMN_KEY)}</span>` : ''}</td>
              <td class="text-body-secondary small">${UI.esc(c.EXTRA)}</td>
              <td class="table-actions text-end">
                <button class="btn btn-outline-primary btn-sm me-1" data-mod="${UI.esc(c.COLUMN_NAME)}" title="Modify"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-danger btn-sm" data-drop="${UI.esc(c.COLUMN_NAME)}" title="Drop"><i class="bi bi-trash"></i></button>
              </td></tr>`).join('')}</tbody></table></div></div>`;

      UI.$('btn-add-col').addEventListener('click', () => this.showAddColumnModal(table));
      ct.querySelectorAll('[data-mod]').forEach(b => b.addEventListener('click', () => this.showModifyColumnModal(table, b.dataset.mod)));
      ct.querySelectorAll('[data-drop]').forEach(b => b.addEventListener('click', async () => {
        if (await UI.confirm(`Drop column "${b.dataset.drop}"?`)) {
          try { await Api.del(`/schema/tables/${table}/columns/${b.dataset.drop}`); UI.toast('Column dropped'); this.tableStructure(table); }
          catch (err) { UI.toast(err.message, 'danger'); }
        }
      }));
    } catch (err) { ct.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  showAddColumnModal(table) {
    const body = `
      <div class="mb-3"><label class="form-label fw-semibold">Column Name</label><input type="text" id="ac-name" class="form-control" required></div>
      <div class="row g-3 mb-3">
        <div class="col-6"><label class="form-label">Type</label><select id="ac-type" class="form-select">${UI.typeOptions()}</select></div>
        <div class="col-3"><label class="form-label">Length</label><input type="number" id="ac-len" class="form-control" value="255"></div>
        <div class="col-3"><label class="form-label">Nullable</label><select id="ac-null" class="form-select"><option value="true">Yes</option><option value="false">No</option></select></div>
      </div>
      <div class="mb-3"><label class="form-label">Default Value</label><input type="text" id="ac-def" class="form-control" placeholder="Leave empty for none"></div>`;
    const footer = '<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="ac-save">Add Column</button>';
    UI.modal('Add Column to ' + table, body, footer);
    document.getElementById('ac-save').addEventListener('click', async () => {
      const name = document.getElementById('ac-name').value.trim();
      if (!name) { UI.toast('Column name required', 'danger'); return; }
      const def = { type: document.getElementById('ac-type').value };
      const len = document.getElementById('ac-len').value;
      if (len && ['VARCHAR','CHAR'].includes(def.type)) def.length = parseInt(len);
      if (len && def.type === 'DECIMAL') { def.precision = parseInt(len); def.scale = 2; }
      if (document.getElementById('ac-null').value === 'false') def.nullable = false;
      const dv = document.getElementById('ac-def').value.trim();
      if (dv) def.default = dv;
      try {
        await Api.post(`/schema/tables/${table}/columns`, { column: name, definition: def });
        UI.closeModal(); UI.toast('Column added'); this.tableStructure(table);
      } catch (err) { UI.toast(err.message, 'danger'); }
    });
  },

  showModifyColumnModal(table, column) {
    const body = `
      <div class="mb-3"><label class="form-label fw-semibold">Column: <code>${UI.esc(column)}</code></label></div>
      <div class="row g-3 mb-3">
        <div class="col-6"><label class="form-label">New Type</label><select id="mc-type" class="form-select">${UI.typeOptions()}</select></div>
        <div class="col-3"><label class="form-label">Length</label><input type="number" id="mc-len" class="form-control" value="255"></div>
        <div class="col-3"><label class="form-label">Nullable</label><select id="mc-null" class="form-select"><option value="true">Yes</option><option value="false">No</option></select></div>
      </div>
      <div class="mb-3"><label class="form-label">Default Value</label><input type="text" id="mc-def" class="form-control" placeholder="Leave empty for none"></div>`;
    const footer = '<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="mc-save">Save Changes</button>';
    UI.modal('Modify Column: ' + column, body, footer);
    document.getElementById('mc-save').addEventListener('click', async () => {
      const def = { type: document.getElementById('mc-type').value };
      const len = document.getElementById('mc-len').value;
      if (len && ['VARCHAR','CHAR'].includes(def.type)) def.length = parseInt(len);
      if (len && def.type === 'DECIMAL') { def.precision = parseInt(len); def.scale = 2; }
      if (document.getElementById('mc-null').value === 'false') def.nullable = false;
      const dv = document.getElementById('mc-def').value.trim();
      if (dv) def.default = dv;
      try {
        await Api.patch(`/schema/tables/${table}/columns/${column}`, { definition: def });
        UI.closeModal(); UI.toast('Column modified'); this.tableStructure(table);
      } catch (err) { UI.toast(err.message, 'danger'); }
    });
  },

  /* ---------- Table Data ---------- */
  _tableColumns: {},

  _perPage: parseInt(localStorage.getItem('crud_per_page') || '20'),
  _sortCol: null,
  _sortDir: 'ASC',

  async tableData(table, page = 1) {
    const ct = document.getElementById('table-tab-content');
    const canEdit = App.isAdmin();
    try {
      const perPage = this._perPage;
      let dataUrl = `/${table}?page=${page}&per_page=${perPage}`;
      if (this._sortCol) dataUrl += `&order_by=${encodeURIComponent(this._sortCol)}&order_dir=${this._sortDir}`;
      // Fetch data AND schema columns in parallel
      const [res, schemaRes] = await Promise.all([
        Api.get(dataUrl),
        Api.get('/schema/tables/' + table)
      ]);
      const rows = res.data || [];
      const meta = res.meta || {};
      const schemaCols = schemaRes.data?.columns || [];
      this._tableColumns[table] = schemaCols;
      const colNames = schemaCols.map(c => c.COLUMN_NAME);

      const actionColHeader = canEdit ? '<th width="100"></th>' : '';
      const rowActions = (r) => canEdit ? `<td class="table-actions text-end text-nowrap">
                <button class="btn btn-outline-primary btn-sm me-1" data-edit='${JSON.stringify(r).replace(/'/g,"&#39;")}'><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-danger btn-sm" data-del="${r.id ?? ''}"><i class="bi bi-trash"></i></button>
              </td>` : '';

      ct.innerHTML = `
        <div class="card shadow-sm">
          <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
              <span class="fw-semibold"><i class="bi bi-card-list me-2"></i>Data</span>
              <span class="badge bg-secondary">${meta.total ?? rows.length} records</span>
              ${!canEdit ? '<span class="badge bg-warning text-dark"><i class="bi bi-eye me-1"></i>Read Only</span>' : ''}
            </div>
            <div class="d-flex gap-2">
              <div class="input-group input-group-sm" style="width:250px">
                <input type="text" class="form-control" id="data-search" placeholder="Search...">
                <button class="btn btn-outline-secondary" id="data-search-btn"><i class="bi bi-search"></i></button>
              </div>
              ${canEdit ? '<button class="btn btn-sm btn-primary" id="btn-add-row"><i class="bi bi-plus-lg me-1"></i>New Record</button>' : ''}
            </div>
          </div>
          ${rows.length ? `<div class="table-responsive"><table class="table table-hover table-sm mb-0 align-middle">
            <thead><tr>${colNames.map(c => {
              const isSorted = this._sortCol === c;
              const arrow = isSorted ? (this._sortDir === 'ASC' ? ' <i class="bi bi-sort-up"></i>' : ' <i class="bi bi-sort-down"></i>') : ' <i class="bi bi-chevron-expand opacity-25"></i>';
              return `<th class="small text-nowrap pointer" data-sort-col="${UI.esc(c)}">${UI.esc(c)}${arrow}</th>`;
            }).join('')}${actionColHeader}</tr></thead>
            <tbody>${rows.map(r => `<tr>${colNames.map(c => `<td class="truncate-cell small">${UI.esc(String(r[c] ?? ''))}</td>`).join('')}${rowActions(r)}</tr>`).join('')}</tbody></table></div>` :
            '<div class="empty-state py-4"><i class="bi bi-inbox"></i>No records found</div>'}
          <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2" id="data-pagination">
            <div class="d-flex align-items-center gap-2 small">
              <span class="text-body-secondary">Rows per page:</span>
              <select class="form-select form-select-sm" id="per-page-select" style="width:auto">
                ${[10,20,50,100].map(n => `<option value="${n}" ${n===perPage?'selected':''}>${n}</option>`).join('')}
              </select>
              <span class="text-body-secondary ms-2">Page ${meta.page ?? 1} of ${meta.total_pages ?? 1}</span>
            </div>
            <div id="data-page-nav"></div>
          </div>
        </div>`;

      // Column sort
      ct.querySelectorAll('[data-sort-col]').forEach(th => {
        th.addEventListener('click', () => {
          const col = th.dataset.sortCol;
          if (this._sortCol === col) {
            this._sortDir = this._sortDir === 'ASC' ? 'DESC' : 'ASC';
          } else {
            this._sortCol = col;
            this._sortDir = 'ASC';
          }
          this.tableData(table, 1);
        });
      });

      // Per-page selector
      UI.$('per-page-select')?.addEventListener('change', (e) => {
        this._perPage = parseInt(e.target.value);
        localStorage.setItem('crud_per_page', this._perPage);
        this.tableData(table, 1);
      });

      // Pagination nav
      if (meta.total_pages > 1) {
        const curPage = meta.page;
        const totalPages = meta.total_pages;
        let nav = '<nav><ul class="pagination pagination-sm mb-0">';
        nav += `<li class="page-item ${curPage<=1?'disabled':''}"><a class="page-link pointer" data-page="${curPage-1}"><i class="bi bi-chevron-left"></i></a></li>`;
        // Smart page range: show first, last, and pages around current
        const pages = new Set();
        pages.add(1); pages.add(totalPages);
        for (let i = Math.max(1, curPage-2); i <= Math.min(totalPages, curPage+2); i++) pages.add(i);
        let prev = 0;
        [...pages].sort((a,b) => a-b).forEach(i => {
          if (i - prev > 1) nav += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
          nav += `<li class="page-item ${i===curPage?'active':''}"><a class="page-link pointer" data-page="${i}">${i}</a></li>`;
          prev = i;
        });
        nav += `<li class="page-item ${curPage>=totalPages?'disabled':''}"><a class="page-link pointer" data-page="${curPage+1}"><i class="bi bi-chevron-right"></i></a></li>`;
        nav += '</ul></nav>';
        document.getElementById('data-page-nav').innerHTML = nav;
        document.getElementById('data-page-nav').addEventListener('click', e => {
          const pg = e.target.closest('[data-page]');
          if (pg && !pg.closest('.disabled')) this.tableData(table, parseInt(pg.dataset.page));
        });
      }

      // Search
      const searchableCols = schemaCols.filter(c => {
        const t = (c.DATA_TYPE || '').toLowerCase();
        return ['varchar','char','text','mediumtext','longtext','enum','set'].includes(t);
      }).map(c => c.COLUMN_NAME);
      const doSearch = async () => {
        const q = document.getElementById('data-search').value.trim();
        if (!q) { this.tableData(table, 1); return; }
        try {
          const searchCol = searchableCols.length ? searchableCols[0] : colNames.filter(c => c !== 'id')[0];
          if (!searchCol) return;
          const sr = await Api.get(`/${table}/filter?${encodeURIComponent(searchCol)}[like]=%${encodeURIComponent(q)}%&per_page=${perPage}`);
          const sRows = sr.data || [];
          const tbody = ct.querySelector('tbody');
          if (tbody && sRows.length) {
            tbody.innerHTML = sRows.map(r => `<tr>${colNames.map(c => `<td class="truncate-cell small">${UI.esc(String(r[c] ?? ''))}</td>`).join('')}${rowActions(r)}</tr>`).join('');
            if (canEdit) this.bindDataActions(table, ct, schemaCols);
          } else if (tbody) { tbody.innerHTML = `<tr><td colspan="${colNames.length + (canEdit ? 1 : 0)}" class="text-center text-body-secondary py-3">No results for "${UI.esc(q)}"</td></tr>`; }
        } catch(err) { UI.toast(err.message, 'danger'); }
      };
      UI.$('data-search-btn')?.addEventListener('click', doSearch);
      UI.$('data-search')?.addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });

      if (canEdit) {
        // Add record — system tables use dedicated modals
        const isSystemUsers = table === 'users';
        const isSystemApiKeys = table === 'api_keys';
        if (isSystemUsers) {
          UI.$('btn-add-row')?.addEventListener('click', () => this.showUserModal(null, () => this.tableData(table)));
        } else if (isSystemApiKeys) {
          UI.$('btn-add-row')?.addEventListener('click', () => { location.hash = '#apikeys'; });
        } else {
          UI.$('btn-add-row')?.addEventListener('click', () => this.showRecordModal(table, schemaCols));
        }

        // Edit / Delete
        this.bindDataActions(table, ct, schemaCols);
      }
    } catch (err) { ct.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  bindDataActions(table, container, schemaCols) {
    const isSystemUsers = table === 'users';
    container.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => {
      const row = JSON.parse(b.dataset.edit);
      if (isSystemUsers) {
        this.showUserModal(row, () => this.tableData(table));
      } else {
        this.showRecordModal(table, schemaCols, row);
      }
    }));
    container.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', async () => {
      const id = b.dataset.del;
      if (!id) { UI.toast('No id column found', 'danger'); return; }
      if (await UI.confirm('Delete this record?')) {
        try {
          const endpoint = isSystemUsers ? `/users/${id}` : `/${table}/${id}`;
          await Api.del(endpoint); UI.toast('Record deleted'); this.tableData(table);
        } catch (err) { UI.toast(err.message, 'danger'); }
      }
    }));
  },

  showRecordModal(table, schemaCols, existing = null) {
    const isEdit = existing !== null;
    // Determine which columns to show as fields
    const autoSkip = ['created_at','updated_at'];
    const fields = schemaCols.filter(col => {
      const name = col.COLUMN_NAME;
      // Always skip auto-generated timestamps for new records
      if (!isEdit && autoSkip.includes(name)) return false;
      // Skip auto_increment columns for new records
      if (!isEdit && (col.EXTRA || '').toLowerCase().includes('auto_increment')) return false;
      // Show auto_increment as readonly for edits
      return true;
    });

    const body = fields.map(col => {
      const name = col.COLUMN_NAME;
      const val = existing ? (existing[name] ?? '') : '';
      const isAuto = (col.EXTRA || '').toLowerCase().includes('auto_increment');
      const ro = isAuto && isEdit ? 'readonly' : '';
      const dataType = (col.DATA_TYPE || '').toLowerCase();
      const colType = (col.COLUMN_TYPE || '').toLowerCase();
      const isNullable = col.IS_NULLABLE === 'YES';
      const req = (!isNullable && !isAuto && !col.COLUMN_DEFAULT) ? 'required' : '';

      // Choose input type based on column data type
      let input = '';
      if (['text','mediumtext','longtext'].includes(dataType)) {
        input = `<textarea class="form-control rec-field" data-col="${UI.esc(name)}" rows="3" ${ro} ${req}>${UI.esc(String(val))}</textarea>`;
      } else if (['date'].includes(dataType)) {
        input = `<input type="date" class="form-control rec-field" data-col="${UI.esc(name)}" value="${UI.esc(String(val))}" ${ro} ${req}>`;
      } else if (['datetime','timestamp'].includes(dataType)) {
        const dtVal = val ? String(val).replace(' ', 'T').slice(0, 16) : '';
        input = `<input type="datetime-local" class="form-control rec-field" data-col="${UI.esc(name)}" value="${UI.esc(dtVal)}" ${ro} ${req}>`;
      } else if (['time'].includes(dataType)) {
        input = `<input type="time" class="form-control rec-field" data-col="${UI.esc(name)}" value="${UI.esc(String(val))}" ${ro} ${req}>`;
      } else if (['int','bigint','smallint','tinyint','mediumint'].includes(dataType) && !colType.includes('tinyint(1)')) {
        input = `<input type="number" class="form-control rec-field" data-col="${UI.esc(name)}" value="${UI.esc(String(val))}" step="1" ${ro} ${req}>`;
      } else if (colType.includes('tinyint(1)') || dataType === 'boolean') {
        const checked = val == 1 || val === true ? 'checked' : '';
        input = `<div class="form-check form-switch mt-1"><input type="checkbox" class="form-check-input rec-field rec-bool" data-col="${UI.esc(name)}" ${checked} ${ro ? 'disabled' : ''}></div>`;
      } else if (['decimal','float','double','numeric'].includes(dataType)) {
        input = `<input type="number" class="form-control rec-field" data-col="${UI.esc(name)}" value="${UI.esc(String(val))}" step="any" ${ro} ${req}>`;
      } else if (dataType === 'enum') {
        // Parse enum values from COLUMN_TYPE like enum('a','b','c')
        const enumMatch = (col.COLUMN_TYPE || '').match(/enum\((.+)\)/i);
        const opts = enumMatch ? enumMatch[1].split(',').map(v => v.trim().replace(/^'|'$/g, '')) : [];
        input = `<select class="form-select rec-field" data-col="${UI.esc(name)}" ${ro} ${req}>
          ${isNullable ? '<option value="">-- None --</option>' : ''}
          ${opts.map(o => `<option value="${UI.esc(o)}" ${String(val) === o ? 'selected' : ''}>${UI.esc(o)}</option>`).join('')}
        </select>`;
      } else if (dataType === 'json') {
        const jsonVal = typeof val === 'object' ? JSON.stringify(val, null, 2) : String(val);
        input = `<textarea class="form-control font-monospace rec-field" data-col="${UI.esc(name)}" rows="4" ${ro} ${req}>${UI.esc(jsonVal)}</textarea>`;
      } else {
        // varchar, char, and everything else
        const maxLen = col.CHARACTER_MAXIMUM_LENGTH ? ` maxlength="${col.CHARACTER_MAXIMUM_LENGTH}"` : '';
        input = `<input type="text" class="form-control rec-field" data-col="${UI.esc(name)}" value="${UI.esc(String(val))}"${maxLen} ${ro} ${req}>`;
      }

      // Build label with type hint
      const typeHint = UI.typeBadge(col.COLUMN_TYPE);
      const keyBadge = col.COLUMN_KEY ? ` <span class="badge bg-info">${UI.esc(col.COLUMN_KEY)}</span>` : '';
      const nullBadge = isNullable ? ' <span class="text-body-secondary small">(nullable)</span>' : '';

      return `<div class="mb-3">
        <label class="form-label fw-semibold">
          <span class="font-monospace">${UI.esc(name)}</span>
          <span class="ms-2">${typeHint}</span>${keyBadge}${nullBadge}
        </label>
        ${input}
      </div>`;
    }).join('');

    if (!fields.length) {
      UI.modal('Error', '<div class="alert alert-warning mb-0">No editable columns found for this table.</div>', '<button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>');
      return;
    }

    const footer = `<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-primary" id="rec-save"><i class="bi bi-${isEdit ? 'check-lg' : 'plus-lg'} me-1"></i>${isEdit ? 'Update' : 'Create'}</button>`;
    UI.modal((isEdit ? 'Edit Record' : 'New Record') + ' in ' + table, body, footer);
    document.getElementById('rec-save').addEventListener('click', async () => {
      const data = {};
      document.querySelectorAll('.rec-field').forEach(f => {
        if (f.readOnly || f.disabled) return;
        const col = f.dataset.col;
        if (f.classList.contains('rec-bool')) {
          data[col] = f.checked ? 1 : 0;
        } else {
          const v = f.value;
          if (v !== '' || !f.hasAttribute('required')) data[col] = v === '' ? null : v;
        }
      });
      if (!Object.keys(data).length) { UI.toast('No data to submit', 'danger'); return; }
      try {
        if (isEdit && existing.id != null) {
          await Api.patch(`/${table}/${existing.id}`, data);
          UI.toast('Record updated');
        } else {
          await Api.post(`/${table}`, data);
          UI.toast('Record created');
        }
        UI.closeModal(); this.tableData(table);
      } catch (err) { UI.toast(err.message, 'danger'); }
    });
  },

  /* ---------- Users ---------- */
  async users(page = 1) {
    const area = UI.$('content-area');
    try {
      const res = await Api.get(`/users?page=${page}&per_page=15`);
      const users = res.data || [];
      const meta = res.meta || {};
      area.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="text-body-secondary">${meta.total ?? users.length} user(s)</span>
          <button class="btn btn-primary" id="btn-new-user"><i class="bi bi-person-plus me-1"></i>New User</button>
        </div>
        <div class="card shadow-sm">
          <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th><th>Created</th><th width="100"></th></tr></thead>
            <tbody>${users.map(u => `<tr>
              <td>${u.id}</td>
              <td class="fw-semibold">${UI.esc(u.username)}</td>
              <td>${UI.esc(u.email)}</td>
              <td><span class="badge bg-${u.role==='admin'?'danger':u.role==='developer'?'warning':'secondary'}">${UI.esc(u.role)}</span></td>
              <td>${u.is_active==1?'<i class="bi bi-check-circle text-success"></i>':'<i class="bi bi-x-circle text-danger"></i>'}</td>
              <td class="text-body-secondary small">${UI.esc(u.created_at)}</td>
              <td class="table-actions text-end">
                <button class="btn btn-outline-primary btn-sm me-1" data-uedit="${u.id}"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-danger btn-sm" data-udel="${u.id}"><i class="bi bi-trash"></i></button>
              </td></tr>`).join('')}</tbody></table></div>
          <div class="card-footer" id="user-pagination"></div>
        </div>`;
      if (meta.total_pages > 1) {
        document.getElementById('user-pagination').innerHTML = UI.pagination(meta);
        document.getElementById('user-pagination').addEventListener('click', e => {
          const pg = e.target.closest('[data-page]'); if (pg) this.users(parseInt(pg.dataset.page));
        });
      }
      UI.$('btn-new-user')?.addEventListener('click', () => this.showUserModal());
      area.querySelectorAll('[data-uedit]').forEach(b => b.addEventListener('click', async () => {
        const u = users.find(x => x.id == b.dataset.uedit);
        if (u) this.showUserModal(u);
      }));
      area.querySelectorAll('[data-udel]').forEach(b => b.addEventListener('click', async () => {
        if (await UI.confirm('Delete this user?')) {
          try { await Api.del('/users/' + b.dataset.udel); UI.toast('User deleted'); this.users(); }
          catch (err) { UI.toast(err.message, 'danger'); }
        }
      }));
    } catch (err) { area.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  showUserModal(existing = null, onDone = null) {
    const isEdit = !!existing;
    const body = `
      <div class="mb-3"><label class="form-label fw-semibold">Username</label><input type="text" id="uf-name" class="form-control" value="${UI.esc(existing?.username??'')}" required></div>
      <div class="mb-3"><label class="form-label fw-semibold">Email</label><input type="email" id="uf-email" class="form-control" value="${UI.esc(existing?.email??'')}" required></div>
      <div class="mb-3"><label class="form-label fw-semibold">Password ${isEdit?'<span class="text-body-secondary">(leave blank to keep)</span>':''}</label><input type="password" id="uf-pass" class="form-control" autocomplete="new-password" ${isEdit?'':'required'}></div>
      <div class="row g-3">
        <div class="col-6"><label class="form-label fw-semibold">Role</label><select id="uf-role" class="form-select">
          <option value="user" ${existing?.role==='user'?'selected':''}>User</option>
          <option value="admin" ${existing?.role==='admin'?'selected':''}>Admin</option>
          <option value="developer" ${existing?.role==='developer'?'selected':''}>Developer</option></select></div>
        <div class="col-6"><label class="form-label fw-semibold">Active</label><select id="uf-active" class="form-select">
          <option value="1" ${!existing||existing.is_active==1?'selected':''}>Active</option>
          <option value="0" ${existing?.is_active==0?'selected':''}>Inactive</option></select></div>
      </div>`;
    const footer = `<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-primary" id="uf-save">${isEdit?'Update':'Create'}</button>`;
    UI.modal((isEdit ? 'Edit User' : 'New User'), body, footer);
    document.getElementById('uf-save').addEventListener('click', async () => {
      const data = { username: UI.$('uf-name').value.trim(), email: UI.$('uf-email').value.trim(), role: UI.$('uf-role').value, is_active: UI.$('uf-active').value === '1' };
      const pass = UI.$('uf-pass').value;
      if (pass) data.password = pass;
      if (!isEdit && !pass) { UI.toast('Password is required', 'danger'); return; }
      try {
        if (isEdit) { await Api.patch('/users/' + existing.id, data); UI.toast('User updated'); }
        else { await Api.post('/users', data); UI.toast('User created'); }
        UI.closeModal();
        if (onDone) onDone(); else this.users();
      } catch (err) { UI.toast(err.message, 'danger'); }
    });
  },

  /* ---------- API Keys ---------- */
  async apiKeys() {
    const area = UI.$('content-area');
    const isAdmin = App.isAdmin();
    try {
      // Admin sees all keys, developer sees own keys
      const res = isAdmin
        ? await Api.get('/auth/apikeys/all')
        : await Api.get('/auth/apikeys');
      const keys = res.data || [];

      const ownerCol = isAdmin ? '<th>Owner</th>' : '';
      const colCount = isAdmin ? 8 : 7;

      area.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="text-body-secondary">${keys.length} key(s)${isAdmin ? ' (all users)' : ''}</span>
          <button class="btn btn-primary" id="btn-new-key"><i class="bi bi-plus-lg me-1"></i>New API Key</button>
        </div>
        <div class="card shadow-sm">
          <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
            <thead><tr><th>ID</th>${ownerCol}<th>Name</th><th>Active</th><th>Last Used</th><th>Expires</th><th>Created</th><th width="80"></th></tr></thead>
            <tbody>${keys.length ? keys.map(k => `<tr>
              <td>${k.id}</td>
              ${isAdmin ? `<td><span class="fw-semibold">${UI.esc(k.username || '?')}</span><br><span class="text-body-secondary small">${UI.esc(k.user_email || '')}</span></td>` : ''}
              <td class="fw-semibold">${UI.esc(k.name || 'Unnamed')}</td>
              <td>${k.is_active==1?'<i class="bi bi-check-circle text-success"></i>':'<i class="bi bi-x-circle text-danger"></i>'}</td>
              <td class="text-body-secondary small">${UI.esc(k.last_used_at || 'Never')}</td>
              <td class="text-body-secondary small">${UI.esc(k.expires_at || 'Never')}</td>
              <td class="text-body-secondary small">${UI.esc(k.created_at)}</td>
              <td class="table-actions text-end">
                <button class="btn btn-outline-danger btn-sm" data-revoke="${k.id}" ${k.is_active!=1?'disabled':''} title="Revoke"><i class="bi bi-x-lg"></i></button>
              </td></tr>`).join('') : `<tr><td colspan="${colCount}" class="text-center text-body-secondary py-3">No API keys</td></tr>`}</tbody></table></div></div>`;

      // Generate key button (developer only, admin manages via users)
      UI.$('btn-new-key')?.addEventListener('click', async () => {
        const body = `<div class="mb-3"><label class="form-label fw-semibold">Key Name</label><input type="text" id="ak-name" class="form-control" placeholder="e.g. Production Key" required></div>
          <div class="mb-3"><label class="form-label">Expires At <span class="text-body-secondary">(optional)</span></label><input type="datetime-local" id="ak-exp" class="form-control"></div>`;
        const footer = '<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="ak-save">Generate Key</button>';
        UI.modal('Generate API Key', body, footer);
        document.getElementById('ak-save').addEventListener('click', async () => {
          const name = UI.$('ak-name').value.trim();
          if (!name) { UI.toast('Name required', 'danger'); return; }
          const payload = { name };
          const exp = UI.$('ak-exp').value;
          if (exp) payload.expires_at = new Date(exp).toISOString().slice(0, 19).replace('T', ' ');
          try {
            const r = await Api.post('/auth/apikey', payload);
            UI.closeModal();
            UI.modal('API Key Generated', `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Copy this key now. It won't be shown again.</div>
              <div class="input-group"><input type="text" class="form-control font-monospace" value="${UI.esc(r.data.api_key)}" readonly id="ak-val">
              <button class="btn btn-outline-secondary" id="ak-copy"><i class="bi bi-clipboard"></i></button></div>`,
              '<button class="btn btn-primary" data-bs-dismiss="modal">Done</button>');
            document.getElementById('ak-copy')?.addEventListener('click', () => {
              navigator.clipboard?.writeText(document.getElementById('ak-val').value);
              UI.toast('Copied to clipboard');
            });
            // Refresh API keys list when modal is closed
            document.getElementById('app-modal').addEventListener('hidden.bs.modal', () => this.apiKeys(), { once: true });
          } catch (err) { UI.toast(err.message, 'danger'); }
        });
      });

      // Revoke buttons
      area.querySelectorAll('[data-revoke]').forEach(b => b.addEventListener('click', async () => {
        if (await UI.confirm('Revoke this API key?')) {
          try { await Api.del('/auth/apikey/' + b.dataset.revoke); UI.toast('API key revoked'); this.apiKeys(); }
          catch (err) { UI.toast(err.message, 'danger'); }
        }
      }));
    } catch (err) { area.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  /* ---------- Queues ---------- */
  async queues() {
    const area = UI.$('content-area');
    const isAdmin = App.isAdmin();
    try {
      const res = await Api.get('/queues');
      const queues = res.data || [];

      area.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="text-body-secondary">${queues.length} queue(s)</span>
          ${isAdmin ? '<button class="btn btn-primary" id="btn-new-queue"><i class="bi bi-plus-lg me-1"></i>New Queue</button>' : ''}
        </div>
        <div class="card shadow-sm">
          <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Name</th><th>Direction</th><th>Delivery</th><th>Pending</th><th>Processing</th><th>Completed</th><th>Failed</th><th>Dead</th><th>Active</th><th width="80"></th></tr></thead>
            <tbody>${queues.length ? queues.map(q => `<tr>
              <td><a href="#queue/${UI.esc(q.name)}" class="fw-semibold text-decoration-none">${UI.esc(q.name)}</a>${q.description ? `<br><span class="text-body-secondary small">${UI.esc(q.description)}</span>` : ''}</td>
              <td><span class="badge ${q.direction === 'inbound' ? 'bg-info' : 'bg-warning'} text-dark">${UI.esc(q.direction)}</span></td>
              <td><span class="badge bg-secondary">${UI.esc(q.delivery)}</span></td>
              <td>${q.pending_count || 0}</td>
              <td>${q.processing_count || 0}</td>
              <td class="text-success">${q.completed_count || 0}</td>
              <td class="text-warning">${q.failed_count || 0}</td>
              <td class="text-danger">${q.dead_count || 0}</td>
              <td>${q.is_active == 1 ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>'}</td>
              <td class="text-end">${isAdmin ? `<button class="btn btn-outline-danger btn-sm" data-del-queue="${UI.esc(q.name)}" title="Delete"><i class="bi bi-trash"></i></button>` : ''}</td>
            </tr>`).join('') : '<tr><td colspan="10" class="text-center text-body-secondary py-3">No queues yet</td></tr>'}</tbody>
          </table></div>
        </div>`;

      // New Queue button
      UI.$('btn-new-queue')?.addEventListener('click', () => this.showQueueModal());

      // Delete buttons
      if (isAdmin) {
        area.querySelectorAll('[data-del-queue]').forEach(b => b.addEventListener('click', async () => {
          if (await UI.confirm('Delete queue "' + b.dataset.delQueue + '" and all its messages?')) {
            try { await Api.del('/queues/' + b.dataset.delQueue); UI.toast('Queue deleted'); this.queues(); }
            catch (err) { UI.toast(err.message, 'danger'); }
          }
        }));
      }
    } catch (err) { area.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  showQueueModal(existing = null) {
    const isEdit = !!existing;
    const body = `
      <div class="mb-3"><label class="form-label fw-semibold">Name</label>
        <input type="text" id="q-name" class="form-control" value="${UI.esc(existing?.name || '')}" ${isEdit ? 'readonly' : ''} placeholder="e.g. order-notifications" required></div>
      <div class="row g-3 mb-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Direction</label>
          <select id="q-direction" class="form-select" ${isEdit ? 'disabled' : ''}>
            <option value="inbound" ${existing?.direction==='inbound'?'selected':''}>Inbound (job queue)</option>
            <option value="outbound" ${existing?.direction==='outbound'?'selected':''}>Outbound (external consumer)</option>
          </select></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Delivery</label>
          <select id="q-delivery" class="form-select" ${isEdit ? 'disabled' : ''}>
            <option value="handler" ${existing?.delivery==='handler'?'selected':''}>Handler (PHP worker)</option>
            <option value="pull" ${existing?.delivery==='pull'?'selected':''}>Pull (consumer polls)</option>
            <option value="push" ${existing?.delivery==='push'?'selected':''}>Push (webhook POST)</option>
          </select></div>
      </div>
      <div class="mb-3" id="q-url-group"><label class="form-label fw-semibold">Delivery URL <span class="text-body-secondary">(push only)</span></label>
        <input type="url" id="q-url" class="form-control" value="${UI.esc(existing?.delivery_url || '')}" placeholder="https://example.com/webhook"></div>
      <div class="mb-3"><label class="form-label fw-semibold">Secret <span class="text-body-secondary">(HMAC signing, optional)</span></label>
        <input type="text" id="q-secret" class="form-control" value="${UI.esc(existing?.secret || '')}" placeholder="hmac_secret_key"></div>
      <div class="mb-3"><label class="form-label fw-semibold">Description <span class="text-body-secondary">(optional)</span></label>
        <input type="text" id="q-desc" class="form-control" value="${UI.esc(existing?.description || '')}"></div>
      <div class="row g-3 mb-3">
        <div class="col-md-4"><label class="form-label">Max Attempts</label>
          <input type="number" id="q-attempts" class="form-control" value="${existing?.max_attempts ?? 3}" min="1"></div>
        <div class="col-md-4"><label class="form-label">Retry Delay (s)</label>
          <input type="number" id="q-delay" class="form-control" value="${existing?.retry_delay ?? 30}" min="1"></div>
        <div class="col-md-4"><label class="form-label">Visibility Timeout (s)</label>
          <input type="number" id="q-timeout" class="form-control" value="${existing?.visibility_timeout ?? 60}" min="1"></div>
      </div>`;
    const footer = `<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-primary" id="q-save"><i class="bi bi-${isEdit ? 'check-lg' : 'plus-lg'} me-1"></i>${isEdit ? 'Update' : 'Create'}</button>`;
    UI.modal((isEdit ? 'Edit Queue' : 'New Queue'), body, footer);

    // Toggle URL field visibility
    const toggleUrl = () => {
      const show = UI.$('q-delivery').value === 'push';
      UI.$('q-url-group').style.display = show ? '' : 'none';
    };
    toggleUrl();
    UI.$('q-delivery').addEventListener('change', toggleUrl);

    document.getElementById('q-save').addEventListener('click', async () => {
      const name = UI.$('q-name').value.trim();
      if (!name) { UI.toast('Name required', 'danger'); return; }
      const payload = {
        name,
        direction: UI.$('q-direction').value,
        delivery: UI.$('q-delivery').value,
        delivery_url: UI.$('q-url').value.trim() || null,
        secret: UI.$('q-secret').value.trim() || null,
        description: UI.$('q-desc').value.trim() || null,
        max_attempts: parseInt(UI.$('q-attempts').value) || 3,
        retry_delay: parseInt(UI.$('q-delay').value) || 30,
        visibility_timeout: parseInt(UI.$('q-timeout').value) || 60,
      };
      try {
        if (isEdit) {
          await Api.patch('/queues/' + name, payload);
          UI.toast('Queue updated');
        } else {
          await Api.post('/queues', payload);
          UI.toast('Queue created');
        }
        UI.closeModal(); this.queues();
      } catch (err) { UI.toast(err.message, 'danger'); }
    });
  },

  /* ---------- Queue Detail ---------- */
  async queueDetail(name) {
    const area = UI.$('content-area');
    const isAdmin = App.isAdmin();
    if (!name) { location.hash = '#queues'; return; }

    try {
      const res = await Api.get('/queues/' + encodeURIComponent(name));
      const q = res.data;
      const s = q.stats || {};

      // Status filter
      const status = new URLSearchParams(location.hash.split('?')[1] || '').get('status') || '';

      area.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
          <div>
            <a href="#queues" class="text-decoration-none text-body-secondary"><i class="bi bi-arrow-left me-1"></i>Queues</a>
            <h5 class="mb-0 mt-1"><i class="bi bi-stack me-2"></i>${UI.esc(q.name)}
              <span class="badge ${q.direction==='inbound'?'bg-info':'bg-warning'} text-dark ms-2">${UI.esc(q.direction)}</span>
              <span class="badge bg-secondary ms-1">${UI.esc(q.delivery)}</span>
            </h5>
            ${q.description ? `<span class="text-body-secondary small">${UI.esc(q.description)}</span>` : ''}
          </div>
          <div class="d-flex gap-2">
            ${isAdmin ? `<button class="btn btn-sm btn-outline-secondary" id="btn-edit-queue"><i class="bi bi-pencil me-1"></i>Edit</button>` : ''}
            <button class="btn btn-sm btn-primary" id="btn-publish-msg"><i class="bi bi-plus-lg me-1"></i>Publish Message</button>
          </div>
        </div>

        <!-- Stats cards -->
        <div class="row g-3 mb-4">
          <div class="col"><div class="card text-center"><div class="card-body py-2">
            <div class="fs-4 fw-bold">${s.pending_count || 0}</div><small class="text-body-secondary">Pending</small>
          </div></div></div>
          <div class="col"><div class="card text-center"><div class="card-body py-2">
            <div class="fs-4 fw-bold">${s.processing_count || 0}</div><small class="text-body-secondary">Processing</small>
          </div></div></div>
          <div class="col"><div class="card text-center border-success"><div class="card-body py-2">
            <div class="fs-4 fw-bold text-success">${s.completed_count || 0}</div><small class="text-body-secondary">Completed</small>
          </div></div></div>
          <div class="col"><div class="card text-center border-warning"><div class="card-body py-2">
            <div class="fs-4 fw-bold text-warning">${s.failed_count || 0}</div><small class="text-body-secondary">Failed</small>
          </div></div></div>
          <div class="col"><div class="card text-center border-danger"><div class="card-body py-2">
            <div class="fs-4 fw-bold text-danger">${s.dead_count || 0}</div><small class="text-body-secondary">Dead</small>
          </div></div></div>
        </div>

        <!-- Config info -->
        ${q.delivery === 'push' && q.delivery_url ? `<div class="alert alert-secondary small mb-3"><i class="bi bi-link-45deg me-1"></i><strong>Delivery URL:</strong> <code>${UI.esc(q.delivery_url)}</code></div>` : ''}

        <!-- Filter tabs -->
        <ul class="nav nav-tabs mb-3" id="msg-status-tabs">
          <li class="nav-item"><a class="nav-link ${!status?'active':''} pointer" data-status="">All</a></li>
          <li class="nav-item"><a class="nav-link ${status==='pending'?'active':''} pointer" data-status="pending">Pending</a></li>
          <li class="nav-item"><a class="nav-link ${status==='processing'?'active':''} pointer" data-status="processing">Processing</a></li>
          <li class="nav-item"><a class="nav-link ${status==='completed'?'active':''} pointer" data-status="completed">Completed</a></li>
          <li class="nav-item"><a class="nav-link ${status==='failed'?'active':''} pointer" data-status="failed">Failed</a></li>
          <li class="nav-item"><a class="nav-link ${status==='dead'?'active':''} pointer" data-status="dead">Dead</a></li>
        </ul>

        <div id="queue-messages-area"></div>`;

      // Load messages
      await this.loadQueueMessages(name, status, 1);

      // Status tab clicks
      document.querySelectorAll('#msg-status-tabs .nav-link').forEach(tab => {
        tab.addEventListener('click', () => {
          document.querySelectorAll('#msg-status-tabs .nav-link').forEach(t => t.classList.remove('active'));
          tab.classList.add('active');
          this.loadQueueMessages(name, tab.dataset.status, 1);
        });
      });

      // Edit button
      UI.$('btn-edit-queue')?.addEventListener('click', () => this.showQueueModal(q));

      // Publish button
      UI.$('btn-publish-msg')?.addEventListener('click', () => {
        const body = `<div class="mb-3"><label class="form-label fw-semibold">Payload (JSON)</label>
          <textarea id="msg-payload" class="form-control font-monospace" rows="6" placeholder='{"key": "value"}'></textarea></div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Priority <span class="text-body-secondary">(0 = normal)</span></label>
              <input type="number" id="msg-priority" class="form-control" value="0"></div>
            <div class="col-md-6"><label class="form-label">Delay <span class="text-body-secondary">(seconds)</span></label>
              <input type="number" id="msg-delay" class="form-control" value="0" min="0"></div>
          </div>`;
        const footer = '<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="msg-send">Publish</button>';
        UI.modal('Publish Message to ' + name, body, footer);
        document.getElementById('msg-send').addEventListener('click', async () => {
          const payloadText = UI.$('msg-payload').value.trim();
          if (!payloadText) { UI.toast('Payload required', 'danger'); return; }
          let payload;
          try { payload = JSON.parse(payloadText); } catch(e) { UI.toast('Invalid JSON: ' + e.message, 'danger'); return; }
          const data = { payload, priority: parseInt(UI.$('msg-priority').value) || 0, delay: parseInt(UI.$('msg-delay').value) || 0 };
          try {
            await Api.post('/queues/' + encodeURIComponent(name) + '/messages', data);
            UI.toast('Message published');
            UI.closeModal();
            this.queueDetail(name);
          } catch (err) { UI.toast(err.message, 'danger'); }
        });
      });

    } catch (err) { area.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  async loadQueueMessages(queueName, status, page) {
    const container = document.getElementById('queue-messages-area');
    if (!container) return;
    const isAdmin = App.isAdmin();

    let url = `/queues/${encodeURIComponent(queueName)}/messages?page=${page}&per_page=20`;
    if (status) url += `&status=${status}`;

    try {
      const res = await Api.get(url);
      const msgs = res.data || [];
      const meta = res.meta || {};

      container.innerHTML = `
        <div class="card shadow-sm">
          <div class="table-responsive"><table class="table table-hover table-sm mb-0 align-middle">
            <thead><tr><th>ID</th><th>Status</th><th>Payload</th><th>Attempts</th><th>Available At</th><th>Error</th><th width="120"></th></tr></thead>
            <tbody>${msgs.length ? msgs.map(m => {
              const statusBadge = {pending:'bg-primary',processing:'bg-info text-dark',completed:'bg-success',failed:'bg-warning text-dark',dead:'bg-danger'}[m.status] || 'bg-secondary';
              const payload = typeof m.payload === 'string' ? m.payload : JSON.stringify(m.payload);
              const truncPayload = payload.length > 80 ? payload.substring(0, 80) + '...' : payload;
              return `<tr>
                <td class="fw-semibold">${m.id}</td>
                <td><span class="badge ${statusBadge}">${m.status}</span></td>
                <td class="small font-monospace text-truncate" style="max-width:300px" title="${UI.esc(payload)}">${UI.esc(truncPayload)}</td>
                <td>${m.attempts}/${m.max_attempts}</td>
                <td class="small text-body-secondary">${UI.esc(m.available_at || '')}</td>
                <td class="small text-danger" title="${UI.esc(m.error || '')}">${m.error ? UI.esc(m.error.substring(0, 50)) + (m.error.length > 50 ? '...' : '') : '—'}</td>
                <td class="text-end text-nowrap">
                  ${m.status === 'pending' ? `<button class="btn btn-outline-danger btn-sm" data-cancel-msg="${m.id}" title="Cancel"><i class="bi bi-x-lg"></i></button>` : ''}
                  ${(m.status === 'dead' || m.status === 'failed') && isAdmin ? `<button class="btn btn-outline-warning btn-sm" data-retry-msg="${m.id}" title="Retry"><i class="bi bi-arrow-clockwise"></i></button>` : ''}
                </td>
              </tr>`;
            }).join('') : '<tr><td colspan="7" class="text-center text-body-secondary py-3">No messages</td></tr>'}</tbody>
          </table></div>
          ${meta.total_pages > 1 ? `<div class="card-footer d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary">Page ${meta.page} of ${meta.total_pages} (${meta.total} messages)</span>
            <nav><ul class="pagination pagination-sm mb-0">
              <li class="page-item ${meta.page <= 1 ? 'disabled' : ''}"><a class="page-link pointer" data-msg-page="${meta.page - 1}"><i class="bi bi-chevron-left"></i></a></li>
              <li class="page-item ${meta.page >= meta.total_pages ? 'disabled' : ''}"><a class="page-link pointer" data-msg-page="${meta.page + 1}"><i class="bi bi-chevron-right"></i></a></li>
            </ul></nav>
          </div>` : ''}
        </div>`;

      // Page nav
      container.querySelectorAll('[data-msg-page]').forEach(a => {
        a.addEventListener('click', () => {
          if (!a.closest('.disabled')) this.loadQueueMessages(queueName, status, parseInt(a.dataset.msgPage));
        });
      });

      // Cancel buttons
      container.querySelectorAll('[data-cancel-msg]').forEach(b => b.addEventListener('click', async () => {
        if (await UI.confirm('Cancel this message?')) {
          try {
            await Api.del(`/queues/${encodeURIComponent(queueName)}/messages/${b.dataset.cancelMsg}`);
            UI.toast('Message cancelled');
            this.loadQueueMessages(queueName, status, page);
          } catch (err) { UI.toast(err.message, 'danger'); }
        }
      }));

      // Retry buttons
      container.querySelectorAll('[data-retry-msg]').forEach(b => b.addEventListener('click', async () => {
        try {
          await Api.post(`/queues/${encodeURIComponent(queueName)}/messages/${b.dataset.retryMsg}/retry`);
          UI.toast('Message queued for retry');
          this.loadQueueMessages(queueName, status, page);
        } catch (err) { UI.toast(err.message, 'danger'); }
      }));

    } catch (err) { container.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  /* ---------- API Explorer ---------- */
  async apiExplorer() {
    const area = UI.$('content-area');
    try {
      const res = await Api.get('/schema/tables');
      const allTables = (res.data || []).filter(t => !this.SYSTEM_TABLES.includes(t.TABLE_NAME));
      const tableNames = allTables.map(t => t.TABLE_NAME);
      const firstTable = tableNames[0] || 'your_table';

      // Endpoint catalog — grouped by category
      const endpoints = [
        { cat: 'Data - List & Search', items: [
          { method: 'GET', path: '/{table}', desc: 'List all records with pagination', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
            { name: 'page', in: 'query', desc: 'Page number (default: 1)' },
            { name: 'per_page', in: 'query', desc: 'Records per page (default: 20, max: 100)' },
            { name: 'sort', in: 'query', desc: 'Sort field (prefix with - for DESC)' },
          ], example: { path: `/${firstTable}?page=1&per_page=10` } },
          { method: 'GET', path: '/{table}/{id}', desc: 'Get a single record by ID', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
            { name: 'id', in: 'path', required: true, desc: 'Record ID' },
          ], example: { path: `/${firstTable}/1` } },
          { method: 'GET', path: '/{table}/filter', desc: 'Filter records with conditions', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
            { name: 'column[op]', in: 'query', desc: 'Filter: eq, neq, gt, gte, lt, lte, like, in, null, notnull' },
          ], example: { path: `/${firstTable}/filter?id[gte]=1&per_page=5` } },
        ]},
        { cat: 'Data - Create, Update, Delete', items: [
          { method: 'POST', path: '/{table}', desc: 'Create a new record', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
            { name: 'body', in: 'body', required: true, desc: 'JSON object with column values' },
          ], example: { path: `/${firstTable}`, body: '{\n  "column1": "value1",\n  "column2": "value2"\n}' } },
          { method: 'PATCH', path: '/{table}/{id}', desc: 'Update an existing record', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
            { name: 'id', in: 'path', required: true, desc: 'Record ID' },
            { name: 'body', in: 'body', required: true, desc: 'JSON object with fields to update' },
          ], example: { path: `/${firstTable}/1`, body: '{\n  "column1": "new_value"\n}' } },
          { method: 'DELETE', path: '/{table}/{id}', desc: 'Delete a record', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
            { name: 'id', in: 'path', required: true, desc: 'Record ID' },
          ], example: { path: `/${firstTable}/1` } },
        ]},
        { cat: 'Schema - Read', items: [
          { method: 'GET', path: '/schema/tables', desc: 'List all tables in the database', params: [], example: { path: '/schema/tables' } },
          { method: 'GET', path: '/schema/tables/{table}', desc: 'Get column structure of a table', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
          ], example: { path: `/schema/tables/${firstTable}` } },
        ]},
        { cat: 'Schema - Write (Admin only)', items: [
          { method: 'POST', path: '/schema/tables', desc: 'Create a new table', params: [
            { name: 'body', in: 'body', required: true, desc: 'Table definition with columns' },
          ], example: { path: '/schema/tables', body: '{\n  "table": "products",\n  "columns": {\n    "id": {"type":"INT","auto_increment":true,"primary":true},\n    "name": {"type":"VARCHAR","length":255},\n    "price": {"type":"DECIMAL","precision":10,"scale":2}\n  }\n}' } },
          { method: 'POST', path: '/schema/tables/{table}/columns', desc: 'Add a column to a table', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
            { name: 'body', in: 'body', required: true, desc: 'Column definition' },
          ], example: { path: `/schema/tables/${firstTable}/columns`, body: '{\n  "column": "description",\n  "definition": {"type":"TEXT","nullable":true}\n}' } },
          { method: 'PATCH', path: '/schema/tables/{table}/columns/{col}', desc: 'Modify a column', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
            { name: 'col', in: 'path', required: true, desc: 'Column name' },
            { name: 'body', in: 'body', required: true, desc: 'New column definition' },
          ], example: { path: `/schema/tables/${firstTable}/columns/name`, body: '{\n  "definition": {"type":"VARCHAR","length":500,"nullable":true}\n}' } },
          { method: 'DELETE', path: '/schema/tables/{table}', desc: 'Drop a table', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
          ], example: { path: `/schema/tables/${firstTable}` } },
          { method: 'DELETE', path: '/schema/tables/{table}/columns/{col}', desc: 'Drop a column from a table', params: [
            { name: 'table', in: 'path', required: true, desc: 'Table name' },
            { name: 'col', in: 'path', required: true, desc: 'Column name' },
          ], example: { path: `/schema/tables/${firstTable}/columns/name` } },
        ]},
        { cat: 'Queues - Management', items: [
          { method: 'GET', path: '/queues', desc: 'List all queues with message counts', params: [], example: { path: '/queues' } },
          { method: 'POST', path: '/queues', desc: 'Create a new queue (Admin)', params: [
            { name: 'body', in: 'body', required: true, desc: 'Queue definition' },
          ], example: { path: '/queues', body: '{\n  "name": "order-notifications",\n  "direction": "outbound",\n  "delivery": "pull",\n  "description": "Order events for CRM"\n}' } },
          { method: 'GET', path: '/queues/{queue}', desc: 'Get queue details and stats', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
          ], example: { path: '/queues/order-notifications' } },
          { method: 'PATCH', path: '/queues/{queue}', desc: 'Update queue settings (Admin)', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
            { name: 'body', in: 'body', required: true, desc: 'Fields to update' },
          ], example: { path: '/queues/order-notifications', body: '{\n  "max_attempts": 5,\n  "retry_delay": 60\n}' } },
          { method: 'DELETE', path: '/queues/{queue}', desc: 'Delete a queue and all messages (Admin)', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
          ], example: { path: '/queues/order-notifications' } },
        ]},
        { cat: 'Queues - Messages', items: [
          { method: 'POST', path: '/queues/{queue}/messages', desc: 'Publish a message to a queue', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
            { name: 'body', in: 'body', required: true, desc: 'Message payload with optional priority and delay' },
          ], example: { path: '/queues/order-notifications/messages', body: '{\n  "payload": {"event": "order.created", "order_id": 42},\n  "priority": 0,\n  "delay": 0\n}' } },
          { method: 'GET', path: '/queues/{queue}/messages', desc: 'List messages with optional status filter', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
            { name: 'status', in: 'query', desc: 'Filter by status: pending, processing, completed, failed, dead' },
            { name: 'page', in: 'query', desc: 'Page number' },
            { name: 'per_page', in: 'query', desc: 'Messages per page' },
          ], example: { path: '/queues/order-notifications/messages?status=pending' } },
          { method: 'GET', path: '/queues/{queue}/consume', desc: 'Consume the next message (pull queues only)', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
          ], example: { path: '/queues/order-notifications/consume' } },
          { method: 'POST', path: '/queues/{queue}/messages/{id}/ack', desc: 'Acknowledge (complete) a processing message', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
            { name: 'id', in: 'path', required: true, desc: 'Message ID' },
          ], example: { path: '/queues/order-notifications/messages/1/ack' } },
          { method: 'POST', path: '/queues/{queue}/messages/{id}/nack', desc: 'Reject a message (retry or dead letter)', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
            { name: 'id', in: 'path', required: true, desc: 'Message ID' },
            { name: 'body', in: 'body', desc: 'Optional error message' },
          ], example: { path: '/queues/order-notifications/messages/1/nack', body: '{\n  "error": "Processing failed: timeout"\n}' } },
          { method: 'POST', path: '/queues/{queue}/messages/{id}/retry', desc: 'Retry a dead/failed message (Admin)', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
            { name: 'id', in: 'path', required: true, desc: 'Message ID' },
          ], example: { path: '/queues/order-notifications/messages/1/retry' } },
          { method: 'DELETE', path: '/queues/{queue}/messages/{id}', desc: 'Cancel a pending message', params: [
            { name: 'queue', in: 'path', required: true, desc: 'Queue name' },
            { name: 'id', in: 'path', required: true, desc: 'Message ID' },
          ], example: { path: '/queues/order-notifications/messages/1' } },
        ]},
        { cat: 'Authentication', items: [
          { method: 'POST', path: '/auth/login', desc: 'Login and get JWT tokens', params: [
            { name: 'body', in: 'body', required: true, desc: 'Credentials' },
          ], example: { path: '/auth/login', body: '{\n  "email": "user@example.com",\n  "password": "your_password"\n}' } },
          { method: 'POST', path: '/auth/refresh', desc: 'Refresh an expired access token', params: [
            { name: 'body', in: 'body', required: true, desc: 'Refresh token' },
          ], example: { path: '/auth/refresh', body: '{\n  "refresh_token": "your_refresh_token"\n}' } },
          { method: 'GET', path: '/auth/profile', desc: 'Get current user profile', params: [], example: { path: '/auth/profile' } },
          { method: 'PATCH', path: '/auth/profile', desc: 'Update own profile', params: [
            { name: 'body', in: 'body', required: true, desc: 'Profile fields to update' },
          ], example: { path: '/auth/profile', body: '{\n  "username": "new_name"\n}' } },
        ]},
      ];

      // Table selector options
      const tableOpts = tableNames.map(t => `<option value="${UI.esc(t)}">${UI.esc(t)}</option>`).join('');

      area.innerHTML = `
        <!-- Auth Card -->
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <div class="row align-items-end g-3">
              <div class="col-md-5">
                <label class="form-label fw-semibold"><i class="bi bi-key me-1"></i>API Key</label>
                <input type="text" id="api-key-input" class="form-control font-monospace" placeholder="Paste your API key here...">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold"><i class="bi bi-table me-1"></i>Table</label>
                <select id="api-table-select" class="form-select">
                  ${tableOpts || '<option value="">No tables</option>'}
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="bi bi-link-45deg me-1"></i>Base URL</label>
                <input type="text" class="form-control font-monospace text-body-secondary" value="${UI.esc(location.origin + API_BASE)}" readonly>
              </div>
            </div>
            <div class="mt-2 small text-body-secondary">
              <i class="bi bi-info-circle me-1"></i>
              Paste an API key to test endpoints externally, or use your current JWT session. Changing the table updates example paths.
            </div>
          </div>
        </div>

        <!-- Endpoint Catalog -->
        <div id="api-catalog">
          ${endpoints.map(group => `
            <h6 class="fw-bold text-body-secondary text-uppercase small mt-4 mb-2"><i class="bi bi-folder2-open me-1"></i>${UI.esc(group.cat)}</h6>
            ${group.items.map((ep, i) => {
              const m = ep.method.toLowerCase();
              const epId = 'ep-' + m + '-' + group.cat.replace(/\W/g,'') + '-' + i;
              const hasBody = ep.params.some(p => p.in === 'body');
              return `
              <div class="api-endpoint border-${m}" id="${epId}">
                <div class="api-endpoint-header" data-ep="${epId}">
                  <span class="api-method ${m}">${ep.method}</span>
                  <span class="api-path">${UI.esc(ep.path)}</span>
                  <span class="api-desc d-none d-md-inline">${UI.esc(ep.desc)}</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </div>
                <div class="api-endpoint-body">
                  <p class="text-body-secondary mb-2">${UI.esc(ep.desc)}</p>
                  ${ep.params.length ? `
                  <table class="table table-sm api-param-table mb-3">
                    <thead><tr><th>Parameter</th><th>In</th><th>Required</th><th>Description</th></tr></thead>
                    <tbody>${ep.params.map(p => `<tr>
                      <td class="font-monospace fw-semibold">${UI.esc(p.name)}</td>
                      <td><span class="badge bg-secondary">${UI.esc(p.in)}</span></td>
                      <td>${p.required ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-dash text-body-secondary"></i>'}</td>
                      <td>${UI.esc(p.desc)}</td>
                    </tr>`).join('')}</tbody>
                  </table>` : ''}
                  <div class="row g-3 mb-3">
                    <div class="col-md-8">
                      <label class="form-label small fw-semibold">Request Path</label>
                      <div class="input-group input-group-sm">
                        <span class="input-group-text font-monospace api-method ${m}" style="color:#fff;font-size:.7rem">${ep.method}</span>
                        <input type="text" class="form-control font-monospace ep-path" value="${UI.esc(ep.example.path)}" data-template="${UI.esc(ep.example.path)}">
                      </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                      <button class="btn btn-sm btn-primary w-100 ep-send" data-method="${ep.method}" data-ep="${epId}"><i class="bi bi-send me-1"></i>Send Request</button>
                    </div>
                  </div>
                  ${hasBody ? `<div class="mb-3">
                    <label class="form-label small fw-semibold">Request Body <span class="text-body-secondary">(JSON)</span></label>
                    <textarea class="form-control font-monospace ep-body" rows="5" data-template="${UI.esc(ep.example.body || '')}">${UI.esc(ep.example.body || '')}</textarea>
                  </div>` : ''}
                  <div class="mb-1"><label class="form-label small fw-semibold">Response</label></div>
                  <div class="api-response-area ep-response" data-ep="${epId}">Click "Send Request" to see the response...</div>
                </div>
              </div>`;
            }).join('')}
          `).join('')}
        </div>`;

      // Accordion toggle
      area.querySelectorAll('.api-endpoint-header').forEach(h => {
        h.addEventListener('click', () => {
          const ep = document.getElementById(h.dataset.ep);
          ep.classList.toggle('open');
          const icon = h.querySelector('.bi-chevron-down, .bi-chevron-up');
          if (icon) icon.className = ep.classList.contains('open') ? 'bi bi-chevron-up ms-auto' : 'bi bi-chevron-down ms-auto';
        });
      });

      // Update paths when table changes
      UI.$('api-table-select')?.addEventListener('change', () => {
        const tbl = UI.$('api-table-select').value;
        area.querySelectorAll('.ep-path').forEach(input => {
          let tmpl = input.dataset.template;
          // Replace the first table name occurrence with selected table
          tmpl = tmpl.replace(new RegExp(`/(${tableNames.map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`), '/' + tbl);
          // Also handle {table} template paths
          tmpl = tmpl.replace(/\{table\}/g, tbl);
          input.value = tmpl;
          input.dataset.template = tmpl;
        });
        area.querySelectorAll('.ep-body').forEach(ta => {
          let tmpl = ta.dataset.template;
          tmpl = tmpl.replace(new RegExp(`(${tableNames.map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`), tbl);
          ta.value = tmpl;
          ta.dataset.template = tmpl;
        });
      });

      // Send request buttons
      area.querySelectorAll('.ep-send').forEach(btn => {
        btn.addEventListener('click', async () => {
          const epEl = document.getElementById(btn.dataset.ep);
          const method = btn.dataset.method;
          const path = epEl.querySelector('.ep-path').value.trim();
          const bodyEl = epEl.querySelector('.ep-body');
          const responseEl = epEl.querySelector('.ep-response');
          const apiKey = UI.$('api-key-input')?.value.trim();

          if (!path) { UI.toast('Path is required', 'danger'); return; }

          // Build request
          const url = API_BASE + (path.startsWith('/') ? path : '/' + path);
          const opts = { method, headers: { 'Content-Type': 'application/json' } };

          // Auth: prefer API key if provided, fallback to JWT
          if (apiKey) {
            opts.headers['X-API-Key'] = apiKey;
          } else if (Api.token) {
            opts.headers['Authorization'] = 'Bearer ' + Api.token;
          }

          // Body for POST/PATCH
          if (bodyEl && ['POST', 'PATCH', 'PUT'].includes(method)) {
            const bodyText = bodyEl.value.trim();
            if (bodyText) {
              try { JSON.parse(bodyText); opts.body = bodyText; }
              catch(e) { responseEl.textContent = 'Error: Invalid JSON body — ' + e.message; return; }
            }
          }

          responseEl.textContent = 'Sending...';
          btn.disabled = true;

          try {
            const start = performance.now();
            const res = await fetch(url, opts);
            const elapsed = Math.round(performance.now() - start);
            const status = res.status;
            const statusText = res.statusText;
            let responseText;
            try {
              const json = await res.json();
              responseText = JSON.stringify(json, null, 2);
            } catch(e) {
              responseText = await res.text().catch(() => '(empty response)');
            }

            const statusColor = status < 300 ? '#49cc90' : status < 400 ? '#fca130' : '#f93e3e';
            responseEl.innerHTML = `<div class="d-flex justify-content-between mb-2"><span style="color:${statusColor};font-weight:700">HTTP ${status} ${UI.esc(statusText)}</span><span class="text-body-secondary">${elapsed}ms</span></div><div>${UI.esc(responseText)}</div>`;
          } catch (err) {
            responseEl.textContent = 'Network Error: ' + err.message;
          } finally {
            btn.disabled = false;
          }
        });
      });

    } catch (err) { area.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  },

  /* ---------- Profile ---------- */
  async profile() {
    const area = UI.$('content-area');
    try {
      const res = await Api.get('/auth/profile');
      const u = res.data;
      area.innerHTML = `
        <div class="row justify-content-center">
          <div class="col-lg-8 col-xl-6">
            <div class="card shadow-sm mb-4">
              <div class="card-header fw-semibold"><i class="bi bi-person-circle me-2"></i>Your Profile</div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Username</label>
                  <input type="text" id="pf-name" class="form-control" value="${UI.esc(u.username)}">
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Email</label>
                  <input type="email" id="pf-email" class="form-control" value="${UI.esc(u.email)}">
                </div>
                <div class="row mb-3">
                  <div class="col-6">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="${UI.esc(u.role)}" readonly disabled>
                  </div>
                  <div class="col-6">
                    <label class="form-label">Member Since</label>
                    <input type="text" class="form-control" value="${UI.esc(u.created_at)}" readonly disabled>
                  </div>
                </div>
                <button class="btn btn-primary" id="pf-save"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                <div id="pf-msg" class="mt-2"></div>
              </div>
            </div>
            <div class="card shadow-sm">
              <div class="card-header fw-semibold"><i class="bi bi-lock me-2"></i>Change Password</div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Current Password</label>
                  <input type="password" id="pf-curpass" class="form-control" autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">New Password</label>
                  <input type="password" id="pf-newpass" class="form-control" autocomplete="new-password" required>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Confirm New Password</label>
                  <input type="password" id="pf-newpass2" class="form-control" autocomplete="new-password" required>
                </div>
                <button class="btn btn-warning" id="pf-chgpass"><i class="bi bi-lock me-1"></i>Change Password</button>
                <div id="pf-passmsg" class="mt-2"></div>
              </div>
            </div>
          </div>
        </div>`;

      // Save profile info
      UI.$('pf-save').addEventListener('click', async () => {
        const data = {};
        const newName = UI.$('pf-name').value.trim();
        const newEmail = UI.$('pf-email').value.trim();
        if (newName && newName !== u.username) data.username = newName;
        if (newEmail && newEmail !== u.email) data.email = newEmail;
        if (!Object.keys(data).length) { UI.toast('No changes to save', 'info'); return; }
        try {
          const r = await Api.patch('/auth/profile', data);
          UI.toast('Profile updated');
          // Update stored user info
          if (r.data) {
            App.user = { ...App.user, ...r.data };
            localStorage.setItem('crud_user', JSON.stringify(App.user));
            UI.$('sidebar-user').textContent = App.user.username || App.user.email;
          }
          UI.$('pf-msg').innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Saved</span>';
          setTimeout(() => { if (UI.$('pf-msg')) UI.$('pf-msg').innerHTML = ''; }, 3000);
        } catch (err) { UI.toast(err.message, 'danger'); }
      });

      // Change password
      UI.$('pf-chgpass').addEventListener('click', async () => {
        const curPass = UI.$('pf-curpass').value;
        const newPass = UI.$('pf-newpass').value;
        const newPass2 = UI.$('pf-newpass2').value;
        if (!curPass) { UI.toast('Current password is required', 'danger'); return; }
        if (!newPass) { UI.toast('New password is required', 'danger'); return; }
        if (newPass !== newPass2) { UI.toast('New passwords do not match', 'danger'); return; }
        try {
          await Api.patch('/auth/profile', { current_password: curPass, new_password: newPass });
          UI.toast('Password changed successfully');
          UI.$('pf-curpass').value = '';
          UI.$('pf-newpass').value = '';
          UI.$('pf-newpass2').value = '';
          UI.$('pf-passmsg').innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Password updated</span>';
          setTimeout(() => { if (UI.$('pf-passmsg')) UI.$('pf-passmsg').innerHTML = ''; }, 3000);
        } catch (err) { UI.toast(err.message, 'danger'); }
      });
    } catch (err) { area.innerHTML = `<div class="alert alert-danger">${UI.esc(err.message)}</div>`; }
  }
};

/* Boot */
document.addEventListener('DOMContentLoaded', () => App.init());
JS;
    }
}
