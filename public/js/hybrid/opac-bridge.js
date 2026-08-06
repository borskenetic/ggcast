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
        // Ask SW to cache this OPAC document + assets for offline reopen
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
    // Auto-pull snapshot only when online; don't spam while offline
    if (H.isOnline()) {
      H.startAutoSync({ attendance: false, opac: true, catalog: false, useSession: true, intervalMs: 120000 });
    }

    const banner = document.getElementById('hybridOfflineBanner');
    const paintBanner = () => {
      if (banner) banner.style.display = H.isOnline() ? 'none' : 'block';
    };
    paintBanner();
    window.addEventListener('online', paintBanner);
    window.addEventListener('offline', paintBanner);

    async function downloadCache(preferSession) {
      let p;
      try {
        p = preferSession
          ? await H.pullOpacSnapshot(true)
          : await H.pullOpacSnapshot(false);
      } catch (e) {
        if (H.getToken()) {
          p = await H.pullOpacSnapshot(false);
        } else {
          throw e;
        }
      }
      const n = (p.titles || []).length;
      await registerSw();
      // Persist shell so reopen works offline
      if ('caches' in window) {
        try {
          const cache = await caches.open('ggcast-hybrid-shell-v3');
          await cache.add('/opac');
          await cache.add('/opac-offline.html');
        } catch (e) {
          /* ignore */
        }
      }
      return n;
    }

    document.getElementById('hybridTokenSave')?.addEventListener('click', async () => {
      const v = document.getElementById('hybridTokenInput')?.value?.trim();
      if (v) H.setToken(v);
      try {
        const n = await downloadCache(false);
        alert(
          `Token saved. Cached ${n} title groups.\n\nKeep this OPAC tab open, then go offline and search without refreshing.\nIf you reopen offline, use /opac or /opac-offline.html`
        );
      } catch (e) {
        alert(e.message || 'Cache failed. Device must allow OPAC, or use Download while signed in as staff.');
      }
    });

    document.getElementById('hybridSyncNow')?.addEventListener('click', async () => {
      try {
        const n = await downloadCache(true);
        alert(
          `Cached ${n} title groups on this device.\n\nNext: stay on this page → go offline → type and Search (results stay here, no page reload).`
        );
      } catch (e) {
        try {
          if (H.getToken()) {
            const n = await downloadCache(false);
            alert(`Cached ${n} title groups (device token).`);
            return;
          }
        } catch (e2) {
          /* fall through */
        }
        alert(e.message || 'Download failed — pair a hybrid OPAC device token, or sign in as staff.');
      }
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
      host.scrollIntoView({ behavior: 'smooth', block: 'start' });

      // Soft-hide online-only heavy sections while offline searching
      document.querySelectorAll('.opac-hero-search, .opac-page-fill .carousel, #carouselTrack').forEach((el) => {
        if (el) el.style.opacity = '0.35';
      });

      if (!titles.length) {
        host.innerHTML = `<div class="alert alert-warning">No offline matches for “${esc(term)}”. While online, open Pair / sync → <strong>Download catalog cache</strong>.</div>`;
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
            '<div class="alert alert-danger">No catalog on this device yet. Go online → Pair / sync hybrid OPAC → <strong>Download catalog cache</strong>. Then search offline without leaving this tab (or open <code>/opac-offline.html</code>).</div>';
        }
        return;
      }
      const hits = await H.searchOpacLocal(term, filters, 60);
      renderOfflineResults(hits, term || '(browse cache)');
    }

    // Capture phase so we always win over browser navigation
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

        // Offline OR flaky network: force local path (navigation fails offline)
        if (!H.isOnline()) {
          e.preventDefault();
          e.stopPropagation();
          handleOfflineSearch(form);
        }
      },
      true
    );

    // Show cache count on badge area
    H.pendingCounts().then((c) => {
      if (c.opac_titles && banner) {
        banner.dataset.cacheCount = String(c.opac_titles);
      }
    });
  });
})();
