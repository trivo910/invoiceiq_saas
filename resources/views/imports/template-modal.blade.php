@php($fields = \App\Exports\ImportTemplateDefinition::for($entity)['fields'])
<button class="btn btn-sm btn-ghost import-template-trigger" type="button" aria-haspopup="dialog" aria-controls="{{ $entity }}ImportDialog" onclick="document.getElementById('{{ $entity }}ImportDialog').showModal()">
    Import <span aria-hidden="true">▾</span>
</button>
<dialog id="{{ $entity }}ImportDialog" class="import-template-dialog" aria-labelledby="{{ $entity }}ImportTitle">
    <div class="import-template-head">
        <div><div class="page-title" id="{{ $entity }}ImportTitle">Import {{ $entity === 'customers' ? 'customers' : 'items' }}</div><p class="page-subtitle">Start by downloading a sample file and filling in your records.</p></div>
        <form method="dialog"><button class="btn btn-sm btn-ghost" aria-label="Close import guide">Close</button></form>
    </div>
    <ol class="import-template-steps" aria-label="Import in three steps">
        <li class="is-complete"><span>✓</span> Download template</li><li aria-current="step"><span>2</span> Upload file</li><li><span>3</span> Review file</li>
    </ol>
    <div class="import-template-content">
        <h2>Upload your filled-in file</h2>
        <p>Choose the Excel or CSV file you filled in. Keep the column headings, remove the grey sample rows, and upload one file at a time.</p>
        <div class="import-template-downloads">
            <a class="btn btn-primary" href="{{ route($entity.'.import.template') }}">Download Excel Template</a>
            <a class="btn" href="{{ route($entity.'.import.template.csv') }}">Download CSV Template</a>
        </div>
        <p class="muted">Excel includes instructions and dropdowns. CSV is a simpler option. Maximum file size: 5 MB; maximum 5,000 records.</p>
        @if($errors->has('file'))<div class="import-notice import-notice-error" role="alert">{{ $errors->first('file') }}</div>@endif
        <form class="import-upload-form" method="POST" enctype="multipart/form-data" action="{{ route($entity.'.import.upload') }}" id="{{ $entity }}ImportUploadForm">
            @csrf
            <input class="import-file-input" id="{{ $entity }}ImportFile" name="file" type="file" accept=".xlsx,.xls,.csv" required data-import-file>
            <label class="import-dropzone" for="{{ $entity }}ImportFile" data-dropzone tabindex="0" role="button" aria-describedby="{{ $entity }}ImportFileHelp">
                <span class="import-dropzone-icon" aria-hidden="true">⇧</span><strong>Drop your file here</strong><span>or <u>browse files</u></span><small id="{{ $entity }}ImportFileHelp">Excel (.xlsx, .xls) or CSV (.csv), up to 5 MB</small>
            </label>
            <div class="import-file-status" data-file-status aria-live="polite"></div>
            <button class="btn btn-primary" type="submit" data-upload-submit disabled>Upload and check file</button>
        </form>
        <details class="import-columns-help"><summary>What columns do I need?</summary><div class="table-wrap"><table class="tbl"><thead><tr><th>Column</th><th>Required?</th><th>What to enter</th></tr></thead><tbody>
            @foreach($fields as $field)<tr><td>{{ $field[0] }}</td><td>{{ $field[1] ? 'Required' : 'Optional' }}</td><td>{{ $field[2] }}</td></tr>@endforeach
        </tbody></table></div></details>
        <p class="import-template-next">After upload, you’ll see a private preview with any missing headings, errors, and possible duplicates. Nothing is added to your records in this step.</p>
    </div>
</dialog>
<script>
(() => {
    @if($errors->has('file'))
    document.getElementById(@json($entity.'ImportDialog'))?.showModal();
    @endif
    const input = document.getElementById(@json($entity.'ImportFile'));
    const form = document.getElementById(@json($entity.'ImportUploadForm'));
    if (!input || !form) return;
    const zone = form.querySelector('[data-dropzone]');
    const status = form.querySelector('[data-file-status]');
    const submit = form.querySelector('[data-upload-submit]');
    const maxBytes = 5 * 1024 * 1024;
    const allowed = ['xlsx', 'xls', 'csv'];
    const check = () => {
        const file = input.files && input.files[0];
        submit.disabled = true;
        zone.classList.remove('is-invalid', 'has-file');
        if (!file) { status.textContent = ''; return; }
        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowed.includes(ext)) { status.textContent = 'This file type is not supported. Please choose an Excel template or CSV file.'; zone.classList.add('is-invalid'); return; }
        if (file.size > maxBytes) { status.textContent = 'This file is larger than 5 MB. Split it into smaller files and try again.'; zone.classList.add('is-invalid'); return; }
        status.textContent = file.name + ' · ' + (file.size / 1024 / 1024).toFixed(2) + ' MB';
        zone.classList.add('has-file');
        submit.disabled = false;
    };
    input.addEventListener('change', check);
    zone.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); input.click(); } });
    ['dragenter', 'dragover'].forEach(name => zone.addEventListener(name, event => { event.preventDefault(); zone.classList.add('is-dragging'); }));
    ['dragleave', 'drop'].forEach(name => zone.addEventListener(name, event => { event.preventDefault(); zone.classList.remove('is-dragging'); }));
    zone.addEventListener('drop', event => { if (event.dataTransfer.files.length) { input.files = event.dataTransfer.files; check(); } });
    form.addEventListener('submit', () => { submit.disabled = true; submit.textContent = 'Checking your file…'; });
})();
</script>
