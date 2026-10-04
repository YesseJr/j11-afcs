// ── API client ───────────────────────────────────────────────────────────────
const API = 'api';

function makeIdempotencyKey() {
  if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
  return 'idem-' + Date.now() + '-' + Math.random().toString(36).slice(2, 12);
}

function normalizeApiError(json, httpOk) {
  if (!json || typeof json !== 'object') {
    return { success: false, error_code: 'INVALID_RESPONSE', message: 'Invalid server response.', data: null };
  }
  // Map legacy/db failures to a stable code for the UI
  if (!json.success) {
    const msg = (json.message || '').toLowerCase();
    if (json.error_code === 'DB_CONNECTION_FAILED' || json.error_code === 'SERVICE_UNAVAILABLE' ||
        msg.includes('database connection') || msg.includes('sqlstate') || msg.includes('refused')) {
      json.error_code = 'SERVICE_UNAVAILABLE';
      if (!msg.includes('enrolled device')) {
        json.message = 'Central fare system is temporarily unavailable. Use an enrolled terminal with a valid offline grant, or try again when the service is restored.';
      }
    }
  }
  return json;
}

async function api(file, action, body = null, opts = {}) {
  try {
    const url = `${API}/${file}.php?action=${action}`;
    const headers = { 'Content-Type': 'application/json' };
    if (opts.idempotent) {
      headers['Idempotency-Key'] = opts.idempotencyKey || makeIdempotencyKey();
    }
    if (opts.requestId) headers['X-Request-Id'] = opts.requestId;

    const fetchOpts = {
      method: body ? 'POST' : 'GET',
      credentials: 'same-origin',
      headers,
    };
    if (body) fetchOpts.body = JSON.stringify(body);
    const res = await fetch(url, fetchOpts);
    let json;
    try { json = await res.json(); } catch {
      return { success: false, error_code: res.status >= 500 ? 'SERVICE_UNAVAILABLE' : 'INVALID_RESPONSE', message: 'Central fare system is temporarily unavailable.', data: null };
    }
    return normalizeApiError(json, res.ok);
  } catch (e) {
    return {
      success: false,
      error_code: 'SERVICE_UNAVAILABLE',
      message: 'Cannot reach the central fare system (network or server offline). Enrolled devices may continue under a valid offline grant.',
      data: null,
    };
  }
}

async function apiGet(file, action, params = {}) {
  const qs = new URLSearchParams({ action, ...params }).toString();
  try {
    const res = await fetch(`${API}/${file}.php?${qs}`, { credentials: 'same-origin' });
    let json;
    try { json = await res.json(); } catch {
      return { success: false, error_code: 'SERVICE_UNAVAILABLE', message: 'Central fare system is temporarily unavailable.', data: null };
    }
    return normalizeApiError(json, res.ok);
  } catch {
    return {
      success: false,
      error_code: 'SERVICE_UNAVAILABLE',
      message: 'Cannot reach the central fare system (network or server offline).',
      data: null,
    };
  }
}

/** Probe central readiness without requiring login */
async function probeCentralHealth() {
  try {
    const res = await fetch(`${API}/health.php?action=ready`, { credentials: 'same-origin' });
    const json = await res.json();
    return { ok: res.ok && json.status === 'ready', detail: json };
  } catch {
    return { ok: false, detail: null };
  }
}
