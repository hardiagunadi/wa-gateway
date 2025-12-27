<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - WA Gateway Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <style>
        body {
            background: radial-gradient(circle at 10% 20%, #e3f2fd 0, transparent 25%),
                        radial-gradient(circle at 90% 0%, #ede9fe 0, transparent 22%),
                        #f8fafc;
        }
        .glass-card {
            backdrop-filter: blur(8px);
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(226,232,240,0.9);
            box-shadow: 0 14px 38px rgba(15,23,42,0.08);
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.18);
            border-color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
            <div class="mb-2">
                <h1 class="h4 mb-1"><i class="fas fa-user-circle text-primary me-2"></i>Profil</h1>
                <p class="text-muted mb-0">Kelola password akun Anda.</p>
            </div>
            <div class="btn-group">
                <a href="{{ route('devices.manage') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-microchip me-1"></i> Device Management</a>
                @if(($user->role ?? '') === 'admin')
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-users me-1"></i> Users</a>
                @endif
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i> Logout</button>
                </form>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card glass-card">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0"><i class="fas fa-key me-2 text-primary"></i>Ubah Password</h5>
                    </div>
                    <div class="card-body">
                        @if(session('status'))
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-1"></i>{{ session('status') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('profile.update') }}" class="needs-validation" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label text-muted small">Username</label>
                                <input type="text" value="{{ $user->name }}" disabled class="form-control bg-light">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Email</label>
                                <input type="text" value="{{ $user->email }}" disabled class="form-control bg-light">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Role</label>
                                <input type="text" value="{{ $user->role }}" disabled class="form-control bg-light text-capitalize">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nomor WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted"><i class="fas fa-phone"></i></span>
                                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" placeholder="62812xxxxxxx">
                                </div>
                                @error('phone')
                                    <small class="text-danger d-block mt-1">{{ $message }}</small>
                                @enderror
                                <small class="text-muted">Nomor ini dipakai untuk reset password via WhatsApp.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password Lama</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="current_password" class="form-control" placeholder="Masukkan password lama jika ingin ganti">
                                </div>
                                @error('current_password')
                                    <small class="text-danger d-block mt-1">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password Baru</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white text-muted"><i class="fas fa-shield-alt"></i></span>
                                        <input type="password" name="password" class="form-control" placeholder="Password baru (opsional)">
                                    </div>
                                    @error('password')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Konfirmasi Password Baru</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white text-muted"><i class="fas fa-check"></i></span>
                                        <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi password baru">
                                    </div>
                                </div>
                            </div>

                            <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (() => {
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach((form) => {
        form.addEventListener('submit', (event) => {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    })();
    </script>
    <footer class="text-center text-muted small py-4">Wa-gateway Panel Develop with ❤️ by Hardi Agunadi – Pranata Komputer Kec. Watumalang</footer>
</body>
</html>
