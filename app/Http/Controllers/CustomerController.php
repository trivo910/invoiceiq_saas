<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Support\DashboardCache;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::query()
            ->when($request->q, fn($q, $term) => $q->search($term))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'      => Customer::count(),
            'with_gstin' => Customer::whereNotNull('gstin')->where('gstin', '!=', '')->count(),
            'with_email' => Customer::whereNotNull('email')->where('email', '!=', '')->count(),
            'states'     => Customer::whereNotNull('billing_state')->where('billing_state', '!=', '')->distinct('billing_state')->count(),
        ];

        return view('customers.index', compact('customers', 'stats'));
    }

    public function store(Request $request)
    {
        \App\Support\TaxIdentifiers::normalize($request, ['gstin', 'pan', 'billing_state_code']);
        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'gstin'              => ['nullable', 'string', \App\Support\TaxIdentifiers::GSTIN],
            'pan'                => ['nullable', 'string', \App\Support\TaxIdentifiers::PAN],
            'email'              => 'nullable|email|max:255',
            'phone'              => 'nullable|string|max:20',
            'billing_address'    => 'nullable|string',
            'billing_city'       => 'nullable|string|max:100',
            'billing_state'      => 'nullable|string|max:100',
            'billing_state_code' => ['nullable', 'string', \App\Support\TaxIdentifiers::STATE],
            'billing_pincode'    => 'nullable|string|max:10',
            'payment_terms'      => 'nullable|integer|min:0',
            'notes'              => 'nullable|string',
        ]);

        $customer = Customer::create($data);
        DashboardCache::forget();

        if ($request->wantsJson()) {
            return response()->json($customer);
        }

        return redirect()->route('customers.index')->with('success', 'Customer added successfully.');
    }

    public function update(Request $request, Customer $customer)
    {
        \App\Support\TaxIdentifiers::normalize($request, ['gstin', 'pan', 'billing_state_code']);
        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'gstin'              => ['nullable', 'string', \App\Support\TaxIdentifiers::GSTIN],
            'pan'                => ['nullable', 'string', \App\Support\TaxIdentifiers::PAN],
            'email'              => 'nullable|email|max:255',
            'phone'              => 'nullable|string|max:20',
            'billing_address'    => 'nullable|string',
            'billing_city'       => 'nullable|string|max:100',
            'billing_state'      => 'nullable|string|max:100',
            'billing_state_code' => ['nullable', 'string', \App\Support\TaxIdentifiers::STATE],
            'billing_pincode'    => 'nullable|string|max:10',
            'payment_terms'      => 'nullable|integer|min:0',
            'notes'              => 'nullable|string',
        ]);

        $customer->update($data);
        DashboardCache::forget();

        if ($request->wantsJson()) {
            return response()->json($customer);
        }

        return redirect()->route('customers.index')->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        DashboardCache::forget();
        return redirect()->route('customers.index')->with('success', 'Customer deleted.');
    }

    public function search(Request $request)
    {
        $customers = Customer::active()
            ->search($request->q ?? '')
            ->select('id', 'name', 'gstin', 'billing_city', 'billing_state', 'billing_state_code', 'billing_address', 'phone', 'email', 'payment_terms')
            ->limit(10)
            ->get();

        return response()->json($customers);
    }
}
