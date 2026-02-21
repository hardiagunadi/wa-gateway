<x-filament-panels::page>
    {{-- Tombol Tambah Perangkat --}}
    <div class="flex justify-end mb-4">
        <button
            type="button"
            wire:click="openCreateModal"
            class="fi-btn inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400 transition"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:20px;height:20px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Tambah Perangkat
        </button>
    </div>

    {{ $this->table }}

    {{-- ============================================= --}}
    {{-- MODAL WIZARD TAMBAH PERANGKAT                 --}}
    {{-- ============================================= --}}
    <x-filament::modal id="create-device" width="lg" :close-by-clicking-away="false">

        <x-slot name="heading">
            @if($wizardStep === 1)
                Tambah Perangkat Baru
            @elseif($createMode === 'qr')
                Scan QR Code
            @else
                Masukkan Kode Pairing
            @endif
        </x-slot>

        <x-slot name="description">
            @if($wizardStep === 1)
                Pilih metode pairing, isi nama dan nomor WhatsApp.
            @elseif($createMode === 'qr')
                Buka WhatsApp &rarr; Perangkat Tertaut &rarr; Tautkan Perangkat
            @else
                Buka WhatsApp &rarr; Perangkat Tertaut &rarr; Tautkan dengan Nomor Telepon
            @endif
        </x-slot>

        {{-- Wrapper dengan wire:key agar Livewire re-render saat step berubah --}}
        <div wire:key="wizard-step-{{ $wizardStep }}-{{ $createMode }}-{{ $pairingCodeSent ? '1' : '0' }}">

        {{-- Step Indicator --}}
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
            <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:12px;font-weight:700;{{ $wizardStep >= 1 ? 'background:#4f46e5;color:#fff;' : 'background:#e5e7eb;color:#6b7280;' }}">1</span>
            <span style="font-size:12px;font-weight:500;{{ $wizardStep >= 1 ? 'color:#4f46e5;' : 'color:#9ca3af;' }}">Data</span>
            <span style="flex:1;height:1px;{{ $wizardStep >= 2 ? 'background:#4f46e5;' : 'background:#e5e7eb;' }}"></span>
            <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:12px;font-weight:700;{{ $wizardStep >= 2 ? 'background:#4f46e5;color:#fff;' : 'background:#e5e7eb;color:#6b7280;' }}">2</span>
            <span style="font-size:12px;font-weight:500;{{ $wizardStep >= 2 ? 'color:#4f46e5;' : 'color:#9ca3af;' }}">Pairing</span>
        </div>

        {{-- ========================================= --}}
        {{-- STEP 1 : Form Input                       --}}
        {{-- ========================================= --}}
        @if($wizardStep === 1)
        <div style="display:flex;flex-direction:column;gap:16px;">

            {{-- Pilihan Mode Pairing --}}
            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;">Metode Pairing</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    {{-- QR Code --}}
                    <button
                        type="button"
                        wire:click="$set('createMode', 'qr')"
                        style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 10px;border-radius:10px;border:2px solid {{ $createMode === 'qr' ? '#4f46e5' : '#e5e7eb' }};background:{{ $createMode === 'qr' ? '#eef2ff' : '#fff' }};cursor:pointer;transition:all .15s;"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="{{ $createMode === 'qr' ? '#4f46e5' : '#9ca3af' }}" style="width:28px;height:28px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                        </svg>
                        <span style="font-size:13px;font-weight:600;color:{{ $createMode === 'qr' ? '#4338ca' : '#4b5563' }};">QR Code</span>
                        <span style="font-size:11px;color:#9ca3af;">Scan dari HP</span>
                    </button>

                    {{-- Pairing Code --}}
                    <button
                        type="button"
                        wire:click="$set('createMode', 'code')"
                        style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px 10px;border-radius:10px;border:2px solid {{ $createMode === 'code' ? '#4f46e5' : '#e5e7eb' }};background:{{ $createMode === 'code' ? '#eef2ff' : '#fff' }};cursor:pointer;transition:all .15s;"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="{{ $createMode === 'code' ? '#4f46e5' : '#9ca3af' }}" style="width:28px;height:28px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-6 18.75h6" />
                        </svg>
                        <span style="font-size:13px;font-weight:600;color:{{ $createMode === 'code' ? '#4338ca' : '#4b5563' }};">Pairing Code</span>
                        <span style="font-size:11px;color:#9ca3af;">Input kode manual</span>
                    </button>
                </div>
            </div>

            {{-- Nama Perangkat --}}
            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">Nama Perangkat</label>
                <input
                    type="text"
                    wire:model="createName"
                    placeholder="Contoh: HP Kantor"
                    style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;outline:none;"
                />
            </div>

            {{-- Nomor WhatsApp --}}
            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">Nomor WhatsApp</label>
                <input
                    type="text"
                    wire:model="createPhone"
                    placeholder="085123456789"
                    style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;outline:none;"
                />
                <p style="margin-top:4px;font-size:11px;color:#9ca3af;">
                    &#9432; Format 08xxx otomatis dikonversi ke 628xxx
                </p>
            </div>
        </div>
        @endif

        {{-- ========================================= --}}
        {{-- STEP 2A : QR Code                         --}}
        {{-- ========================================= --}}
        @if($wizardStep === 2 && $createMode === 'qr')
        <div
            x-data="{
                statusTimer: null,
                init() {
                    this.statusTimer = setInterval(() => $wire.checkQrStatus(), 5000);
                },
                destroy() {
                    if (this.statusTimer) clearInterval(this.statusTimer);
                }
            }"
            style="display:flex;flex-direction:column;align-items:center;gap:16px;"
        >
            {{-- QR Image --}}
            @if($qrImage)
                <div style="position:relative;padding:12px;border:2px solid #e5e7eb;border-radius:12px;background:#fff;">
                    <img src="{{ $qrImage }}" alt="QR Code" style="max-height:250px;max-width:250px;border-radius:8px;display:block;" />
                    <div wire:loading wire:target="refreshQr" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.85);border-radius:12px;">
                        <svg style="width:32px;height:32px;animation:spin 1s linear infinite;color:#4f46e5;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    </div>
                </div>
            @else
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:250px;width:250px;border:2px dashed #e5e7eb;border-radius:12px;background:#f9fafb;">
                    <svg style="width:32px;height:32px;animation:spin 1s linear infinite;color:#4f46e5;margin-bottom:8px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span style="font-size:13px;color:#9ca3af;">Memuat QR Code...</span>
                </div>
            @endif

            <p style="font-size:11px;color:#9ca3af;text-align:center;">
                Jika QR expired, klik tombol Refresh QR di bawah
            </p>

            <button
                type="button"
                wire:click="refreshQr"
                wire:loading.attr="disabled"
                style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:13px;font-weight:500;color:#374151;cursor:pointer;"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                Refresh QR
            </button>
        </div>
        @endif

        {{-- ========================================= --}}
        {{-- STEP 2B : Pairing Code                    --}}
        {{-- ========================================= --}}
        @if($wizardStep === 2 && $createMode === 'code')
        <div
            x-data="{
                statusTimer: null,
                init() {
                    this.statusTimer = setInterval(() => $wire.checkPairingStatus(), 5000);
                },
                destroy() {
                    if (this.statusTimer) clearInterval(this.statusTimer);
                }
            }"
            style="display:flex;flex-direction:column;gap:16px;"
        >
            {{-- Info Nomor WA --}}
            <div style="padding:14px;border-radius:10px;background:#f9fafb;border:1px solid #e5e7eb;text-align:center;">
                <p style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Nomor WhatsApp</p>
                <p style="font-size:22px;font-weight:700;color:#111827;font-family:monospace;letter-spacing:1px;">{{ $activeSessionId }}</p>
            </div>

            @if(!$pairingCodeSent)
                {{-- Belum kirim kode --}}
                <div style="text-align:center;">
                    <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Klik tombol di bawah untuk membuat kode pairing.</p>
                    <button
                        type="button"
                        wire:click="sendPairingCode"
                        wire:loading.attr="disabled"
                        style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border:none;border-radius:8px;background:#4f46e5;color:#fff;font-size:13px;font-weight:600;cursor:pointer;"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                        <span wire:loading.remove wire:target="sendPairingCode">Buat Kode Pairing</span>
                        <span wire:loading wire:target="sendPairingCode">Memproses...</span>
                    </button>
                </div>
            @else
                {{-- Kode pairing --}}
                @if($pairingCode)
                <div style="padding:18px;border-radius:12px;background:#ecfdf5;border:2px solid #a7f3d0;text-align:center;">
                    <p style="font-size:10px;color:#059669;text-transform:uppercase;letter-spacing:2px;font-weight:700;margin-bottom:6px;">Kode Pairing Anda</p>
                    <p style="font-size:36px;font-weight:900;color:#065f46;font-family:monospace;letter-spacing:6px;">{{ $pairingCode }}</p>
                </div>
                @endif

                {{-- Instruksi --}}
                <div style="padding:12px 14px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;">
                    <p style="font-size:13px;font-weight:600;color:#1d4ed8;margin-bottom:6px;">Cara memasukkan kode:</p>
                    <ol style="margin:0;padding-left:18px;font-size:12px;color:#2563eb;line-height:1.8;">
                        <li>Buka <strong>WhatsApp</strong> di HP</li>
                        <li>Ketuk <strong>Perangkat Tertaut</strong></li>
                        <li>Ketuk <strong>Tautkan Perangkat</strong></li>
                        <li>Pilih <strong>Tautkan dengan nomor telepon</strong></li>
                        <li>Masukkan kode di atas</li>
                    </ol>
                </div>

                {{-- Status polling --}}
                <p style="text-align:center;font-size:11px;color:#9ca3af;">
                    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#22c55e;margin-right:4px;animation:pulse 2s infinite;"></span>
                    Menunggu koneksi... (cek otomatis setiap 5 detik)
                </p>

                {{-- Tombol kirim ulang --}}
                <div style="text-align:center;">
                    <button
                        type="button"
                        wire:click="resendPairingCode"
                        wire:loading.attr="disabled"
                        style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border:1px solid #fbbf24;border-radius:8px;background:#fffbeb;color:#92400e;font-size:13px;font-weight:500;cursor:pointer;"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                        <span wire:loading.remove wire:target="resendPairingCode">Kirim Ulang Kode</span>
                        <span wire:loading wire:target="resendPairingCode">Mengirim...</span>
                    </button>
                </div>
            @endif
        </div>
        @endif

        </div>{{-- end wire:key wrapper --}}

        {{-- ========================================= --}}
        {{-- Footer                                    --}}
        {{-- ========================================= --}}
        <x-slot name="footer">
            <div style="display:flex;justify-content:space-between;width:100%;">
                @if($wizardStep === 1)
                    <button
                        type="button"
                        x-on:click="$dispatch('close-modal', { id: 'create-device' })"
                        style="padding:8px 16px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:13px;font-weight:500;color:#374151;cursor:pointer;"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="proceedToStep2"
                        wire:loading.attr="disabled"
                        style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;border:none;border-radius:8px;background:#4f46e5;color:#fff;font-size:13px;font-weight:600;cursor:pointer;"
                    >
                        <span wire:loading.remove wire:target="proceedToStep2">Selanjutnya</span>
                        <span wire:loading wire:target="proceedToStep2">Memproses...</span>
                        <svg wire:loading.remove wire:target="proceedToStep2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                    </button>
                @else
                    <div></div>
                    <button
                        type="button"
                        wire:click="closeCreateModal"
                        style="padding:8px 16px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:13px;font-weight:500;color:#374151;cursor:pointer;"
                    >
                        Tutup
                    </button>
                @endif
            </div>
        </x-slot>
    </x-filament::modal>

    <style>
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .4; } }
    </style>
</x-filament-panels::page>
