<?php

namespace App\Domain\Social\Actions;

use App\Domain\Finance\Services\InvoiceAmountsCalculator;
use App\Models\MarketingCampaign;
use App\Models\Invoice;
use App\Models\MarketingCampaignPeriod;
use App\Models\MarketingCampaignExtra;
use App\Enums\Social\MarketingCampaignPeriodStatus;
use App\Enums\Social\MarketingCampaignExtraStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateMarketingCampaignInvoiceAction
{
    public function __construct(
        private readonly InvoiceAmountsCalculator $amounts,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(MarketingCampaign $campaign, array $data): Invoice
    {
        return DB::transaction(function () use ($campaign, $data) {
            $periodIds = $data['period_ids'] ?? [];
            $extraIds = $data['extra_ids'] ?? [];

            $periods = MarketingCampaignPeriod::whereIn('id', $periodIds)
                ->where('marketing_campaign_id', $campaign->id)
                ->whereNull('invoice_id')
                ->lockForUpdate()
                ->get();

            $extras = MarketingCampaignExtra::whereIn('id', $extraIds)
                ->where('marketing_campaign_id', $campaign->id)
                ->whereNull('invoice_id')
                ->where('status', MarketingCampaignExtraStatus::Pending)
                ->lockForUpdate()
                ->get();

            $customLines = collect($data['custom_lines'] ?? [])->filter(fn($l) => !empty($l['description']) && isset($l['unit_price']));

            if ($periods->isEmpty() && $extras->isEmpty() && $customLines->isEmpty()) {
                throw new \Exception("Nessun periodo, extra o voce personalizzata valida.");
            }

            $subtotal = $periods->sum('amount') 
                      + $extras->sum('amount') 
                      + $customLines->sum(fn($l) => ((float)($l['quantity'] ?? 1)) * (float)$l['unit_price']);
            $vatRate = (float) ($data['vat_rate'] ?? 22);
            $taxAmount = round($subtotal * $vatRate / 100, 2, PHP_ROUND_HALF_UP);
            $total = $subtotal + $taxAmount;

            $invoice = Invoice::create([
                'client_id' => $campaign->client_id,
                'project_id' => null,
                'marketing_campaign_id' => $campaign->id,
                'created_by' => auth()->id(),
                'number' => $data['number'],
                'issue_date' => Carbon::parse($data['issue_date']),
                'due_date' => isset($data['due_date']) && $data['due_date'] ? Carbon::parse($data['due_date']) : null,
                'status' => $data['status'] ?? 'draft',
                'currency' => $data['currency'] ?? 'EUR',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'paid_total' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($periods as $period) {
                $invoice->items()->create(array_merge($this->amounts->lineAttributes([
                    'description' => $period->description,
                    'quantity' => 1,
                    'unit_price' => $period->amount,
                    'unit_of_measure' => 'NR',
                    'vat_rate' => $vatRate,
                ]), [
                    'billable_type' => MarketingCampaignPeriod::class,
                    'billable_id' => $period->id,
                ]));

                $period->update([
                    'invoice_id' => $invoice->id,
                    'status' => MarketingCampaignPeriodStatus::Invoiced,
                ]);
            }

            foreach ($extras as $extra) {
                $invoice->items()->create(array_merge($this->amounts->lineAttributes([
                    'description' => $extra->description,
                    'quantity' => 1,
                    'unit_price' => $extra->amount,
                    'unit_of_measure' => 'NR',
                    'vat_rate' => $vatRate,
                ]), [
                    'billable_type' => MarketingCampaignExtra::class,
                    'billable_id' => $extra->id,
                ]));

                $extra->update([
                    'invoice_id' => $invoice->id,
                    'status' => MarketingCampaignExtraStatus::Invoiced,
                ]);
            }

            foreach ($customLines as $line) {
                $qty = (float) ($line['quantity'] ?? 1);
                $price = (float) $line['unit_price'];
                $invoice->items()->create(array_merge($this->amounts->lineAttributes([
                    'description' => $line['description'],
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'unit_of_measure' => 'NR',
                    'vat_rate' => $vatRate,
                ]), [
                    'billable_type' => null,
                    'billable_id'   => null,
                ]));
            }

            $this->amounts->recalculate($invoice);

            return $invoice;
        });
    }
}
