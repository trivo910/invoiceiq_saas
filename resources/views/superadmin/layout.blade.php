<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Platform administration') - InvoiceIQ</title>
    <link rel="stylesheet" href="{{ asset('css/app.css?v=9') }}">
    <style>
        body{background:#f4f6fa;color:#182333}.sa-nav{background:#152033;color:white;padding:18px 24px;display:flex;gap:20px;align-items:center;flex-wrap:wrap}.sa-nav a{color:inherit}.sa-nav form{margin-left:auto}.sa-main{max-width:1240px;margin:auto;padding:24px}.sa-panel{background:white;border:1px solid #dce2ec;border-radius:12px;padding:22px;margin:18px 0;overflow:auto}.sa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}.sa-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end}.sa-form label{display:flex;flex-direction:column;gap:6px}.sa-form input,.sa-form select,.sa-form textarea{width:100%;padding:9px;border:1px solid #b8c1cf;border-radius:6px;background:white;color:#182333}.sa-form textarea{min-height:80px}.sa-button{padding:9px 14px;border:0;border-radius:6px;background:#245dc9;color:white;cursor:pointer}.sa-warning{background:#fff2c7;padding:14px;border-radius:8px}.sa-table{width:100%;border-collapse:collapse}.sa-table th,.sa-table td{padding:12px;text-align:left;border-bottom:1px solid #e3e7ef;vertical-align:top}.sa-detail{white-space:pre-wrap;overflow-wrap:anywhere;max-width:650px}h1{font-size:26px;margin-bottom:14px}h2{font-size:19px;margin-bottom:14px}small{color:#53627b}.sa-metric strong{display:block;font-size:30px;margin-top:8px}button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid #f0b23c;outline-offset:2px}
    </style>
</head>
<body>
<nav class="sa-nav" aria-label="Platform administration">
    <strong>InvoiceIQ / Platform</strong>
    @auth('superadmin')
        <a href="{{ route('superadmin.dashboard') }}">Overview</a>
        <a href="{{ route('superadmin.tenants') }}">Workspaces</a>
        @foreach(['plans', 'coupons', 'announcements'] as $section)<a href="{{ route('superadmin.catalog', $section) }}">{{ ucfirst($section) }}</a>@endforeach
        <a href="{{ route('superadmin.audit') }}">Audit</a>
        <form method="post" action="{{ route('superadmin.logout') }}">@csrf<button class="sa-button">Sign out</button></form>
    @endauth
</nav>
<main class="sa-main">
    @if($errors->any())<div class="sa-warning" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @foreach(['success', 'warning'] as $flash)@if(session($flash))<p class="sa-warning" role="status">{{ session($flash) }}</p>@endif @endforeach
    @yield('content')
</main>
</body>
</html>
