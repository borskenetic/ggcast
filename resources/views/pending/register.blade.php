<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Registration — {{ $brand['school_name'] ?? 'GGCAST' }}</title>

    @include('partials.branding-styles')
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @if(!empty($brand['favicon_url']))
        <link rel="icon" type="{{ $brand['favicon_mime'] ?? 'image/x-icon' }}" href="{{ $brand['favicon_url'] }}">
    @endif
</head>
<body class="register-page">

<div class="register-page-wrapper">
    <header class="register-topbar">
        <a href="{{ $brand['school_home_url'] ?? route('home') }}" class="register-back-btn" aria-label="Back to home">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>Back</span>
        </a>

        @if(!empty($brand['logo_landscape_url']))
            <img src="{{ $brand['logo_landscape_url'] }}" alt="{{ $brand['school_name'] }}" class="register-topbar-logo">
        @elseif(!empty($brand['logo_url']))
            <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['school_name'] }}" class="register-topbar-logo">
        @endif
    </header>

    <main class="register-main">
        <div class="register-card">
            <div class="register-header">
                @if(!empty($brand['logo_url']))
                    <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['school_name'] }}" class="register-logo">
                @endif
                <h1 class="register-title">Library Registration</h1>
                <p class="register-subtitle">Create your library account to get started</p>
            </div>

            @if(session('success'))
                <div class="register-alert register-alert--success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="register-alert register-alert--danger">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="register-alert register-alert--danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="register-tabs" role="tablist">
                <button type="button" class="register-tab is-active" id="btnStudent" role="tab" aria-selected="true">Student</button>
                <button type="button" class="register-tab" id="btnEmployee" role="tab" aria-selected="false">Faculty &amp; Staff</button>
            </div>

            {{-- STUDENT FORM --}}
            <form id="studentForm" method="POST" action="{{ route('pending.store') }}" enctype="multipart/form-data">
                @csrf
                <h2 class="register-section-title">Student Information</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="student_firstname">First Name</label>
                        <input type="text" id="student_firstname" name="firstname" class="form-control" placeholder="First Name" value="{{ old('firstname') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="student_lastname">Last Name</label>
                        <input type="text" id="student_lastname" name="lastname" class="form-control" placeholder="Last Name" value="{{ old('lastname') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">M.I.</label>
                        @include('partials.middle_initial_input', ['value' => old('middle_initial')])
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_id_number">ID Number</label>
                        <input type="text" id="student_id_number" name="id_number" class="form-control" placeholder="ID Number" value="{{ old('id_number') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_birthday">Birthday</label>
                        <input type="date" id="student_birthday" name="birthday" class="form-control" value="{{ old('birthday') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_course">Course</label>
                        <select id="student_course" name="course" class="form-select">
                            <option value="">Select Course</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->program_code }}"
                                    {{ old('course') == $program->program_code ? 'selected' : '' }}>
                                    {{ $program->program_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_year">Year</label>
                        <select id="student_year" name="year" class="form-select">
                            <option value="">Select Year</option>
                            @foreach(['1st','2nd','3rd','4th','5th'] as $y)
                                <option value="{{ $y }} Year" {{ old('year') === $y.' Year' ? 'selected' : '' }}>{{ $y }} Year</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_mobile">Mobile Number</label>
                        <input type="text" id="student_mobile" name="mobile_number" class="form-control" placeholder="Mobile Number"
                               value="{{ old('mobile_number') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="student_address">Address</label>
                        <input type="text" id="student_address" name="address" class="form-control" placeholder="Address"
                               value="{{ old('address') }}">
                    </div>

                    <div class="col-12 mt-2">
                        <h3 class="register-section-title register-section-title--sub">Emergency Contact</h3>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_emergency_person">Emergency Person</label>
                        <input type="text" id="student_emergency_person" name="emergency_person" class="form-control" placeholder="Emergency Person"
                               value="{{ old('emergency_person') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_emergency_relationship">Relationship</label>
                        <input type="text" id="student_emergency_relationship" name="emergency_relationship" class="form-control" placeholder="Relationship"
                               value="{{ old('emergency_relationship') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_emergency_number">Emergency Number</label>
                        <input type="text" id="student_emergency_number" name="emergency_number" class="form-control" placeholder="Emergency Number"
                               value="{{ old('emergency_number') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_emergency_address">Emergency Address</label>
                        <input type="text" id="student_emergency_address" name="emergency_address" class="form-control" placeholder="Emergency Address"
                               value="{{ old('emergency_address') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="student_profile_picture">Profile Picture</label>
                        <input type="file" id="student_profile_picture" name="profile_picture" class="form-control" accept=".jpg,.jpeg,.png">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Signature (draw below)</label>
                        <canvas id="studentSignaturePad"></canvas>
                        <input type="hidden" name="student_signature" id="studentSignatureInput">
                        <button type="button" id="clearStudentSignature" class="register-clear-sig">Clear</button>
                    </div>
                </div>

                <button type="submit" class="register-submit">Submit Student Registration</button>
            </form>

            {{-- FACULTY & STAFF FORM --}}
            <form id="employeeForm" method="POST" action="{{ route('pendingEmployee.store') }}" enctype="multipart/form-data" class="hidden">
                @csrf
                <h2 class="register-section-title">Faculty &amp; Staff Information</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="employee_firstname">First Name</label>
                        <input type="text" id="employee_firstname" name="firstname" class="form-control" value="{{ old('firstname') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="employee_lastname">Last Name</label>
                        <input type="text" id="employee_lastname" name="lastname" class="form-control" value="{{ old('lastname') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Middle Initial</label>
                        @include('partials.middle_initial_input', ['value' => old('middle_initial')])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="employee_id">ID Number</label>
                        <input type="text" id="employee_id" name="employee_id" class="form-control" value="{{ old('employee_id') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="employee_designation">Designation</label>
                        <input type="text" id="employee_designation" name="designation" class="form-control" placeholder="e.g. Instructor I, Librarian"
                               value="{{ old('designation') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="employee_program">Select Program</label>
                        <select id="employee_program" name="program" class="form-select" required>
                            <option value="">— Select program —</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->program_code }}" @selected(old('program') === $program->program_code)>
                                    {{ $program->program_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="employee_year_start">Year of start of work in this HEI</label>
                        <select id="employee_year_start" name="year_start_work" class="form-select" required>
                            <option value="">— Select year —</option>
                            @foreach ($workStartYears as $yr)
                                <option value="{{ $yr }}" @selected(old('year_start_work') == (string) $yr)>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="employee_birth_date">Birthday</label>
                        <input type="date" id="employee_birth_date" name="birth_date" class="form-control" value="{{ old('birth_date') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="employee_mobile">Mobile Number</label>
                        <input type="text" id="employee_mobile" name="mobile_number" class="form-control" placeholder="09XXXXXXXXX"
                               value="{{ old('mobile_number') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="employee_address">Address</label>
                        <textarea id="employee_address" name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="employee_formal_picture">Formal picture (optional)</label>
                        <input type="file" id="employee_formal_picture" name="formal_picture" class="form-control" accept=".jpg,.jpeg,.png">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Signature (optional)</label>
                        <canvas id="employeeSignaturePad"></canvas>
                        <input type="hidden" name="employee_signature" id="employeeSignatureInput">
                        <button type="button" id="clearEmployeeSignature" class="register-clear-sig">Clear</button>
                    </div>
                </div>

                <h3 class="register-section-title register-section-title--sub mt-4">Emergency Contact Information</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="emp_emergency_name">Contact person</label>
                        <input type="text" id="emp_emergency_name" name="emergency_contact_name" class="form-control" placeholder="Contact person"
                               value="{{ old('emergency_contact_name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="emp_emergency_relationship">Relationship</label>
                        <input type="text" id="emp_emergency_relationship" name="emergency_contact_relationship" class="form-control" placeholder="Relationship"
                               value="{{ old('emergency_contact_relationship') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="emp_emergency_number">Contact number</label>
                        <input type="text" id="emp_emergency_number" name="emergency_contact_number" class="form-control" placeholder="Contact number"
                               value="{{ old('emergency_contact_number') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="emp_emergency_address">Emergency address</label>
                        <input type="text" id="emp_emergency_address" name="emergency_address" class="form-control" placeholder="Emergency address"
                               value="{{ old('emergency_address') }}">
                    </div>
                </div>

                <button type="submit" class="register-submit">Submit Faculty &amp; Staff Registration</button>
            </form>
        </div>
    </main>
</div>

<script>
function setupSignaturePad(canvasId, inputId, clearBtnId) {
    const canvas = document.getElementById(canvasId);
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let points = [];

    function resizeCanvas() {
        const dataUrl = canvas.toDataURL();
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        const img = new Image();
        img.src = dataUrl;
        img.onload = () => ctx.drawImage(img, 0, 0);
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    canvas.style.touchAction = 'none';

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    function startDrawing(e) {
        e.preventDefault();
        drawing = true;
        points = [];
        points.push(getPos(e));
    }

    function draw(e) {
        e.preventDefault();
        if (!drawing) return;

        const pos = getPos(e);
        points.push(pos);

        ctx.lineCap = 'round';
        ctx.strokeStyle = '#000';

        if (points.length === 1) {
            ctx.beginPath();
            ctx.arc(pos.x, pos.y, 1.5, 0, Math.PI * 2);
            ctx.fill();
            return;
        }

        ctx.beginPath();

        const last = points[points.length - 2];
        const dx = pos.x - last.x;
        const dy = pos.y - last.y;
        const speed = Math.sqrt(dx * dx + dy * dy);

        ctx.lineWidth = Math.max(1, 4 - speed / 2);
        ctx.moveTo(last.x, last.y);
        ctx.lineTo(pos.x, pos.y);

        ctx.stroke();
    }

    function stopDrawing() {
        if (!drawing) return;
        drawing = false;
        document.getElementById(inputId).value = canvas.toDataURL();
    }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);

    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);

    document.getElementById(clearBtnId).addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        points = [];
        document.getElementById(inputId).value = '';
    });

    return { resize: resizeCanvas };
}

