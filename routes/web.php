<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookLogController;
use App\Http\Controllers\RFIDScanController;
use App\Http\Controllers\BookImportController; 
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EbookController;
use App\Http\Controllers\ProspectusController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AttendanceLogController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\IdCardController;
use Spatie\SimpleExcel\SimpleExcelWriter;
use App\Http\Controllers\PendingStudentController;
use App\Http\Controllers\PendingEmployeeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\RoomReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SMSController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FineSettingController;
use App\Http\Controllers\FineClearanceController;
use App\Http\Controllers\CirculationSettingController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\OpenLibraryCopyCatalogController;
use App\Http\Controllers\CatalogFrameworkAdminController;
use App\Http\Controllers\CatalogMarcSelectOptionsController;
use App\Http\Controllers\EmployeeIdCardController;
use App\Http\Controllers\HybridDeviceController;
use App\Http\Controllers\Api\HybridSyncController;
use Carbon\Carbon;
use App\Models\Book;

// =============================
// Public Routes
// =============================
Route::get('/', function () {
    if (auth()->check() && in_array(auth()->user()->role, ['admin', 'staff'], true)) {
        return redirect()->route('book.index');
    }

    return view('index');
})->name('home');
// Route::get('/', function () {
//     return redirect()->route('login');
// })->name('home');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/index', fn() => redirect()->route('book.index'));
Route::get('/filter/years', [BookController::class, 'getYears']);
Route::get('/filter/courses', [BookController::class, 'getCourses']);
Route::get('/rooms/book', [RoomReservationController::class, 'create'])->name('rooms.book');
Route::post('/rooms/book', [RoomReservationController::class, 'store'])->name('room-reservations.store');
Route::get('/rooms/schedule', [RoomReservationController::class, 'schedule'])->name('rooms.schedule');
Route::get('/rooms/{id}/show', [RoomReservationController::class, 'show'])->name('rooms.show');

Route::get('/register', [PendingStudentController::class, 'create'])->name('patron.register');
Route::post('/register', [PendingStudentController::class, 'store'])->name('pending.store');
Route::post('/register-employee', [PendingEmployeeController::class, 'store'])->name('pendingEmployee.store');
Route::redirect('/patrons/register', '/register');
// Feedback Form (User-facing)
Route::get('/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store'); 
Route::get('/books/copies', [BookController::class, 'viewCopies'])->name('books.copies');

Route::get('/attendance', [AttendanceController::class, 'showScanner'])->name('attendance.scan');
Route::post('/attendance', [AttendanceController::class, 'scan'])->name('attendance.process');

// Hybrid OPAC snapshot is public for offline browse (rate-bound by app use; prefer device tokens for kiosks)
Route::get('/hybrid/opac/snapshot', [HybridSyncController::class, 'opacSnapshotAuthenticated'])
    ->name('hybrid.opac.snapshot');


Route::post('/attendance-feedback', [FeedController::class, 'store'])
    ->name('attendance.feedback.store');

Route::get('/student/qr/{qrcode}', [StudentController::class, 'profile'])
    ->name('student.qr.profile');

Route::get('/kiosk/scan', fn () => view('kiosk.scan'))->name('kiosk.scan');

Route::post('/students/profile/request',
    [StudentController::class, 'submitEditRequest']
)->name('students.profile.request');

Route::post('/checkout/process', [CheckoutController::class, 'process'])
    ->name('checkout.process');

Route::get('/opac', [BookController::class, 'landingPage'])->name('landing');
Route::get('/opac/api/book/{book}', [BookController::class, 'opacBookDetails'])->name('opac.book.details');
Route::post('/checkout/bulk', [CheckoutController::class, 'bulk'])
    ->name('checkout.bulk');
// =============================
// Student / Faculty only
// =============================


