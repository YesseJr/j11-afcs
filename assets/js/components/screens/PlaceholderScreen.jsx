function PlaceholderScreen({ title, icon, desc }) {
  return (
    <div className="fade-in" style={{ flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',gap:16,padding:40 }}>
      <div style={{fontSize:64}}>{icon}</div>
      <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:24,color:'var(--text-primary)' }}>{title}</div>
      <div style={{ fontSize:14,color:'var(--text-muted)',maxWidth:340,textAlign:'center' }}>{desc}</div>
      <div style={{ background:'rgba(0,212,180,.05)',border:'1px dashed rgba(0,212,180,.2)',borderRadius:12,padding:'12px 24px',fontSize:12,color:'var(--text-dim)' }}>Coming in next iteration</div>
    </div>
  );
}

