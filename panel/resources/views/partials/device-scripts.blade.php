<script>
(() => {
  const devicePage = document.getElementById('device-page');
  const gatewayBase = devicePage?.getAttribute('data-gateway-base') || '';
  const gatewayKey = devicePage?.getAttribute('data-gateway-key') || '';
  const sessionsBaseUrl = '{{ url('/sessions') }}';
  const apiStatusUrl = '{{ route('api.status') }}';
  const deviceStatusUrl = '{{ route('devices.status') }}';
  const csrf = '{{ csrf_token() }}';
  const tokenTargets = @json($tokenTargets ?? []);

  const apiStatusLabel = document.getElementById('api-status-label');
  const apiStatusDetail = document.getElementById('api-status-detail');
  const refreshMs = 5000;

  const statusClass = {
    online: 'text-success',
    error: 'text-danger',
    unknown: 'text-warning',
  };

  const setApiStatus = ({ status, health, message }) => {
    if (!apiStatusLabel || !apiStatusDetail) return;
    const state = status || 'unknown';
    const labelMap = { online: 'Online', error: 'Tidak bisa dijangkau', unknown: 'Tidak diketahui' };
    const label = labelMap[state] || labelMap.unknown;
    const detail = state === 'online' && health ? JSON.stringify(health) : (message || 'Cek konfigurasi API.');
    apiStatusLabel.textContent = label;
    apiStatusLabel.className = `${statusClass[state] || statusClass.unknown} fw-semibold`;
    apiStatusDetail.textContent = detail;
    apiStatusDetail.className = 'text-muted small mt-1 break-all';
  };

  const lastStatuses = new Map();
  document.querySelectorAll('.device-card').forEach((card) => {
    const id = card.getAttribute('data-device');
    const st = card.getAttribute('data-status') || 'disconnected';
    if (id) lastStatuses.set(id, st);
  });

  const applyDeviceStatus = (device) => {
    const card = document.querySelector(`.device-card[data-device="${CSS.escape(device.id)}"]`);
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
      badge.className = `badge badge-pill device-badge ${
        status === 'connected'
          ? 'status-connected'
          : status === 'connecting'
          ? 'status-connecting'
          : 'status-disconnected'
      }`;
    }
    if (phone) {
      const clean = (device.user?.id || '').replace(/@.*/, '');
      phone.textContent = clean || 'Not linked';
    }
    const cleanPhone = (device.user?.id || '').replace(/@.*/, '');
    card.setAttribute('data-phone', cleanPhone || '');
    return { prevStatus, status };
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
    if (!apiStatusUrl || document.hidden) return;
    try {
      const response = await fetch(apiStatusUrl, { headers: { 'Accept': 'application/json' } });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const data = await response.json();
      setApiStatus(data);
    } catch (error) {
      setApiStatus({ status: 'error', message: 'Tidak bisa menjangkau API.' });
    }
  };

  const modalCreateEl = document.getElementById('modal-create-device');
  const modalCreate = modalCreateEl ? new bootstrap.Modal(modalCreateEl) : null;
  const modalCreateResultEl = document.getElementById('modal-create-result');
  const modalCreateResult = modalCreateResultEl ? new bootstrap.Modal(modalCreateResultEl) : null;
  const resultBody = document.getElementById('create-result-body');
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
  const modalSyncTokenEl = document.getElementById('modal-sync-token');
  const modalSyncToken = modalSyncTokenEl ? new bootstrap.Modal(modalSyncTokenEl) : null;
  const syncForm = document.getElementById('sync-token-form');
  const syncTargetSelect = document.getElementById('sync-target');
  const syncTargetInfo = document.getElementById('sync-target-info');
  const syncDeviceLabel = document.getElementById('sync-device-label');
  const createForm = document.getElementById('create-form');
  const step1 = document.getElementById('create-step-1');
  const step2 = document.getElementById('create-step-2');
  const btnNext = document.getElementById('btn-create-next');
  const btnBack = document.getElementById('btn-create-back');
  const btnSubmit = document.getElementById('btn-create-submit');
  const inputName = document.getElementById('create-device-name');
  const inputPhone = document.getElementById('create-device-phone');
  const confirmName = document.getElementById('confirm-device-name');
  const confirmPhone = document.getElementById('confirm-device-phone');
  const confirmMode = document.getElementById('confirm-device-mode');
  const alertPlaceholder = document.getElementById('global-alerts');

  const show = (modal) => modal?.show();

  const showAlert = (msg, variant = 'success') => {
    if (!alertPlaceholder) return;
    const div = document.createElement('div');
    div.className = `alert alert-${variant} alert-dismissible fade show`;
    div.innerHTML = `<span>${msg}</span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    alertPlaceholder.appendChild(div);
  };

  const syncTargetsAvailable = tokenTargets && Object.keys(tokenTargets).length > 0;
  if (syncTargetsAvailable && syncForm && modalSyncToken) {
    const actionTemplate = syncForm.getAttribute('data-action-template') || '';
    const updateSyncInfo = () => {
      if (!syncTargetSelect || !syncTargetInfo) return;
      const key = syncTargetSelect.value;
      const target = tokenTargets[key] || {};
      const envPath = target.env_path || '-';
      const envKey = target.env_key || 'WA_GATEWAY_TOKEN';
      const allowed = Array.isArray(target.allowed_sessions) && target.allowed_sessions.length > 0
        ? target.allowed_sessions.join(', ')
        : 'Semua device';
      syncTargetInfo.textContent = `ENV: ${envPath} (${envKey}) • Allowed: ${allowed}`;
    };

    syncTargetSelect?.addEventListener('change', updateSyncInfo);
    updateSyncInfo();

    document.querySelectorAll('.btn-sync-token').forEach((btn) => {
      btn.addEventListener('click', () => {
        const session = btn.getAttribute('data-session') || '';
        if (!session) return;
        if (actionTemplate) {
          syncForm.setAttribute('action', actionTemplate.replace('__device__', encodeURIComponent(session)));
        }
        if (syncDeviceLabel) syncDeviceLabel.textContent = session;
        updateSyncInfo();
        modalSyncToken.show();
      });
    });
  }

  btnCreate?.addEventListener('click', () => {
    if (createForm) createForm.classList.remove('was-validated');
    toggleStep(1);
    show(modalCreate);
  });
  modalCreateEl?.addEventListener('hidden.bs.modal', () => {
    createForm?.classList.remove('was-validated');
    createForm?.reset();
    if (modeInput) modeInput.value = 'qr';
    modeButtons.forEach((b, idx) => {
      const isDefault = idx === 0;
      b.classList.toggle('active', isDefault);
      b.classList.toggle('btn-primary', isDefault);
      b.classList.toggle('btn-outline-secondary', !isDefault);
    });
    toggleStep(1);
  });
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

  const pollStatusUntilConnected = (sessionId, onConnected) => {
    const interval = setInterval(async () => {
      try {
        const response = await fetch(deviceStatusUrl, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        const devices = Array.isArray(data.devices) ? data.devices : [];
        const found = devices.find((d) => d.id === sessionId);
        if (found?.status === 'connected') {
          clearInterval(interval);
          onConnected?.();
        }
      } catch {
        // ignore
      }
    }, 2000);
  };

  const renderCreateResult = (payload, mode, sessionId) => {
    if (!resultBody) return;
    if (mode === 'qr') {
      const qr = payload.qr;
      const qrImage = payload.qr_image || payload.qrImage || null;
      const qrSrc = qrImage
        ? qrImage
        : qr
        ? `https://quickchart.io/qr?text=${encodeURIComponent(qr)}&size=300`
        : null;
      resultBody.innerHTML = qrSrc
        ? `
            <p class="text-muted small mb-2">Session: ${sessionId}</p>
            <img class="img-fluid rounded border" src="${qrSrc}" alt="QR Code">
            <p class="text-muted small mt-2 mb-0">Scan dengan WhatsApp Anda.</p>
          `
        : `<p class="text-muted small mb-0">Tidak ada QR yang diterima (mungkin sudah tersambung).</p>`;
    } else {
      const code = payload.pairing_code || payload.pairingCode || null;
      resultBody.innerHTML = code
        ? `
            <p class="text-muted small mb-2">Session: ${sessionId}</p>
            <div class="p-3 bg-primary-subtle border rounded text-center">
              <p class="h4 mb-1 text-primary">${code}</p>
              <p class="text-muted small mb-0">Masukkan pairing code ini di WhatsApp (Link with phone number).</p>
            </div>
          `
        : `<p class="text-muted small mb-0">Pairing code tidak tersedia.</p>`;
    }
  };

  const toggleStep = (step) => {
    if (!step1 || !step2 || !btnNext || !btnBack || !btnSubmit) return;
    const step1Active = step === 1;
    step1.classList.toggle('d-none', !step1Active);
    step2.classList.toggle('d-none', step1Active);
    btnNext.classList.toggle('d-none', !step1Active);
    btnBack.classList.toggle('d-none', step1Active);
    btnSubmit.classList.toggle('d-none', step1Active);
  };

  btnNext?.addEventListener('click', () => {
    const name = (inputName?.value || '').trim();
    const phone = (inputPhone?.value || '').trim();
    if (!name || !phone) {
      if (createForm) createForm.classList.add('was-validated');
      return;
    }
    const mode = (modeInput?.value || 'qr').toLowerCase();
    if (confirmName) confirmName.textContent = name;
    if (confirmPhone) confirmPhone.textContent = phone;
    if (confirmMode) confirmMode.textContent = mode === 'code' ? 'Pairing Code' : 'Scan QR';
    toggleStep(2);
  });

  btnBack?.addEventListener('click', () => {
    toggleStep(1);
  });

  const runCreate = async () => {
    const name = (inputName?.value || '').trim();
    const phone = (inputPhone?.value || '').trim();
    const mode = (modeInput?.value || 'qr').toLowerCase();
    if (!name || !phone) {
      toggleStep(1);
      if (createForm) createForm.classList.add('was-validated');
      return;
    }
    if (resultBody) {
      resultBody.innerHTML = `
        <div class="d-flex align-items-center">
          <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
          <div>
            <p class="mb-0 fw-semibold">Memproses...</p>
            <p class="text-muted small mb-0">Memulai proses ${mode === 'code' ? 'Pairing Code' : 'Scan QR'}.</p>
          </div>
        </div>`;
    }
    modalCreate?.hide();
    show(modalCreateResult);

    if (btnSubmit) btnSubmit.disabled = true;
    try {
      const url = '{{ route('devices.create_json') }}';
      const body = JSON.stringify({ device_phone: phone, device_name: name, mode });
      const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
      };
      const res = await fetch(url, { method: 'POST', headers, body });
      const data = await res.json().catch(() => ({}));
      const ok = data.ok ?? data.status ?? false;
      if (!ok) throw new Error(data.message || 'Gagal membuat device');
      const payload = data.data || data || {};
      renderCreateResult(payload, mode, payload.device || phone);
      pollStatusUntilConnected(payload.device || phone, () => {
        modalCreateResult?.hide();
        showAlert('Perangkat berhasil ditambahkan.', 'success');
        setTimeout(() => {
          window.location.reload();
        }, 400);
      });
    } catch (err) {
      if (resultBody) {
        resultBody.innerHTML = `<p class="text-danger small mb-0">${err?.message || 'Gagal membuat device'}</p>`;
      } else {
        showAlert(err?.message || 'Gagal membuat device', 'danger');
      }
    } finally {
      if (btnSubmit) btnSubmit.disabled = false;
    }
  };

  btnSubmit?.addEventListener('click', runCreate);

  document.querySelectorAll('.form-connect').forEach((form) => {
    form.addEventListener('submit', (e) => {
      const session = form.getAttribute('data-session') || '';
      if (!session) return;
      const card = document.querySelector(`.device-card[data-device="${CSS.escape(session)}"]`);
      const status = card?.getAttribute('data-status') || 'disconnected';
      const linkedPhone = (card?.getAttribute('data-phone') || '').trim();

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
      const card = document.querySelector(`.device-card[data-device="${CSS.escape(session)}"]`);
      if (!card) return;

      const name = card.getAttribute('data-name') || '';
      const webhook = card.getAttribute('data-webhook') || '';
      const apiKeyStored = card.getAttribute('data-api-key') || '';
      const incoming = card.getAttribute('data-incoming') === '1';
      const autoreply = card.getAttribute('data-autoreply') === '1';
      const tracking = card.getAttribute('data-tracking') === '1';
      const deviceStatus = card.getAttribute('data-device-status') === '1';

      settingsContent.innerHTML = `
        <form method="POST" action="${'{{ url('/sessions') }}'}/${encodeURIComponent(session)}/config" class="row g-3 needs-validation" novalidate>
            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
            <div class="col-12">
                <label class="form-label text-muted small">Device Name</label>
                <input name="device_name" type="text" value="${name.replace(/\\\"/g,'&quot;')}" class="form-control" placeholder="Nama device">
            </div>
            <div class="col-12">
                <label class="form-label text-muted small">Webhook URL (Incoming & Auto Reply)</label>
                <div class="input-group">
                    <input id="webhook_base_url" name="webhook_base_url" type="text" value="${webhook.replace(/\\\"/g,'&quot;')}" class="form-control" required>
                    <button type="button" class="btn btn-outline-secondary btn-test-webhook" data-type="base" data-session="${encodeURIComponent(session)}">Test</button>
                </div>
                <p class="text-muted small mb-0 mt-1">Endpoint test: <span class="text-monospace">/message</span></p>
                <p id="test-result-base" class="small mt-1"></p>
            </div>
            <div class="col-12">
                <label class="form-label text-muted small">API Key</label>
                <div class="input-group">
                    <input id="api_key" name="api_key" type="text" value="${apiKeyStored.replace(/\\\"/g,'&quot;')}" class="form-control" placeholder="API key">
                    <button type="button" id="btn-generate-apikey" class="btn btn-dark">Generate</button>
                </div>
            </div>
            <div class="col-12 d-flex flex-wrap gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="incoming_enabled" value="1" id="incoming_enabled" ${incoming ? 'checked' : ''}>
                    <label class="form-check-label" for="incoming_enabled">Get Incoming Message</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="auto_reply_enabled" value="1" id="auto_reply_enabled" ${autoreply ? 'checked' : ''}>
                    <label class="form-check-label" for="auto_reply_enabled">Get Auto Reply From Webhook</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tracking_enabled" value="1" id="tracking_enabled" ${tracking ? 'checked' : ''}>
                    <label class="form-check-label" for="tracking_enabled">Get Tracking URL (status)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="device_status_enabled" value="1" id="device_status_enabled" ${deviceStatus ? 'checked' : ''}>
                    <label class="form-check-label" for="device_status_enabled">Get Device Status</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label text-muted small">Tracking Webhook URL (Status)</label>
                <div class="input-group">
                    <input id="tracking_webhook_base_url" name="tracking_webhook_base_url" type="text" class="form-control" placeholder="kosong = pakai Webhook URL utama">
                    <button type="button" class="btn btn-outline-secondary btn-test-webhook" data-type="tracking" data-session="${encodeURIComponent(session)}">Test</button>
                </div>
                <p class="text-muted small mb-0 mt-1">Endpoint test: <span class="text-monospace">/status</span></p>
                <p id="test-result-tracking" class="small mt-1"></p>
            </div>
            <div class="col-12">
                <label class="form-label text-muted small">Device Status Webhook URL</label>
                <div class="input-group">
                    <input id="device_status_webhook_base_url" name="device_status_webhook_base_url" type="text" class="form-control" placeholder="kosong = pakai Webhook URL utama">
                    <button type="button" class="btn btn-outline-secondary btn-test-webhook" data-type="device_status" data-session="${encodeURIComponent(session)}">Test</button>
                </div>
                <p class="text-muted small mb-0 mt-1">Endpoint test: <span class="text-monospace">/session</span></p>
                <p id="test-result-device_status" class="small mt-1"></p>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-primary">Save</button>
            </div>
        </form>
        <hr class="my-3">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <h6 class="card-title mb-2"><i class="fas fa-paper-plane me-2 text-primary"></i>Test Kirim WA</h6>
                <p class="text-muted small mb-2">Kirim pesan uji coba ke nomor tertentu untuk memastikan perangkat aktif.</p>
                <div class="row g-2 align-items-center">
                    <div class="col-md-8">
                        <input type="text" class="form-control form-control-sm" id="test-phone" placeholder="Contoh: 62812xxxxxxx">
                    </div>
                    <div class="col-md-4 d-grid d-md-block">
                        <button type="button" class="btn btn-success btn-sm w-100 w-md-auto" id="btn-test-send">Kirim Test</button>
                    </div>
                </div>
                <p class="text-muted small mt-1 mb-0">Pesan: <span class="text-monospace">aplikasi berjalan lancar dan perangkat ${session} berjalan normal. id_device: #6285158663803</span></p>
                <p id="test-send-result" class="small mt-2 mb-0 text-muted"></p>
            </div>
        </div>
      `;

      const trackingUrl = card.getAttribute('data-tracking-webhook') || '';
      const deviceStatusUrl = card.getAttribute('data-device-status-webhook') || '';
      const trackingInput = settingsContent.querySelector('#tracking_webhook_base_url');
      const deviceStatusInput = settingsContent.querySelector('#device_status_webhook_base_url');
      if (trackingInput) trackingInput.value = trackingUrl;
      if (deviceStatusInput) deviceStatusInput.value = deviceStatusUrl;

      const genBtn = settingsContent.querySelector('#btn-generate-apikey');
      genBtn?.addEventListener('click', () => {
        const input = settingsContent.querySelector('#api_key');
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

      const testButtons = settingsContent.querySelectorAll('.btn-test-webhook');
      testButtons.forEach((b) => {
        b.addEventListener('click', async () => {
          const type = b.getAttribute('data-type') || 'base';
          const sessionEncoded = b.getAttribute('data-session') || encodeURIComponent(session);
          const apiKeyInput = settingsContent.querySelector('#api_key');
          const apiKey = apiKeyInput?.value || '';
          const urlFieldId = type === 'tracking'
              ? 'tracking_webhook_base_url'
              : (type === 'device_status' ? 'device_status_webhook_base_url' : 'webhook_base_url');
          const urlInput = settingsContent.querySelector('#' + urlFieldId);
          const url = (urlInput?.value || '').trim();
          const out = settingsContent.querySelector(`#test-result-${type}`);
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

      const btnTestSend = settingsContent.querySelector('#btn-test-send');
      const testResult = settingsContent.querySelector('#test-send-result');
      btnTestSend?.addEventListener('click', async () => {
        const phoneInput = settingsContent.querySelector('#test-phone');
        const phone = (phoneInput?.value || '').trim();
        if (!phone) {
          if (testResult) {
            testResult.textContent = 'Masukkan nomor tujuan.';
            testResult.className = 'small mt-2 text-danger';
          }
          return;
        }
        if (testResult) {
          testResult.textContent = 'Mengirim...';
          testResult.className = 'small mt-2 text-secondary';
        }
        try {
          const res = await fetch(`${'{{ url('/sessions') }}'}/${encodeURIComponent(session)}/test-send`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ phone }),
          });
          const data = await res.json().catch(() => ({}));
          const ok = !!data.ok;
          if (testResult) {
            testResult.textContent = ok ? 'Pesan uji coba dikirim.' : (data.message || 'Gagal mengirim pesan.');
            testResult.className = `small mt-2 ${ok ? 'text-success' : 'text-danger'}`;
          }
        } catch (err) {
          if (testResult) {
            testResult.textContent = 'Gagal mengirim pesan.';
            testResult.className = 'small mt-2 text-danger';
          }
        }
      });

      show(modalSettings);
    });
  });

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
        <div class="log-result mt-2 text-sm text-muted">Loading...</div>
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
              logResult.className = 'log-result mt-2 text-sm text-secondary';
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
                  logResult.className = 'log-result mt-2 text-sm text-warning';
                  return;
              }
              logResult.className = 'log-result mt-2 text-sm text-muted overflow-x-auto';
              logResult.innerHTML = `
                  <table class="min-w-full text-xs border border-slate-100 rounded-lg overflow-hidden">
                      <thead class="bg-light text-muted">
                          <tr>
                              <th class="px-2 py-2 text-start">Message ID</th>
                              <th class="px-2 py-2 text-start">Tujuan</th>
                              <th class="px-2 py-2 text-start">Isi</th>
                              <th class="px-2 py-2 text-start">Status</th>
                              <th class="px-2 py-2 text-start">Updated</th>
                          </tr>
                      </thead>
                      <tbody>
                          ${filtered.map((item) => {
                              const status = (item.status || '').toString();
                              const ts = item.updatedAt ? new Date(item.updatedAt).toLocaleString() : '-';
                              const id = (item.id || '').toString();
                              const to = escapeHtml(item.to || '-');
                              const previewRaw = escapeHtml(item.preview || '');
                              const preview = previewRaw || '<span class="text-muted">-</span>';
                              const badgeClass = status === 'delivered' || status === 'read' || status === 'sent'
                                  ? 'bg-success-subtle text-success border-success'
                                  : status === 'pending'
                                  ? 'bg-warning-subtle text-warning border-warning'
                                  : 'bg-danger-subtle text-danger border-danger';
                              return `
                                  <tr class="border-top border-slate-100">
                                      <td class="px-2 py-2 font-monospace text-break">${id}</td>
                                      <td class="px-2 py-2 font-monospace text-break">${to}</td>
                                      <td class="px-2 py-2 text-break">${preview}</td>
                                      <td class="px-2 py-2">
                                          <span class="text-[11px] px-2 py-0.5 rounded-pill border ${badgeClass}">${status || '-'}</span>
                                      </td>
                                      <td class="px-2 py-2 text-muted">${ts}</td>
                                  </tr>
                              `;
                          }).join('')}
                      </tbody>
                  </table>
              `;
          } catch (err) {
              if (!logResult) return;
              logResult.textContent = 'Gagal memuat log status.';
              logResult.className = 'log-result mt-2 text-sm text-danger';
          }
      };

      messageLogContent.querySelector('.btn-refresh-log')?.addEventListener('click', loadLog);
      messageLogContent.querySelector('.btn-apply-log-filter')?.addEventListener('click', loadLog);
      loadLog();
      show(modalMessageLog);
    });
  });

  const searchInput = document.getElementById('device-search');
  const searchBtn = document.getElementById('device-search-btn');
  const applySearch = () => {
    const q = (searchInput?.value || '').trim().toLowerCase();
    document.querySelectorAll('.device-card').forEach((card) => {
        const id = (card.getAttribute('data-device') || '').toLowerCase();
        const phone = (card.getAttribute('data-phone') || '').toLowerCase();
        const match = !q || id.includes(q) || phone.includes(q);
        card.closest('.col-sm-6')?.classList.toggle('d-none', !match);
        card.closest('.col-xl-4')?.classList.toggle('d-none', !match);
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
