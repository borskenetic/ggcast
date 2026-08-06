/**
 * Hybrid catalog create: queue form offline, push when online.
 * Online with connectivity: normal form POST (unchanged).
 * Offline or failed network: IndexedDB outbox → /hybrid/catalog/records
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const H = window.GgcastHybrid;
    if (!H) return;

    H.mountStatusBadge('#hybridStatus');
    H.startAutoSync({ attendance: false, opac: false, catalog: true, useSession: true, intervalMs: 60000 });

    document.getElementById('hybridTokenSave')?.addEventListener('click', () => {
      const v = document.getElementById('hybridTokenInput')?.value?.trim();
      if (!v) return alert('Paste a catalog-capable hybrid device token.');
      H.setToken(v);
      alert('Token saved on this browser.');
    });

    document.getElementById('hybridPushCatalog')?.addEventListener('click', () => {
      H.pushCatalogOutbox(true)
        .then((r) => alert(`Uploaded ${r.accepted || 0}, rejected ${r.rejected || 0}.`))
        .catch((e) => alert(e.message || 'Upload failed'));
    });

    const form = document.getElementById('addBookForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      // Always try normal online path first when online
      if (H.isOnline()) {
        // Still allow background queue flush
        H.pushCatalogOutbox(true).catch(() => {});
        return;
      }

      e.preventDefault();
      e.stopPropagation();

      try {
        const payload = H.formToPayload(form);
        // File covers cannot upload offline — drop cover; re-edit later online
        delete payload.cover_image;
        await H.enqueueCatalogRecord(payload);
        const hint = document.getElementById('hybridCatalogHint');
        if (hint) {
          hint.textContent = 'Saved offline. Will upload when connectivity returns.';
          hint.classList.add('text-warning');
        }
        alert('No internet — book draft queued on this device. It will upload automatically when you reconnect.');
        // Soft reset copy fields only if multi-copy stays
      } catch (err) {
        alert(err.message || 'Could not queue offline catalog entry.');
      }
    }, true);

    // On reconnect, drain catalog queue
    window.addEventListener('online', () => {
      H.pushCatalogOutbox(true).catch(() => {});
    });
  });
})();
