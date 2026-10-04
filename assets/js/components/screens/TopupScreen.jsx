// ── Topup ─────────────────────────────────────────────────────────────────────
function TopupScreen({ toast, onStatsUpdate }) {
  const [cardData,setCardData]=useState(null);
  const [amount,setAmount]=useState('');
  const [loading,setLoading]=useState(false);
  const [success,setSuccess]=useState(null);

  const handleRead=(no,card)=>setCardData({no,card});

  const doTopup = async () => {
    if (!cardData||!amount) return;
    setLoading(true);
    const res = await api('cards','topup',{ card_number:cardData.no, amount:parseFloat(amount) }, { idempotent: true });
    setLoading(false);
    if (!res.success) { toast(res.message,'error'); return; }
    setSuccess(res.data);
    setCardData(p=>({...p,card:{...p.card,balance:res.data.new_balance}}));
    setAmount('');
    onStatsUpdate();
    setTimeout(()=>setSuccess(null),6000);
  };

  return (
    <div className="fade-in" style={{ flex:1,display:'flex',gap:20,padding:'24px',overflow:'hidden' }}>
      <div style={{ width:300,display:'flex',flexDirection:'column',gap:16 }}>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:18,color:'var(--text-primary)' }}>Card Top-Up</div>
        <div style={{ background:'var(--bg-card)',border:`1px solid ${cardData?'var(--accent-teal)':'var(--border)'}`,borderRadius:20,padding:'28px 24px',flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',transition:'border-color .3s' }}>
          {!cardData
            ? <CardReader onCardRead={handleRead} allowedStatuses={['active']} blockedTypes={['Staff']} blockedMessage="Staff cards can't be topped up — unlimited service access" label="Tap card to load balance"/>
            : <div style={{ width:'100%',display:'flex',flexDirection:'column',gap:12 }}>
                <CardProfile cardNo={cardData.no} card={cardData.card}/>
                <button className="btn-ghost" onClick={()=>{setCardData(null);setAmount('');setSuccess(null);}} style={{ padding:'11px',fontSize:13 }}>↩ Scan Different Card</button>
              </div>
          }
        </div>
      </div>

      <div style={{ flex:1,display:'flex',flexDirection:'column',gap:16,minHeight:0,overflowY:'auto' }}>
        {/* Steps */}
        <div style={{ display:'flex',alignItems:'center',gap:0 }}>
          {[{n:1,l:'Scan Card'},{n:2,l:'Enter Amount'},{n:3,l:'Done'}].map((s,i)=>{
            const active = cardData ? success ? 3 : 2 : 1;
            return (
              <div key={s.n} style={{ display:'flex',alignItems:'center' }}>
                <div style={{ display:'flex',alignItems:'center',gap:8 }}>
                  <div style={{ width:28,height:28,borderRadius:'50%',background:s.n<=active?'var(--accent-teal)':'var(--bg-card)',border:`2px solid ${s.n<=active?'var(--accent-teal)':'var(--border)'}`,display:'flex',alignItems:'center',justifyContent:'center',fontSize:11,fontWeight:700,color:s.n<=active?'#070c14':'var(--text-dim)',transition:'all .3s' }}>
                    {s.n<active?'✓':s.n}
                  </div>
                  <span style={{ fontSize:12,color:s.n<=active?'var(--text-primary)':'var(--text-dim)',fontWeight:s.n===active?600:400 }}>{s.l}</span>
                </div>
                {i<2&&<div style={{ width:32,height:2,background:s.n<active?'var(--accent-teal)':'var(--border)',margin:'0 10px',transition:'background .3s' }}/>}
              </div>
            );
          })}
        </div>

        {success&&(
          <div className="slide-up" style={{ background:'linear-gradient(135deg,rgba(0,230,118,.12),rgba(0,212,180,.08))',border:'1.5px solid var(--success)',borderRadius:20,padding:'22px 28px',display:'flex',justifyContent:'space-between',alignItems:'center' }}>
            <div>
              <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:20,color:'var(--success)',marginBottom:4 }}>✓ Topup Successful!</div>
              <div style={{ fontSize:13,color:'var(--text-muted)' }}>Added <span style={{ color:'var(--accent-amber)',fontFamily:"'DM Mono',monospace" }}>Tshs {fmtMoney(success.amount_added)}</span> — receipt printed</div>
            </div>
            <div style={{ textAlign:'right' }}>
              <div style={{ fontSize:11,color:'var(--text-dim)' }}>New Balance</div>
              <div style={{ fontFamily:"'DM Mono',monospace",fontSize:28,fontWeight:500,color:'var(--success)' }}>Tshs {fmtMoney(success.new_balance)}</div>
            </div>
          </div>
        )}

        <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr',gap:14,flex:1 }}>
          <div style={{ display:'flex',flexDirection:'column',gap:14 }}>
            <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:11,color:'var(--text-dim)',letterSpacing:2,textTransform:'uppercase' }}>Enter Amount</div>
            <div style={{ background:'var(--bg-deep)',border:`1.5px solid ${cardData?'var(--accent-amber)':'var(--border)'}`,borderRadius:12,padding:'16px 18px',transition:'border-color .3s' }}>
              <div style={{ fontSize:11,color:'var(--text-dim)',marginBottom:6 }}>Amount (Tshs)</div>
              <div style={{ fontFamily:"'DM Mono',monospace",fontSize:32,color:'var(--accent-amber)',minHeight:40 }}>{amount||<span style={{color:'var(--text-dim)'}}>0</span>}</div>
            </div>
            <Numpad value={amount} onChange={v=>cardData&&setAmount(v)} disabled={!cardData}/>
          </div>
          <div style={{ display:'flex',flexDirection:'column',gap:14 }}>
            <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:11,color:'var(--text-dim)',letterSpacing:2,textTransform:'uppercase' }}>Quick Amounts</div>
            <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr',gap:10,flex:1 }}>
              {[500,1000,2000,3000,5000,10000].map(a=>(
                <button key={a} className="amount-chip" onClick={()=>cardData&&setAmount(String(a))} style={{ aspectRatio:'auto',padding:'14px 8px',opacity:cardData?1:.4 }}>
                  <div style={{ fontSize:13,fontWeight:600 }}>{a.toLocaleString()}</div>
                  <div style={{ fontSize:10,color:'var(--text-dim)',marginTop:2 }}>Tshs</div>
                </button>
              ))}
            </div>
            <button className="btn-primary" onClick={doTopup} disabled={!cardData||!amount||loading} style={{ padding:'18px',fontSize:15,letterSpacing:1 }}>
              {loading?<><Spinner/> PROCESSING...</>:'TOPUP CARD ↑'}
            </button>
            <button className="btn-ghost" onClick={()=>setAmount('')} style={{ padding:'12px' }}>Clear</button>
          </div>
        </div>
      </div>
    </div>
  );
}

