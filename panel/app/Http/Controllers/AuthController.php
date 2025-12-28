<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['username'])
            ->orWhere('name', $data['username'])
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return back()
                ->withErrors(['username' => 'Kredensial salah.'])
                ->withInput(['username' => $data['username']]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Login berhasil.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah logout.');
    }

    public function showForgotPassword(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('forgot-password');
    }

    public function sendResetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'phone' => ['required', 'string'],
        ]);

        $throttleKey = 'reset-pass:' . sha1(strtolower($data['username']) . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return back()
                ->withErrors(['username' => 'Terlalu banyak percobaan. Coba lagi beberapa menit.'])
                ->withInput();
        }
        RateLimiter::hit($throttleKey, 300);

        $user = User::where('email', $data['username'])
            ->orWhere('name', $data['username'])
            ->first();

        $publicMessage = 'Jika data cocok, password baru akan dikirim lewat WhatsApp.';

        if (!$user) {
            return back()->with('status', $publicMessage);
        }

        $storedPhone = $this->normalizePhone($user->phone);
        $inputPhone = $this->normalizePhone($data['phone']);

        if (!$storedPhone) {
            return back()->with('status', $publicMessage);
        }

        if ($storedPhone !== $inputPhone) {
            return back()->with('status', $publicMessage);
        }

        $sessionId = $this->resolvePasswordResetSession();
        if (!$sessionId) {
            return back()
                ->withErrors(['username' => 'Tidak ada device yang tersedia untuk mengirim reset password.'])
                ->withInput();
        }

        $newPassword = Str::random(10);
        $oldHash = $user->password;
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        try {
            $message = "Reset password WA Gateway.\nUser: {$user->name}\nPassword baru: {$newPassword}\nSegera login dan ganti password.";
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            $gateway->sendText($sessionId, $storedPhone, $message);
        } catch (\Throwable $e) {
            $user->update(['password' => $oldHash]);
            return back()
                ->withErrors(['username' => 'Gagal mengirim reset password via WhatsApp: ' . $e->getMessage()])
                ->withInput();
        }

        RateLimiter::clear($throttleKey);

        return redirect()
            ->route('login')
            ->with('status', 'Password baru sudah dikirim ke WhatsApp Anda.');
    }

    private function resolvePasswordResetSession(): ?string
    {
        try {
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            $sessions = $gateway->listSessions();
        } catch (\Throwable) {
            return null;
        }

        $allowed = config('gateway.password_reset_sessions', []);
        if (is_array($allowed) && count($allowed) > 0) {
            $sessions = array_values(array_intersect($sessions, $allowed));
        }

        return $sessions[0] ?? null;
    }

    private function normalizePhone(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        return $digits !== '' ? $digits : null;
    }
}
