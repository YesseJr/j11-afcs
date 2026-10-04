// ── Forced / voluntary password change ───────────────────────────────────────
function ChangePasswordScreen({ user, onDone, forced = false, toast }) {
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirm, setConfirm] = useState('');
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState('');

  const submit = async () => {
    setErr('');
    if (!current || !next || !confirm) { setErr('All fields are required.'); return; }
    if (next !== confirm) { setErr('New passwords do not match.'); return; }
    if (next.length < 8) { setErr('Password must be at least 8 characters.'); return; }
    setLoading(true);
    const res = await api('auth', 'change_password', {
      current_password: current,
      new_password: next,
      confirm_password: confirm,
    });
    setLoading(false);
    if (!res.success) { setErr(res.message || 'Password change failed'); return; }
    if (toast) toast('Password updated successfully', 'ok');
    onDone && onDone();
  };

  return (
    <div style={{ width:'100vw', height:'100vh', background:'var(--bg-deep)', display:'flex', alignItems:'center', justifyContent:'center' }}>
      <div className="slide-up" style={{ background:'var(--bg-panel)', border:'1px solid var(--border-bright)', borderRadius:24, padding:'40px 40px', width:440, boxShadow:'0 40px 80px rgba(0,0,0,.6)' }}>
        <div style={{ textAlign:'center', marginBottom:28 }}>
          <div style={{ fontSize:36, marginBottom:8 }}>🔐</div>
          <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:700, fontSize:22, color:'var(--accent-teal)' }}>
            {forced ? 'Password Change Required' : 'Change Password'}
          </div>
          <div style={{ fontSize:13, color:'var(--text-muted)', marginTop:8 }}>
            {forced
              ? 'For security, you must set a new password before continuing.'
              : 'Update your account password.'}
          </div>
          {user?.username && (
            <div style={{ marginTop:10, fontSize:12, color:'var(--text-dim)', fontFamily:"'DM Mono',monospace" }}>
              {user.username}
            </div>
          )}
        </div>
        <div style={{ display:'flex', flexDirection:'column', gap:14 }}>
          <div>
            <label style={{ display:'block', fontSize:11, fontWeight:600, color:'var(--text-muted)', marginBottom:6, letterSpacing:1, textTransform:'uppercase' }}>Current password</label>
            <input className="input-field" type="password" value={current} onChange={e=>setCurrent(e.target.value)} placeholder="Current password"/>
          </div>
          <div>
            <label style={{ display:'block', fontSize:11, fontWeight:600, color:'var(--text-muted)', marginBottom:6, letterSpacing:1, textTransform:'uppercase' }}>New password</label>
            <input className="input-field" type="password" value={next} onChange={e=>setNext(e.target.value)} placeholder="At least 8 characters"/>
          </div>
          <div>
            <label style={{ display:'block', fontSize:11, fontWeight:600, color:'var(--text-muted)', marginBottom:6, letterSpacing:1, textTransform:'uppercase' }}>Confirm new password</label>
            <input className="input-field" type="password" value={confirm} onChange={e=>setConfirm(e.target.value)} placeholder="Repeat new password" onKeyDown={e=>e.key==='Enter'&&submit()}/>
          </div>
          {err && <div style={{ background:'rgba(255,71,87,.1)', border:'1px solid rgba(255,71,87,.3)', borderRadius:8, padding:'10px 14px', fontSize:13, color:'var(--accent-red)' }}>⚠️ {err}</div>}
          <button className="btn-primary" onClick={submit} disabled={loading} style={{ padding:'14px', marginTop:6, fontSize:14, letterSpacing:1 }}>
            {loading ? <><Spinner/> SAVING...</> : 'UPDATE PASSWORD →'}
          </button>
        </div>
      </div>
    </div>
  );
}
