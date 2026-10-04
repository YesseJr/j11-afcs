// ── Fare Products Management (Admin) ─────────────────────────────────────────
function FareManagementScreen({ toast }) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm] = useState({ code:'', name:'', amount:'', passenger_category:'STUDENT', media_types:'QR', validity_hours:'24', priority:'100', currency:'TZS' });
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    const res = await apiGet('fares', 'list', { all: '1' });
    setLoading(false);
    if (res.success) setItems(res.data || []);
    else toast(res.message, 'error');
  }, []);

  useEffect(() => { load(); }, [load]);

  const save = async () => {
    if (!form.code || !form.name || form.amount === '') { toast('Code, name and amount required', 'error'); return; }
    setSaving(true);
    const res = await api('fares', 'save', {
      code: form.code.trim().toUpperCase(),
      name: form.name.trim(),
      amount: parseFloat(form.amount),
      passenger_category: form.passenger_category,
      media_types: form.media_types,
      validity_hours: parseInt(form.validity_hours || '24', 10),
      priority: parseInt(form.priority || '100', 10),
      currency: form.currency || 'TZS',
      product_type: 'FLAT',
      active: 1,
    });
    setSaving(false);
    if (!res.success) { toast(res.message, 'error'); return; }
    toast('Fare product saved', 'ok');
    setForm({ code:'', name:'', amount:'', passenger_category:'STUDENT', media_types:'QR', validity_hours:'24', priority:'100', currency:'TZS' });
    load();
  };

  const deactivate = async (id) => {
    const res = await api('fares', 'deactivate', { id });
    if (!res.success) { toast(res.message, 'error'); return; }
    toast('Fare deactivated', 'ok');
    load();
  };

  return (
    <div className="fade-in" style={{ flex:1, overflowY:'auto', padding:'28px', display:'flex', flexDirection:'column', gap:24 }}>
      <div>
        <div style={{ fontFamily:"'Syne',sans-serif", fontWeight:800, fontSize:22, color:'var(--text-primary)' }}>Fare Products</div>
        <div style={{ fontSize:13, color:'var(--text-muted)', marginTop:4 }}>Configure fares without code changes. Active products feed ticket sales.</div>
      </div>

      <div style={{ background:'var(--bg-panel)', border:'1px solid #3a2f0f', borderRadius:16, padding:20 }}>
        <div style={{ fontSize:12, fontWeight:700, color:'var(--accent-amber)', letterSpacing:1, textTransform:'uppercase', marginBottom:14 }}>Add / update product</div>
        <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:12 }}>
          <input className="input-field" placeholder="Code e.g. STUDENT_FLAT" value={form.code} onChange={e=>setForm({...form, code:e.target.value})}/>
          <input className="input-field" placeholder="Display name" value={form.name} onChange={e=>setForm({...form, name:e.target.value})}/>
          <input className="input-field" placeholder="Amount" type="number" value={form.amount} onChange={e=>setForm({...form, amount:e.target.value})}/>
          <select className="input-field" value={form.passenger_category} onChange={e=>setForm({...form, passenger_category:e.target.value})}>
            <option value="STUDENT">Student</option>
            <option value="ADULT">Adult</option>
            <option value="CHILD">Child</option>
            <option value="SENIOR">Senior</option>
            <option value="STAFF">Staff</option>
            <option value="ALL">All</option>
          </select>
          <input className="input-field" placeholder="Media e.g. QR,CARD" value={form.media_types} onChange={e=>setForm({...form, media_types:e.target.value})}/>
          <input className="input-field" placeholder="Validity hours" type="number" value={form.validity_hours} onChange={e=>setForm({...form, validity_hours:e.target.value})}/>
        </div>
        <button className="btn-primary" onClick={save} disabled={saving} style={{ marginTop:14, padding:'12px 20px' }}>
          {saving ? 'Saving…' : 'Save fare product'}
        </button>
      </div>

      <div style={{ background:'var(--bg-panel)', border:'1px solid #3a2f0f', borderRadius:16, overflow:'hidden' }}>
        {loading ? <div style={{ padding:24 }}><Spinner/></div> : (
          <table style={{ width:'100%', borderCollapse:'collapse', fontSize:13 }}>
            <thead>
              <tr style={{ background:'#1a1608', color:'var(--text-muted)', textAlign:'left' }}>
                <th style={{ padding:'12px 16px' }}>Code</th>
                <th style={{ padding:'12px 16px' }}>Name</th>
                <th style={{ padding:'12px 16px' }}>Category</th>
                <th style={{ padding:'12px 16px' }}>Media</th>
                <th style={{ padding:'12px 16px' }}>Amount</th>
                <th style={{ padding:'12px 16px' }}>Status</th>
                <th style={{ padding:'12px 16px' }}></th>
              </tr>
            </thead>
            <tbody>
              {items.map(f => (
                <tr key={f.id} style={{ borderTop:'1px solid #2a2410' }}>
                  <td style={{ padding:'12px 16px', fontFamily:"'DM Mono',monospace", color:'var(--accent-amber)' }}>{f.code}</td>
                  <td style={{ padding:'12px 16px' }}>{f.name}</td>
                  <td style={{ padding:'12px 16px' }}>{f.passenger_category}</td>
                  <td style={{ padding:'12px 16px' }}>{f.media_types}</td>
                  <td style={{ padding:'12px 16px', fontFamily:"'DM Mono',monospace" }}>{Number(f.amount).toLocaleString()} {f.currency}</td>
                  <td style={{ padding:'12px 16px' }}>
                    <span style={{ color: Number(f.active) ? 'var(--success)' : 'var(--text-dim)' }}>{Number(f.active) ? 'Active' : 'Off'}</span>
                  </td>
                  <td style={{ padding:'12px 16px' }}>
                    {Number(f.active) ? (
                      <button className="btn-ghost" style={{ fontSize:11, padding:'6px 10px' }} onClick={()=>deactivate(f.id)}>Deactivate</button>
                    ) : null}
                  </td>
                </tr>
              ))}
              {!items.length && <tr><td colSpan={7} style={{ padding:24, color:'var(--text-muted)' }}>No fare products yet</td></tr>}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
