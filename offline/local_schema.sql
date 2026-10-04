-- AFCS Terminal Local Database (SQLite)
-- Use on cashier terminals / gates when operating offline-first.
-- Sync via api/sync.php push/pull/ack when connectivity returns.

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

CREATE TABLE IF NOT EXISTS local_meta (
  key TEXT PRIMARY KEY,
  value TEXT
);

CREATE TABLE IF NOT EXISTS cached_fare_products (
  id INTEGER PRIMARY KEY,
  code TEXT NOT NULL,
  name TEXT,
  amount REAL NOT NULL,
  currency TEXT DEFAULT 'TZS',
  media_types TEXT,
  passenger_category TEXT,
  validity_hours INTEGER,
  priority INTEGER DEFAULT 100,
  active INTEGER DEFAULT 1,
  synced_at TEXT
);

CREATE TABLE IF NOT EXISTS cached_cards (
  card_number TEXT PRIMARY KEY,
  holder_name TEXT,
  card_type TEXT,
  balance REAL DEFAULT 0,
  status TEXT,
  penalty_flag INTEGER DEFAULT 0,
  synced_at TEXT
);

CREATE TABLE IF NOT EXISTS local_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  event_id TEXT UNIQUE NOT NULL,
  sequence_number INTEGER,
  event_type TEXT NOT NULL,
  payload TEXT NOT NULL,
  local_timestamp TEXT NOT NULL,
  sync_status TEXT DEFAULT 'PENDING', -- PENDING | SYNCED | FAILED
  retry_count INTEGER DEFAULT 0,
  last_error TEXT
);

CREATE TABLE IF NOT EXISTS local_tickets (
  ticket_code TEXT PRIMARY KEY,
  qr_payload TEXT,
  signature TEXT,
  price REAL,
  status TEXT DEFAULT 'valid', -- valid | used | expired
  issued_at TEXT,
  expires_at TEXT,
  used_at TEXT,
  origin TEXT DEFAULT 'local' -- local | server
);

CREATE TABLE IF NOT EXISTS local_session (
  id INTEGER PRIMARY KEY,
  server_session_id INTEGER,
  user_id INTEGER,
  terminal TEXT,
  started_at TEXT,
  ended_at TEXT,
  opening_float REAL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS offline_policy_cache (
  policy_key TEXT PRIMARY KEY,
  policy_value TEXT,
  device_type TEXT,
  cached_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_outbox_status ON local_outbox(sync_status);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON local_tickets(status);
