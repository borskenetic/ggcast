<a href="{{ route('logs.index') }}">Circulation</a>
@can('isAdmin')
<a href="{{ route('fines.outstanding') }}">Outstanding Fines</a>
@endcan
<a href="{{ route('catalog.copy.openlibrary.form') }}">Copy Cataloging</a>
<a href="{{ route('rfid.scanner') }}" hidden>RFID Scanner</a>
<a href="{{ route('reports.library_holdings.create') }}">Library Holdings Report</a>
<a href="{{ route('book.report.download') }}">Download Book Report (PDF)</a>
@can('isAdmin')
<a href="{{ route('fines.edit') }}">Fines and Due Dates</a>
<a href="{{ route('circulation.borrow_limits.edit') }}">Borrow Limits</a>
@endcan
