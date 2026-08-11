<!DOCTYPE html>
<html>
<head>
  <title>Library Attendance & Book RFID</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <link rel="stylesheet" href="{{ asset('css/attendance/scan.css') }}">
  <link rel="stylesheet" href="{{ asset('js/hybrid/status.css') }}">
  <link href="{{ asset('vendor/fontsource/poppins/latin-400.css') }}" rel="stylesheet">
  <link href="{{ asset('vendor/fontsource/poppins/latin-600.css') }}" rel="stylesheet">
  <link href="{{ asset('vendor/fontsource/poppins/latin-700.css') }}" rel="stylesheet">
  <style>
    .marquee-container {
      width: 100%;
      overflow: hidden;
      background-color: #222;
      color: #fff;
      border-top: 2px solid #444;
      padding: 15px 0;
      box-sizing: border-box;
    }

    .marquee {
      display: inline-block;
      white-space: nowrap;
      padding-left: 100%;
      animation: scroll-text 15s linear infinite;
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 24px;
    }

    @keyframes scroll-text {
      0% { transform: translateX(0%); }
      100% { transform: translateX(-100%); }
    }

    .hybrid-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
      justify-content: space-between;
      padding: 0.4rem 0.75rem;
      background: #111;
      color: #eee;
      font-size: 12px;
    }
  </style>
