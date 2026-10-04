// ── Security / MFA settings (Admin or self-service) ──────────────────────────
function SecuritySettingsScreen({ user, toast }) {
  const [secret, setSecret] = useState('');
  const [uri, setUri] = useState('');
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [mfaOn, setMfaOn] = useState(!!user?.mfa_enabled);
  const [pwd, setPwd] = useState('');

  const beginEnroll = async () => {
    setBusy(true);
    const res = await api('auth', 'mfa_enroll_begin', {});
    setBusy(false);
    if (!res.success) { toast(res.message, 'error'); return; }
    setSecret(res.data.secret);
    setUri(res.data.otpauth_uri);
    toast('Scan the secret in your authenticator app', 'ok');
  };

  const confirmEnroll = async () => {
    setBusy(true);
    const res = await api('auth', 'mfa_enroll_confirm', { code });
    setBusy(false);
    if (!res.success) { toast(res.message, 'error'); return; }
    setMfaOn(true);
    setSecret(''); setUri(''); setCode('');
    toast('MFA enabled', 'ok');
  };

  const disable = async () => {
    setBusy(true);
    const res = await api('auth', 'mfa_disable', { code, password: pwd });
    setBusy(false);
    if (!res.success) { toast(res.message, 'error'); return; }
    setMfaOn(false);
    setCode(''); setPwd('');
    toast('MFA disabled', 'ok');
  };

  return (
    <div className="fade-in" style={{ flex:1, overflowY:'auto', padding:'28px', maxWidth:640 }}>
      <div style={{ marginBottom:24 }}>
        <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:22, color:'var(--text-primary)' }}>Security settings</div>
        <div style={{ fontSize:13, color:'var(--text-muted)', marginTop:4 }}>Two-factor authentication (TOTP) for this account</div>
      </div>

      <div style={{ background:'var(--bg-panel)', border:'1px solid #3a2f0f', borderRadius:16, padding:20, marginBottom:20 }}>
        <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center' }}>
          <div>
            <div style={{ fontWeight:700, color:'var(--text-primary)' }}>Authenticator MFA</div>
            <div style={{ fontSize:12, color:'var(--text-muted)', marginTop:4 }}>
              Status: <span style={{ color: mfaOn ? 'var(--success)' : 'var(--accent-amber)' }}>{mfaOn ? 'Enabled' : 'Disabled'}</span>
            </div>
          </div>
        </div>

        {!mfaOn && !secret && (
          <button className="btn-primary" onClick={beginEnroll} disabled={busy} style={{ marginTop:16, padding:'12px 18px' }}>
            {busy ? 'Working…' : 'Enable MFA'}
          </button>
        )}

        {secret && (
          <div style={{ marginTop:16 }}>
            <div style={{ fontSize:12, color:'var(--text-muted)', marginBottom:8 }}>Secret (enter manually if QR not available)</div>
            <div style={{ fontFamily:"'DM Mono',monospace", fontSize:14, color:'var(--accent-amber)', wordBreak:'break-all', marginBottom:10 }}>{secret}</div>
            <div style={{ fontSize:11, color:'var(--text-dim)', marginBottom:12, wordBreak:'break-all' }}>{uri}</div>
            <input className="input-field" placeholder="6-digit code" value={code} onChange={e=>setCode(e.target.value.replace(/\D/g,'').slice(0,6))} style={{ marginBottom:10 }}/>
            <button className="btn-primary" onClick={confirmEnroll} disabled={busy} style={{ padding:'12px 18px' }}>Confirm & enable</button>
          </div>
        )}

        {mfaOn && (
          <div style={{ marginTop:16 }}>
            <div style={{ fontSize:12, color:'var(--text-muted)', marginBottom:8 }}>Disable MFA (requires password + current code)</div>
            <input className="input-field" type="password" placeholder="Password" value={pwd} onChange={e=>setPwd(e.target.value)} style={{ marginBottom:8 }}/>
            <input className="input-field" placeholder="6-digit code" value={code} onChange={e=>setCode(e.target.value.replace(/\D/g,'').slice(0,6))} style={{ marginBottom:10 }}/>
            <button className="btn-ghost" onClick={disable} disabled={busy} style={{ padding:'10px 16px', color:'var(--accent-red)' }}>Disable MFA</button>
          </div>
        )}
      </div>

      <div style={{ fontSize:12, color:'var(--text-dim)', lineHeight:1.6 }}>
        Use Google Authenticator, Authy, or any TOTP app. After enabling, login will require the 6-digit code.
      </div>
    </div>
  );
}
