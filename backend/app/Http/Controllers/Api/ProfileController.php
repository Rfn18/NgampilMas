<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    private const FIELD_PROFIL = ['nama', 'no_telp', 'alamat'];

    // Semua role: ubah data diri sendiri
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $lama = $user->only(self::FIELD_PROFIL);

            $user->update($request->validated());

            LogAktivitas::catat('update', 'users', $user->id, $lama, $user->only(self::FIELD_PROFIL));
        });

        return new UserResource($user);
    }

    // Semua role: ganti password sendiri
    public function password(ChangePasswordRequest $request)
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $user->forceFill([
                'password'       => $request->validated('password'),   // di-hash oleh cast 'hashed'
                'remember_token' => Str::random(60),
            ])->save();

            LogAktivitas::catat('ganti_password', 'users', $user->id);
        });

        // keluarkan perangkat lain, sesi yang sedang dipakai tetap hidup
        if ($request->hasSession()) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return response()->json(['message' => 'Password berhasil diubah.']);
    }
}