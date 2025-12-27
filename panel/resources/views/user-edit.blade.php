<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - WA Gateway Panel</title>
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
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
            <div class="mb-2">
                <h1 class="h4 mb-1"><i class="fas fa-user-edit text-primary me-2"></i>Edit User</h1>
                <p class="text-muted mb-0">Kelola profil user lain.</p>
            </div>
            <div class="btn-group">
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-users me-1"></i> Users</a>
                <a href="{{ route('devices.manage') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-microchip me-1"></i> Device Management</a>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i> Logout</button>
                </form>
            </div>
        </div>

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

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card glass-card">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0"><i class="fas fa-user-cog text-primary me-2"></i>Profil User</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('users.update', $user) }}" class="needs-validation" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label text-muted small">Nama</label>
                                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Email</label>
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Nomor WhatsApp</label>
                                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" placeholder="62812xxxxxxx">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="user" {{ old('role', $user->role) === 'user' ? 'selected' : '' }}>User</option>
                                    <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Password Baru (opsional)</label>
                                <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Konfirmasi Password Baru</label>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi password baru">
                            </div>
                            <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Simpan Perubahan</button>
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
</body>
</html>
