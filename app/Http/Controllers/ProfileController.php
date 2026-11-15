<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'user' => request()->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active && $user->auth_version === $request->user()->auth_version, 403);
            $user->fill($request->safe()->only(['name', 'email']));
            $user->lock_version++;
            $user->save();
        }, 3);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Los datos de tu perfil se actualizaron correctamente.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $version = DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active && $user->auth_version === $request->user()->auth_version, 403);
            $user->forceFill([
                'password' => Hash::make($request->validated('password')),
                'auth_version' => $user->auth_version + 1,
                'remember_token' => null,
                'lock_version' => $user->lock_version + 1,
            ])->save();

            return $user->auth_version;
        }, 3);
        $request->session()->regenerate();
        $request->session()->put('auth_version', $version);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Tu contraseña se actualizó correctamente.');
    }
}
