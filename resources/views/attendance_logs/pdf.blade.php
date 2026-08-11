<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Logs PDF</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #444; padding: 6px; text-align: left; }
        th { background-color: #f0f0f0; }
    </style>
</head>
<body>
    <h2>Attendance Logs</h2>
    <table>
        <thead>
            <tr>
                <th>Last Name</th>
                <th>First Name</th>
                <th>Course</th> <!-- ✅ ADD THIS -->
                <th>Status</th>
                <th>Scanned At</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->patronLastName() }}</td>
                    <td>{{ $log->patronFirstName() }}{{ $log->isEmployee() ? ' (Staff)' : '' }}</td>
                    <td>{{ $log->patronProgram() }}</td>
                    <td>{{ strtoupper($log->status) }}</td>
                    <td>
    {{ $log->scanned_at ? \Carbon\Carbon::parse($log->scanned_at, 'UTC')->timezone('Asia/Manila')->format('Y-m-d h:i A') : '—' }}                    
</td>


                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
