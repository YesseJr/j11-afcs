// ── End Session Modal (with cash count) ───────────────────────────────────────
function EndSessionModal({ onConfirm, onCancel }) {
  const [step,    setStep]    = useState('confirm'); // confirm | report | cash | done
  const [report,  setReport]  = useState(null);
  const [loading, setLoading] = useState(false);
  const [actualCash, setActualCash] = useState('');
  const [cashResult, setCashResult] = useState(null);
  const [ending, setEnding] = useState(false);

  const fetchAndShow = async () => {
    setLoading(true);
    const res = await apiGet('reports', 'session');
    setLoading(false);
    if (!res.success) return;
    setReport(res.data);
    setStep('report');
  };

  const goCashCount = () => setStep('cash');

  const confirmEnd = async () => {
    setEnding(true);
    // Record cash count if provided
    if (actualCash !== '' && !isNaN(parseFloat(actualCash))) {
      const cashRes = await api('finance', 'close_cash_count', {
        actual_cash: parseFloat(actualCash),
      });
      if (cashRes.success) setCashResult(cashRes.data);
    }

    const res = await api('auth', 'end_session', {});
    if (res.success && res.data.ended_session_id) {
      const r = await apiGet('reports', 'session_by_id', { session_id: res.data.ended_session_id });
      if (r.success) {
        setReport(r.data);
        setTimeout(() => window.print(), 600);
      }
    }
    setEnding(false);
    setStep('done');
    await onConfirm();
  };

  const fmtMon = n => Number(n||0).toLocaleString();
  const expectedFromReport = report
    ? Number(report.qr_sales?.total || 0) + Number(report.topups?.total || 0)
    : 0;

  return (
    <>
      <style>{`
        @media print {
          body > * { display: none !important; }
          #print-root { display: block !important; }
        }
        #print-root { display: none; }
      `}</style>
      {report && (
        <div id="print-root">
          <SessionReportPrint report={report}/>
        </div>
      )}

      <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,.75)', display:'flex', alignItems:'center', justifyContent:'center', zIndex:9999, backdropFilter:'blur(4px)' }}>
        <div className="slide-up" style={{ background:'var(--bg-panel)', border:'1px solid var(--border-bright)', borderRadius:24, padding:'36px 40px', width: step==='report'||step==='cash' ? 560 : 440, maxHeight:'88vh', overflowY:'auto', boxShadow:'0 40px 80px rgba(0,0,0,.7)' }}>

          {step === 'confirm' && (
            <div style={{ textAlign:'center' }}>
              <div style={{ fontSize:56, marginBottom:16 }}>🔒</div>
              <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:22, color:'var(--text-primary)', marginBottom:10 }}>End Current Session?</div>
              <div style={{ fontSize:14, color:'var(--text-muted)', marginBottom:28, lineHeight:1.6 }}>
                Review the report, count cash drawer, then close. Terminal returns to standby.
              </div>
              <div style={{ display:'flex', gap:12 }}>
                <button className="btn-ghost" onClick={onCancel} style={{ flex:1, padding:'14px' }}>Cancel</button>
                <button onClick={fetchAndShow} disabled={loading} style={{ flex:1, padding:'14px', background:'linear-gradient(135deg,#ff4757,#c0392b)', color:'#fff', border:'none', borderRadius:12, fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:14, cursor:'pointer' }}>
                  {loading ? <><Spinner/> Loading...</> : 'Preview Report →'}
                </button>
              </div>
            </div>
          )}

          {step === 'report' && report && (
            <div>
              <div style={{ textAlign:'center', marginBottom:20 }}>
                <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:20, color:'var(--text-primary)', marginBottom:4 }}>Session Report Preview</div>
                <div style={{ fontSize:12, color:'var(--text-muted)' }}>Review totals, then count cash</div>
              </div>
              <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:10, marginBottom:16 }}>
                {[
                  { label:'QR Tickets',  value: report.qr_sales.count,   sub:`Tshs ${fmtMon(report.qr_sales.total)}`,  icon:'🎫', color:'#4a9eff' },
                  { label:'Topups',      value: report.topups.count,      sub:`Tshs ${fmtMon(report.topups.total)}`,    icon:'💳', color:'#ffb830' },
                  { label:'Transactions',value: report.total_txns,        sub:'total operations',                        icon:'📊', color:'#a78bfa' },
                  { label:'Grand Total', value:`Tshs ${fmtMon(report.grand_total)}`, sub:'session revenue',             icon:'💰', color:'#00d4b4' },
                ].map(c => (
                  <div key={c.label} style={{ background:'var(--bg-card)', border:`1px solid var(--border)`, borderRadius:14, padding:'14px', borderLeft:`3px solid ${c.color}` }}>
                    <div style={{ display:'flex', justifyContent:'space-between', marginBottom:6 }}>
                      <span style={{ fontSize:10, color:'var(--text-muted)', textTransform:'uppercase', letterSpacing:1 }}>{c.label}</span>
                      <span>{c.icon}</span>
                    </div>
                    <div style={{ fontFamily:"'DM Mono',monospace", fontSize:20, color:c.color, fontWeight:500 }}>{c.value}</div>
                    <div style={{ fontSize:10, color:'var(--text-dim)', marginTop:3 }}>{c.sub}</div>
                  </div>
                ))}
              </div>
              <div style={{ background:'var(--bg-card)', border:'1px solid var(--border)', borderRadius:12, padding:'12px 16px', marginBottom:16, fontSize:12 }}>
                <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'6px 16px' }}>
                  {[['Cashier', report.session.cashier_name], ['Terminal', report.session.terminal],
                    ['Login', new Date(report.session.started_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})],
                    ['Card Reg.', `${report.card_sales.count} card(s)`]
                  ].map(([k,v]) => (
                    <div key={k} style={{ display:'flex', gap:6 }}>
                      <span style={{ color:'var(--text-dim)' }}>{k}:</span>
                      <span style={{ color:'var(--text-primary)', fontWeight:500 }}>{v}</span>
                    </div>
                  ))}
                </div>
              </div>
              <div style={{ display:'flex', gap:10 }}>
                <button className="btn-ghost" onClick={onCancel} style={{ flex:1, padding:'12px', fontSize:13 }}>← Keep Working</button>
                <button onClick={goCashCount} style={{ flex:1.5, padding:'12px', background:'linear-gradient(135deg,#00d4b4,#007a6e)', color:'#fff', border:'none', borderRadius:12, fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:13, cursor:'pointer' }}>
                  Next: Cash Count →
                </button>
              </div>
            </div>
          )}

          {step === 'cash' && (
            <div>
              <div style={{ textAlign:'center', marginBottom:20 }}>
                <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:20, color:'var(--text-primary)', marginBottom:4 }}>Cash Drawer Count</div>
                <div style={{ fontSize:12, color:'var(--text-muted)' }}>Enter actual cash in drawer. Variance is recorded for reconciliation.</div>
              </div>
              <div style={{ background:'var(--bg-card)', border:'1px solid var(--border)', borderRadius:14, padding:16, marginBottom:16 }}>
                <div style={{ fontSize:12, color:'var(--text-muted)', marginBottom:6 }}>Expected cash (QR + top-ups)</div>
                <div style={{ fontFamily:"'DM Mono',monospace", fontSize:24, color:'var(--accent-teal)' }}>Tshs {fmtMon(expectedFromReport)}</div>
              </div>
              <label style={{ display:'block', fontSize:11, fontWeight:600, color:'var(--text-muted)', marginBottom:8, letterSpacing:1, textTransform:'uppercase' }}>Actual cash counted</label>
              <input className="input-field" type="number" value={actualCash} onChange={e=>setActualCash(e.target.value)} placeholder="0" style={{ fontSize:18, fontFamily:"'DM Mono',monospace", marginBottom:12 }}/>
              {actualCash !== '' && !isNaN(parseFloat(actualCash)) && (
                <div style={{ marginBottom:16, fontSize:13, color: Math.abs(parseFloat(actualCash)-expectedFromReport) < 0.01 ? 'var(--success)' : 'var(--accent-amber)' }}>
                  Variance: Tshs {fmtMon(parseFloat(actualCash) - expectedFromReport)}
                </div>
              )}
              <div style={{ display:'flex', gap:10 }}>
                <button className="btn-ghost" onClick={()=>setStep('report')} style={{ flex:1, padding:'12px', fontSize:13 }}>← Back</button>
                <button onClick={confirmEnd} disabled={ending} style={{ flex:1.5, padding:'12px', background:'linear-gradient(135deg,#ff4757,#c0392b)', color:'#fff', border:'none', borderRadius:12, fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:13, cursor:'pointer' }}>
                  {ending ? <><Spinner/> Closing...</> : '✓ Close & Print'}
                </button>
              </div>
              <button className="btn-ghost" onClick={confirmEnd} disabled={ending} style={{ width:'100%', marginTop:10, fontSize:11, color:'var(--text-dim)' }}>
                Skip cash count and close
              </button>
            </div>
          )}

          {step === 'done' && (
            <div style={{ textAlign:'center', padding:'20px 0' }}>
              <div style={{ fontSize:56, marginBottom:16 }}>✅</div>
              <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:22, color:'var(--success)', marginBottom:8 }}>Session Closed</div>
              {cashResult && (
                <div style={{ fontSize:13, color:'var(--text-muted)', marginBottom:8 }}>
                  Cash variance: Tshs {fmtMon(cashResult.variance)}
                </div>
              )}
              <div style={{ fontSize:14, color:'var(--text-muted)' }}>Report sent to printer. Returning to standby...</div>
            </div>
          )}
        </div>
      </div>
    </>
  );
}
