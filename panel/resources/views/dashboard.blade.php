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
                        @php $masked = str_repeat('•', max(strlen($gatewayConfig['key']) - 3, 3)); @endphp
                        <p class="text-xs text-slate-500">API Key: {{ $masked }}</p>
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
                            <p class="text-emerald-600 font-semibold">Running (PID {{ $npmStatus['pid'] }})</p>
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
                            $cfg = $sessionConfigs[$session] ?? [];
                            $deviceName = $cfg['deviceName'] ?? null;
                            $deviceName = is_string($deviceName) && trim($deviceName) !== '' ? trim($deviceName) : null;

                            $badgeClass = match($st) {
                                'connected' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'connecting' => 'bg-amber-50 text-amber-700 border-amber-200',
                                default => 'bg-rose-50 text-rose-700 border-rose-200',
                            };
                        @endphp
                        <div class="device-card border border-slate-100 rounded-xl p-4 bg-white"
                             data-device="{{ $session }}"
                             data-name="{{ $deviceName ?? '' }}"
                             data-phone="{{ $userId ?? '' }}"
                             data-status="{{ $st }}"
                             data-webhook="{{ $cfg['webhookBaseUrl'] ?? '' }}"
                             data-tracking-webhook="{{ $cfg['trackingWebhookBaseUrl'] ?? '' }}"
                             data-device-status-webhook="{{ $cfg['deviceStatusWebhookBaseUrl'] ?? '' }}"
                             data-incoming="{{ ($cfg['incomingEnabled'] ?? true) ? '1' : '0' }}"
                             data-autoreply="{{ ($cfg['autoReplyEnabled'] ?? false) ? '1' : '0' }}"
                             data-tracking="{{ ($cfg['trackingEnabled'] ?? true) ? '1' : '0' }}"
                             data-device-status="{{ ($cfg['deviceStatusEnabled'] ?? true) ? '1' : '0' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center">📱</div>
                                    <div>
                                        <p class="font-semibold leading-tight">{{ $deviceName ?? $session }}</p>
                                        <p class="text-xs text-slate-500">#{{ $session }}</p>
                                        <p class="device-phone text-xs text-slate-500">{{ $userId ?: 'Not linked' }}</p>
                                    </div>
                                </div>
                                <span class="device-badge text-xs px-2 py-1 rounded-full border {{ $badgeClass }}">{{ ucfirst($st) }}</span>
                            </div>

                            <div class="mt-3 rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600">
                                <p class="truncate"><span class="text-slate-500">Webhook:</span> <span class="font-mono">{{ $cfg['webhookBaseUrl'] ?? '-' }}</span></p>
                                <p class="mt-1">
                                    <span class="text-slate-500">Incoming:</span> {{ ($cfg['incomingEnabled'] ?? true) ? 'ON' : 'OFF' }} ·
                                    <span class="text-slate-500">AutoReply:</span> {{ ($cfg['autoReplyEnabled'] ?? false) ? 'ON' : 'OFF' }}
                                </p>
                            </div>

                            <div class="mt-4 flex items-center justify-between">
                                <form method="POST" action="{{ route('sessions.start') }}" class="inline form-connect" data-session="{{ $session }}">
                                    @csrf
                                    <input type="hidden" name="session" value="{{ $session }}">
                                    <button class="px-3 py-2 rounded-lg bg-slate-900 text-white text-xs hover:bg-slate-800">Connect / QR</button>
                                </form>

                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn-open-settings px-3 py-2 rounded-lg border border-slate-200 text-xs hover:bg-slate-50" data-session="{{ $session }}">Settings</button>
                                    <form method="POST" action="{{ route('devices.delete', $session) }}" onsubmit="return confirm('Hapus device {{ $session }}?');" class="inline">
                                        @csrf
                                        <button class="px-3 py-2 rounded-lg bg-rose-600 text-white text-xs hover:bg-rose-700">Delete</button>
                                    </form>
                                </div>
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
                    <p class="text-sm text-slate-500 mb-2">Session: {{ $qrSession }}</p>
                    <img class="w-full rounded-lg border border-slate-100" src="https://quickchart.io/qr?text={{ urlencode($qrData) }}&size=300" alt="QR Code">
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

    <div id="modal-settings" class="fixed inset-0 bg-black/40 hidden items-center justify-center px-4">
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-lg border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Device Settings</h3>
                <button type="button" id="btn-close-settings" class="text-slate-500 hover:text-slate-900">✕</button>
            </div>
            <div id="settings-content" class="text-sm text-slate-600">Pilih device untuk mengatur.</div>
        </div>
    </div>

    <script>
        (() => {
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

            const applyDeviceStatus = (device) => {
                const card = document.querySelector(`.device-card[data-device=\"${CSS.escape(device.id)}\"]`);
                if (!card) return;
                const badge = card.querySelector('.device-badge');
                const phone = card.querySelector('.device-phone');

                const status = device.status || 'disconnected';
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
                    phone.textContent = device.user?.id || 'Not linked';
                }
                card.setAttribute('data-phone', device.user?.id || '');
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

            const show = (el) => el && (el.classList.remove('hidden'), el.classList.add('flex'));
            const hide = (el) => el && (el.classList.add('hidden'), el.classList.remove('flex'));

            btnCreate?.addEventListener('click', () => show(modalCreate));
            btnCloseCreate?.addEventListener('click', () => hide(modalCreate));
            btnCancelCreate?.addEventListener('click', () => hide(modalCreate));
            modalCreate?.addEventListener('click', (e) => { if (e.target === modalCreate) hide(modalCreate); });

            btnCloseSettings?.addEventListener('click', () => hide(modalSettings));
            modalSettings?.addEventListener('click', (e) => { if (e.target === modalSettings) hide(modalSettings); });

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
                                    <input id=\"api_key\" name=\"api_key\" type=\"text\" class=\"w-full rounded-lg border border-slate-200 px-3 py-2\" placeholder=\"isi jika ingin update\" />
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
