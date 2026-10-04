// ── Card Reader widget ────────────────────────────────────────────────────────
// In production this would listen to a serial/USB NFC reader event.
// Here the cashier enters the card number that was read by the physical reader.
const DEMO_CARDS = [
  // Active registered cards
  '994160222206','994160232770','994160199001','994160187650',
  '994160187651','994160187652','994160187653','994160187654','994160187655',
  // Inactive
  '994160009988',
  // Blank unregistered cards (for registration simulation)
  '994160000001','994160000002','994160000003','994160000004','994160000005',
  '994160000006','994160000007','994160000008','994160000009','994160000010',
];
let demoIdx = 0;

function CardReader({ onCardRead, allowedStatuses = ['active'], blockedTypes = [], blockedMessage, label = 'Tap Card on Reader', fetchNext = null }) {
  const [state, setState] = useState('idle');
  const [msg, setMsg]     = useState('');
  const [manual, setManual] = useState(false);
  const [manualNo, setManualNo] = useState('');
  const [manualErr, setManualErr] = useState('');

  const clr = { idle:'var(--accent-teal)', scanning:'var(--accent-amber)', success:'var(--success)', error:'var(--accent-red)' }[state];
  const bg  = { idle:'rgba(0,212,180,.07)', scanning:'rgba(255,184,48,.07)', success:'rgba(0,230,118,.1)', error:'rgba(255,71,87,.08)' }[state];
  const ico = { idle:'📶', scanning:'⏳', success:'✅', error:'❌' }[state];

  const doLookup = async (cardNo) => {
    setState('scanning'); setMsg('Communicating with card...');
    const res = await apiGet('cards', 'lookup', { card_number: cardNo });
    if (!res.success) {
      setState('error'); setMsg(res.message);
      setTimeout(() => { setState('idle'); setMsg(''); }, 3000);
      return;
    }
    const card = res.data;
    if (!allowedStatuses.includes(card.status)) {
      setState('error'); setMsg(`Card is ${card.status.toUpperCase()} — not allowed here`);
      setTimeout(() => { setState('idle'); setMsg(''); }, 3000);
      return;
    }
    if (blockedTypes.includes(card.card_type)) {
      setState('error'); setMsg(blockedMessage || `${card.card_type} cards not allowed here`);
      setTimeout(() => { setState('idle'); setMsg(''); }, 3000);
      return;
    }
    setState('success'); setMsg('Card read successfully');
    setTimeout(() => onCardRead(cardNo, card), 600);
  };

  // Simulates the physical NFC reader sending a card number.
  // If `fetchNext` is supplied (e.g. for registration), it pulls a real card
  // number from the server — a deterministic pick from actual inventory —
  // instead of randomly cycling the shared demo pool, so this can never hand
  // out a card that's already registered or already given to another cashier.
  const simulateTap = async () => {
    if (state === 'scanning') return;
    if (fetchNext) {
      setState('scanning'); setMsg('Fetching next available card...');
      const res = await fetchNext();
      if (!res.success) {
        setState('error'); setMsg(res.message);
        setTimeout(() => { setState('idle'); setMsg(''); }, 3500);
        return;
      }
      doLookup(res.data.card_number);
      return;
    }
    const no = DEMO_CARDS[demoIdx % DEMO_CARDS.length]; demoIdx++;
    doLookup(no);
  };

  const submitManual = () => {
    if (!manualNo.trim()) { setManualErr('Enter a card number.'); return; }
    setManualErr('');
    doLookup(manualNo.trim());
  };

  if (manual) return (
    <div className="fade-in" style={{ width:'100%',display:'flex',flexDirection:'column',gap:12 }}>
      <div style={{ display:'flex',justifyContent:'space-between' }}>
        <span style={{ fontSize:12,color:'var(--text-muted)',fontWeight:600 }}>Manual Card Lookup</span>
        <button onClick={()=>{setManual(false);setManualNo('');setManualErr('');}} style={{ background:'none',border:'none',color:'var(--accent-teal)',cursor:'pointer',fontSize:12 }}>← Use Reader</button>
      </div>
      <input className="input-field" value={manualNo} onChange={e=>setManualNo(e.target.value)} placeholder="Card number e.g. 994160222206" onKeyDown={e=>e.key==='Enter'&&submitManual()}/>
      {manualErr && <div style={{ fontSize:12,color:'var(--accent-red)' }}>⚠️ {manualErr}</div>}
      <button className="btn-primary" onClick={submitManual} style={{ padding:'13px' }}>
        {state==='scanning'?<Spinner/>:'Lookup Card →'}
      </button>
    </div>
  );

  return (
    <div style={{ display:'flex',flexDirection:'column',alignItems:'center',gap:18,width:'100%' }}>
      <div style={{ position:'relative',width:190 }}>
        {(state==='idle'||state==='scanning') && ['nfc-ring-1','nfc-ring-2','nfc-ring-3'].map(c=>(
          <div key={c} className={c} style={{ position:'absolute',top:'50%',left:'50%',width:state==='scanning'?200:160,height:state==='scanning'?200:160,borderRadius:'50%',border:`1.5px solid ${clr}`,pointerEvents:'none',transition:'width .4s,height .4s' }}/>
        ))}
        <button onClick={simulateTap} className={`reader-${state}`} style={{ width:'100%',aspectRatio:'1.58/1',background:bg,border:`2px solid ${clr}`,borderRadius:18,cursor:state==='scanning'?'wait':'pointer',display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',gap:8,position:'relative',overflow:'hidden',transition:'background .4s,border-color .4s',zIndex:1 }}>
          {state==='scanning'&&<div className="scan-beam" style={{ position:'absolute',left:0,right:0,height:2,background:`linear-gradient(90deg,transparent,${clr},transparent)` }}/>}
          <span style={{ fontSize:28,transition:'font-size .3s' }}>{ico}</span>
          <span style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:10,color:clr,letterSpacing:1.5,textTransform:'uppercase',textAlign:'center',lineHeight:1.4 }}>
            {state==='idle'&&<>TAP CARD<br/>ON READER</>}
            {state==='scanning'&&'READING...'}
            {state==='success'&&'CARD READ!'}
            {state==='error'&&'READ FAILED'}
          </span>
        </button>
        {state==='scanning'&&(
          <div className="card-anim" style={{ position:'absolute',top:-44,left:'50%',width:96,height:60,borderRadius:8,background:'linear-gradient(135deg,#1a3a5c,#0d2035)',border:`1.5px solid ${clr}`,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',gap:3,boxShadow:`0 12px 28px ${clr}30`,fontSize:9,color:'var(--accent-amber)',fontFamily:"'DM Mono',monospace",letterSpacing:.5 }}>
            <span style={{ fontSize:18 }}>💳</span><span>J11 CARD</span>
          </div>
        )}
      </div>
      {msg && <div style={{ fontSize:12,color:clr,fontFamily:"'DM Mono',monospace",animation:'fadeIn .3s' }}>{msg}</div>}
      {state==='idle'&&(
        <div style={{ textAlign:'center' }}>
          <div style={{ fontSize:13,color:'var(--text-muted)',marginBottom:10 }}>Ask passenger to tap card on reader</div>
          <button onClick={()=>setManual(true)} style={{ background:'none',border:'none',color:'var(--text-dim)',cursor:'pointer',fontSize:12,textDecoration:'underline' }}>Reader not responding? Enter manually</button>
        </div>
      )}
    </div>
  );
}

