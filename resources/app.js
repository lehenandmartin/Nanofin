import './app.css'
import Alpine from 'alpinejs'
import collapse from '@alpinejs/collapse'

// ── Plugins ───────────────────────────────────────────────────────
Alpine.plugin(collapse)

// ── Dynamic values injected via <meta> tags ───────────────────────
// Avoids CSP-blocked inline <script> blocks for per-request values.
const BASE_PATH = document.querySelector('meta[name="app-base-path"]')?.content ?? '';
window.BASE_PATH = BASE_PATH; // exposed for compatibility

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ── Password generator (shared by create-user form and reset modal) ──
function generatePassword(length = 12) {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const array = new Uint8Array(length);
    crypto.getRandomValues(array);
    return Array.from(array, b => chars[b % chars.length]).join('');
}

// ── Poster fallback (replaces blocked inline onerror="…") ─────────
// Handles broken poster images on any page without an inline handler.
document.addEventListener('error', (e) => {
    if (e.target.tagName === 'IMG' && e.target.dataset.fallback === 'poster') {
        e.target.removeAttribute('data-fallback'); // prevent retry loop
        e.target.src = BASE_PATH + '/poster/__placeholder';
    }
}, true /* capture: fires before the element handles it */);

// ─────────────────────────────────────────────────────────────────
// Auth: two-phase login (identifier → password or magic_sent)
// Phase is driven server-side via <meta name="login-phase"> and
// <meta name="login-identifier"> injected by the controller.
// ─────────────────────────────────────────────────────────────────
Alpine.data('loginPage', () => ({
    phase:      'identifier',
    identifier: '',
    loading:    false,
    error:      '',

    init() {
        this.phase      = document.querySelector('meta[name="login-phase"]')?.content      || 'identifier';
        this.identifier = document.querySelector('meta[name="login-identifier"]')?.content || '';
        // Auto-focus the right field on initial load
        if (this.phase === 'password') {
            this.$nextTick(() => requestAnimationFrame(() => this.$refs.passwordInput?.focus()));
        }
    },

    async submitIdentifier() {
        if (this.identifier.trim() === '') return;
        this.loading = true;
        this.error   = '';
        try {
            const res  = await fetch(BASE_PATH + '/login/identify', {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({ identifier: this.identifier, csrf_token: getCsrf() }),
            });
            const data = await res.json();
            if (data.error) {
                this.error = data.error;
            } else {
                this.phase = data.phase;
                if (data.phase === 'password') {
                    this.$nextTick(() => requestAnimationFrame(() => this.$refs.passwordInput?.focus()));
                }
            }
        } catch {
            this.error = '…';
        } finally {
            this.loading = false;
        }
    },

    usePasswordInstead() {
        this.phase = 'password';
        this.$nextTick(() => requestAnimationFrame(() => this.$refs.passwordInput?.focus()));
    },

    changeIdentifier() {
        this.phase = 'identifier';
        this.error = '';
        this.$nextTick(() => requestAnimationFrame(() => this.$refs.identifierInput?.focus()));
    },
}));

