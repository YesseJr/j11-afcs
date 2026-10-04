function Numpad({ value, onChange, disabled }) {
  const keys = ['1','2','3','4','5','6','7','8','9','.','0','⌫'];
  return (
    <div style={{ display:'grid',gridTemplateColumns:'repeat(3,1fr)',gap:8 }}>
      {keys.map(k => (
        <button key={k} className="numpad-btn" onClick={() => {
          if (disabled) return;
          if (k==='⌫') onChange(value.slice(0,-1));
          else if (k==='.' && value.includes('.')) return;
          else onChange(value + k);
        }} style={{ color:k==='⌫'?'var(--accent-red)':undefined, opacity:disabled?.4:1 }}>{k}</button>
      ))}
    </div>
  );
}

