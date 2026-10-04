// ── Card Sale (Registration) ──────────────────────────────────────────────────
function CardSaleScreen({ toast }) {
  const [step,setStep]=useState(1);
  const [cardNo,setCardNo]=useState('');
  const [form,setForm]=useState({holder_name:'',phone:'',dob:'',card_type:'Adult',gender:'M'});
  const [loading,setLoading]=useState(false);
  const [done,setDone]=useState(null);
  const [blankStock,setBlankStock]=useState(null);

  const loadStock = useCallback(async () => {
    const res = await apiGet('cards','blank_stock');
    if (res.success) setBlankStock(res.data.available);
  }, []);

  useEffect(() => {
    loadStock();
    const t = setInterval(loadStock, 5000); // live sync — reflects an admin topping up inventory, or another terminal draining it
    return () => clearInterval(t);
  }, [loadStock]);

  const handleRead=(no,card)=>{ setCardNo(no); setStep(2); loadStock(); };

  const register = async () => {
    if (!form.holder_name||!form.phone) { toast('Name and phone are required','error'); return; }
    setLoading(true);
    const res = await api('cards','register',{ card_number:cardNo, ...form });
    setLoading(false);
    if (!res.success) { toast(res.message,'error'); return; }
    setDone(res.data); setStep(3); loadStock();
  };

  const reset=()=>{setStep(1);setCardNo('');setForm({holder_name:'',phone:'',dob:'',card_type:'Adult',gender:'M'});setDone(null);};

  const F=(label,key,ph,type='text')=>(
    <div><label style={{ display:'block',fontSize:11,color:'var(--text-muted)',marginBottom:7,fontWeight:600,letterSpacing:1,textTransform:'uppercase' }}>{label}</label>
    <input className="input-field" type={type} value={form[key]} onChange={e=>setForm(f=>({...f,[key]:type==='text'?e.target.value.toUpperCase():e.target.value}))} placeholder={ph}/></div>
  );

  return (
    <div className="fade-in" style={{ flex:1,display:'flex',gap:20,padding:'24px',overflow:'hidden' }}>
      <div style={{ width:280,display:'flex',flexDirection:'column',gap:14 }}>
        <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:18,color:'var(--text-primary)' }}>Card Registration</div>
        {[{n:1,l:'Scan New Card',d:'Place blank card on reader'},{n:2,l:'Passenger Details',d:'Fill registration form'},{n:3,l:'Complete',d:'Card registered & active'}].map(s=>(
          <div key={s.n} style={{ background:step===s.n?'rgba(0,212,180,.07)':'var(--bg-card)',border:`1.5px solid ${step===s.n?'var(--accent-teal)':step>s.n?'rgba(0,230,118,.3)':'var(--border)'}`,borderRadius:14,padding:'14px 16px',transition:'all .3s' }}>
            <div style={{ display:'flex',gap:10,alignItems:'center' }}>
              <div style={{ width:26,height:26,borderRadius:'50%',background:step>s.n?'var(--success)':step===s.n?'var(--accent-teal)':'var(--bg-deep)',border:`2px solid ${step>=s.n?step>s.n?'var(--success)':'var(--accent-teal)':'var(--border)'}`,display:'flex',alignItems:'center',justifyContent:'center',fontSize:11,fontWeight:700,color:step>=s.n?'#070c14':'var(--text-dim)',flexShrink:0 }}>
                {step>s.n?'✓':s.n}
              </div>
              <div><div style={{ fontSize:13,fontWeight:600,color:step>=s.n?'var(--text-primary)':'var(--text-dim)' }}>{s.l}</div><div style={{ fontSize:11,color:'var(--text-dim)',marginTop:2 }}>{s.d}</div></div>
            </div>
          </div>
        ))}
        {step===2&&<div style={{ background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:12,padding:'14px' }}>
          <div style={{ fontSize:10,color:'var(--text-dim)',letterSpacing:2,marginBottom:6 }}>CARD SERIAL</div>
          <div style={{ fontFamily:"'DM Mono',monospace",fontSize:13,color:'var(--accent-teal)',letterSpacing:1.5 }}>{cardNo}</div>
        </div>}
      </div>

      <div style={{ flex:1,display:'flex',flexDirection:'column',gap:16,overflow:'hidden' }}>
        {step===1&&(
          <div style={{ flex:1,background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:20,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',padding:40,gap:20 }}>
            <div style={{ display:'flex',alignItems:'center',gap:8,padding:'6px 14px',borderRadius:20,background:blankStock===0?'rgba(255,71,87,.1)':'rgba(0,212,180,.08)',border:`1px solid ${blankStock===0?'var(--accent-red)':'var(--accent-teal)'}` }}>
              <span style={{ width:7,height:7,borderRadius:'50%',background:blankStock===0?'var(--accent-red)':'var(--accent-teal)' }}/>
              <span style={{ fontFamily:"'DM Mono',monospace",fontSize:12,color:blankStock===0?'var(--accent-red)':'var(--accent-teal)' }}>
                {blankStock===null?'Checking inventory…':`${blankStock} blank card${blankStock===1?'':'s'} in stock`}
              </span>
            </div>
            {blankStock===0 ? (
              <div style={{ textAlign:'center',color:'var(--text-muted)',fontSize:13,maxWidth:280 }}>
                No unregistered cards left in inventory.<br/>Ask an admin to add more blank cards before registering another passenger.
              </div>
            ) : (
              <>
                <div style={{ fontSize:11,color:'var(--text-dim)',letterSpacing:2,textTransform:'uppercase',textAlign:'center' }}>Pull the next unregistered card from inventory</div>
                <CardReader onCardRead={handleRead} allowedStatuses={['new']} fetchNext={()=>apiGet('cards','next_blank')} label="Scan new card"/>
              </>
            )}
          </div>
        )}
        {step===2&&(
          <div className="slide-up" style={{ flex:1,overflowY:'auto',background:'var(--bg-card)',border:'1px solid var(--border)',borderRadius:20,padding:28 }}>
            <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:700,fontSize:15,color:'var(--text-primary)',marginBottom:22 }}>Passenger Information</div>
            <div style={{ display:'grid',gridTemplateColumns:'1fr 1fr',gap:16 }}>
              {F('Full Name','holder_name','e.g. AMINA JUMA')}
              {F('Phone Number','phone','e.g. 0712345678','tel')}
              {F('Date of Birth','dob','','date')}
              <div>
                <label style={{ display:'block',fontSize:11,color:'var(--text-muted)',marginBottom:7,fontWeight:600,letterSpacing:1,textTransform:'uppercase' }}>Card Type</label>
                <div style={{ display:'flex',gap:8 }}>
                  {['Adult','Staff'].map(t=>(
                    <button key={t} onClick={()=>setForm(f=>({...f,card_type:t}))} style={{ flex:1,padding:'9px 2px',borderRadius:10,cursor:'pointer',fontSize:11,fontWeight:600,fontFamily:"'Syne',sans-serif",background:form.card_type===t?'linear-gradient(135deg,#00d4b4,#00a896)':'var(--bg-deep)',color:form.card_type===t?'#070c14':'var(--text-muted)',border:form.card_type===t?'none':'1.5px solid var(--border)',transition:'all .15s' }}>{t}</button>
                  ))}
                </div>
              </div>
              <div>
                <label style={{ display:'block',fontSize:11,color:'var(--text-muted)',marginBottom:7,fontWeight:600,letterSpacing:1,textTransform:'uppercase' }}>Gender</label>
                <div style={{ display:'flex',gap:8 }}>
                  {[['M','Male'],['F','Female']].map(([v,l])=>(
                    <button key={v} onClick={()=>setForm(f=>({...f,gender:v}))} style={{ flex:1,padding:'10px',borderRadius:10,cursor:'pointer',fontSize:12,fontWeight:600,background:form.gender===v?'linear-gradient(135deg,#4a9eff,#2b7de9)':'var(--bg-deep)',color:form.gender===v?'#fff':'var(--text-muted)',border:form.gender===v?'none':'1.5px solid var(--border)',transition:'all .15s' }}>{l}</button>
                  ))}
                </div>
              </div>
            </div>
            <div style={{ borderTop:'1px solid var(--border)',marginTop:24,paddingTop:20,display:'flex',gap:12 }}>
              <button className="btn-primary" onClick={register} disabled={loading||!form.holder_name||!form.phone} style={{ flex:1,padding:'16px',fontSize:14,letterSpacing:1 }}>
                {loading?<><Spinner/> REGISTERING...</>:'REGISTER & ACTIVATE CARD →'}
              </button>
              <button className="btn-ghost" onClick={()=>setStep(1)} style={{ padding:'16px 20px' }}>Back</button>
            </div>
          </div>
        )}
        {step===3&&done&&(
          <div className="slide-up" style={{ flex:1,display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',gap:24 }}>
            <div style={{ fontSize:72 }}>🎉</div>
            <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:28,color:'var(--success)' }}>Card Registered!</div>
            <div style={{ background:'linear-gradient(135deg,#0d2035,#0a1826)',border:'1.5px solid var(--success)',borderRadius:20,padding:'24px 36px',textAlign:'center' }}>
              <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:22,color:'var(--text-primary)',marginBottom:6 }}>{done.holder_name}</div>
              <div style={{ fontFamily:"'DM Mono',monospace",fontSize:13,color:'var(--text-muted)',letterSpacing:2,marginBottom:12 }}>{done.card_number}</div>
              <div style={{ fontSize:12,color:'var(--success)' }}>● ACTIVE — Ready for use</div>
            </div>
            <div style={{ display:'flex',gap:12 }}>
              <button className="btn-primary" onClick={reset} style={{ padding:'14px 28px' }}>Register Another</button>
              <button className="btn-ghost" style={{ padding:'14px 20px' }}>🖨️ Print Slip</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