// ─────────────────────────────────────────────────────────────────
// Admin: edit-user modal
// Registered via Alpine.data() so it is served from the bundle
// (CSP: script-src 'self') rather than a blocked inline <script>.
// ─────────────────────────────────────────────────────────────────
Alpine.data('editUserModal', () => ({
    visible:          false,
    userId:           null,
    originalUsername: '',
    username:         '',
    email:            '',
    role:             'user',
    access:           'both',
    ageLimitValue:    '',
    isSelf:           false,
    saving:           false,
    saved:            false,
    saveError:        '',
    hasChanges:       false,

    // Password reset
    pwdLoading:      false,
    pwdPassword:     '',
    pwdInput:        '',
    pwdForceChange:  true,
    pwdHasEmail:     false,
    pwdSmtpReady:    false,
    pwdEmailSent:    false,
    pwdEmailLoading: false,
    pwdError:        '',
    pwdEmailError:   '',

    openModal(user) {
        this.userId           = user.id;
        this.originalUsername = user.username;
        this.username         = user.username;
        this.email            = user.email ?? '';
        this.role             = user.role;
        this.access           = user.access;
        this.ageLimitValue    = user.ageLimit !== null && user.ageLimit !== undefined ? String(user.ageLimit) : '';
        this.isSelf           = user.isSelf;
        this.saving           = false;
        this.saved            = false;
        this.saveError        = '';
        this.hasChanges       = false;
        this.pwdLoading      = false;
        this.pwdPassword     = '';
        this.pwdInput        = generatePassword();
        this.pwdForceChange  = true;
        this.pwdHasEmail     = false;
        this.pwdSmtpReady     = false;
        this.pwdEmailSent     = false;
        this.pwdEmailLoading  = false;
        this.pwdError         = '';
        this.pwdEmailError    = '';
        this.visible          = true;
    },

    closeModal() {
        this.visible = false;
        if (this.hasChanges) window.location.reload();
    },

    async saveSettings() {
        this.saving    = true;
        this.saved     = false;
        this.saveError = '';
        try {
            const res  = await fetch(`${BASE_PATH}/admin/users/${this.userId}`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({
                    csrf_token:     getCsrf(),
                    username:       this.username,
                    email:          this.email,
                    role:           this.role,
                    content_access: this.access,
                    age_limit:      this.ageLimitValue,
                }),
            });
            const data = await res.json();
            if (data.error) {
                this.saveError = data.error;
            } else {
                this.originalUsername = data.username;
                this.saved            = true;
                this.hasChanges       = true;
                setTimeout(() => { this.saved = false; }, 2500);
            }
        } catch (e) {
            this.saveError = e.message;
        } finally {
            this.saving = false;
        }
    },

    regeneratePwdInput() { this.pwdInput = generatePassword(); },

    async doResetPassword() {
        this.pwdLoading = true;
        this.pwdError   = '';
        try {
            const res  = await fetch(`${BASE_PATH}/admin/users/${this.userId}/password`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({
                    csrf_token:            getCsrf(),
                    password:              this.pwdInput,
                    force_password_change: this.pwdForceChange ? '1' : '0',
                }),
            });
            const data = await res.json();
            if (data.error) {
                this.pwdError = data.error;
            } else {
                this.pwdPassword  = data.password;
                this.pwdHasEmail  = data.hasEmail;
                this.pwdSmtpReady = data.smtpReady;
                this.hasChanges   = true;
            }
        } catch (e) {
            this.pwdError = e.message;
        } finally {
            this.pwdLoading = false;
        }
    },

    async sendPasswordEmail() {
        this.pwdEmailLoading = true;
        this.pwdEmailError   = '';
        try {
            const res  = await fetch(`${BASE_PATH}/admin/users/${this.userId}/password/email`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({
                    csrf_token: getCsrf(),
                    password:   this.pwdPassword,
                }),
            });
            const data = await res.json();
            if (data.error) { this.pwdEmailError = data.error; }
            else            { this.pwdEmailSent  = true; }
        } catch (e) {
            this.pwdEmailError = e.message;
        } finally {
            this.pwdEmailLoading = false;
        }
    },
}));

// ─────────────────────────────────────────────────────────────────
// Admin: create-user form
// ─────────────────────────────────────────────────────────────────
Alpine.data('createUserForm', () => ({
    newEmail:      '',
    passwordValue: '',
    init()       { this.passwordValue = generatePassword(); },
    regenerate() { this.passwordValue = generatePassword(); },
}));

