<?php

use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('allows a user when their role is in the allowed roles', function () {
    $user = new User(['role' => 'admin']);
    $request = Request::create('/api/users');
    $request->setUserResolver(fn () => $user);

    $response = (new CheckRole())->handle(
        $request,
        fn () => new Response('', 204),
        'peminjam',
        'admin',
    );

    expect($response->getStatusCode())->toBe(204);
});

test('rejects an authenticated user with a role outside the allowed roles', function () {
    $user = new User(['role' => 'peminjam']);
    $request = Request::create('/api/users');
    $request->setUserResolver(fn () => $user);

    try {
        (new CheckRole())->handle(
            $request,
            fn () => new Response('', 204),
            'admin',
        );
    } catch (HttpException $exception) {
        $this->assertSame(403, $exception->getStatusCode());

        return;
    }

    $this->fail('Expected an HTTP 403 response.');
});

test('rejects a request without an authenticated user', function () {
    $request = Request::create('/api/users');
    $request->setUserResolver(fn () => null);

    try {
        (new CheckRole())->handle(
            $request,
            fn () => new Response('', 204),
            'admin',
        );
    } catch (HttpException $exception) {
        $this->assertSame(403, $exception->getStatusCode());

        return;
    }

    $this->fail('Expected an HTTP 403 response.');
});
