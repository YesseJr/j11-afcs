// ── Balance Transfer ──────────────────────────────────────────────────────────
function BalanceTransferScreen({ toast, onStatsUpdate }) {
  const [step,      setStep]      = useState(1); // 1=scan from, 2=scan to, 3=amount+confirm
  const [fromData,  setFromData]  = useState(null);
  const [toData,    setToData]    = useState(null);
  const [amount,    setAmount]    = useState('');
  const [loading,   setLoading]   = useState(false);
  const [done,      setDone]      = useState(null);

  const handleFromRead = (no, card) => { setFromData({no, card}); setStep(2); };
  const handleToRead   = (no, card) => {
    if (no === fromData?.no) { toast('Source and destination cannot be the same card.', 'error'); return; }
    setToData({no, card}); setStep(3);
  };

  const doTransfer = async () => {
    const amt = parseFloat(amount);
    if (!amt || amt <= 0) { toast('Enter a valid amount.', 'error'); return; }
    if (amt > parseFloat(fromData.card.balance)) { toast('Insufficient balance on source card.', 'error'); return; }
    setLoading(true);
    const res = await api('cards', 'balance_transfer', {
      from_card: fromData.no, to_card: toData.no, amount: amt,
    }, { idempotent: true });
    setLoading(false);
    if (!res.success) { toast(res.message, 'error'); return; }
    setDone(res.data);
    if (onStatsUpdate) onStatsUpdate();
  };

  const reset = () => { setStep(1); setFromData(null); setToData(null); setAmount(''); setDone(null); };

  const STEPS = [{n:1,l:'Source Card'},{n:2,l:'Dest. Card'},{n:3,l:'Amount'}];

  if (done) return (
    <div className="fade-in" style={{ flex:1, display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', gap:24, padding:40 }}>
      <div style={{fontSize:72}}>🔄</div>
      <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:28, color:'var(--success)' }}>Transfer Complete!</div>
      <div style={{ display:'grid', gridTemplateColumns:'1fr auto 1fr', gap:16, alignItems:'center', width:460 }}>
        {[
          { label:'Source Card', no:done.from_card, bal:done.from_new_balance, color:'var(--accent-red)' },
          null,
          { label:'Destination Card', no:done.to_card, bal:done.to_new_balance, color:'var(--success)' },
        ].map((c,i) => c ? (
          <div key={i} style={{ background:'var(--bg-card)', border:`1.5px solid ${c.color}`, borderRadius:16, padding:'16px 18px', textAlign:'center' }}>
            <div style={{ fontSize:10, color:'var(--text-dim)', letterSpacing:2, marginBottom:6 }}>{c.label}</div>
            <div style={{ fontFamily:"'DM Mono',monospace", fontSize:12, color:'var(--text-muted)', marginBottom:8 }}>{c.no}</div>
            <div style={{ fontSize:10, color:'var(--text-dim)', marginBottom:2 }}>New Balance</div>
            <div style={{ fontFamily:"'DM Mono',monospace", fontSize:20, color:c.color }}>Tshs {fmtMoney(c.bal)}</div>
          </div>
        ) : (
          <div key={i} style={{ textAlign:'center' }}>
            <div style={{ fontSize:28 }}>→</div>
            <div style={{ fontFamily:"'DM Mono',monospace", fontSize:13, color:'var(--accent-amber)', marginTop:6 }}>Tshs {fmtMoney(done.amount)}</div>
          </div>
        ))}
      </div>
      <div style={{ display:'flex', gap:12 }}>
        <button className="btn-primary" onClick={reset} style={{ padding:'14px 28px' }}>New Transfer</button>
        <button className="btn-ghost" style={{ padding:'14px 20px' }}>🖨️ Print Receipt</button>
      </div>
    </div>
  );

  return (
    <div className="fade-in" style={{ flex:1, display:'flex', gap:20, padding:'24px', overflow:'hidden' }}>
      {/* Left: steps sidebar */}
      <div style={{ width:260, display:'flex', flexDirection:'column', gap:14 }}>
        <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:18, color:'var(--text-primary)' }}>Balance Transfer</div>
        {STEPS.map(s => (
          <div key={s.n} style={{ background:step===s.n?'rgba(251,146,60,.08)':'var(--bg-card)', border:`1.5px solid ${step===s.n?'var(--accent-amber)':step>s.n?'rgba(0,230,118,.3)':'var(--border)'}`, borderRadius:14, padding:'14px 16px', transition:'all .3s' }}>
            <div style={{ display:'flex', gap:10, alignItems:'center' }}>
              <div style={{ width:26, height:26, borderRadius:'50%', background:step>s.n?'var(--success)':step===s.n?'var(--accent-amber)':'var(--bg-deep)', border:`2px solid ${step>=s.n?step>s.n?'var(--success)':'var(--accent-amber)':'var(--border)'}`, display:'flex', alignItems:'center', justifyContent:'center', fontSize:11, fontWeight:700, color:step>=s.n?'#070c14':'var(--text-dim)', flexShrink:0 }}>
                {step>s.n?'✓':s.n}
              </div>
              <div style={{ fontSize:13, fontWeight:600, color:step>=s.n?'var(--text-primary)':'var(--text-dim)' }}>{s.l}</div>
            </div>
          </div>
        ))}
        {fromData && (
          <div style={{ background:'var(--bg-card)', border:'1.5px solid rgba(255,71,87,.4)', borderRadius:12, padding:'14px' }}>
            <div style={{ fontSize:10, color:'var(--text-dim)', letterSpacing:2, marginBottom:6 }}>FROM CARD</div>
            <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:13, color:'var(--text-primary)', marginBottom:2 }}>{fromData.card.holder_name||'—'}</div>
            <div style={{ fontFamily:"'DM Mono',monospace", fontSize:11, color:'var(--text-muted)', marginBottom:6 }}>{fromData.no}</div>
            <div style={{ fontSize:12, color:'var(--accent-amber)' }}>Balance: Tshs {fmtMoney(fromData.card.balance)}</div>
          </div>
        )}
        {toData && (
          <div style={{ background:'var(--bg-card)', border:'1.5px solid rgba(0,230,118,.4)', borderRadius:12, padding:'14px' }}>
            <div style={{ fontSize:10, color:'var(--text-dim)', letterSpacing:2, marginBottom:6 }}>TO CARD</div>
            <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:13, color:'var(--text-primary)', marginBottom:2 }}>{toData.card.holder_name||'—'}</div>
            <div style={{ fontFamily:"'DM Mono',monospace", fontSize:11, color:'var(--text-muted)' }}>{toData.no}</div>
          </div>
        )}
      </div>

      {/* Right: active step content */}
      <div style={{ flex:1, display:'flex', flexDirection:'column', gap:16, overflow:'hidden' }}>
        {step === 1 && (
          <div style={{ flex:1, background:'var(--bg-card)', border:'1px solid var(--border)', borderRadius:20, display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', padding:40 }}>
            <div style={{ fontSize:11, color:'var(--text-dim)', letterSpacing:2, textTransform:'uppercase', marginBottom:32, textAlign:'center' }}>Scan the card funds will be transferred <em>from</em></div>
            <CardReader onCardRead={handleFromRead} allowedStatuses={['active']} label="Tap source card"/>
          </div>
        )}
        {step === 2 && (
          <div style={{ flex:1, background:'var(--bg-card)', border:'1px solid var(--border)', borderRadius:20, display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', padding:40 }}>
            <div style={{ fontSize:11, color:'var(--text-dim)', letterSpacing:2, textTransform:'uppercase', marginBottom:32, textAlign:'center' }}>Now scan the card funds will be transferred <em>to</em></div>
            <CardReader onCardRead={handleToRead} allowedStatuses={['active']} label="Tap destination card"/>
            <button className="btn-ghost" onClick={()=>{setStep(1);setFromData(null);}} style={{ marginTop:16, padding:'10px 20px', fontSize:12 }}>← Re-scan Source</button>
          </div>
        )}
        {step === 3 && fromData && toData && (
          <div className="slide-up" style={{ flex:1, display:'flex', gap:16, overflow:'hidden' }}>
            <div style={{ flex:1, display:'flex', flexDirection:'column', gap:16, minHeight:0, overflowY:'auto' }}>
              {/* Transfer summary */}
              <div style={{ background:'var(--bg-card)', border:'1px solid var(--border)', borderRadius:16, padding:'20px 24px' }}>
                <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:11, color:'var(--text-dim)', letterSpacing:2, textTransform:'uppercase', marginBottom:14 }}>Transfer Summary</div>
                <div style={{ display:'grid', gridTemplateColumns:'1fr auto 1fr', gap:12, alignItems:'center', marginBottom:16 }}>
                  <div style={{ background:'rgba(255,71,87,.07)', border:'1.5px solid rgba(255,71,87,.3)', borderRadius:12, padding:'12px 14px' }}>
                    <div style={{ fontSize:10, color:'var(--text-dim)', marginBottom:4 }}>FROM</div>
                    <div style={{ fontSize:13, fontWeight:600, color:'var(--text-primary)' }}>{fromData.card.holder_name}</div>
                    <div style={{ fontFamily:"'DM Mono',monospace", fontSize:11, color:'var(--text-muted)', marginTop:2 }}>{fromData.no}</div>
                    <div style={{ fontSize:11, color:'var(--accent-amber)', marginTop:6 }}>Bal: Tshs {fmtMoney(fromData.card.balance)}</div>
                  </div>
                  <div style={{ fontSize:24, color:'var(--accent-amber)', textAlign:'center' }}>→</div>
                  <div style={{ background:'rgba(0,230,118,.07)', border:'1.5px solid rgba(0,230,118,.3)', borderRadius:12, padding:'12px 14px' }}>
                    <div style={{ fontSize:10, color:'var(--text-dim)', marginBottom:4 }}>TO</div>
                    <div style={{ fontSize:13, fontWeight:600, color:'var(--text-primary)' }}>{toData.card.holder_name}</div>
                    <div style={{ fontFamily:"'DM Mono',monospace", fontSize:11, color:'var(--text-muted)', marginTop:2 }}>{toData.no}</div>
                  </div>
                </div>
                <div style={{ background:'var(--bg-deep)', borderRadius:12, padding:'14px 18px', display:'flex', justifyContent:'space-between', alignItems:'center' }}>
                  <div>
                    <div style={{ fontSize:11, color:'var(--text-dim)', marginBottom:4 }}>Amount to Transfer</div>
                    <div style={{ fontFamily:"'DM Mono',monospace", fontSize:32, color:'var(--accent-amber)' }}>{amount ? `Tshs ${fmtMoney(parseFloat(amount))}` : <span style={{color:'var(--text-dim)'}}>Tshs 0</span>}</div>
                  </div>
                  {amount && parseFloat(amount) <= parseFloat(fromData.card.balance) && (
                    <div style={{ textAlign:'right' }}>
                      <div style={{ fontSize:11, color:'var(--text-dim)', marginBottom:4 }}>Remaining Balance</div>
                      <div style={{ fontFamily:"'DM Mono',monospace", fontSize:20, color:'var(--text-muted)' }}>Tshs {fmtMoney(parseFloat(fromData.card.balance) - parseFloat(amount))}</div>
                    </div>
                  )}
                  {amount && parseFloat(amount) > parseFloat(fromData.card.balance) && (
                    <div style={{ color:'var(--accent-red)', fontSize:12 }}>⚠️ Insufficient balance</div>
                  )}
                </div>
              </div>
              <button
                onClick={doTransfer}
                disabled={loading || !amount || parseFloat(amount) <= 0 || parseFloat(amount) > parseFloat(fromData.card.balance)}
                style={{ padding:'18px', fontSize:15, letterSpacing:1, background:'linear-gradient(135deg,#fb923c,#ea580c)', color:'#fff', border:'none', borderRadius:12, fontFamily:"'Syne',sans-serif", fontWeight:700, cursor:'pointer', boxShadow:'0 4px 20px rgba(251,146,60,.4)', opacity:(loading || !amount || parseFloat(amount) <= 0 || parseFloat(amount) > parseFloat(fromData.card.balance)) ? .5 : 1, transition:'opacity .2s' }}>
                {loading ? <><Spinner/> PROCESSING...</> : '🔄  CONFIRM TRANSFER'}
              </button>
              <button className="btn-ghost" onClick={()=>{setStep(2);setToData(null);}} style={{ padding:'12px', fontSize:13 }}>← Re-scan Destination</button>
            </div>
            {/* Amount numpad */}
            <div style={{ width:210, display:'flex', flexDirection:'column', gap:14 }}>
              <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:11, color:'var(--text-dim)', letterSpacing:2, textTransform:'uppercase' }}>Enter Amount</div>
              <div style={{ background:'var(--bg-deep)', border:`1.5px solid ${amount?'var(--accent-amber)':'var(--border)'}`, borderRadius:12, padding:'16px 18px' }}>
                <div style={{ fontSize:11, color:'var(--text-dim)', marginBottom:4 }}>Tshs</div>
                <div style={{ fontFamily:"'DM Mono',monospace", fontSize:28, color:'var(--accent-amber)', minHeight:36 }}>{amount||<span style={{color:'var(--text-dim)'}}>0</span>}</div>
              </div>
              <Numpad value={amount} onChange={setAmount}/>
              <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
                {[500,1000,2000,5000].map(a=>(<button key={a} className="amount-chip" onClick={()=>setAmount(String(a))} style={{ padding:'10px 4px', fontSize:12 }}>{a.toLocaleString()}</button>))}
                <button className="amount-chip" onClick={()=>setAmount('')} style={{ color:'var(--accent-red)', gridColumn:'1/-1' }}>Clear</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

