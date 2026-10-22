<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Copies</title>

    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/books/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/books/index.css') }}">
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</head>

<body>

<div class="container mt-4">

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('landing') }}" class="btn btn-primary">← Back to OPAC</a>
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Previous page</a>
    </div>

    <h3>
        Copies of: <strong>{{ $title }}</strong><br>
        <small>{{ $author }} — {{ $year }}</small>
    </h3>
    <p class="text-muted small mb-2">Add a specific copy to your cart, then use <strong>Back to OPAC</strong> to browse and check out everything together (cart is saved in this browser).</p>
    @include('partials.reserve_loan_policy_note')

    <table class="table table-bordered table-striped mt-3">
        <thead>
            <tr>
                <th>Accession No</th>
                <th>Barcode</th>
                <th>RFID</th>
                <th>Sublocation</th>
                <th>Availability</th>
                <th>Date Added</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            @foreach($copies as $copy)
            <tr data-copy-id="{{ $copy->id }}" data-reserve="{{ $copy->isReserve() ? '1' : '0' }}">
                <td>{{ $copy->accession_no }}</td>
                <td>{{ $copy->barcode }}</td>
                <td>{{ $copy->rfid }}</td>
                <td>
                    {{ $copy->section ?: '—' }}
                    @if($copy->isReserve())
                        <span class="badge text-bg-warning">Reserve</span>
                    @endif
                </td>

                <td class="{{ $copy->availability === 'Available' ? 'text-success' : 'text-danger' }}">
                    {{ $copy->availability }}
                </td>

                <td>{{ $copy->created_at?->format('Y-m-d') }}</td>

                <td>
                    @if($copy->availability === 'Available')
                        <button
                            type="button"
                            class="btn btn-primary btn-sm"
                            onclick="openStudentCheckout({{ $copy->id }})">
                            Self Check-Out
                        </button>
                        <button
                            type="button"
                            class="btn btn-dark btn-sm btn-add-copy-to-cart"
                            data-copy-id="{{ $copy->id }}"
                            data-title="{{ e($copy->title_statement) }}"
                            data-author="{{ e($author) }}"
                            data-reserve="{{ $copy->isReserve() ? '1' : '0' }}">
                            Add to Cart
                        </button>
                    @else
                        <span class="text-danger">Not Available</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{ $copies->links('pagination::bootstrap-5') }}

</div>

<div class="modal fade" id="studentCheckoutModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-lg">

            <div class="modal-header">
                <h5 class="modal-title">Self Check-Out</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="selectedCopyId" value="">

                <label class="form-label">Student ID</label>
                <input type="text" id="copyStudentId" class="form-control">

                <div id="copyError" class="text-danger mt-2 d-none"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="confirmCopyCheckout()">
                    Confirm Checkout
                </button>
            </div>

        </div>
    </div>
</div>

<div class="modal" id="cartModal">
    <div class="modal-content cart-modal-clean">
        <span class="close" onclick="closeCartModal()">&times;</span>

        <div class="cart-header">
            <h2>Borrow Cart</h2>
            @include('partials.reserve_loan_policy_note')
        </div>

        <div id="cartBody" class="cart-body">
            <ul id="cartList" class="cart-list"></ul>

            <div id="emptyCart" class="empty-cart" style="display:none;">
                Your cart is empty.
            </div>
        </div>

        <div class="cart-footer">
            <div class="cart-count">
                Total Books: <strong id="cartTotal">0</strong>
            </div>

            <button type="button" class="btn btn-dark px-5" onclick="openStudentModalFromCart()">
                Proceed to Checkout
            </button>
        </div>
    </div>
</div>

<button id="cartButton" type="button" onclick="openCartModal()"
    style="position:fixed; bottom:30px; right:30px; z-index:999;
           padding:12px 20px; border-radius:50px;"
    class="btn btn-dark">
    Cart (<span id="cartCount">0</span>)
</button>

<div class="modal" id="studentModal">
    <div class="modal-content">
        <span class="close" onclick="closeStudentModal()">&times;</span>

        <h4>Self Check-Out</h4>

        <div class="mb-3">
            <label for="studentIdInput" class="form-label"><strong>Student ID</strong></label>
            <input type="text" id="studentIdInput" class="form-control" placeholder="Enter your Student ID">
        </div>

        <button type="button" class="btn btn-primary mt-3" onclick="confirmCheckout()">
            Confirm Checkout
        </button>

        <p id="studentError" class="text-danger mt-2" style="display:none;"></p>
    </div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script>
    window.CHECKOUT_URL = "{{ route('checkout.process') }}";
    window.CSRF_TOKEN = "{{ csrf_token() }}";
    window.RECEIPT_PRINTER_NAME = @json(config('branding.receipt_printer', 'POS-80'));
    window.RECEIPT_PRINT_AGENT_URL = @json(config('printing.agent_url', ''));
    window.RECEIPT_PRINT_AGENT_TOKEN = @json(config('printing.agent_token', ''));
    window.LIBRARY_NAME = @json($brand['library_name'] ?? config('branding.library_name', 'Library'));
    @include('partials.loan_policy_vars')
</script>
<script src="{{ asset('js/cart.js') }}"></script>
<script src="{{ asset('js/landings.js') }}"></script>
<script>
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-add-copy-to-cart');
        if (!btn) return;
        e.preventDefault();
        var id = btn.getAttribute('data-copy-id');
        var title = btn.getAttribute('data-title') || '';
        var author = btn.getAttribute('data-author') || '';
        var reserve = btn.getAttribute('data-reserve') === '1';
        if (id && typeof selectBookAndAddToCart === 'function') {
            selectBookAndAddToCart(id, title, author, reserve);
        }
    });
</script>

</body>
</html>
