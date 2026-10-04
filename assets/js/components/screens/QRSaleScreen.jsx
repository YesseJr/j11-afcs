// ── QR Sale ───────────────────────────────────────────────────────────────────
// One flat student fare applies system-wide, so there's no route to pick —
// every ticket is simply a Kigamboni student ticket at Tshs 200.
const STUDENT_TICKET = { label:'KIGAMBONI STUDENT TICKET', from:'Kigamboni', to:'All Destinations', price:200 };

function QRSaleScreen({ toast }) {
  const [qty,    setQty]    = useState(1);
  const [cash,   setCash]   = useState('');
  const [loading,setLoading]= useState(false);
  const [result, setResult] = useState(null); // { tickets, change }
  const canvasRefs = useRef([]);

  const total  = STUDENT_TICKET.price * qty;
  const change = parseFloat(cash||0) - total;

  const sell = async () => {
    setLoading(true);
    const res = await api('qr','sell',{
      route: STUDENT_TICKET.label, quantity: qty, price: STUDENT_TICKET.price, cash_received: parseFloat(cash||0)
    }, { idempotent: true });
    setLoading(false);
    if (!res.success) { toast(res.message,'error'); return; }
    setResult(res.data);
    toast(`${qty} QR ticket(s) issued!`,'ok');
  };

  // Draw QR codes after result is set
  useEffect(() => {
    if (!result) return;
    result.tickets.forEach((t,i) => {
      const canvas = canvasRefs.current[i];
      if (canvas && window.QRCode) {
        // Use full payload so gate gets all station info from QR scan
        const qrData = t.qr_payload || t.ticket_code;
        QRCode.toCanvas(canvas, qrData, { width:180, margin:1, color:{ dark:'#000', light:'#fff' } });
      }
    });
  }, [result]);

  if (result) return (
    <div className="fade-in" style={{ flex:1,overflowY:'auto',padding:'24px' }}>
      <div style={{ display:'flex',justifyContent:'space-between',alignItems:'center',marginBottom:20 }}>
        <div>
          <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:22,color:'var(--success)' }}>✅ {result.quantity} Ticket{result.quantity>1?'s':''} Issued</div>
          <div style={{ fontSize:13,color:'var(--text-muted)',marginTop:2 }}>Kigamboni Student Ticket — Tshs {fmtMoney(result.tickets[0].price)} each</div>
        </div>
        <div style={{ display:'flex',gap:10 }}>
          <button className="btn-ghost" onClick={()=>window.print()} style={{ padding:'10px 18px',fontSize:13 }}>🖨️ Print</button>
          <button className="btn-primary" onClick={()=>{setResult(null);setCash('');setQty(1);}} style={{ padding:'10px 18px',fontSize:13 }}>New Sale</button>
        </div>
      </div>
      <div style={{ display:'flex',gap:16,flexWrap:'wrap' }}>
        {result.tickets.map((t,i)=>(
          <div key={t.ticket_code} style={{ background:'#fff',borderRadius:16,padding:'14px',textAlign:'center',width:230,boxShadow:'0 4px 16px rgba(0,0,0,.12)' }}>
            {/* Header */}
            <div style={{ background:'#0d2035',borderRadius:10,padding:'8px 10px',marginBottom:10,display:'flex',alignItems:'center',justifyContent:'space-between' }}>
              <span style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:11,color:'#00d4b4',letterSpacing:1 }}>🚇 J11 AFCS</span>
              <span style={{ fontSize:9,color:'#6b82a0',fontFamily:'monospace' }}>STUDENT · {i+1}/{result.tickets.length}</span>
            </div>
            {/* Route */}
            <div style={{ marginBottom:8,padding:'6px 8px',background:'#f0f9ff',borderRadius:8 }}>
              <div style={{ fontSize:10,color:'#666',marginBottom:2 }}>VALID FROM</div>
              <div style={{ fontWeight:700,fontSize:12,color:'#0d2035' }}>{t.from||'Kigamboni'}</div>
              <div style={{ fontSize:11,color:'#888' }}>→ {t.to||'All Destinations'}</div>
            </div>
            {/* QR Code */}
            <canvas ref={el=>canvasRefs.current[i]=el} style={{ borderRadius:6 }}/>
            {/* Ticket code */}
            <div style={{ fontFamily:'monospace',fontSize:7,color:'#999',marginTop:4,wordBreak:'break-all',padding:'0 4px' }}>{t.ticket_code}</div>
            {/* Details */}
            <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr',gap:4,marginTop:8 }}>
              <div style={{ background:'#f0fdf4',borderRadius:6,padding:'5px',fontSize:10,color:'#16a34a',fontWeight:700 }}>Tshs {fmtMoney(t.price)}</div>
              <div style={{ background:'#fef3c7',borderRadius:6,padding:'5px',fontSize:9,color:'#92400e' }}>Valid 24h</div>
            </div>
            <div style={{ fontSize:8,color:'#aaa',marginTop:6 }}>Expires: {fmtDate(t.expires_at)}</div>
          </div>
        ))}
      </div>
      {result.change > 0 && (
        <div style={{ marginTop:20,background:'rgba(0,230,118,.1)',border:'1.5px solid var(--success)',borderRadius:14,padding:'14px 20px',fontSize:14,color:'var(--success)' }}>
          💵 Change to return: <strong style={{ fontFamily:"'DM Mono',monospace" }}>Tshs {fmtMoney(result.change)}</strong>
        </div>
      )}
    </div>
  );

  return (
    <div className="fade-in" style={{ flex:1,display:'flex',gap:20,padding:'24px',overflow:'hidden' }}>
      {/* Left: summary */}
      <div style={{ flex:1,display:'flex',flexDirection:'column',gap:14,minHeight:0 }}>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:18,color:'var(--text-primary)' }}>QR Student Tickets</div>
        <div style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,padding:'18px 20px' }}>
          <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:'10px',marginBottom:14 }}>
            {[['Type','Student (QR)'],['Terminal',STUDENT_TICKET.from.toUpperCase()],['Fare','Flat — any dest.']].map(([k,v])=>(
              <div key={k}><div style={{ fontSize:10,color:'var(--text-dim)',marginBottom:3 }}>{k}</div><div style={{ fontSize:13,fontWeight:600,color:'var(--text-primary)' }}>{v}</div></div>
            ))}
          </div>
          <div style={{ borderTop:'1px solid var(--border)',paddingTop:14,display:'flex',justifyContent:'space-between',alignItems:'center' }}>
            <div>
              <div style={{ fontSize:11,color:'var(--text-dim)',marginBottom:6 }}>Quantity</div>
              <div style={{ display:'flex',alignItems:'center',gap:10 }}>
                <button onClick={()=>setQty(Math.max(1,qty-1))} style={{ width:28,height:28,borderRadius:8,background:'var(--bg-deep)',border:'1px solid var(--border)',color:'var(--text-muted)',cursor:'pointer',fontSize:16 }}>−</button>
                <span style={{ fontFamily:"'DM Mono',monospace",fontSize:20,color:'var(--text-primary)',width:32,textAlign:'center' }}>{qty}</span>
                <button onClick={()=>setQty(Math.min(20,qty+1))} style={{ width:28,height:28,borderRadius:8,background:'var(--bg-deep)',border:'1px solid var(--border)',color:'var(--text-muted)',cursor:'pointer',fontSize:16 }}>+</button>
                <span style={{ fontSize:11,color:'var(--text-dim)' }}>ticket{qty>1?'s':''} @ Tshs {STUDENT_TICKET.price}</span>
              </div>
            </div>
            <div style={{ textAlign:'right' }}>
              <div style={{ fontSize:11,color:'var(--text-dim)' }}>Total</div>
              <div style={{ fontFamily:"'DM Mono',monospace",fontSize:30,fontWeight:500,color:'var(--accent-blue)' }}>{fmtMoney(total)}</div>
            </div>
          </div>
        </div>
        <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:12,background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:16,padding:'14px 20px' }}>
          {[['Cash Received',cash?`Tshs ${fmtMoney(parseFloat(cash))}`:'— (optional)','var(--accent-amber)'],['Total Charge',`Tshs ${fmtMoney(total)}`,'var(--text-primary)'],['Change',cash&&change>0?`Tshs ${fmtMoney(change)}`:cash&&change<0?'Insufficient':'—',cash&&change>=0?'var(--success)':cash&&change<0?'var(--accent-red)':'var(--text-muted)']].map(([l,v,c])=>(
            <div key={l} style={{ textAlign:'center' }}><div style={{ fontSize:11,color:'var(--text-dim)',marginBottom:6 }}>{l}</div><div style={{ fontFamily:"'DM Mono',monospace",fontSize:16,color:c }}>{v}</div></div>
          ))}
        </div>
        <button className="btn-primary" onClick={sell} disabled={loading} style={{ padding:'16px',fontSize:15,letterSpacing:1 }}>
          {loading?<><Spinner/> ISSUING...</>:`⬛  ISSUE ${qty} QR TICKET${qty>1?'S':''}`}
        </button>
      </div>

      {/* Right: cash numpad */}
      <div style={{ width:300,display:'flex',flexDirection:'column',gap:14,minHeight:0 }}>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:11,color:'var(--text-dim)',letterSpacing:2,textTransform:'uppercase' }}>Cash Received</div>
        <div style={{ background:'var(--bg-deep)',border:'1.5px solid var(--border)',borderRadius:12,padding:'12px 16px' }}>
          <div style={{ fontSize:11,color:'var(--text-dim)',marginBottom:4 }}>Tshs</div>
          <div style={{ fontFamily:"'DM Mono',monospace",fontSize:24,color:'var(--accent-amber)',minHeight:34 }}>{cash||<span style={{color:'var(--text-dim)'}}>0</span>}</div>
        </div>
        <Numpad value={cash} onChange={setCash}/>
        <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:8 }}>
          {[200,500,1000,2000,5000,10000].map(a=>(
            <button key={a} className="amount-chip" onClick={()=>setCash(String(a))} style={{ padding:'10px 2px',fontSize:12,whiteSpace:'nowrap' }}>{a.toLocaleString()}</button>
          ))}
          <button className="amount-chip" onClick={()=>setCash('')} style={{ color:'var(--accent-red)',gridColumn:'1/-1' }}>Clear</button>
        </div>
      </div>
    </div>
  );
}