</head>
<body>
  <div class="hybrid-bar">
    <span id="hybridStatus" class="hybrid-status-badge">Hybrid…</span>
    <details class="hybrid-pair-panel" style="margin:0; background:#1a1a1a; color:#ddd; border-color:#444;">
      <summary style="cursor:pointer;">Pair hybrid device</summary>
      <div style="margin-top:0.4rem;">
        <input type="password" id="hybridTokenInput" placeholder="Paste device token" autocomplete="off">
        <button type="button" id="hybridTokenSave">Save</button>
        <button type="button" id="hybridTokenClear">Clear</button>
        <button type="button" id="hybridSyncNow">Sync now</button>
      </div>
    </details>
  </div>
  <div id="hybridOfflineBanner" class="hybrid-offline-banner" style="display:none;">
    Offline mode — scans are stored on this device and will upload when online.
  </div>

  <header>
    <div class="header">
      <div class="logo-title">
        <img src="{{ asset('images/pantasLogo.png') }}" alt="Logo">
        <div class="system-title">POWERED BY PANTAS</div>
        <a href="{{ route('book.index') }}" class="home-button" hidden>Home</a>
      </div>
    </div>
  </header>

  <div class="main">
    <div class="sidebar">
      <div class="date" id="currentDate">Date</div>
      <div class="time" id="currentTime">--:--:--</div>

      <div class="profile-pic">
        @if(isset($student) && $student->profile_picture)
          <img src="{{ asset($student->profile_picture) }}" alt="Profile">
        @else
          <img src="{{ asset('images/2x2_undifined_gender.jpg') }}" alt="Default Profile">
        @endif
      </div>

      @if(isset($student))
        <div class="name-box">
          <div class="student-name">{{ $student->firstname }} {{ $student->lastname }}</div>
          <div class="label">Name</div>
          <div class="status-button {{ strtolower($status) === 'out' ? 'status-out' : '' }}">
            {{ $status }}
          </div>
          <div class="timestamp">
            {{ isset($log) ? \Carbon\Carbon::parse($log->scanned_at)->format('Y-m-d h:i:s A') : '' }}
          </div>
        </div>
      @endif

      @if(isset($book))
        <div class="name-box">
          <div class="student-name">{{ $book->title_statement }}</div>
          <div class="label">Book Title</div>
          <div class="status-button {{ strtolower($bookStatus) === 'not checked out' ? 'status-out' : '' }}">
            {{ $bookStatus }}
          </div>
        </div>
      @endif

      @if(session('error'))
        <div class="name-box">
          <div class="student-name">{{ session('error') }}</div>
          <div class="label">Error</div>
        </div>
      @endif
    </div>

    <div class="right-content">
      <form id="scanForm">
        @csrf
        <textarea name="qrcode" id="qrcode" style="opacity:0; position:absolute;" autofocus autocomplete="off"></textarea>
      </form>

      <video autoplay loop controls class="ads-vid">
        <source src="{{ asset('videos/area51_product_slideshow.mp4') }}" type="video/mp4">
        Your browser does not support the video tag.
      </video>
    </div>
  </div>

  <footer>
    <div class="footer1">
      <div class="footer-logo">
        <div class="marquee-container">
          <div class="marquee">
            Welcome to Governor Generoso College of Arts, Sciences and Technology
          </div>
        </div>
      </div>
    </div>
  </footer>

  <audio id="alertSound" src="{{ asset('sounds/alert.wav') }}" type="audio/wav"></audio>

  <div id="feedbackModal" class="section-modal" aria-hidden="true">
    <div class="modal-content feedback-card" role="dialog" aria-labelledby="feedbackModalTitle">
      <h2 id="feedbackModalTitle">How was your library experience?</h2>
      <div class="feedback-options">
        <button type="button" data-rating="excellent">😊<span>Excellent</span></button>
        <button type="button" data-rating="good">🙂<span>Good</span></button>
        <button type="button" data-rating="medium">😐<span>Medium</span></button>
        <button type="button" data-rating="poor">🙁<span>Poor</span></button>
        <button type="button" data-rating="very_bad">😠<span>Very Bad</span></button>
      </div>
      <button type="button" id="declineFeedback" class="decline-btn">Skip</button>
    </div>
  </div>

  <script src="{{ asset('js/hybrid/hybrid.js') }}"></script>
  <script>
    window.HYBRID_API_BASE = @json(url('/api/hybrid'));
    window.HYBRID_CSRF = @json(csrf_token());

    const LOGOUT_FEEDBACK_ENABLED = @json($logoutFeedbackEnabled ?? true);
    const feedbackModal = document.getElementById('feedbackModal');
    let currentStudentId = null;
    let clearDisplayTimer = null;

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw-hybrid.js', { scope: '/' }).catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function () {
      const H = window.GgcastHybrid;
      H.mountStatusBadge('#hybridStatus');
      H.startAutoSync({ attendance: true, opac: false, catalog: false, intervalMs: 45000 });

      const banner = document.getElementById('hybridOfflineBanner');
      const paintBanner = () => {
        banner.style.display = H.isOnline() ? 'none' : 'block';
      };
      paintBanner();
      window.addEventListener('online', paintBanner);
      window.addEventListener('offline', paintBanner);

      document.getElementById('hybridTokenSave')?.addEventListener('click', () => {
        const v = document.getElementById('hybridTokenInput').value.trim();
        if (!v) return alert('Paste a token from Admin → Hybrid devices.');
        H.setToken(v);
        document.getElementById('hybridTokenInput').value = '';
        H.runSyncCycle({ attendance: true, opac: false, catalog: false }).then(() => alert('Token saved. Roster sync started.'));
      });
      document.getElementById('hybridTokenClear')?.addEventListener('click', () => {
        H.setToken('');
        alert('Token cleared on this browser.');
      });
      document.getElementById('hybridSyncNow')?.addEventListener('click', () => {
        H.runSyncCycle({ attendance: true, opac: false, catalog: false })
          .then((s) => alert(s.error === 'offline' ? 'Still offline.' : 'Sync finished.'))
          .catch((e) => alert(e.message || 'Sync failed'));
      });

      const input = document.getElementById('qrcode');
      const profileImg = document.querySelector('.profile-pic img');
      const sidebar = document.querySelector('.sidebar');
      const alertSound = document.getElementById('alertSound');
      const hybridBar = document.querySelector('.hybrid-bar');
      let isCooldown = false;

      /** Scanner auto-focus must not steal from pairing, modals, or other UI. */
      function shouldHoldQrFocus() {
        if (feedbackModal && feedbackModal.style.display === 'flex') return false;
        if (document.activeElement) {
          const el = document.activeElement;
          if (el === input) return true;
          if (hybridBar && hybridBar.contains(el)) return false;
          const tag = (el.tagName || '').toLowerCase();
          if (tag === 'input' || tag === 'textarea' || tag === 'select' || tag === 'button') return false;
          if (el.isContentEditable) return false;
        }
        // Pair panel open — leave focus alone so user can click/type
        const pairPanel = hybridBar?.querySelector('details');
        if (pairPanel && pairPanel.open) return false;
        return true;
      }

      function focusQrScanner() {
        if (!shouldHoldQrFocus()) return;
        input.focus({ preventScroll: true });
      }

      setInterval(focusQrScanner, 100);
      focusQrScanner();

      // Resume scanner focus when pairing panel is closed
      hybridBar?.querySelector('details')?.addEventListener('toggle', (e) => {
        if (!e.target.open) {
          setTimeout(focusQrScanner, 50);
        }
      });

      function clearDisplay() {
        if (feedbackModal && feedbackModal.style.display === 'flex') {
          return;
        }
        profileImg.src = "{{ asset('images/2x2_undifined_gender.jpg') }}";
        document.querySelectorAll('.name-box').forEach(box => box.remove());
        currentStudentId = null;
      }

      function scheduleClear(delayMs) {
        if (clearDisplayTimer) {
          clearTimeout(clearDisplayTimer);
        }
        clearDisplayTimer = setTimeout(clearDisplay, delayMs);
      }

      function showLogoutFeedback() {
        if (!LOGOUT_FEEDBACK_ENABLED || !feedbackModal || !currentStudentId) {
          scheduleClear(2000);
          return;
        }
        if (clearDisplayTimer) {
          clearTimeout(clearDisplayTimer);
          clearDisplayTimer = null;
        }
        setTimeout(() => {
          feedbackModal.style.display = 'flex';
          feedbackModal.setAttribute('aria-hidden', 'false');
        }, 500);
      }

      function applyScanData(data) {
        if (feedbackModal && feedbackModal.style.display === 'flex') {
          closeFeedbackModal();
        }
        clearDisplay();

        if (data.type === 'student' || data.type === 'employee') {
          const person = data.type === 'student' ? data.student : data.employee;
          currentStudentId = data.type === 'student' ? data.student_id : null;
          const pic = person.profile_picture
            ? "{{ asset('') }}" + person.profile_picture
            : "{{ asset('images/2x2_undifined_gender.jpg') }}";
          profileImg.src = pic;

          const div = document.createElement('div');
          div.classList.add('name-box');
          const offlineTag = data.offline ? ' <small>(queued offline)</small>' : '';
          const staffTag = data.type === 'employee' ? ' <small>(Staff)</small>' : '';
          div.innerHTML = `
            <div class="student-name">${person.firstname} ${person.lastname}${staffTag}${offlineTag}</div>
            <div class="label">Name</div>
            <div class="status-button ${data.status.toLowerCase() === 'out' ? 'status-out' : ''}">${data.status}</div>
            <div class="timestamp">${data.log.scanned_at}</div>
          `;
          sidebar.appendChild(div);

          const feedbackOn = data.logout_feedback_enabled ?? LOGOUT_FEEDBACK_ENABLED;
          if (data.type === 'student' && data.status.toLowerCase() === 'out' && feedbackOn && !data.offline) {
            showLogoutFeedback();
          } else {
            scheduleClear(2000);
          }
        } else if (data.type === 'book') {
          if (data.bookStatus.toLowerCase() === 'not checked out') {
            alertSound.play().catch(() => {});
          }
          const div = document.createElement('div');
          div.classList.add('name-box');
          div.innerHTML = `
            <div class="student-name">${data.book.title_statement}</div>
            <div class="label">Book Title</div>
            <div class="status-button ${data.bookStatus.toLowerCase() === 'not checked out' ? 'status-out' : ''}">${data.bookStatus}</div>
          `;
          sidebar.appendChild(div);
          scheduleClear(2000);
        } else if (data.type === 'error') {
          const div = document.createElement('div');
          div.classList.add('name-box');
          div.innerHTML = `
            <div class="student-name">${data.message}</div>
            <div class="label">Error</div>
          `;
          sidebar.appendChild(div);
          scheduleClear(2000);
        }
      }

      async function processScan(raw) {
        if (!H.isOnline()) {
          return H.recordLocalAttendance(raw);
        }

        const formData = new FormData();
        formData.append('qrcode', raw);
        formData.append('_token', '{{ csrf_token() }}');

        try {
          const res = await fetch("{{ route('attendance.process') }}", {
            method: 'POST',
            body: formData
          });
          return await res.json();
        } catch (err) {
          // Network failure mid-request → queue offline
          return H.recordLocalAttendance(raw);
        }
      }

      input.addEventListener('keypress', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        if (isCooldown) return;
        isCooldown = true;
        setTimeout(() => { isCooldown = false; }, 100);

        const raw = input.value.trim().replace(/\r/g, '');
        processScan(raw)
          .then(applyScanData)
          .catch(err => console.error(err))
          .finally(() => { input.value = ''; });
      });

      function updateDateTime() {
        const now = new Date();
        const dateEl = document.getElementById('currentDate');
        const timeEl = document.getElementById('currentTime');
        if (dateEl && timeEl) {
          dateEl.textContent = now.toLocaleDateString('en-GB', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
          });
          timeEl.textContent = now.toLocaleTimeString('en-US');
        }
      }
      updateDateTime();
      setInterval(updateDateTime, 1000);

      const feedbackButtons = document.querySelectorAll('.feedback-options button');
      const declineBtn = document.getElementById('declineFeedback');

      function closeFeedbackModal() {
        if (!feedbackModal) return;
        feedbackModal.style.display = 'none';
        feedbackModal.setAttribute('aria-hidden', 'true');
      }

      function sendFeedback(rating = null, declined = 0) {
        if (!currentStudentId) {
          closeFeedbackModal();
          clearDisplay();
          input.focus();
          return;
        }

        fetch("{{ route('attendance.feedback.store') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            student_id: currentStudentId,
            rating: rating,
            declined: declined ? 1 : 0,
          }),
        }).catch(err => console.error(err)).finally(() => {
          closeFeedbackModal();
          clearDisplay();
          input.focus();
        });
      }

      feedbackButtons.forEach(btn => {
        btn.addEventListener('click', function () {
          sendFeedback(this.dataset.rating, 0);
        });
      });

      declineBtn?.addEventListener('click', function () {
        sendFeedback(null, 1);
      });
    });
  </script>
</body>
</html>
