<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - WA Gateway Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.13/dist/tailwind.min.css">
</head>
<body class="min-h-screen bg-slate-100">
    <div class="max-w-4xl mx-auto px-4 py-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Profil</h1>
            <p class="text-sm text-slate-500">Kelola password akun Anda.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded-lg bg-slate-200 text-slate-800 hover:bg-slate-300 text-sm">Kembali ke Dashboard</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="px-3 py-2 rounded-lg bg-rose-600 text-white hover:bg-rose-700 text-sm">Logout</button>
            </form>
        </div>
    </div>

    <div class="max-w-2xl mx-auto bg-white shadow rounded-2xl p-6 border border-slate-200">
        @if(session('status'))
            <div class="mb-4 px-3 py-2 rounded-lg bg-emerald-50 text-emerald-700 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs text-slate-500 mb-1">Username</label>
                <input type="text" value="{{ $user->name }}" disabled class="w-full rounded-lg border border-slate-200 px-3 py-2 bg-slate-50 text-slate-600">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Email</label>
                <input type="text" value="{{ $user->email }}" disabled class="w-full rounded-lg border border-slate-200 px-3 py-2 bg-slate-50 text-slate-600">
            </div>

            <div>
                <label class="block text-xs text-slate-500 mb-1">Password Lama</label>
                <input type="password" name="current_password" required class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                @error('current_password')
                    <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Password Baru</label>
                    <input type="password" name="password" required class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    @error('password')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password Baru</label>
                    <input type="password" name="password_confirmation" required class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition">
                Simpan Perubahan
            </button>
        </form>
    </div>
</body>
</html>
