<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management - WA Gateway Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <style>
        body {
            background: radial-gradient(circle at 20% 20%, #e3f2fd 0, transparent 25%),
                        radial-gradient(circle at 80% 0%, #f3e8ff 0, transparent 20%),
                        #f5f6fa;
        }
        .device-card {
            transition: transform .15s ease, box-shadow .15s ease;
            border-radius: 16px;
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 28px rgba(0,0,0,0.06);
        }
        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0,0,0,0.12);
        }
        .badge-pill {
            border-radius: 999px;
            font-size: 11px;
            padding: 4px 10px;
        }
        .device-badge.status-connected { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .device-badge.status-connecting { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }
        .device-badge.status-disconnected { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .modal-content {
            border-radius: 16px;
            overflow: hidden;
        }
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4" id="device-page" data-gateway-base="{{ $gatewayConfig['base'] }}" data-gateway-key="{{ $gatewayConfig['key'] }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
            <div>
                <p class="text-muted mb-0">WA Gateway</p>
                <h1 class="h4 fw-bold mb-0">Device Management</h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-home me-1"></i> Dashboard</a>
                <a href="{{ route('devices.manage') }}" class="btn btn-primary btn-sm"><i class="fas fa-microchip me-1"></i> Device Management</a>
                <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-user me-1"></i> Profil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i> Logout</button>
                </form>
            </div>
        </div>
        <div id="global-alerts"></div>

        @if($statusMessage)
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-1"></i>{{ $statusMessage }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if(!empty($permissionWarnings))
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-1"></i>Beberapa izin/berkas perlu dicek agar non-root bisa jalan:
                <ul class="mb-0 ps-3">
                    @foreach ($permissionWarnings as $warn)
                        <li>{{ $warn }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center">
                <span class="btn btn-sm btn-primary disabled me-2"><i class="fas fa-plug"></i></span>
                <div>
                    <p class="text-muted small mb-0">List Device #all</p>
                    <h5 class="mb-0 fw-semibold">Devices</h5>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="input-group input-group-sm me-2" style="min-width: 240px;">
                    <input id="device-search" type="text" class="form-control" placeholder="Cari Device ID / Phone">
                    <button type="button" id="device-search-btn" class="btn btn-outline-secondary">Search</button>
                </div>
                <button type="button" id="btn-create-device" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i> Tambah Device</button>
            </div>
        </div>

        @php
            $statusMap = [];
            foreach (($sessionStatuses ?? []) as $row) {
                if (is_array($row) && isset($row['id'])) $statusMap[$row['id']] = $row;
            }
        @endphp

        <div class="row" id="device-grid">
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
                        'connected' => 'status-connected',
                        'connecting' => 'status-connecting',
                        default => 'status-disconnected',
                    };
                @endphp
                <div class="col-sm-6 col-xl-4 mb-3">
                    <div class="device-card h-100 p-3"
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
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div>
                                    <p class="fw-semibold mb-0">{{ $deviceName ?? $session }}</p>
                                    <p class="text-muted small mb-0 text-monospace">#{{ $session }}</p>
                                    <p class="device-phone text-monospace small mb-1">{{ $userDisplay }}</p>
                                </div>
                            </div>
                            <span class="badge badge-pill device-badge {{ $badgeClass }}">{{ ucfirst($st) }}</span>
                        </div>
                        <div class="mt-3 p-2 rounded bg-white border">
                            <p class="mb-1 small text-truncate"><span class="text-muted">Webhook:</span> <span class="text-monospace">{{ $cfg['webhookBaseUrl'] ?? '-' }}</span></p>
                            <p class="mb-0 small text-muted">
                                Incoming: {{ ($cfg['incomingEnabled'] ?? true) ? 'ON' : 'OFF' }} ·
                                AutoReply: {{ ($cfg['autoReplyEnabled'] ?? false) ? 'ON' : 'OFF' }}
                            </p>
                        </div>
                        <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                            <form method="POST" action="{{ route('sessions.start') }}" class="inline form-connect" data-session="{{ $session }}">
                                @csrf
                                <input type="hidden" name="session" value="{{ $session }}">
                                <button class="btn btn-dark btn-sm" title="Connect / QR" aria-label="Connect / QR"><i class="fas fa-link"></i></button>
                            </form>
                            <button type="button" class="btn-open-settings btn btn-warning btn-sm text-white" data-session="{{ $session }}" title="Settings" aria-label="Settings"><i class="fas fa-cog"></i></button>
                            <button type="button" class="btn-message-log btn btn-primary btn-sm text-white" data-session="{{ $session }}" title="Message Status Log" aria-label="Message Status Log"><i class="fas fa-list"></i></button>
                            <button type="button" class="btn-group-finder btn btn-info btn-sm text-white" data-session="{{ $session }}" title="Group ID Finder" aria-label="Group ID Finder"><i class="fas fa-users"></i></button>
                            <form method="POST" action="{{ route('devices.delete', $session) }}" onsubmit="return confirm('Hapus device {{ $session }}?');" class="inline">
                                @csrf
                                <button class="btn btn-danger btn-sm" title="Delete" aria-label="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-muted">Belum ada device.</div>
            @endforelse
        </div>
    </div>

    @include('partials.device-modals')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @include('partials.device-scripts')
</body>
</html>
