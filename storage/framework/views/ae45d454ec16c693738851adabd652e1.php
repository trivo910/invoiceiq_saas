<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Trivo - InvoiceIQ'); ?> — Trivo - InvoiceIQ</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css?v=3')); ?>">
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
    </style>
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>
<div class="app-shell">

    
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

        
        <?php if(auth()->check() && auth()->user()->canWrite()): ?>
        <div class="sidebar-new-btn">
            <a href="<?php echo e(route('invoices.create')); ?>">
                <svg width="14" height="14" fill="none" viewBox="0 0 14 14">
                    <circle cx="7" cy="7" r="6.5" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M7 4v6M4 7h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                New Invoice
            </a>
        </div>
        <?php endif; ?>

        <nav>
            <div class="nav-section">Main</div>
            <a href="<?php echo e(route('dashboard')); ?>" class="nav-item <?php echo e(request()->routeIs('dashboard') ? 'active' : ''); ?>" data-tooltip="Dashboard">
                <svg viewBox="0 0 16 16" fill="none">
                    <rect x="1" y="1" width="6" height="6" rx="1.5" fill="currentColor" opacity=".7"/>
                    <rect x="9" y="1" width="6" height="6" rx="1.5" fill="currentColor" opacity=".7"/>
                    <rect x="1" y="9" width="6" height="6" rx="1.5" fill="currentColor" opacity=".4"/>
                    <rect x="9" y="9" width="6" height="6" rx="1.5" fill="currentColor" opacity=".4"/>
                </svg>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?php echo e(route('invoices.index')); ?>" class="nav-item <?php echo e(request()->routeIs('invoices.*') ? 'active' : ''); ?>" data-tooltip="Invoices">
                <svg viewBox="0 0 16 16" fill="none">
                    <rect x="2" y="1" width="12" height="14" rx="2" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M5 5h6M5 8h6M5 11h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Invoices</span>
            </a>

            <div class="nav-section">Masters</div>
            <a href="<?php echo e(route('customers.index')); ?>" class="nav-item <?php echo e(request()->routeIs('customers.*') ? 'active' : ''); ?>" data-tooltip="Customers">
                <svg viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M2 14c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Customers</span>
            </a>
            <a href="<?php echo e(route('items.index')); ?>" class="nav-item <?php echo e(request()->routeIs('items.*') ? 'active' : ''); ?>" data-tooltip="Items">
                <svg viewBox="0 0 16 16" fill="none">
                    <rect x="1.5" y="1.5" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
                    <rect x="9" y="1.5" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
                    <rect x="1.5" y="9" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M11.75 9v5.5M9 11.75h5.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Items</span>
            </a>

            <div class="nav-section">Reports</div>
            <a href="<?php echo e(route('reports.gst')); ?>" class="nav-item <?php echo e(request()->routeIs('reports.*') ? 'active' : ''); ?>" data-tooltip="GST Reports">
                <svg viewBox="0 0 16 16" fill="none">
                    <path d="M3 13V7M7 13V4M11 13V9M15 13H1" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">GST Reports</span>
            </a>

            
            <?php if(auth()->check() && auth()->user()->isAdmin()): ?>
            <div class="nav-section">Admin</div>
            <a href="<?php echo e(route('users.index')); ?>" class="nav-item <?php echo e(request()->routeIs('users.*') ? 'active' : ''); ?>" data-tooltip="Users">
                <svg viewBox="0 0 16 16" fill="none">
                    <circle cx="6" cy="5" r="2.5" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M1 14c0-2.761 2.239-5 5-5s5 2.239 5 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    <path d="M12 7v4M10 9h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Users</span>
            </a>
            <a href="<?php echo e(route('settings.index')); ?>" class="nav-item <?php echo e(request()->routeIs('settings.*') ? 'active' : ''); ?>" data-tooltip="Settings">
                <svg viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M8 1v2M8 13v2M1 8h2M13 8h2M2.93 2.93l1.41 1.41M11.66 11.66l1.41 1.41M2.93 13.07l1.41-1.41M11.66 4.34l1.41-1.41" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                <span class="nav-label">Settings</span>
            </a>
            <?php endif; ?>
        </nav>

        
        <?php if(auth()->guard()->check()): ?>
        <div class="sidebar-footer">
            <div class="user-panel" data-tooltip="<?php echo e(auth()->user()->name); ?>">
                <div class="user-avatar"><?php echo e(strtoupper(substr(auth()->user()->name, 0, 1))); ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo e(auth()->user()->name); ?></div>
                    <div class="user-role"><?php echo e(auth()->user()->role_label); ?></div>
                </div>
            </div>
            <form method="POST" action="<?php echo e(route('logout')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="logout-btn" data-tooltip="Sign Out">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                        <path d="M6 2H3a1 1 0 00-1 1v10a1 1 0 001 1h3M11 11l3-3-3-3M14 8H6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="logout-label">Sign Out</span>
                </button>
            </form>
        </div>
        <?php endif; ?>
    </aside>

    
    <main class="main">
        <?php if(session('success')): ?>
            <div style="padding:0 28px;padding-top:16px;">
                <div class="flash flash-success"><?php echo e(session('success')); ?></div>
            </div>
        <?php endif; ?>
        <?php if(session('error')): ?>
            <div style="padding:0 28px;padding-top:16px;">
                <div class="flash flash-error"><?php echo e(session('error')); ?></div>
            </div>
        <?php endif; ?>
        <?php if(session('warning')): ?>
            <div style="padding:0 28px;padding-top:16px;">
                <div class="flash flash-warning"><?php echo e(session('warning')); ?></div>
            </div>
        <?php endif; ?>

        <?php echo $__env->yieldContent('content'); ?>
    </main>

</div>
<?php echo $__env->yieldPushContent('scripts'); ?>
<script>
(function () {
    const STORAGE_KEY = 'sidebarCollapsed';
    const shell   = document.querySelector('.app-shell');
    const sidebar = document.querySelector('.sidebar');

    /* ── Sidebar toggle ── */
    if (localStorage.getItem(STORAGE_KEY) === '1') {
        shell.classList.add('sidebar-collapsed');
    }

    document.getElementById('sidebarToggle').addEventListener('click', function () {
        shell.classList.toggle('sidebar-collapsed');
        localStorage.setItem(STORAGE_KEY, shell.classList.contains('sidebar-collapsed') ? '1' : '0');
        tip.classList.remove('visible');
    });

    /* ── Collapsed tooltip ── */
    const tip = document.createElement('div');
    tip.id = 'sidebar-tip';
    document.body.appendChild(tip);

    let hideTimer = null;

    sidebar.querySelectorAll('[data-tooltip]').forEach(function (el) {
        el.addEventListener('mouseenter', function () {
            if (!shell.classList.contains('sidebar-collapsed')) return;

            clearTimeout(hideTimer);
            const label = el.dataset.tooltip;
            if (!label) return;

            const rect = el.getBoundingClientRect();
            tip.textContent  = label;
            tip.style.top    = (rect.top + rect.height / 2) + 'px';
            tip.style.left   = (rect.right + 10) + 'px';
            tip.classList.add('visible');
        });

        el.addEventListener('mouseleave', function () {
            hideTimer = setTimeout(function () {
                tip.classList.remove('visible');
            }, 80);
        });
    });
})();
</script>
</body>
</html>
<?php /**PATH D:\xampp\htdocs\invoiceiq\resources\views/layouts/app.blade.php ENDPATH**/ ?>