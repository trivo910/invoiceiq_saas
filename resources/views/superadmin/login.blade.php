@extends('superadmin.layout')
@section('title', 'Super-admin sign in')
@section('content')
<section class="sa-panel" style="max-width:480px;margin:40px auto">
    <h1>Super-admin sign in</h1>
    <p>Use your platform administrator account.</p>
    <form method="post" action="{{ route('superadmin.authenticate') }}" class="sa-form">@csrf
        <label>Email<input type="email" name="email" autocomplete="username" value="{{ old('email') }}" required></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="sa-button">Sign in</button>
    </form>
</section>
@endsection
