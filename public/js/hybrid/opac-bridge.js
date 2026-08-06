/**
 * Wire OPAC search forms to hybrid offline cache.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const H = window.GgcastHybrid;
    if (!H) return;

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/js/hybrid/sw.js').catch(() => {});
    }

    H.mountStatusBadge('#hybridStatus');
    H.startAutoSync({ attendance: false, opac: true, catalog: false, useSession: true, intervalMs: 120000 });

    const banner = document.getElementById('hybridOfflineBanner');
    const paintBanner = () => {
      if (banner) banner.style.display = H.isOnline() ? 'none' : 'block';
    };
    paintBanner();
    window.addEventListener('online', paintBanner);
    window.addEventListener('offline', paintBanner);

    document.getElementById('hybridTokenSave')?.addEventListener('click', () => {
      const v = document.getElementById('hybridTokenInput')?.value?.trim();
      if (v) H.setToken(v);
      H.pullOpacSnapshot(false)
        .then((p) => alert(`Cached ${(p.titles || []).length} title groups.`))
        .catch((e) => alert(e.message || 'Cache failed'));
    });

    document.getElementById('hybridSyncNow')?.addEventListener('click', () => {
      H.pullOpacSnapshot(true)
        .then((p) => alert(`Cached ${(p.titles || []).length} title groups.`))
        .catch((e) => {
          if (H.getToken()) {
            return H.pullOpacSnapshot(false).then((p) => alert(`Cached ${(p.titles || []).length} title groups.`));
          }
          throw e;
        })
        .catch((e) => alert(e.message || 'Download failed — pair a hybrid device or stay signed in as staff.'));
    });

    function coverUrl(t) {
      if (!t.cover_image) return '/images/default-book.png';
      if (/^https?:\/\//i.test(t.cover_image)) return t.cover_image;
      return '/' + String(t.cover_image).replace(/^\//, '');
    }

    function renderOfflineResults(titles, term) {
      const host = document.getElementById('hybridOfflineResults');
      if (!host) return;
      host.style.display = 'block';
      if (!titles.length) {
        host.innerHTML = `<div class="alert alert-warning">No offline matches for “${esc(term)}”. Sync a catalog cache while online.</div>`;
        return;
      }
      host.innerHTML = `
        <h2 class="h5 mb-3">Offline results for “${esc(term)}” <span class="badge text-bg-secondary">${titles.length}</span></h2>
        <div class="row g-3">
          ${titles
            .map(
              (t) => `
            <div class="col-6 col-md-3 col-lg-2">
              <div class="book-card card h-100 hybrid-offline-card" data-id="${t.id}"
                   data-title="${escAttr(t.title_statement || '')}"
                   data-author="${escAttr(t.main_author || '')}"
                   data-year="${escAttr(t.pub_year || '')}"
                   data-availability="${t.is_available ? 'Available' : 'Unavailable'}"
                   data-copies="${t.copies || 1}"
                   role="button" tabindex="0">
                <img src="${escAttr(coverUrl(t))}" class="card-img-top" alt="" style="object-fit:cover;height:160px;" onerror="this.src='/images/default-book.png'">
                <div class="card-body p-2">
                  <div class="small fw-semibold">${esc(t.title_statement || 'Untitled')}</div>
                  <div class="small text-muted">${esc(t.main_author || '')}</div>
                  <div class="small">${t.is_available ? 'Available' : 'Check holdings'} · ${t.copies || 1} cop.</div>
                </div>
              </div>
            </div>`
            )
            .join('')}
        </div>`;

      host.querySelectorAll('.hybrid-offline-card').forEach((card) => {
        card.addEventListener('click', async () => {
          const title = card.getAttribute('data-title');
          const author = card.getAttribute('data-author');
          const year = card.getAttribute('data-year');
          const copies = await H.getLocalCopiesForTitle(title, author, year);
          const group = {
            title,
            author,
            year,
            id: Number(card.getAttribute('data-id')),
          };
          window.selectedBook = {
            id: Number(card.getAttribute('data-id')),
            title,
            author,
            availability: card.getAttribute('data-availability'),
            copies: Number(card.getAttribute('data-copies') || 1),
            pubYear: year,
          };
          window.opacDetailPayload = {
            group,
            copies: copies.map((c) => ({
              id: c.id,
              accession_no: c.accession_no,
              barcode: c.barcode,
              call_number: c.call_number,
              availability: c.availability,
              section: c.section,
              circulation_status: c.availability === 'Available' ? 'On shelf' : 'Checked out',
            })),
            description: '',
            marc: [],
          };
          if (typeof openBookModal === 'function') {
            // Prefer existing modal if openBookCard/open path exists
          }
          if (typeof renderHoldingsTable === 'function') {
            renderHoldingsTable(window.opacDetailPayload.copies, group);
          }
          const modal = document.getElementById('bookModal') || document.querySelector('.book-modal');
          const titleEl = document.getElementById('modalBookTitle');
          if (titleEl) titleEl.textContent = title;
          const authorEl = document.getElementById('modalBookAuthor');
          if (authorEl) authorEl.textContent = author || '';
          if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('show');
          } else {
            alert(`${title}\n${author || ''}\nCopies offline: ${copies.length}`);
          }
        });
      });
    }

    function esc(s) {
      const d = document.createElement('div');
      d.textContent = String(s ?? '');
      return d.innerHTML;
    }
    function escAttr(s) {
      return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
    }

    document.querySelectorAll('form[action*="opac"], form.opac-search-form, form.opac-search-header-form, form.opac-hero-search-form').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        if (H.isOnline()) return;
        e.preventDefault();
        const fd = new FormData(form);
        const term = String(fd.get('search') || '').trim();
        const filters = {
          course: fd.get('course') || 'all',
          subject_topic: fd.get('subject_topic') || 'All',
          genre: fd.get('genre') || 'All',
          content_type: fd.get('content_type') || 'All',
          section: fd.get('section') || 'All',
        };
        const hits = await H.searchOpacLocal(term, filters, 60);
        renderOfflineResults(hits, term || '(all cached titles)');
      });
    });
  });
})();
