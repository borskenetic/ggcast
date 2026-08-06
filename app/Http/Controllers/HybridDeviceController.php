<?php

namespace App\Http\Controllers;

use App\Models\HybridDevice;
use Illuminate\Http\Request;

class HybridDeviceController extends Controller
{
    public function index()
    {
        $devices = HybridDevice::query()->orderByDesc('id')->get();

        return view('hybrid_devices.index', compact('devices'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'allow_attendance' => 'nullable|boolean',
            'allow_opac' => 'nullable|boolean',
            'allow_catalog' => 'nullable|boolean',
        ]);

        $issued = HybridDevice::issue($validated['name'], [
            'allow_attendance' => $request->boolean('allow_attendance', true),
            'allow_opac' => $request->boolean('allow_opac', true),
            'allow_catalog' => $request->boolean('allow_catalog', false),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('hybrid_devices.index')
            ->with('success', 'Hybrid device created. Copy the token now — it will not be shown again.')
            ->with('plain_token', $issued['plain_token'])
            ->with('new_device_id', $issued['device']->id);
    }

    public function update(Request $request, HybridDevice $hybridDevice)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'allow_attendance' => 'nullable|boolean',
            'allow_opac' => 'nullable|boolean',
            'allow_catalog' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $hybridDevice->update([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'allow_attendance' => $request->boolean('allow_attendance'),
            'allow_opac' => $request->boolean('allow_opac'),
            'allow_catalog' => $request->boolean('allow_catalog'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Device updated.');
    }

    public function reissue(HybridDevice $hybridDevice)
    {
        $token = $hybridDevice->reissueToken();

        return redirect()
            ->route('hybrid_devices.index')
            ->with('success', 'Token reissued. Copy it now — it will not be shown again.')
            ->with('plain_token', $token)
            ->with('new_device_id', $hybridDevice->id);
    }

    public function destroy(HybridDevice $hybridDevice)
    {
        $hybridDevice->delete();

        return back()->with('success', 'Device removed.');
    }
}
