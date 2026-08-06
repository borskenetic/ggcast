<?php

namespace App\Http\Middleware;

use App\Models\HybridDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateHybridDevice
{
    public function handle(Request $request, Closure $next, ?string $capability = null): Response
    {
        $device = HybridDevice::resolveFromRequest($request);

        if (! $device) {
            return response()->json(['message' => 'Hybrid device token required.'], 401);
        }

        if ($capability && ! $device->allows($capability)) {
            return response()->json([
                'message' => "This device is not allowed to use the {$capability} hybrid channel.",
            ], 403);
        }

        $request->attributes->set('hybrid_device', $device);

        return $next($request);
    }
}
