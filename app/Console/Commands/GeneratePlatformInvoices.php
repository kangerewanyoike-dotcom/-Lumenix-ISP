<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\PlatformInvoice;
use App\Models\PlatformUsageSnapshot;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class GeneratePlatformInvoices extends Command
{
    protected $signature = 'platform-billing:run {--period= : Billing month in YYYY-MM format; defaults to previous calendar month}';

    protected $description = 'Snapshot active subscribers and issue separate Lumenix platform invoices';

    public function handle(TenantContext $context): int
    {
        $period = $this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m');
        try {
            $start = Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
        } catch (\Throwable) {
            $this->error('Period must use YYYY-MM format.');

            return self::FAILURE;
        }
        if ($start->format('Y-m') !== $period) {
            $this->error('Period must use YYYY-MM format.');

            return self::FAILURE;
        }
        $end = $start->copy()->endOfMonth();
        $periodStart = $start->toDateString();
        $periodEnd = $end->toDateString();
        $created = 0;

        foreach (Tenant::query()->where('status', 'active')->with('platformSubscription.plan')->whereHas('platformSubscription', fn ($q) => $q->where('status', 'active'))->orderBy('id')->get() as $tenant) {
            $subscription = $tenant->platformSubscription;
            DB::transaction(function () use ($tenant, $subscription, $start, $end, $periodStart, $periodEnd, $context, &$created): void {
                $context->set($tenant);
                try {
                    $count = Customer::query()->where('status', 'active')->count();
                } finally {
                    $context->clear();
                }
                $plan = $subscription->plan;
                $overageCount = $plan ? max(0, $count - $plan->included_subscribers) : $count;
                $amountMinor = ($plan?->monthly_price_minor ?? 0) + ($overageCount * $subscription->price_per_subscriber_minor);
                DB::table('platform_usage_snapshots')->upsert([[
                    'tenant_id' => $tenant->id, 'period_start' => $periodStart, 'period_end' => $periodEnd,
                    'active_subscriber_count' => $count, 'unit_price_minor' => $subscription->price_per_subscriber_minor,
                    'amount_minor' => $amountMinor, 'created_at' => now(),
                ]], ['tenant_id', 'period_start', 'period_end'], ['tenant_id']);
                $snapshot = PlatformUsageSnapshot::query()->where('tenant_id', $tenant->id)
                    ->whereDate('period_start', $periodStart)->whereDate('period_end', $periodEnd)->firstOrFail();
                $invoice = PlatformInvoice::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'idempotency_key' => "platform:{$tenant->id}:{$start->format('Y-m')}"],
                    [
                        'platform_subscription_id' => $subscription->id, 'usage_snapshot_id' => $snapshot->id,
                        'number' => sprintf('LUM-%s-%s', $tenant->slug, $start->format('Ym')), 'status' => 'issued',
                        'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                        'issued_at' => now()->toDateString(), 'due_at' => $end->copy()->addDays(14)->toDateString(),
                        'currency' => $subscription->currency, 'total_minor' => $amountMinor, 'balance_minor' => $amountMinor,
                    ],
                );
                if ($invoice->wasRecentlyCreated) {
                    $created++;
                }
            });
        }
        $this->info("Issued {$created} platform invoice(s) for {$period}.");

        return self::SUCCESS;
    }
}
