// ── Activate / Deactivate (shared pattern) ────────────────────────────────────
function CardActionScreen({ title, icon, action, allowedStatuses, confirmColor, confirmLabel, reasonPresets, toast, onStatsUpdate }) {
  const [cardData,setCardData]=useState(null);
  const [done,setDone]=useState(false);
  const [reason,setReason]=useState(reasonPresets?.[0]||'');
  const [loading,setLoading]=useState(false);

  const handleRead=(no,card)=>setCardData({no,card});

  const doAction = async () => {
    setLoading(true);
    const res = await api('cards',action,{ card_number:cardData.no, reason });
    setLoading(false);
    if(!res.success){toast(res.message,'error');return;}
    setDone(true);
    if(onStatsUpdate) onStatsUpdate();
    toast(res.message,'ok');
  };

  const reset=()=>{setCardData(null);setDone(false);setReason(reasonPresets?.[0]||'');};

  return (
    <div className="fade-in" style={{ flex:1,display:'flex',gap:20,padding:'24px',overflow:'hidden' }}>
      <div style={{ width:300,display:'flex',flexDirection:'column',gap:16 }}>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:18,color:'var(--text-primary)' }}>{title}</div>
        <div style={{ background:'var(--bg-card)',border:`1px solid ${cardData?confirmColor:'var(--border)'}`,borderRadius:20,padding:'28px 24px',flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',transition:'border-color .3s' }}>
          {!cardData
            ? <CardReader onCardRead={handleRead} allowedStatuses={allowedStatuses} label="Tap card"/>
            : <div style={{ width:'100%',display:'flex',flexDirection:'column',gap:12 }}>
                <CardProfile cardNo={cardData.no} card={cardData.card}/>
                {!done&&<button className="btn-ghost" onClick={reset} style={{ padding:'11px',fontSize:13 }}>↩ Scan Different</button>}
              </div>
          }
        </div>
      </div>
      <div style={{ flex:1,display:'flex',flexDirection:'column',gap:16,minHeight:0,overflowY:'auto' }}>
        {!cardData&&<div style={{ flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',gap:12,color:'var(--text-dim)',textAlign:'center' }}><div style={{fontSize:56}}>{icon}</div><div>Tap a card to proceed</div></div>}
        {cardData&&!done&&(
          <div className="slide-up" style={{ flex:1,display:'flex',flexDirection:'column',gap:16 }}>
            <div style={{ background:`${confirmColor}10`,border:`1.5px solid ${confirmColor}50`,borderRadius:16,padding:'16px 20px' }}>
              <div style={{ fontWeight:700,color:confirmColor,marginBottom:4,fontFamily:"'Syne',sans-serif" }}>Confirm {title}</div>
              <div style={{ fontSize:13,color:'var(--text-muted)' }}>Card <span style={{fontFamily:"'DM Mono',monospace",color:'var(--text-primary)'}}>{cardData.no}</span> belonging to <strong style={{color:'var(--text-primary)'}}>{cardData.card.holder_name}</strong> will be {action==='activate'?'activated':'deactivated'} immediately.</div>
            </div>
            {reasonPresets&&(
              <div>
                <label style={{ display:'block',fontSize:11,color:'var(--text-muted)',marginBottom:8,fontWeight:600,letterSpacing:1,textTransform:'uppercase' }}>Reason</label>
                <div style={{ display:'flex',flexWrap:'wrap',gap:8,marginBottom:10 }}>
                  {reasonPresets.map(r=>(
                    <button key={r} onClick={()=>setReason(r)} style={{ padding:'7px 14px',borderRadius:20,cursor:'pointer',fontSize:12,background:reason===r?`${confirmColor}20`:'var(--bg-card)',color:reason===r?confirmColor:'var(--text-muted)',border:`1px solid ${reason===r?`${confirmColor}60`:'var(--border)'}`,transition:'all .15s' }}>{r}</button>
                  ))}
                </div>
                <input className="input-field" value={reason} onChange={e=>setReason(e.target.value)} placeholder="Custom reason..."/>
              </div>
            )}
            <div style={{ display:'flex',gap:12,marginTop:'auto' }}>
              <button onClick={doAction} disabled={loading} style={{ flex:1,padding:'16px',fontSize:14,letterSpacing:1,background:`linear-gradient(135deg,${confirmColor},${confirmColor}cc)`,color:'#fff',border:'none',borderRadius:12,fontFamily:"'Syne',sans-serif",fontWeight:700,cursor:'pointer',boxShadow:`0 4px 20px ${confirmColor}40` }}>
                {loading?<><Spinner/> PROCESSING...</>:`${icon} ${confirmLabel}`}
              </button>
              <button className="btn-ghost" onClick={reset} style={{ padding:'16px 20px' }}>Cancel</button>
            </div>
          </div>
        )}
        {done&&(
          <div className="slide-up" style={{ flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',gap:20 }}>
            <div style={{fontSize:64}}>{icon}</div>
            <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:26,color:confirmColor }}>{title} Complete</div>
            <div style={{ fontSize:14,color:'var(--text-muted)',textAlign:'center' }}>{cardData.card.holder_name}'s card has been {action==='activate'?'activated':action==='penalty'?'flagged for penalty':'deactivated'}.</div>
            <div style={{ display:'flex',gap:12 }}>
              <button className="btn-primary" onClick={reset} style={{ padding:'14px 28px' }}>Do Another</button>
              <button className="btn-ghost" style={{ padding:'14px 20px' }}>🖨️ Print Receipt</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

