/**
 * Wire OPAC → automatic offline shell + local cache search.
 */
(function () {
  'use strict';

  const OFFLINE_OPAC = '/opac-offline.html';

  function offlineUrl(searchTerm) {
    const q = (searchTerm || '').trim();
    return q ? `${OFFLINE_OPAC}?q=${encodeURIComponent(q)}` : OFFLINE_OPAC;
  }

  function goOfflineOpac(searchTerm) {
    if (location.pathname.indexOf('opac-offline') !== -1) return;
    location.replace(offlineUrl(searchTerm));
  }

  function registerSw() {
    if (!('serviceWorker' in navigator)) return Promise.resolve(null);
    return navigator.serviceWorker
      .register('/sw-hybrid.js', { scope: '/' })
      .then((reg) => {
        const urls = [
          '/opac',
          OFFLINE_OPAC,
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

  // Redirect as early as possible if already offline when this script loads
  if (typeof navigator !== 'undefined' && !navigator.onLine) {
    const params = new URLSearchParams(location.search);
    goOfflineOpac(params.get('search') || params.get('q') || '');
    return;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const H = window.GgcastHybrid;
    if (!H) return;

    registerSw();
    H.mountStatusBadge('#hybridStatus');

    if (H.isOnline()) {
      H.startAutoSync({ attendance: false, opac: true, catalog: false, useSession: true, intervalMs: 120000 });
    } else {
      goOfflineOpac();
      return;
    }

    // Live transition: online → offline while on /opac
    window.addEventListener('offline', () => {
      const input = document.getElementById('searchBar');
      const term = input ? input.value.trim() : '';
      const banner = document.getElementById('hybridOfflineBanner');
      if (banner) banner.style.display = 'block';
      setTimeout(() => goOfflineOpac(term), 250);
    });

    // Collapsible hybrid bar
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
          const cache = await caches.open('ggcast-hybrid-shell-v4');
          await cache.add('/opac');
          await cache.add(OFFLINE_OPAC);
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
        '.\n\nIf the network drops, you will be sent to the offline OPAC automatically.\n' +
        'You can also open Offline OPAC anytime from this panel.'
      );
    }

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
        hint.innerHTML = `${c.opac_titles} title groups cached. Offline uses <a href="${OFFLINE_OPAC}">offline OPAC</a> automatically.`;
        hint.classList.remove('text-danger');
      } else {
        hint.innerHTML =
          'No catalog cache yet — click <strong>Download catalog cache</strong> while online.';
      }
    }
    updateCacheHint();

    // Search while offline → offline shell with query
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
          const fd = new FormData(form);
          goOfflineOpac(String(fd.get('search') || ''));
        }
      },
      true
    );

    // New arrivals need detail API — offline → offline shell
    document.addEventListener(
      'click',
      (e) => {
        if (H.isOnline()) return;
        if (e.target.closest('#hybridOpacShell')) return;
        const card = e.target.closest?.('.book-card, .carosel');
        if (!card) return;
        e.preventDefault();
        e.stopPropagation();
        goOfflineOpac(card.dataset?.title || '');
      },
      true
    );

    const patchOpen = () => {
      if (typeof window.openBookCard !== 'function') return;
      const original = window.openBookCard;
      if (original.__hybridPatched) return;
      function hybridOpenBookCard(card) {
        if (!H.isOnline()) {
          goOfflineOpac(card?.dataset?.title || '');
          return;
        }
        return original.apply(this, arguments);
      }
      hybridOpenBookCard.__hybridPatched = true;
      window.openBookCard = hybridOpenBookCard;
    };
    patchOpen();
    setTimeout(patchOpen, 0);
  });
})();
