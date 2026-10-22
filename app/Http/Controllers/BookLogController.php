<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookLog;
use App\Models\Employee;
use App\Models\FineSetting;
use App\Models\Setting;
use App\Models\Student;
use App\Support\LoanDueDate;
use App\Support\LoanLimits;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookLogController extends Controller
{
    protected function calculateOverdueDays(Carbon $dueDate, Carbon $returnedDate, int $gracePeriod = 0)
    {
        if ($returnedDate->lessThanOrEqualTo($dueDate)) {
            return 0;
        }

        $holidays = \App\Models\Holiday::pluck('holiday_date')->map(function ($d) {
            return Carbon::parse($d)->startOfDay()->toDateString();
        });

        $overdueDays = 0;
        $current = $dueDate->copy()->addDay()->startOfDay();

        while ($current->lessThanOrEqualTo($returnedDate)) {
            $isWeekend = $current->isWeekend();
            $isHoliday = $holidays->contains($current->toDateString());

            if (! $isWeekend && ! $isHoliday) {
                $overdueDays++;
            }

            $current->addDay();
        }

        return max(0, $overdueDays - $gracePeriod);
    }

    protected function addBusinessDays(Carbon $start, int $days)
    {
        $holidays = \App\Models\Holiday::pluck('holiday_date')->map(function ($d) {
            return Carbon::parse($d)->startOfDay()->toDateString();
        });

        $date = $start->copy()->startOfDay();
        $added = 0;

        while ($added < $days) {
            $date->addDay();

            $isWeekend = $date->isWeekend();
            $isHoliday = $holidays->contains($date->toDateString());

            if (! $isWeekend && ! $isHoliday) {
                $added++;
            }
        }

        return $date;
    }

    /**
     * Cooldown: after returning a book, the same student must wait before borrowing the same book again.
     */
    protected function enforceReborrowCooldownOrNull(?int $studentId, ?int $employeeId, int $bookId): ?string
    {
        $latestReturn = BookLog::query()
            ->where('book_id', $bookId)
            ->where('status', 'Checked In')
            ->whereNotNull('returned_date')
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->orderByDesc('returned_date')
            ->value('returned_date');

        if (! $latestReturn) {
            return null;
        }

        $returnedAt = Carbon::parse($latestReturn)->timezone('Asia/Manila');
        $allowedAt = $returnedAt->copy()->addDays(BookController::REBORROW_COOLDOWN_DAYS);
        $now = Carbon::now('Asia/Manila');

        if ($now->lt($allowedAt)) {
            return 'This patron must wait '.BookController::REBORROW_COOLDOWN_DAYS.' days after returning this book before borrowing it again. (Available again on '.$allowedAt->format('M j, Y').')';
        }

        return null;
    }

    public function index(Request $request)
    {
        $logs = BookLog::with(['book', 'student', 'employee']);

        if ($request->filled('student_id')) {
            $student = Student::find($request->student_id);
            if ($student) {
                $legacyComma = "{$student->lastname}, {$student->firstname}";
                $legacySpace = trim("{$student->firstname} {$student->lastname}");
                $logs->where(function ($q) use ($student, $legacyComma, $legacySpace) {
                    $q->where('student_id', $student->id)
                        ->orWhere('patron_name', $legacyComma)
                        ->orWhere('patron_name', $legacySpace);
                });
            }
        } elseif ($request->filled('employee_id')) {
            $employee = Employee::find($request->employee_id);
            if ($employee) {
                $legacyComma = "{$employee->lastname}, {$employee->firstname}";
                $legacySpace = trim("{$employee->firstname} {$employee->lastname}");
                $logs->where(function ($q) use ($employee, $legacyComma, $legacySpace) {
                    $q->where('employee_id', $employee->id)
                        ->orWhere('patron_name', $legacyComma)
                        ->orWhere('patron_name', $legacySpace);
                });
            }
        } elseif ($request->filled('filter_patron')) {
            $term = trim((string) $request->filter_patron);
            if ($term !== '') {
                $logs->where(function ($q) use ($term) {
                    $q->where('patron_name', 'like', '%'.$term.'%')
                        ->orWhereHas('student', function ($s) use ($term) {
                            $s->where('firstname', 'like', '%'.$term.'%')
                                ->orWhere('lastname', 'like', '%'.$term.'%')
                                ->orWhere('id_number', 'like', '%'.$term.'%')
                                ->orWhereRaw(
                                    'LOWER(CONCAT(firstname, \' \', lastname)) LIKE ?',
                                    ['%'.strtolower($term).'%']
                                );
                        })
                        ->orWhereHas('employee', function ($e) use ($term) {
                            $e->where('firstname', 'like', '%'.$term.'%')
                                ->orWhere('lastname', 'like', '%'.$term.'%')
                                ->orWhere('employee_id', 'like', '%'.$term.'%')
                                ->orWhereRaw(
                                    'LOWER(CONCAT(firstname, \' \', lastname)) LIKE ?',
                                    ['%'.strtolower($term).'%']
                                );
                        });
                });
            }
        }

        if ($request->filled('book_title')) {
            $titleTerm = trim((string) $request->book_title);
            $logs->whereHas('book', function ($query) use ($titleTerm) {
                $query->where('title_statement', 'like', '%'.$titleTerm.'%');
            });
        }

        if ($request->filled('accession_no')) {
            $accessionTerm = trim((string) $request->accession_no);
            if ($accessionTerm !== '') {
                $logs->whereHas('book', function ($query) use ($accessionTerm) {
                    $query->where('accession_no', 'like', '%'.$accessionTerm.'%');
                });
            }
        }

        if ($request->filled('start_date')) {
            $logs->whereDate('timestamp', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $logs->whereDate('timestamp', '<=', $request->end_date);
        }

        if ($request->filled('circulation_type') && in_array($request->circulation_type, ['checkout', 'room_use'], true)) {
            if ($request->circulation_type === 'room_use') {
                $logs->where('circulation_type', BookLog::CIRCULATION_ROOM_USE);
            } else {
                $logs->where(function ($q) {
                    $q->where('circulation_type', BookLog::CIRCULATION_CHECKOUT)
                        ->orWhereNull('circulation_type');
                });
            }
        }

        $logs = $logs->latest()->paginate(10);

        $prefillPatronLabel = '';
        if ($request->filled('student_id')) {
            $ps = Student::find($request->student_id);
            if ($ps) {
                $prefillPatronLabel = $this->patronDisplayLabel($ps);
            }
        } elseif ($request->filled('employee_id')) {
            $pe = Employee::find($request->employee_id);
            if ($pe) {
                $prefillPatronLabel = $this->employeeDisplayLabel($pe);
            }
        } elseif ($request->filled('filter_patron')) {
            $prefillPatronLabel = trim((string) $request->filter_patron);
        }

        $filterBookTitle = trim((string) $request->input('book_title', ''));

        $prefillCopyIdentifier = trim((string) $request->input(
            'copy_identifier',
            $request->input('rfid', '')
        ));

        $loanDefaultDays = (int) (FineSetting::latest('created_at')->first()?->loan_duration_days ?? 7);

        return view('books.logs', compact(
            'logs',
            'prefillPatronLabel',
            'filterBookTitle',
            'prefillCopyIdentifier',
            'loanDefaultDays',
        ));
    }

    public function store(Request $request)
    {
        $copyCode = trim((string) ($request->input('copy_identifier') ?: $request->input('rfid')));

        $request->validate([
            'copy_identifier' => 'nullable|string|max:255',
            'rfid' => 'nullable|string|max:255',
            'status' => 'required|string|in:checked_out,room_use,checked_in',
            'student_id' => 'nullable|integer|exists:students,id|required_without:employee_id',
            'employee_id' => 'nullable|integer|exists:employees,id|required_without:student_id',
            'due_date' => 'nullable|date|after_or_equal:today',
            'loan_duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($copyCode === '') {
            return back()->withInput()->with('error', 'Enter the copy accession number, barcode, or RFID.');
        }

        $book = Book::findByCopyIdentifier($copyCode);

        if (! $book) {
            return back()->withInput()->with(
                'error',
                'No copy found for that code. Use accession number (recommended), barcode, or RFID.'
            );
        }

        $student = null;
        $employee = null;
        $studentId = $request->filled('student_id') ? (int) $request->student_id : null;
        $employeeId = $request->filled('employee_id') ? (int) $request->employee_id : null;

        if ($employeeId) {
            $employee = Employee::findOrFail($employeeId);
            $patronName = "{$employee->lastname}, {$employee->firstname}";
            if ($employee->middle_initial) {
                $patronName .= ' '.$employee->middle_initial.'.';
            }
        } else {
            $student = Student::findOrFail($studentId);
            $patronName = "{$student->lastname}, {$student->firstname}";
        }

        $action = $request->status;
        $isOutbound = in_array($action, ['checked_out', 'room_use'], true);

        $lastLog = BookLog::where('book_id', $book->id)
            ->latest('timestamp')
            ->first();

        if ($isOutbound && $lastLog && $lastLog->status === 'Checked Out') {
            return back()->with('error', 'This book is already on loan (check in first).');
        }

        if ($action === 'checked_in' && (! $lastLog || $lastLog->status !== 'Checked Out')) {
            return back()->with('error', 'This book is already checked in.');
        }

        if ($action === 'checked_in' && $lastLog) {
            if ($employeeId && $lastLog->employee_id) {
                if ($employeeId !== (int) $lastLog->employee_id) {
                    return back()->with('error', 'Patron must match the faculty/staff member who has this book.');
                }
            } elseif ($studentId && $lastLog->student_id) {
                if ($studentId !== (int) $lastLog->student_id) {
                    return back()->with('error', 'Patron must match the student who has this book.');
                }
            }
        }

        if ($isOutbound) {
            $cooldownError = $this->enforceReborrowCooldownOrNull($studentId, $employeeId, (int) $book->id);
            if ($cooldownError) {
                return back()->with('error', $cooldownError);
            }

            $limitMessage = LoanLimits::rejectionMessage($studentId, $employeeId, [$book]);
            if ($limitMessage) {
                return back()->with('error', $limitMessage);
            }
        }

        $newStatus = $isOutbound ? 'Checked Out' : 'Checked In';
        $book->availability = $isOutbound ? 'Borrowed' : 'Available';

        $circulationType = BookLog::CIRCULATION_CHECKOUT;
        if ($isOutbound && $action === 'room_use') {
            $circulationType = BookLog::CIRCULATION_ROOM_USE;
        } elseif (! $isOutbound && $lastLog) {
            $circulationType = $lastLog->circulation_type ?? BookLog::CIRCULATION_CHECKOUT;
        }

        $settings = FineSetting::latest('created_at')->first();

        if ($isOutbound && $action === 'checked_out' && ! $settings) {
            return back()->with('error', 'Fine settings are not configured.');
        }

        $dueDate = null;
        $returnedDate = null;
        $fineIncurred = null;

        $isReserveLoan = $book->isReserve();
        if (! $isOutbound && $lastLog) {
            $isReserveLoan = $lastLog->usesReservePolicy();
        }

        if ($isOutbound && $action === 'checked_out') {
            $loanTerms = LoanDueDate::forCopy(
                Carbon::now('Asia/Manila'),
                $settings,
                $book,
                $request->input('due_date'),
                $request->filled('loan_duration_days') ? (int) $request->loan_duration_days : null,
                true,
            );
            $dueDate = $loanTerms['due_date'];
        }

        if ($action === 'checked_in') {
            $returnedDate = Carbon::now('Asia/Manila');

            if ($lastLog && $lastLog->due_date) {
                $dueDate = $lastLog->due_date;

                $gracePeriod = $settings->graceDaysFor($isReserveLoan);
                $finePerDay = $settings->finePerDayFor($isReserveLoan);
                $maxFine = $settings->maxFineFor($isReserveLoan);

                $overdueDays = $this->calculateOverdueDays(
                    Carbon::parse($dueDate)->startOfDay(),
                    $returnedDate->copy()->startOfDay(),
                    $gracePeriod
                );

                $fineIncurred = $overdueDays * $finePerDay;

                if ($overdueDays > 0) {
                    $rateName = $isReserveLoan ? 'reserve fine' : 'fine';
                    session()->flash('overdue_modal', [
                        'book_title' => $book->title_statement,
                        'patron_name' => $patronName,
                        'days_late' => $overdueDays,
                        'fine' => $fineIncurred,
                        'breakdown' => "{$overdueDays} day(s) × ₱".number_format($finePerDay, 2)." ({$rateName}) = ₱".number_format($fineIncurred, 2),
                    ]);
                }

                if (! is_null($maxFine)) {
                    $fineIncurred = min($fineIncurred, $maxFine);
                }
            }
        }

        BookLog::create([
            'book_id' => $book->id,
            'student_id' => $studentId,
            'employee_id' => $employeeId,
            'patron_name' => $patronName,
            'status' => $newStatus,
            'circulation_type' => $circulationType,
            'is_reserve' => $isReserveLoan,
            'renew_count' => 0,
            'timestamp' => Carbon::now('Asia/Manila'),
            'due_date' => $dueDate,
            'returned_date' => $returnedDate,
            'fine_incurred' => $fineIncurred,
        ]);

        $book->save();

        if ($action === 'room_use') {
            $reserveNote = $isReserveLoan ? ' This reserve copy counts toward the reserve limit and the total loan limit.' : '';

            return back()->with('success', 'Room use recorded (in library only). Remind the patron to check in when finished.'.$reserveNote);
        }

        if ($action === 'checked_out' && $dueDate) {
            $kind = $isReserveLoan ? 'Reserve book checked out' : 'Book checked out';

            return back()->with('success', $kind.'. Due '.$dueDate->format('Y-m-d').'.');
        }

        return back()->with('success', "Book has been {$newStatus} successfully!");
    }

    public function renew(Request $request, Book $book)
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
        ]);

        $studentId = (int) $request->student_id;

        $lastLog = BookLog::query()
            ->where('book_id', $book->id)
            ->latest('timestamp')
            ->first();

        if (! $lastLog || $lastLog->status !== 'Checked Out') {
            return back()->with('error', 'This book is not currently checked out.');
        }

        if ((int) $lastLog->student_id !== $studentId) {
            return back()->with('error', 'Only the patron who borrowed this book can renew it.');
        }

        if (($lastLog->circulation_type ?? BookLog::CIRCULATION_CHECKOUT) !== BookLog::CIRCULATION_CHECKOUT) {
            return back()->with('error', 'Room-use loans cannot be renewed.');
        }

        if (! $lastLog->due_date) {
            return back()->with('error', 'This loan has no due date to renew.');
        }

        $renewCount = (int) ($lastLog->renew_count ?? 0);
        $maxRenewals = Setting::maxRenewalsPerLoan();
        if ($maxRenewals <= 0) {
            return back()->with('error', 'Renewals are turned off.');
        }
        if ($renewCount >= $maxRenewals) {
            return back()->with('error', 'Renewal limit reached (max '.$maxRenewals.' renewals).');
        }

        $settings = FineSetting::latest('created_at')->first();
        $reserveLoan = $lastLog->usesReservePolicy();
        $loanDays = $reserveLoan
            ? (int) ($settings?->reserveLoanDays() ?? 1)
            : (int) ($settings->loan_duration_days ?? 7);

        $base = Carbon::parse($lastLog->due_date, 'Asia/Manila');
        $newDue = $this->addBusinessDays($base, $loanDays);

        $lastLog->due_date = $newDue;
        $lastLog->renew_count = $renewCount + 1;
        $lastLog->last_renewed_at = Carbon::now('Asia/Manila');
        $lastLog->save();

        $renewLabel = $reserveLoan ? 'Reserve loan renewed' : 'Loan renewed';

        return back()->with('success', $renewLabel.'. New due date: '.$newDue->format('Y-m-d').'. ('.$lastLog->renew_count.'/'.$maxRenewals.' renewals used)');
    }

    protected function patronDisplayLabel(Student $s): string
    {
        $label = "{$s->lastname}, {$s->firstname}";
        if ($s->id_number) {
            $label .= " ({$s->id_number})";
        }

        return $label;
    }

    protected function employeeDisplayLabel(Employee $e): string
    {
        $label = "{$e->lastname}, {$e->firstname}";
        if ($e->middle_initial) {
            $label .= ' '.$e->middle_initial.'.';
        }
        if ($e->employee_id) {
            $label .= " ({$e->employee_id})";
        }

        return $label.' · Staff';
    }

    /**
     * @return list<array{id: int, type: string, name: string}>
     */
    protected function patronSuggestionItems(string $search, int $limit = 10): array
    {
        if (trim($search) === '') {
            return [];
        }

        $students = Student::query()
            ->where(function ($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhere('id_number', 'LIKE', "%{$search}%")
                    ->orWhereRaw(
                        'LOWER(CONCAT(firstname, \' \', lastname)) LIKE ?',
                        ['%'.strtolower($search).'%']
                    );
            })
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'type' => 'student',
                'name' => $this->patronDisplayLabel($s),
            ]);

        $employees = Employee::query()
            ->where(function ($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhere('employee_id', 'LIKE', "%{$search}%")
                    ->orWhere('designation', 'LIKE', "%{$search}%")
                    ->orWhereRaw(
                        'LOWER(CONCAT(firstname, \' \', lastname)) LIKE ?',
                        ['%'.strtolower($search).'%']
                    );
            })
            ->limit($limit)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'type' => 'employee',
                'name' => $this->employeeDisplayLabel($e),
            ]);

        return $students->concat($employees)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->take($limit)
            ->values()
            ->all();
    }

    public function bookTitleLogSuggestions(Request $request)
    {
        $search = trim((string) $request->get('query', ''));
        if ($search === '') {
            return response()->json([]);
        }

        $titles = Book::query()
            ->whereHas('logs')
            ->where('title_statement', 'like', '%'.$search.'%')
            ->whereNotNull('title_statement')
            ->orderBy('title_statement')
            ->pluck('title_statement')
            ->unique()
            ->take(12)
            ->values();

        return response()->json($titles->map(fn ($title) => ['title' => $title]));
    }

    public function accessionLogSuggestions(Request $request)
    {
        $search = trim((string) $request->get('query', ''));
        if ($search === '') {
            return response()->json([]);
        }

        $books = Book::query()
            ->whereHas('logs')
            ->whereNotNull('accession_no')
            ->where('accession_no', 'like', '%'.$search.'%')
            ->orderBy('accession_no')
            ->limit(12)
            ->get(['accession_no', 'title_statement']);

        return response()->json($books->map(fn (Book $book) => [
            'accession_no' => $book->accession_no,
            'label' => trim($book->accession_no.($book->title_statement ? ' — '.$book->title_statement : '')),
        ]));
    }

    public function patronSuggestions(Request $request)
    {
        $search = trim((string) $request->get('query', ''));

        return response()->json($this->patronSuggestionItems($search));
    }

    public function bookSuggestions(Request $request)
    {
        $search = $request->get('query', '');

        $books = Book::whereNull('archived_at')->where(function ($q) use ($search) {
            $q->where('title_statement', 'LIKE', "%{$search}%")
                ->orWhere('main_author', 'LIKE', "%{$search}%")
                ->orWhere('accession_no', 'LIKE', "%{$search}%")
                ->orWhere('rfid', 'LIKE', "%{$search}%")
                ->orWhere('barcode', 'LIKE', "%{$search}%");
        })
            ->limit(10)
            ->get();

        return response()->json(
            $books->map(function ($b) {
                $lastCheckout = BookLog::with(['student', 'employee'])
                    ->where('book_id', $b->id)
                    ->where('status', 'Checked Out')
                    ->latest('timestamp')
                    ->first();

                return [
                    'id' => $b->id,
                    'title' => $b->title_statement,
                    'author' => $b->main_author,
                    'accession_no' => $b->accession_no,
                    'barcode' => $b->barcode,
                    'rfid' => $b->rfid,
                    'copy_identifier' => $b->copyIdentifierForCirculation(),
                    'copy_identifier_summary' => $b->copyIdentifierSummary(),
                    'availability' => $b->availability,
                    'section' => $b->section,
                    'is_reserve' => $b->isReserve(),
                    'last_student_id' => $lastCheckout?->student_id,
                    'last_employee_id' => $lastCheckout?->employee_id,
                    'last_patron' => $lastCheckout ? $lastCheckout->patronLabel() : null,
                    'last_circulation_type' => $lastCheckout?->circulation_type,
                ];
            })
        );
    }
}
