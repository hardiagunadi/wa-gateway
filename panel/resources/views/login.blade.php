<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - WA Gateway Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body.login-page {
            background: radial-gradient(circle at 20% 20%, #0ea5e9 0, rgba(14,165,233,0) 25%),
                        radial-gradient(circle at 80% 0%, #22c55e 0, rgba(34,197,94,0) 25%),
                        #0f172a;
            color: #e2e8f0;
        }
        .brand-text { font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
        .login-card-body { backdrop-filter: blur(6px); }
    </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        <span class="brand-text text-teal">WA Gateway</span>
    </div>
    <div class="card shadow">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Control Panel</p>
            <p class="text-muted text-center mb-3">Masuk untuk mengelola sesi dan server.</p>

            @if(session('status'))
                <div class="alert alert-success py-2 px-3 text-sm">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="input-group mb-3">
                    <input type="text" name="username" value="{{ old('username') }}" class="form-control" placeholder="Username" required autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                @error('username')
                    <p class="text-danger text-xs mb-2">{{ $message }}</p>
                @enderror
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                @error('password')
                    <p class="text-danger text-xs mb-2">{{ $message }}</p>
                @enderror
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">Masuk</button>
                    </div>
                </div>
            </form>

            <p class="mt-3 text-center text-muted text-sm">Default: admin / admin. Segera ganti password di halaman Profil.</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/plugins/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