const studentPad = setupSignaturePad('studentSignaturePad', 'studentSignatureInput', 'clearStudentSignature');
const employeePad = setupSignaturePad('employeeSignaturePad', 'employeeSignatureInput', 'clearEmployeeSignature');

const studentForm = document.getElementById('studentForm');
const employeeForm = document.getElementById('employeeForm');
const btnStudent = document.getElementById('btnStudent');
const btnEmployee = document.getElementById('btnEmployee');

function showRegisterTab(which) {
    const isStudent = which === 'student';
    studentForm.classList.toggle('hidden', !isStudent);
    employeeForm.classList.toggle('hidden', isStudent);
    btnStudent.classList.toggle('is-active', isStudent);
    btnEmployee.classList.toggle('is-active', !isStudent);
    btnStudent.setAttribute('aria-selected', isStudent ? 'true' : 'false');
    btnEmployee.setAttribute('aria-selected', isStudent ? 'false' : 'true');
    setTimeout(() => (isStudent ? studentPad : employeePad).resize(), 50);
}

btnStudent.addEventListener('click', () => showRegisterTab('student'));
btnEmployee.addEventListener('click', () => showRegisterTab('employee'));

@if ($errors->has('employee_id') || $errors->has('designation') || $errors->has('program') || $errors->has('year_start_work') || old('designation'))
    showRegisterTab('employee');
@endif
</script>
</body>
</html>
