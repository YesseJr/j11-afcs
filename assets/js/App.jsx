// ── Root App ──────────────────────────────────────────────────────────────────
function App() {
  // mode: 'login' | 'standby' | 'active' | 'admin'
  const [mode,   setMode]   = useState('login');
  const [screen, setScreen] = useState('DASHBOARD');
  const [user,   setUser]   = useState(null);
  const [toast,  setToast]  = useState({ msg:'', type:'ok' });
  const [stats,  setStats]  = useState({ qr_qty:0, qr_total:0, topup_qty:0, topup_total:0 });
  const [showEndModal, setShowEndModal] = useState(false);
  const [startingSession,  setStartingSession]  = useState(false);
  const [lastSync, setLastSync] = useState(null);
  const [syncing,  setSyncing]  = useState(false);
  const [blockedSession, setBlockedSession] = useState(null); // { cashier_name, started_at } — another cashier's open session on this terminal

  // Check existing PHP session on page load
  useEffect(() => {
    apiGet('auth', 'me').then(res => {
      if (res.success) {
        setUser(res.data.user);
        if (res.data.user?.role === 'admin') {
          setMode('admin'); setScreen('ADMIN_DASHBOARD');
        } else if (res.data.session_id) {
          setMode('active');
          refreshStats();
        } else {
          setMode('standby');
        }
      }
    });
  }, []);

  const showToast = (msg, type='ok') => setToast({ msg, type });

  const refreshStats = useCallback(async () => {
    setSyncing(true);
    const res = await apiGet('reports', 'session');
    if (res.success) setStats({
      qr_qty:      res.data.qr_sales.count,
      qr_total:    res.data.qr_sales.total,
      topup_qty:   res.data.topups.count,
      topup_total: res.data.topups.total,
    });
    setLastSync(new Date());
    setSyncing(false);
  }, []);

  // ── Real-time sync ──────────────────────────────────────────────────────────
  // Every terminal polls the server on a short interval so a transaction or
  // record made anywhere (this terminal, another cashier, another gate) shows
  // up here automatically — no manual refresh needed. Also re-syncs instantly
  // whenever the cashier returns to this tab after being away.
  useEffect(() => {
    if (mode !== 'active') return;
    const t = setInterval(refreshStats, 5000);
    const onVisible = () => { if (document.visibilityState === 'visible') refreshStats(); };
    document.addEventListener('visibilitychange', onVisible);
    // Cache offline policies + flush any queued local events when connectivity returns
    if (typeof afcsRefreshOfflinePolicies === 'function') afcsRefreshOfflinePolicies('cashier_terminal');
    if (typeof afcsFlushOutbox === 'function') afcsFlushOutbox();
    const onOnline = () => { afcsFlushOutbox && afcsFlushOutbox(); afcsRefreshOfflinePolicies && afcsRefreshOfflinePolicies('cashier_terminal'); };
    window.addEventListener('online', onOnline);
    return () => {
      clearInterval(t);
      document.removeEventListener('visibilitychange', onVisible);
      window.removeEventListener('online', onOnline);
    };
  }, [mode, refreshStats]);

  const handleLogin = (userData) => {
    setUser(userData);

    // Force password change for seeded / admin-flagged accounts
    if (userData.must_change_password) {
      setMode('change_password');
      return;
    }

    if (userData.role === 'admin') {
      setMode('admin'); setScreen('ADMIN_DASHBOARD');
      return;
    }

    if (userData.resume_session) {
      // Same cashier picking back up a session they logged out of earlier —
      // no need to hit standby at all, just continue where they left off.
      setBlockedSession(null);
      setScreen('DASHBOARD');
      setMode('active');
      refreshStats();
      showToast(`Welcome back — resumed your session from ${new Date(userData.resume_session.started_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}`, 'ok');
      return;
    }

    if (userData.blocked_session) {
      // Another cashier left this terminal's session open — this cashier can
      // log in and see that, but can't start their own or touch the other one.
      setBlockedSession(userData.blocked_session);
      setMode('standby');
      return;
    }

    setBlockedSession(null);
    setMode('standby');
  };

  const handleStartSession = async () => {
    setStartingSession(true);
    const res = await api('auth', 'start_session', {});
    setStartingSession(false);
    if (!res.success) { showToast(res.message, 'error'); return; }
    setScreen('DASHBOARD');
    setStats({ qr_qty:0, qr_total:0, topup_qty:0, topup_total:0 });
    setMode('active');
    showToast(`Session started — ${user?.terminal}`, 'ok');
  };

  const handleEndSession = async () => {
    // Note: end_session was already called by EndSessionModal.confirmEnd before
    // invoking this callback — do NOT call it again here.
    setShowEndModal(false);
    setMode('standby');
    setScreen('DASHBOARD');
    showToast('Session ended. Terminal in standby.', 'ok');
  };

  const handleLogout = async () => {
    // Logging out no longer ends an active cashier session server-side — it
    // stays open so the same cashier can resume it on their next login.
    await api('auth', 'logout', {});
    setUser(null);
    setBlockedSession(null);
    setMode('login');
  };

  const screenProps = { toast: showToast, onStatsUpdate: refreshStats };

  const screens = {
    DASHBOARD:           <DashboardScreen user={user} onNav={setScreen} stats={stats}/>,
    QR_SALE:             <QRSaleScreen {...screenProps}/>,
    CARD_SALE:           <CardSaleScreen {...screenProps}/>,
    TOPUP:               <TopupScreen {...screenProps}/>,
    CARD_DETAILS:        <CardDetailsScreen {...screenProps}/>,
    SESSION_REPORT:      <SessionReportScreen {...screenProps}/>,
    LOGIN_LOGOUT_REPORT: <LoginReportScreen {...screenProps}/>,
    CARD_ACTIVATION:     <CardActionScreen {...screenProps} title="Card Activation"   icon="🔓" action="activate"   allowedStatuses={['inactive','new']} confirmColor="var(--success)"      confirmLabel="ACTIVATE CARD"/>,
    CARD_DEACTIVATION:   <CardActionScreen {...screenProps} title="Card Deactivation" icon="🔒" action="deactivate" allowedStatuses={['active']}         confirmColor="var(--accent-red)"   confirmLabel="DEACTIVATE CARD" reasonPresets={['Lost card','Stolen card','Damaged card','Customer request','Fraud suspected']}/>,
    BALANCE_TRANSFER:    <BalanceTransferScreen {...screenProps}/>,
    CARD_REISSUE:        <PlaceholderScreen title="Card Reissue"     icon="🔁" desc="Scan old card to migrate balance, then scan the new blank card."/>,
    PENALTY_FLAG:        <CardActionScreen {...screenProps} title="Penalty Flag" icon="🚨" action="penalty" allowedStatuses={['active','inactive']} confirmColor="var(--accent-amber)" confirmLabel="SET PENALTY FLAG" reasonPresets={['Fare evasion','Gate tailgating','Card misuse']}/>,
  };

  // ── Admin: an entirely separate console, not the cashier UI with extras ──────
  const adminScreens = {
    ADMIN_DASHBOARD:     <AdminDashboardScreen user={user} onNav={setScreen}/>,
    SESSION_MANAGEMENT:  <SessionManagementScreen toast={showToast}/>,
    CARD_INVENTORY:      <CardInventoryScreen toast={showToast} user={user}/>,
    FARE_MANAGEMENT:     <FareManagementScreen toast={showToast}/>,
    DEVICE_MANAGEMENT:   <DeviceManagementScreen toast={showToast}/>,
    SETTLEMENT:          <SettlementScreen toast={showToast}/>,
    SECURITY_SETTINGS:   <SecuritySettingsScreen user={user} toast={showToast}/>,
  };

  if (mode === 'change_password') return (
    <>
      <Toast msg={toast.msg} type={toast.type} onClose={() => setToast({ msg:'', type:'ok' })}/>
      <ChangePasswordScreen
        user={user}
        forced={!!user?.must_change_password}
        toast={showToast}
        onDone={() => {
          // Refresh user flag and continue normal flow
          const u = { ...user, must_change_password: 0 };
          setUser(u);
          if (u.role === 'admin') { setMode('admin'); setScreen('ADMIN_DASHBOARD'); }
          else { setMode('standby'); }
        }}
      />
    </>
  );

  if (mode === 'login') return (
    <>
      <Toast msg={toast.msg} type={toast.type} onClose={() => setToast({ msg:'', type:'ok' })}/>
      <LoginScreen onLogin={handleLogin}/>
    </>
  );

  if (mode === 'admin') return (
    <>
      <Toast msg={toast.msg} type={toast.type} onClose={() => setToast({ msg:'', type:'ok' })}/>
      <div style={{ display:'flex', flexDirection:'column', height:'100vh', overflow:'hidden', background:'var(--bg-deep)' }}>
        <AdminTopBar user={user} onNav={setScreen} screen={screen} onLogout={handleLogout}/>
        <div style={{ flex:1, display:'flex', overflow:'hidden' }}>
          {adminScreens[screen] || adminScreens.ADMIN_DASHBOARD}
        </div>
        <div style={{ background:'var(--bg-deep)',borderTop:'1px solid #3a2f0f',padding:'0 24px',height:32,flexShrink:0,display:'flex',alignItems:'center',fontSize:11,color:'var(--text-dim)',fontFamily:"'DM Mono',monospace" }}>
          J11 AFCS — Management Console v1.0
        </div>
      </div>
    </>
  );

  if (mode === 'standby') return (
    <>
      <Toast msg={toast.msg} type={toast.type} onClose={() => setToast({ msg:'', type:'ok' })}/>
      <StandbyScreen
        user={user}
        onStartSession={handleStartSession}
        onLogout={handleLogout}
        startingSession={startingSession}
        blockedSession={blockedSession}
      />
    </>
  );

  // mode === 'active'
  return (
    <>
      <Toast msg={toast.msg} type={toast.type} onClose={() => setToast({ msg:'', type:'ok' })}/>
      {showEndModal && (
        <EndSessionModal
          onConfirm={handleEndSession}
          onCancel={() => setShowEndModal(false)}
        />
      )}
      <div style={{ display:'flex', flexDirection:'column', height:'100vh', overflow:'hidden' }}>
        <TopBar
          user={user}
          onEndSession={() => setShowEndModal(true)}
          onNav={setScreen}
          screen={screen}
        />
        <div style={{ flex:1, display:'flex', overflow:'hidden' }}>
          {screens[screen] || screens.DASHBOARD}
        </div>
        <StatusBar lastSync={lastSync} syncing={syncing} onSyncNow={refreshStats}/>
      </div>
    </>
  );
}

