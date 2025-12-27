<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WA Gateway Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at 20% 20%, #dbeafe 0, transparent 25%),
                        radial-gradient(circle at 80% 0%, #e9d5ff 0, transparent 22%),
                        #f8fafc;
        }
        .glass-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
        }
        .brand-text { font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #0f172a; }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.15);
            border-color: #4f46e5;
        }
    </style>
</head>
<body>
<div class="container d-flex align-items-center justify-content-center py-5">
    <div class="w-100" style="max-width: 420px;">
        <div class="text-center mb-4">
              <div class="brand-text d-block">WA Gateway</div>
            <p class="text-muted mb-0">Control Panel</p>
        </div>
        <div class="card glass-card">
            <div class="card-body p-4">
                <p class="text-muted text-center mb-3">Masuk untuk mengelola sesi dan server.</p>

                @if(session('status'))
                    <div class="alert alert-success py-2 px-3 text-sm mb-3">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}" class="needs-validation" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small text-muted">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" value="{{ old('username') }}" class="form-control" placeholder="Username" required autofocus>
                            <div class="invalid-feedback">Username wajib diisi.</div>
                        </div>
                        @error('username')
                            <p class="text-danger small mt-1 mb-0">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                            <div class="invalid-feedback">Password wajib diisi.</div>
                        </div>
                        @error('password')
                            <p class="text-danger small mt-1 mb-0">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Masuk</button>
                </form>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <a class="small text-decoration-none" href="{{ route('password.request') }}">Lupa password?</a>
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
