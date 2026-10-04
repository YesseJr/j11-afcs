// ── Dashboard ─────────────────────────────────────────────────────────────────
const NAV = [
  {k:'QR_SALE',   icon:'⬛',label:'QR Sale',         color:'#4a9eff', desc:'Student QR tickets'},
  {k:'CARD_SALE', icon:'🪪',label:'Card Sale',        color:'#00d4b4', desc:'Register new card',highlight:true},
  {k:'TOPUP',     icon:'💰',label:'Topup',            color:'#ffb830', desc:'Add card balance',  highlight:true},
  {k:'CARD_DETAILS',icon:'🔍',label:'Card Details',   color:'#a78bfa', desc:'View card info & balance'},
  {k:'SESSION_REPORT',icon:'📊',label:'Session Report',color:'#34d399',desc:'Current session totals'},
  {k:'CARD_DEACTIVATION',icon:'🔒',label:'Deactivate',color:'#f87171',desc:'Block a card'},
  {k:'BALANCE_TRANSFER',icon:'🔄',label:'Bal. Transfer',color:'#fb923c',desc:'Move funds between cards'},
  {k:'CARD_REISSUE',icon:'🔁',label:'Card Reissue',  color:'#38bdf8', desc:'Replace lost/damaged card'},
  {k:'LOGIN_LOGOUT_REPORT',icon:'📋',label:'Login Log',color:'#a3e635',desc:'Access history'},
  {k:'CARD_ACTIVATION',icon:'🔓',label:'Activate',   color:'#4ade80', desc:'Enable a card'},
  {k:'PENALTY_FLAG',icon:'🚨',label:'Penalty Flag',   color:'#fb7185', desc:'Flag/unflag violations'},
];

function DashboardScreen({ user, onNav, stats }) {
  const hour = new Date().getHours();
  const greeting = hour<12?'Morning':hour<17?'Afternoon':'Evening';
  const nav = NAV;
  return (
    <div className="fade-in" style={{ flex:1,overflowY:'auto',padding:'24px' }}>
      <div style={{ display:'flex',justifyContent:'space-between',alignItems:'flex-end',marginBottom:24 }}>
        <div>
          <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:24,color:'var(--text-primary)' }}>Good {greeting}, {user?.name?.split(' ')[0]} 👋</div>
          <div style={{ fontSize:13,color:'var(--text-muted)',marginTop:4 }}>{user?.terminal} — Active Session</div>
        </div>
      </div>
      {/* Live stats */}
      <div style={{ display:'grid',gridTemplateColumns:'repeat(4,1fr)',gap:14,marginBottom:24 }}>
        {[
          {label:'QR Tickets',value:stats.qr_qty,icon:'🎫',color:'#4a9eff'},
          {label:'Topups',value:stats.topup_qty,icon:'💳',color:'#ffb830'},
          {label:'QR Revenue',value:fmtMoney(stats.qr_total),unit:'Tshs',icon:'💵',color:'#00d4b4'},
          {label:'Topup Revenue',value:fmtMoney(stats.topup_total),unit:'Tshs',icon:'💰',color:'#a78bfa'},
        ].map(s=>(
          <div key={s.label} style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,padding:'20px',borderTop:`3px solid ${s.color}` }}>
            <div style={{ display:'flex',justifyContent:'space-between',marginBottom:10 }}>
              <span style={{ fontSize:11,color:'var(--text-muted)',textTransform:'uppercase',letterSpacing:1 }}>{s.label}</span>
              <span style={{ fontSize:18 }}>{s.icon}</span>
            </div>
            <div style={{ fontFamily:"'DM Mono',monospace",fontSize:24,fontWeight:500,color:s.color }}>{s.value}</div>
            {s.unit&&<div style={{ fontSize:11,color:'var(--text-dim)',marginTop:4 }}>{s.unit}</div>}
          </div>
        ))}
      </div>
      {/* Nav grid */}
      <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:11,color:'var(--text-dim)',letterSpacing:2,textTransform:'uppercase',marginBottom:12 }}>Operations</div>
      <div style={{ display:'grid',gridTemplateColumns:'repeat(4,1fr)',gap:14 }}>
        {nav.map((item,i)=>(
          <button key={item.k} onClick={()=>onNav(item.k)} style={{ background:item.highlight?'rgba(0,212,180,.08)':'var(--bg-card)',border:`1.5px solid ${item.highlight?'var(--accent-teal)':'var(--border)'}`,borderRadius:16,padding:'18px 16px',cursor:'pointer',textAlign:'left',transition:'all .2s',animation:`slideUp .4s ${i*.04}s both`,position:'relative',overflow:'hidden' }}
            onMouseEnter={e=>{e.currentTarget.style.transform='translateY(-3px)';e.currentTarget.style.borderColor=item.color;}}
            onMouseLeave={e=>{e.currentTarget.style.transform='';e.currentTarget.style.borderColor=item.highlight?'var(--accent-teal)':'var(--border)';}}>
            <div style={{ fontSize:22,marginBottom:8 }}>{item.icon}</div>
            <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:12,color:'var(--text-primary)',marginBottom:3 }}>{item.label}</div>
            <div style={{ fontSize:11,color:'var(--text-muted)' }}>{item.desc}</div>
            <div style={{ position:'absolute',bottom:10,right:10,width:6,height:6,borderRadius:'50%',background:item.color,boxShadow:`0 0 8px ${item.color}` }}/>
          </button>
        ))}
      </div>
    </div>
  );
}

