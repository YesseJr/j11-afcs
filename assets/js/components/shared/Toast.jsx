function Toast({ msg, type, onClose }) {
  useEffect(() => { if (msg) { const t = setTimeout(onClose, 4000); return () => clearTimeout(t); } }, [msg]);
  if (!msg) return null;
  const bg = type === 'error' ? 'rgba(255,71,87,.15)' : 'rgba(0,212,180,.12)';
  const bc = type === 'error' ? 'rgba(255,71,87,.4)' : 'rgba(0,212,180,.4)';
  const col = type === 'error' ? 'var(--accent-red)' : 'var(--accent-teal)';
  return (
    <div className="slide-up" style={{ position:'fixed',bottom:24,right:24,zIndex:9999,background:bg,border:`1.5px solid ${bc}`,borderRadius:14,padding:'14px 20px',maxWidth:340,boxShadow:'0 16px 40px rgba(0,0,0,.5)' }}>
      <div style={{ display:'flex',justifyContent:'space-between',alignItems:'flex-start',gap:12 }}>
        <div style={{ color:col,fontSize:13,fontWeight:500 }}>{type==='error'?'⚠️':'✅'} {msg}</div>
        <button onClick={onClose} style={{ background:'none',border:'none',color:'var(--text-dim)',cursor:'pointer',fontSize:16,lineHeight:1 }}>×</button>
      </div>
    </div>
  );
}

