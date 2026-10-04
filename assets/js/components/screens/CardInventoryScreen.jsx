// ── Card Inventory (Admin) ────────────────────────────────────────────────────
// Top-level management for the physical card stock. Cashiers can only pull
// blank cards out of inventory (Card Sale screen); only an admin can put more
// in. This screen is gated in App.jsx so non-admins never see it.
function CardInventoryScreen({ toast, user }) {
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);
  const [startNo, setStartNo] = useState('994160000026');
  const [qty, setQty]         = useState(20);
  const [adding, setAdding]   = useState(false);

  const load = useCallback(async (silent=false) => {
    const res = await apiGet('cards','admin_inventory');
    setLoading(false);
    if (res.success) setData(res.data);
    else if (!silent) toast(res.message,'error');
  }, []);

  useEffect(() => {
    load();
    const t = setInterval(()=>load(true), 5000); // live sync with other admins/terminals
    return () => clearInterval(t);
  }, [load]);

  const addBlankCards = async () => {
    if (!/^\d+$/.test(startNo)) { toast('Start number must be numeric','error'); return; }
    if (qty < 1 || qty > 500)   { toast('Quantity must be between 1 and 500','error'); return; }
    setAdding(true);
    const res = await api('cards','admin_add_blank_cards',{ start_number:startNo, quantity:qty });
    setAdding(false);
    if (!res.success) { toast(res.message,'error'); return; }
    toast(res.message,'ok');
    load(true);
    // bump the start number past what we just added, ready for next batch
    setStartNo(String(parseInt(startNo,10) + qty).padStart(startNo.length,'0'));
  };

  if (user?.role !== 'admin') {
    return (
      <div style={{ flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',gap:12,color:'var(--text-muted)' }}>
        <div style={{ fontSize:48 }}>🔒</div>
        <div>Admin access required.</div>
      </div>
    );
  }

  if (loading) return <div style={{ flex:1,display:'flex',alignItems:'center',justifyContent:'center' }}><Spinner/></div>;
  if (!data)   return null;

  const statusCount = s => (data.by_status.find(x=>x.status===s)?.n) || 0;
  const typeCount    = t => (data.by_type.find(x=>x.card_type===t)?.n) || 0;

  return (
    <div className="fade-in" style={{ flex:1,overflowY:'auto',padding:'24px',display:'flex',flexDirection:'column',gap:20 }}>
      <div style={{ display:'flex',justifyContent:'space-between',alignItems:'center' }}>
        <div>
          <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:22,color:'var(--text-primary)' }}>Card Inventory</div>
          <div style={{ fontSize:13,color:'var(--text-muted)',marginTop:2,display:'flex',alignItems:'center',gap:8 }}>
            <span>Admin-only stock management</span>
            <span style={{ display:'flex',alignItems:'center',gap:5,fontSize:11,color:'var(--success)' }}>
              <span className="pulse-dot" style={{ width:6,height:6,borderRadius:'50%',background:'var(--success)',boxShadow:'0 0 6px var(--success)' }}/>
              LIVE
            </span>
          </div>
        </div>
      </div>

      {/* Stat cards */}
      <div style={{ display:'grid',gridTemplateColumns:'repeat(5,1fr)',gap:14 }}>
        {[
          {label:'Blank (unregistered)', value:statusCount('new'),      color:'var(--accent-teal)'},
          {label:'Active',               value:statusCount('active'),  color:'var(--success)'},
          {label:'Inactive',             value:statusCount('inactive'),color:'var(--accent-red)'},
          {label:'Adult cards',          value:typeCount('Adult'),     color:'#4a9eff'},
          {label:'Staff cards',          value:typeCount('Staff'),     color:'#a78bfa'},
        ].map(s=>(
          <div key={s.label} style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,padding:'18px',borderTop:`3px solid ${s.color}` }}>
            <div style={{ fontSize:11,color:'var(--text-muted)',textTransform:'uppercase',letterSpacing:1,marginBottom:8 }}>{s.label}</div>
            <div style={{ fontFamily:"'DM Mono',monospace",fontSize:26,fontWeight:500,color:s.color }}>{s.value}</div>
          </div>
        ))}
      </div>

      <div style={{ display:'flex',gap:20,flex:1,minHeight:0 }}>
        {/* Add blank cards */}
        <div style={{ width:340,background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,padding:'22px',display:'flex',flexDirection:'column',gap:16,alignSelf:'flex-start' }}>
          <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:14,color:'var(--text-primary)' }}>Add Blank Cards to Stock</div>
          <div style={{ fontSize:12,color:'var(--text-muted)' }}>Generates sequential unregistered cards that cashiers can pull from on the Card Sale screen.</div>
          <div>
            <label style={{ display:'block',fontSize:11,color:'var(--text-muted)',marginBottom:7,fontWeight:600,letterSpacing:1,textTransform:'uppercase' }}>Starting Card Number</label>
            <input className="input-field" value={startNo} onChange={e=>setStartNo(e.target.value.replace(/\D/g,''))} placeholder="e.g. 994160000026"/>
          </div>
          <div>
            <label style={{ display:'block',fontSize:11,color:'var(--text-muted)',marginBottom:7,fontWeight:600,letterSpacing:1,textTransform:'uppercase' }}>Quantity</label>
            <input className="input-field" type="number" min="1" max="500" value={qty} onChange={e=>setQty(parseInt(e.target.value||'0',10))}/>
          </div>
          <button className="btn-primary" onClick={addBlankCards} disabled={adding} style={{ padding:'14px',fontSize:14 }}>
            {adding?<><Spinner/> ADDING...</>:`+ ADD ${qty||0} BLANK CARD${qty===1?'':'S'}`}
          </button>
        </div>

        {/* Current blank stock list */}
        <div style={{ flex:1,background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,padding:'22px',display:'flex',flexDirection:'column',gap:12,minHeight:0 }}>
          <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:14,color:'var(--text-primary)' }}>Unregistered Cards in Stock ({data.blank_cards.length})</div>
          <div style={{ flex:1,overflowY:'auto',display:'grid',gridTemplateColumns:'repeat(3,1fr)',gap:8,alignContent:'start' }}>
            {data.blank_cards.length===0 && <div style={{ color:'var(--text-dim)',fontSize:13 }}>No blank cards in stock — add some above.</div>}
            {data.blank_cards.map(c=>(
              <div key={c.card_number} style={{ background:'var(--bg-deep)',border:'1px solid var(--border)',borderRadius:8,padding:'8px 10px',fontFamily:"'DM Mono',monospace",fontSize:12,color:'var(--accent-teal)' }}>
                {c.card_number}
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
