<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next): Response
    {
       $user = $request->user();

       if (!$user) {
            return redirect()->route('login');
       }

       $hasAccess = match ($role) {
            'admin' => $user->isAdmin(),
            'petugas' => $user->isPetugas(),
            'peminjam' => $user->isPeminjam(),
            default => false
       };

       if (!$hasAccess) {
            abort(403, 'Anda tidak punya akses ke halaman ini.');
       };

       return $next($request);
    }
}
