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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white shadow rounded-xl p-5 border border-slate-100">
                <p class="text-sm text-slate-500 mb-1">API Status</p>
                @if($apiError)
                    <p class="text-rose-600 font-semibold">Tidak bisa dijangkau</p>
                    <p class="text-xs text-slate-500 mt-1">{{ $apiError }}</p>
                @elseif($health)
                    <p class="text-emerald-600 font-semibold">Online</p>
                    <p class="text-xs text-slate-500 mt-1">{{ json_encode($health) }}</p>
                @else
                    <p class="text-amber-600 font-semibold">Tidak diketahui</p>
                    <p class="text-xs text-slate-500 mt-1">Cek konfigurasi API.</p>
                @endif
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

            <div class="bg-white shadow rounded-xl p-5 border border-slate-100">
                <p class="text-sm text-slate-500 mb-2">Webhook & API Key</p>
                <form method="POST" action="{{ route('webhook.update') }}" class="space-y-3">
                    @csrf
                    <label class="block text-xs text-slate-500">Webhook Base URL</label>
                    <input type="text" name="webhook_base_url" value="{{ old('webhook_base_url', $nodeEnv['WEBHOOK_BASE_URL'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <label class="block text-xs text-slate-500">API KEY (disimpan ke .env Node)</label>
                    <input type="text" name="gateway_key" value="{{ old('gateway_key', $nodeEnv['KEY'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <button class="w-full px-3 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 transition">Simpan</button>
                    <p class="text-xs text-slate-500">Setelah ubah env, restart server agar terbaca.</p>
                </form>
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

                @if(!empty($sessions))
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500">
                                    <th class="py-2">Session</th>
                                    <th class="py-2 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sessions as $session)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-2 font-medium">{{ $session }}</td>
                                        <td class="py-2 text-right">
                                            <form method="POST" action="{{ route('sessions.close', $session) }}" onsubmit="return confirm('Tutup session {{ $session }}?');" class="inline">
                                                @csrf
                                                <button class="px-3 py-1 rounded-lg bg-rose-600 text-white text-xs hover:bg-rose-700 transition">Close</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-slate-500">Belum ada session aktif.</p>
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
</body>
</html>
