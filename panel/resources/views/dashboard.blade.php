<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WA Gateway Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
    <div class="max-w-6xl mx-auto px-6 py-10">
        <header class="mb-8 flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500">WA Gateway</p>
                <h1 class="text-3xl font-semibold">Control Panel</h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right text-sm">
                    <p class="text-slate-500">API Base</p>
                    <p class="font-semibold">{{ $gatewayConfig['base'] }}</p>
                    @if($gatewayConfig['key'])
                        @php
                            $plainKey = (string) $gatewayConfig['key'];
                            $masked = str_repeat('•', max(strlen($plainKey) - 3, 3));
                        @endphp
                        <div class="mt-1 text-xs text-slate-500">
                            <span>Master Key:</span>
                            <span id="gateway-key-masked" class="font-mono">{{ $masked }}</span>
                            <span id="gateway-key-plain" class="font-mono hidden">{{ $plainKey }}</span>
                            <button type="button" id="btn-toggle-gateway-key" class="ml-2 underline hover:text-slate-900">Show</button>
                            <button type="button" id="btn-copy-gateway-key" class="ml-2 underline hover:text-slate-900">Copy</button>
                        </div>
                    @else
                        <p class="text-xs text-amber-600">API Key kosong (akses publik)</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('profile.show') }}" class="px-3 py-2 rounded-lg bg-slate-200 text-slate-800 hover:bg-slate-300 text-xs">Profil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="px-3 py-2 rounded-lg bg-rose-600 text-white hover:bg-rose-700 text-xs">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        @if($statusMessage)
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-emerald-800">
                {{ $statusMessage }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-rose-800">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white shadow rounded-xl p-5 border border-slate-100">
                <p class="text-sm text-slate-500 mb-1">API Status</p>
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
                <p id="api-status-label" class="{{ $apiStatusClass }} font-semibold">{{ $apiStatusLabel }}</p>
                <p id="api-status-detail" class="text-xs text-slate-500 mt-1 break-all">{{ $apiStatusDetail }}</p>
            </div>

            <div class="bg-white shadow rounded-xl p-5 border border-slate-100">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-slate-500 mb-1">NPM Server</p>
                        @if($npmStatus['running'])
                            @if(isset($npmStatus['source']) && $npmStatus['source'] === 'inferred')
                                <p class="text-emerald-600 font-semibold">Running (external)</p>
                            @elseif($npmStatus['pid'])
                                <p class="text-emerald-600 font-semibold">Running (PID {{ $npmStatus['pid'] }})</p>
                            @else
                                <p class="text-emerald-600 font-semibold">Running</p>
                            @endif
                        @else
                            <p class="text-rose-600 font-semibold">Stopped</p>
                        @endif
                        <p class="text-xs text-slate-500 mt-1 break-all">{{ $npmStatus['command'] }}</p>
                        <p class="text-xs text-slate-500 break-all">Dir: {{ $npmStatus['workingDir'] }}</p>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('server.start') }}">
                            @csrf
                            <button class="px-3 py-2 text-xs rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition disabled:opacity-60" {{ $npmStatus['running'] ? 'disabled' : '' }}>
                                Start
                            </button>
                        </form>
                        <form method="POST" action="{{ route('server.stop') }}">
                            @csrf
                            <button class="px-3 py-2 text-xs rounded-lg bg-rose-600 text-white hover:bg-rose-700 transition disabled:opacity-60" {{ $npmStatus['running'] ? '' : 'disabled' }}>
                                Stop
                            </button>
                        </form>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500 break-all">Log: {{ $npmStatus['logFile'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white shadow rounded-xl p-5 border border-slate-100">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <span class="text-lg">🔌</span>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">List Device #all</p>
                            <h2 class="text-xl font-semibold">Devices</h2>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-2">
                            <input id="device-search" type="text" placeholder="Search by Device ID / Phone" class="w-full sm:w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <button type="button" id="device-search-btn" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm">Search</button>
                        </div>
                        <button type="button" id="btn-create-device" class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">+ Create Device</button>
                    </div>
                </div>

                @php
                    $statusMap = [];
                    foreach (($sessionStatuses ?? []) as $row) {
                        if (is_array($row) && isset($row['id'])) $statusMap[$row['id']] = $row;
                    }
                @endphp

                <div id="device-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    @forelse($sessions as $session)
                        @php
                            $row = $statusMap[$session] ?? [];
                            $st = $row['status'] ?? 'disconnected';
                            $userId = $row['user']['id'] ?? null;
                            $userDisplay = $userId ? preg_replace('/@.*/', '', $userId) : 'Not linked';
                            $cfg = $sessionConfigs[$session] ?? [];
                            $deviceName = $cfg['deviceName'] ?? null;
                            $deviceName = is_string($deviceName) && trim($deviceName) !== '' ? trim($deviceName) : null;

                            $badgeClass = match($st) {
                                'connected' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'connecting' => 'bg-amber-50 text-amber-700 border-amber-200',
                                default => 'bg-rose-50 text-rose-700 border-rose-200',
                            };
                        @endphp
                        <div class="device-card border border-slate-100 rounded-2xl p-5 bg-gradient-to-br from-white via-slate-50 to-slate-100 shadow-sm hover:shadow-md transition"
                             data-device="{{ $session }}"
                             data-name="{{ $deviceName ?? '' }}"
                             data-phone="{{ $userDisplay ?? '' }}"
                             data-status="{{ $st }}"
                             data-webhook="{{ $cfg['webhookBaseUrl'] ?? '' }}"
                             data-tracking-webhook="{{ $cfg['trackingWebhookBaseUrl'] ?? '' }}"
                             data-device-status-webhook="{{ $cfg['deviceStatusWebhookBaseUrl'] ?? '' }}"
                             data-api-key="{{ $cfg['apiKey'] ?? '' }}"
                             data-incoming="{{ ($cfg['incomingEnabled'] ?? true) ? '1' : '0' }}"
                             data-autoreply="{{ ($cfg['autoReplyEnabled'] ?? false) ? '1' : '0' }}"
                             data-tracking="{{ ($cfg['trackingEnabled'] ?? true) ? '1' : '0' }}"
                             data-device-status="{{ ($cfg['deviceStatusEnabled'] ?? true) ? '1' : '0' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-12 w-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center text-lg shadow-inner">📱</div>
                                    <div>
                                        <p class="font-semibold leading-tight text-slate-900">{{ $deviceName ?? $session }}</p>
                                        <p class="text-xs text-slate-500 font-mono">#{{ $session }}</p>
                                        <p class="device-phone text-xs text-slate-600 font-mono">{{ $userDisplay }}</p>
                                        <span class="device-badge inline-block mt-1 text-[10px] px-2 py-0.5 rounded-full border {{ $badgeClass }}">{{ ucfirst($st) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border border-slate-100 bg-white/80 p-3 text-xs text-slate-700 shadow-inner">
                                <p class="truncate"><span class="text-slate-500">Webhook:</span> <span class="font-mono">{{ $cfg['webhookBaseUrl'] ?? '-' }}</span></p>
                                <p class="mt-1">
                                    <span class="text-slate-500">Incoming:</span> {{ ($cfg['incomingEnabled'] ?? true) ? 'ON' : 'OFF' }} ·
                                    <span class="text-slate-500">AutoReply:</span> {{ ($cfg['autoReplyEnabled'] ?? false) ? 'ON' : 'OFF' }}
                                </p>
                            </div>

                            <div class="mt-5 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('sessions.start') }}" class="inline form-connect" data-session="{{ $session }}">
                                    @csrf
                                    <input type="hidden" name="session" value="{{ $session }}">
                                    <button class="h-9 w-9 rounded-lg bg-slate-900 text-white text-sm font-semibold shadow hover:bg-slate-800 flex items-center justify-center" title="Connect / QR" aria-label="Connect / QR">🔗</button>
                                </form>

                                <button type="button" class="btn-open-settings h-9 w-9 rounded-lg border border-amber-200 bg-amber-100 text-sm font-semibold text-amber-800 shadow-sm hover:bg-amber-200/80 flex items-center justify-center" data-session="{{ $session }}" title="Settings" aria-label="Settings">⚙️</button>
                                <button type="button" class="btn-message-log h-9 w-9 rounded-lg bg-indigo-600 text-white text-sm font-semibold shadow hover:bg-indigo-700 flex items-center justify-center" data-session="{{ $session }}" title="Message Status Log" aria-label="Message Status Log">📑</button>
                                <button type="button" class="btn-group-finder h-9 w-9 rounded-lg bg-cyan-600 text-white text-sm font-semibold shadow hover:bg-cyan-700 flex items-center justify-center" data-session="{{ $session }}" title="Group ID Finder" aria-label="Group ID Finder">👥</button>
                                <form method="POST" action="{{ route('devices.delete', $session) }}" onsubmit="return confirm('Hapus device {{ $session }}?');" class="inline">
                                    @csrf
                                    <button class="h-9 w-9 rounded-lg bg-rose-600 text-white text-sm font-semibold shadow hover:bg-rose-700 flex items-center justify-center" title="Delete" aria-label="Delete">🗑️</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">Belum ada device.</div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white shadow rounded-xl p-5 border border-slate-100">
                <h3 class="text-lg font-semibold mb-2">QR Terbaru</h3>
                @if($qrData)
                    @php
                        $qrSrc = str_starts_with($qrData, 'data:image')
                            ? $qrData
                            : 'https://quickchart.io/qr?text=' . urlencode($qrData) . '&size=300';
                    @endphp
                    <p class="text-sm text-slate-500 mb-2">Session: {{ $qrSession }}</p>
                    <img class="w-full rounded-lg border border-slate-100" src="{{ $qrSrc }}" alt="QR Code">
                    <p class="text-xs text-slate-500 mt-2">Scan dengan WhatsApp Anda.</p>
                @else
                    <p class="text-sm text-slate-500">Belum ada permintaan QR.</p>
                @endif
            </div>
        </div>
    </div>
    <div id="modal-create-device" class="fixed inset-0 bg-black/40 hidden items-center justify-center px-4">
        <div class="bg-white w-full max-w-lg rounded-xl shadow-lg border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Create Device</h3>
                <button type="button" id="btn-close-create" class="text-slate-500 hover:text-slate-900">✕</button>
            </div>
            <form method="POST" action="{{ route('devices.create') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @csrf
                <div class="col-span-1 md:col-span-2">
                    <label class="block text-xs text-slate-500 mb-1">Device Name</label>
                    <input name="device_name" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="mis. TopSETTING" required>
                </div>
                <div class="col-span-1 md:col-span-2">
                    <label class="block text-xs text-slate-500 mb-1">Nomor WA Device</label>
                    <input name="device_phone" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="62812xxxxxx" required>
                </div>

                <div class="col-span-1 md:col-span-2 flex justify-end gap-2 pt-2">
                    <button type="button" id="btn-cancel-create" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">Cancel</button>
                    <button class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">Create</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-settings" class="fixed inset-0 bg-black/30 backdrop-blur-sm hidden items-center justify-center px-4">
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-lg border border-slate-100 p-5 max-h-[80vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Device Settings</h3>
                <button type="button" id="btn-close-settings" class="text-slate-500 hover:text-slate-900">✕</button>
            </div>
            <div id="settings-content" class="text-sm text-slate-600">Pilih device untuk mengatur.</div>
        </div>
    </div>

    <div id="modal-group-finder" class="fixed inset-0 bg-black/40 hidden items-center justify-center px-4">
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-lg border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Group ID Finder</h3>
                <button type="button" id="btn-close-group" class="text-slate-500 hover:text-slate-900">✕</button>
            </div>
            <div id="group-finder-content" class="text-sm text-slate-600">Pilih device.</div>
        </div>
    </div>

    <div id="modal-message-log" class="fixed inset-0 bg-black/30 backdrop-blur-sm hidden items-center justify-center px-4">
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-lg border border-slate-100 p-5 max-h-[80vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Message Status Log</h3>
                <button type="button" id="btn-close-message-log" class="text-slate-500 hover:text-slate-900">✕</button>
            </div>
            <div id="message-log-content" class="text-sm text-slate-600">Pilih device.</div>
        </div>
    </div>

    <script>
        (() => {
            const sessionsBaseUrl = '{{ url('/sessions') }}';

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

            const statusClass = {
                online: 'text-emerald-600',
                error: 'text-rose-600',
                unknown: 'text-amber-600',
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
            };

            const lastStatuses = new Map();
            document.querySelectorAll('.device-card').forEach((card) => {
                const id = card.getAttribute('data-device');
                const st = card.getAttribute('data-status') || 'disconnected';
                if (id) lastStatuses.set(id, st);
            });
            let refreshAfterConnect = false;

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

                // Notify once when QR is scanned and the device transitions to connected
                if (!refreshAfterConnect && prevStatus && prevStatus !== 'connected' && status === 'connected') {
                    refreshAfterConnect = true;
                    setTimeout(() => {
                        alert(`Kode QR berhasil discan dan device ${device.id} terhubung. Halaman akan direfresh.`);
                        window.location.reload();
                    }, 50);
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
            const modalCreate = document.getElementById('modal-create-device');
            const btnCreate = document.getElementById('btn-create-device');
            const btnCloseCreate = document.getElementById('btn-close-create');
            const btnCancelCreate = document.getElementById('btn-cancel-create');
            const modalSettings = document.getElementById('modal-settings');
            const btnCloseSettings = document.getElementById('btn-close-settings');
            const settingsContent = document.getElementById('settings-content');
            const modalGroup = document.getElementById('modal-group-finder');
            const btnCloseGroup = document.getElementById('btn-close-group');
            const groupContent = document.getElementById('group-finder-content');
            const modalMessageLog = document.getElementById('modal-message-log');
            const btnCloseMessageLog = document.getElementById('btn-close-message-log');
            const messageLogContent = document.getElementById('message-log-content');

            const show = (el) => el && (el.classList.remove('hidden'), el.classList.add('flex'));
            const hide = (el) => el && (el.classList.add('hidden'), el.classList.remove('flex'));

            btnCreate?.addEventListener('click', () => show(modalCreate));
            btnCloseCreate?.addEventListener('click', () => hide(modalCreate));
            btnCancelCreate?.addEventListener('click', () => hide(modalCreate));
            btnCloseSettings?.addEventListener('click', () => hide(modalSettings));
            btnCloseGroup?.addEventListener('click', () => hide(modalGroup));
            btnCloseMessageLog?.addEventListener('click', () => hide(modalMessageLog));

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
                        <form method=\"POST\" action=\"${'{{ url('/sessions') }}'}/${encodeURIComponent(session)}/config\" class=\"grid grid-cols-1 md:grid-cols-2 gap-3\">
                            <input type=\"hidden\" name=\"_token\" value=\"{{ csrf_token() }}\" />
                            <div class=\"col-span-1 md:col-span-2\">
                                <label class=\"block text-xs text-slate-500 mb-1\">Device Name</label>
                                <input name=\"device_name\" type=\"text\" value=\"${name.replace(/\\\"/g,'&quot;')}\" class=\"w-full rounded-lg border border-slate-200 px-3 py-2\" placeholder=\"Nama device\" />
                            </div>
                            <div class=\"col-span-1 md:col-span-2\">
                                <label class=\"block text-xs text-slate-500 mb-1\">Webhook URL (Incoming & Auto Reply)</label>
                                <div class=\"flex gap-2\">
                                    <input id=\"webhook_base_url\" name=\"webhook_base_url\" type=\"text\" value=\"${webhook.replace(/\\\"/g,'&quot;')}\" class=\"w-full rounded-lg border border-slate-200 px-3 py-2\" required>
                                    <button type=\"button\" class=\"btn-test-webhook px-3 py-2 rounded-lg border border-slate-200 text-xs hover:bg-slate-50\" data-type=\"base\" data-session=\"${encodeURIComponent(session)}\">Test</button>
                                </div>
                                <p class=\"text-xs text-slate-500 mt-1\">Endpoint test: <span class=\"font-mono\">/message</span></p>
                                <p id=\"test-result-base\" class=\"text-xs mt-1\"></p>
                            </div>
                            <div class=\"col-span-1 md:col-span-2\">
                                <label class=\"block text-xs text-slate-500 mb-1\">API Key</label>
                                <div class=\"flex gap-2\">
                                    <input id=\"api_key\" name=\"api_key\" type=\"text\" value=\"${apiKeyStored.replace(/\\\"/g,'&quot;')}\" class=\"w-full rounded-lg border border-slate-200 px-3 py-2\" placeholder=\"API key\" />
                                    <button type=\"button\" id=\"btn-generate-apikey\" class=\"px-3 py-2 rounded-lg bg-slate-900 text-white text-xs hover:bg-slate-800\">Generate</button>
                                </div>
                            </div>
                            <label class=\"flex items-center gap-2 text-sm\">
                                <input type=\"checkbox\" name=\"incoming_enabled\" value=\"1\" class=\"rounded\" ${incoming ? 'checked' : ''}>
                                Get Incoming Message
                            </label>
                            <label class=\"flex items-center gap-2 text-sm\">
                                <input type=\"checkbox\" name=\"auto_reply_enabled\" value=\"1\" class=\"rounded\" ${autoreply ? 'checked' : ''}>
                                Get Auto Reply From Webhook
                            </label>
                            <label class=\"flex items-center gap-2 text-sm\">
                                <input type=\"checkbox\" name=\"tracking_enabled\" value=\"1\" class=\"rounded\" ${tracking ? 'checked' : ''}>
                                Get Tracking URL (status)
                            </label>
                            <div class=\"col-span-1 md:col-span-2\">
                                <label class=\"block text-xs text-slate-500 mb-1\">Tracking Webhook URL (Status)</label>
                                <div class=\"flex gap-2\">
                                    <input id=\"tracking_webhook_base_url\" name=\"tracking_webhook_base_url\" type=\"text\" class=\"w-full rounded-lg border border-slate-200 px-3 py-2\" placeholder=\"kosong = pakai Webhook URL utama\" />
                                    <button type=\"button\" class=\"btn-test-webhook px-3 py-2 rounded-lg border border-slate-200 text-xs hover:bg-slate-50\" data-type=\"tracking\" data-session=\"${encodeURIComponent(session)}\">Test</button>
                                </div>
                                <p class=\"text-xs text-slate-500 mt-1\">Endpoint test: <span class=\"font-mono\">/status</span></p>
                                <p id=\"test-result-tracking\" class=\"text-xs mt-1\"></p>
                            </div>
                            <label class=\"flex items-center gap-2 text-sm\">
                                <input type=\"checkbox\" name=\"device_status_enabled\" value=\"1\" class=\"rounded\" ${deviceStatus ? 'checked' : ''}>
                                Get Device Status
                            </label>
                            <div class=\"col-span-1 md:col-span-2\">
                                <label class=\"block text-xs text-slate-500 mb-1\">Device Status Webhook URL</label>
                                <div class=\"flex gap-2\">
                                    <input id=\"device_status_webhook_base_url\" name=\"device_status_webhook_base_url\" type=\"text\" class=\"w-full rounded-lg border border-slate-200 px-3 py-2\" placeholder=\"kosong = pakai Webhook URL utama\" />
                                    <button type=\"button\" class=\"btn-test-webhook px-3 py-2 rounded-lg border border-slate-200 text-xs hover:bg-slate-50\" data-type=\"device_status\" data-session=\"${encodeURIComponent(session)}\">Test</button>
                                </div>
                                <p class=\"text-xs text-slate-500 mt-1\">Endpoint test: <span class=\"font-mono\">/session</span></p>
                                <p id=\"test-result-device_status\" class=\"text-xs mt-1\"></p>
                            </div>
                            <div class=\"col-span-1 md:col-span-2 flex justify-end pt-2\">
                                <button class=\"px-3 py-2 rounded-lg bg-slate-900 text-white text-sm\">Save</button>
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
                                out.className = 'text-xs mt-1 text-slate-500';
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
                                    out.className = `text-xs mt-1 ${ok ? 'text-emerald-700' : 'text-rose-700'}`;
                                }
                            } catch (err) {
                                if (out) {
                                    out.textContent = 'FAILED: tidak bisa menguji URL';
                                    out.className = 'text-xs mt-1 text-rose-700';
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
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-slate-500">Group ID Finder</p>
                                    <p class="font-semibold text-slate-900">${session}</p>
                                </div>
                                <button type="button" class="btn-refresh-groups px-3 py-2 rounded-lg border border-slate-200 text-xs hover:bg-slate-50" data-session="${encodeURIComponent(session)}">Reload</button>
                            </div>
                            <p class="text-xs text-slate-500">Cari Group ID WhatsApp (device harus Connected).</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <input type="text" class="group-search-q w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Cari nama group / group id">
                                <input type="text" class="group-search-phone w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Cari nomor anggota (628xxx / 08xxx)">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-search-groups px-3 py-2 rounded-lg bg-slate-900 text-white text-xs hover:bg-slate-800">Search</button>
                            </div>
                            <div class="groups-result mt-2 text-sm text-slate-600">Belum ada data.</div>
                        </div>
                    `;

                    const groupsResult = groupContent.querySelector('.groups-result');
                    const loadGroups = async () => {
                        const qEl = groupContent.querySelector('.group-search-q');
                        const phoneEl = groupContent.querySelector('.group-search-phone');
                        const q = (qEl?.value || '').trim();
                        const phone = (phoneEl?.value || '').trim();
                        if (groupsResult) {
                            groupsResult.textContent = 'Loading...';
                            groupsResult.className = 'groups-result mt-2 text-sm text-slate-500';
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
                                groupsResult.className = 'groups-result mt-2 text-sm text-amber-700';
                                return;
                            }
                            groupsResult.className = 'groups-result mt-2 text-sm text-slate-600';
                            groupsResult.innerHTML = `
                                <div class="space-y-2">
                                    ${groups.map((g) => {
                                        const gid = (g.id || '').toString();
                                        const subject = (g.subject || '').toString();
                                        const size = (g.size ?? '').toString();
                                        const safeGid = gid.replace(/\"/g,'&quot;');
                                        return `
                                            <div class="border border-slate-100 rounded-lg p-3 bg-white flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="font-semibold truncate">${subject || '-'}</p>
                                                    <p class="text-xs text-slate-500 truncate font-mono">${gid}</p>
                                                    <p class="text-xs text-slate-500">Members: ${size || '-'}</p>
                                                </div>
                                                <button type="button" class="btn-copy-groupid px-3 py-2 rounded-lg border border-slate-200 text-xs hover:bg-slate-50" data-group-id="${safeGid}">Copy ID</button>
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
                            groupsResult.className = 'groups-result mt-2 text-sm text-rose-700';
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
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-slate-500">Message Status Log</p>
                                    <p class="font-semibold text-slate-900">${session}</p>
                                </div>
                                <button type="button" class="btn-refresh-log px-3 py-2 rounded-lg border border-slate-200 text-xs hover:bg-slate-50" data-session="${encodeURIComponent(session)}">Reload</button>
                            </div>
                            <div class="log-result mt-2 text-sm text-slate-600">Loading...</div>
                        </div>
                    `;

                    const logResult = messageLogContent.querySelector('.log-result');
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
                            if (!logResult) return;
                            if (items.length === 0) {
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
                                            <th class="px-2 py-2 text-left">Status</th>
                                            <th class="px-2 py-2 text-left">Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${items.map((item) => {
                                            const status = (item.status || '').toString();
                                            const ts = item.updatedAt ? new Date(item.updatedAt).toLocaleString() : '-';
                                            const id = (item.id || '').toString();
                                            const badgeClass = status === 'delivered' || status === 'read' || status === 'sent'
                                                ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                                : status === 'pending'
                                                ? 'bg-amber-100 text-amber-800 border-amber-200'
                                                : 'bg-rose-100 text-rose-800 border-rose-200';
                                            return `
                                                <tr class="border-t border-slate-100">
                                                    <td class="px-2 py-2 font-mono break-all">${id}</td>
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
</body>
</html>
