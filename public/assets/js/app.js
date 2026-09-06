const LaenutusApp = {
    TOKEN_KEY: 'laenutus:token',
    USER_KEY: 'laenutus:user',

    getToken() {
        return localStorage.getItem(this.TOKEN_KEY);
    },

    getUser() {
        const raw = localStorage.getItem(this.USER_KEY);
        return raw ? JSON.parse(raw) : null;
    },

    setSession(token, user) {
        localStorage.setItem(this.TOKEN_KEY, token);
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
        this.updateNav();
    },

    clearSession() {
        localStorage.removeItem(this.TOKEN_KEY);
        localStorage.removeItem(this.USER_KEY);
        this.updateNav();
    },

    updateNav() {
        const nav = document.getElementById('main-nav');
        if (!nav) return;
        if (this.getToken()) {
            nav.classList.remove('hidden');
        } else {
            nav.classList.add('hidden');
        }
    },

    requireAuth() {
        if (!this.getToken()) {
            window.location.href = '/login';
        } else {
            this.updateNav();
        }
    },

    async login(email, password) {
        const res = await fetch('/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password }),
        });

        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'Sisselogimine ebaõnnestus');
        }

        this.setSession(data.token, data.user);
        return data;
    },

    async api(path, options = {}) {
        const headers = {
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        };

        const token = this.getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const res = await fetch(path, { ...options, headers });

        if (res.status === 204) {
            return null;
        }

        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || `Viga ${res.status}`);
        }

        return data;
    },

    showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.textContent = message;
        toast.className = `toast ${type}`;
        setTimeout(() => toast.classList.add('hidden'), 4000);
        toast.classList.remove('hidden');
    },
};

document.addEventListener('DOMContentLoaded', () => {
    LaenutusApp.updateNav();

    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            LaenutusApp.clearSession();
            window.location.href = '/login';
        });
    }
});
