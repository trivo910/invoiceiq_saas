<?php

namespace App\Http\Controllers;

use App\Models\Plan;

class PublicSiteController extends Controller
{
    public function home()
    {
        return auth('web')->check() ? redirect()->route('dashboard') : view('public.home');
    }
    public function pricing()
    {
        return view('public.pricing', ['plans' => Plan::where('is_active', true)->whereNotIn('code', ['trial', 'legacy'])->orderBy('sort_order')
            ->get(['name', 'description', 'monthly_price_paise', 'yearly_price_paise', 'monthly_invoice_limit', 'member_limit']),
            'sample' => config('billing.demo_mode'), 'approved' => config('launch.pricing_approved')]);
    }
    public function legal(string $page)
    {
        abort_unless(in_array($page, ['terms', 'privacy']), 404);
        return view('public.page', ['page' => $page]);
    }
}
