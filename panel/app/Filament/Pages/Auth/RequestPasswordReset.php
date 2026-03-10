<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\GatewayService;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('username')
                    ->label('Nama Pengguna atau Email')
                    ->required()
                    ->autocomplete('username')
                    ->autofocus(),
                TextInput::make('phone')
                    ->label('Nomor Telepon')
                    ->required()
                    ->helperText('Masukkan nomor telepon yang terdaftar di akun Anda.'),
            ]);
    }

    public function request(): void
    {
        $data = $this->form->getState();

        $throttleKey = 'reset-pass:' . sha1(strtolower($data['username']) . '|' . request()->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            Notification::make()
                ->danger()
                ->title('Terlalu banyak percobaan. Coba lagi beberapa menit.')
                ->send();
            return;
        }
        RateLimiter::hit($throttleKey, 300);

        $user = User::where('email', $data['username'])
            ->orWhere('name', $data['username'])
            ->first();

        $publicMessage = 'Jika data cocok, password baru akan dikirim lewat WhatsApp.';

        if (! $user) {
            Notification::make()->success()->title($publicMessage)->send();
            return;
        }

        $storedPhone = $this->normalizePhone($user->phone);
        $inputPhone = $this->normalizePhone($data['phone']);

        if (! $storedPhone || $storedPhone !== $inputPhone) {
            Notification::make()->success()->title($publicMessage)->send();
            return;
        }

        $sessionId = $this->resolvePasswordResetSession();
        if (! $sessionId) {
            Notification::make()
                ->danger()
                ->title('Tidak ada device yang tersedia untuk mengirim reset password.')
                ->send();
            return;
        }

        $newPassword = Str::random(10);
        $oldHash = $user->password;
        $user->update(['password' => Hash::make($newPassword)]);

        try {
            $message = "Reset password WA Gateway.\nUser: {$user->name}\nPassword baru: {$newPassword}\nSegera login dan ganti password.";
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            $gateway->sendText($sessionId, $storedPhone, $message);
        } catch (\Throwable $e) {
            $user->update(['password' => $oldHash]);
            Notification::make()
                ->danger()
                ->title('Gagal mengirim reset password via WhatsApp: ' . $e->getMessage())
                ->send();
            return;
        }

        RateLimiter::clear($throttleKey);

        Notification::make()
            ->success()
            ->title('Password baru sudah dikirim ke WhatsApp Anda.')
            ->send();
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
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        return $digits !== '' ? $digits : null;
    }
}
