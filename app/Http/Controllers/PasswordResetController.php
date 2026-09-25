<?php

namespace App\Http\Controllers;

use App\Actions\Identity\RevokeUserAccess;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function create()
    {
        return view('auth.passwords.email');
    }

    public function store(ForgotPasswordRequest $request)
    {
        $email = $request->string('email')->toString();
        $activeUserExists = User::query()->where('email', $email)->where('is_active', true)->exists();

        if ($activeUserExists) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', 'Se houver uma conta ativa com este e-mail, você receberá as instruções para redefinir a senha.');
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.passwords.reset', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(ResetPasswordRequest $request, RevokeUserAccess $revokeAccess)
    {
        $data = $request->validated();
        $resetUserId = null;
        $status = Password::reset(
            [
                'email' => $data['email'],
                'is_active' => true,
                'password' => $data['password'],
                'token' => $data['token'],
            ],
            function (User $user, string $password) use (&$resetUserId, $revokeAccess): void {
                DB::transaction(function () use ($user, $password, &$resetUserId, $revokeAccess) {
                    $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                    if (! $lockedUser->isActive()) {
                        throw ValidationException::withMessages([
                            'email' => 'A conta não está disponível para redefinição de senha.',
                        ]);
                    }
                    $lockedUser->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();
                    $revokeAccess->execute($lockedUser);
                    $resetUserId = $lockedUser->id;
                }, 3);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            if ($request->user()?->id === $resetUserId) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->with('status', 'Senha redefinida. Faça login para continuar.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
