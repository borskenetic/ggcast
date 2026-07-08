@php
    $resourceTypes = config('reports.ereport_resource_types', ['Electronic', 'Printed']);
    $publicationScopes = config('reports.ereport_publication_scopes', ['Foreign', 'Local']);
    $frequencies = config('reports.ereport_frequencies', ['Annual', 'Semi-Annual', 'Quarterly', 'Monthly', 'Weekly', 'Other']);
    $subscriptionTypes = config('reports.ereport_subscription_types', ['Renewable', 'One-time', 'Open Access']);
    $ebook = $ebook ?? null;
@endphp

<section class="ebooks-form-section">
    <h2 class="ebooks-form-section__title">Report 3 (journal subscription)</h2>
    <p class="ebooks-form-hint mb-3">These fields appear on the Library Holdings Report — Report 3 sheet.</p>
    <div class="ebooks-form-grid">
        <div>
            <label for="resource_type" class="form-label">Type</label>
            <select id="resource_type" name="resource_type" class="form-select">
                @foreach($resourceTypes as $type)
                    <option value="{{ $type }}" @selected(old('resource_type', $ebook->resource_type ?? 'Electronic') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="publication_scope" class="form-label">Publication</label>
            <select id="publication_scope" name="publication_scope" class="form-select">
                <option value="">— Select —</option>
                @foreach($publicationScopes as $scope)
                    <option value="{{ $scope }}" @selected(old('publication_scope', $ebook->publication_scope ?? '') === $scope)>{{ $scope }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="ebooks-form-grid mt-0">
        <div>
            <label for="frequency" class="form-label">Frequency</label>
            <select id="frequency" name="frequency" class="form-select">
                <option value="">— Select —</option>
                @foreach($frequencies as $frequency)
                    <option value="{{ $frequency }}" @selected(old('frequency', $ebook->frequency ?? '') === $frequency)>{{ $frequency }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="subscription_type" class="form-label">Regular subscription</label>
            <select id="subscription_type" name="subscription_type" class="form-select">
                <option value="">— Select —</option>
                @foreach($subscriptionTypes as $subscription)
                    <option value="{{ $subscription }}" @selected(old('subscription_type', $ebook->subscription_type ?? '') === $subscription)>{{ $subscription }}</option>
                @endforeach
            </select>
        </div>
    </div>
</section>
