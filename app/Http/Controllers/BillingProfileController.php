<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBillingProfileRequest;
use App\Models\BillingProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingProfileController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->canAccessFinance(), 403);

        $billingProfile = BillingProfile::current() ?? new BillingProfile([
            'profile_key' => 'default',
            'vat_country_code' => 'IT',
            'country_code' => 'IT',
            'fiscal_regime' => 'RF01',
            'invoice_series' => 'FE',
            'initial_sequence' => 1,
        ]);

        return view('billing-profile.edit', compact('billingProfile'));
    }

    public function update(UpdateBillingProfileRequest $request): RedirectResponse
    {
        BillingProfile::query()->updateOrCreate(
            ['profile_key' => 'default'],
            $request->validated(),
        );

        return redirect()
            ->route('billing-profile.edit')
            ->with('success', 'Dati fiscali dell’agenzia salvati correttamente.');
    }
}
