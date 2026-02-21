<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';
    protected static ?string $title = 'Profil Saya';
    protected string $view = 'filament.pages.profile';
    protected static bool $shouldRegisterNavigation = false;

    public ?array $profileData = [];
    public ?array $passwordData = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->profileForm->fill([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);

        $this->passwordForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'profileForm',
            'passwordForm',
        ];
    }

    public function profileForm(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Informasi Profil')->schema([
                    TextInput::make('name')
                        ->label('Nama')
                        ->disabled(),
                    TextInput::make('email')
                        ->label('Email')
                        ->disabled(),
                    TextInput::make('phone')
                        ->label('Nomor Telepon')
                        ->maxLength(20),
                ]),
            ])
            ->statePath('profileData');
    }

    public function passwordForm(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Ubah Password')->schema([
                    TextInput::make('current_password')
                        ->label('Password Lama')
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('password')
                        ->label('Password Baru')
                        ->password()
                        ->revealable()
                        ->minLength(6)
                        ->required()
                        ->confirmed(),
                    TextInput::make('password_confirmation')
                        ->label('Konfirmasi Password Baru')
                        ->password()
                        ->revealable()
                        ->required(),
                ]),
            ])
            ->statePath('passwordData');
    }

    public function saveProfile(): void
    {
        $data = $this->profileForm->getState();
        $user = auth()->user();

        $phone = isset($data['phone']) ? preg_replace('/\D+/', '', $data['phone']) : null;
        $phone = $phone !== '' ? $phone : null;

        // Konversi format lokal ke internasional: 08xxx → 628xxx
        if ($phone && str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        $user->update(['phone' => $phone]);

        Notification::make()
            ->success()
            ->title('Profil berhasil diperbarui.')
            ->send();
    }

    public function savePassword(): void
    {
        $data = $this->passwordForm->getState();
        $user = auth()->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            Notification::make()
                ->danger()
                ->title('Password lama tidak sesuai.')
                ->send();
            return;
        }

        $user->update(['password' => Hash::make($data['password'])]);

        $this->passwordForm->fill();

        Notification::make()
            ->success()
            ->title('Password berhasil diubah.')
            ->send();
    }
}
