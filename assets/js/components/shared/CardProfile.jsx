function CardProfile({ cardNo, card }) {
  const sc = { active:'var(--success)', inactive:'var(--accent-red)', new:'var(--accent-amber)' }[card.status]||'var(--text-muted)';
  const sl = { active:'● ACTIVE', inactive:'● INACTIVE', new:'● UNREGISTERED' }[card.status]||card.status;
  return (
    <div className="slide-up" style={{ background:'linear-gradient(135deg,#0d2035,#0a1826)',border:`1.5px solid ${sc}`,borderRadius:20,padding:'22px',position:'relative',overflow:'hidden' }}>
      <div style={{ position:'absolute',top:0,right:0,width:80,height:80,borderRadius:'0 0 0 80px',background:`${sc}08` }}/>
      <div style={{ fontSize:10,color:'var(--text-dim)',letterSpacing:2,textTransform:'uppercase',marginBottom:12 }}>Card Holder</div>
      <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:18,color:'var(--text-primary)',marginBottom:3 }}>{card.holder_name||'Unregistered'}</div>
      <div style={{ fontFamily:"'DM Mono',monospace",fontSize:12,color:'var(--text-muted)',letterSpacing:2,marginBottom:2 }}>{cardNo}</div>
      {card.phone && <div style={{ fontSize:12,color:'var(--text-muted)',marginBottom:12 }}>📞 {card.phone}</div>}
      {card.card_type && <div style={{ fontSize:11,color:'var(--accent-blue)',marginBottom:12 }}>{card.card_type} Card</div>}
      <div style={{ display:'flex',justifyContent:'space-between',alignItems:'flex-end' }}>
        <div>
          <div style={{ fontSize:10,color:'var(--text-dim)',marginBottom:4 }}>Balance</div>
          <div style={{ fontFamily:"'DM Mono',monospace",fontSize:24,fontWeight:500,color:sc }}>
            {fmtMoney(card.balance)} <span style={{ fontSize:12,color:'var(--text-muted)' }}>Tshs</span>
          </div>
        </div>
        <div style={{ background:`${sc}15`,border:`1px solid ${sc}40`,borderRadius:20,padding:'4px 12px',fontSize:10,color:sc,fontWeight:700 }}>{sl}</div>
      </div>
    </div>
  );
}

