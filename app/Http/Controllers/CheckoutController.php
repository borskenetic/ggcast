<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookLog;
use App\Models\FineSetting;
use App\Models\ReceiptPrintJob;
use App\Models\Setting;
use App\Models\Student;
use App\Services\ReceiptPrinterService;
use App\Support\LoanDueDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function process(Request $request)
    {
        Log::info('Checkout request data: ', $request->all());

        try {
            $request->validate([
                'student_id' => 'required|string',
                'book_id' => 'nullable|integer',
                'books' => 'nullable|array',
                'books.*.id' => 'required_with:books|integer',
                'due_date' => 'nullable|date|after_or_equal:today',
                'loan_duration_days' => 'nullable|integer|min:1|max:365',
            ]);

            $student = Student::where('id_number', $request->student_id)->first();

            if (! $student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student ID not found.',
                ]);
            }

            $patronLegacyName = "{$student->lastname}, {$student->firstname}";

            $hasOverdue = BookLog::where('status', 'Checked Out')
                ->whereDate('due_date', '<', now())
                ->where(function ($q) use ($student, $patronLegacyName) {
                    $q->where('student_id', $student->id)
                        ->orWhere('patron_name', $patronLegacyName);
                })
                ->exists();

            if ($hasOverdue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkout blocked: student has overdue book(s).',
                ]);
            }

            $bookIds = [];
            if ($request->book_id) {
                $bookIds[] = (int) $request->book_id;
            }
            if ($request->books) {
                foreach ($request->books as $b) {
                    $bookIds[] = (int) $b['id'];
                }
            }

            if ($bookIds === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No books provided.',
                ]);
            }

            $availableIds = [];
            foreach (array_unique($bookIds) as $bookId) {
                $b = Book::find($bookId);
                if ($b && $b->availability === 'Available') {
                    $availableIds[] = (int) $bookId;
                }
            }

            if ($availableIds === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available copies could be checked out.',
                ]);
            }

            $currentLoans = BookLog::countActiveLoansForStudent((int) $student->id);
            $studentMax = Setting::maxLoansForStudents();
            if (Setting::wouldExceedStudentLoanLimit($currentLoans, count($availableIds))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkout blocked: patron may have at most '.$studentMax.' books on loan at a time (including room use).',
                ]);
            }

            $fineSetting = FineSetting::orderBy('created_at', 'desc')->first();

            if (! $fineSetting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fine settings not configured.',
                ]);
            }

            $borrowedAt = Carbon::now('Asia/Manila');
            $loanTerms = LoanDueDate::resolveFromRequest(
                $borrowedAt,
                $fineSetting,
                $request->input('due_date'),
                $request->filled('loan_duration_days') ? (int) $request->loan_duration_days : null,
            );
            $dueDate = $loanTerms['due_date'];
            $processedBooks = [];

            foreach ($availableIds as $bookId) {
                $book = Book::find($bookId);

                if (! $book || $book->availability !== 'Available') {
                    continue;
                }

                $latestReturn = BookLog::query()
                    ->where('student_id', $student->id)
                    ->where('book_id', $book->id)
                    ->where('status', 'Checked In')
                    ->whereNotNull('returned_date')
                    ->orderByDesc('returned_date')
                    ->value('returned_date');

                if ($latestReturn) {
                    $returnedAt = Carbon::parse($latestReturn)->timezone('Asia/Manila');
                    $allowedAt = $returnedAt->copy()->addDays(BookController::REBORROW_COOLDOWN_DAYS);
                    $nowManila = Carbon::now('Asia/Manila');
                    if ($nowManila->lt($allowedAt)) {
                        continue;
                    }
                }

                BookLog::create([
                    'book_id' => $book->id,
                    'student_id' => $student->id,
                    'patron_name' => $patronLegacyName,
                    'status' => 'Checked Out',
                    'circulation_type' => BookLog::CIRCULATION_CHECKOUT,
                    'renew_count' => 0,
                    'timestamp' => $borrowedAt,
                    'due_date' => $dueDate,
                    'fine_incurred' => 0,
                ]);

                $book->update(['availability' => 'Borrowed']);

                $processedBooks[] = [
                    'id' => $book->id,
                    'title' => $book->title_statement,
                    'author' => $book->main_author,
                    'barcode' => $book->barcode,
                    'due_date' => $dueDate->format('Y-m-d'),
                ];
            }

            if ($processedBooks === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available copies could be checked out (some may be blocked by the 1-week re-borrow cooldown).',
                ]);
            }

            $studentPayload = [
                'name' => $patronLegacyName,
                'id_number' => $student->id_number,
                'course' => $student->course,
            ];

            return response()->json([
                'success' => true,
                'student' => $studentPayload,
                'books' => $processedBooks,
                'book' => count($processedBooks) === 1 ? $processedBooks[0] : null,
                'due_date' => $processedBooks[count($processedBooks) - 1]['due_date'] ?? null,
                'receipt_printed' => $this->printCheckoutReceipt($studentPayload, $processedBooks),
            ]);
        } catch (\Exception $e) {
            Log::error('Checkout Exception: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    public function bulk(Request $request)
    {
        $request->validate([
            'student_id' => 'required|string',
            'book_ids' => 'required|array',
            'due_date' => 'nullable|date|after_or_equal:today',
            'loan_duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        $student = Student::where('id_number', $request->student_id)->first();

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
            ]);
        }

        $patronLegacyName = "{$student->lastname}, {$student->firstname}";

        $hasOverdue = BookLog::where('status', 'Checked Out')
            ->whereDate('due_date', '<', now())
            ->where(function ($q) use ($student, $patronLegacyName) {
                $q->where('student_id', $student->id)
                    ->orWhere('patron_name', $patronLegacyName);
            })
            ->exists();

        if ($hasOverdue) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout blocked: student has overdue book(s).',
            ]);
        }

        $fineSetting = FineSetting::latest()->first();
        if (! $fineSetting) {
            return response()->json(['success' => false, 'message' => 'Fine settings not configured.']);
        }

        $availableIds = [];
        foreach ($request->book_ids as $bookId) {
            $book = Book::find($bookId);
            if ($book && $book->availability === 'Available') {
                $availableIds[] = (int) $book->id;
            }
        }

        if ($availableIds === []) {
            return response()->json(['success' => false, 'message' => 'No available copies could be checked out.']);
        }

        $currentLoans = BookLog::countActiveLoansForStudent((int) $student->id);
        $studentMax = Setting::maxLoansForStudents();
        if (Setting::wouldExceedStudentLoanLimit($currentLoans, count($availableIds))) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout blocked: patron may have at most '.$studentMax.' books on loan at a time.',
            ]);
        }

        $borrowedAt = Carbon::now('Asia/Manila');
        $loanTerms = LoanDueDate::resolveFromRequest(
            $borrowedAt,
            $fineSetting,
            $request->input('due_date'),
            $request->filled('loan_duration_days') ? (int) $request->loan_duration_days : null,
        );
        $dueDate = $loanTerms['due_date'];
        $results = [];

        foreach ($availableIds as $bookId) {
            $book = Book::find($bookId);

            if (! $book || $book->availability !== 'Available') {
                continue;
            }

            $latestReturn = BookLog::query()
                ->where('student_id', $student->id)
                ->where('book_id', $book->id)
                ->where('status', 'Checked In')
                ->whereNotNull('returned_date')
                ->orderByDesc('returned_date')
                ->value('returned_date');

            if ($latestReturn) {
                $returnedAt = Carbon::parse($latestReturn)->timezone('Asia/Manila');
                $allowedAt = $returnedAt->copy()->addDays(BookController::REBORROW_COOLDOWN_DAYS);
                $nowManila = Carbon::now('Asia/Manila');
                if ($nowManila->lt($allowedAt)) {
                    continue;
                }
            }

            BookLog::create([
                'book_id' => $book->id,
                'student_id' => $student->id,
                'patron_name' => $patronLegacyName,
                'status' => 'Checked Out',
                'circulation_type' => BookLog::CIRCULATION_CHECKOUT,
                'renew_count' => 0,
                'timestamp' => $borrowedAt,
                'due_date' => $dueDate,
                'fine_incurred' => 0,
            ]);

            $book->update(['availability' => 'Borrowed']);

            $results[] = [
                'id' => $book->id,
                'title' => $book->title_statement,
                'author' => $book->main_author,
                'barcode' => $book->barcode,
                'due_date' => $dueDate->format('Y-m-d'),
            ];
        }

        $studentPayload = [
            'name' => $patronLegacyName,
            'id_number' => $student->id_number,
        ];

        return response()->json([
            'success' => true,
            'student' => $studentPayload,
            'books' => $results,
            'receipt_printed' => $this->printCheckoutReceipt($studentPayload, $results),
        ]);
    }

    /**
     * @param  array{name?: string, id_number?: string}  $student
     * @param  list<array{title?: string, author?: string, barcode?: string, due_date?: string}>  $books
     */
    private function printCheckoutReceipt(array $student, array $books): bool
    {
        $printed = false;

        try {
            $printed = app(ReceiptPrinterService::class)->printCheckout($student, $books);
        } catch (\Throwable $e) {
            Log::warning('Checkout receipt print failed: '.$e->getMessage());
        }

        ReceiptPrintJob::create([
            'payload' => [
                'student' => $student,
                'books' => $books,
            ],
            'printed_at' => $printed ? now() : null,
        ]);

        return $printed;
    }
}
