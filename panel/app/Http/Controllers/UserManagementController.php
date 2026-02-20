<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $users = User::orderBy('name')->get();

        return view('users', [
            'users' => $users,
            'currentUser' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string'],
            'role' => ['required', 'in:admin,user'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $phone = $this->normalizePhone($data['phone'] ?? null);

        User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => $phone,
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        return redirect()
            ->route('users.index')
            ->with('status', 'User baru berhasil ditambahkan.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureAdmin($request);

        return view('user-edit', [
            'user' => $user,
            'currentUser' => $request->user(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string'],
            'role' => ['required', 'in:admin,user'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $updates = [
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => $this->normalizePhone($data['phone'] ?? null),
            'role' => $data['role'],
        ];

        if (!empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        $user->update($updates);

        return redirect()
            ->route('users.index')
            ->with('status', 'Profil user berhasil diperbarui.');
    }

    private function ensureAdmin(Request $request): void
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Hanya admin yang boleh mengakses halaman ini.');
        }
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
