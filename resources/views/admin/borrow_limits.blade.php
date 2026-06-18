@extends('layouts.sec')

@section('content')
<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">Borrow Limits</h3>
        <a href="{{ route('logs.index') }}" class="btn btn-outline-secondary btn-sm">Circulation desk</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <p class="text-muted">
                Set how many books a patron may have on loan at the same time, including
                <strong>check out</strong> and <strong>room use</strong>. These limits apply at the circulation desk
                and on student self-checkout (OPAC).
            </p>

            <form method="POST" action="{{ route('circulation.borrow_limits.update') }}" id="borrowLimitsForm">
                @csrf

                <div class="mb-4">
                    <label for="studentMax" class="form-label fw-semibold">Students — maximum books on loan</label>
                    <input type="number" name="student_max" id="studentMax" class="form-control"
                           min="1" max="100" value="{{ old('student_max', $studentMax) }}" required>
                    <div class="form-text">Recommended: 5. Students cannot borrow more than this total at once.</div>
                </div>

                <hr>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Faculty &amp; Staff — maximum books on loan</label>
                    <input type="hidden" name="employee_unlimited" value="0">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="employeeUnlimited"
                               name="employee_unlimited" value="1"
                               {{ old('employee_unlimited', $employeeUnlimited ? '1' : '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="employeeUnlimited">Unlimited (no cap)</label>
                    </div>
                    <div id="employeeMaxField">
                        <input type="number" name="employee_max" id="employeeMax" class="form-control"
                               min="1" max="100" value="{{ old('employee_max', $employeeMax) }}">
                        <div class="form-text">When not unlimited, faculty and staff may borrow up to this many books at once.</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save borrow limits</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Current policy</div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Students: <strong>{{ $studentMax }}</strong> book(s) maximum</li>
                <li>Faculty &amp; Staff:
                    @if($employeeUnlimited)
                        <strong>Unlimited</strong>
                    @else
                        <strong>{{ $employeeMax }}</strong> book(s) maximum
                    @endif
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const unlimited = document.getElementById('employeeUnlimited');
    const maxField = document.getElementById('employeeMaxField');
    const maxInput = document.getElementById('employeeMax');

    function syncEmployeeMaxField() {
        const isUnlimited = unlimited.checked;
        maxField.style.display = isUnlimited ? 'none' : 'block';
        maxInput.required = !isUnlimited;
    }

    unlimited.addEventListener('change', syncEmployeeMaxField);
    syncEmployeeMaxField();
});
</script>
@endsection
