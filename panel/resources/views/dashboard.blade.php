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
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-slate-500">Sessions</p>
                        <h2 class="text-xl font-semibold">Daftar Session</h2>
                    </div>
                    <form method="POST" action="{{ route('sessions.start') }}" class="flex gap-2">
                        @csrf
                        <input type="text" name="session" placeholder="nama session" class="rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500" required>
                        <button class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition">Tambah</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500">
                                <th class="py-2">Session</th>
                                <th class="py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="sessions-body">
                            @forelse($sessions as $session)
                                <tr class="border-t border-slate-100">
                                    <td class="py-2 font-medium">{{ $session }}</td>
                                    <td class="py-2 text-right">
                                        <form method="POST" action="{{ route('sessions.close', $session) }}" onsubmit="return confirm('Tutup session {{ $session }}?');" class="inline">
                                            @csrf
                                            <button class="px-3 py-1 rounded-lg bg-rose-600 text-white text-xs hover:bg-rose-700 transition">Close</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr class="border-t border-slate-100">
                                    <td colspan="2" class="py-2 text-sm text-slate-500">Belum ada session aktif.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(!empty($sessions))
                    <div class="mt-6 border-t border-slate-100 pt-4">
                        <h3 class="text-lg font-semibold mb-2">Webhook Configuration (1 device = 1 webhook)</h3>
                        <p class="text-xs text-slate-500 mb-4">Gunakan 1 URL webhook per session (endpoint tunggal). Gateway akan POST ke URL yang sama untuk event: incoming, auto-reply, status, device.</p>

                        <div class="space-y-4">
                            @foreach($sessions as $session)
                                @php $cfg = $sessionConfigs[$session] ?? []; @endphp
                                <div class="border border-slate-100 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <p class="font-semibold">{{ $session }}</p>
                                        <a href="#"
                                           onclick="document.getElementById('cfg-{{ $session }}').classList.toggle('hidden'); return false;"
                                           class="text-xs text-emerald-700 hover:text-emerald-900">Toggle</a>
                                    </div>

                                    <form id="cfg-{{ $session }}" method="POST" action="{{ route('sessions.config', $session) }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        @csrf
                                        <div class="col-span-1 md:col-span-2">
                                            <label class="block text-xs text-slate-500 mb-1">Webhook URL</label>
                                            <input type="text" name="webhook_base_url" value="{{ old('webhook_base_url', $cfg['webhookBaseUrl'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="https://watumalang.online/wablas/webhook" required>
                                        </div>
                                        <div class="col-span-1 md:col-span-2">
                                            <label class="block text-xs text-slate-500 mb-1">API Key</label>
                                            <input type="text" name="api_key" value="{{ old('api_key', $cfg['apiKey'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                            <p class="text-xs text-slate-500 mt-1">Gateway akan mengirim API key sebagai header <span class="font-mono">key</span> dan field <span class="font-mono">tl_code</span> di body.</p>
                                        </div>

                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="incoming_enabled" value="1" class="rounded" {{ ($cfg['incomingEnabled'] ?? true) ? 'checked' : '' }}>
                                            Get Incoming Message
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="auto_reply_enabled" value="1" class="rounded" {{ ($cfg['autoReplyEnabled'] ?? false) ? 'checked' : '' }}>
                                            Get Auto Reply From Webhook
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="tracking_enabled" value="1" class="rounded" {{ ($cfg['trackingEnabled'] ?? true) ? 'checked' : '' }}>
                                            Get Tracking URL (status message)
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="device_status_enabled" value="1" class="rounded" {{ ($cfg['deviceStatusEnabled'] ?? true) ? 'checked' : '' }}>
                                            Get Webhook Device Status
                                        </label>

                                        <div class="col-span-1 md:col-span-2">
                                            <button class="mt-3 px-3 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 text-sm">Simpan</button>
                                        </div>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
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
    <script>
        (() => {
            const tableBody = document.getElementById('sessions-body');
            const apiStatusLabel = document.getElementById('api-status-label');
            const apiStatusDetail = document.getElementById('api-status-detail');
            const csrfToken = '{{ csrf_token() }}';
            const closeUrlTemplate = '{{ url('/sessions') }}/:session/close';
            const listUrl = '{{ route('sessions.list') }}';
            const apiStatusUrl = '{{ route('api.status') }}';
            const refreshMs = 5000;
            const statusClass = {
                online: 'text-emerald-600',
                error: 'text-rose-600',
                unknown: 'text-amber-600',
            };

            const escapeHtml = (str) => String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const renderSessions = (sessions) => {
                if (!tableBody) return;

                const normalized = Array.isArray(sessions)
                    ? sessions
                    : (sessions && typeof sessions === 'object')
                        ? Object.keys(sessions)
                        : [];

                if (normalized.length === 0) {
                    tableBody.innerHTML = '<tr class="border-t border-slate-100"><td colspan="2" class="py-2 text-sm text-slate-500">Belum ada session aktif.</td></tr>';
                    return;
                }

                tableBody.innerHTML = normalized.map((session) => {
                    const safeSession = escapeHtml(session);
                    const closeAction = closeUrlTemplate.replace(':session', encodeURIComponent(session));

                    return `
                        <tr class="border-t border-slate-100">
                            <td class="py-2 font-medium">${safeSession}</td>
                            <td class="py-2 text-right">
                                <form method="POST" action="${closeAction}" onsubmit="return confirm('Tutup session ${safeSession}?');" class="inline">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <button class="px-3 py-1 rounded-lg bg-rose-600 text-white text-xs hover:bg-rose-700 transition">Close</button>
                                </form>
                            </td>
                        </tr>
                    `;
                }).join('');
            };

            const setApiStatus = ({ status, health, message }) => {
                if (!apiStatusLabel || !apiStatusDetail) return;

                const state = status || 'unknown';
                const labelMap = {
                    online: 'Online',
                    error: 'Tidak bisa dijangkau',
                    unknown: 'Tidak diketahui',
                };

                const label = labelMap[state] || labelMap.unknown;
                const detail = state === 'online' && health ? JSON.stringify(health) : (message || 'Cek konfigurasi API.');

                apiStatusLabel.textContent = label;
                apiStatusLabel.className = `${statusClass[state] || statusClass.unknown} font-semibold`;
                apiStatusDetail.textContent = detail;
                apiStatusDetail.className = 'text-xs text-slate-500 mt-1 break-all';
            };

            const refreshSessions = async () => {
                if (document.hidden) return;

                try {
                    const response = await fetch(listUrl, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    const data = await response.json();
                    renderSessions(data.sessions || []);
                } catch (error) {
                    console.error('Gagal refresh sessions:', error);
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
                    console.error('Gagal refresh API status:', error);
                    setApiStatus({ status: 'error', message: 'Tidak bisa menjangkau API.' });
                }
            };

            const refreshAll = () => {
                refreshSessions();
                refreshApiStatus();
            };

            refreshAll();
            setInterval(() => {
                if (document.hidden) return;
                refreshAll();
            }, refreshMs);
        })();
    </script>
</body>
</html>
