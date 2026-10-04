// ── Session Report ────────────────────────────────────────────────────────────
function SessionReportScreen({ toast }) {
  const [data,setData]=useState(null);
  const [loading,setLoading]=useState(true);

  const load = useCallback(async (silent=false) => {
    const res = await apiGet('reports','session');
    setLoading(false);
    if(res.success) setData(res.data);
    else if(!silent) toast(res.message,'error');
  }, []);

  useEffect(()=>{
    load();
    // Live sync: pick up transactions made from this or any other terminal
    // without the cashier having to reopen the screen.
    const t = setInterval(()=>load(true), 5000);
    return () => clearInterval(t);
  },[load]);

  if (loading) return <div style={{ flex:1,display:'flex',alignItems:'center',justifyContent:'center',color:'var(--text-muted)',gap:12 }}><Spinner/> Loading session data...</div>;
  if (!data) return null;

  const s = data.session;
  return (
    <div className="fade-in" style={{ flex:1,overflowY:'auto',padding:'24px' }}>
      <div style={{ display:'flex',justifyContent:'space-between',alignItems:'center',marginBottom:24 }}>
        <div>
          <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:22,color:'var(--text-primary)' }}>Session Report</div>
          <div style={{ fontSize:13,color:'var(--text-muted)',marginTop:2,display:'flex',alignItems:'center',gap:8 }}>
            <span>Started: {fmtDate(s.started_at)} — {s.terminal}</span>
            <span style={{ display:'flex',alignItems:'center',gap:5,fontSize:11,color:'var(--success)' }}>
              <span className="pulse-dot" style={{ width:6,height:6,borderRadius:'50%',background:'var(--success)',boxShadow:'0 0 6px var(--success)' }}/>
              LIVE
            </span>
          </div>
        </div>
        <button className="btn-primary" onClick={()=>window.print()} style={{ padding:'12px 24px',fontSize:13 }}>🖨️ Print</button>
      </div>

      {/* Summary cards */}
      <div style={{ display:'grid',gridTemplateColumns:'repeat(4,1fr)',gap:14,marginBottom:24 }}>
        {[
          {label:'QR Tickets Sold',value:data.qr_sales.count,icon:'🎫',color:'#4a9eff'},
          {label:'QR Revenue',value:`Tshs ${fmtMoney(data.qr_sales.total)}`,icon:'💵',color:'#00d4b4'},
          {label:'Topups Done',value:data.topups.count,icon:'💳',color:'#ffb830'},
          {label:'Topup Revenue',value:`Tshs ${fmtMoney(data.topups.total)}`,icon:'💰',color:'#a78bfa'},
        ].map(c=>(
          <div key={c.label} style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,padding:'20px',borderTop:`3px solid ${c.color}` }}>
            <div style={{ display:'flex',justifyContent:'space-between',marginBottom:10 }}><span style={{ fontSize:11,color:'var(--text-muted)',textTransform:'uppercase',letterSpacing:1 }}>{c.label}</span><span style={{fontSize:18}}>{c.icon}</span></div>
            <div style={{ fontFamily:"'DM Mono',monospace",fontSize:20,fontWeight:500,color:c.color }}>{c.value}</div>
          </div>
        ))}
      </div>

      {/* Grand total */}
      <div style={{ background:'linear-gradient(135deg,rgba(0,212,180,.1),rgba(0,168,150,.05))',border:'1.5px solid var(--accent-teal)',borderRadius:16,padding:'20px 28px',marginBottom:24,display:'flex',justifyContent:'space-between',alignItems:'center' }}>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:18,color:'var(--text-primary)' }}>Session Grand Total</div>
        <div style={{ fontFamily:"'DM Mono',monospace",fontSize:32,fontWeight:500,color:'var(--accent-teal)' }}>Tshs {fmtMoney(data.grand_total)}</div>
      </div>

      {/* Transactions */}
      <div style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,overflow:'hidden' }}>
        <div style={{ display:'grid',gridTemplateColumns:'2fr 1fr 1fr 1fr 2fr',background:'linear-gradient(135deg,#0d2035,#111d2e)',padding:'12px 20px',gap:8,fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:10,color:'var(--accent-teal)',letterSpacing:1,textTransform:'uppercase' }}>
          {['Type','Amount','Qty','Card','Time'].map(h=><div key={h}>{h}</div>)}
        </div>
        {data.transactions.length===0&&<div style={{ padding:'24px',textAlign:'center',color:'var(--text-dim)',fontSize:13 }}>No transactions this session yet.</div>}
        {data.transactions.map((t,i)=>(
          <div key={i} className="table-row" style={{ display:'grid',gridTemplateColumns:'2fr 1fr 1fr 1fr 2fr',padding:'13px 20px',gap:8,background:i%2===0?'var(--bg-card)':'var(--bg-panel)',borderTop:'1px solid var(--border)' }}>
            <div style={{ fontSize:13,color:'var(--text-primary)',fontWeight:500,textTransform:'capitalize' }}>{t.type.replace('_',' ')}</div>
            <div style={{ fontFamily:"'DM Mono',monospace",fontSize:13,color:t.amount>0?'var(--accent-teal)':'var(--text-muted)' }}>{t.amount?`${fmtMoney(t.amount)}`:'—'}</div>
            <div style={{ fontSize:13,color:'var(--text-muted)' }}>{t.quantity}</div>
            <div style={{ fontFamily:"'DM Mono',monospace",fontSize:11,color:'var(--accent-blue)' }}>{t.card_number||'—'}</div>
            <div style={{ fontFamily:"'DM Mono',monospace",fontSize:11,color:'var(--text-dim)' }}>{fmtDate(t.created_at)}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

