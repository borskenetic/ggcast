<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ComposesEmployeeIdCard;
use App\Models\Employee;
use ZipArchive;

/**
 * Faculty & staff Library ID cards — distinct templates from student IDs.
 */
class EmployeeIdCardController extends Controller
{
    use ComposesEmployeeIdCard;

    public function front($id)
    {
        $employee = Employee::findOrFail($id);
        $img = $this->employeeIdCardTemplate('front');

        $middle = trim((string) ($employee->middle_initial ?? ''));
        $fullName = trim(implode(' ', array_filter([
            $employee->firstname,
            $middle !== '' ? rtrim($middle, '.').'.' : null,
            $employee->lastname,
        ])));

        $this->composeEmployeeIdCardFront($img, [
            'photo' => $employee->formal_picture,
            'full_name' => $fullName,
        ]);

        return $img->response('png');
    }

    public function back($id)
    {
        $employee = Employee::findOrFail($id);
        $img = $this->employeeIdCardTemplate('back');

        $department = $employee->department
            ?: $employee->designation
            ?: $employee->position
            ?: $employee->program;

        $this->composeEmployeeIdCardBack($img, [
            'birth_date' => $employee->birth_date,
            'blood_type' => $employee->blood_type,
            'sex' => $employee->sex,
            'department' => $department,
            'year_start_work' => $employee->year_start_work,
            'mobile_number' => $employee->mobile_number,
            'address' => $employee->address,
            'signature' => $employee->employee_signature,
        ]);

        return $img->response('png');
    }

    public function download($id)
    {
        $employee = Employee::findOrFail($id);

        $front = $this->front($id)->getContent();
        $back = $this->back($id)->getContent();

        $zipPath = storage_path("app/temp_emp_id_{$id}.zip");
        $frontPath = storage_path("app/emp_front_{$id}.png");
        $backPath = storage_path("app/emp_back_{$id}.png");

        file_put_contents($frontPath, $front);
        file_put_contents($backPath, $back);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($frontPath, "{$employee->lastname}_{$employee->firstname}_front.png");
            $zip->addFile($backPath, "{$employee->lastname}_{$employee->firstname}_back.png");
            $zip->close();
        }

        unlink($frontPath);
        unlink($backPath);

        return response()->download($zipPath, "{$employee->lastname}_{$employee->firstname}_ID.zip")
            ->deleteFileAfterSend(true);
    }
}
