<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyHmacSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Hub-Signature-256');
        $secret = config('services.gowa.webhook_secret');

        if (!$secret) {
            abort(500, 'GOWA webhook secret is not configured.');
        }

        $expected = 'sha256=' . hash_hmac(
            'sha256',
            $request->getContent(),
            $secret
        );

        if (!$signature || !hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook HMAC signature.');
        }

        return $next($request);
    }
}
