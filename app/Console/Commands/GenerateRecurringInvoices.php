<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Services\BillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'billing:run {--date= : Billing date in YYYY-MM-DD format; defaults to each tenant local date}';

    protected $description = 'Generate due recurring invoices for all active tenants';

    public function handle(BillingService $billing, TenantContext $context): int
    {
        $explicitDate = $this->option('date');
        $count = 0;
        foreach (Tenant::query()->where('status', 'active')->orderBy('id')->get() as $tenant) {
            $context->set($tenant);
            try {
                $date = $explicitDate
                    ? CarbonImmutable::parse($explicitDate)->startOfDay()
                    : CarbonImmutable::now($tenant->timezone ?? config('app.timezone'))->startOfDay();
                $count += $billing->runRecurringForTenantDate($date);
            } finally {
                $context->clear();
            }
        }
        $this->info("Processed {$count} recurring invoice(s).");

        return self::SUCCESS;
    }
}
