// ── Login (+ optional MFA) + central availability awareness ───────────────────
function LoginScreen({ onLogin }) {
  const [u,setU]=useState(''); const [p,setP]=useState('');
  const [sp,setSp]=useState(false); const [loading,setLoading]=useState(false); const [err,setErr]=useState('');
  const [mfaStep,setMfaStep]=useState(false); const [mfaCode,setMfaCode]=useState('');
  const [centralOk, setCentralOk] = useState(null); // null=checking, true, false
  const [offlineGrant, setOfflineGrant] = useState(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const h = await probeCentralHealth();
      if (cancelled) return;
      setCentralOk(!!h.ok);
      // Surface any stored offline grant (device-bound) for operator awareness
      try {
        if (typeof afcsGetOfflineGrant === 'function') {
          const g = afcsGetOfflineGrant();
          if (g && g.expires_at && new Date(g.expires_at) > new Date()) setOfflineGrant(g);
          else setOfflineGrant(null);
        }
      } catch { setOfflineGrant(null); }
    })();
    const onOnline = async () => {
      const h = await probeCentralHealth();
      setCentralOk(!!h.ok);
    };
    window.addEventListener('online', onOnline);
    return () => { cancelled = true; window.removeEventListener('online', onOnline); };
  }, []);

  const login = async () => {
    if (!u||!p) { setErr('Please enter credentials.'); return; }
    setLoading(true); setErr('');
    const res = await api('auth','login',{ username:u, password:p });
    setLoading(false);
    if (!res.success) {
      if (res.error_code === 'SERVICE_UNAVAILABLE') {
        setCentralOk(false);
        setErr(res.message);
      } else {
        setErr(res.message);
      }
      return;
    }
    if (res.data?.mfa_required) { setMfaStep(true); return; }
    onLogin(res.data);
  };

  const verifyMfa = async () => {
    if (!mfaCode || mfaCode.length < 6) { setErr('Enter the 6-digit code from your authenticator app.'); return; }
    setLoading(true); setErr('');
    const res = await api('auth','mfa_verify',{ code: mfaCode });
    setLoading(false);
    if (!res.success) { setErr(res.message); return; }
    onLogin(res.data);
  };

  return (
    <div style={{ width:'100vw',height:'100vh',background:'var(--bg-deep)',display:'flex',alignItems:'center',justifyContent:'center',position:'relative',overflow:'hidden' }}>
      <div style={{ position:'absolute',inset:0,backgroundImage:'linear-gradient(var(--border) 1px,transparent 1px),linear-gradient(90deg,var(--border) 1px,transparent 1px)',backgroundSize:'60px 60px',opacity:.4 }}/>
      <div style={{ position:'absolute',top:'20%',left:'15%',width:300,height:300,borderRadius:'50%',background:'radial-gradient(circle,rgba(0,212,180,.08),transparent 70%)' }}/>
      <div className="slide-up" style={{ background:'var(--bg-panel)',border:'1px solid var(--border-bright)',borderRadius:24,padding:'48px 44px',width:420,boxShadow:'0 40px 80px rgba(0,0,0,.6)' }}>
        <div style={{ textAlign:'center',marginBottom:28 }}>
          <div style={{ display:'inline-flex',alignItems:'center',gap:12,marginBottom:12 }}>
            <div style={{ width:48,height:48,borderRadius:14,background:'linear-gradient(135deg,#00d4b4,#007a6e)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:24 }}>🚇</div>
            <div>
              <div style={{ fontFamily:"'Syne',sans-serif",fontWeight:800,fontSize:28,color:'var(--accent-teal)',letterSpacing:2 }}>J11</div>
              <div style={{ fontSize:9,color:'var(--text-muted)',letterSpacing:3,textTransform:'uppercase' }}>J11 Rapid Transit</div>
            </div>
          </div>
          <div style={{ fontSize:13,color:'var(--text-muted)' }}>Automated Fare Collection System</div>
          <div style={{ width:40,height:2,background:'linear-gradient(90deg,transparent,var(--accent-teal),transparent)',margin:'14px auto 0' }}/>
        </div>

        {/* Central system status */}
        {centralOk === false && (
          <div style={{ background:'rgba(255,184,48,.1)', border:'1px solid rgba(255,184,48,.35)', borderRadius:12, padding:'12px 14px', marginBottom:16, fontSize:12, color:'var(--accent-amber)', lineHeight:1.5 }}>
            <strong style={{ display:'block', marginBottom:4 }}>Central system unavailable</strong>
            Online login is disabled while the database or network is down.
            {offlineGrant ? (
              <span> An offline grant is present on this browser until <strong>{new Date(offlineGrant.expires_at).toLocaleString()}</strong> for device <strong>{offlineGrant.device_uid}</strong> — native terminal offline runtime required for offline sales.</span>
            ) : (
              <span> This browser is not an enrolled terminal with a valid offline grant. Restore MySQL/network or use a registered device.</span>
            )}
          </div>
        )}
        {centralOk === true && (
          <div style={{ fontSize:11, color:'var(--success)', marginBottom:12, textAlign:'center' }}>● Central system online</div>
        )}
        {centralOk === null && (
          <div style={{ fontSize:11, color:'var(--text-dim)', marginBottom:12, textAlign:'center' }}>Checking central system…</div>
        )}

        {!mfaStep ? (
          <div style={{ display:'flex',flexDirection:'column',gap:16 }}>
            <div>
              <label style={{ display:'block',fontSize:11,fontWeight:600,color:'var(--text-muted)',marginBottom:8,letterSpacing:1,textTransform:'uppercase' }}>Username</label>
              <input className="input-field" value={u} onChange={e=>setU(e.target.value)} placeholder="e.g. luogaw" onKeyDown={e=>e.key==='Enter'&&login()} disabled={centralOk===false}/>
            </div>
            <div>
              <label style={{ display:'block',fontSize:11,fontWeight:600,color:'var(--text-muted)',marginBottom:8,letterSpacing:1,textTransform:'uppercase' }}>Password</label>
              <div style={{ position:'relative' }}>
                <input className="input-field" type={sp?'text':'password'} value={p} onChange={e=>setP(e.target.value)} placeholder="••••••••" style={{ paddingRight:44 }} onKeyDown={e=>e.key==='Enter'&&login()} disabled={centralOk===false}/>
                <button type="button" onClick={()=>setSp(!sp)} style={{ position:'absolute',right:14,top:'50%',transform:'translateY(-50%)',background:'none',border:'none',color:'var(--text-dim)',cursor:'pointer',fontSize:14 }}>{sp?'🙈':'👁'}</button>
              </div>
            </div>
            {err&&<div style={{ background:'rgba(255,71,87,.1)',border:'1px solid rgba(255,71,87,.3)',borderRadius:8,padding:'10px 14px',fontSize:13,color:'var(--accent-red)' }}>⚠️ {err}</div>}
            <button className="btn-primary" onClick={login} disabled={loading || centralOk===false} style={{ padding:'15px',marginTop:8,fontSize:14,letterSpacing:1, opacity: centralOk===false ? 0.5 : 1 }}>
              {loading?<><Spinner/> AUTHENTICATING...</>:'LOGIN →'}
            </button>
          </div>
        ) : (
          <div style={{ display:'flex',flexDirection:'column',gap:16 }}>
            <div style={{ fontSize:13, color:'var(--text-muted)', textAlign:'center' }}>
              Enter the 6-digit code from your authenticator app
            </div>
            <input className="input-field" value={mfaCode} onChange={e=>setMfaCode(e.target.value.replace(/\D/g,'').slice(0,6))} placeholder="000000" style={{ textAlign:'center', letterSpacing:8, fontSize:22, fontFamily:"'DM Mono',monospace" }} onKeyDown={e=>e.key==='Enter'&&verifyMfa()}/>
            {err&&<div style={{ background:'rgba(255,71,87,.1)',border:'1px solid rgba(255,71,87,.3)',borderRadius:8,padding:'10px 14px',fontSize:13,color:'var(--accent-red)' }}>⚠️ {err}</div>}
            <button className="btn-primary" onClick={verifyMfa} disabled={loading} style={{ padding:'15px',fontSize:14,letterSpacing:1 }}>
              {loading?<><Spinner/> VERIFYING...</>:'VERIFY MFA →'}
            </button>
            <button className="btn-ghost" onClick={()=>{ setMfaStep(false); setMfaCode(''); setErr(''); }} style={{ fontSize:12 }}>← Back</button>
          </div>
        )}

        <div style={{ marginTop:20,textAlign:'center',fontSize:11,color:'var(--text-dim)' }}>
          Demo login requires password change on first use
        </div>
      </div>
    </div>
  );
}
