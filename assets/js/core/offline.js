// ── Offline-first helpers (browser side) ─────────────────────────────────────
// Security model:
// - Offline *operations* require a server-issued offline grant for an enrolled device
// - Grants expire; permissions are a subset (no unrestricted admin/refund)
// - Browser localStorage is a cache for demos/POS shells — gates should use SQLite
// - Without a valid grant, UI must not claim offline sales are available

const AFCS_OUTBOX_KEY = 'afcs_sync_outbox_v1';
const AFCS_POLICY_KEY = 'afcs_offline_policies_v1';
const AFCS_GRANT_KEY  = 'afcs_offline_grant_v1';

function afcsIsOnline() {
  return typeof navigator !== 'undefined' ? navigator.onLine : true;
}

function afcsLoadOutbox() {
  try { return JSON.parse(localStorage.getItem(AFCS_OUTBOX_KEY) || '[]'); }
  catch { return []; }
}

function afcsSaveOutbox(items) {
  localStorage.setItem(AFCS_OUTBOX_KEY, JSON.stringify(items));
}

function afcsEnqueueLocalEvent(eventType, payload) {
  const items = afcsLoadOutbox();
  const event = {
    event_id: (crypto.randomUUID && crypto.randomUUID()) || ('evt-' + Date.now() + '-' + Math.random().toString(36).slice(2)),
    event_type: eventType,
    payload,
    local_timestamp: new Date().toISOString(),
    sequence_number: items.length + 1,
  };
  items.push(event);
  afcsSaveOutbox(items);
  return event;
}

async function afcsFlushOutbox() {
  if (!afcsIsOnline()) return { flushed: 0, reason: 'offline' };
  const items = afcsLoadOutbox();
  if (!items.length) return { flushed: 0 };
  const res = await api('sync', 'push', {
    events: items,
    device_uid: localStorage.getItem('afcs_device_uid') || 'browser-terminal',
  });
  if (res.success) {
    const accepted = new Set([...(res.data.accepted || []), ...(res.data.duplicates || [])]);
    const remaining = items.filter(e => !accepted.has(e.event_id));
    afcsSaveOutbox(remaining);
    return { flushed: items.length - remaining.length, remaining: remaining.length };
  }
  return { flushed: 0, error: res.message };
}

async function afcsRefreshOfflinePolicies(deviceType = 'cashier_terminal') {
  if (!afcsIsOnline()) return null;
  const res = await apiGet('network', 'offline_policies', { device_type: deviceType });
  if (res.success) {
    localStorage.setItem(AFCS_POLICY_KEY, JSON.stringify(res.data.policies || {}));
    return res.data.policies;
  }
  return null;
}

function afcsGetOfflinePolicies() {
  try { return JSON.parse(localStorage.getItem(AFCS_POLICY_KEY) || '{}'); }
  catch { return {}; }
}

function afcsCanOffline(actionKey) {
  const grant = afcsGetOfflineGrant();
  if (!grant || !afcsGrantIsValid(grant)) return false;
  const perms = grant.permissions || [];
  const map = {
    allow_qr_sale: 'ISSUE_QR',
    allow_topup: 'TOPUP_CARD',
    allow_card_issue: 'CREATE_CARD',
    allow_validate_qr: 'VALIDATE_QR',
    allow_validate_card: 'VIEW_CARD',
  };
  const need = map[actionKey] || actionKey;
  return perms.indexOf(need) >= 0;
}

function afcsSaveOfflineGrant(grant) {
  localStorage.setItem(AFCS_GRANT_KEY, JSON.stringify(grant));
  if (grant.device_uid) localStorage.setItem('afcs_device_uid', grant.device_uid);
  if (grant.policies) localStorage.setItem(AFCS_POLICY_KEY, JSON.stringify(grant.policies));
}

function afcsGetOfflineGrant() {
  try { return JSON.parse(localStorage.getItem(AFCS_GRANT_KEY) || 'null'); }
  catch { return null; }
}

function afcsGrantIsValid(grant) {
  if (!grant || !grant.expires_at || !grant.device_uid || !grant.signature) return false;
  if (new Date(grant.expires_at) <= new Date()) return false;
  // Client cannot re-derive HMAC without secret — presence of server signature + expiry is the gate;
  // native clients should verify signature with embedded public key in a later iteration.
  return Array.isArray(grant.permissions);
}

/**
 * Request a time-bound offline grant for an enrolled device (requires central online once).
 */
async function afcsRequestOfflineGrant(deviceUid, authToken) {
  const res = await api('devices', 'offline_grant', {
    device_uid: deviceUid,
    auth_token: authToken,
  });
  if (res.success && res.data) {
    afcsSaveOfflineGrant(res.data);
    return res.data;
  }
  return null;
}

function afcsClearOfflineGrant() {
  localStorage.removeItem(AFCS_GRANT_KEY);
}
