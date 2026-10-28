@extends('superadmin.layout')
@section('content')
<h1>{{ ucfirst($kind) }}</h1>
@if($kind === 'coupons')<p class="sa-warning">Coupon catalog only. Checkout redemption remains unavailable until discount and provider billing rules are approved.</p>@endif
@if($kind === 'plans')<p class="sa-warning">Prices are in paise. Blank quotas mean unlimited; blank feature choices mean undecided. Referenced plan prices/provider IDs are immutable: create a new plan for those changes. Checkout readiness still follows billing configuration.</p>@endif
<section class="sa-panel"><h2>Create {{ rtrim($kind, 's') }}</h2><form class="sa-form" method="post" action="{{ route('superadmin.catalog.create', $kind) }}">@csrf @include('superadmin.catalog-fields', ['record' => null])<button class="sa-button">Create</button></form></section>
@forelse($records as $record)
<section class="sa-panel"><h2>{{ $record->name ?? $record->title ?? $record->code }}</h2>
<form class="sa-form" method="post" action="{{ route('superadmin.catalog.update', [$kind, $record->id]) }}">@csrf @method('put') @include('superadmin.catalog-fields')<button class="sa-button">Save changes</button></form>
<form method="post" action="{{ route('superadmin.catalog.delete', [$kind, $record->id]) }}" style="margin-top:16px">@csrf @method('delete')<button class="sa-button">Delete {{ rtrim($kind, 's') }}</button></form>
</section>
@empty<p>No records.</p>@endforelse
{{ $records->links() }}
@endsection
