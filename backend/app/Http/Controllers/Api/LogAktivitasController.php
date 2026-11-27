<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LogAktivitasResource;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;

class LogAktivitasController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'modul'     => ['nullable', 'string', 'max:50'],
            'aksi'      => ['nullable', 'string', 'max:50'],
            'user_id'   => ['nullable', 'integer'],
            'data_id'   => ['nullable', 'integer'],
            'dari'      => ['nullable', 'date'],
            'sampai'    => ['nullable', 'date', 'after_or_equal:dari'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $log = LogAktivitas::with(['user' => fn ($q) => $q->withTrashed()])
            ->when($request->modul, fn ($q, $m) => $q->where('modul', $m))
            ->when($request->aksi, fn ($q, $a) => $q->where('aksi', $a))
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->data_id, fn ($q, $id) => $q->where('data_id', $id))
            ->when($request->dari, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->sampai, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return LogAktivitasResource::collection($log);
    }

    public function show(LogAktivitas $logAktivitas)
    {
        return new LogAktivitasResource(
            $logAktivitas->load(['user' => fn ($q) => $q->withTrashed])
        );
    }
}
