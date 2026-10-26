<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Trivo - InvoiceIQ') — Trivo - InvoiceIQ</title>
    <link rel="stylesheet" href="{{ asset('css/app.css?v=8') }}">
    <style>
        /* ── Sidebar user panel ────────────────── */
        .sidebar-footer {
            margin-top: auto;
            padding: 12px 10px;
            border-top: 1px solid rgba(255,255,255,.07);
        }
        .user-panel {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: var(--radius);
            margin-bottom: 4px;
        }
        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .user-info { min-width: 0; flex: 1; }
        .user-name  { font-size: 13px; font-weight: 500; color: rgba(255,255,255,.85); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role  { font-size: 11px; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .6px; margin-top: 2px; }
        .logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 10px;
            border: none;
            background: none;
            color: rgba(255,255,255,.4);
            font-size: 13px;
            font-family: var(--font);
            cursor: pointer;
            border-radius: var(--radius);
            transition: all .12s;
            text-align: left;
        }
        .logout-btn:hover { background: rgba(255,255,255,.06); color: rgba(255,255,255,.65); }
        .logout-btn svg { flex-shrink: 0; }

        /* ── Flash messages ────────────────────────── */
        .flash { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .flash-close {
            flex-shrink: 0;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
            padding: 0 2px;
            opacity: .5;
            color: inherit;
            font-family: var(--font);
        }
        .flash-close:hover { opacity: 1; }
        .flash-wrap { transition: opacity .35s; }
        .flash-wrap.hiding { opacity: 0; }
    </style>
    @stack('styles')
</head>
<body>

{{-- Mobile top bar (visible only on ≤768px) --}}
<header class="mob-bar no-print" id="mobBar">
    <button class="mob-hamburger" id="mobHamburger" aria-label="Open menu">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M2 4.5h14M2 9h14M2 13.5h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
    </button>
    <span class="mob-brand">InvoiceIQ</span>
    @if(auth()->check() && auth()->user()->canWrite())
        <a href="{{ route('invoices.create') }}" class="mob-new-btn">+ New</a>
    @else
        <span class="mob-placeholder"></span>
    @endif
</header>

{{-- Sidebar backdrop (mobile overlay) --}}
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="app-shell">

    {{-- Sidebar --}}
    <aside class="sidebar no-print">
        <div class="sidebar-logo">
            <div class="sidebar-brand">
                <div class="brand">Trivo - InvoiceIQ</div>
                <div class="tagline">GST Billing Suite</div>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M2 4h12M2 8h12M2 12h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        {{-- New Invoice button (admin + staff only) --}}
        @if(auth()->check() && auth()->user()->canWrite())
        <div class="sidebar-new-btn">
            <a href="{{ route('invoices.create') }}">
                <svg width="14" height="14" fill="none" viewBox="0 0 14 14">
                    <circle cx="7" cy="7" r="6.5" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M7 4v6M4 7h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                New Invoice
            </a>
        </div>
        @endif

        <nav>
            <div class="nav-section">Main</div>
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-tooltip="Dashboard">
                <svg viewBox="0 0 16 16" fill="none">
                    <rect x="1" y="1" width="6" height="6" rx="1.5" fill="currentColor" opacity=".7"/>
                    <rect x="9" y="1" width="6" height="6" rx="1.5" fill="currentColor" opacity=".7"/>
                    <rect x="1" y="9" width="6" height="6" rx="1.5" fill="currentColor" opacity=".4"/>
                    <rect x="9" y="9" width="6" height="6" rx="1.5" fill="currentColor" opacity=".4"/>
                </svg>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="{{ route('invoices.index') }}" class="nav-item {{ request()->routeIs('invoices.*') ? 'active' : '' }}" data-tooltip="Invoices">
                <svg viewBox="0 0 16 16" fill="none">
                    <rect x="2" y="1" width="12" height="14" rx="2" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M5 5h6M5 8h6M5 11h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Invoices</span>
            </a>

            <div class="nav-section">Masters</div>
            <a href="{{ route('customers.index') }}" class="nav-item {{ request()->routeIs('customers.*') ? 'active' : '' }}" data-tooltip="Customers">
                <svg viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M2 14c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Customers</span>
            </a>
            <a href="{{ route('items.index') }}" class="nav-item {{ request()->routeIs('items.*') ? 'active' : '' }}" data-tooltip="Items">
                <svg viewBox="0 0 16 16" fill="none">
                    <rect x="1.5" y="1.5" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
                    <rect x="9" y="1.5" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
                    <rect x="1.5" y="9" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M11.75 9v5.5M9 11.75h5.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Items</span>
            </a>

            <div class="nav-section">Reports</div>
            <a href="{{ route('reports.gst') }}" class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}" data-tooltip="GST Reports">
                <svg viewBox="0 0 16 16" fill="none">
                    <path d="M3 13V7M7 13V4M11 13V9M15 13H1" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">GST Reports</span>
            </a>

            {{-- Admin-only section --}}
            @if(auth()->check() && auth()->user()->isAdmin())
            <div class="nav-section">Admin</div>
            <a href="{{ route('billing.index') }}" class="nav-item {{ request()->routeIs('billing.*') ? 'active' : '' }}" data-tooltip="Billing">
                <svg viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.3"/><path d="M1 6h14M4 10h3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                <span class="nav-label">Billing</span>
            </a>
            <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}" data-tooltip="Users">
                <svg viewBox="0 0 16 16" fill="none">
                    <circle cx="6" cy="5" r="2.5" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M1 14c0-2.761 2.239-5 5-5s5 2.239 5 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    <path d="M12 7v4M10 9h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Users</span>
            </a>
            <a href="{{ route('settings.index') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}" data-tooltip="Settings">
                <svg viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M8 1v2M8 13v2M1 8h2M13 8h2M2.93 2.93l1.41 1.41M11.66 11.66l1.41 1.41M2.93 13.07l1.41-1.41M11.66 4.34l1.41-1.41" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Settings</span>
            </a>
            @endif
        </nav>

        {{-- User panel + logout --}}
        @auth
        <div class="sidebar-footer">
            <div class="user-panel" data-tooltip="{{ auth()->user()->name }}">
                <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                <div class="user-info">
                    <div class="user-name">{{ auth()->user()->name }}</div>
                    <div class="user-role">{{ auth()->user()->role_label }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn" data-tooltip="Sign Out">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                        <path d="M6 2H3a1 1 0 00-1 1v10a1 1 0 001 1h3M11 11l3-3-3-3M14 8H6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="logout-label">Sign Out</span>
                </button>
            </form>
        </div>
        @endauth
    </aside>

    {{-- Main --}}
    <main class="main">
        @if(session('success'))
            <div style="padding:0 28px;padding-top:16px;" class="flash-wrap">
                <div class="flash flash-success">
                    <span>{{ session('success') }}</span>
                    <button type="button" class="flash-close" onclick="dismissFlash(this)" aria-label="Dismiss">×</button>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div style="padding:0 28px;padding-top:16px;" class="flash-wrap">
                <div class="flash flash-error">
                    <span>{{ session('error') }}</span>
                    <button type="button" class="flash-close" onclick="dismissFlash(this)" aria-label="Dismiss">×</button>
                </div>
            </div>
        @endif
        @if(session('warning'))
            <div style="padding:0 28px;padding-top:16px;" class="flash-wrap">
                <div class="flash flash-warning">
                    <span>{{ session('warning') }}</span>
                    <button type="button" class="flash-close" onclick="dismissFlash(this)" aria-label="Dismiss">×</button>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

</div>
@stack('scripts')
<script>
(function () {
    /* ── Flash dismiss ── */
    window.dismissFlash = function (btn) {
        const wrap = btn.closest('.flash-wrap');
        wrap.classList.add('hiding');
        wrap.addEventListener('transitionend', function () { wrap.remove(); }, { once: true });
    };

    document.querySelectorAll('.flash-wrap').forEach(function (wrap) {
        if (wrap.querySelector('.flash-error')) return;
        var delay = wrap.querySelector('.flash-warning') ? 6000 : 4000;
        setTimeout(function () {
            if (wrap.isConnected) dismissFlash(wrap.querySelector('.flash-close'));
        }, delay);
    });

    const STORAGE_KEY = 'sidebarCollapsed';
    const shell    = document.querySelector('.app-shell');
    const sidebar  = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    const isMobile = () => window.innerWidth <= 768;

    /* ── Tooltip element (desktop collapsed state) ── */
    const tip = document.createElement('div');
    tip.id = 'sidebar-tip';
    document.body.appendChild(tip);
    let hideTimer = null;

    /* ── Desktop: restore collapsed state ── */
    if (!isMobile() && localStorage.getItem(STORAGE_KEY) === '1') {
        shell.classList.add('sidebar-collapsed');
    }

    /* ── Desktop sidebar toggle (collapse/expand) ── */
    document.getElementById('sidebarToggle').addEventListener('click', function () {
        if (isMobile()) return; // mobile uses mob-hamburger instead
        shell.classList.toggle('sidebar-collapsed');
        localStorage.setItem(STORAGE_KEY, shell.classList.contains('sidebar-collapsed') ? '1' : '0');
        tip.classList.remove('visible');
    });

    /* ── Mobile hamburger → open sidebar overlay ── */
    const mobHamburger = document.getElementById('mobHamburger');
    if (mobHamburger) {
        mobHamburger.addEventListener('click', function () {
            const isOpen = sidebar.classList.toggle('mob-open');
            backdrop.classList.toggle('visible', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        });
    }

    /* ── Backdrop tap → close sidebar ── */
    backdrop.addEventListener('click', function () {
        sidebar.classList.remove('mob-open');
        backdrop.classList.remove('visible');
        document.body.style.overflow = '';
    });

    /* ── Nav link tap on mobile → close sidebar ── */
    sidebar.querySelectorAll('.nav-item').forEach(function (link) {
        link.addEventListener('click', function () {
            if (!isMobile()) return;
            sidebar.classList.remove('mob-open');
            backdrop.classList.remove('visible');
            document.body.style.overflow = '';
        });
    });

    /* ── Resize: clean up mobile state when returning to desktop ── */
    window.addEventListener('resize', function () {
        if (!isMobile()) {
            sidebar.classList.remove('mob-open');
            backdrop.classList.remove('visible');
            document.body.style.overflow = '';
        }
    });

    /* ── Desktop collapsed tooltip ── */
    sidebar.querySelectorAll('[data-tooltip]').forEach(function (el) {
        el.addEventListener('mouseenter', function () {
            if (isMobile()) return;
            if (!shell.classList.contains('sidebar-collapsed')) return;
            clearTimeout(hideTimer);
            const label = el.dataset.tooltip;
            if (!label) return;
            const rect = el.getBoundingClientRect();
            tip.textContent = label;
            tip.style.top   = (rect.top + rect.height / 2) + 'px';
            tip.style.left  = (rect.right + 10) + 'px';
            tip.classList.add('visible');
        });
        el.addEventListener('mouseleave', function () {
            hideTimer = setTimeout(function () { tip.classList.remove('visible'); }, 80);
        });
    });
})();
</script>
</body>
</html>
