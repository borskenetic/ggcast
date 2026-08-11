@extends('layouts.sec')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/tailwind-build.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/attendance_logs/index.css') }}">
@endsection

@section('content')

    <div class="container mt-4">
        <div class="mb-4 flex gap-3 flex-wrap">
            <a href="{{ route('attendance_logs.reports.hub') }}" class="export-btn">
                📈 Reports
            </a>
            <a href="{{ route('attendance_logs.export.pdf', request()->query()) }}" class="export-btn">
                📄 Export PDF
            </a>
            
            <a href="{{ route('attendance_logs.export.excel', request()->query()) }}" class="export-btn">
                📊 Export Excel
            </a>

            <a href="{{ route('book.index') }}" class="export-btn">
                Go Back
            </a>
        </div>

        <!-- ✅ Filters: fully dynamic -->
        <div class="mb-6 no-bg p-4">
            <form method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 items-end">
                <!-- Global Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search"
                           class="border px-3 py-2 w-full">
                </div>


                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="border px-3 py-2 w-full">
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="border px-3 py-2 w-full">
                </div>

                <!-- Student Name Dropdown -->
                <div hidden>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Student Name</label>
                    <select name="student_name" class="border px-3 py-2 w-full">
                        <option value="">All Students</option>
                        @foreach($students as $student)
                        <option value="{{ $student->id }}" {{ request('student_name')==$student->id ? 'selected' : ''
                            }}>
                            {{ $student->lastname }}, {{ $student->firstname }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Course Code Dropdown -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Program</label>
                    <select name="course_code" class="border px-3 py-2 w-full">
                        <option value="">All Programs</option>
                        @foreach($courses as $course)
                        <option value="{{ $course }}" {{ request('course_code')==$course ? 'selected' : '' }}>
                            {{ $course }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Year Level Dropdown -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                    <select name="year_level" class="border px-3 py-2 w-full">
                        <option value="">All Levels</option>
                        <option value="First Year">First Year</option>
                        <option value="Second Year">Second Year</option>
                        <option value="Third Year">Third Year</option>
                        <option value="Fourth Year">Fourth Year</option>
                        <option value="Fifth Year">Fifth Year</option>

                    </select>
                </div>

                <!-- Search Button -->
                <div>
                    <button type="submit" class="btn-search">
                        🔍 Search
                    </button>
                </div>
            </form>
        </div>

        <!-- ✅ Attendance Logs Table -->
        <div class="overflow-x-auto bg-white rounded shadow">
            <table class="w-full text-sm text-left table-auto">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="px-4 py-2">Last Name</th>
                        <th class="px-4 py-2">First Name</th>
                        <th class="px-4 py-2">Program</th>
                        <th class="px-4 py-2">Year Level</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Scanned At</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($logs as $log)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2">
                            {{ $log->patronLastName() }}
                        </td>
                        <td class="px-4 py-2">
                            {{ $log->patronFirstName() }}
                            @if($log->isEmployee())
                                <span class="text-xs text-gray-500">(Staff)</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            {{ $log->patronProgram() }}
                        </td>
                        <td class="px-4 py-2">
                            {{ $log->patronYear() }}
                        </td>
                        <td class="px-4 py-2">
                            @php $status = strtolower($log->status); @endphp
                            @if($status === 'in')
                            <span class="in">IN</span>
                            @elseif($status === 'out')
                            <span class="out">OUT</span>
                            @else
                            <span class="inline-block px-2 py-1 text-xs font-semibold text-white bg-gray-500 rounded">
                                Unknown
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $log->scanned_at ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center px-4 py-6 text-gray-500">No attendance records found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $logs->links() }}
        </div>
    </div>
    
@endsection