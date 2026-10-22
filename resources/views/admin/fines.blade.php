@extends('layouts.sec')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/books/index.css') }}">
<style>
    #holiday-calendar { min-height: 420px; }
    #calendarWrapper.is-open { display: block !important; }
    #calendarError { display: none; }
    #calendarError.is-visible { display: block; }
</style>
@endsection

@section('content')
<div class="container">
    <h3 class="mb-4">Fine Settings</h3>

    <div class="row">

        <div class="col-md-6">

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('fines.update') }}">
                @csrf

                <h5 class="mb-3">Regular books</h5>

                <div class="mb-3">
                    <label class="form-label">Fine per Day (₱)</label>
                    <input type="number" step="0.01" name="fine_per_day"
                           class="form-control"
                           value="{{ $settings->fine_per_day ?? '' }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Maximum Fine (₱)</label>
                    <input type="number" step="0.01" name="max_fine"
                           class="form-control"
                           value="{{ $settings->max_fine ?? '' }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Loan duration (business days)</label>
                    <input type="number" name="loan_duration_days" min="1"
                           value="{{ $settings->loan_duration_days ?? 7 }}"
                           class="form-control" required>
                    <div class="form-text">Due date for copies that are not on reserve. Self-checkout uses this length. Weekends and holidays are skipped.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Grace Period (Days)</label>
                    <input type="number" name="grace_period_days"
                           class="form-control"
                           value="{{ $settings->grace_period_days ?? 0 }}" required>
                </div>

                <hr>

                <h5 class="mb-2">Reserve books</h5>
                <p class="text-muted small">
                    Used when the copy’s sublocation contains “Reserve”. These fines and due dates
                    are separate from the regular policy above.
                </p>

                <div class="mb-3">
                    <label class="form-label">Reserve fine per day (₱)</label>
                    <input type="number" step="0.01" name="reserve_fine_per_day"
                           class="form-control"
                           value="{{ old('reserve_fine_per_day', $settings->reserve_fine_per_day ?? $settings->fine_per_day ?? '') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Reserve maximum fine (₱)</label>
                    <input type="number" step="0.01" name="reserve_max_fine"
                           class="form-control"
                           value="{{ old('reserve_max_fine', $settings->reserve_max_fine ?? '') }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Reserve loan duration (business days)</label>
                    <input type="number" name="reserve_loan_duration_days" min="1"
                           value="{{ old('reserve_loan_duration_days', $settings->reserve_loan_duration_days ?? 1) }}"
                           class="form-control" required>
                    <div class="form-text">Due date for reserve copies. Self-checkout always uses this length. Staff can still set a different date at the circulation desk.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Reserve grace period (days)</label>
                    <input type="number" name="reserve_grace_period_days" min="0"
                           class="form-control"
                           value="{{ old('reserve_grace_period_days', $settings->reserve_grace_period_days ?? 0) }}" required>
                </div>

                <button class="btn btn-primary">Save Fine Policy</button>
            </form>

            @if($settings)
                <p class="text-muted mt-3">
                    Effective since: {{ $settings->effective_from }}
                </p>
            @endif

        </div>

        <div class="col-md-6">

            <h4>Holiday Calendar</h4>
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <button type="button" id="toggleCalendar" class="btn btn-secondary btn-sm">
                    Show Calendar
                </button>
                <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#holidayListModal">
                    View Holidays
                </button>
            </div>

            <div id="calendarError" class="alert alert-warning" role="alert"></div>

            <div id="calendarWrapper" style="display: none;">
                <div id="holiday-calendar"></div>
            </div>

        </div>

    </div>
</div>

<div class="modal fade" id="holidayModal" tabindex="-1" aria-labelledby="holidayModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="holidayModalLabel">Set Holiday</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="holidayMessage"></p>
        <div id="holidayNameField">
            <label class="form-label" for="holidayName">Holiday Name</label>
            <input type="text" id="holidayName" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmHolidayBtn" class="btn btn-primary">Confirm</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="holidayListModal" tabindex="-1" aria-labelledby="holidayListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="holidayListModalLabel">Holiday List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex overflow-auto mb-3" id="monthTabs">
                    <button type="button" class="btn btn-outline-primary btn-sm me-2 month-tab active" data-month="all">All</button>
                    @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i => $label)
                        <button type="button" class="btn btn-outline-primary btn-sm me-2 month-tab" data-month="{{ $i }}">{{ $label }}</button>
                    @endforeach
                </div>
                <ul class="list-group" id="holidayList"></ul>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@php
    $fcLocal = public_path('vendor/fullcalendar/index.global.min.js');
    $fcSrc = file_exists($fcLocal)
        ? asset('vendor/fullcalendar/index.global.min.js')
        : 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js';
