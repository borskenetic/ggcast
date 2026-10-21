<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReceiptPrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptPrintJobController extends Controller
{
    public function next(Request $request): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json(['message' => 'Invalid print token'], 403);
        }

        $job = ReceiptPrintJob::query()
            ->whereNull('printed_at')
            ->where(function ($query) {
                $query->whereNull('claimed_at')
                    ->orWhere('claimed_at', '<', now()->subMinutes(2));
            })
            ->orderBy('id')
            ->first();

        if (! $job) {
            return response()->json(['job' => null]);
        }

        $job->update(['claimed_at' => now()]);

        return response()->json([
            'job' => [
                'id' => $job->id,
                'student' => $job->payload['student'] ?? [],
                'books' => $job->payload['books'] ?? [],
            ],
        ]);
    }

    public function printed(Request $request, ReceiptPrintJob $job): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json(['message' => 'Invalid print token'], 403);
        }

        $job->update(['printed_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function release(Request $request, ReceiptPrintJob $job): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json(['message' => 'Invalid print token'], 403);
        }

        if (! $job->printed_at) {
            $job->update(['claimed_at' => null]);
        }

        return response()->json(['ok' => true]);
    }

    private function tokenIsValid(Request $request): bool
    {
        $expected = (string) config('printing.agent_token', '');
        if ($expected === '') {
            return false;
        }

        $given = (string) $request->header('X-Receipt-Token', $request->query('token', ''));

        return hash_equals($expected, $given);
    }
}
