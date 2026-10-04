// ── Session Management (Admin) ────────────────────────────────────────────────
// This is how an admin finds a session a cashier left open (logged out
// without ending it) and closes it out — the only other way it can be closed
// is the same cashier logging back in and ending it themselves.
function SessionManagementScreen({ toast }) {
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading]   = useState(true);
  const [confirming, setConfirming] = useState(null); // session object pending confirmation
  const [ending, setEnding] = useState(false);

  const load = useCallback(async (silent=false) => {
    const res = await apiGet('auth','admin_open_sessions');
    setLoading(false);
    if (res.success) setSessions(res.data);
    else if (!silent) toast(res.message,'error');
  }, []);

  useEffect(() => {
    load();
    const t = setInterval(()=>load(true), 5000); // live sync
    return () => clearInterval(t);
  }, [load]);

  const forceEnd = async () => {
    if (!confirming) return;
    setEnding(true);
    const res = await api('auth','admin_end_session',{ session_id: confirming.id });
    setEnding(false);
    if (!res.success) { toast(res.message,'error'); return; }
    toast(`Session ${res.data.session_code} closed for ${confirming.cashier_name}`,'ok');
    setConfirming(null);
    load(true);
  };

  const duration = (started) => {
    const mins = Math.floor((Date.now() - new Date(started).getTime()) / 60000);
    if (mins < 60) return `${mins}m`;
    return `${Math.floor(mins/60)}h ${mins%60}m`;
  };

  return (
    <div className="fade-in" style={{ flex:1,overflowY:'auto',padding:'28px',display:'flex',flexDirection:'column',gap:20 }}>
      <div>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:22,color:'var(--text-primary)' }}>Session Management</div>
        <div style={{ fontSize:13,color:'var(--text-muted)',marginTop:2,display:'flex',alignItems:'center',gap:8 }}>
          <span>Every cashier session currently left open, across every terminal</span>
          <span style={{ display:'flex',alignItems:'center',gap:5,fontSize:11,color:'var(--success)' }}>
            <span className="pulse-dot" style={{ width:6,height:6,borderRadius:'50%',background:'var(--success)',boxShadow:'0 0 6px var(--success)' }}/>
            LIVE
          </span>
        </div>
      </div>

      {loading ? (
        <div style={{ flex:1,display:'flex',alignItems:'center',justifyContent:'center' }}><Spinner/></div>
      ) : sessions.length === 0 ? (
        <div style={{ flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',gap:10,color:'var(--text-dim)' }}>
          <div style={{ fontSize:44 }}>✅</div>
          <div>No open sessions right now — every terminal is clocked out.</div>
        </div>
      ) : (
        <div style={{ display:'flex',flexDirection:'column',gap:10 }}>
          {sessions.map(s=>(
            <div key={s.id} style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,padding:'18px 22px',display:'flex',alignItems:'center',justifyContent:'space-between',gap:20 }}>
              <div style={{ minWidth:180 }}>
                <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:15,color:'var(--text-primary)' }}>{s.cashier_name}</div>
                <div style={{ fontSize:11,color:'var(--text-muted)',marginTop:2 }}>@{s.username} — {s.terminal}</div>
              </div>
              <div style={{ display:'flex',alignItems:'center',gap:6,fontSize:12,color:'var(--accent-amber)' }}>
                <span className="pulse-dot" style={{ width:6,height:6,borderRadius:'50%',background:'var(--accent-amber)',boxShadow:'0 0 6px var(--accent-amber)' }}/>
                Open {duration(s.started_at)}
              </div>
              <div style={{ display:'flex',gap:18,fontFamily:"'DM Mono',monospace",fontSize:12,color:'var(--text-muted)' }}>
                <span>🎫 {s.qr_qty}</span>
                <span>💳 {s.topup_qty}</span>
                <span>🪪 {s.cards_issued}</span>
                <span style={{ color:'var(--accent-teal)' }}>Tshs {Number(Number(s.qr_total)+Number(s.topup_total)).toLocaleString()}</span>
              </div>
              <button className="btn-danger" onClick={()=>setConfirming(s)} style={{ padding:'10px 18px',fontSize:12 }}>End Session</button>
            </div>
          ))}
        </div>
      )}

      {/* Confirm modal */}
      {confirming && (
        <div style={{ position:'fixed',inset:0,background:'rgba(0,0,0,.6)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:999 }}>
          <div className="slide-up" style={{ background:'var(--bg-panel)',border:'1px solid var(--border-bright)',borderRadius:20,padding:'32px',width:420 }}>
            <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:18,color:'var(--text-primary)',marginBottom:10 }}>End this cashier's session?</div>
            <div style={{ fontSize:13,color:'var(--text-muted)',lineHeight:1.6,marginBottom:22 }}>
              This will close <b style={{color:'var(--text-primary)'}}>{confirming.cashier_name}</b>'s session on <b style={{color:'var(--text-primary)'}}>{confirming.terminal}</b>, open for {duration(confirming.started_at)}.
              It'll be marked in the audit log as closed by you, not by them. They won't be able to resume it — a fresh login will start a brand new session instead.
            </div>
            <div style={{ display:'flex',gap:12 }}>
              <button className="btn-danger" onClick={forceEnd} disabled={ending} style={{ flex:1,padding:'14px' }}>
                {ending?<><Spinner/> ENDING...</>:'END SESSION'}
              </button>
              <button className="btn-ghost" onClick={()=>setConfirming(null)} style={{ padding:'14px 20px' }}>Cancel</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
