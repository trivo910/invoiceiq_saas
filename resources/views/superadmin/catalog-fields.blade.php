@if($kind === 'plans')
    @foreach(['code' => 'Code', 'name' => 'Name', 'description' => 'Description', 'monthly_price_paise' => 'Monthly price (paise)', 'yearly_price_paise' => 'Yearly price (paise)', 'monthly_invoice_limit' => 'Monthly invoice quota', 'member_limit' => 'Member quota', 'razorpay_plan_monthly_id' => 'Razorpay monthly plan ID', 'razorpay_plan_yearly_id' => 'Razorpay yearly plan ID', 'sort_order' => 'Display order'] as $field => $label)
    <label>{{ $label }}<input name="{{ $field }}" value="{{ $record?->$field ?? ($field === 'sort_order' ? 0 : '') }}" @if(in_array($field, ['code', 'name', 'sort_order'])) required @endif @if(str_contains($field, 'paise') || str_contains($field, 'limit') || $field === 'sort_order') type="number" min="0" @endif></label>
    @endforeach
    @foreach(['gst_reports' => 'GST reports', 'custom_branding' => 'Custom branding', 'api_access' => 'API access'] as $field => $label)
    <label>{{ $label }}<select name="{{ $field }}"><option value="" @selected($record?->$field === null)>Undecided</option><option value="1" @selected($record?->$field === true)>Included</option><option value="0" @selected($record?->$field === false)>Not included</option></select></label>
    @endforeach
@elseif($kind === 'coupons')
    <label>Code<input name="code" maxlength="50" value="{{ $record?->code }}" required></label>
    <label>Type<select name="type"><option value="percent" @selected($record?->type === 'percent')>Percent</option><option value="fixed_paise" @selected($record?->type === 'fixed_paise')>Fixed INR (paise)</option></select></label>
    <label>Value<input type="number" min="1" name="value" value="{{ $record?->value }}" required></label>
@else
    <label>Title<input name="title" maxlength="255" value="{{ $record?->title }}" required></label>
    <label>Message<textarea name="body" maxlength="5000" required>{{ $record?->body }}</textarea></label>
@endif
<label>Status<select name="is_active"><option value="0" @selected(! $record?->is_active)>Inactive</option><option value="1" @selected($record?->is_active)>Active</option></select></label>
@if($kind !== 'plans')
    <label>Starts at (optional)<input type="datetime-local" name="starts_at" value="{{ $record?->starts_at?->format('Y-m-d\TH:i') }}"></label>
    <label>Expires at (optional)<input type="datetime-local" name="expires_at" value="{{ $record?->expires_at?->format('Y-m-d\TH:i') }}"></label>
@endif
