/**
 * Hybrid offline store (IndexedDB) for ggcast cataloging / OPAC / attendance.
 * Pattern mirrors acd_online gate-terminal: local queue + pull/push when online.
 */
(function (global) {
  'use strict';

  const DB_NAME = 'ggcast_hybrid_v1';
  const DB_VERSION = 2;
  const TOKEN_KEY = 'ggcast_hybrid_device_token';
  const META_PREFIX = 'ggcast_hybrid_meta_';

  function uuid() {
    if (global.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      const v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  function openDb() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = () => {
        const db = req.result;
        if (!db.objectStoreNames.contains('students')) {
          const s = db.createObjectStore('students', { keyPath: 'id' });
          s.createIndex('by_qrcode', 'qrcode', { unique: false });
          s.createIndex('by_id_number', 'id_number', { unique: false });
          s.createIndex('by_normalized_name', 'normalized_name', { unique: false });
        }
        if (!db.objectStoreNames.contains('employees')) {
          const e = db.createObjectStore('employees', { keyPath: 'id' });
          e.createIndex('by_qrcode', 'qrcode', { unique: false });
          e.createIndex('by_employee_id', 'employee_id', { unique: false });
        }
        if (!db.objectStoreNames.contains('attendance_outbox')) {
          const o = db.createObjectStore('attendance_outbox', { keyPath: 'client_uuid' });
          o.createIndex('by_synced', 'synced_at', { unique: false });
        }
        if (!db.objectStoreNames.contains('opac_titles')) {
          const t = db.createObjectStore('opac_titles', { keyPath: 'id' });
          t.createIndex('by_title', 'title_statement', { unique: false });
        }
        if (!db.objectStoreNames.contains('opac_copies')) {
          db.createObjectStore('opac_copies', { keyPath: 'id' });
        }
        if (!db.objectStoreNames.contains('opac_ebooks')) {
          db.createObjectStore('opac_ebooks', { keyPath: 'id' });
        }
        if (!db.objectStoreNames.contains('catalog_outbox')) {
          db.createObjectStore('catalog_outbox', { keyPath: 'client_uuid' });
        }
        if (!db.objectStoreNames.contains('kv')) {
          db.createObjectStore('kv', { keyPath: 'key' });
        }
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  function txDone(tx) {
    return new Promise((resolve, reject) => {
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
      tx.onabort = () => reject(tx.error);
    });
  }

  async function kvGet(key) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('kv', 'readonly');
      const req = tx.objectStore('kv').get(key);
      req.onsuccess = () => resolve(req.result ? req.result.value : null);
      req.onerror = () => reject(req.error);
    });
  }

  async function kvSet(key, value) {
    const db = await openDb();
    const tx = db.transaction('kv', 'readwrite');
    tx.objectStore('kv').put({ key, value });
    await txDone(tx);
  }

  async function putAll(storeName, rows) {
    if (!rows || !rows.length) return;
    const db = await openDb();
    const tx = db.transaction(storeName, 'readwrite');
    const store = tx.objectStore(storeName);
    for (const row of rows) store.put(row);
    await txDone(tx);
  }

  async function getAll(storeName) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readonly');
      const req = tx.objectStore(storeName).getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => reject(req.error);
    });
  }

  async function getByKey(storeName, key) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, 'readonly');
      const req = tx.objectStore(storeName).get(key);
      req.onsuccess = () => resolve(req.result || null);
      req.onerror = () => reject(req.error);
    });
  }

  async function deleteKey(storeName, key) {
    const db = await openDb();
    const tx = db.transaction(storeName, 'readwrite');
    tx.objectStore(storeName).delete(key);
    await txDone(tx);
  }

  function getToken() {
    try {
      return localStorage.getItem(TOKEN_KEY) || '';
    } catch {
      return '';
    }
  }

  function setToken(token) {
    try {
      if (token) localStorage.setItem(TOKEN_KEY, token.trim());
      else localStorage.removeItem(TOKEN_KEY);
    } catch { /* ignore */ }
  }

  function isOnline() {
    return typeof navigator !== 'undefined' ? navigator.onLine : true;
  }

  function apiBase() {
    return (global.HYBRID_API_BASE || '/api/hybrid').replace(/\/$/, '');
  }

  async function hybridFetch(path, options = {}) {
    const token = getToken();
    const headers = Object.assign(
      {
        Accept: 'application/json',
      },
      options.headers || {}
    );
    if (token) {
      headers.Authorization = `Bearer ${token}`;
      headers['X-Hybrid-Token'] = token;
    }
    if (options.jsonBody) {
      headers['Content-Type'] = 'application/json';
    }

    const res = await fetch(apiBase() + path, Object.assign({}, options, {
      headers,
      body: options.jsonBody ? JSON.stringify(options.jsonBody) : options.body,
    }));

    const text = await res.text();
    let body = null;
    try {
      body = text ? JSON.parse(text) : null;
    } catch {
      body = { message: text };
    }
    if (!res.ok) {
      const err = new Error((body && body.message) || `HTTP ${res.status}`);
      err.status = res.status;
      err.body = body;
      throw err;
    }
    return body;
  }

  /** Session-authenticated web routes (CSRF). */
  async function webFetch(path, options = {}) {
    const csrf =
      document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
      global.HYBRID_CSRF ||
      '';
    const headers = Object.assign(
      {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      options.headers || {}
    );
    if (options.jsonBody) headers['Content-Type'] = 'application/json';

    const res = await fetch(path, Object.assign({}, options, {
      headers,
      credentials: 'same-origin',
      body: options.jsonBody ? JSON.stringify(options.jsonBody) : options.body,
    }));
    const text = await res.text();
    let body = null;
    try {
      body = text ? JSON.parse(text) : null;
    } catch {
      body = { message: text };
    }
    if (!res.ok) {
      const err = new Error((body && body.message) || `HTTP ${res.status}`);
      err.status = res.status;
      err.body = body;
      throw err;
    }
    return body;
  }

  // ---------- Attendance ----------

  function normalizeName(name) {
    return String(name || '')
      .toUpperCase()
      .replace(/[^A-Z\s]/g, '')
      .replace(/\b[A-Z]\b/g, '')
      .replace(/\s+/g, '');
  }

  function parseQrLocal(raw) {
    const r = String(raw || '').replace(/\r/g, '').trim();
    if (!r) return {};
    if (r.includes('\n')) {
      const lines = r.split('\n').map((s) => s.trim()).filter(Boolean);
      return { student_no: lines[0], full_name: lines[1], course: lines[2] };
    }
    const parts = r.split(',').map((s) => s.trim());
    if (/^\d{2}-\d+$/.test(parts[0] || '')) {
      return { student_no: parts[0], full_name: parts[1], course: parts[2] };
    }
    return { full_name: parts[0], course: parts[1] };
  }

  async function findLocalStudent(scanToken) {
    const token = String(scanToken || '').replace(/\r/g, '').trim();
    const students = await getAll('students');
    let hit = students.find((s) => s.qrcode && s.qrcode === token);
    if (hit) return hit;
    hit = students.find((s) => s.id_number && s.id_number === token);
    if (hit) return hit;

    const parsed = parseQrLocal(token);
    if (parsed.student_no) {
      hit = students.find((s) => s.id_number === parsed.student_no);
      if (hit) return hit;
    }
    if (parsed.full_name) {
      const nn = normalizeName(parsed.full_name);
      hit = students.find((s) => (s.normalized_name || normalizeName(`${s.firstname} ${s.lastname}`)) === nn);
      if (hit) return hit;
    }
    return null;
  }

  async function findLocalEmployee(scanToken) {
    const token = String(scanToken || '').replace(/\r/g, '').trim();
    if (!token) return null;
    let employees = [];
    try {
      employees = await getAll('employees');
    } catch (_) {
      return null;
    }
    let hit = employees.find((e) => e.qrcode && e.qrcode === token);
    if (hit) return hit;
    hit = employees.find((e) => e.employee_id && e.employee_id === token);
    if (hit) return hit;

    const parsed = parseQrLocal(token);
    if (parsed.student_no) {
      hit = employees.find((e) => e.employee_id === parsed.student_no);
      if (hit) return hit;
    }
    if (parsed.full_name) {
      const nn = normalizeName(parsed.full_name);
      hit = employees.find((e) => normalizeName(`${e.firstname} ${e.lastname}`) === nn);
      if (hit) return hit;
    }
    return null;
  }

  async function findLocalPatron(scanToken) {
    const student = await findLocalStudent(scanToken);
    if (student) return { type: 'student', patron: student };
    const employee = await findLocalEmployee(scanToken);
    if (employee) return { type: 'employee', patron: employee };
    return null;
  }

  async function recordLocalAttendance(scanToken) {
    const found = await findLocalPatron(scanToken);
    if (!found) {
      return { type: 'error', message: 'Patron not in offline roster. Sync when online.' };
    }

    const { type, patron } = found;
    const last = patron.last_log;
    const newStatus = last && String(last.status).toUpperCase() === 'IN' ? 'OUT' : 'IN';
    const scannedAt = new Date().toISOString();
    const client_uuid = uuid();

    const row = {
      client_uuid,
      scan_token: String(scanToken).replace(/\r/g, '').trim(),
      status: newStatus,
      scanned_at: scannedAt,
      student_id: type === 'student' ? patron.id : null,
      employee_id: type === 'employee' ? patron.id : null,
      patron_type: type,
      synced_at: null,
    };

    const storeName = type === 'student' ? 'students' : 'employees';
    const db = await openDb();
    const stores = db.objectStoreNames.contains(storeName)
      ? ['attendance_outbox', storeName]
      : ['attendance_outbox'];
    const tx = db.transaction(stores, 'readwrite');
    tx.objectStore('attendance_outbox').put(row);
    patron.last_log = { status: newStatus, scanned_at: scannedAt };
    if (db.objectStoreNames.contains(storeName)) {
      tx.objectStore(storeName).put(patron);
    }
    await txDone(tx);

    const picture = type === 'student' ? patron.profile_picture : patron.profile_picture;
    const payload = {
      type,
      offline: true,
      status: newStatus,
      logout_feedback_enabled: !!((await kvGet('attendance_settings')) || {}).logout_feedback_enabled,
      log: {
        scanned_at: new Date(scannedAt).toLocaleString('en-US', { hour12: true }),
      },
      client_uuid,
    };

    if (type === 'student') {
      payload.student_id = patron.id;
      payload.student = {
        firstname: patron.firstname,
        lastname: patron.lastname,
        profile_picture: picture,
      };
    } else {
      payload.employee_id = patron.id;
      payload.employee = {
        firstname: patron.firstname,
        lastname: patron.lastname,
        profile_picture: picture,
        designation: patron.designation || patron.department,
      };
    }

    return payload;
  }

  async function pullAttendanceRoster(useSession = false) {
    const since = await kvGet('attendance_last_pull_at');
    const q = since ? `?since=${encodeURIComponent(since)}` : '';
    let payload;
    if (useSession || !getToken()) {
      payload = await webFetch(`/hybrid/attendance/roster${q}`);
    } else {
      payload = await hybridFetch(`/attendance/roster${q}`);
    }

    if (payload.full_snapshot) {
      const db = await openDb();
      const stores = ['students'];
      if (db.objectStoreNames.contains('employees')) stores.push('employees');
      const tx = db.transaction(stores, 'readwrite');
      tx.objectStore('students').clear();
      if (db.objectStoreNames.contains('employees')) tx.objectStore('employees').clear();
      await txDone(tx);
    }

    await putAll('students', payload.students || []);
    if (payload.employees) {
      await putAll('employees', payload.employees || []);
    }

    if (payload.logs_since && payload.logs_since.length) {
      const students = await getAll('students');
      const byStudentId = new Map(students.map((s) => [s.id, s]));
      let employees = [];
      try {
        employees = await getAll('employees');
      } catch (_) {}
      const byEmployeeId = new Map(employees.map((e) => [e.id, e]));

      for (const log of payload.logs_since) {
        if (log.student_id != null && byStudentId.has(log.student_id)) {
          const s = byStudentId.get(log.student_id);
          const prev = s.last_log?.scanned_at ? new Date(s.last_log.scanned_at).getTime() : 0;
          const cur = log.scanned_at ? new Date(log.scanned_at).getTime() : 0;
          if (cur >= prev) {
            s.last_log = { status: log.status, scanned_at: log.scanned_at };
            byStudentId.set(s.id, s);
          }
        }
        if (log.employee_id != null && byEmployeeId.has(log.employee_id)) {
          const e = byEmployeeId.get(log.employee_id);
          const prev = e.last_log?.scanned_at ? new Date(e.last_log.scanned_at).getTime() : 0;
          const cur = log.scanned_at ? new Date(log.scanned_at).getTime() : 0;
          if (cur >= prev) {
            e.last_log = { status: log.status, scanned_at: log.scanned_at };
            byEmployeeId.set(e.id, e);
          }
        }
      }
      await putAll('students', Array.from(byStudentId.values()));
      if (byEmployeeId.size) {
        await putAll('employees', Array.from(byEmployeeId.values()));
      }
    }

    if (payload.settings) await kvSet('attendance_settings', payload.settings);
    if (payload.server_time) await kvSet('attendance_last_pull_at', payload.server_time);
    await kvSet('attendance_online', true);
    return payload;
  }

  async function pushAttendanceOutbox(useSession = false) {
    const all = await getAll('attendance_outbox');
    const pending = all.filter((r) => !r.synced_at);
    if (!pending.length) return { accepted: 0, rejected: 0 };

    // Device token required for push (session-only scanners still use online POST directly)
    if (!getToken() && !useSession) {
      throw new Error('Pair a hybrid device token to upload offline attendance scans.');
    }

    const body = {
      scans: pending.slice(0, 500).map((r) => ({
        client_uuid: r.client_uuid,
        scan_token: r.scan_token,
        status: r.status,
        scanned_at: r.scanned_at,
      })),
    };

    const result = await hybridFetch('/attendance/scans', {
      method: 'POST',
      jsonBody: body,
    });

    const accepted = new Set(
      (result.results || []).filter((r) => r.accepted).map((r) => r.client_uuid)
    );
    const now = new Date().toISOString();
    for (const row of pending) {
      if (accepted.has(row.client_uuid)) {
        row.synced_at = now;
        const db = await openDb();
        const tx = db.transaction('attendance_outbox', 'readwrite');
        tx.objectStore('attendance_outbox').put(row);
        await txDone(tx);
      }
    }
    return result;
  }

  // ---------- OPAC ----------

  /**
   * @param {boolean} useSession
   * @param {{ forceFull?: boolean }} [opts]
   */
  async function pullOpacSnapshot(useSession = false, opts = {}) {
    const forceFull = !!opts.forceFull;
    if (forceFull) {
      await kvSet('opac_last_pull_at', null);
    }
    const since = forceFull ? null : await kvGet('opac_last_pull_at');
    const q = since ? `?since=${encodeURIComponent(since)}` : '';
    let payload;
    try {
      if (getToken() && !useSession) {
        payload = await hybridFetch(`/opac/snapshot${q}`);
      } else {
        payload = await webFetch(`/hybrid/opac/snapshot${q}`);
      }
    } catch (e) {
      if (getToken()) payload = await hybridFetch(`/opac/snapshot${q}`);
      else throw e;
    }

    const isFull = forceFull || payload.full_snapshot;
    if (isFull) {
      const db = await openDb();
      for (const store of ['opac_titles', 'opac_copies', 'opac_ebooks']) {
        const tx = db.transaction(store, 'readwrite');
        tx.objectStore(store).clear();
        await txDone(tx);
      }
    }

    await putAll('opac_titles', payload.titles || []);
    await putAll('opac_copies', payload.copies || []);
    await putAll('opac_ebooks', payload.ebooks || []);
    if (payload.filters) await kvSet('opac_filters', payload.filters);
    if (payload.server_time) await kvSet('opac_last_pull_at', payload.server_time);

    const counts = await pendingCounts();
    payload.stored_title_count = counts.opac_titles;
    payload.batch_title_count = (payload.titles || []).length;
    return payload;
  }

  function matchTitle(t, term, filters = {}) {
    const hay = [
      t.title_statement,
      t.main_author,
      t.isbn,
      t.call_number,
      t.subject_topic,
      t.genre,
      t.publisher,
      t.section,
      t.course,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    if (term && !hay.includes(term.toLowerCase())) return false;
    if (filters.course && filters.course !== 'all' && t.course !== filters.course) return false;
    if (filters.subject_topic && filters.subject_topic !== 'All' && t.subject_topic !== filters.subject_topic) return false;
    if (filters.genre && filters.genre !== 'All' && t.genre !== filters.genre) return false;
    if (filters.content_type && filters.content_type !== 'All' && t.content_type !== filters.content_type) return false;
    if (filters.section && filters.section !== 'All' && t.section !== filters.section) return false;
    return true;
  }

  /** Resolve catalog cover URL the same way OPAC Blade does (storage/ + defaultBook.png). */
  function resolveCoverUrl(raw) {
    const FALLBACK = '/images/defaultBook.png';
    if (!raw) return FALLBACK;
    const s = String(raw).trim();
    if (!s) return FALLBACK;
    if (/^https?:\/\//i.test(s) || s.startsWith('data:')) return s;
    // Already a public web path
    if (s.startsWith('/storage/') || s.startsWith('/images/')) return s;
    if (s.startsWith('storage/')) return '/' + s;
    // DB value is usually relative like "covers/xyz.jpg" under public/storage
    if (s.startsWith('/')) {
      // "/covers/..." was wrong historically — remap into storage when it looks like a cover path
      if (s.startsWith('/covers/') || s.startsWith('/ebooks/')) return '/storage' + s;
      return s;
    }
    return '/storage/' + s.replace(/^\//, '');
  }

  /**
   * Wire <img> failure once so a missing default never loops (blink storm).
   */
  function bindCoverImg(img, url) {
    if (!img) return;
    const FALLBACK = '/images/defaultBook.png';
    let triedFallback = false;
    img.onerror = function () {
      if (triedFallback) {
        img.onerror = null;
        img.removeAttribute('src');
        img.style.background = '#e5e7eb';
        return;
      }
      triedFallback = true;
      img.src = FALLBACK;
    };
    img.src = resolveCoverUrl(url);
  }

  async function searchOpacLocal(term, filters = {}, limit = 40) {
    const titles = await getAll('opac_titles');
    const q = String(term || '').trim();
    return titles.filter((t) => matchTitle(t, q, filters)).slice(0, limit);
  }

  async function getLocalTitle(id) {
    return getByKey('opac_titles', Number(id) || id);
  }

  async function getLocalCopiesForTitle(title, author, year) {
    const copies = await getAll('opac_copies');
    return copies.filter(
      (c) =>
        c.title_statement === title &&
        c.main_author === author &&
        String(c.pub_year || '') === String(year || '')
    );
  }

  // ---------- Catalog ----------

  async function enqueueCatalogRecord(payload) {
    const client_uuid = payload.client_uuid || uuid();
    const row = {
      client_uuid,
      payload,
      created_at: new Date().toISOString(),
      synced_at: null,
      last_error: null,
    };
    const db = await openDb();
    const tx = db.transaction('catalog_outbox', 'readwrite');
    tx.objectStore('catalog_outbox').put(row);
    await txDone(tx);
    return row;
  }

  async function pushCatalogOutbox(useSession = true) {
    const all = await getAll('catalog_outbox');
    const pending = all.filter((r) => !r.synced_at);
    if (!pending.length) return { accepted: 0, rejected: 0, results: [] };

    const body = {
      records: pending.slice(0, 50).map((r) => ({
        client_uuid: r.client_uuid,
        payload: r.payload,
      })),
    };

    let result;
    if (getToken() && !useSession) {
      result = await hybridFetch('/catalog/records', { method: 'POST', jsonBody: body });
    } else {
      result = await webFetch('/hybrid/catalog/records', { method: 'POST', jsonBody: body });
    }

    const now = new Date().toISOString();
    for (const r of result.results || []) {
      const row = pending.find((p) => p.client_uuid === r.client_uuid);
      if (!row) continue;
      if (r.accepted) {
        row.synced_at = now;
        row.last_error = null;
      } else {
        row.last_error = r.error || 'Rejected';
      }
      const db = await openDb();
      const tx = db.transaction('catalog_outbox', 'readwrite');
      tx.objectStore('catalog_outbox').put(row);
      await txDone(tx);
    }
    return result;
  }

  async function pendingCounts() {
    const att = (await getAll('attendance_outbox')).filter((r) => !r.synced_at).length;
    const cat = (await getAll('catalog_outbox')).filter((r) => !r.synced_at).length;
    const titles = (await getAll('opac_titles')).length;
    const students = (await getAll('students')).length;
    return { attendance: att, catalog: cat, opac_titles: titles, students };
  }

  async function runSyncCycle(opts = {}) {
    const summary = { online: isOnline(), pulled: {}, pushed: {}, error: null };
    if (!isOnline()) {
      summary.error = 'offline';
      return summary;
    }
    try {
      if (opts.attendance !== false) {
        try {
          summary.pulled.attendance = await pullAttendanceRoster(!!opts.useSession);
        } catch (e) {
          summary.pulled.attendance_error = e.message;
        }
        try {
          if (getToken()) summary.pushed.attendance = await pushAttendanceOutbox();
        } catch (e) {
          summary.pushed.attendance_error = e.message;
        }
      }
      if (opts.opac !== false) {
        try {
          summary.pulled.opac = await pullOpacSnapshot(!!opts.useSession);
        } catch (e) {
          summary.pulled.opac_error = e.message;
        }
      }
      if (opts.catalog !== false) {
        try {
          summary.pushed.catalog = await pushCatalogOutbox(opts.useSession !== false);
        } catch (e) {
          summary.pushed.catalog_error = e.message;
        }
      }
    } catch (e) {
      summary.error = e.message;
    }
    summary.pending = await pendingCounts();
    dispatchStatus(summary);
    return summary;
  }

  function dispatchStatus(detail) {
    try {
      global.dispatchEvent(new CustomEvent('ggcast:hybrid-status', { detail }));
    } catch { /* ignore */ }
  }

  function mountStatusBadge(targetEl) {
    if (!targetEl) return;
    const el = typeof targetEl === 'string' ? document.querySelector(targetEl) : targetEl;
    if (!el) return;

    const paint = async () => {
      const online = isOnline();
      const counts = await pendingCounts().catch(() => ({ attendance: 0, catalog: 0 }));
      const pending = (counts.attendance || 0) + (counts.catalog || 0);
      el.textContent = online
        ? pending
          ? `Online · ${pending} pending sync`
          : 'Online · hybrid ready'
        : pending
          ? `Offline · ${pending} queued`
          : 'Offline mode';
      el.classList.toggle('is-offline', !online);
      el.classList.toggle('has-pending', pending > 0);
      el.title = getToken() ? 'Device token paired' : 'No device token (session sync if signed in)';
    };

    paint();
    global.addEventListener('online', paint);
    global.addEventListener('offline', paint);
    global.addEventListener('ggcast:hybrid-status', paint);
    setInterval(paint, 15000);
  }

  function startAutoSync(opts = {}) {
    const intervalMs = opts.intervalMs || 60000;
    const tick = () => runSyncCycle(opts).catch(() => {});
    tick();
    const id = setInterval(tick, intervalMs);
    global.addEventListener('online', tick);
    return () => clearInterval(id);
  }

  // form helpers
  function formToPayload(form) {
    const fd = new FormData(form);
    const payload = {};
    const marc = {};
    const copies = [];
    const program_ids = [];

    for (const [key, value] of fd.entries()) {
      if (key === '_token' || key === 'cover_image') continue;
      if (key.startsWith('marc[')) {
        // marc[tag][sub][]
        const m = key.match(/^marc\[([^\]]+)\]\[([^\]]+)\](?:\[\])?$/);
        if (m) {
          const tag = m[1];
          const sub = m[2];
          marc[tag] = marc[tag] || {};
          marc[tag][sub] = marc[tag][sub] || [];
          if (String(value).trim() !== '') marc[tag][sub].push(String(value));
        }
        continue;
      }
      if (key.startsWith('copies[')) {
        const m = key.match(/^copies\[(\d+)\]\[([^\]]+)\]$/);
        if (m) {
          const i = Number(m[1]);
          copies[i] = copies[i] || {};
          copies[i][m[2]] = String(value);
        }
        continue;
      }
      if (key === 'program_ids[]' || key === 'program_ids') {
        if (value) program_ids.push(Number(value));
        continue;
      }
      if (key === 'multiple_copies') {
        payload.multiple_copies = true;
        continue;
      }
      payload[key] = value;
    }

    payload.marc = marc;
    if (copies.length) payload.copies = copies.filter(Boolean);
    if (program_ids.length) payload.program_ids = program_ids;
    return payload;
  }

  global.GgcastHybrid = {
    uuid,
    openDb,
    getToken,
    setToken,
    isOnline,
    hybridFetch,
    webFetch,
    findLocalStudent,
    findLocalEmployee,
    findLocalPatron,
    recordLocalAttendance,
    pullAttendanceRoster,
    pushAttendanceOutbox,
    pullOpacSnapshot,
    searchOpacLocal,
    getLocalTitle,
    getLocalCopiesForTitle,
    resolveCoverUrl,
    bindCoverImg,
    enqueueCatalogRecord,
    pushCatalogOutbox,
    pendingCounts,
    runSyncCycle,
    mountStatusBadge,
    startAutoSync,
    formToPayload,
    kvGet,
    kvSet,
  };
})(typeof window !== 'undefined' ? window : globalThis);
