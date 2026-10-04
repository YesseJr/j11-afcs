// ── Card Details ──────────────────────────────────────────────────────────────
function CardDetailsScreen({ toast }) {
  const [cardData,setCardData]=useState(null);
  const handleRead=(no,card)=>setCardData({no,card});

  // Live sync: if this card gets topped up, activated, or flagged from a
  // different screen or terminal while it's open here, reflect it automatically.
  useEffect(() => {
    if (!cardData) return;
    const t = setInterval(async () => {
      const res = await apiGet('cards','lookup',{ card_number: cardData.no });
      if (res.success) setCardData(p => p ? { ...p, card: res.data } : p);
    }, 5000);
    return () => clearInterval(t);
  }, [cardData?.no]);
  return (
    <div className="fade-in" style={{ flex:1,display:'flex',gap:20,padding:'24px',overflow:'hidden' }}>
      <div style={{ width:300,display:'flex',flexDirection:'column',gap:16 }}>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:18,color:'var(--text-primary)',display:'flex',alignItems:'center',gap:8 }}>
          Card Details
          {cardData && <span style={{ display:'flex',alignItems:'center',gap:5,fontSize:10,color:'var(--success)',fontFamily:"'DM Mono',monospace",fontWeight:400 }}>
            <span className="pulse-dot" style={{ width:6,height:6,borderRadius:'50%',background:'var(--success)',boxShadow:'0 0 6px var(--success)' }}/>LIVE
          </span>}
        </div>
        <div style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:20,padding:'28px 24px',flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center' }}>
          {!cardData
            ? <CardReader onCardRead={handleRead} allowedStatuses={['active','inactive','new']} label="Tap any card to view"/>
            : <div style={{ width:'100%',display:'flex',flexDirection:'column',gap:12 }}>
                <CardProfile cardNo={cardData.no} card={cardData.card}/>
                <button className="btn-ghost" onClick={()=>setCardData(null)} style={{ padding:'11px',fontSize:13 }}>↩ Check Another</button>
              </div>
          }
        </div>
      </div>
      <div style={{ flex:1,display:'flex',flexDirection:'column',gap:16,overflowY:'auto' }}>
        {!cardData&&<div style={{ flex:1,display:'flex',alignItems:'center',justifyContent:'center',flexDirection:'column',gap:12,color:'var(--text-dim)' }}><div style={{fontSize:56}}>🔍</div><div>Tap a card to view its full details</div></div>}
        {cardData&&(
          <div className="slide-up" style={{ display:'flex',flexDirection:'column',gap:16 }}>
            {/* Full details */}
            <div style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:20,padding:'24px' }}>
              <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:13,color:'var(--text-dim)',letterSpacing:2,textTransform:'uppercase',marginBottom:20 }}>Holder Information</div>
              <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr',gap:'14px 20px' }}>
                {[['Full Name',cardData.card.holder_name||'—'],['Phone',cardData.card.phone||'—'],['Date of Birth',cardData.card.dob||'—'],['Gender',cardData.card.gender==='M'?'Male':cardData.card.gender==='F'?'Female':'—'],['Card Type',cardData.card.card_type||'—'],['Registered',fmtDate(cardData.card.registered_at)],['Penalty Flag',cardData.card.penalty_flag?'⚠️ FLAGGED':'None'],['Registered By',cardData.card.registered_by_name||'—']].map(([k,v])=>(
                  <div key={k}><div style={{ fontSize:11,color:'var(--text-dim)',marginBottom:4 }}>{k}</div><div style={{ fontSize:14,fontWeight:500,color:'var(--text-primary)' }}>{v}</div></div>
                ))}
              </div>
            </div>
            {/* Recent transactions */}
            {cardData.card.recent_transactions?.length>0&&(
              <div style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:20,padding:'24px' }}>
                <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:13,color:'var(--text-dim)',letterSpacing:2,textTransform:'uppercase',marginBottom:16 }}>Recent Transactions</div>
                {cardData.card.recent_transactions.map((t,i)=>(
                  <div key={i} style={{ display:'flex',justifyContent:'space-between',alignItems:'center',padding:'10px 0',borderBottom:i<cardData.card.recent_transactions.length-1?'1px solid var(--border)':'none' }}>
                    <div><div style={{ fontSize:13,color:'var(--text-primary)',fontWeight:500,textTransform:'capitalize' }}>{t.type.replace('_',' ')}</div><div style={{ fontSize:11,color:'var(--text-dim)' }}>{fmtDate(t.created_at)}</div></div>
                    <div style={{ fontFamily:"'DM Mono',monospace",fontSize:14,color:t.amount>0?'var(--accent-teal)':t.amount<0?'var(--accent-red)':'var(--text-muted)' }}>
                      {t.amount>0?'+':''}{t.amount?`Tshs ${fmtMoney(t.amount)}`:'—'}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

