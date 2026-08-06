@extends('layouts.sec')

@section('content')
<div class="container py-4" style="max-width: 960px;">
    <h1 class="h3 mb-2">Hybrid devices</h1>
    <p class="text-muted mb-4">
        Pair kiosks and staff machines for offline attendance, OPAC browse cache, and cataloging upload.
        Tokens are shown once — store them securely on each terminal.
    </p>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('plain_token'))
        <div class="alert alert-warning">
            <strong>Device token (copy now):</strong>
            <code class="d-block mt-2 user-select-all" style="word-break: break-all; font-size: 0.95rem;">{{ session('plain_token') }}</code>
            <p class="mb-0 mt-2 small">Paste this into the Hybrid pair panel on Attendance, OPAC, or Catalog create pages.</p>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-semibold">Register device</div>
        <div class="card-body">
            <form method="POST" action="{{ route('hybrid_devices.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="Front gate attendance PC">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional">
                </div>
                <div class="col-12 d-flex flex-wrap gap-3">
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="allow_attendance" value="1" checked>
                        <span class="form-check-label">Attendance</span>
                    </label>
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="allow_opac" value="1" checked>
                        <span class="form-check-label">OPAC cache</span>
                    </label>
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="allow_catalog" value="1">
                        <span class="form-check-label">Catalog upload</span>
                    </label>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Create &amp; show token</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Devices</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Capabilities</th>
                        <th>Last seen / sync</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $device)
                        <tr>
                            <td>
                                <strong>{{ $device->name }}</strong>
                                @if ($device->notes)
                                    <div class="small text-muted">{{ $device->notes }}</div>
                                @endif
                            </td>
                            <td class="small">
                                @if ($device->allow_attendance) Attendance @endif
                                @if ($device->allow_opac) · OPAC @endif
                                @if ($device->allow_catalog) · Catalog @endif
                            </td>
                            <td class="small">
                                {{ $device->last_seen_at?->timezone('Asia/Manila')->format('Y-m-d H:i') ?? '—' }}
                                <br>
                                {{ $device->last_sync_at?->timezone('Asia/Manila')->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td>
                                <form method="POST" action="{{ route('hybrid_devices.update', $device) }}" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="name" value="{{ $device->name }}">
                                    <input type="hidden" name="notes" value="{{ $device->notes }}">
                                    <input type="hidden" name="allow_attendance" value="{{ $device->allow_attendance ? '1' : '0' }}">
                                    <input type="hidden" name="allow_opac" value="{{ $device->allow_opac ? '1' : '0' }}">
                                    <input type="hidden" name="allow_catalog" value="{{ $device->allow_catalog ? '1' : '0' }}">
                                    <input type="hidden" name="is_active" value="{{ $device->is_active ? '0' : '1' }}">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                                        {{ $device->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('hybrid_devices.reissue', $device) }}" class="d-inline" onsubmit="return confirm('Reissue token? The old token stops working.');">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-warning" type="submit">Reissue</button>
                                </form>
                                <form method="POST" action="{{ route('hybrid_devices.destroy', $device) }}" class="d-inline" onsubmit="return confirm('Delete this device?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted text-center py-4">No hybrid devices yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 small text-muted">
        <p class="mb-1"><strong>How to use</strong></p>
        <ol class="mb-0">
            <li>Create a device with the needed capabilities and copy the token.</li>
            <li>On the target browser (attendance kiosk, OPAC station, or catalog station), open the Hybrid pair panel and save the token.</li>
            <li>While online, the app downloads rosters / OPAC cache. While offline, scans and catalog drafts queue locally and upload on reconnect.</li>
        </ol>
    </div>
</div>
@endsection
