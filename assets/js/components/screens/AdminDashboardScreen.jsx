// ── Admin Dashboard ────────────────────────────────────────────────────────────
const ADMIN_NAV = [
  {k:'SESSION_MANAGEMENT', icon:'🗝️', label:'Session Management', desc:'Find and close sessions left open by cashiers'},
  {k:'CARD_INVENTORY',     icon:'📦', label:'Card Inventory',      desc:'Stock and issue blank cards'},
  {k:'FARE_MANAGEMENT',    icon:'💵', label:'Fare Products',       desc:'Configure fares without code changes'},
  {k:'DEVICE_MANAGEMENT',  icon:'📱', label:'Devices',             desc:'Register terminals, gates and inspectors'},
  {k:'SETTLEMENT',         icon:'📊', label:'Settlement',          desc:'Reconciliation and revenue batches'},
  {k:'SECURITY_SETTINGS',  icon:'🔐', label:'Security / MFA',       desc:'Enable authenticator two-factor login'},
];

function AdminDashboardScreen({ user, onNav }) {
  const hour = new Date().getHours();
  const greeting = hour<12?'Morning':hour<17?'Afternoon':'Evening';
  return (
    <div className="fade-in" style={{ flex:1,overflowY:'auto',padding:'32px' }}>
      <div style={{ marginBottom:32 }}>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:26,color:'var(--text-primary)' }}>Good {greeting}, {user?.name?.split(' ')[0]} 👋</div>
        <div style={{ fontSize:13,color:'var(--text-muted)',marginTop:4 }}>Top-level oversight — cashier operations aren't shown here.</div>
      </div>
      <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:11,color:'var(--accent-amber)',letterSpacing:2,textTransform:'uppercase',marginBottom:14 }}>Management</div>
      <div style={{ display:'grid',gridTemplateColumns:'repeat(3,1fr)',gap:18,maxWidth:1100 }}>
        {ADMIN_NAV.map((item,i)=>(
          <button key={item.k} onClick={()=>onNav(item.k)} style={{ background:'linear-gradient(160deg,#1a1608,#100d05)',border:'1.5px solid #3a2f0f',borderRadius:18,padding:'26px 24px',cursor:'pointer',textAlign:'left',transition:'all .2s',animation:`slideUp .4s ${i*.06}s both`,position:'relative',overflow:'hidden' }}
            onMouseEnter={e=>{e.currentTarget.style.transform='translateY(-3px)';e.currentTarget.style.borderColor='var(--accent-amber)';}}
            onMouseLeave={e=>{e.currentTarget.style.transform='';e.currentTarget.style.borderColor='#3a2f0f';}}>
            <div style={{ fontSize:30,marginBottom:12 }}>{item.icon}</div>
            <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:15,color:'var(--text-primary)',marginBottom:6 }}>{item.label}</div>
            <div style={{ fontSize:12,color:'var(--text-muted)' }}>{item.desc}</div>
            <div style={{ position:'absolute',bottom:14,right:14,width:7,height:7,borderRadius:'50%',background:'var(--accent-amber)',boxShadow:'0 0 8px var(--accent-amber)' }}/>
          </button>
        ))}
      </div>
    </div>
  );
}
