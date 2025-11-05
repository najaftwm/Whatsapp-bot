// Lightweight auth client that talks to PHP backend (backendphp/api/login.php)
// Stores auth state in localStorage (isAuthenticated) to match existing App.jsx expectations.

const AUTH_FLAG_KEY = 'isAuthenticated';
const USER_DATA_KEY = 'authUser';

// Adjust this if your backend runs at a different origin/path
const BASE_URL = 'http://localhost/whatsapp-backend/backendphp/api';

export const authClient = {
  isAuthenticated() {
    try {
      return localStorage.getItem(AUTH_FLAG_KEY) === 'true';
    } catch (_) {
      return false;
    }
  },

  getUser() {
    try {
      const raw = localStorage.getItem(USER_DATA_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (_) {
      return null;
    }
  },

  async login(email, password) {
    const resp = await fetch(`${BASE_URL}/login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include', // allow PHP session cookie
      body: JSON.stringify({ email, password })
    });

    const data = await resp.json().catch(() => ({}));

    if (!resp.ok || data?.ok !== true) {
      const message = data?.error || 'Login failed';
      throw new Error(message);
    }

    try {
      localStorage.setItem(AUTH_FLAG_KEY, 'true');
      localStorage.setItem(USER_DATA_KEY, JSON.stringify(data.user));
      // Fire storage event compatibility for same-tab state consumers if needed
      window.dispatchEvent(new StorageEvent('storage', { key: AUTH_FLAG_KEY, newValue: 'true' }));
    } catch (_) {
      // ignore storage errors
    }

    return data.user;
  },

  logout() {
    try {
      localStorage.removeItem(AUTH_FLAG_KEY);
      localStorage.removeItem(USER_DATA_KEY);
      window.dispatchEvent(new StorageEvent('storage', { key: AUTH_FLAG_KEY, newValue: 'false' }));
    } catch (_) {
      // ignore
    }
  }
};


