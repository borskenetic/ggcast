/* =========================================
   GLOBAL STATE
========================================= */

window.selectedBook = null;
window.selectedStudent = null;
window.selectedBooks = null;
window.opacDetailPayload = null;

const track = document.getElementById('carouselTrack');
let scrollAmount = 0;

function goToEBookPage() {
    window.location.href = '/opac?view=ebooks';
}

function opacBookDetailUrl(bookId) {
    const base = window.OPAC_BOOK_DETAIL_BASE || '/opac/api/book/';
    return `${base}${bookId}`;
}

function escHtml(s) {
    if (s === null || s === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

function setOpacTab(name) {
    document.querySelectorAll('.opac-tab').forEach((btn) => {
        const on = btn.getAttribute('data-tab') === name;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll('.opac-tab-panel').forEach((panel) => {
        const id = panel.id;
        const match =
            (name === 'holdings' && id === 'opacTabHoldings') ||
            (name === 'description' && id === 'opacTabDescription') ||
            (name === 'marc' && id === 'opacTabMarc');
        panel.classList.toggle('is-active', match);
    });
}

function bindOpacTabsOnce() {
    const bar = document.querySelector('.opac-tabs');
    if (!bar || bar.dataset.bound === '1') return;
    bar.dataset.bound = '1';
    bar.addEventListener('click', (e) => {
        const btn = e.target.closest('.opac-tab');
        if (!btn) return;
        setOpacTab(btn.getAttribute('data-tab'));
    });
}

function addCopyToCartFromHoldings(copyId, title, author, availability) {
    if (availability !== 'Available') {
        showToast('This copy is not available.', 'error');
        return;
    }
    window.selectedBook = {
        id: Number(copyId),
        title,
        author: author || '',
        availability: 'Available',
        copies: 1,
        pubYear: ''
    };
    if (typeof addToCart === 'function') addToCart();
}

function renderHoldingsTable(copies, group) {
    const tbody = document.getElementById('holdingsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    const title = group?.title || '';
    const author = group?.author || '';

    copies.forEach((c) => {
        const tr = document.createElement('tr');
        tr.dataset.copyId = c.id;
        const cartCell =
            c.availability === 'Available'
                ? '<button type="button" class="btn btn-sm btn-dark opac-add-cart-copy">Add to cart</button>'
                : '<span class="text-muted small">—</span>';
        tr.innerHTML = `
            <td>${escHtml(c.accession_no ?? '—')}</td>
            <td>${escHtml(c.call_number ?? '—')}</td>
            <td>${escHtml(c.volume ?? '—')}</td>
            <td>${escHtml(c.copy_no ?? '—')}</td>
            <td>${escHtml(c.collection ?? '—')}</td>
            <td>${escHtml(c.shelving_location ?? '—')}</td>
            <td>${escHtml(c.circulation_type ?? '—')}</td>
            <td>${escHtml(c.circulation_status ?? '—')}</td>
            <td>${escHtml(c.barcode ?? '—')}</td>
            <td>${escHtml(c.rfid ?? '—')}</td>
            <td>${cartCell}</td>
        `;
        tr.querySelector('.opac-add-cart-copy')?.addEventListener('click', (e) => {
            e.stopPropagation();
            addCopyToCartFromHoldings(c.id, title, author, c.availability);
        });
        tbody.appendChild(tr);
    });
}

function renderDescriptionDl(desc) {
    const dl = document.getElementById('descriptionDl');
    if (!dl) return;
    dl.innerHTML = '';

    const rows = [
        ['Item description', desc.general_note],
        ['Physical description', desc.physical_description],
        ['Bibliography', desc.bibliography],
        ['ISBN', desc.isbn],
        ['Edition', desc.edition],
        ['Published', desc.published],
        ['Series', desc.series],
        ['Subjects / topics', desc.subject_topic],
        ['Subject form', desc.subject_form],
        ['Genre', desc.genre]
    ];

    rows.forEach(([label, val]) => {
        if (val === null || val === undefined || String(val).trim() === '') return;
        const dt = document.createElement('dt');
        dt.textContent = label;
        const dd = document.createElement('dd');
        dd.textContent = String(val);
        dl.appendChild(dt);
        dl.appendChild(dd);
    });

    if (!dl.children.length) {
        const dt = document.createElement('dt');
        dt.textContent = 'Description';
        const dd = document.createElement('dd');
        dd.textContent = 'No additional description fields are stored for this title.';
        dl.appendChild(dt);
        dl.appendChild(dd);
    }
}

function renderMarcViewTable(marcRows) {
    const tbody = document.getElementById('marcViewTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!marcRows || marcRows.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="2" class="text-muted small py-2">No MARC tags to display (empty or differ between copies — see Holdings).</td>';
        tbody.appendChild(tr);
        return;
    }

    marcRows.forEach((row) => {
        const tr = document.createElement('tr');
        const th = document.createElement('th');
        th.scope = 'row';
        th.textContent = row.label || '';
        const td = document.createElement('td');
        td.textContent = row.value != null ? String(row.value) : '';
        tr.appendChild(th);
        tr.appendChild(td);
        tbody.appendChild(tr);
    });
}

function renderBibSummary(desc) {
    const tb = document.getElementById('opacBibSummary');
    if (!tb) return;
    const rows = [
        ['Main author', desc.main_author],
        ['Format', desc.format],
        ['Language', desc.language],
        ['Published', desc.published],
        ['Edition', desc.edition],
        ['Subjects', desc.subject_topic]
    ];
    tb.innerHTML = '';
    rows.forEach(([k, v]) => {
        if (!v) return;
        const tr = document.createElement('tr');
        tr.innerHTML = `<th scope="row" class="small text-muted pe-2">${escHtml(k)}</th><td>${escHtml(v)}</td>`;
        tb.appendChild(tr);
    });
}

/* =========================================
   BOOK MODAL
========================================= */

function showBookModalLoading() {
    const load = document.getElementById('opacDetailLoading');
    const content = document.getElementById('opacDetailContent');
    if (load) {
        load.textContent = 'Loading record…';
        load.style.display = 'block';
    }
    if (content) content.style.display = 'none';
}

function showBookModalReady() {
    const load = document.getElementById('opacDetailLoading');
    const content = document.getElementById('opacDetailContent');
    if (load) load.style.display = 'none';
    if (content) content.style.display = 'block';
}

function openBookModalShell() {
    bindOpacTabsOnce();
    setOpacTab('holdings');
    const modal = document.getElementById('bookModal');
    if (modal) modal.style.display = 'flex';
    showBookModalLoading();
}

function applyOpacPayload(payload, card) {
    window.opacDetailPayload = payload;
    const coverUrl = card.dataset.img;
    const title = payload.group.title || '';
    const author = payload.group.author || '';

    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalAuthor').textContent = author;

    const bc = document.getElementById('opacBreadcrumb');
    if (bc) {
        const course = card.dataset.course?.trim();
        bc.textContent = course ? course : 'Catalog';
    }

    const libLine = document.getElementById('opacHoldingsLibraryLine');
    if (libLine) {
        const libs = [...new Set((payload.copies || []).map((c) => c.shelving_location).filter(Boolean))];
        libLine.textContent = libs.length ? libs.join(' · ') : 'Holdings';
    }

    const img = document.getElementById('modalImg');
    if (img) img.src = coverUrl || '';

    renderBibSummary(payload.description || {});
    renderDescriptionDl(payload.description || {});
    renderMarcViewTable(payload.marc_view_rows || []);
    renderHoldingsTable(payload.copies || [], payload.group || {});

    showBookModalReady();
}

function openBookCard(card) {
    const bookId = card.dataset.id;
    openBookModalShell();

    fetch(opacBookDetailUrl(bookId), { headers: { Accept: 'application/json' } })
        .then((r) => {
            if (!r.ok) throw new Error('Failed to load record');
            return r.json();
        })
        .then((payload) => {
            applyOpacPayload(payload, card);
        })
        .catch((err) => {
            console.error(err);
            const load = document.getElementById('opacDetailLoading');
            if (load) {
                load.textContent = 'Unable to load this record. Please try again.';
                load.style.display = 'block';
            }
            const content = document.getElementById('opacDetailContent');
            if (content) content.style.display = 'none';
            showToast('Could not load full record.', 'error');
        });

    window.selectedBook = {
        id: bookId,
        title: card.dataset.title,
        author: card.dataset.author,
        availability: card.dataset.availability,
        copies: parseInt(card.dataset.copies, 10) || 1,
        pubYear: card.dataset.year || ''
    };
}

function closeModal() {
    const el = document.getElementById('bookModal');
    if (el) el.style.display = 'none';
}

/* =========================================
   STUDENT MODAL + CHECKOUT
========================================= */

function openStudentModal() {
    const input = document.getElementById('studentIdInput');
    const err = document.getElementById('studentError');
    const modal = document.getElementById('studentModal');
    if (!input || !modal) return;
    input.value = '';
    if (err) err.style.display = 'none';
    modal.style.display = 'flex';
}

function closeStudentModal() {
    const el = document.getElementById('studentModal');
    if (el) el.style.display = 'none';
}

function markBorrowedInUi(bookId) {
    document.querySelectorAll(`.book-card[data-id="${bookId}"], .carosel[data-id="${bookId}"]`).forEach((card) => {
        const statusP = card.querySelector('p.text-success, p.text-danger');
        if (statusP) {
            statusP.textContent = 'Borrowed';
            statusP.classList.remove('text-success');
            statusP.classList.add('text-danger');
        }
        card.dataset.availability = 'Borrowed';
    });

    const row = document.querySelector(`#holdingsTableBody tr[data-copy-id="${bookId}"]`);
    if (row) {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 8) {
            cells[7].textContent = 'Checked out';
        }
    }
}

function runCheckoutRequest(studentId, booksToCheckout, loanTerms) {
    if (!window.CHECKOUT_URL || !window.CSRF_TOKEN) {
        showToast('Checkout is not configured.', 'error');
        return;
    }

    const payload = {
        student_id: studentId,
        books: booksToCheckout
    };
    if (loanTerms?.due_date) payload.due_date = loanTerms.due_date;
    if (loanTerms?.loan_duration_days) payload.loan_duration_days = loanTerms.loan_duration_days;

    fetch(window.CHECKOUT_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': window.CSRF_TOKEN
        },
        body: JSON.stringify(payload)
    })
        .then((res) => res.json())
        .then((data) => {
            if (data.success) {
                window.selectedStudent = {
                    name: data.student.name,
                    id_number: data.student.id_number
                };

                window.selectedBooks = (data.books || []).map((b) => ({
                    id: b.id,
                    title: b.title,
                    author: b.author || '',
                    barcode: b.barcode || '',
                    due_date: b.due_date || data.due_date || ''
                }));

                if (window.selectedBooks.length === 0 && data.book) {
                    window.selectedBooks = [{
                        id: data.book.id,
                        title: data.book.title,
                        author: data.book.author || '',
                        barcode: data.book.barcode || '',
                        due_date: data.book.due_date || data.due_date || ''
                    }];
                }

                if (window.selectedBooks.length === 1) {
                    window.selectedBook = window.selectedBooks[0];
                } else {
                    window.selectedBook = null;
                }

                window.selectedBooks.forEach((book) => markBorrowedInUi(book.id));

                window.cart = [];
                localStorage.removeItem('borrowCart');
                updateCartUI();

                closeStudentModal();
                closeCartModal();

                if (window.selectedBooks.length > 0) {
                    sendReceiptToPrintAgent();
                }

                showToast('Checkout successful!', 'success');

                if (window.opacDetailPayload && window.selectedBooks.length === 1) {
                    const id = window.selectedBooks[0].id;
                    const c = window.opacDetailPayload.copies.find((x) => Number(x.id) === Number(id));
                    if (c) {
                        c.availability = 'Borrowed';
                        c.circulation_status = 'Checked out';
                    }
                }
            } else {
                showToast('Checkout failed: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch((err) => {
            console.error(err);
            showToast('Server error occurred.', 'error');
        });
}

function confirmCheckout() {
    const studentId = document.getElementById('studentIdInput').value.trim();

    if (!studentId) {
        showToast('Please enter your Student ID.', 'error');
        return;
    }

    let booksToCheckout = [];

    if (window.cart && window.cart.length > 0) {
        booksToCheckout = window.cart;
    } else if (window.selectedBook) {
        booksToCheckout = [window.selectedBook];
    } else {
        showToast('No book selected.', 'error');
        return;
    }

    const proceed = typeof promptLoanTerms === 'function'
        ? promptLoanTerms()
        : Promise.resolve({});

    proceed.then((loanTerms) => runCheckoutRequest(studentId, booksToCheckout, loanTerms));
}

function sendReceiptToPrintAgent() {
    const url = String(window.RECEIPT_PRINT_AGENT_URL || '').trim();
    if (!url || !window.selectedStudent || !window.selectedBooks || window.selectedBooks.length === 0) {
        return;
    }

    const headers = { 'Content-Type': 'application/json' };
    if (window.RECEIPT_PRINT_AGENT_TOKEN) {
        headers['X-Receipt-Token'] = window.RECEIPT_PRINT_AGENT_TOKEN;
    }

    fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify({
            student: window.selectedStudent,
            books: window.selectedBooks
        })
    }).catch((err) => {
        console.error('Print agent error:', err);
    });
}

/* =========================================
   BROWSER RECEIPT PRINTING (no QZ Tray)
   Select POS-80 in the print dialog, or set it as
   the Windows default printer. Chrome kiosk:
   chrome.exe --kiosk --kiosk-printing
========================================= */

function escapeReceiptText(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function buildCheckoutReceiptHtml() {
    const libraryName = window.LIBRARY_NAME || 'Library';
    const now = new Date();
    const booksHtml = window.selectedBooks.map((book) => `
        <div class="item">
            <div class="title">${escapeReceiptText(book.title)}</div>
            ${book.author ? `<div>${escapeReceiptText(book.author)}</div>` : ''}
            ${book.barcode ? `<div>Barcode: ${escapeReceiptText(book.barcode)}</div>` : ''}
            <div>Due: ${escapeReceiptText(book.due_date || '')}</div>
        </div>
    `).join('');

    return `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Checkout receipt</title>
<style>
  @page { size: 80mm auto; margin: 4mm; }
  html, body {
    margin: 0;
    padding: 0;
    width: 72mm;
    background: #fff;
    color: #000;
    font-family: "Consolas", "Courier New", monospace;
    font-size: 12px;
    line-height: 1.35;
  }
  .receipt { width: 72mm; }
  .center { text-align: center; }
  .library { font-weight: 700; font-size: 13px; text-transform: uppercase; }
  .muted { margin: 4px 0 10px; }
  .rule { border-top: 1px dashed #000; margin: 8px 0; }
  .name { font-weight: 700; text-transform: uppercase; }
  .item { margin-bottom: 8px; }
  .title { font-weight: 700; }
  .served { margin-top: 10px; }
</style>
</head>
<body>
  <div class="receipt">
    <div class="center library">${escapeReceiptText(libraryName)}</div>
    <div class="center muted">${escapeReceiptText(now.toLocaleDateString())} ${escapeReceiptText(now.toLocaleTimeString())}</div>
    <div class="name">${escapeReceiptText(window.selectedStudent.name)}</div>
    <div>${escapeReceiptText(window.selectedStudent.id_number)}</div>
    <div class="rule"></div>
    <div class="title">Today's Checkouts</div>
    <div class="rule"></div>
    ${booksHtml}
    <div class="rule"></div>
    <div class="served">Served By: ________________</div>
  </div>
</body>
</html>`;
}

window.printReceiptBulk = function printReceiptBulk() {
    if (!window.selectedStudent || !window.selectedBooks || window.selectedBooks.length === 0) {
        alert('No checkout data available to print.');
        return;
    }

    const previous = document.getElementById('checkoutReceiptFrame');
    if (previous) previous.remove();

    const iframe = document.createElement('iframe');
    iframe.id = 'checkoutReceiptFrame';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const frameWindow = iframe.contentWindow;
    const frameDocument = iframe.contentDocument || frameWindow.document;
    frameDocument.open();
    frameDocument.write(buildCheckoutReceiptHtml());
    frameDocument.close();

    const triggerPrint = () => {
        try {
            frameWindow.focus();
            frameWindow.print();
        } catch (err) {
            console.error('Receipt print failed:', err);
            alert('Printing failed. Choose POS-80 in the print dialog.');
        }
        setTimeout(() => {
            if (iframe.parentNode) iframe.remove();
        }, 1500);
    };

    if (frameDocument.readyState === 'complete') {
        setTimeout(triggerPrint, 50);
    } else {
        iframe.onload = () => setTimeout(triggerPrint, 50);
    }
};

/* =========================================
   CAROUSEL
========================================= */

function slide(direction) {
    if (!track) return;

    const bookWidth = 130;
    scrollAmount += direction * bookWidth * 2;

    if (scrollAmount < 0) scrollAmount = 0;
    const maxScroll = track.scrollWidth - track.clientWidth;
    if (scrollAmount > maxScroll) scrollAmount = maxScroll;

    track.style.transform = `translateX(-${scrollAmount}px)`;
}

function openStudentModalFromCart() {
    if (!window.cart || window.cart.length === 0) {
        showToast('Cart is empty.', 'error');
        return;
    }
    window.selectedBook = null;
    closeCartModal();
    openStudentModal();
}

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    closeModal();
    closeStudentModal();
    if (typeof closeCartModal === 'function') closeCartModal();
});

document.addEventListener('DOMContentLoaded', () => {
    bindOpacTabsOnce();

    const hash = window.location.hash || '';
    const m = /^#book-(\d+)$/.exec(hash);
    if (m) {
        const id = m[1];
        const card = document.querySelector(`.book-card[data-id="${id}"], .carosel[data-id="${id}"]`);
        if (card) {
            openBookCard(card);
        }
    }
});
