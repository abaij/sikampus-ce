<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerApiKey
{
    /**
     * Handle an incoming request.
     * Memvalidasi API key dari header (untuk akses aplikasi partner seperti Siska).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $validKeys = config('partner_api.api_keys', []);
        $headerName = config('partner_api.header_name', 'X-API-Key');

        if (empty($validKeys)) {
            return response()->json([
                'message' => 'Partner API tidak dikonfigurasi.',
            ], 503);
        }

        $apiKey = $request->header($headerName);

        if (empty($apiKey) || ! in_array($apiKey, $validKeys, true)) {
            return response()->json([
                'message' => 'API key tidak valid atau tidak diberikan.',
            ], 401);
        }

        return $next($request);
    }
}
