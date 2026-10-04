// ── Settlement & Reconciliation (Admin) ──────────────────────────────────────
function SettlementScreen({ toast }) {
  const [recon, setRecon] = useState(null);
  const [batches, setBatches] = useState([]);
  const [running, setRunning] = useState(false);
  const [settling, setSettling] = useState(false);

  const loadBatches = useCallback(async () => {
    const res = await apiGet('settlement', 'list_settlements');
    if (res.success) setBatches(res.data || []);
  }, []);

  useEffect(() => { loadBatches(); }, [loadBatches]);

  const runRecon = async () => {
    setRunning(true);
    const res = await api('settlement', 'run_reconciliation', {
      period_start: new Date(new Date().setHours(0,0,0,0)).toISOString().slice(0,19).replace('T',' '),
      period_end: new Date().toISOString().slice(0,19).replace('T',' '),
    });
    setRunning(false);
    if (!res.success) { toast(res.message, 'error'); return; }
    setRecon(res.data);
    toast('Reconciliation complete', 'ok');
  };

  const createSettlement = async () => {
    setSettling(true);
    const res = await api('settlement', 'create_settlement', {
      period_start: new Date(Date.now()-86400000).toISOString().slice(0,10) + ' 00:00:00',
      period_end: new Date(Date.now()-86400000).toISOString().slice(0,10) + ' 23:59:59',
    });
    setSettling(false);
    if (!res.success) { toast(res.message, 'error'); return; }
    toast(`Settlement net: ${Number(res.data.total_net).toLocaleString()}`, 'ok');
    loadBatches();
  };

  const card = (label, value, color) => (
    <div style={{ background:'#1a1608', border:'1px solid #3a2f0f', borderRadius:14, padding:'16px 18px' }}>
      <div style={{ fontSize:11, color:'var(--text-muted)', letterSpacing:1, textTransform:'uppercase' }}>{label}</div>
      <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:22, color: color||'var(--text-primary)', marginTop:6 }}>{value}</div>
    </div>
  );

  return (
    <div className="fade-in" style={{ flex:1, overflowY:'auto', padding:'28px', display:'flex', flexDirection:'column', gap:24 }}>
      <div>
        <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:22, color:'var(--text-primary)' }}>Settlement & Reconciliation</div>
        <div style={{ fontSize:13, color:'var(--text-muted)', marginTop:4 }}>Detect mismatches and close daily revenue batches.</div>
      </div>

      <div style={{ display:'flex', gap:12 }}>
        <button className="btn-primary" onClick={runRecon} disabled={running} style={{ padding:'12px 18px' }}>
          {running ? 'Running…' : 'Run today\'s reconciliation'}
        </button>
        <button className="btn-ghost" onClick={createSettlement} disabled={settling} style={{ padding:'12px 18px' }}>
          {settling ? 'Creating…' : 'Create yesterday settlement'}
        </button>
      </div>

      {recon && (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:12 }}>
          {card('QR sales', Number(recon.qr_sales?.total||0).toLocaleString(), 'var(--accent-teal)')}
          {card('Top-ups', Number(recon.topups?.total||0).toLocaleString(), 'var(--accent-amber)')}
          {card('Refunds', Number(recon.refunds?.total||0).toLocaleString(), 'var(--accent-red)')}
          {card('Net revenue', Number(recon.net_revenue||0).toLocaleString(), 'var(--success)')}
          {card('Cash expected', Number(recon.cash?.expected||0).toLocaleString())}
          {card('Cash actual', Number(recon.cash?.actual||0).toLocaleString())}
          {card('Cash variance', Number(recon.cash?.variance||0).toLocaleString(), Math.abs(recon.cash?.variance||0)>0?'var(--accent-amber)':'var(--success)')}
          {card('Ledger net', Number(recon.ledger_net||0).toLocaleString())}
        </div>
      )}

      {recon?.issues?.length > 0 && (
        <div style={{ background:'rgba(255,71,87,.08)', border:'1px solid rgba(255,71,87,.25)', borderRadius:14, padding:16 }}>
          <div style={{ fontWeight:700, color:'var(--accent-red)', marginBottom:8 }}>Issues detected ({recon.issues.length})</div>
          {recon.issues.map((iss, i) => (
            <div key={i} style={{ fontSize:12, color:'var(--text-muted)', marginBottom:6 }}>
              <strong style={{ color:'var(--text-primary)' }}>{iss.type}</strong> — {Array.isArray(iss.items) ? iss.items.length : 0} item(s)
            </div>
          ))}
        </div>
      )}

      <div style={{ background:'var(--bg-panel)', border:'1px solid #3a2f0f', borderRadius:16, overflow:'hidden' }}>
        <div style={{ padding:'14px 16px', borderBottom:'1px solid #3a2f0f', fontWeight:700, color:'var(--accent-amber)', fontSize:12, letterSpacing:1, textTransform:'uppercase' }}>Settlement batches</div>
        <table style={{ width:'100%', borderCollapse:'collapse', fontSize:13 }}>
          <thead>
            <tr style={{ color:'var(--text-muted)', textAlign:'left' }}>
              <th style={{ padding:'10px 16px' }}>UID</th>
              <th style={{ padding:'10px 16px' }}>Period</th>
              <th style={{ padding:'10px 16px' }}>Revenue</th>
              <th style={{ padding:'10px 16px' }}>Refunds</th>
              <th style={{ padding:'10px 16px' }}>Net</th>
              <th style={{ padding:'10px 16px' }}>Status</th>
            </tr>
          </thead>
          <tbody>
            {batches.map(b => (
              <tr key={b.id} style={{ borderTop:'1px solid #2a2410' }}>
                <td style={{ padding:'10px 16px', fontFamily:"'DM Mono',monospace", fontSize:11 }}>{b.batch_uid?.slice(0,8)}…</td>
                <td style={{ padding:'10px 16px', fontSize:12 }}>{b.period_start} → {b.period_end}</td>
                <td style={{ padding:'10px 16px' }}>{Number(b.total_revenue).toLocaleString()}</td>
                <td style={{ padding:'10px 16px' }}>{Number(b.total_refunds).toLocaleString()}</td>
                <td style={{ padding:'10px 16px', color:'var(--success)' }}>{Number(b.total_net).toLocaleString()}</td>
                <td style={{ padding:'10px 16px' }}>{b.status}</td>
              </tr>
            ))}
            {!batches.length && <tr><td colSpan={6} style={{ padding:20, color:'var(--text-muted)' }}>No settlement batches yet</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
