<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const FIELD_LOG = ['nama', 'username', 'email', 'role', 'no_telp', 'alamat', 'is_active'];

    // Admin
    public function index(Request $request)
    {
        $request->validate([
            'q'         => ['nullable', 'string', 'max:100'],
            'role'      => ['nullable', Rule::in(['admin', 'petugas', 'peminjam'])],
            'is_active' => ['nullable', 'boolean'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->when($request->q, fn ($q, $kw) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$kw}%")
                ->orWhere('username', 'like', "%{$kw}%")
                ->orWhere('email', 'like', "%{$kw}%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->filled('is_active'),
                fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('nama')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    // Admin
    public function show(User $user)
    {
        return new UserResource($user);
    }

    // Admin
    public function store(StoreUserRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = new User($request->validated());
            $user->email_verified_at = now();     // akun buatan admin dianggap terverifikasi
            $user->save();

            LogAktivitas::catat('create', 'users', $user->id, null, $user->only(self::FIELD_LOG));

            return $user;
        });

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    // Admin
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        // 1. lindungi akun sendiri supaya admin tidak terkunci dari sistem
        if ($user->is($request->user())) {
            if (isset($data['role']) && $data['role'] !== $user->role) {
                throw ValidationException::withMessages(['role' => 'Anda tidak bisa mengubah role akun sendiri.']);
            }
            if (array_key_exists('is_active', $data) && ! $request->boolean('is_active')) {
                throw ValidationException::withMessages(['is_active' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
            }
        }

        // 2. peminjam dengan peminjaman berjalan tidak boleh diganti role-nya
        if (isset($data['role']) && $data['role'] !== $user->role
            && $user->role === 'peminjam' && $this->punyaPeminjamanBerjalan($user)) {
            throw ValidationException::withMessages([
                'role' => 'Role tidak bisa diubah selama user masih punya peminjaman yang diajukan atau berjalan.',
            ]);
        }

        // 3. password hanya diubah kalau diisi
        $passwordDiubah = filled($data['password'] ?? null);
        if (! $passwordDiubah) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $passwordDiubah) {
            $lama = $user->only(self::FIELD_LOG);

            $user->update($data);

            $baru = $user->only(self::FIELD_LOG);
            if ($passwordDiubah) {
                $baru['password_diubah'] = true;      // dicatat sebagai penanda, bukan isinya
                DB::table('sessions')->where('user_id', $user->id)->delete();   // paksa login ulang (SESSION_DRIVER=database)
            }

            LogAktivitas::catat('update', 'users', $user->id, $lama, $baru);
        });

        return new UserResource($user);
    }

    // Admin
    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'Anda tidak bisa menghapus akun sendiri.']);
        }

        if ($this->punyaPeminjamanBerjalan($user)) {
            throw ValidationException::withMessages([
                'user' => 'User masih memiliki peminjaman yang diajukan atau sedang berjalan.',
            ]);
        }

        DB::transaction(function () use ($user) {
            $lama = $user->only(self::FIELD_LOG);

            $user->delete();

            LogAktivitas::catat('delete', 'users', $user->id, $lama);
        });

        return response()->noContent();
    }

    private function punyaPeminjamanBerjalan(User $user): bool
    {
        return $user->peminjaman()->whereIn('status', ['diajukan', 'dipinjam'])->exists();
    }
}