@endphp
<script src="{{ $fcSrc }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('holiday-calendar');
    const wrapper = document.getElementById('calendarWrapper');
    const toggleBtn = document.getElementById('toggleCalendar');
    const calendarError = document.getElementById('calendarError');
    const holidayModalEl = document.getElementById('holidayModal');
    const holidayListModalEl = document.getElementById('holidayListModal');

    const holidaysListUrl = @json(route('holidays.list'));
    const holidaysAllUrl = @json(route('holidays.all'));
    const holidaysToggleUrl = @json(route('holidays.toggle'));
    const csrfToken = @json(csrf_token());

    let calendar = null;
    let selectedDate = null;
    let allHolidays = [];
    let currentMonth = 'all';

    function showCalendarError(message) {
        calendarError.textContent = message;
        calendarError.classList.add('is-visible');
    }

    function hideCalendarError() {
        calendarError.classList.remove('is-visible');
        calendarError.textContent = '';
    }

    toggleBtn.addEventListener('click', function () {
        const opening = wrapper.style.display === 'none' || !wrapper.classList.contains('is-open');
        if (opening) {
            wrapper.style.display = 'block';
            wrapper.classList.add('is-open');
            toggleBtn.textContent = 'Hide Calendar';
            if (calendar) {
                requestAnimationFrame(function () {
                    calendar.updateSize();
                });
            }
        } else {
            wrapper.style.display = 'none';
            wrapper.classList.remove('is-open');
            toggleBtn.textContent = 'Show Calendar';
        }
    });

    if (typeof FullCalendar === 'undefined') {
        showCalendarError('Calendar library failed to load. Run npm install and refresh, or check your network connection.');
        return;
    }

    fetch(holidaysListUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('Could not load holidays (HTTP ' + res.status + ').');
            }
            return res.json();
        })
        .then(function (data) {
            hideCalendarError();
            const events = (Array.isArray(data) ? data : []).map(function (h) {
                return {
                    title: h.name || 'Holiday',
                    start: h.holiday_date,
                    allDay: true,
                    color: '#dc3545',
                };
            });

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                events: events,
                dateClick: function (info) {
                    selectedDate = info.dateStr;
                    const event = calendar.getEvents().find(function (e) {
                        return e.startStr === selectedDate;
                    });
                    const isHoliday = !!event;
                    const modal = bootstrap.Modal.getOrCreateInstance(holidayModalEl);

                    if (isHoliday) {
                        document.getElementById('holidayMessage').innerText =
                            'This date is already a holiday. Do you want to remove it?';
                        document.getElementById('holidayNameField').style.display = 'none';
                    } else {
                        document.getElementById('holidayMessage').innerText =
                            'Do you want to set this date as a holiday?';
                        document.getElementById('holidayNameField').style.display = 'block';
                        document.getElementById('holidayName').value = '';
                    }

                    modal.show();
                },
            });

            calendar.render();
        })
        .catch(function (err) {
            showCalendarError(err.message || 'Could not load the holiday calendar.');
        });

    document.getElementById('confirmHolidayBtn').addEventListener('click', function () {
        if (!selectedDate || !calendar) {
            return;
        }

        const name = document.getElementById('holidayName').value;

        fetch(holidaysToggleUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                date: selectedDate,
                name: name,
            }),
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Could not save holiday.');
                }
                return res.json();
            })
            .then(function (data) {
                if (data.status === 'added') {
                    calendar.addEvent({
                        title: name || 'Holiday',
                        start: selectedDate,
                        allDay: true,
                        color: '#dc3545',
                    });
                }
                if (data.status === 'removed') {
                    calendar.getEvents().forEach(function (event) {
                        if (event.startStr === selectedDate) {
                            event.remove();
                        }
                    });
                }
                bootstrap.Modal.getInstance(holidayModalEl)?.hide();
            })
            .catch(function () {
                alert('Could not update holiday. Please try again.');
            });
    });

    holidayListModalEl.addEventListener('show.bs.modal', function () {
        fetch(holidaysAllUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Could not load holiday list.');
                }
                return res.json();
            })
            .then(function (data) {
                allHolidays = Array.isArray(data) ? data : [];
                renderHolidayTable();
            })
            .catch(function () {
                document.getElementById('holidayList').innerHTML =
                    '<li class="list-group-item text-center text-muted">Could not load holidays.</li>';
            });
    });

    function renderHolidayTable() {
        const list = document.getElementById('holidayList');
        list.innerHTML = '';

        let filtered = allHolidays;
        if (currentMonth !== 'all') {
            filtered = allHolidays.filter(function (h) {
                return new Date(h.holiday_date).getMonth() === currentMonth;
            });
        }

        if (filtered.length === 0) {
            list.innerHTML = '<li class="list-group-item text-center text-muted">No holidays</li>';
            return;
        }

        filtered.forEach(function (h) {
            const d = new Date(h.holiday_date);
            const formatted = d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between';
            li.innerHTML = '<span>' + formatted + '</span><span class="text-muted">' + (h.name || 'Holiday') + '</span>';
            list.appendChild(li);
        });
    }

    document.querySelectorAll('.month-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.month-tab').forEach(function (b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            const month = this.dataset.month;
            currentMonth = month === 'all' ? 'all' : parseInt(month, 10);
            renderHolidayTable();
        });
    });
});
</script>
@endsection
