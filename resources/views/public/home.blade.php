@extends('public.layout')
@section('title','InvoiceIQ | GST invoicing for your business')
@section('content')
<section class="hero"><span class="badge">GST invoicing workspace</span><h1>Create invoices and keep your business records organized.</h1><p class="muted">InvoiceIQ helps Indian businesses manage customers, catalog items, GST invoices and workspace access in one place.</p><a class="button" href="{{ route('register') }}">Create an account</a> <a href="{{ route('pricing') }}">View plans</a></section>
<section class="grid"><article class="card"><h2>GST invoice workflow</h2><p>Prepare and manage invoices with customer, item and tax details in a workspace.</p></article><article class="card"><h2>Team workspaces</h2><p>Invite team members and assign workspace roles for day-to-day work.</p></article><article class="card"><h2>Share and export</h2><p>Use expiring invoice links and workspace exports with access controls.</p></article></section>
<p class="muted">Plan features, prices, seller identity and legal terms are subject to final approval. This site does not constitute tax or legal advice.</p>
@endsection
