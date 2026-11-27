<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                ...$request->safe()->only(['nama', 'username', 'email', 'password', 'no_telp', 'alamat']),
                'role' => 'peminjam',          // dipaksa, tidak pernah dari request
            ]);

            LogAktivitas::catat('register', 'users', $user->id, null,
                $user->only(['username', 'email', 'role']), userId: $user->id);

            return $user;
        });

        // di luar transaksi: kalau SMTP gagal, akun tetap jadi dan bisa kirim ulang
        rescue(fn () => $user->sendEmailVerificationNotification());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('username', 'password') + ['is_active' => true];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            LogAktivitas::catat('login_gagal', 'users', null, null, ['username' => $request->username]);

            throw ValidationException::withMessages([
                'username' => 'Username atau password salah, atau akun tidak aktif.',
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user = $request->user();
        $user->update(['last_login_at' => now()]);

        LogAktivitas::catat('login', 'users', $user->id, userId: $user->id);

        return new UserResource($user);
    }

    public function logout(Request $request)
    {
        LogAktivitas::catat('logout', 'users', $request->user()->id);   // sebelum logout, supaya Auth::id() masih ada

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        rescue(fn () => Password::sendResetLink($request->only('email')));

        // jawaban selalu sama, supaya orang tidak bisa menebak email mana yang terdaftar
        return response()->json([
            'message' => 'Jika email terdaftar, tautan reset password telah dikirim.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])
                     ->setRememberToken(Str::random(60));
                $user->save();

                LogAktivitas::catat('reset_password', 'users', $user->id, userId: $user->id);
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Token reset tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        return response()->json(['message' => 'Password berhasil direset. Silakan login.']);
    }

    // Dipanggil frontend dengan query dari link email; middleware 'signed' menjaga keasliannya
    public function verifyEmail(int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Tautan verifikasi tidak valid.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
            LogAktivitas::catat('verify_email', 'users', $user->id, userId: $user->id);
        }

        return response()->json(['message' => 'Email berhasil diverifikasi.']);
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Tautan verifikasi telah dikirim ulang.']);
    }
}