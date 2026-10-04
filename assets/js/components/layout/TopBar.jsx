// ── Top Bar ───────────────────────────────────────────────────────────────────
const SCREENS = {
  LOGIN:'LOGIN', DASHBOARD:'DASHBOARD', QR_SALE:'QR_SALE', CARD_SALE:'CARD_SALE',
  TOPUP:'TOPUP', CARD_DETAILS:'CARD_DETAILS', SESSION_REPORT:'SESSION_REPORT',
  LOGIN_LOGOUT_REPORT:'LOGIN_LOGOUT_REPORT', CARD_DEACTIVATION:'CARD_DEACTIVATION',
  CARD_ACTIVATION:'CARD_ACTIVATION', BALANCE_TRANSFER:'BALANCE_TRANSFER',
  CARD_REISSUE:'CARD_REISSUE', PENALTY_FLAG:'PENALTY_FLAG',
};

function TopBar({ user, onEndSession, onNav, screen }) {
  const [time, setTime] = useState(new Date());
  useEffect(() => { const t = setInterval(()=>setTime(new Date()),1000); return ()=>clearInterval(t); }, []);
  return (
    <div style={{ background:'var(--bg-panel)',borderBottom:'1px solid var(--border)',display:'flex',alignItems:'center',justifyContent:'space-between',padding:'0 24px',height:60,flexShrink:0 }}>
      <div style={{ display:'flex',alignItems:'center',gap:16 }}>
        <div style={{ background:'linear-gradient(135deg,#00d4b4,#00a896)',borderRadius:10,padding:'6px 14px',fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:16,color:'#070c14',letterSpacing:1 }}>J11</div>
        {screen!=='DASHBOARD'&&<button onClick={()=>onNav(SCREENS.DASHBOARD)} style={{ background:'none',border:'none',color:'var(--text-muted)',cursor:'pointer',fontSize:13 }}>← Dashboard</button>}
      </div>
      <div style={{ display:'flex',alignItems:'center',gap:6 }}>
        <div style={{ width:8,height:8,borderRadius:'50%',background:'var(--success)',boxShadow:'0 0 8px var(--success)' }} className="pulse-dot"/>
        <span style={{ fontFamily:"'DM Mono',monospace",fontSize:12,color:'var(--text-muted)' }}>{user?.terminal||'KIGAMBONI TERMINAL'} — TUBM1</span>
      </div>
      <div style={{ display:'flex',alignItems:'center',gap:20 }}>
        <div style={{ textAlign:'right' }}>
          <div style={{ fontFamily:"'DM Mono',monospace",fontSize:17,fontWeight:500,color:'var(--text-primary)' }}>{time.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit',second:'2-digit'})}</div>
          <div style={{ fontSize:11,color:'var(--text-muted)' }}>{time.toLocaleDateString('en-GB',{weekday:'short',day:'2-digit',month:'short',year:'numeric'})}</div>
        </div>
        <div style={{ width:1,height:32,background:'var(--border)' }}/>
        <div style={{ display:'flex',alignItems:'center',gap:10 }}>
          <div style={{ width:36,height:36,borderRadius:'50%',background:'linear-gradient(135deg,#1e3a56,#0d2035)',border:'2px solid var(--accent-teal)',display:'flex',alignItems:'center',justifyContent:'center',fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:13,color:'var(--accent-teal)' }}>
            {user?.name?.split(' ').map(n=>n[0]).join('').slice(0,2)}
          </div>
          <div>
            <div style={{ fontSize:13,fontWeight:600,color:'var(--text-primary)' }}>{user?.name}</div>
            <div style={{ fontSize:11,color:'var(--text-muted)',textTransform:'capitalize' }}>{user?.role}</div>
          </div>
        </div>
        <button onClick={onEndSession} className="btn-danger" style={{ padding:'8px 16px',fontSize:12 }}>🔒 End Session</button>
      </div>
    </div>
  );
}