// =============================
// Admin + Staff
// =============================
Route::middleware(['auth', 'can:isAdminOrStaff'])->group(function () {
    // Hybrid sync for signed-in staff (session / CSRF)
    Route::get('/hybrid/attendance/roster', [HybridSyncController::class, 'attendanceRosterAuthenticated'])
        ->name('hybrid.attendance.roster');
    Route::post('/hybrid/catalog/records', [HybridSyncController::class, 'pushCatalogAuthenticated'])
        ->name('hybrid.catalog.records');

    Route::resource('book', BookController::class);
    Route::get('/book/catalog/courses-for-programs', [BookController::class, 'coursesForPrograms'])
        ->name('books.coursesForPrograms');

    Route::get('/books', [BookController::class, 'index'])->name('books.index');
    Route::get('/staff/books/copies', [BookController::class, 'viewCopiesStaff'])->name('books.copies.staff');
    Route::get('/staff/books/archived', [BookController::class, 'archivedIndex'])->name('books.archived');
    Route::get('/staff/books/trash', [BookController::class, 'trashIndex'])->name('books.trash');
    Route::post('/books/{book}/archive', [BookController::class, 'archive'])->name('books.archive');
    Route::post('/books/{book}/unarchive', [BookController::class, 'unarchive'])->name('books.unarchive');
    Route::post('/books/{id}/restore', [BookController::class, 'restoreTrashed'])->name('books.restore');
    Route::delete('/books/{id}/force-delete', [BookController::class, 'forceDeleteTrashed'])->name('books.forceDelete');

    Route::get('/rfid-scanner', [RFIDScanController::class, 'index'])->name('rfid.scanner');
    Route::post('/rfid-scan', [RFIDScanController::class, 'scan'])->name('rfid.scan');

    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
    Route::post('/import-books', [BookImportController::class, 'import'])->name('books.import');

    Route::resource('ebooks', EbookController::class);
    Route::get('/program/{program?}/courses', [App\Http\Controllers\EbookController::class, 'getCourses'])->name('program.courses');
    Route::get('/ebooks/get-courses/{programId}', [EbookController::class, 'getCourses']);
    Route::get('/export-books', [ExportController::class, 'exportBooks'])->name('export.books');
    Route::get('/export-transactions', [ExportController::class, 'exportTransactions'])->name('transactions.export');


    // Attendance Scanner and Book Reports (Admin + Staff)
    
    Route::get('/download-book-report', [BookController::class, 'downloadBookReport'])->name('book.report.download');
    Route::get('/reports/library-holdings', [\App\Http\Controllers\LibraryHoldingsReportController::class, 'create'])->name('reports.library_holdings.create');
    Route::post('/reports/library-holdings', [\App\Http\Controllers\LibraryHoldingsReportController::class, 'download'])->name('reports.library_holdings.download');
    Route::get('/attendance/change-video', [AttendanceController::class, 'showChangeVideo'])->name('attendance.changeVideo');
    Route::post('/attendance/upload-video', [AttendanceController::class, 'uploadVideo'])->name('attendance.uploadVideo');
    Route::get('/attendance/logout-feedback', [AttendanceController::class, 'feedbackSettings'])->name('attendance.feedback.settings');
    Route::post('/attendance/logout-feedback', [AttendanceController::class, 'updateFeedbackSettings'])->name('attendance.feedback.settings.update');
    
    Route::get('/patron-suggestions', [BookLogController::class, 'patronSuggestions'])->name('patron.suggestions');
    Route::get('/book-suggestions', [BookLogController::class, 'bookSuggestions'])->name('book.suggestions');
    Route::get('/book-title-log-suggestions', [BookLogController::class, 'bookTitleLogSuggestions'])->name('book.title.log.suggestions');
    Route::get('/catalog/copy/openlibrary', [OpenLibraryCopyCatalogController::class, 'searchForm'])
    ->name('catalog.copy.openlibrary.form');
    
    Route::match(['get', 'post'], '/catalog/copy/openlibrary/search', [OpenLibraryCopyCatalogController::class, 'search'])
        ->name('catalog.copy.openlibrary.search');
    
    Route::post('/catalog/copy/openlibrary/store', [OpenLibraryCopyCatalogController::class, 'store'])
        ->name('catalog.copy.openlibrary.store');

    Route::get('/feedbacks', [FeedbackController::class, 'index'])->name('feedback.index');

    Route::get('/holidays/list', [HolidayController::class, 'list'])->name('holidays.list');
    Route::post('/holidays/toggle', [HolidayController::class, 'toggle'])->name('holidays.toggle');
    Route::get('/holidays/all', [HolidayController::class, 'all'])->name('holidays.all');

    Route::post('/sms/send', [SMSController::class, 'send'])->name('sms.send');

});

