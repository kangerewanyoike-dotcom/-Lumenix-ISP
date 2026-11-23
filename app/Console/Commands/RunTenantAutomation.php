<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Services\AutomationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class RunTenantAutomation extends Command
{
    protected $signature = 'automation:run {--date= : Tenant-local processing date in YYYY-MM-DD format}';

    protected $description = 'Send tenant invoice reminders and apply overdue access policy';

    public function handle(AutomationService $automation, TenantContext $context): int
    {
        $totals = ['reminders' => 0, 'suspended' => 0, 'reactivated' => 0, 'failed' => 0];
        foreach (Tenant::query()->where('status', 'active')->orderBy('id')->get() as $tenant) {
            $context->set($tenant);
            try {
                $date = $this->option('date')
                    ? CarbonImmutable::parse($this->option('date'))->startOfDay()
                    : CarbonImmutable::now($tenant->timezone ?? config('app.timezone'))->startOfDay();
                foreach ($automation->runForTenantDate($tenant, $date) as $key => $value) {
                    $totals[$key] += $value;
                }
            } catch (\Throwable $exception) {
                report($exception);
                $totals['failed']++;
                $this->error('Tenant automation failed for tenant '.$tenant->id.'.');
            } finally {
                $context->clear();
            }
        }
        $this->info("Sent {$totals['reminders']} reminder(s); suspended {$totals['suspended']} customer(s); {$totals['failed']} failure(s).");

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
