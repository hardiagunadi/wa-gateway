<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - WA Gateway Panel</title>
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
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.15);
            border-color: #4f46e5;
        }
    </style>
</head>
<body>
<div class="container d-flex align-items-center justify-content-center py-5">
    <div class="w-100" style="max-width: 460px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-2" style="width:58px;height:58px;">
                <i class="fas fa-key fa-lg"></i>
            </div>
            <h1 class="h5 fw-bold mb-1">Reset Password</h1>
            <p class="text-muted mb-0">Password baru akan dikirim lewat WhatsApp.</p>
        </div>
        <div class="card glass-card">
            <div class="card-body p-4">
                @if(session('status'))
                    <div class="alert alert-success py-2 px-3 text-sm mb-3">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('password.reset') }}" class="needs-validation" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small text-muted">Username / Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" value="{{ old('username') }}" class="form-control" placeholder="Username atau email" required autofocus>
                            <div class="invalid-feedback">Username wajib diisi.</div>
                        </div>
                        @error('username')
                            <p class="text-danger small mt-1 mb-0">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Nomor WhatsApp</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted"><i class="fas fa-phone"></i></span>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="62812xxxxxxx" required>
                            <div class="invalid-feedback">Nomor WA wajib diisi.</div>
                        </div>
                        @error('phone')
                            <p class="text-danger small mt-1 mb-0">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Kirim Password Baru</button>
                </form>

                <div class="text-center mt-3">
                    <a class="small text-decoration-none" href="{{ route('login') }}">Kembali ke login</a>
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
