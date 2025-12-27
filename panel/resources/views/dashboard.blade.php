<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WA Gateway Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <style>
        body {
            background: radial-gradient(circle at 20% 20%, #e3f2fd 0, transparent 25%),
                        radial-gradient(circle at 80% 0%, #f3e8ff 0, transparent 20%),
                        #f5f6fa;
        }
        html, body { min-height: 100%; }
        .content-wrapper { min-height: 100vh; }
        .device-card {
            transition: transform .15s ease, box-shadow .15s ease;
            border-radius: 16px;
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            border: 1px solid #e5e7eb;
        }
        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
        }
        .hidden { display: none !important; }
        .badge-pill {
            border-radius: 999px;
            font-size: 11px;
            padding: 4px 10px;
        }
        .modal-content {
            border-radius: 16px;
            overflow: hidden;
        }
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        .gap-2 { gap: .5rem; }
        .gap-3 { gap: .75rem; }
    </style>
</head>
<body class="bg-light">
    <div class="wrapper">
        <div class="content-wrapper">
            <div class="content pt-4">
                <div class="container-fluid">
                    <div class="card card-outline card-primary shadow-sm mb-3">
                        <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                            <div class="mb-2 mb-md-0">
                                <p class="text-muted mb-0">WA Gateway</p>
                                <h1 class="h3 font-weight-bold mb-0">Control Panel</h1>
                            </div>
            <div class="d-flex align-items-start gap-3">
                <div class="text-right small">
                    <p class="text-muted mb-1"><i class="fas fa-link me-1"></i>API Base</p>
                    <p class="font-weight-bold mb-1">{{ $gatewayConfig['base'] }}</p>
                    @if($gatewayConfig['key'])
                        @php
                            $plainKey = (string) $gatewayConfig['key'];
                            $masked = str_repeat('•', max(strlen($plainKey) - 3, 3));
                        @endphp
                        <div class="text-muted">
                            <span>Master Key:</span>
                            <span id="gateway-key-masked" class="font-mono">{{ $masked }}</span>
                            <span id="gateway-key-plain" class="font-mono hidden">{{ $plainKey }}</span>
                            <button type="button" id="btn-toggle-gateway-key" class="btn btn-link btn-sm p-0 ml-2">Show</button>
                            <button type="button" id="btn-copy-gateway-key" class="btn btn-link btn-sm p-0 ml-2">Copy</button>
                        </div>
                    @else
                        <p class="text-warning mb-0"><i class="fas fa-exclamation-triangle me-1"></i>API Key kosong (akses publik)</p>
                    @endif
                </div>
                <div class="btn-group">
                    <a href="{{ route('devices.manage') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-microchip me-1"></i> Device Management</a>
                    @if(!empty($isAdmin))
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-users me-1"></i> Users</a>
                    @endif
                    <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-user me-1"></i> Profil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i> Logout</button>
                    </form>
                </div>
            </div>
                        </div>
                    </div>

                    @if($statusMessage)
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-1"></i>{{ $statusMessage }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show">
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            <ul class="mb-0 pl-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if(!empty($permissionWarnings))
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-1"></i>Beberapa izin/berkas perlu dicek agar non-root bisa jalan:
                            <ul class="mb-0 pl-3">
                                @foreach ($permissionWarnings as $warn)
                                    <li>{{ $warn }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body">
                                    <p class="text-muted text-sm mb-1"><i class="fas fa-signal me-1 text-primary"></i>API Status</p>
                @php
                    $apiStatusLabel = 'Tidak diketahui';
                    $apiStatusClass = 'text-amber-600';
                    $apiStatusDetail = 'Cek konfigurasi API.';

                    if ($apiError) {
                        $apiStatusLabel = 'Tidak bisa dijangkau';
                        $apiStatusClass = 'text-rose-600';
                        $apiStatusDetail = $apiError;
                    } elseif ($health) {
                        $apiStatusLabel = 'Online';
                        $apiStatusClass = 'text-emerald-600';
                        $apiStatusDetail = json_encode($health);
                    }
                @endphp
                                    <h5 id="api-status-label" class="{{ $apiStatusClass }} font-weight-bold">{{ $apiStatusLabel }}</h5>
                                    <p id="api-status-detail" class="text-muted small mb-0">{{ $apiStatusDetail }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted text-sm mb-1"><i class="fas fa-server me-1 text-primary"></i>NPM Server</p>
                        @if($npmStatus['running'])
                            @if(isset($npmStatus['source']) && $npmStatus['source'] === 'inferred')
                                <p class="text-success font-weight-bold mb-1">Running (external)</p>
                            @elseif($npmStatus['pid'])
                                <p class="text-success font-weight-bold mb-1">Running (PID {{ $npmStatus['pid'] }})</p>
                            @else
                                <p class="text-success font-weight-bold mb-1">Running</p>
                            @endif
                        @else
                            <p class="text-danger font-weight-bold mb-1">Stopped</p>
                        @endif
                                        <p class="text-muted small mb-0"><strong>Cmd:</strong> {{ $npmStatus['command'] }}</p>
                                        <p class="text-muted small mb-0"><strong>Dir:</strong> {{ $npmStatus['workingDir'] }}</p>
                                    </div>
                                    <div class="btn-group">
                                        <form method="POST" action="{{ route('server.start') }}">
                                            @csrf
                                            <button class="btn btn-success btn-sm" {{ $npmStatus['running'] ? 'disabled' : '' }}>
                                                <i class="fas fa-play me-1"></i>Start
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('server.stop') }}">
                                            @csrf
                                            <button class="btn btn-danger btn-sm" {{ $npmStatus['running'] ? '' : 'disabled' }}>
                                                <i class="fas fa-stop me-1"></i>Stop
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="card-footer text-muted small">
                                    <i class="fas fa-file-alt me-1"></i>Log: {{ $npmStatus['logFile'] }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <p class="text-muted text-sm mb-0"><i class="fas fa-exclamation-triangle me-1 text-danger"></i>Log Error Terakhir</p>
                                            <h5 class="mb-0">Server Log</h5>
                                        </div>
                                        <span class="badge bg-light text-muted">{{ $npmStatus['logFile'] }}</span>
                                    </div>
                                    @if($logTail)
                                        <pre class="bg-dark text-light p-3 rounded small mb-0" style="max-height: min(40vh, 320px); overflow:auto; white-space:pre-wrap;">{{ $logTail }}</pre>
                                    @else
                                        <p class="text-muted small mb-0">Belum ada log error atau file log belum tersedia.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>
    <div class="modal fade" id="modal-create-device" tabindex="-1" aria-labelledby="modalCreateLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateLabel">Create Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('devices.create') }}" class="row g-3 needs-validation" novalidate>
                        @csrf
                        <input type="hidden" name="mode" id="create-mode" value="qr">
                        <div class="col-12">
                            <label class="form-label text-muted small">Device Name</label>
                            <input name="device_name" type="text" class="form-control" placeholder="mis. TopSETTING" required>
                            <div class="invalid-feedback">Nama device wajib diisi.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Nomor WA Device</label>
                            <input name="device_phone" type="text" class="form-control" placeholder="62812xxxxxx" required>
                            <div class="invalid-feedback">Nomor WA wajib diisi.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Metode Koneksi</label>
                            <div class="btn-group w-100" role="group" aria-label="Metode koneksi">
                                <button type="button" class="btn btn-outline-primary btn-sm w-50 active" data-mode="qr">Scan QR</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm w-50" data-mode="code">Pairing Code</button>
                            </div>
                            <small class="text-muted d-block mt-1">Pilih Pairing Code untuk link lewat nomor WhatsApp (tanpa scan QR).</small>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-success btn-sm">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-settings" tabindex="-1" aria-labelledby="modalSettingsLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSettingsLabel">Device Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="settings-content" class="text-muted small">Pilih device untuk mengatur.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-group-finder" tabindex="-1" aria-labelledby="modalGroupLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGroupLabel">Group ID Finder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="group-finder-content" class="text-muted small">Pilih device.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-message-log" tabindex="-1" aria-labelledby="modalLogLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLogLabel">Message Status Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="message-log-content" class="text-muted small">Pilih device.</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const sessionsBaseUrl = '{{ url('/sessions') }}';
            const autoRefreshReason = @json($autoRefresh ?? null);
            const qrPresent = @json((bool) $qrData);

            // Master key toggle/copy
            const keyMasked = document.getElementById('gateway-key-masked');
            const keyPlain = document.getElementById('gateway-key-plain');
            const btnToggleKey = document.getElementById('btn-toggle-gateway-key');
            const btnCopyKey = document.getElementById('btn-copy-gateway-key');

            const getPlainKey = () => keyPlain?.textContent?.trim() || '';
            btnToggleKey?.addEventListener('click', () => {
                if (!keyMasked || !keyPlain) return;
                const isHidden = keyPlain.classList.contains('hidden');
                keyPlain.classList.toggle('hidden', !isHidden);
                keyMasked.classList.toggle('hidden', isHidden);
                btnToggleKey.textContent = isHidden ? 'Hide' : 'Show';
            });
            btnCopyKey?.addEventListener('click', async () => {
                const val = getPlainKey();
                if (!val) return;
                try {
                    await navigator.clipboard.writeText(val);
                    btnCopyKey.textContent = 'Copied';
                    setTimeout(() => (btnCopyKey.textContent = 'Copy'), 1200);
                } catch {
                    // fallback
                    const tmp = document.createElement('textarea');
                    tmp.value = val;
                    document.body.appendChild(tmp);
                    tmp.select();
                    document.execCommand('copy');
                    document.body.removeChild(tmp);
                    btnCopyKey.textContent = 'Copied';
                    setTimeout(() => (btnCopyKey.textContent = 'Copy'), 1200);
                }
            });

            const apiStatusLabel = document.getElementById('api-status-label');
            const apiStatusDetail = document.getElementById('api-status-detail');
            const apiStatusUrl = '{{ route('api.status') }}';
            const deviceStatusUrl = '{{ route('devices.status') }}';
            const refreshMs = 5000;
            const shouldAutoRefreshServer = autoRefreshReason === 'server-start';
            const shouldAutoRefreshDevice = false;
            const reloadDelayMs = 6000;
            let reloadTimer = null;

            const statusClass = {
                online: 'text-emerald-600',
                error: 'text-rose-600',
                unknown: 'text-amber-600',
            };

            const scheduleReload = () => {
                if (reloadTimer) return;
                reloadTimer = setTimeout(() => window.location.reload(), reloadDelayMs);
            };

            const setApiStatus = ({ status, health, message }) => {
                if (!apiStatusLabel || !apiStatusDetail) return;
                const state = status || 'unknown';
                const labelMap = { online: 'Online', error: 'Tidak bisa dijangkau', unknown: 'Tidak diketahui' };
                const label = labelMap[state] || labelMap.unknown;
                const detail = state === 'online' && health ? JSON.stringify(health) : (message || 'Cek konfigurasi API.');
                apiStatusLabel.textContent = label;
                apiStatusLabel.className = `${statusClass[state] || statusClass.unknown} font-semibold`;
                apiStatusDetail.textContent = detail;
                apiStatusDetail.className = 'text-xs text-slate-500 mt-1 break-all';

                if (shouldAutoRefreshServer && state === 'online') {
                    scheduleReload();
                }
            };

            const lastStatuses = new Map();
            document.querySelectorAll('.device-card').forEach((card) => {
                const id = card.getAttribute('data-device');
                const st = card.getAttribute('data-status') || 'disconnected';
                if (id) lastStatuses.set(id, st);
            });

            const applyDeviceStatus = (device) => {
                const card = document.querySelector(`.device-card[data-device=\"${CSS.escape(device.id)}\"]`);
                if (!card) return;
                const badge = card.querySelector('.device-badge');
                const phone = card.querySelector('.device-phone');

                const status = device.status || 'disconnected';
                const prevStatus = lastStatuses.get(device.id);
                lastStatuses.set(device.id, status);
                card.setAttribute('data-status', status);
                const label = status.charAt(0).toUpperCase() + status.slice(1);
                if (badge) {
                    badge.textContent = label;
                    badge.classList.remove('bg-emerald-50','text-emerald-700','border-emerald-200','bg-amber-50','text-amber-700','border-amber-200','bg-rose-50','text-rose-700','border-rose-200');
                    if (status === 'connected') badge.classList.add('bg-emerald-50','text-emerald-700','border-emerald-200');
                    else if (status === 'connecting') badge.classList.add('bg-amber-50','text-amber-700','border-amber-200');
                    else badge.classList.add('bg-rose-50','text-rose-700','border-rose-200');
                }
                if (phone) {
                    const clean = (device.user?.id || '').replace(/@.*/, '');
                    phone.textContent = clean || 'Not linked';
                }
                const cleanPhone = (device.user?.id || '').replace(/@.*/, '');
                card.setAttribute('data-phone', cleanPhone || '');

                if (shouldAutoRefreshDevice && prevStatus !== 'connected' && status === 'connected') {
                    scheduleReload();
                }
            };

            const refreshDeviceStatuses = async () => {
                if (document.hidden) return;
                try {
                    const response = await fetch(deviceStatusUrl, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();
                    const devices = Array.isArray(data.devices) ? data.devices : [];
                    devices.forEach(applyDeviceStatus);
                } catch (error) {
                    console.error('Gagal refresh device statuses:', error);
                }
            };

            const refreshApiStatus = async () => {
                if (document.hidden) return;
                try {
                    const response = await fetch(apiStatusUrl, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    const data = await response.json();
                    setApiStatus(data);
                } catch (error) {
                    setApiStatus({ status: 'error', message: 'Tidak bisa menjangkau API.' });
                }
            };

            // Modals
            const modalCreateEl = document.getElementById('modal-create-device');
            const modalCreate = modalCreateEl ? new bootstrap.Modal(modalCreateEl) : null;
            const btnCreate = document.getElementById('btn-create-device');
            const modeInput = document.getElementById('create-mode');
            const modeButtons = document.querySelectorAll('[data-mode]');
            const modalSettingsEl = document.getElementById('modal-settings');
            const modalSettings = modalSettingsEl ? new bootstrap.Modal(modalSettingsEl) : null;
            const settingsContent = document.getElementById('settings-content');
            const modalGroupEl = document.getElementById('modal-group-finder');
            const modalGroup = modalGroupEl ? new bootstrap.Modal(modalGroupEl) : null;
            const groupContent = document.getElementById('group-finder-content');
            const modalMessageLogEl = document.getElementById('modal-message-log');
            const modalMessageLog = modalMessageLogEl ? new bootstrap.Modal(modalMessageLogEl) : null;
            const messageLogContent = document.getElementById('message-log-content');

            const show = (modal) => modal?.show();
            const hide = (modal) => modal?.hide();

            btnCreate?.addEventListener('click', () => show(modalCreate));
            modeButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    modeButtons.forEach((b) => b.classList.remove('active', 'btn-primary'));
                    modeButtons.forEach((b) => b.classList.add('btn-outline-secondary'));
                    btn.classList.add('active', 'btn-primary');
                    btn.classList.remove('btn-outline-secondary');
                    const mode = btn.getAttribute('data-mode') || 'qr';
                    if (modeInput) modeInput.value = mode;
                });
            });

            // Connect / QR behavior:
            // - If connected/connecting -> confirm restart and submit to sessions.restart
            // - If disconnected -> start session as usual
            document.querySelectorAll('form.form-connect').forEach((form) => {
                form.addEventListener('submit', (e) => {
                    const session = form.getAttribute('data-session') || '';
                    if (!session) return;
                    const card = document.querySelector(`.device-card[data-device=\"${CSS.escape(session)}\"]`);
                    const status = card?.getAttribute('data-status') || 'disconnected';
                    const linkedPhone = (card?.getAttribute('data-phone') || '').trim();

                    // If device is still connecting and not linked yet, prioritize Scan QR flow:
                    // restart session to trigger QR refresh without asking for confirmation.
                    if (status === 'connecting' && !linkedPhone) {
                        form.setAttribute('action', '{{ route('sessions.restart') }}');
                        return;
                    }

                    if (status !== 'disconnected') {
                        const ok = confirm('Perangkat dalam kondisi Aktif, apakah ingin Restart Perangkat?');
                        if (!ok) {
                            e.preventDefault();
                            return;
                        }
                        form.setAttribute('action', '{{ route('sessions.restart') }}');
                    } else {
                        form.setAttribute('action', '{{ route('sessions.start') }}');
                    }
                });
            });

            document.querySelectorAll('.btn-open-settings').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const session = btn.getAttribute('data-session');
                    if (!session || !settingsContent) return;
                    const card = document.querySelector(`.device-card[data-device=\"${CSS.escape(session)}\"]`);
                    if (!card) return;

                    const name = card.getAttribute('data-name') || '';
                    const webhook = card.getAttribute('data-webhook') || '';
                    const apiKeyStored = card.getAttribute('data-api-key') || '';
                    const incoming = card.getAttribute('data-incoming') === '1';
                    const autoreply = card.getAttribute('data-autoreply') === '1';
                    const tracking = card.getAttribute('data-tracking') === '1';
                    const deviceStatus = card.getAttribute('data-device-status') === '1';
                    const csrf = '{{ csrf_token() }}';

                    settingsContent.innerHTML = `
                        <form method=\"POST\" action=\"${'{{ url('/sessions') }}'}/${encodeURIComponent(session)}/config\" class=\"row g-3 needs-validation\" novalidate>
                            <input type=\"hidden\" name=\"_token\" value=\"{{ csrf_token() }}\" />
                            <div class=\"col-12\">
                                <label class=\"form-label text-muted small\">Device Name</label>
                                <input name=\"device_name\" type=\"text\" value=\"${name.replace(/\\\"/g,'&quot;')}\" class=\"form-control\" placeholder=\"Nama device\">
                            </div>
                            <div class=\"col-12\">
                                <label class=\"form-label text-muted small\">Webhook URL (Incoming & Auto Reply)</label>
                                <div class=\"input-group\">
                                    <input id=\"webhook_base_url\" name=\"webhook_base_url\" type=\"text\" value=\"${webhook.replace(/\\\"/g,'&quot;')}\" class=\"form-control\" required>
                                    <button type=\"button\" class=\"btn btn-outline-secondary btn-test-webhook\" data-type=\"base\" data-session=\"${encodeURIComponent(session)}\">Test</button>
                                </div>
                                <p class=\"text-muted small mb-0 mt-1\">Endpoint test: <span class=\"text-monospace\">/message</span></p>
                                <p id=\"test-result-base\" class=\"small mt-1\"></p>
                            </div>
                            <div class=\"col-12\">
                                <label class=\"form-label text-muted small\">API Key</label>
                                <div class=\"input-group\">
                                    <input id=\"api_key\" name=\"api_key\" type=\"text\" value=\"${apiKeyStored.replace(/\\\"/g,'&quot;')}\" class=\"form-control\" placeholder=\"API key\">
                                    <button type=\"button\" id=\"btn-generate-apikey\" class=\"btn btn-dark\">Generate</button>
                                </div>
                            </div>
                            <div class=\"col-12 d-flex flex-wrap gap-3\">
                                <div class=\"form-check\">
                                    <input class=\"form-check-input\" type=\"checkbox\" name=\"incoming_enabled\" value=\"1\" id=\"incoming_enabled\" ${incoming ? 'checked' : ''}>
                                    <label class=\"form-check-label\" for=\"incoming_enabled\">Get Incoming Message</label>
                                </div>
                                <div class=\"form-check\">
                                    <input class=\"form-check-input\" type=\"checkbox\" name=\"auto_reply_enabled\" value=\"1\" id=\"auto_reply_enabled\" ${autoreply ? 'checked' : ''}>
                                    <label class=\"form-check-label\" for=\"auto_reply_enabled\">Get Auto Reply From Webhook</label>
                                </div>
                                <div class=\"form-check\">
                                    <input class=\"form-check-input\" type=\"checkbox\" name=\"tracking_enabled\" value=\"1\" id=\"tracking_enabled\" ${tracking ? 'checked' : ''}>
                                    <label class=\"form-check-label\" for=\"tracking_enabled\">Get Tracking URL (status)</label>
                                </div>
                                <div class=\"form-check\">
                                    <input class=\"form-check-input\" type=\"checkbox\" name=\"device_status_enabled\" value=\"1\" id=\"device_status_enabled\" ${deviceStatus ? 'checked' : ''}>
                                    <label class=\"form-check-label\" for=\"device_status_enabled\">Get Device Status</label>
                                </div>
                            </div>
                            <div class=\"col-12\">
                                <label class=\"form-label text-muted small\">Tracking Webhook URL (Status)</label>
                                <div class=\"input-group\">
                                    <input id=\"tracking_webhook_base_url\" name=\"tracking_webhook_base_url\" type=\"text\" class=\"form-control\" placeholder=\"kosong = pakai Webhook URL utama\">
                                    <button type=\"button\" class=\"btn btn-outline-secondary btn-test-webhook\" data-type=\"tracking\" data-session=\"${encodeURIComponent(session)}\">Test</button>
                                </div>
                                <p class=\"text-muted small mb-0 mt-1\">Endpoint test: <span class=\"text-monospace\">/status</span></p>
                                <p id=\"test-result-tracking\" class=\"small mt-1\"></p>
                            </div>
                            <div class=\"col-12\">
                                <label class=\"form-label text-muted small\">Device Status Webhook URL</label>
                                <div class=\"input-group\">
                                    <input id=\"device_status_webhook_base_url\" name=\"device_status_webhook_base_url\" type=\"text\" class=\"form-control\" placeholder=\"kosong = pakai Webhook URL utama\">
                                    <button type=\"button\" class=\"btn btn-outline-secondary btn-test-webhook\" data-type=\"device_status\" data-session=\"${encodeURIComponent(session)}\">Test</button>
                                </div>
                                <p class=\"text-muted small mb-0 mt-1\">Endpoint test: <span class=\"text-monospace\">/session</span></p>
                                <p id=\"test-result-device_status\" class=\"small mt-1\"></p>
                            </div>
                            <div class=\"col-12 d-flex justify-content-end\">
                                <button class=\"btn btn-primary\">Save</button>
                            </div>
                        </form>
                    `;

                    // Prefill tracking/device-status URL from dataset when available
                    // (kept optional; if empty, gateway uses main webhook url).
                    const trackingUrl = card.getAttribute('data-tracking-webhook') || '';
                    const deviceStatusUrl = card.getAttribute('data-device-status-webhook') || '';
                    const trackingInput = document.getElementById('tracking_webhook_base_url');
                    const deviceStatusInput = document.getElementById('device_status_webhook_base_url');
                    if (trackingInput) trackingInput.value = trackingUrl;
                    if (deviceStatusInput) deviceStatusInput.value = deviceStatusUrl;

                    const genBtn = document.getElementById('btn-generate-apikey');
                    genBtn?.addEventListener('click', () => {
                        const input = document.getElementById('api_key');
                        if (!input) return;
                        const bytes = new Uint8Array(16);
                        if (window.crypto?.getRandomValues) {
                            window.crypto.getRandomValues(bytes);
                        } else {
                            for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
                        }
                        const hex = Array.from(bytes).map(b => b.toString(16).padStart(2,'0')).join('');
                        input.value = hex;
                    });

                    // Test webhook buttons: call panel endpoint so it uses per-device API key.
                    const testButtons = settingsContent.querySelectorAll('.btn-test-webhook');
                    testButtons.forEach((b) => {
                        b.addEventListener('click', async () => {
                            const type = b.getAttribute('data-type') || 'base';
                            const sessionEncoded = b.getAttribute('data-session') || encodeURIComponent(session);
                            const apiKeyInput = document.getElementById('api_key');
                            const apiKey = apiKeyInput?.value || '';
                            const urlFieldId = type === 'tracking'
                                ? 'tracking_webhook_base_url'
                                : (type === 'device_status' ? 'device_status_webhook_base_url' : 'webhook_base_url');
                            const urlInput = document.getElementById(urlFieldId);
                            const url = (urlInput?.value || '').trim();
                            const out = document.getElementById(`test-result-${type}`);
                            if (out) {
                                out.textContent = 'Testing...';
                                out.className = 'small mt-1 text-secondary';
                            }
                            try {
                                const res = await fetch(`${'{{ url('/sessions') }}'}/${sessionEncoded}/webhook-test`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrf,
                                    },
                                    body: JSON.stringify({ type, url, api_key: apiKey }),
                                });
                                const data = await res.json().catch(() => ({}));
                                const ok = !!data.ok;
                                const msg = data.message || (ok ? 'OK' : 'FAILED');
                                if (out) {
                                    out.textContent = msg;
                                    out.className = `small mt-1 ${ok ? 'text-success' : 'text-danger'}`;
                                }
                            } catch (err) {
                                if (out) {
                                    out.textContent = 'FAILED: tidak bisa menguji URL';
                                    out.className = 'small mt-1 text-danger';
                                }
                            }
                        });
                    });

                    show(modalSettings);
                });
            });

            // Group Finder modal
            document.querySelectorAll('.btn-group-finder').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const session = btn.getAttribute('data-session');
                    if (!session || !groupContent) return;

                    groupContent.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <p class="text-muted small mb-0">Group ID Finder</p>
                                <p class="fw-semibold mb-0">${session}</p>
                            </div>
                            <button type="button" class="btn-refresh-groups btn btn-outline-secondary btn-sm" data-session="${encodeURIComponent(session)}">Reload</button>
                        </div>
                        <p class="text-muted small">Cari Group ID WhatsApp (device harus Connected).</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="text" class="group-search-q form-control form-control-sm" placeholder="Cari nama group / group id">
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="group-search-phone form-control form-control-sm" placeholder="Cari nomor anggota (628xxx / 08xxx)">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-2">
                            <button type="button" class="btn-search-groups btn btn-primary btn-sm">Search</button>
                        </div>
                        <div class="groups-result mt-2 text-muted small">Belum ada data.</div>
                    `;

                    const groupsResult = groupContent.querySelector('.groups-result');
                    const loadGroups = async () => {
                        const qEl = groupContent.querySelector('.group-search-q');
                        const phoneEl = groupContent.querySelector('.group-search-phone');
                        const q = (qEl?.value || '').trim();
                        const phone = (phoneEl?.value || '').trim();
                        if (groupsResult) {
                            groupsResult.textContent = 'Loading...';
                            groupsResult.className = 'groups-result mt-2 text-secondary small';
                        }
                        try {
                            const url = new URL(`${sessionsBaseUrl}/${encodeURIComponent(session)}/groups`, window.location.origin);
                            if (q) url.searchParams.set('q', q);
                            if (phone) url.searchParams.set('phone', phone);
                            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                            const data = await res.json().catch(() => ({}));
                            if (!data.ok) throw new Error(data.message || 'Failed');
                            const groups = Array.isArray(data.data) ? data.data : [];
                            if (!groupsResult) return;
                            if (groups.length === 0) {
                                groupsResult.textContent = 'Tidak ada group ditemukan.';
                                groupsResult.className = 'groups-result mt-2 text-warning small';
                                return;
                            }
                            groupsResult.className = 'groups-result mt-2 text-muted small';
                            groupsResult.innerHTML = `
                                <div class="d-flex flex-column gap-2">
                                    ${groups.map((g) => {
                                        const gid = (g.id || '').toString();
                                        const subject = (g.subject || '').toString();
                                        const size = (g.size ?? '').toString();
                                        const safeGid = gid.replace(/\\\"/g,'&quot;');
                                        return `
                                            <div class="border rounded p-3 bg-white d-flex align-items-start justify-content-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="fw-semibold mb-1 text-truncate">${subject || '-'}</p>
                                                    <p class="text-muted small mb-1 text-truncate text-monospace">${gid}</p>
                                                    <p class="text-muted small mb-0">Members: ${size || '-'}</p>
                                                </div>
                                                <button type="button" class="btn-copy-groupid btn btn-outline-secondary btn-sm" data-group-id="${safeGid}">Copy ID</button>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            `;

                            groupsResult.querySelectorAll('.btn-copy-groupid').forEach((btnCopy) => {
                                btnCopy.addEventListener('click', async () => {
                                    const gid = btnCopy.getAttribute('data-group-id') || '';
                                    if (!gid) return;
                                    try {
                                        await navigator.clipboard.writeText(gid);
                                        btnCopy.textContent = 'Copied';
                                        setTimeout(() => (btnCopy.textContent = 'Copy ID'), 1000);
                                    } catch {
                                        btnCopy.textContent = 'Copy failed';
                                        setTimeout(() => (btnCopy.textContent = 'Copy ID'), 1200);
                                    }
                                });
                            });
                        } catch (err) {
                            if (!groupsResult) return;
                            groupsResult.textContent = 'Gagal memuat group. Pastikan device sudah Connected.';
                            groupsResult.className = 'groups-result mt-2 text-danger small';
                        }
                    };

                    groupContent.querySelector('.btn-refresh-groups')?.addEventListener('click', loadGroups);
                    groupContent.querySelector('.btn-search-groups')?.addEventListener('click', loadGroups);

                    show(modalGroup);
                });
            });

            // Message log modal
            document.querySelectorAll('.btn-message-log').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const session = btn.getAttribute('data-session');
                    if (!session || !messageLogContent) return;

                    messageLogContent.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <p class="text-muted small mb-0">Message Status Log</p>
                                <p class="fw-semibold mb-0">${session}</p>
                            </div>
                            <button type="button" class="btn-refresh-log btn btn-outline-secondary btn-sm" data-session="${encodeURIComponent(session)}">Reload</button>
                        </div>
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Filter nomor/tujuan</label>
                                <input type="text" class="form-control form-control-sm log-filter-phone" placeholder="628xx / id">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small mb-1">Status</label>
                                <select class="form-select form-select-sm log-filter-status">
                                    <option value="">Semua status</option>
                                    <option value="pending">pending</option>
                                    <option value="sent">sent</option>
                                    <option value="delivered">delivered</option>
                                    <option value="read">read</option>
                                    <option value="failed">failed</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-grid d-md-block">
                                <button type="button" class="btn btn-primary btn-sm btn-apply-log-filter">Filter</button>
                            </div>
                        </div>
                        <div class="log-result mt-2 text-sm text-slate-600">Loading...</div>
                    `;

                    const logResult = messageLogContent.querySelector('.log-result');
                    const escapeHtml = (value) => String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                    const loadLog = async () => {
                        if (logResult) {
                            logResult.textContent = 'Loading...';
                            logResult.className = 'log-result mt-2 text-sm text-slate-500';
                        }
                        try {
                            const url = `${sessionsBaseUrl}/${encodeURIComponent(session)}/message-status`;
                            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            const data = await res.json().catch(() => ({}));
                            if (!data.ok) throw new Error(data.message || 'Failed');
                            const items = Array.isArray(data.data) ? data.data : [];
                            const phoneFilter = (messageLogContent.querySelector('.log-filter-phone')?.value || '').trim().toLowerCase();
                            const statusFilter = (messageLogContent.querySelector('.log-filter-status')?.value || '').trim().toLowerCase();
                            const filtered = items.filter((item) => {
                                const to = (item.to || '').toString().toLowerCase();
                                const st = (item.status || '').toString().toLowerCase();
                                const matchPhone = !phoneFilter || to.includes(phoneFilter);
                                const matchStatus = !statusFilter || st === statusFilter;
                                return matchPhone && matchStatus;
                            });
                            if (!logResult) return;
                            if (filtered.length === 0) {
                                logResult.textContent = 'Belum ada log status.';
                                logResult.className = 'log-result mt-2 text-sm text-amber-700';
                                return;
                            }
                            logResult.className = 'log-result mt-2 text-sm text-slate-600 overflow-x-auto';
                            logResult.innerHTML = `
                                <table class="min-w-full text-xs border border-slate-100 rounded-lg overflow-hidden">
                                    <thead class="bg-slate-100 text-slate-700">
                                        <tr>
                                            <th class="px-2 py-2 text-left">Message ID</th>
                                            <th class="px-2 py-2 text-left">Tujuan</th>
                                            <th class="px-2 py-2 text-left">Isi</th>
                                            <th class="px-2 py-2 text-left">Status</th>
                                            <th class="px-2 py-2 text-left">Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${filtered.map((item) => {
                                            const status = (item.status || '').toString();
                                            const ts = item.updatedAt ? new Date(item.updatedAt).toLocaleString() : '-';
                                            const id = (item.id || '').toString();
                                            const to = escapeHtml(item.to || '-');
                                            const previewRaw = escapeHtml(item.preview || '');
                                            const preview = previewRaw || '<span class="text-slate-400">-</span>';
                                            const badgeClass = status === 'delivered' || status === 'read' || status === 'sent'
                                                ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                                : status === 'pending'
                                                ? 'bg-amber-100 text-amber-800 border-amber-200'
                                                : 'bg-rose-100 text-rose-800 border-rose-200';
                                            return `
                                                <tr class="border-t border-slate-100">
                                                    <td class="px-2 py-2 font-mono break-all">${id}</td>
                                                    <td class="px-2 py-2 font-mono text-[11px] break-all">${to}</td>
                                                    <td class="px-2 py-2 text-slate-700 break-all">${preview}</td>
                                                    <td class="px-2 py-2">
                                                        <span class="text-[11px] px-2 py-0.5 rounded-full border ${badgeClass}">${status || '-'}</span>
                                                    </td>
                                                    <td class="px-2 py-2 text-slate-500">${ts}</td>
                                                </tr>
                                            `;
                                        }).join('')}
                                    </tbody>
                                </table>
                            `;
                        } catch (err) {
                            if (!logResult) return;
                            logResult.textContent = 'Gagal memuat log status.';
                            logResult.className = 'log-result mt-2 text-sm text-rose-700';
                        }
                    };

                    messageLogContent.querySelector('.btn-refresh-log')?.addEventListener('click', loadLog);
                    messageLogContent.querySelector('.btn-apply-log-filter')?.addEventListener('click', loadLog);
                    loadLog();
                    show(modalMessageLog);
                });
            });

            // Search
            const searchInput = document.getElementById('device-search');
            const searchBtn = document.getElementById('device-search-btn');
            const applySearch = () => {
                const q = (searchInput?.value || '').trim().toLowerCase();
                document.querySelectorAll('.device-card').forEach((card) => {
                    const id = (card.getAttribute('data-device') || '').toLowerCase();
                    const phone = (card.getAttribute('data-phone') || '').toLowerCase();
                    const match = !q || id.includes(q) || phone.includes(q);
                    card.classList.toggle('hidden', !match);
                });
            };
            searchBtn?.addEventListener('click', applySearch);
            searchInput?.addEventListener('input', applySearch);

            refreshApiStatus();
            refreshDeviceStatuses();
            setInterval(() => {
                refreshApiStatus();
                refreshDeviceStatuses();
            }, refreshMs);
        })();
    </script>
    <footer class="text-center text-muted small py-4">Wa-gateway Panel Develop with ❤️ by Hardi Agunadi – Pranata Komputer Kec. Watumalang</footer>
</body>
</html>
