// ── Session Report Print Layout ──────────────────────────────────────────────
function SessionReportPrint({ report }) {
  const s = report.session;
  const fmtDt = d => d ? new Date(d).toLocaleString('en-GB', {
    day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'
  }) : '—';
  const code  = s.session_code || '—';
  const total = Number(report.grand_total || 0);

  return (
    <div id="print-area" style={{ fontFamily:"Arial,sans-serif", color:'#000', background:'#fff', padding:'24px 28px', maxWidth:480, margin:'0 auto', fontSize:13 }}>
      {/* Header */}
      <div style={{ textAlign:'center', borderBottom:'2px solid #000', paddingBottom:12, marginBottom:14 }}>
        <div style={{ fontSize:20, fontWeight:900, letterSpacing:2 }}>J11 AFCS</div>
        <div style={{ fontSize:11, color:'#555', letterSpacing:1 }}>J11 RAPID TRANSIT</div>
        <div style={{ fontSize:11, marginTop:4 }}>Automated Fare Collection System</div>
        <div style={{ fontWeight:700, fontSize:14, marginTop:8, letterSpacing:1 }}>CASHIER SESSION REPORT</div>
        <div style={{ fontSize:18, fontWeight:900, color:'#0d6e6e', marginTop:4 }}>{code}</div>
      </div>

      {/* Session info */}
      <table style={{ width:'100%', borderCollapse:'collapse', marginBottom:14, fontSize:12 }}>
        <tbody>
          {[
            ['Cashier Name', s.cashier_name],
            ['Username',     s.username],
            ['Terminal',     s.terminal],
            ['Session Code', code],
            ['Login Time',   fmtDt(s.started_at)],
            ['Logout Time',  fmtDt(s.ended_at)],
            ['Report Printed', fmtDt(report.printed_at)],
          ].map(([k,v]) => (
            <tr key={k}>
              <td style={{ padding:'4px 0', color:'#555', width:'45%' }}>{k}</td>
              <td style={{ padding:'4px 0', fontWeight:600 }}>{v}</td>
            </tr>
          ))}
        </tbody>
      </table>

      {/* Divider */}
      <div style={{ borderTop:'1px dashed #999', marginBottom:14 }}/>

      {/* Summary table */}
      <div style={{ fontWeight:700, fontSize:12, textTransform:'uppercase', letterSpacing:1, marginBottom:8 }}>Transaction Summary</div>
      <table style={{ width:'100%', borderCollapse:'collapse', fontSize:12, marginBottom:14 }}>
        <thead>
          <tr style={{ background:'#f0f0f0' }}>
            <th style={{ padding:'6px 8px', textAlign:'left', borderBottom:'1px solid #ccc' }}>Description</th>
            <th style={{ padding:'6px 8px', textAlign:'center', borderBottom:'1px solid #ccc' }}>Count</th>
            <th style={{ padding:'6px 8px', textAlign:'right', borderBottom:'1px solid #ccc' }}>Amount (TZS)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style={{ padding:'6px 8px', borderBottom:'1px solid #eee' }}>QR Student Tickets Sold</td>
            <td style={{ padding:'6px 8px', textAlign:'center', borderBottom:'1px solid #eee' }}>{report.qr_sales.count}</td>
            <td style={{ padding:'6px 8px', textAlign:'right', borderBottom:'1px solid #eee', fontFamily:'monospace' }}>{Number(report.qr_sales.total).toLocaleString()}</td>
          </tr>
          <tr>
            <td style={{ padding:'6px 8px', borderBottom:'1px solid #eee' }}>Card Top-Ups Processed</td>
            <td style={{ padding:'6px 8px', textAlign:'center', borderBottom:'1px solid #eee' }}>{report.topups.count}</td>
            <td style={{ padding:'6px 8px', textAlign:'right', borderBottom:'1px solid #eee', fontFamily:'monospace' }}>{Number(report.topups.total).toLocaleString()}</td>
          </tr>
          {report.card_sales.count > 0 && (
            <tr>
              <td style={{ padding:'6px 8px', borderBottom:'1px solid #eee' }}>Card Registrations</td>
              <td style={{ padding:'6px 8px', textAlign:'center', borderBottom:'1px solid #eee' }}>{report.card_sales.count}</td>
              <td style={{ padding:'6px 8px', textAlign:'right', borderBottom:'1px solid #eee', fontFamily:'monospace' }}>—</td>
            </tr>
          )}
        </tbody>
        <tfoot>
          <tr style={{ background:'#f9f9f9', fontWeight:700 }}>
            <td style={{ padding:'8px', borderTop:'2px solid #000' }}>TOTAL TRANSACTIONS</td>
            <td style={{ padding:'8px', textAlign:'center', borderTop:'2px solid #000' }}>{report.total_txns}</td>
            <td style={{ padding:'8px', textAlign:'right', borderTop:'2px solid #000', fontFamily:'monospace', fontSize:14 }}>{total.toLocaleString()}</td>
          </tr>
        </tfoot>
      </table>

      {/* Signature */}
      <div style={{ borderTop:'1px dashed #999', paddingTop:14, marginTop:4 }}>
        <div style={{ display:'flex', justifyContent:'space-between', alignItems:'flex-end' }}>
          <div>
            <div style={{ fontSize:11, color:'#555', marginBottom:24 }}>Cashier Signature</div>
            <div style={{ borderTop:'1px solid #000', width:180, paddingTop:4, fontSize:11 }}>{s.cashier_name}</div>
          </div>
          <div style={{ textAlign:'right' }}>
            <div style={{ fontSize:11, color:'#555', marginBottom:24 }}>Supervisor Signature</div>
            <div style={{ borderTop:'1px solid #000', width:160, paddingTop:4, fontSize:11 }}>&nbsp;</div>
          </div>
        </div>
        <div style={{ textAlign:'center', marginTop:16, fontSize:9, color:'#aaa' }}>
          J11 AFCS — {code} — Generated {fmtDt(report.printed_at)}
        </div>
      </div>
    </div>
  );
}