// ─────────────────────────────────────────────────────────────────
// Profile: active sessions list
// ─────────────────────────────────────────────────────────────────
Alpine.data('sessionsList', () => ({
    sessions: [],
    loading:  true,
    error:    '',

    get hasOthers() {
        return this.sessions.some(s => !s.current);
    },

    async init() {
        await this.load();
    },

    async load() {
        this.loading = true;
        this.error   = '';
        try {
            const res  = await fetch(BASE_PATH + '/account/sessions');
            const data = await res.json();
            this.sessions = data.sessions ?? [];
        } catch (e) {
            this.error = e.message;
        } finally {
            this.loading = false;
        }
    },

    async revoke(id) {
        this.error = '';
        try {
            const res  = await fetch(`${BASE_PATH}/account/sessions/${id}/revoke`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({ csrf_token: getCsrf() }),
            });
            const data = await res.json();
            if (data.error) { this.error = data.error; }
            else             { await this.load(); }
        } catch (e) {
            this.error = e.message;
        }
    },

    async revokeOthers() {
        this.error = '';
        try {
            const res  = await fetch(BASE_PATH + '/account/sessions/revoke-others', {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({ csrf_token: getCsrf() }),
            });
            const data = await res.json();
            if (data.error) { this.error = data.error; }
            else             { await this.load(); }
        } catch (e) {
            this.error = e.message;
        }
    },
}));

// ─────────────────────────────────────────────────────────────────
// Library: skeleton cards + grid trimming
// GRID_ROWS is read from data-grid-rows on the grid container.
// ─────────────────────────────────────────────────────────────────
function getGridRows() {
    return parseInt(document.getElementById('library-grid')?.dataset.gridRows ?? '4', 10);
}

function buildSkeleton(grid) {
    const rows  = getGridRows();
    const cols  = getComputedStyle(grid).gridTemplateColumns.split(' ').length;
    const count = rows * cols;
    return Array.from({ length: count }, (_, i) => `
        <div class="card skeleton-pulse" style="animation-delay:${i * 18}ms">
            <div class="card-img skeleton-pulse"></div>
            <div class="p-3 space-y-2">
                <div class="skeleton-pulse" style="height:11px;border-radius:6px;width:72%;margin-bottom:7px"></div>
                <div class="skeleton-pulse" style="height:10px;border-radius:6px;width:40%"></div>
            </div>
        </div>`).join('');
}

function trimGrid() {
    const grid  = document.getElementById('library-grid');
    const count = document.getElementById('results-count');
    if (!grid) return;
    const rows    = getGridRows();
    const cols    = getComputedStyle(grid).gridTemplateColumns.split(' ').length;
    const visible = rows * cols;
    let shown = 0;
    grid.querySelectorAll('article').forEach((el, i) => {
        const hide = i >= visible;
        el.hidden = hide;
        if (!hide) shown++;
    });
    if (count) {
        const parts = count.textContent.split('/');
        if (parts.length === 2) count.textContent = shown + ' /' + parts[1];
    }
}

// Library Alpine component — search value read from current URL (no Twig injection needed)
Alpine.data('libraryPage', () => ({
    search: new URLSearchParams(window.location.search).get('q') ?? '',
    doSearch() {
        const grid = document.getElementById('library-grid');
        if (grid) grid.innerHTML = buildSkeleton(grid);
        const params = new URLSearchParams(window.location.search);
        const q = this.search.trim();
        if (q) { params.set('q', q); } else { params.delete('q'); }
        params.delete('page'); // reset to page 1 on new search
        // 'order' is preserved automatically since it stays in params
        window.location.href = BASE_PATH + '/?' + params.toString();
    },
}));

// Initial skeleton: replace grid with shimmer cards on load,
// restore real SSR cards once the DOM is ready.
;(function () {
    const grid = document.getElementById('library-grid');
    if (!grid) return;

    const realHTML = grid.innerHTML;
    grid.innerHTML = buildSkeleton(grid);

    function restore() {
        grid.innerHTML = realHTML;
        trimGrid();
        window.addEventListener('resize', trimGrid);
        document.querySelectorAll('nav[aria-label="Pagination"] a').forEach(a => {
            a.addEventListener('click', () => {
                const g = document.getElementById('library-grid');
                if (g) g.innerHTML = buildSkeleton(g);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restore);
    } else {
        requestAnimationFrame(() => requestAnimationFrame(restore));
    }
})();

// ── Start ─────────────────────────────────────────────────────────
window.Alpine = Alpine
Alpine.start()