// =============================
// Admin-only Routes
// =============================
Route::middleware(['auth', 'can:isAdmin'])->group(function () {
    // Book Logs
    Route::get('/logs', [BookLogController::class, 'index'])->name('logs.index');
    Route::post('/logs', [BookLogController::class, 'store'])->name('logs.store');
    Route::post('/logs/{book}/renew', [BookLogController::class, 'renew'])->name('logs.renew');

    // Prospectus
    Route::prefix('prospectus')->name('prospectus.')->group(function () {
        Route::get('/', [ProspectusController::class, 'index'])->name('index');
        Route::post('/store-program', [ProspectusController::class, 'storeProgram'])->name('storeProgram');
        Route::get('/{program}/years', [ProspectusController::class, 'getProgramYears'])->name('getProgramYears');
    });
    // Course management
    Route::post('/prospectus/{year}/course', [ProspectusController::class, 'storeCourse'])->name('prospectus.storeCourse');
    Route::put('/prospectus/course/{course}', [ProspectusController::class, 'updateCourse'])->name('prospectus.updateCourse');
    Route::delete('/prospectus/course/{course}', [ProspectusController::class, 'destroyCourse'])->name('prospectus.destroyCourse');
    Route::put('/prospectus/program/{program}', [ProspectusController::class, 'updateProgram'])->name('prospectus.updateProgram');
    Route::delete('/prospectus/program/{program}', [ProspectusController::class, 'destroyProgram'])->name('prospectus.destroyProgram');
    // Show form to add subject (course & year are passed via query)
    Route::get('/prospectus/add-subject', [ProspectusController::class, 'createSubject'])->name('prospectus.addSubject');
    // Store new subject
    Route::post('/prospectus/store-subject', [ProspectusController::class, 'storeSubject'])->name('prospectus.storeSubject');

    // Student Management
    Route::get('/students/report', [StudentController::class, 'index'])->name('students.report');
    Route::post('/students/import', [StudentController::class, 'import'])->name('students.import');
    Route::get('/students/export', [StudentController::class, 'export'])->name('students.export');
    Route::resource('students', StudentController::class);
    Route::get('/idcard/download/{id}', [IdCardController::class, 'download'])->name('idcard.download');

    Route::get('/student/pending-requests',
        [StudentController::class, 'pendingRequests']
    )->name('students.pending.requests');

    Route::post('/admin/requests/{id}/approve',
        [StudentController::class, 'approveRequest']
    )->name('admin.requests.approve');

    Route::post('/admin/requests/{id}/reject',
        [StudentController::class, 'rejectRequest']
    )->name('admin.requests.reject');

    // Attendance Logs
    Route::get('/attendance-logs', [AttendanceLogController::class, 'index'])->name('attendance_logs.index');
    Route::get('/attendance-logs/reports', [AttendanceLogController::class, 'reportsHub'])->name('attendance_logs.reports.hub');
    Route::get('/attendance-logs/reports/dashboard', [AttendanceLogController::class, 'reportsDashboard'])->name('attendance_logs.reports.dashboard');
    Route::get('/attendance-logs/reports/export', [AttendanceLogController::class, 'reportsExportCsv'])->name('attendance_logs.reports.export');
    Route::get('/attendance-logs/export/excel', [AttendanceLogController::class, 'exportExcel'])->name('attendance_logs.export.excel');
    Route::get('/attendance-logs/export/pdf', [AttendanceLogController::class, 'exportPdf'])->name('attendance_logs.export.pdf');

    // File Repository
    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::post('/files/upload', [FileController::class, 'upload'])->name('files.upload');
    Route::get('/files/view/{id}', [FileController::class, 'view'])->name('files.view');
    Route::get('/files/download/{id}', [FileController::class, 'download'])->name('files.download');
    Route::delete('/files/delete/{id}', [FileController::class, 'delete'])->name('files.delete');

    // User Management
    Route::get('/view-users', [UserController::class, 'index'])->name('users.index');
    Route::get('/edit-user/{id}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/update-user/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/delete-user/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    
    Route::get('/create-user', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');

    Route::get('/idcard/{id}', [IdCardController::class, 'generate']);
    Route::get('/idcard/front/{id}', [IdCardController::class, 'front']);
    Route::get('/idcard/back/{id}', [IdCardController::class, 'back'])->name('idcard.back');

    Route::get('/admin/pending', [StudentController::class, 'pending'])->name('students.pending');
    Route::post('/admin/pending/{id}/approve', [StudentController::class, 'approve'])->name('students.approve');
    Route::post('/admin/pending/{id}/reject', [StudentController::class, 'reject'])->name('students.reject');
    Route::get('/pending', [PendingStudentController::class, 'index'])->name('pending.index');
    
    Route::get('/pending/employees', [PendingEmployeeController::class, 'index'])->name('pending.employees');
    Route::post('/pending/employees/approve/{id}', [PendingEmployeeController::class, 'approve'])->name('employees.approve');
    Route::post('/pending/employees/reject/{id}', [PendingEmployeeController::class, 'reject'])->name('employees.reject');
    
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/edit/{id}', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/update/{id}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/delete/{id}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });
    Route::prefix('employees/idcard')->group(function () {
        Route::get('/front/{id}', [EmployeeIdCardController::class, 'front'])->name('employees.id.front');
        Route::get('/back/{id}', [EmployeeIdCardController::class, 'back'])->name('employees.id.back');
        Route::get('/download/{id}', [EmployeeIdCardController::class, 'download'])->name('employees.id.download');
    });
    
    Route::get('/rooms/pending', [RoomReservationController::class, 'pending'])->name('rooms.pending');
    Route::post('/rooms/{id}/approve', [RoomReservationController::class, 'approve'])->name('rooms.approve');
    Route::post('/rooms/reject/{id}', [RoomReservationController::class, 'reject'])->name('rooms.reject');
    Route::delete('/resrooms/{id}', [RoomReservationController::class, 'destroy'])->name('resrooms.destroy');
    Route::get('/rooms/check-availability', [RoomReservationController::class, 'checkAvailability'])->name('rooms.check');
    Route::get('/rooms/logs', [RoomReservationController::class, 'logs'])->name('rooms.logs');
    
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/{id}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
    Route::put('/rooms/{id}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{id}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    
    Route::get('/admin/fines', [FineSettingController::class, 'edit'])->name('fines.edit');
    Route::post('/admin/fines', [FineSettingController::class, 'update'])->name('fines.update');
    Route::get('/admin/circulation/borrow-limits', [CirculationSettingController::class, 'edit'])->name('circulation.borrow_limits.edit');
    Route::post('/admin/circulation/borrow-limits', [CirculationSettingController::class, 'update'])->name('circulation.borrow_limits.update');
    Route::get('/admin/fines/outstanding', [FineClearanceController::class, 'index'])->name('fines.outstanding');
    Route::post('/admin/fines/logs/{bookLog}/clear', [FineClearanceController::class, 'clear'])->name('fines.logs.clear');

    Route::get('/admin/attendance-feedbacks', [FeedController::class, 'index'])->name('admin.attendance.feedbacks');

    Route::get('/sms-blast', [SMSController::class, 'index'])->name('sms.page');
    Route::get('/sms/scan-message', [SMSController::class, 'scanMessage'])->name('sms.scan-message');
    Route::post('/sms/scan-message', [SMSController::class, 'updateScanMessage'])->name('sms.scan-message.update');
    Route::post('/sms/send-one-student', [SMSController::class, 'sendOneStudent'])->name('sms.send-one-student');
    Route::post('/sms/send-overdue', [SMSController::class, 'sendOverdue'])->name('sms.send-overdue');
    Route::get('/sms/count', [SMSController::class, 'count'])->name('sms.count');

    Route::prefix('admin/catalog-frameworks')->name('admin.catalog_frameworks.')->group(function () {
        Route::get('/', [CatalogFrameworkAdminController::class, 'index'])->name('index');
        Route::get('/{catalog_framework}/edit', [CatalogFrameworkAdminController::class, 'edit'])->name('edit');
        Route::put('/{catalog_framework}/fields', [CatalogFrameworkAdminController::class, 'updateFields'])->name('fields.update');
        Route::post('/{catalog_framework}/fields', [CatalogFrameworkAdminController::class, 'attachField'])->name('fields.attach');
        Route::post('/{catalog_framework}/marc-fields', [CatalogFrameworkAdminController::class, 'storeMarcField'])->name('marc_fields.store');
        Route::delete('/{catalog_framework}/fields/{field}', [CatalogFrameworkAdminController::class, 'detachField'])->name('fields.detach');
    });

    Route::get('/admin/catalog-select-options', [CatalogMarcSelectOptionsController::class, 'index'])
        ->name('admin.catalog_select_options.index');
    Route::post('/admin/catalog-select-options', [CatalogMarcSelectOptionsController::class, 'store'])
        ->name('admin.catalog_select_options.store');
    Route::delete('/admin/catalog-select-options', [CatalogMarcSelectOptionsController::class, 'destroy'])
        ->name('admin.catalog_select_options.destroy');

    Route::get('/admin/hybrid-devices', [HybridDeviceController::class, 'index'])->name('hybrid_devices.index');
    Route::post('/admin/hybrid-devices', [HybridDeviceController::class, 'store'])->name('hybrid_devices.store');
    Route::put('/admin/hybrid-devices/{hybridDevice}', [HybridDeviceController::class, 'update'])->name('hybrid_devices.update');
    Route::post('/admin/hybrid-devices/{hybridDevice}/reissue', [HybridDeviceController::class, 'reissue'])->name('hybrid_devices.reissue');
    Route::delete('/admin/hybrid-devices/{hybridDevice}', [HybridDeviceController::class, 'destroy'])->name('hybrid_devices.destroy');

});
