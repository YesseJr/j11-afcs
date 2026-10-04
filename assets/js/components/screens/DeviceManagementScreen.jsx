// ── Device Management (Admin) ────────────────────────────────────────────────
function DeviceManagementScreen({ toast }) {
  const [devices, setDevices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm] = useState({ device_uid:'', name:'', device_type:'cashier_terminal', terminal_label:'', station_code:'' });
  const [lastToken, setLastToken] = useState(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    const res = await apiGet('devices', 'list');
    setLoading(false);
    if (res.success) setDevices(res.data || []);
    else toast(res.message, 'error');
  }, []);

  useEffect(() => { load(); const t=setInterval(load, 15000); return ()=>clearInterval(t); }, [load]);

  const register = async () => {
    if (!form.device_uid) { toast('device_uid required', 'error'); return; }
    setSaving(true);
    const res = await api('devices', 'register', form);
    setSaving(false);
    if (!res.success) { toast(res.message, 'error'); return; }
    setLastToken(res.data.auth_token);
    toast('Device registered — copy the token now', 'ok');
    setForm({ device_uid:'', name:'', device_type:'cashier_terminal', terminal_label:'', station_code:'' });
    load();
  };

  const setStatus = async (id, status) => {
    const res = await api('devices', 'update_status', { device_id: id, status });
    if (!res.success) { toast(res.message, 'error'); return; }
    toast(`Status → ${status}`, 'ok');
    load();
  };

  const heartbeatAge = (ts) => {
    if (!ts) return 'Never';
    const m = Math.floor((Date.now() - new Date(ts).getTime()) / 60000);
    if (m < 1) return 'Just now';
    if (m < 60) return `${m}m ago`;
    return `${Math.floor(m/60)}h ago`;
  };

  return (
    <div className="fade-in" style={{ flex:1, overflowY:'auto', padding:'28px', display:'flex', flexDirection:'column', gap:24 }}>
      <div>
        <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:22, color:'var(--text-primary)' }}>Devices</div>
        <div style={{ fontSize:13, color:'var(--text-muted)', marginTop:4 }}>Register terminals, gates and inspectors. Tokens are shown once.</div>
      </div>

      <div style={{ background:'var(--bg-panel)', border:'1px solid #3a2f0f', borderRadius:16, padding:20 }}>
        <div style={{ fontSize:12, fontWeight:700, color:'var(--accent-amber)', letterSpacing:1, textTransform:'uppercase', marginBottom:14 }}>Register device</div>
        <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:12 }}>
          <input className="input-field" placeholder="Device UID" value={form.device_uid} onChange={e=>setForm({...form, device_uid:e.target.value})}/>
          <input className="input-field" placeholder="Display name" value={form.name} onChange={e=>setForm({...form, name:e.target.value})}/>
          <select className="input-field" value={form.device_type} onChange={e=>setForm({...form, device_type:e.target.value})}>
            <option value="cashier_terminal">Cashier terminal</option>
            <option value="gate_validator">Gate validator</option>
            <option value="inspector_mobile">Inspector mobile</option>
            <option value="pos">POS</option>
            <option value="printer">Printer</option>
            <option value="other">Other</option>
          </select>
          <input className="input-field" placeholder="Terminal label" value={form.terminal_label} onChange={e=>setForm({...form, terminal_label:e.target.value})}/>
          <input className="input-field" placeholder="Station code" value={form.station_code} onChange={e=>setForm({...form, station_code:e.target.value})}/>
        </div>
        <button className="btn-primary" onClick={register} disabled={saving} style={{ marginTop:14, padding:'12px 20px' }}>
          {saving ? 'Registering…' : 'Register device'}
        </button>
        {lastToken && (
          <div style={{ marginTop:14, padding:14, background:'rgba(255,184,48,.08)', border:'1px solid rgba(255,184,48,.3)', borderRadius:10, fontFamily:"'DM Mono',monospace", fontSize:12, wordBreak:'break-all' }}>
            Auth token (copy now): <span style={{ color:'var(--accent-amber)' }}>{lastToken}</span>
          </div>
        )}
      </div>

      <div style={{ background:'var(--bg-panel)', border:'1px solid #3a2f0f', borderRadius:16, overflow:'hidden' }}>
        {loading ? <div style={{ padding:24 }}><Spinner/></div> : (
          <table style={{ width:'100%', borderCollapse:'collapse', fontSize:13 }}>
            <thead>
              <tr style={{ background:'#1a1608', color:'var(--text-muted)', textAlign:'left' }}>
                <th style={{ padding:'12px 16px' }}>UID</th>
                <th style={{ padding:'12px 16px' }}>Name</th>
                <th style={{ padding:'12px 16px' }}>Type</th>
                <th style={{ padding:'12px 16px' }}>Status</th>
                <th style={{ padding:'12px 16px' }}>Heartbeat</th>
                <th style={{ padding:'12px 16px' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {devices.map(d => (
                <tr key={d.id} style={{ borderTop:'1px solid #2a2410' }}>
                  <td style={{ padding:'12px 16px', fontFamily:"'DM Mono',monospace", fontSize:11 }}>{d.device_uid}</td>
                  <td style={{ padding:'12px 16px' }}>{d.name || '—'}</td>
                  <td style={{ padding:'12px 16px' }}>{d.device_type}</td>
                  <td style={{ padding:'12px 16px', color: d.status==='ACTIVE'?'var(--success)':'var(--accent-amber)' }}>{d.status}</td>
                  <td style={{ padding:'12px 16px' }}>{heartbeatAge(d.last_heartbeat)}</td>
                  <td style={{ padding:'12px 16px', display:'flex', gap:6 }}>
                    {d.status!=='ACTIVE' && <button className="btn-ghost" style={{ fontSize:11, padding:'6px 10px' }} onClick={()=>setStatus(d.id,'ACTIVE')}>Activate</button>}
                    {d.status==='ACTIVE' && <button className="btn-ghost" style={{ fontSize:11, padding:'6px 10px' }} onClick={()=>setStatus(d.id,'SUSPENDED')}>Suspend</button>}
                    <button className="btn-ghost" style={{ fontSize:11, padding:'6px 10px' }} onClick={()=>setStatus(d.id,'MAINTENANCE')}>Maintenance</button>
                  </td>
                </tr>
              ))}
              {!devices.length && <tr><td colSpan={6} style={{ padding:24, color:'var(--text-muted)' }}>No devices registered</td></tr>}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
