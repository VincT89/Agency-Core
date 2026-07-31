<?php

namespace App\Http\Controllers;

use App\Enums\Finance\FatturaPaPaymentMethod;
use App\Exceptions\Finance\ArubaApiException;
use App\Exceptions\Finance\ArubaConfigurationException;
use App\Http\Requests\UpdateBillingProfileRequest;
use App\Models\BillingProfile;
use App\Services\Integrations\Aruba\ArubaConfiguration;
use App\Services\Integrations\Aruba\ArubaInvoiceClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingProfileController extends Controller
{
    public function edit(
        Request $request,
        ArubaConfiguration $configuration,
    ): View
    {
        abort_unless($request->user()->canAccessFinance(), 403);

        $billingProfile = BillingProfile::current() ?? new BillingProfile([
            'profile_key' => 'default',
            'vat_country_code' => 'IT',
            'country_code' => 'IT',
            'fiscal_regime' => 'RF01',
            'invoice_series' => 'FE',
            'initial_sequence' => 1,
            'default_payment_method' => FatturaPaPaymentMethod::BankTransfer->value,
        ]);

        try {
            $arubaStatus = $configuration->status();
        } catch (ArubaConfigurationException $exception) {
            $arubaStatus = [
                'enabled' => false,
                'environment' => null,
                'environment_label' => 'Configurazione non valida',
                'credentials_configured' => false,
                'callback_configured' => false,
                'allow_send' => false,
                'ready_for_validation' => false,
                'ready_for_send' => false,
                'configuration_error' => $exception->getMessage(),
            ];
        }

        return view('billing-profile.edit', [
            'billingProfile' => $billingProfile,
            'paymentMethods' => FatturaPaPaymentMethod::cases(),
            'arubaStatus' => $arubaStatus,
        ]);
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

    public function testArubaConnection(
        Request $request,
        ArubaInvoiceClient $client,
    ): RedirectResponse {
        abort_unless($request->user()->canAccessFinance(), 403);

        try {
            $client->userInfo();
        } catch (ArubaApiException|ArubaConfigurationException $exception) {
            return back()->withErrors([
                'aruba' => $exception instanceof ArubaApiException
                    ? $exception->userMessage
                    : 'Collegamento Aruba non disponibile.',
            ]);
        }

        return back()->with(
            'success',
            'Collegamento Aruba verificato correttamente.'
        );
    }
}
