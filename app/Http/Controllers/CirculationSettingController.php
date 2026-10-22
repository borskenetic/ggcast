<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class CirculationSettingController extends Controller
{
    public function edit()
    {
        return view('admin.borrow_limits', [
            'studentMax' => Setting::maxLoansForStudents(),
            'employeeUnlimited' => Setting::employeeLoansUnlimited(),
            'employeeMax' => Setting::maxLoansForEmployees(),
            'reserveMax' => Setting::maxReserveLoans(),
            'maxRenewals' => Setting::maxRenewalsPerLoan(),
        ]);
    }

    public function update(Request $request)
    {
        $employeeUnlimited = $request->input('employee_unlimited') === '1';

        $request->validate([
            'student_max' => 'required|integer|min:1|max:100',
            'employee_unlimited' => 'required|in:0,1',
            'employee_max' => $employeeUnlimited ? 'nullable|integer|min:1|max:100' : 'required|integer|min:1|max:100',
            'reserve_max' => 'required|integer|min:0|max:100',
            'max_renewals' => 'required|integer|min:0|max:100',
        ]);

        Setting::setBorrowLimits(
            (int) $request->input('student_max'),
            $employeeUnlimited,
            (int) ($request->input('employee_max') ?: Setting::DEFAULT_MAX_LOANS_EMPLOYEES),
            (int) $request->input('reserve_max'),
            (int) $request->input('max_renewals')
        );

        return redirect()
            ->route('circulation.borrow_limits.edit')
            ->with('success', 'Borrow limits updated successfully.');
    }
}
