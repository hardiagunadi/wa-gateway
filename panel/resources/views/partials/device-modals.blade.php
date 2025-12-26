    <div class="modal fade" id="modal-create-device" tabindex="-1" aria-labelledby="modalCreateLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateLabel">Create Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="create-form" novalidate>
                    <div class="modal-body">
                        <div id="create-step-1">
                            <div class="row g-3">
                                <input type="hidden" name="mode" id="create-mode" value="qr">
                                <div class="col-12">
                                    <label class="form-label text-muted small">Device Name</label>
                                    <input name="device_name" id="create-device-name" type="text" class="form-control" placeholder="mis. TopSETTING" required>
                                    <div class="invalid-feedback">Nama device wajib diisi.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small">Nomor WA Device</label>
                                    <input name="device_phone" id="create-device-phone" type="text" class="form-control" placeholder="62812xxxxxx" required>
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
                            </div>
                        </div>
                        <div id="create-step-2" class="d-none">
                            <p class="text-muted small mb-1">Konfirmasi data sebelum proses:</p>
                            <ul class="list-unstyled small mb-3">
                                <li><strong>Device Name:</strong> <span id="confirm-device-name"></span></li>
                                <li><strong>Nomor WA:</strong> <span id="confirm-device-phone"></span></li>
                                <li><strong>Metode:</strong> <span id="confirm-device-mode"></span></li>
                            </ul>
                            <p class="text-muted small mb-0">Klik Create untuk memulai proses Scan QR / Pairing Code.</p>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btn-create-back">Back</button>
                    <button type="button" class="btn btn-success btn-sm" id="btn-create-next">Next</button>
                    <button type="button" class="btn btn-primary btn-sm d-none" id="btn-create-submit">Create</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-create-result" tabindex="-1" aria-labelledby="modalCreateResultLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateResultLabel">QR / Pairing Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="create-result-body">
                    <p class="text-muted small mb-0">Menunggu data...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-settings" tabindex="-1" aria-labelledby="modalSettingsLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
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
