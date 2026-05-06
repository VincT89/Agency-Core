<?php

namespace App\Domain\Social\Actions;

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

            if ($periods->isEmpty() && $extras->isEmpty()) {
                throw new \Exception("Nessun periodo o extra valido selezionato per la fatturazione.");
            }

            $subtotal = $periods->sum('amount') + $extras->sum('amount');
            $taxAmount = $data['tax_amount'] ?? 0;
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
                $invoice->items()->create([
                    'billable_type' => MarketingCampaignPeriod::class,
                    'billable_id' => $period->id,
                    'description' => $period->description,
                    'quantity' => 1,
                    'unit_price' => $period->amount,
                    'total' => $period->amount,
                ]);

                $period->update([
                    'invoice_id' => $invoice->id,
                    'status' => MarketingCampaignPeriodStatus::Invoiced,
                ]);
            }

            foreach ($extras as $extra) {
                $invoice->items()->create([
                    'billable_type' => MarketingCampaignExtra::class,
                    'billable_id' => $extra->id,
                    'description' => $extra->description,
                    'quantity' => 1,
                    'unit_price' => $extra->amount,
                    'total' => $extra->amount,
                ]);

                $extra->update([
                    'invoice_id' => $invoice->id,
                    'status' => MarketingCampaignExtraStatus::Invoiced,
                ]);
            }

            return $invoice;
        });
    }
}
