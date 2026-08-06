/**
 * Wire OPAC search forms to hybrid offline cache.
 * Offline search never navigates — results render on this page from IndexedDB.
 */
(function () {
  'use strict';

  function registerSw() {
    if (!('serviceWorker' in navigator)) return Promise.resolve(null);
    return navigator.serviceWorker
      .register('/sw-hybrid.js', { scope: '/' })
      .then((reg) => {
        const urls = [
          '/opac',
          '/opac-offline.html',
          location.href.split('#')[0],
          '/js/hybrid/hybrid.js',
          '/js/hybrid/opac-bridge.js',
          '/js/hybrid/status.css',
          '/vendor/bootstrap/css/bootstrap.min.css',
          '/css/books/landing.css',
          '/css/site-responsive.css',
        ];
        const post = (sw) => sw && sw.postMessage({ type: 'CACHE_URLS', urls });
        if (reg.active) post(reg.active);
        navigator.serviceWorker.ready.then((r) => post(r.active));
        return reg;
      })
      .catch(() => null);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const H = window.GgcastHybrid;
    if (!H) return;

    registerSw();

    H.mountStatusBadge('#hybridStatus');
    if (H.isOnline()) {
      // Background incremental only — manual Download always force-full
      H.startAutoSync({ attendance: false, opac: true, catalog: false, useSession: true, intervalMs: 120000 });
    }

    const banner = document.getElementById('hybridOfflineBanner');
    const paintBanner = () => {
      if (banner) banner.style.display = H.isOnline() ? 'none' : 'block';
    };
    paintBanner();
    window.addEventListener('online', paintBanner);
    window.addEventListener('offline', paintBanner);

    // Collapsible hybrid bar (closed by default)
    const hybridShell = document.getElementById('hybridOpacShell');
    const hybridToggle = document.getElementById('hybridBarToggle');
    const hybridBody = document.getElementById('hybridBarBody');
    const setHybridOpen = (open) => {
      if (!hybridShell || !hybridToggle || !hybridBody) return;
      hybridShell.classList.toggle('is-open', open);
      hybridToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      hybridBody.hidden = !open;
      try {
        localStorage.setItem('ggcast_hybrid_bar_open', open ? '1' : '0');
      } catch (e) {
        /* ignore */
      }
    };
    if (hybridToggle) {
      let preferOpen = false;
      try {
        preferOpen = localStorage.getItem('ggcast_hybrid_bar_open') === '1';
      } catch (e) {
        /* ignore */
      }
      setHybridOpen(preferOpen);
      hybridToggle.addEventListener('click', () => {
        const open = hybridToggle.getAttribute('aria-expanded') !== 'true';
        setHybridOpen(open);
      });
    }

    async function afterCacheShell() {
      await registerSw();
      if ('caches' in window) {
        try {
          const cache = await caches.open('ggcast-hybrid-shell-v3');
          await cache.add('/opac');
          await cache.add('/opac-offline.html');
        } catch (e) {
          /* ignore */
        }
      }
    }

    function cacheMessage(p) {
      const stored = p.stored_title_count ?? (p.titles || []).length;
      const batch = p.batch_title_count ?? (p.titles || []).length;
      if (stored === 0) {
        return (
          'Cached 0 titles on this device.\n\n' +
          'The server returned no book groups. Confirm the library has cataloged books, ' +
          'or try again while signed in as staff.\n\n' +
          `(This download batch: ${batch} titles.)`
        );
      }
      return (
        `Catalog cache ready: ${stored} title group(s) on this device` +
        (batch !== stored ? ` (this download: ${batch} updates)` : '') +
        '.\n\nStay on this page → go offline → Search (no page reload).\n' +
        'Or reopen /opac-offline.html when offline.'
      );
    }

    /** Always force full catalog pull for manual actions. */
    async function downloadCacheFull(preferSession) {
      let p;
      try {
        p = await H.pullOpacSnapshot(!!preferSession, { forceFull: true });
      } catch (e) {
        if (H.getToken()) {
          p = await H.pullOpacSnapshot(false, { forceFull: true });
        } else {
          throw e;
        }
      }
      await afterCacheShell();
      return p;
    }

    document.getElementById('hybridTokenSave')?.addEventListener('click', async () => {
      const v = document.getElementById('hybridTokenInput')?.value?.trim();
      if (v) H.setToken(v);
      try {
        const p = await downloadCacheFull(false);
        alert(cacheMessage(p));
        updateCacheHint();
      } catch (e) {
        alert(e.message || 'Cache failed. Device must allow OPAC, or use Download while signed in as staff.');
      }
    });

    document.getElementById('hybridSyncNow')?.addEventListener('click', async () => {
      try {
        const p = await downloadCacheFull(true);
        alert(cacheMessage(p));
        updateCacheHint();
      } catch (e) {
        alert(e.message || 'Download failed — pair a hybrid OPAC device token, or sign in as staff.');
      }
    });

    async function updateCacheHint() {
      const hint = document.getElementById('hybridCacheHint');
      if (!hint) return;
      const c = await H.pendingCounts();
      if (c.opac_titles > 0) {
        hint.textContent = `${c.opac_titles} title groups cached for offline search on this browser.`;
        hint.classList.remove('text-danger');
      } else {
        hint.innerHTML =
          'No catalog cache yet — expand this panel and click <strong>Download catalog cache</strong> while online.';
      }
    }
    updateCacheHint();

    function coverUrl(t) {
      if (!t.cover_image) return '/images/default-book.png';
      if (/^https?:\/\//i.test(t.cover_image)) return t.cover_image;
      return '/' + String(t.cover_image).replace(/^\//, '');
    }

    function renderOfflineResults(titles, term) {
      const host = document.getElementById('hybridOfflineResults');
      if (!host) return;
      host.style.display = 'block';
      host.scrollIntoView({ behavior: 'smooth', block: 'start' });

      if (!titles.length) {
        host.innerHTML = `<div class="alert alert-warning">No offline matches for “${esc(term)}”. Expand Hybrid controls and download the catalog cache while online.</div>`;
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
          const lines = copies.length
            ? copies
                .map(
                  (c) =>
                    `• ${c.accession_no || c.barcode || c.id} — ${c.availability || '?'} (${c.call_number || '—'})`
                )
                .join('\n')
            : 'No copy rows in local cache.';
          alert(`${title}\n${author || ''}\n\nHoldings (cached):\n${lines}`);
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

    async function handleOfflineSearch(form) {
      const fd = new FormData(form);
      const term = String(fd.get('search') || '').trim();
      const filters = {
        course: fd.get('course') || 'all',
        subject_topic: fd.get('subject_topic') || 'All',
        genre: fd.get('genre') || 'All',
        content_type: fd.get('content_type') || 'All',
        section: fd.get('section') || 'All',
      };
      const counts = await H.pendingCounts();
      if (!counts.opac_titles) {
        const host = document.getElementById('hybridOfflineResults');
        if (host) {
          host.style.display = 'block';
          host.innerHTML =
            '<div class="alert alert-danger">No catalog on this device yet. Expand <strong>Hybrid / offline OPAC</strong> → <strong>Download catalog cache</strong> while online.</div>';
        }
        setHybridOpen(true);
        return;
      }
      const hits = await H.searchOpacLocal(term, filters, 60);
      renderOfflineResults(hits, term || '(browse cache)');
    }

    document.addEventListener(
      'submit',
      (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const isOpac =
          form.classList.contains('opac-search-form') ||
          form.classList.contains('opac-search-header-form') ||
          form.classList.contains('opac-hero-search-form') ||
          (form.getAttribute('action') || '').includes('opac');
        if (!isOpac) return;
        if (!H.isOnline()) {
          e.preventDefault();
          e.stopPropagation();
          handleOfflineSearch(form);
        }
      },
      true
    );
  });
})();
