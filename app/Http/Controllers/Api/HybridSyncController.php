<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HybridDevice;
use App\Services\HybridAttendanceRosterService;
use App\Services\HybridAttendanceScanService;
use App\Services\HybridCatalogIngestService;
use App\Services\HybridOpacSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HybridSyncController extends Controller
{
    public function health(Request $request)
    {
        /** @var HybridDevice $device */
        $device = $request->attributes->get('hybrid_device');

        return response()->json([
            'status' => 'ok',
            'server_time' => now()->toIso8601String(),
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'allow_attendance' => $device->allow_attendance,
                'allow_opac' => $device->allow_opac,
                'allow_catalog' => $device->allow_catalog,
                'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            ],
        ]);
    }

    public function attendanceRoster(Request $request, HybridAttendanceRosterService $roster)
    {
        $since = $this->parseSince($request);
        if ($since === false) {
            return response()->json(['message' => 'Invalid since timestamp.'], 422);
        }

        return response()->json($roster->build($since));
    }

    public function pushAttendance(
        Request $request,
        HybridAttendanceScanService $scanService
    ) {
        /** @var HybridDevice $device */
        $device = $request->attributes->get('hybrid_device');

        $validated = $request->validate([
            'scans' => 'required|array|min:1|max:500',
            'scans.*.client_uuid' => 'required|uuid|distinct',
            'scans.*.scan_token' => 'required|string|max:500',
            'scans.*.status' => 'nullable|in:IN,OUT,in,out',
            'scans.*.scanned_at' => 'required|date',
        ]);

        $results = [];

        foreach ($validated['scans'] as $row) {
            $clientUuid = $row['client_uuid'];
            $patron = $scanService->resolvePatron($row['scan_token']);

            if (! $patron) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'accepted' => false,
                    'error' => 'Patron not found for scan token.',
                ];

                continue;
            }

            try {
                $scannedAt = Carbon::parse($row['scanned_at'])->timezone(config('app.timezone'));
                $model = $patron['type'] === 'student' ? $patron['student'] : $patron['employee'];
                $log = $scanService->recordSyncedScan(
                    $model,
                    strtoupper((string) ($row['status'] ?? 'IN')),
                    $scannedAt,
                    $clientUuid,
                    $device,
                );

                $entry = [
                    'client_uuid' => $clientUuid,
                    'accepted' => true,
                    'attendance_log_id' => $log->id,
                    'status' => strtoupper((string) $log->status),
                    'scanned_at' => $log->scanned_at
                        ? Carbon::parse($log->scanned_at)->toIso8601String()
                        : null,
                    'patron_type' => $patron['type'],
                ];
                if ($patron['type'] === 'student') {
                    $entry['student_id'] = $model->id;
                } else {
                    $entry['employee_id'] = $model->id;
                }
                $results[] = $entry;
            } catch (\Throwable $e) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'accepted' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $device->touchSynced();

        $accepted = collect($results)->where('accepted', true)->count();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'accepted' => $accepted,
            'rejected' => count($results) - $accepted,
            'results' => $results,
        ]);
    }

    public function opacSnapshot(Request $request, HybridOpacSnapshotService $snapshot)
    {
        $since = $this->parseSince($request);
        if ($since === false) {
            return response()->json(['message' => 'Invalid since timestamp.'], 422);
        }

        /** @var HybridDevice $device */
        $device = $request->attributes->get('hybrid_device');
        $device->touchSynced();

        return response()->json($snapshot->build($since));
    }

    public function pushCatalog(Request $request, HybridCatalogIngestService $ingest)
    {
        /** @var HybridDevice $device */
        $device = $request->attributes->get('hybrid_device');

        $validated = $request->validate([
            'records' => 'required|array|min:1|max:50',
            'records.*.client_uuid' => 'required|uuid|distinct',
            'records.*.payload' => 'required|array',
        ]);

        $results = [];

        foreach ($validated['records'] as $row) {
            $clientUuid = $row['client_uuid'];
            try {
                $accepted = $ingest->ingest($clientUuid, $row['payload'], $device);
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'accepted' => true,
                    'book_id' => $accepted['book_id'],
                    'copies_created' => $accepted['copies_created'],
                    'book_ids' => $accepted['book_ids'],
                ];
            } catch (ValidationException $e) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'accepted' => false,
                    'error' => collect($e->errors())->flatten()->first() ?: 'Validation failed.',
                    'errors' => $e->errors(),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'accepted' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $device->touchSynced();
        $accepted = collect($results)->where('accepted', true)->count();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'accepted' => $accepted,
            'rejected' => count($results) - $accepted,
            'results' => $results,
        ]);
    }

    /**
     * Session-authenticated catalog push for staff browsers without a device token.
     */
    public function pushCatalogAuthenticated(Request $request, HybridCatalogIngestService $ingest)
    {
        $validated = $request->validate([
            'records' => 'required|array|min:1|max:50',
            'records.*.client_uuid' => 'required|uuid|distinct',
            'records.*.payload' => 'required|array',
        ]);

        $results = [];
        foreach ($validated['records'] as $row) {
            $clientUuid = $row['client_uuid'];
            try {
                $accepted = $ingest->ingest(
                    $clientUuid,
                    $row['payload'],
                    null,
                    $request->user()?->id
                );
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'accepted' => true,
                    'book_id' => $accepted['book_id'],
                    'copies_created' => $accepted['copies_created'],
                    'book_ids' => $accepted['book_ids'],
                ];
            } catch (ValidationException $e) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'accepted' => false,
                    'error' => collect($e->errors())->flatten()->first() ?: 'Validation failed.',
                    'errors' => $e->errors(),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'client_uuid' => $clientUuid,
                    'accepted' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $accepted = collect($results)->where('accepted', true)->count();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'accepted' => $accepted,
            'rejected' => count($results) - $accepted,
            'results' => $results,
        ]);
    }

    /**
     * Staff can pull OPAC/roster snapshots while logged in (no device token).
     */
    public function opacSnapshotAuthenticated(Request $request, HybridOpacSnapshotService $snapshot)
    {
        $since = $this->parseSince($request);
        if ($since === false) {
            return response()->json(['message' => 'Invalid since timestamp.'], 422);
        }

        return response()->json($snapshot->build($since));
    }

    public function attendanceRosterAuthenticated(Request $request, HybridAttendanceRosterService $roster)
    {
        $since = $this->parseSince($request);
        if ($since === false) {
            return response()->json(['message' => 'Invalid since timestamp.'], 422);
        }

        return response()->json($roster->build($since));
    }

    /** @return Carbon|null|false */
    protected function parseSince(Request $request)
    {
        if (! $request->filled('since')) {
            return null;
        }

        try {
            return Carbon::parse($request->query('since'));
        } catch (\Throwable) {
            return false;
        }
    }
}
