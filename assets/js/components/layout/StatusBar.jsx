function StatusBar({ lastSync, syncing, onSyncNow }) {
  const [, forceTick] = useState(0);
  const [online, setOnline] = useState(typeof navigator !== 'undefined' ? navigator.onLine : true);
  const [outboxCount, setOutboxCount] = useState(0);

  useEffect(() => {
    const t = setInterval(() => {
      forceTick(n => n + 1);
      try { setOutboxCount((JSON.parse(localStorage.getItem('afcs_sync_outbox_v1') || '[]')).length); } catch {}
    }, 1000);
    const on = () => setOnline(true);
    const off = () => setOnline(false);
    window.addEventListener('online', on);
    window.addEventListener('offline', off);
    return () => {
      clearInterval(t);
      window.removeEventListener('online', on);
      window.removeEventListener('offline', off);
    };
  }, []);

  const syncLabel = () => {
    if (syncing) return 'Syncing…';
    if (!lastSync) return 'Not synced yet';
    const secs = Math.max(0, Math.round((Date.now() - lastSync.getTime()) / 1000));
    if (secs < 2) return 'Synced just now';
    if (secs < 60) return `Synced ${secs}s ago`;
    return `Synced ${Math.round(secs / 60)}m ago`;
  };

  return (
    <div style={{ background:'var(--bg-deep)', borderTop:'1px solid var(--border)', display:'flex', alignItems:'center', justifyContent:'space-between', padding:'0 24px', height:36, flexShrink:0, fontSize:11, color:'var(--text-dim)' }}>
      <span style={{ fontFamily:"'DM Mono',monospace" }}>AFCS v4 — Offline-ready platform</span>
      <div style={{ display:'flex', alignItems:'center', gap:20 }}>
        <span style={{ color: online ? 'var(--success)' : 'var(--accent-amber)', fontWeight:600 }}>
          {online ? '🟢 ONLINE' : '🟠 OFFLINE'}
        </span>
        {outboxCount > 0 && (
          <span style={{ color:'var(--accent-amber)' }} title="Pending local events">📤 {outboxCount} queued</span>
        )}
        <span style={{ color:'var(--accent-teal)' }}>💳 Card Reader Ready</span>
        <span>🖨️ Printer Ready</span>
        <div style={{ display:'flex', alignItems:'center', gap:6, cursor: onSyncNow ? 'pointer' : 'default' }} onClick={onSyncNow} title="Click to sync now">
          <div style={{ width:7, height:7, borderRadius:'50%', background: syncing ? 'var(--accent-amber)' : 'var(--success)', boxShadow:`0 0 6px ${syncing ? 'var(--accent-amber)' : 'var(--success)'}` }} className={syncing ? '' : 'pulse-dot'}/>
          <span style={{ fontFamily:"'DM Mono',monospace", color: syncing ? 'var(--accent-amber)' : 'var(--text-dim)' }}>{syncLabel()}</span>
        </div>
      </div>
    </div>
  );
}
