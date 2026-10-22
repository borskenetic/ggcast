/* =========================================
   GLOBAL CART SYSTEM (SHARED)
========================================= */

// Use window.cart globally so all pages share the same cart
window.cart = window.cart || JSON.parse(localStorage.getItem('borrowCart')) || [];

/* =========================================
   TOAST (ALWAYS EXISTS)
========================================= */

function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');

    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-card toast-${type}`;
    toast.style.background = '#222';
    toast.style.color = '#fff';
    toast.style.padding = '12px 16px';
    toast.style.marginBottom = '10px';
    toast.style.borderRadius = '8px';
    toast.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';
    toast.style.minWidth = '200px';

    toast.innerHTML = `
        <span>${message}</span>
        <span style="float:right; cursor:pointer; margin-left:10px;"
              onclick="this.parentElement.remove()">×</span>
    `;

    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

/* =========================================
   UTILITIES
========================================= */

function saveCart() {
    localStorage.setItem('borrowCart', JSON.stringify(window.cart));
}

function updateCartUI() {
    const countEls = document.querySelectorAll('#cartCount');
    countEls.forEach(el => el.textContent = window.cart.length);
    saveCart();
}

/* =========================================
   ADD TO CART (Unified)
========================================= */

function cartLimit() {
    const max = Number(window.MAX_LOANS);
    return Number.isFinite(max) && max > 0 ? max : 5;
}

function reserveCartLimit() {
    const max = Number(window.MAX_RESERVE_LOANS);
    return Number.isFinite(max) && max >= 0 ? max : 1;
}

function isReserveCartItem(book) {
    return !!(book && (book.reserve || book.is_reserve));
}

function addToCart() {
    if (!window.selectedBook || !window.selectedBook.id) {
        showToast("No book selected.", "error");
        return;
    }

    if (window.cart.length >= cartLimit()) {
        showToast("Maximum of " + cartLimit() + " books allowed. Reserve books count toward this total.", "error");
        return;
    }

    const addingReserve = !!(window.selectedBook.reserve || window.selectedBook.is_reserve);
    if (addingReserve && window.cart.filter(isReserveCartItem).length >= reserveCartLimit()) {
        const cap = reserveCartLimit();
        showToast("You can borrow at most " + cap + " reserve book" + (cap === 1 ? "" : "s") + ". That book also counts toward your total limit.", "error");
        return;
    }

    const newId = Number(window.selectedBook.id);
    if (window.cart.some(item => Number(item.id) === newId)) {
        showToast("Book already in cart.", "info");
        return;
    }

    window.cart.push({
        id: newId,
        title: window.selectedBook.title,
        author: window.selectedBook.author || '',
        reserve: addingReserve
    });

    updateCartUI();
    showToast(addingReserve ? "Reserve book added to cart." : "Added to cart.", "success");
}

/* =========================================
   UNIVERSAL HELPER FOR COPIES.BLADE
========================================= */

function selectBookAndAddToCart(id, title, author = '', reserve = false) {
    window.selectedBook = { id: Number(id), title, author, reserve: !!reserve };
    addToCart();
}

/* =========================================
   COPIES PAGE — direct self check-out (one copy)
========================================= */

function openStudentCheckout(copyId) {
    const hidden = document.getElementById('selectedCopyId');
    const input = document.getElementById('copyStudentId');
    const err = document.getElementById('copyError');
    const el = document.getElementById('studentCheckoutModal');
    if (!hidden || !input || !el) return;

    hidden.value = String(copyId);
    const row = document.querySelector('tr[data-copy-id="' + copyId + '"]');
    window.selectedBook = {
        id: Number(copyId),
        reserve: row?.getAttribute('data-reserve') === '1',
        title: '',
        author: ''
    };
    input.value = '';
    if (err) {
        err.textContent = '';
        err.classList.add('d-none');
    }

    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(el).show();
    }
}

function confirmCopyCheckout() {
    const copyId = parseInt(document.getElementById('selectedCopyId')?.value, 10);
    const studentId = document.getElementById('copyStudentId')?.value.trim();
    const err = document.getElementById('copyError');

    if (!studentId) {
        if (err) {
            err.textContent = 'Please enter your Student ID.';
            err.classList.remove('d-none');
        }
        showToast('Please enter your Student ID.', 'error');
        return;
    }

    if (!window.CHECKOUT_URL || !window.CSRF_TOKEN) {
        showToast('Checkout is not configured on this page.', 'error');
        return;
    }

    const payload = {
        student_id: studentId,
        book_id: copyId,
    };

    fetch(window.CHECKOUT_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.success) {
                    showToast('Checkout successful!', 'success');
                    const modalEl = document.getElementById('studentCheckoutModal');
                    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    }
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    const msg = data.message || 'Checkout failed.';
                    if (err) {
                        err.textContent = msg;
                        err.classList.remove('d-none');
                    }
                    showToast(msg, 'error');
                }
            })
            .catch(() => showToast('Server error occurred.', 'error'));
}

/* =========================================
   REMOVE
========================================= */

function removeFromCart(index) {
    window.cart.splice(index, 1);
    updateCartUI();
    openCartModal();
}

/* =========================================
   CART MODAL
========================================= */

function openCartModal() {
    const list = document.getElementById('cartList');
    const emptyState = document.getElementById('emptyCart');
    const totalEl = document.getElementById('cartTotal');
    const modal = document.getElementById('cartModal');

    if (!list || !modal) return;

    list.innerHTML = '';

    if (window.cart.length === 0) {
        if (emptyState) emptyState.style.display = 'block';
    } else {
        if (emptyState) emptyState.style.display = 'none';

        window.cart.forEach((book, index) => {
            const li = document.createElement('li');
            li.className = "cart-item";

            const reserveBadge = isReserveCartItem(book)
                ? '<span class="badge text-bg-warning ms-1">Reserve</span>'
                : '';
            li.innerHTML = `
                <div>
                    <strong>${book.title}</strong>${reserveBadge}<br>
                    <small>${book.author ?? ''}</small>
                </div>
                <button class="remove-btn" onclick="removeFromCart(${index})">
                    Remove
                </button>
            `;

            list.appendChild(li);
        });
    }

    if (totalEl) totalEl.textContent = window.cart.length;
    modal.style.display = 'flex';
}

function closeCartModal() {
    const modal = document.getElementById('cartModal');
    if (modal) modal.style.display = 'none';
}

/* =========================================
   INIT
========================================= */

document.addEventListener("DOMContentLoaded", function () {
    updateCartUI();
});
