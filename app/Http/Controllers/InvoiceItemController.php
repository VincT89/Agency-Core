<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use App\Domain\Finance\Services\InvoiceAmountsCalculator;
use App\Enums\Finance\VatNature;
use Illuminate\Validation\Rule;

class InvoiceItemController extends Controller
{
    public function store(
        Request $request,
        Invoice $invoice,
        InvoiceAmountsCalculator $amounts,
    ): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'description' => 'required|string|max:255',
            'quantity'    => 'required|numeric|min:0.01',
            'unit_price'  => 'required|numeric|min:0',
            'unit_of_measure' => 'nullable|string|max:10',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'vat_nature' => ['nullable', Rule::enum(VatNature::class)],
            'vat_reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'invoice_items')->withInput();
        }

        $data = $validator->validated();

        $vatRate = filled($data['vat_rate'] ?? null) ? (float) $data['vat_rate'] : null;

        if ($vatRate === 0.0 && (blank($data['vat_nature'] ?? null) || blank($data['vat_reference'] ?? null))) {
            return back()
                ->withErrors([
                    'vat_nature' => 'Con aliquota zero devi indicare natura IVA e riferimento normativo.',
                ], 'invoice_items')
                ->withInput();
        }

        $invoice->items()->create(array_merge($amounts->lineAttributes($data), [
            'billable_type' => null,
            'billable_id'   => null,
        ]));

        $this->recalculateInvoice($invoice, $amounts);

        return back()->with('success', 'Voce aggiunta correttamente.');
    }

    public function destroy(
        Invoice $invoice,
        InvoiceItem $item,
        InvoiceAmountsCalculator $amounts,
    ): RedirectResponse
    {
        $this->authorize('update', $invoice);

        // Sicurezza: solo voci libere eliminabili, non quelle collegate
        if ($item->billable_type !== null) {
            abort(403, 'Le voci collegate a contratti o extra non possono essere eliminate.');
        }

        // Sicurezza: la voce deve appartenere a questa fattura
        if ($item->invoice_id !== $invoice->id) {
            abort(403);
        }

        $item->delete();

        $this->recalculateInvoice($invoice, $amounts);

        return back()->with('success', 'Voce rimossa correttamente.');
    }

    private function recalculateInvoice(
        Invoice $invoice,
        InvoiceAmountsCalculator $amounts,
    ): void {
        if (
            $invoice->items()->exists()
            && ! $invoice->items()->whereNull('vat_rate')->exists()
        ) {
            $amounts->recalculate($invoice);

            return;
        }

        $subtotal = round((float) $invoice->items()->sum('total'), 2, PHP_ROUND_HALF_UP);

        $invoice->update([
            'subtotal' => $subtotal,
            'total' => round($subtotal + (float) $invoice->tax_amount, 2, PHP_ROUND_HALF_UP),
        ]);
    }
}
