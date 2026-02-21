<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['phone'])) {
            $data['phone'] = preg_replace('/\D+/', '', $data['phone']) ?: null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $newUser = $this->record;
        $admins = User::where('role', 'admin')->where('id', '!=', auth()->id())->get();

        if ($admins->isNotEmpty()) {
            Notification::make()
                ->title('User Baru Dibuat')
                ->body("User {$newUser->name} ({$newUser->email}) dibuat oleh " . auth()->user()->name . '.')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->sendToDatabase($admins);
        }
    }
}
