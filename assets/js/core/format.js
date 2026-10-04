// ── Shared helpers ────────────────────────────────────────────────────────────
const fmtMoney = n => Number(n||0).toLocaleString();
const fmtDate  = d => d ? new Date(d).toLocaleString('en-GB', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';

