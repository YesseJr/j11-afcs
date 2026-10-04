// ── Login Report ──────────────────────────────────────────────────────────────
function LoginReportScreen({ toast }) {
  const [logs,setLogs]=useState([]);
  const [loading,setLoading]=useState(true);
  useEffect(()=>{
    apiGet('reports','login_log').then(res=>{
      setLoading(false);
      if(res.success) setLogs(res.data);
      else toast(res.message,'error');
    });
  },[]);

  return (
    <div className="fade-in" style={{ flex:1,overflowY:'auto',padding:'24px' }}>
      <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:22,color:'var(--text-primary)',marginBottom:20 }}>Login / Logout Report</div>
      {loading?<div style={{ color:'var(--text-muted)',display:'flex',gap:10 }}><Spinner/> Loading...</div>:(
        <div style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,overflow:'hidden' }}>
          <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr 1.2fr 2fr 2fr',background:'linear-gradient(135deg,#0d2035,#111d2e)',padding:'12px 20px',gap:8,fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:10,color:'var(--accent-teal)',letterSpacing:1,textTransform:'uppercase' }}>
            {['First Name','Last Name','Username','Login Time','Logout Time'].map(h=><div key={h}>{h}</div>)}
          </div>
          {logs.length===0&&<div style={{ padding:'24px',textAlign:'center',color:'var(--text-dim)',fontSize:13 }}>No login records found.</div>}
          {logs.map((l,i)=>(
            <div key={i} className="table-row" style={{ display:'grid',gridTemplateColumns:'1fr 1fr 1.2fr 2fr 2fr',padding:'14px 20px',gap:8,background:i%2===0?'var(--bg-card)':'var(--bg-panel)',borderTop:'1px solid var(--border)' }}>
              <div style={{fontSize:13,color:'var(--text-primary)',fontWeight:600}}>{l.first_name}</div>
              <div style={{fontSize:13,color:'var(--text-primary)'}}>{l.last_name}</div>
              <div style={{fontFamily:"'DM Mono',monospace",fontSize:12,color:'var(--accent-blue)'}}>{l.username}</div>
              <div style={{fontFamily:"'DM Mono',monospace",fontSize:11,color:'var(--text-muted)'}}>{fmtDate(l.login_time)}</div>
              <div style={{fontFamily:"'DM Mono',monospace",fontSize:11,color:l.logout_time?'var(--text-muted)':'var(--success)'}}>{l.logout_time?fmtDate(l.logout_time):'● Active'}</div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

