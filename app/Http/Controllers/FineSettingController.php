<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FineSetting;

class FineSettingController extends Controller
{
    public function edit()
    {
        $settings = FineSetting::latest('created_at')->first();
        return view('admin.fines', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'fine_per_day' => 'required|numeric|min:0',
            'max_fine' => 'nullable|numeric|min:0',
            'grace_period_days' => 'required|integer|min:0',
            'loan_duration_days' => 'required|integer|min:1',
            'reserve_fine_per_day' => 'required|numeric|min:0',
            'reserve_max_fine' => 'nullable|numeric|min:0',
            'reserve_grace_period_days' => 'required|integer|min:0',
            'reserve_loan_duration_days' => 'required|integer|min:1',
        ]);

        FineSetting::create([
            'fine_per_day' => $request->fine_per_day,
            'max_fine' => $request->max_fine,
            'grace_period_days' => $request->grace_period_days,
            'loan_duration_days' => $request->loan_duration_days,
            'reserve_fine_per_day' => $request->reserve_fine_per_day,
            'reserve_max_fine' => $request->reserve_max_fine,
            'reserve_grace_period_days' => $request->reserve_grace_period_days,
            'reserve_loan_duration_days' => $request->reserve_loan_duration_days,
            'effective_from' => now(),
        ]);

        return redirect()->route('fines.edit')
                 ->with('success', 'Fine policy updated successfully.');
    }
}
