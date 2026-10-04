// ── Admin Top Bar ──────────────────────────────────────────────────────────────
// Deliberately different palette (gold/management vs. cashier teal) and no
// cashier-only controls (no terminal clock-in status, no "End Session") —
// this is a management console, not a cashier till with an extra tile bolted on.
function AdminTopBar({ user, onNav, screen, onLogout }) {
  return (
    <div style={{ background:'linear-gradient(90deg,#1a1608,#0d0b04)',borderBottom:'1px solid #3a2f0f',display:'flex',alignItems:'center',justifyContent:'space-between',padding:'0 24px',height:60,flexShrink:0 }}>
      <div style={{ display:'flex',alignItems:'center',gap:16 }}>
        <div style={{ background:'linear-gradient(135deg,#ffb830,#c9860a)',borderRadius:10,padding:'6px 14px',fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:16,color:'#1a1608',letterSpacing:1 }}>J11</div>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:13,color:'var(--accent-amber)',letterSpacing:2,textTransform:'uppercase' }}>Management Console</div>
        {screen!=='ADMIN_DASHBOARD'&&<button onClick={()=>onNav('ADMIN_DASHBOARD')} style={{ background:'none',border:'none',color:'var(--text-muted)',cursor:'pointer',fontSize:13 }}>← Console Home</button>}
      </div>
      <div style={{ display:'flex',alignItems:'center',gap:20 }}>
        <div style={{ display:'flex',alignItems:'center',gap:10 }}>
          <div style={{ width:36,height:36,borderRadius:'50%',background:'linear-gradient(135deg,#4a3a10,#1a1608)',border:'2px solid var(--accent-amber)',display:'flex',alignItems:'center',justifyContent:'center',fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:13,color:'var(--accent-amber)' }}>
            {user?.name?.split(' ').map(n=>n[0]).join('').slice(0,2)}
          </div>
          <div>
            <div style={{ fontSize:13,fontWeight:600,color:'var(--text-primary)' }}>{user?.name}</div>
            <div style={{ fontSize:11,color:'var(--accent-amber)',textTransform:'uppercase',letterSpacing:1 }}>Administrator</div>
          </div>
        </div>
        <button onClick={onLogout} className="btn-ghost" style={{ padding:'8px 16px',fontSize:12 }}>⏻ Logout</button>
      </div>
    </div>
  );
}
