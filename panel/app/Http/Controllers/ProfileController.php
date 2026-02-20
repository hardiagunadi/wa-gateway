<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        return view('profile', [
            'user' => Auth::user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();
        $updates = [];

        $updates['phone'] = $this->normalizePhone($data['phone'] ?? null);

        if ($request->filled('password')) {
            if (!$request->filled('current_password')) {
                return back()->withErrors(['current_password' => 'Password lama wajib diisi.']);
            }

            if (!Hash::check((string) $data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Password lama tidak sesuai.']);
            }

            $updates['password'] = Hash::make((string) $data['password']);
        }

        $user->update($updates);

        return back()->with('status', 'Profil berhasil diperbarui.');
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
