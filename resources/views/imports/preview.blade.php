@extends('layouts.app')
@section('title', 'Review import file')
@section('content')
@php($template = \App\Exports\ImportTemplateDefinition::for($entity))
<div class="page import-preview-page">
    <div class="page-head flex justify-between items-center">
        <div><div class="page-title">Review {{ $entity === 'customers' ? 'customers' : 'items' }}</div><div class="page-subtitle">Check your file before anything is added to your workspace.</div></div>
        <a class="btn btn-sm btn-ghost" href="{{ route($entity.'.index') }}">Back to {{ $entity === 'customers' ? 'Customers' : 'Items' }}</a>
    </div>

    <ol class="import-template-steps import-preview-steps" aria-label="Import in three steps">
        <li class="is-complete"><span>✓</span> Download template</li><li class="is-complete"><span>✓</span> Upload file</li><li aria-current="step"><span>3</span> Review file</li>
    </ol>

    @if(session('success'))<div class="import-notice import-notice-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="import-notice import-notice-error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="import-notice import-notice-info" aria-label="Import safety notice">
        @if($batch->status === 'completed')
            <strong>Import complete</strong><span>{{ number_format($resultCounts['imported']) }} records added; {{ number_format($resultCounts['skipped']) }} rows skipped. The rows below show the final result.</span>
        @else
            <strong>Preview only</strong><span>Nothing has been added to your {{ $entity }} list yet. Review the results and choose how to handle rows that need attention.</span>
        @endif
    </section>
    <div class="import-batch-card"><span>File</span><strong>{{ $batch->original_filename }}</strong><span>Uploaded {{ $batch->created_at->format('d M Y, H:i') }}</span></div>

    @if($mappingNeeded)
        <section class="card import-mapping-card">
            <div class="card-header"><h2 class="card-title">Match your column headings</h2></div>
            <div class="card-body">
                <p>Some headings differ from the template. Choose which file column matches each field. You can leave optional fields blank.</p>
                <form method="POST" action="{{ route($entity.'.import.mapping', $batch) }}">
                    @csrf
                    <div class="import-mapping-list">
                        @foreach($expected as $key => $field)
                            @php($selected = $auto['mapping'][$key] ?? '')
                            <label class="import-mapping-row"><span><strong>{{ $field['label'] }}</strong><small>{{ $field['required'] ? 'Required' : 'Optional' }}</small></span>
                                <select name="mapping[{{ $key }}]" class="form-control" {{ $field['required'] ? 'required' : '' }}>
                                    @if(!$field['required'])<option value="">Skip this column</option>@endif
                                    @foreach($batch->headers as $index => $header)<option value="{{ $index }}" @selected((string)$selected === (string)$index)>{{ $header !== '' ? $header : 'Column '.($index + 1) }}</option>@endforeach
                                </select>
                            </label>
                        @endforeach
                    </div>
                    <div class="flex" style="gap:10px;margin-top:16px;"><button class="btn btn-primary">Match headings and check rows</button><a class="btn btn-ghost" href="{{ route($entity.'.index') }}">Cancel</a></div>
                </form>
            </div>
        </section>
    @else
        <div class="stat-grid import-summary-grid">
            <div class="stat-card"><div class="stat-label">Total rows</div><div class="stat-value">{{ number_format($batch->total_rows) }}</div></div>
            <div class="stat-card import-stat-ready"><div class="stat-label">Ready</div><div class="stat-value">{{ number_format($batch->ready_rows) }}</div></div>
            <div class="stat-card import-stat-warning"><div class="stat-label">Warnings</div><div class="stat-value">{{ number_format($batch->warning_rows) }}</div></div>
            <div class="stat-card import-stat-error"><div class="stat-label">Errors</div><div class="stat-value">{{ number_format($batch->error_rows) }}</div></div>
        </div>
        @if($batch->status === 'validated')
            @if($batch->ready_rows > 0 || $batch->warning_rows > 0 || $batch->error_rows > 0)
                <section class="card import-process-card">
                    <div class="card-header"><h2 class="card-title">Import reviewed rows</h2></div>
                    <div class="card-body">
                        <p>Choose whether to add valid rows while skipping warnings/errors, or stop if any rows need attention. Records are saved together as one operation.</p>
                        <form method="POST" action="{{ route($entity.'.import.process', $batch) }}">
                            @csrf
                            <label class="import-process-option"><input type="radio" name="mode" value="import_valid" checked><span><strong>Import valid rows</strong><small>Add {{ number_format($batch->ready_rows) }} ready rows; skip {{ number_format($batch->warning_rows + $batch->error_rows) }} warning/error rows.</small></span></label>
                            <label class="import-process-option"><input type="radio" name="mode" value="stop_on_issues"><span><strong>Stop if there are warnings or errors</strong><small>Add nothing unless every row is ready.</small></span></label>
                            <button class="btn btn-primary" type="submit">Import {{ $entity === 'customers' ? 'customers' : 'items' }}</button>
                        </form>
                    </div>
                </section>
            @endif
        @endif
        <section class="card">
            <div class="card-header"><h2 class="card-title">Rows from your file</h2></div>
            <div class="card-body">
                <nav class="import-preview-tabs" aria-label="Filter preview rows">
                    @php($tabOptions = $batch->status === 'completed'
                        ? ['all' => 'All', 'imported' => 'Imported', 'skipped' => 'Skipped', 'errors' => 'Errors']
                        : ['all' => 'All', 'ready' => 'Ready', 'warnings' => 'Warnings', 'errors' => 'Errors'])
                    @foreach($tabOptions as $key => $label)
                        <a class="{{ $tab === $key ? 'active' : '' }}" href="{{ route($entity.'.import.preview', [$batch, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="table-wrap import-preview-table-wrap"><table class="tbl import-preview-table"><thead><tr><th>File row</th><th>Status</th><th>{{ $entity === 'customers' ? 'Customer name' : 'Item name' }}</th><th>Messages</th></tr></thead><tbody>
                    @forelse($rows as $row)
                        @php($nameKey = $entity === 'customers' ? 'customer_name' : 'item_name')
                        <tr>
                            <td>{{ $row->row_number }}</td>
                            <td><span class="import-status import-status-{{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
                            <td>{{ $row->payload[$nameKey] ?? '—' }}</td>
                            <td>
                                @foreach($row->errors ?? [] as $message)<div class="import-row-message is-error">{{ $message }}</div>@endforeach
                                @foreach($row->warnings ?? [] as $message)<div class="import-row-message is-warning">{{ $message }}</div>@endforeach
                                @if(empty($row->errors) && empty($row->warnings))<span class="muted">Ready to import</span>@endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="4">No rows in this category.</td></tr>@endforelse
                </tbody></table></div>
                {{ $rows->links('partials.pagination') }}
            </div>
        </section>
    @endif
</div>
@endsection
