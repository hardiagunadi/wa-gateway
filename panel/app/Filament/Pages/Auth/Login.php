<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Nama Pengguna atau Email')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email' => $data['email'],
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        $username = $data['email'] ?? '';

        $user = User::where('email', $username)
            ->orWhere('name', $username)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'data.email' => 'Kredensial salah.',
            ]);
        }

        auth()->login($user, $data['remember'] ?? false);
        session()->regenerate();

        // Kirim notifikasi login ke semua admin
        $admins = User::where('role', 'admin')->where('id', '!=', $user->id)->get();
        if ($admins->isNotEmpty()) {
            Notification::make()
                ->title('Login Berhasil')
                ->body("{$user->name} berhasil login pada " . now()->format('d M Y H:i') . '.')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('info')
                ->sendToDatabase($admins);
        }

        return app(LoginResponse::class);
    }
}
