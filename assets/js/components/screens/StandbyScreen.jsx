// ── Standby Screen ───────────────────────────────────────────────────────────
function StandbyScreen({ user, onStartSession, onLogout, startingSession, blockedSession }) {
  const [time, setTime] = useState(new Date());
  const [prevSessions, setPrevSessions] = useState([]);

  useEffect(() => {
    const t = setInterval(() => setTime(new Date()), 1000);
    return () => clearInterval(t);
  }, []);

  useEffect(() => {
    apiGet('reports', 'all_sessions').then(res => {
      if (res.success) setPrevSessions(res.data.slice(0, 5));
    });
  }, []);

  return (
    <div style={{ width:'100vw', height:'100vh', background:'var(--bg-deep)', display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', position:'relative', overflow:'hidden' }}>
      {/* Background grid */}
      <div style={{ position:'absolute', inset:0, backgroundImage:'linear-gradient(var(--border) 1px,transparent 1px),linear-gradient(90deg,var(--border) 1px,transparent 1px)', backgroundSize:'60px 60px', opacity:.3 }}/>
      <div style={{ position:'absolute', top:'20%', left:'10%', width:400, height:400, borderRadius:'50%', background:'radial-gradient(circle,rgba(0,212,180,.05),transparent 70%)' }}/>
      <div style={{ position:'absolute', bottom:'10%', right:'10%', width:300, height:300, borderRadius:'50%', background:'radial-gradient(circle,rgba(74,158,255,.05),transparent 70%)' }}/>

      {/* Main card */}
      <div className="slide-up" style={{ background:'var(--bg-panel)', border:'1px solid var(--border-bright)', borderRadius:28, padding:'48px 52px', width:480, textAlign:'center', boxShadow:'0 40px 80px rgba(0,0,0,.6)', zIndex:1 }}>
        {/* J11 logo */}
        <div style={{ display:'flex', alignItems:'center', justifyContent:'center', gap:14, marginBottom:32 }}>
          <div style={{ width:52, height:52, borderRadius:16, background:'linear-gradient(135deg,#00d4b4,#007a6e)', display:'flex', alignItems:'center', justifyContent:'center', fontSize:26 }}>🚇</div>
          <div>
            <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:32, color:'var(--accent-teal)', letterSpacing:2 }}>J11</div>
            <div style={{ fontSize:9, color:'var(--text-muted)', letterSpacing:3 }}>AUTOMATED FARE COLLECTION</div>
          </div>
        </div>

        {/* Live clock */}
        <div style={{ marginBottom:28 }}>
          <div style={{ fontFamily:"'DM Mono',monospace", fontSize:48, fontWeight:300, color:'var(--text-primary)', letterSpacing:4, lineHeight:1 }}>
            {time.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit', second:'2-digit' })}
          </div>
          <div style={{ fontSize:13, color:'var(--text-muted)', marginTop:8 }}>
            {time.toLocaleDateString('en-GB', { weekday:'long', day:'2-digit', month:'long', year:'numeric' })}
          </div>
        </div>

        {/* Terminal & cashier info */}
        <div style={{ background:'var(--bg-card)', border:'1px solid var(--border)', borderRadius:16, padding:'16px 20px', marginBottom:28 }}>
          <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center' }}>
            <div style={{ textAlign:'left' }}>
              <div style={{ fontSize:10, color:'var(--text-dim)', letterSpacing:2, textTransform:'uppercase', marginBottom:4 }}>Logged in as</div>
              <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:16, color:'var(--text-primary)' }}>{user?.name}</div>
              <div style={{ fontSize:12, color:'var(--text-muted)', marginTop:2, textTransform:'capitalize' }}>{user?.role}</div>
            </div>
            <div style={{ textAlign:'right' }}>
              <div style={{ fontSize:10, color:'var(--text-dim)', letterSpacing:2, textTransform:'uppercase', marginBottom:4 }}>Terminal</div>
              <div style={{ fontSize:13, color:'var(--accent-teal)', fontFamily:"'DM Mono',monospace" }}>{user?.terminal}</div>
              <div style={{ display:'flex', alignItems:'center', gap:5, justifyContent:'flex-end', marginTop:4 }}>
                <div style={{ width:7, height:7, borderRadius:'50%', background:blockedSession?'var(--accent-red)':'var(--accent-amber)', boxShadow:`0 0 6px ${blockedSession?'var(--accent-red)':'var(--accent-amber)'}` }} className="pulse-dot"/>
                <span style={{ fontSize:11, color:blockedSession?'var(--accent-red)':'var(--accent-amber)' }}>{blockedSession?'LOCKED':'STANDBY'}</span>
              </div>
            </div>
          </div>
        </div>

        {blockedSession ? (
          <div style={{ background:'rgba(255,71,87,.08)',border:'1.5px solid var(--accent-red)',borderRadius:16,padding:'18px 20px',marginBottom:12,textAlign:'left' }}>
            <div style={{ display:'flex',alignItems:'center',gap:8,marginBottom:8 }}>
              <span style={{ fontSize:16 }}>🔒</span>
              <span style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:13,color:'var(--accent-red)' }}>Terminal Locked</span>
            </div>
            <div style={{ fontSize:12,color:'var(--text-muted)',lineHeight:1.6 }}>
              <b style={{color:'var(--text-primary)'}}>{blockedSession.cashier_name}</b> has an open session here since{' '}
              {new Date(blockedSession.started_at).toLocaleString('en-GB',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'})}.
              Only they can log back in to continue it, or an admin can close it from Session Management.
            </div>
          </div>
        ) : (
          /* Start Session button */
          <button
            className="btn-primary"
            onClick={onStartSession}
            disabled={startingSession}
            style={{ width:'100%', padding:'18px', fontSize:15, letterSpacing:1, marginBottom:12, background:'linear-gradient(135deg,#00e676,#00c853)', boxShadow:'0 4px 24px rgba(0,230,118,.35)' }}>
            {startingSession
              ? <><Spinner/> STARTING SESSION...</>
              : '▶  START SESSION'}
          </button>
        )}

        {/* Logout */}
        <button className="btn-ghost" onClick={onLogout} style={{ width:'100%', padding:'13px', fontSize:13 }}>
          ⏻  Logout
        </button>
      </div>

      {/* Previous sessions (last 5) */}
      {prevSessions.length > 0 && (
        <div className="fade-in" style={{ zIndex:1, marginTop:24, width:480 }}>
          <div style={{ fontSize:10, color:'var(--text-dim)', letterSpacing:2, textTransform:'uppercase', textAlign:'center', marginBottom:10 }}>Previous Sessions</div>
          <div style={{ display:'flex', flexDirection:'column', gap:6 }}>
            {prevSessions.map((s, i) => (
              <div key={s.id} style={{ background:'var(--bg-panel)', border:'1px solid var(--border)', borderRadius:12, padding:'10px 16px', display:'flex', justifyContent:'space-between', alignItems:'center', opacity: 1 - i * 0.15 }}>
                <div style={{ fontFamily:"'DM Mono',monospace", fontSize:11, color:'var(--text-muted)' }}>
                  {new Date(s.started_at).toLocaleString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' })}
                  {' → '}
                  {s.ended_at ? new Date(s.ended_at).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }) : <span style={{ color:'var(--accent-red)' }}>Unclosed</span>}
                </div>
                <div style={{ display:'flex', gap:16, fontFamily:"'DM Mono',monospace", fontSize:11 }}>
                  <span style={{ color:'var(--accent-blue)' }}>🎫 {s.qr_qty}</span>
                  <span style={{ color:'var(--accent-amber)' }}>💰 {s.topup_qty}</span>
                  <span style={{ color:'var(--accent-teal)' }}>Tshs {Number(s.qr_total + s.topup_total).toLocaleString()}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

