<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - WA Gateway Panel</title>
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
                <h1 class="h4 mb-1"><i class="fas fa-user-shield text-primary me-2"></i>Manajemen User</h1>
                <p class="text-muted mb-0">Buat akun baru untuk login ke panel.</p>
            </div>
            <div class="btn-group">
                <a href="{{ route('devices.manage') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-microchip me-1"></i> Device Management</a>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Dashboard</a>
                <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-user me-1"></i> Profil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i> Logout</button>
                </form>
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-1"></i>{{ session('status') }}
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

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card glass-card">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0"><i class="fas fa-user-plus text-primary me-2"></i>Tambah User</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('users.store') }}" class="needs-validation" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label text-muted small">Nama</label>
                                <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                                <div class="invalid-feedback">Nama wajib diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                                <div class="invalid-feedback">Email wajib diisi.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Nomor WhatsApp</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="62812xxxxxxx">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>User</option>
                                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Konfirmasi Password</label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>
                            <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Simpan User</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card glass-card">
                    <div class="card-header bg-white border-0">
                        <h5 class="card-title mb-0"><i class="fas fa-users text-primary me-2"></i>Daftar User</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>WA</th>
                                        <th>Role</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($users as $user)
                                        <tr>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->email }}</td>
                                            <td>{{ $user->phone ?? '-' }}</td>
                                            <td class="text-capitalize">{{ $user->role }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">Belum ada user.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
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
