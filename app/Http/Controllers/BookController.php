<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Models\Ebook;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\BookMarcField;
use App\Models\CatalogFramework;
use App\Services\BookMarcDisplay;
use App\Support\PublicStoragePublisher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class BookController extends Controller
{
    public function __construct(protected BookMarcDisplay $marcDisplay) {}

    /**
     * Fallback default when no borrow-limit setting exists. Live limits are in {@see Setting}.
     * Enforced in {@see \App\Http\Controllers\BookLogController} and {@see \App\Http\Controllers\CheckoutController}.
     */
    public const MAX_CONCURRENT_BOOK_LOANS_PER_STUDENT = 3;

    /** Max number of renewals allowed per loan. */
    public const MAX_RENEWALS_PER_LOAN = 3;

    /** Cooldown (in days) before the same patron can borrow the same book again after return. */
    public const REBORROW_COOLDOWN_DAYS = 7;

   protected function applyBookSearch($query, ?string $search)
   {
       $search = is_string($search) ? trim($search) : '';
       if ($search === '') {
           return $query;
       }

       // Multi-keyword search: all tokens must match somewhere.
       $tokens = preg_split('/\s+/', $search) ?: [];
       $tokens = array_values(array_filter(array_map('trim', $tokens)));

       foreach ($tokens as $token) {
           $like = "%{$token}%";
           $query->where(function ($q) use ($like, $token) {
               $q->where('title_statement', 'like', $like)
                   ->orWhere('main_author', 'like', $like)
                   ->orWhere('title_author', 'like', $like)
                   ->orWhere('control_no', 'like', $like)
                   ->orWhere('isbn', 'like', $like)
                   ->orWhere('publisher', 'like', $like)
                   ->orWhere('pub_place', 'like', $like)
                   ->orWhere('pub_year', 'like', $like)
                   ->orWhere('edition', 'like', $like)
                   ->orWhere('call_number', 'like', $like)
                   ->orWhere('accession_no', 'like', $like)
                   ->orWhere('barcode', 'like', $like)
                   ->orWhere('rfid', 'like', $like)
                   ->orWhere('availability', 'like', $like)
                   ->orWhere('content_type', 'like', $like)
                   ->orWhere('media_type', 'like', $like)
                   ->orWhere('carrier_type', 'like', $like)
                   ->orWhere('library_name', 'like', $like)
                   ->orWhere('section', 'like', $like)
                   ->orWhere('course', 'like', $like)
                   ->orWhere('curriculum', 'like', $like)
                   ->orWhere('year', 'like', $like)
                   ->orWhere('series_title', 'like', $like)
                   ->orWhere('subject_topic', 'like', $like)
                   ->orWhere('subject_form', 'like', $like)
                   ->orWhere('genre', 'like', $like)
                   ->orWhere('general_note', 'like', $like)
                   ->orWhere('bibliography_note', 'like', $like)
                   ->orWhere('source_vendor', 'like', $like)
                   ->orWhere('source_date', 'like', $like);

               // Allow searching by program name/code via pivot
               $q->orWhereHas('programs', function ($p) use ($token) {
                   $p->where('programs.program_name', 'like', "%{$token}%")
                       ->orWhere('programs.program_code', 'like', "%{$token}%");
               });
           });
       }

       return $query;
   }
   protected function booksFramework()
   {
       return $this->marcDisplay->booksFramework();
   }

   /** Strip empty selects so `exists:programs,id` does not run on "". */
   protected function normalizeProgramIdsOnRequest(Request $request): void
   {
       $raw = $request->input('program_ids', []);
       if (! is_array($raw)) {
           $raw = [];
       }
       $ids = array_values(array_unique(array_filter(array_map(static function ($v) {
           $i = (int) $v;

           return $i > 0 ? $i : null;
       }, $raw))));
       $request->merge(['program_ids' => $ids]);
   }

   protected function marcValuesForBook(Book $book, $frameworkFields = null): array
   {
       return $this->marcDisplay->marcValuesForBook($book, $frameworkFields);
   }

   protected function extractMarcPayload(Request $request): array
   {
       $marc = $request->input('marc', []);
       return is_array($marc) ? $marc : [];
   }

   protected function normalizeMarcValues(array $marc, string $tag, ?string $subfield): array
   {
       $subKey = $subfield ?? '_';
       $vals = $marc[$tag][$subKey] ?? [];
       if (! is_array($vals)) {
           $vals = [$vals];
       }
       $vals = array_values(array_filter(array_map(static function ($v) {
           $v = is_string($v) ? trim($v) : $v;
           return $v === '' ? null : $v;
       }, $vals)));
       return $vals;
   }

   protected function saveMarcFieldsForBook(Book $book, $framework, array $marc): void
   {
       if (! $framework) {
           return;
       }

       foreach ($framework->fields as $ff) {
           $mf = $ff->marcField;
           if (! $mf) continue;

           $values = $this->normalizeMarcValues($marc, $mf->tag, $mf->subfield);

           if ($ff->required && count($values) === 0) {
               $subKey = $mf->subfield ?? '_';
               throw ValidationException::withMessages([
                   "marc.{$mf->tag}.{$subKey}" => ["{$mf->tag}".($mf->subfield ? " ‡{$mf->subfield}" : '')." is required."],
               ]);
           }

           BookMarcField::where('book_id', $book->id)
               ->where('tag', $mf->tag)
               ->where(function ($q) use ($mf) {
                   if ($mf->subfield === null) {
                       $q->whereNull('subfield');
                   } else {
                       $q->where('subfield', $mf->subfield);
                   }
               })
               ->delete();

           foreach ($values as $i => $val) {
               BookMarcField::create([
                   'book_id' => $book->id,
                   'tag' => $mf->tag,
                   'subfield' => $mf->subfield,
                   'occurrence' => $i,
                   'value' => $val,
               ]);
           }

           if ($ff->book_column) {
               $book->{$ff->book_column} = $values[0] ?? null;
           }
       }

       $book->save();
   }

   /**
    * @return array<string, array<string, array<int, string>>>
    */
   protected function stripCopyIdentifiersFromMarc(array $marc): array
   {
       foreach (config('catalog.copy_unique_marc', []) as $def) {
           $tag = $def['tag'];
           $subKey = ($def['subfield'] ?? null) ?? '_';
           unset($marc[$tag][$subKey]);
           if (isset($marc[$tag]) && $marc[$tag] === []) {
               unset($marc[$tag]);
           }
       }

       return $marc;
   }

   /**
    * @param  array<string, mixed>  $copy
    * @return array<string, array<string, array<int, string>>>
    */
   protected function applyCopyIdentifiersToMarc(array $marc, array $copy): array
   {
       foreach (config('catalog.copy_unique_marc', []) as $def) {
           $column = $def['book_column'];
           $val = trim((string) ($copy[$column] ?? ''));
           if ($val === '') {
               continue;
           }
           $tag = $def['tag'];
           $subKey = ($def['subfield'] ?? null) ?? '_';
           $marc[$tag][$subKey][0] = $val;
       }

       return $marc;
   }

   protected function validateCopyRows(Request $request): void
   {
       $copies = $request->input('copies', []);
       if (! is_array($copies) || count($copies) === 0) {
           throw ValidationException::withMessages([
               'copies' => ['Add at least one copy (accession and/or RFID).'],
           ]);
       }

       $accessions = [];
       $rfids = [];
       $errors = [];

       foreach ($copies as $i => $copy) {
           if (! is_array($copy)) {
               continue;
           }
           $acc = trim((string) ($copy['accession_no'] ?? ''));
           $rfid = trim((string) ($copy['rfid'] ?? ''));

           if ($acc === '' && $rfid === '') {
               $errors["copies.{$i}.accession_no"] = ['Each copy needs an accession number and/or RFID.'];
               continue;
           }

           if ($acc !== '') {
               if (in_array($acc, $accessions, true)) {
                   $errors["copies.{$i}.accession_no"] = ['Duplicate accession in this batch.'];
               } elseif (Book::withTrashed()->where('accession_no', $acc)->exists()) {
                   $errors["copies.{$i}.accession_no"] = ['Accession already exists in the catalog.'];
               } else {
                   $accessions[] = $acc;
               }
           }

           if ($rfid !== '') {
               if (in_array($rfid, $rfids, true)) {
                   $errors["copies.{$i}.rfid"] = ['Duplicate RFID in this batch.'];
               } elseif (Book::withTrashed()->where('rfid', $rfid)->exists()) {
                   $errors["copies.{$i}.rfid"] = ['RFID already exists in the catalog.'];
               } else {
                   $rfids[] = $rfid;
               }
           }
       }

       if ($errors !== []) {
           throw ValidationException::withMessages($errors);
       }
   }

   /**
    * @param  array<string, array<string, array<int, string>>>  $marc
    */
   protected function createAdditionalCopiesFromBook(Book $sourceBook, Request $request, $framework, array $marc): int
   {
       $baseMarc = $this->stripCopyIdentifiersFromMarc($marc);
       $programIds = $sourceBook->load('programs')->programs->pluck('id')->all();

       $shared = [
           'availability' => 'Available',
           'year' => $sourceBook->year,
           'course' => $sourceBook->course,
           'curriculum' => $sourceBook->curriculum,
           'cover_image' => $sourceBook->cover_image,
       ];

       $created = 0;
       foreach ($request->input('copies', []) as $copy) {
           if (! is_array($copy)) {
               continue;
           }
           $acc = trim((string) ($copy['accession_no'] ?? ''));
           $rfid = trim((string) ($copy['rfid'] ?? ''));
           if ($acc === '' && $rfid === '') {
               continue;
           }

           $book = Book::create($shared);
           $copyMarc = $this->applyCopyIdentifiersToMarc($baseMarc, $copy);
           $this->saveMarcFieldsForBook($book, $framework, $copyMarc);
           $this->assertCopyUniqueOnBook($book);

           if ($programIds !== []) {
               $book->programs()->attach($programIds);
           }

           $created++;
       }

       if ($created === 0) {
           throw ValidationException::withMessages([
               'copies' => ['Add at least one copy with an accession number and/or RFID.'],
           ]);
       }

       return $created;
   }

   protected function assertCopyUniqueOnBook(Book $book): void
   {
       if ($book->barcode && Book::withTrashed()->where('barcode', $book->barcode)->where('id', '!=', $book->id)->exists()) {
           throw ValidationException::withMessages(['marc.876.p' => ['Barcode must be unique.']]);
       }
       if ($book->rfid && Book::withTrashed()->where('rfid', $book->rfid)->where('id', '!=', $book->id)->exists()) {
           throw ValidationException::withMessages(['marc.999.r' => ['RFID must be unique.']]);
       }
       if ($book->accession_no && Book::withTrashed()->where('accession_no', $book->accession_no)->where('id', '!=', $book->id)->exists()) {
           throw ValidationException::withMessages(['copies' => ['Accession '.$book->accession_no.' already exists in the catalog.']]);
       }
   }

   public function index(Request $request)
    {
        // --- Get programs for filter dropdown ---
        $programs = Program::orderBy('program_name')->get();

        $hasActiveQuery = $request->boolean('show_all')
            || $request->filled('search')
            || $request->filled('program')
            || ($request->filled('year_filter') && $request->filled('year1'))
            || ($request->has('status') && in_array($request->status, ['Available', 'Borrowed'], true));

        if (! $hasActiveQuery) {
            $books = new LengthAwarePaginator([], 0, 10, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
            $courses = collect();
            $years = collect();

            return view('books.index', compact('books', 'programs', 'courses', 'years', 'hasActiveQuery'));
        }
    
        // --- Base filtered query ---
        $filteredQuery = Book::query()->whereNull('archived_at');
    
        // Status filter
        if ($request->has('status') && in_array($request->status, ['Available', 'Borrowed'])) {
            $filteredQuery->where('availability', $request->status);
        }
    
        // Program filter
        if ($request->filled('program')) {
            $filteredQuery->whereHas('programs', function ($q) use ($request) {
                $q->where('programs.id', $request->program);
            });
        }
    
        // Year filter
        if ($request->filled('year_filter') && $request->filled('year1')) {
            $year1 = (int) $request->year1;
            $year2 = (int) $request->year2;
    
            switch ($request->year_filter) {
                case 'exact':
                    $filteredQuery->where('pub_year', $year1);
                    break;
                case 'before':
                    $filteredQuery->where('pub_year', '<=', $year1);
                    break;
                case 'after':
                    $filteredQuery->where('pub_year', '>=', $year1);
                    break;
                case 'between':
                    if ($request->filled('year2')) {
                        $filteredQuery->whereBetween('pub_year', [$year1, $year2]);
                    }
                    break;
            }
        }
    
        // Search (multi-field, multi-keyword)
        $this->applyBookSearch($filteredQuery, $request->input('search'));
    
        // --- Dynamic dropdowns for course/year ---
        $courses = Book::whereNull('archived_at')
            ->when($request->program, fn($q) => $q->whereHas('programs', fn($p) => $p->where('program_name', $request->program)))
            ->select('course')->distinct()->orderBy('course')->pluck('course');
    
        $years = Book::whereNull('archived_at')
            ->when($request->program, fn($q) => $q->whereHas('programs', fn($p) => $p->where('program_name', $request->program)))
            ->when($request->course, fn($q) => $q->where('course', $request->course))
            ->select('year')->distinct()->orderBy('year')->pluck('year');
    
        // --- Aggregate on filtered query ---
        $books = DB::table(DB::raw("({$filteredQuery->toSql()}) as sub"))
            ->mergeBindings($filteredQuery->getQuery())
            ->select(
                'main_author',
                'title_statement',
                'pub_year',
                'content_type', // add this
                DB::raw('COUNT(*) as copies'),
                DB::raw('MIN(id) as sample_id')
            )
            ->groupBy('main_author', 'title_statement', 'pub_year', 'content_type') // add content_type here
            ->orderBy('title_statement')
            ->paginate(10)
            ->withQueryString();

    
        return view('books.index', compact('books', 'programs', 'courses', 'years', 'hasActiveQuery'));
    }
    
    public function viewCopies(Request $request)
    {
        // Validate that nullable params exist
        if (!$request->filled('title') || !$request->filled('author') || !$request->filled('year')) {
            abort(404, 'Missing book group information.');
        }
    
        $title = $request->title;
        $author = $request->author;
        $year = $request->year;
    
        // Get all copies matching the same group
        $copies = Book::whereNull('archived_at')
            ->where('title_statement', $title)
            ->where('main_author', $author)
            ->where('pub_year', $year)
            ->orderBy('accession_no', 'asc')
            ->paginate(10)
            ->withQueryString(); // Keep URL parameters when switching pages
    
        return view('books.copies', compact('copies', 'title', 'author', 'year'));
    }

    /**
     * Public OPAC JSON for grouped title: holdings list, description fields, optional MARC lines.
     */
    public function opacBookDetails(Book $book)
    {
        if ($book->archived_at !== null) {
            abort(404);
        }

        $copies = Book::query()
            ->whereNull('archived_at')
            ->where('title_statement', $book->title_statement)
            ->where('main_author', $book->main_author)
            ->where('pub_year', $book->pub_year)
            ->orderBy('accession_no')
            ->get([
                'id',
                'accession_no',
                'call_number',
                'volume',
                'barcode',
                'rfid',
                'availability',
                'course',
                'section',
                'library_name',
                'content_type',
                'title_statement',
                'main_author',
                'pub_year',
                'barcode',
                'rfid',
            ]);

        $physicalParts = array_values(array_filter([
            $book->pages ? trim((string) $book->pages).' p.' : null,
            $book->illustrations ? trim((string) $book->illustrations) : null,
            $book->size ? trim((string) $book->size).' cm' : null,
        ]));
        $physicalDesc = $physicalParts !== [] ? implode(' ', $physicalParts) : null;

        $published = trim(implode(' ', array_filter([
            $book->pub_place,
            $book->publisher,
            $book->pub_year !== null && $book->pub_year !== '' ? (string) $book->pub_year : null,
        ])));

        $copyIds = $copies->pluck('id')->all();

        $fullBooks = $copyIds === []
            ? collect()
            : Book::query()
                ->whereNull('archived_at')
                ->whereIn('id', $copyIds)
                ->orderBy('accession_no')
                ->get();

        $rep = $fullBooks->firstWhere('id', $book->id) ?? $fullBooks->first() ?? $book;
        $rep->loadMissing('programs');
        $fullBooks->loadMissing('programs');

        $marcViewRows = $this->opacMarcViewRowsForGroupedTitle($rep, $fullBooks);

        return response()->json([
            'group' => [
                'title' => $book->title_statement,
                'author' => $book->main_author,
                'year' => $book->pub_year,
            ],
            'description' => [
                'main_author' => $book->main_author,
                'title' => $book->title_statement,
                'format' => $book->content_type,
                'edition' => $book->edition,
                'published' => $published !== '' ? $published : null,
                'isbn' => $book->isbn,
                'general_note' => $book->general_note,
                'physical_description' => $physicalDesc,
                'bibliography' => $book->bibliography_note,
                'subject_topic' => $book->subject_topic,
                'subject_form' => $book->subject_form,
                'genre' => $book->genre,
                'series' => $book->series_title,
            ],
            'copies' => $copies->map(function (Book $c) {
                $statusLabel = match ($c->availability) {
                    'Available' => 'On-Shelf',
                    'Borrowed' => 'Checked out',
                    default => $c->availability ?? '—',
                };

                return [
                    'id' => $c->id,
                    'accession_no' => $c->accession_no,
                    'call_number' => $c->call_number,
                    'volume' => $c->volume,
                    'copy_no' => null,
                    'collection' => $c->course,
                    'shelving_location' => trim(implode(' — ', array_filter([$c->library_name, $c->section]))),
                    'circulation_type' => 'Regular circulation',
                    'circulation_status' => $statusLabel,
                    'availability' => $c->availability,
                    'barcode' => $c->barcode,
                    'rfid' => $c->rfid,
                ];
            })->values(),
            'marc_view_rows' => $marcViewRows,
        ]);
    }

    /**
     * MARC-style rows aligned with {@see \App\Http\Controllers\BookController::show} / books.show — only fields
     * that are non-empty on the representative copy and identical on every copy in the group.
     *
     * @param  \Illuminate\Support\Collection<int, Book>  $fullBooks
     * @return list<array{label: string, value: string}>
     */
    protected function opacMarcViewRowsForGroupedTitle(Book $rep, $fullBooks): array
    {
        return $this->marcDisplay->opacRowsForGroupedTitle($rep, $fullBooks);
    }

    public function viewCopiesStaff(Request $request)
    {
        if (!$request->filled('title') || !$request->filled('author') || !$request->filled('year')) {
            abort(404, 'Missing book group information.');
        }

        $title = $request->title;
        $author = $request->author;
        $year = $request->year;

        $copies = Book::whereNull('archived_at')
            ->where('title_statement', $title)
            ->where('main_author', $author)
            ->where('pub_year', $year)
            ->orderBy('accession_no', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('books.copies_staff', compact('copies', 'title', 'author', 'year'));
    }

    public function landingPage(Request $request)
    {
        $viewMode = (string) $request->input('view', 'books'); // 'books' | 'ebooks'
        $viewMode = in_array($viewMode, ['books', 'ebooks'], true) ? $viewMode : 'books';

        $searchActive =
            $viewMode === 'ebooks' ||
            ($request->filled('search') && trim((string) $request->input('search')) !== '');

        // ----------------------
        // 1) Carousel (recent rows, always unfiltered) + grouped stats (same as OPAC)
        // ----------------------
        // 1) Carousel: one card per title (title + author + year), newest groups first.
        $carouselGroup = Book::query()
            ->whereNull('archived_at')
            ->select(
                'title_statement',
                'main_author',
                'pub_year',
                DB::raw('COUNT(*) AS copies'),
                DB::raw('MIN(id) AS sample_id'),
                DB::raw("MAX(CASE WHEN availability = 'Available' THEN 1 ELSE 0 END) AS is_available")
            )
            ->groupBy('title_statement', 'main_author', 'pub_year')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit(12);
        
        $carouselGroupRows = DB::table(DB::raw("({$carouselGroup->toSql()}) as grouped"))
            ->mergeBindings($carouselGroup->getQuery())
            ->select('grouped.sample_id', 'grouped.copies', 'grouped.is_available')
            ->get();
        
        $carouselSampleIds = $carouselGroupRows->pluck('sample_id')->all();
        $carouselBooksById = $carouselSampleIds === []
            ? collect()
            : Book::query()->whereIn('id', $carouselSampleIds)->get()->keyBy('id');
        
        $carouselBooks = collect($carouselSampleIds)
            ->map(fn ($id) => $carouselBooksById->get($id))
            ->filter()
            ->values();
        
        $carouselMeta = [];
        foreach ($carouselGroupRows as $row) {
            $carouselMeta[(int) $row->sample_id] = [
                'copies' => (int) $row->copies,
                'is_available' => (int) $row->is_available === 1,
            ];
        }

        $carouselStatLookup = $this->carouselGroupStatsLookup($carouselBooks);
        $carouselMeta = [];
        foreach ($carouselBooks as $cb) {
            $key = $cb->title_statement."\0".$cb->main_author."\0".$cb->pub_year;
            $carouselMeta[$cb->id] = $carouselStatLookup[$key] ?? [
                'copies' => 1,
                'is_available' => $cb->availability === 'Available',
            ];
        }

        $ebooks = null;

        if ($viewMode === 'ebooks') {
            $q = Ebook::query();
            if ($request->filled('search')) {
                $term = trim((string) $request->input('search'));
                $q->where(function ($w) use ($term) {
                    $like = '%'.$term.'%';
                    $w->where('title', 'like', $like)
                        ->orWhere('author', 'like', $like)
                        ->orWhere('publisher', 'like', $like)
                        ->orWhere('source', 'like', $like)
                        ->orWhere('publication_year', 'like', $like);
                });
            }

            $ebooks = $q->orderBy('title')->paginate(20)->withQueryString();

            // Keep `$books` as empty paginator to avoid blade errors on counts.
            $perPage = 20;
            $currentPage = max(1, (int) $request->input('page', 1));
            $books = new LengthAwarePaginator([], 0, $perPage, $currentPage, [
                'path' => $request->url(),
                'pageName' => 'page',
            ]);
            $books->withQueryString();
        } elseif (! $searchActive) {
            $perPage = 20;
            $currentPage = max(1, (int) $request->input('page', 1));
            $books = new LengthAwarePaginator([], 0, $perPage, $currentPage, [
                'path' => $request->url(),
                'pageName' => 'page',
            ]);
            $books->withQueryString();
        } else {
            // ----------------------
            // 2) Base Eloquent query (filters + search) — only after a non-empty search
            // ----------------------
            $query = Book::query()->whereNull('archived_at');

            if ($request->filled('course') && $request->course !== 'all') {
                $query->where('course', $request->course);
            }

            if ($request->filled('subject_topic') && $request->subject_topic !== 'All') {
                $query->where('subject_topic', $request->subject_topic);
            }

            if ($request->filled('genre') && $request->genre !== 'All') {
                $query->where('genre', $request->genre);
            }

            if ($request->filled('content_type') && $request->content_type !== 'All') {
                $query->where('content_type', $request->content_type);
            }

            if ($request->filled('section') && $request->section !== 'All') {
                $query->where('section', $request->section);
            }

            $this->applyBookSearch($query, $request->input('search'));

            // ----------------------
            // 3) Grouped subquery (count copies, get a sample id, detect availability)
            // ----------------------
            $grouped = $query->getQuery()->clone()
                ->select(
                    'title_statement',
                    'main_author',
                    'pub_year',
                    DB::raw('COUNT(*) AS copies'),
                    DB::raw('MIN(id) AS sample_id'),
                    DB::raw("MAX(CASE WHEN availability = 'Available' THEN 1 ELSE 0 END) AS is_available")
                )
                ->groupBy('title_statement', 'main_author', 'pub_year');

            // ----------------------
            // 4) Join grouped subquery back to books to grab sample fields
            // ----------------------
            $books = DB::table(DB::raw("({$grouped->toSql()}) as grouped"))
                ->mergeBindings($grouped)
                ->join('books', 'books.id', '=', 'grouped.sample_id')
                ->select(
                    'grouped.title_statement',
                    'grouped.main_author',
                    'grouped.pub_year',
                    'grouped.copies',
                    'grouped.sample_id as id',
                    'grouped.is_available',
                    'books.call_number',
                    'books.general_note',
                    'books.cover_image',
                    'books.rfid',
                    'books.barcode',
                    'books.content_type',
                    'books.fixed_length_data',
                    'books.library_name',
                    'books.course'
                )
                ->orderBy('grouped.title_statement')
                ->paginate(20)
                ->withQueryString();
        }
    
        // ----------------------
        // 5) Distinct dropdown sources (always from full table)
        // ----------------------
        $subjectTopics = Book::select('subject_topic')
            ->distinct()
            ->whereNull('archived_at')
            ->whereNotNull('subject_topic')
            ->orderBy('subject_topic')
            ->pluck('subject_topic');
    
        $genres = Book::select('genre')
            ->distinct()
            ->whereNull('archived_at')
            ->whereNotNull('genre')
            ->orderBy('genre')
            ->pluck('genre');
        
        $content_type = Book::select('content_type')
            ->distinct()
            ->whereNull('archived_at')
            ->whereNotNull('content_type')
            ->orderBy('content_type')
            ->pluck('content_type');
            
        $sections = Book::select('section')
            ->distinct()
            ->whereNull('archived_at')
            ->whereNotNull('section')
            ->orderBy('section')
            ->pluck('section');
    
        $courses = Book::select('course')
            ->distinct()
            ->whereNull('archived_at')
            ->whereNotNull('course')
            ->orderBy('course')
            ->pluck('course');
    
        // ----------------------
        // 6) Return view
        // ----------------------
        return view('books.landing', compact(
            'books',
            'ebooks',
            'carouselBooks',
            'carouselMeta',
            'subjectTopics',
            'genres',
            'sections',
            'courses',
            'content_type',
            'searchActive',
            'viewMode'
        ));
    }

    /**
     * Copy count + "any available" per title/author/year for carousel cards (matches OPAC grouping).
     *
     * @param  \Illuminate\Support\Collection<int, Book>  $carouselBooks
     * @return array<string, array{copies: int, is_available: bool}>
     */
    protected function carouselGroupStatsLookup($carouselBooks): array
    {
        if ($carouselBooks->isEmpty()) {
            return [];
        }

        $tuples = $carouselBooks->map(fn (Book $b) => [
            'title_statement' => $b->title_statement,
            'main_author' => $b->main_author,
            'pub_year' => $b->pub_year,
        ])->unique(fn (array $t) => $t['title_statement']."\0".$t['main_author']."\0".$t['pub_year'])
            ->values();

        $query = Book::query()
            ->select('title_statement', 'main_author', 'pub_year')
            ->selectRaw('COUNT(*) as copies')
            ->selectRaw("MAX(CASE WHEN availability = 'Available' THEN 1 ELSE 0 END) as is_available");

        $query->where(function ($outer) use ($tuples) {
            foreach ($tuples as $t) {
                $outer->orWhere(function ($w) use ($t) {
                    $w->where('title_statement', $t['title_statement'])
                        ->where('main_author', $t['main_author'])
                        ->where('pub_year', $t['pub_year']);
                });
            }
        });

        $rows = $query->groupBy('title_statement', 'main_author', 'pub_year')->get();

        $lookup = [];
        foreach ($rows as $row) {
            $k = $row->title_statement."\0".$row->main_author."\0".$row->pub_year;
            $lookup[$k] = [
                'copies' => (int) $row->copies,
                'is_available' => (int) $row->is_available === 1,
            ];
        }

        return $lookup;
    }

    public function destroy(Book $book)
    {
        $book->delete();
        return redirect()->route('book.index')->with('success', 'Book deleted successfully!');
    }

    public function archivedIndex(Request $request)
    {
        $books = Book::query()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->paginate(20)
            ->withQueryString();

        return view('books.archived', compact('books'));
    }

    public function trashIndex(Request $request)
    {
        $books = Book::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return view('books.trash', compact('books'));
    }

    public function archive(Book $book)
    {
        if ($book->archived_at === null) {
            $book->archived_at = Carbon::now();
            $book->save();
        }

        return back()->with('success', 'Book archived.');
    }

    public function unarchive(Book $book)
    {
        if ($book->archived_at !== null) {
            $book->archived_at = null;
            $book->save();
        }

        return back()->with('success', 'Book restored from archive.');
    }

    public function restoreTrashed(int $id)
    {
        $book = Book::onlyTrashed()->findOrFail($id);
        $book->restore();
        return back()->with('success', 'Book restored.');
    }

    public function forceDeleteTrashed(int $id)
    {
        $book = Book::onlyTrashed()->with(['programs', 'marcFields', 'logs'])->findOrFail($id);

        DB::transaction(function () use ($book) {
            $book->programs()->detach();
            $book->marcFields()->delete();
            $book->logs()->delete();
            $book->forceDelete();
        });

        return back()->with('success', 'Book permanently deleted.');
    }

    public function create()
    {
        $programs = Program::orderBy('program_name')->get();

        $framework = $this->booksFramework();
        $frameworkFields = $framework?->fields ?? collect();

        return view('books.create', compact('programs', 'frameworkFields'));
    }

    /**
     * Prospectus courses (program_courses.course_name) for one or more programs — cataloging AJAX.
     */
    public function coursesForPrograms(Request $request)
    {
        $ids = $request->input('program_ids', []);
        if (! is_array($ids)) {
            $ids = array_filter([$ids]);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return response()->json([]);
        }

        $names = ProgramCourse::query()
            ->whereHas('year', static function ($q) use ($ids) {
                $q->whereIn('program_id', $ids);
            })
            ->orderBy('course_name')
            ->pluck('course_name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => mb_strtolower($n))
            ->values();

        return response()->json($names);
    }

    public function store(Request $request)
    {
        $this->normalizeProgramIdsOnRequest($request);

        $multipleCopies = $request->boolean('multiple_copies');

        $request->validate([
            'multiple_copies' => 'nullable|boolean',
            'copies' => $multipleCopies ? 'required|array|min:1' : 'nullable|array',
            'copies.*.accession_no' => 'nullable|string|max:255',
            'copies.*.rfid' => 'nullable|string|max:255',
            'program_ids' => 'nullable|array',
            'program_ids.*' => 'integer|exists:programs,id',
            'year' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:255',
            'curriculum' => 'nullable|string|in:'.implode(',', array_keys(config('catalog.curriculum_options', []))),
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'external_cover_url' => 'nullable|string|max:2048',
            'catalog_source' => 'nullable|string|in:openlibrary,googlebooks',
        ]);

        if ($multipleCopies) {
            $this->validateCopyRows($request);
        }

        $framework = $this->booksFramework();
        $marc = $this->extractMarcPayload($request);
        if ($multipleCopies) {
            $marc = $this->stripCopyIdentifiersFromMarc($marc);
        }

        try {
            $result = DB::transaction(function () use ($request, $framework, $marc, $multipleCopies) {
                $coverPath = $this->resolveCoverPathOnStore($request);

                $shared = [
                    'availability' => 'Available',
                    'year' => $request->year,
                    'course' => $request->course,
                    'curriculum' => $request->curriculum,
                    'cover_image' => $coverPath,
                ];

                if (! $multipleCopies) {
                    $book = Book::create($shared);
                    $this->saveMarcFieldsForBook($book, $framework, $marc);
                    $this->assertCopyUniqueOnBook($book);

                    if (! empty($request->program_ids)) {
                        $book->programs()->attach($request->program_ids);
                    }

                    return ['book' => $book, 'count' => 1];
                }

                $created = [];
                foreach ($request->input('copies', []) as $copy) {
                    if (! is_array($copy)) {
                        continue;
                    }
                    $acc = trim((string) ($copy['accession_no'] ?? ''));
                    $rfid = trim((string) ($copy['rfid'] ?? ''));
                    if ($acc === '' && $rfid === '') {
                        continue;
                    }

                    $book = Book::create($shared);
                    $copyMarc = $this->applyCopyIdentifiersToMarc($marc, $copy);
                    $this->saveMarcFieldsForBook($book, $framework, $copyMarc);
                    $this->assertCopyUniqueOnBook($book);

                    if (! empty($request->program_ids)) {
                        $book->programs()->attach($request->program_ids);
                    }

                    $created[] = $book;
                }

                if (count($created) === 0) {
                    throw ValidationException::withMessages([
                        'copies' => ['Add at least one copy with an accession number and/or RFID.'],
                    ]);
                }

                return ['book' => $created[0], 'count' => count($created)];
            });

            $book = $result['book'];
            $copyCount = $result['count'];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Book store failed: '.$e->getMessage(), ['exception' => $e]);

            return back()
                ->withInput()
                ->with('error', 'Could not save the book: '.$e->getMessage());
        }

        if (in_array($request->input('catalog_source'), ['openlibrary', 'googlebooks'], true)) {
            $returnIsbn = $book->isbn ?: $request->input('openlibrary_return_isbn');
            if ($returnIsbn) {
                $msg = $copyCount > 1
                    ? "{$copyCount} copies saved successfully."
                    : 'Book saved successfully.';

                return redirect()
                    ->route('catalog.copy.openlibrary.search', ['isbn' => $returnIsbn])
                    ->with('success', $msg);
            }
        }

        $msg = $copyCount > 1
            ? "{$copyCount} copies added successfully!"
            : 'Book added successfully!';

        return redirect()->route('book.index')->with('success', $msg);
    }

    protected function resolveCoverPathOnStore(Request $request): ?string
    {
        if ($request->hasFile('cover_image')) {
            Storage::disk('public')->makeDirectory('covers');

            return PublicStoragePublisher::publish(
                $request->file('cover_image')->store('covers', 'public')
            );
        }

        if ($request->filled('external_cover_url')) {
            $url = trim((string) $request->input('external_cover_url'));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                try {
                    $resp = Http::timeout(25)->get($url);
                    if ($resp->successful() && strlen($resp->body()) > 0) {
                        $ext = strtolower((string) pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                            $ext = 'jpg';
                        }
                        $coverPath = 'covers/ol_'.Str::random(12).'.'.$ext;
                        Storage::disk('public')->put($coverPath, $resp->body());

                        return PublicStoragePublisher::publish($coverPath);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('external_cover_url download failed: '.$e->getMessage());
                }
            }
        }

        return null;
    }

    public function show($id)
    {
        $book = Book::with(['programs', 'marcFields'])->findOrFail($id);
        $marcDetailSections = $this->marcDisplay->detailSectionsForBook($book);

        return view('books.show', compact('book', 'marcDetailSections'));
    }

    public function edit($id)
    {
        $book = Book::with(['programs', 'marcFields'])->findOrFail($id);
        $programs = Program::orderBy('program_name')->get();

        $framework = $this->booksFramework();
        $frameworkFields = $framework?->fields ?? collect();
        $marcValues = $this->marcValuesForBook($book, $frameworkFields);

        return view('books.edit', compact('book', 'programs', 'frameworkFields', 'marcValues'));
    }

    public function update(Request $request, $id)
    {
        $book = Book::findOrFail($id);

        $this->normalizeProgramIdsOnRequest($request);

        $addCopies = $request->boolean('add_copies');

        $request->validate([
            'add_copies' => 'nullable|boolean',
            'copies' => $addCopies ? 'required|array|min:1' : 'nullable|array',
            'copies.*.accession_no' => 'nullable|string|max:255',
            'copies.*.rfid' => 'nullable|string|max:255',
            'year' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:255',
            'curriculum' => 'nullable|string|in:'.implode(',', array_keys(config('catalog.curriculum_options', []))),
            'program_ids' => 'nullable|array',
            'program_ids.*' => 'integer|exists:programs,id',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($addCopies) {
            $this->validateCopyRows($request);
        }

        $framework = $this->booksFramework();
        $marc = $this->extractMarcPayload($request);

        $addedCopyCount = DB::transaction(function () use ($request, $book, $framework, $marc, $addCopies) {
            $data = $request->only(['year', 'course', 'curriculum']);

            if ($request->hasFile('cover_image')) {
                Storage::disk('public')->makeDirectory('covers');
                $data['cover_image'] = PublicStoragePublisher::publish(
                    $request->file('cover_image')->store('covers', 'public')
                );
            }

            $book->update($data);
            $this->saveMarcFieldsForBook($book, $framework, $marc);
            $this->assertCopyUniqueOnBook($book);

            if (! empty($request->program_ids)) {
                $book->programs()->sync($request->program_ids);
            } else {
                $book->programs()->detach();
            }

            if (! $addCopies) {
                return 0;
            }

            $book->refresh();

            return $this->createAdditionalCopiesFromBook($book, $request, $framework, $marc);
        });

        $message = $addedCopyCount > 0
            ? "Book updated successfully. {$addedCopyCount} additional ".($addedCopyCount === 1 ? 'copy' : 'copies').' added.'
            : 'Book updated successfully!';

        return redirect()->route('book.index')->with('success', $message);
    }


    public function getYears(Request $request)
    {
        $program = $request->program;
        $years = Book::where('program', $program)
            ->select('year')->distinct()->orderBy('year')->pluck('year');
        return response()->json($years);
    }

    public function getCourses(Request $request)
    {
        $program = $request->program;
        $year = $request->year;
        $courses = Book::where('program', $program)
            ->where('year', $year)
            ->select('course')->distinct()->orderBy('course')->pluck('course');
        return response()->json($courses);
    }

    public function downloadBookReport()
    {
        // Count total books per title
        $booksByTitle = Book::select('title_statement')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('title_statement')
            ->orderBy('title_statement')
            ->get();

        $totalBooks = $booksByTitle->sum('total');

        // Get all subjects grouped by course
        $books = DB::table('books')
            ->select('course', 'title_statement')
            ->groupBy('course', 'title_statement')
            ->orderBy('course')
            ->orderBy('title_statement')
            ->get();

        $groupedBooks = $books->groupBy('course');

        // Pass both variables to the view
        $pdf = Pdf::loadView('pdf.book_report', compact('booksByTitle', 'totalBooks', 'groupedBooks'));

        return $pdf->download('book_report.pdf');
    }
}