<?php

namespace App\Console\Commands;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\RadiusAccountingSession;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class IngestRadiusAccounting extends Command
{
    protected $signature = 'radius:ingest-accounting {--all : Scan the full accounting table for initial backfill}';

    protected $description = 'Project FreeRADIUS accounting records into tenant-scoped usage sessions';

    public function handle(TenantContext $context): int
    {
        $count = 0;
        $records = DB::table('radacct')->whereNotNull('username');
        if (! $this->option('all')) {
            $since = now()->subMinutes(15);
            $records->where(fn ($query) => $query->where('acctupdatetime', '>=', $since)->orWhere('acctstarttime', '>=', $since)->orWhere('acctstoptime', '>=', $since));
        }
        try {
            $records->orderBy('radacctid')->chunk(500, function ($records) use (&$count, $context): void {
                foreach ($records as $record) {
                    $customerRow = DB::table('customers')->where('pppoe_username', $record->username)->whereNull('deleted_at')->first(['id', 'tenant_id']);
                    $voucherRow = $customerRow ? null : DB::table('hotspot_vouchers')->where('username', $record->username)->first(['id', 'tenant_id']);
                    if (! $customerRow && ! $voucherRow) {
                        continue;
                    }
                    $tenantId = $customerRow?->tenant_id ?? $voucherRow->tenant_id;
                    $tenant = Tenant::query()->find($tenantId);
                    if (! $tenant) {
                        continue;
                    }
                    $context->set($tenant);
                    $customer = $customerRow ? Customer::query()->find($customerRow->id) : null;
                    if ($customerRow && ! $customer) {
                        continue;
                    }
                    RadiusAccountingSession::query()->updateOrCreate(
                        ['acct_unique_id' => $record->acctuniqueid],
                        [
                            'customer_id' => $customer?->id, 'username' => $record->username,
                            'access_type' => $customer ? 'pppoe' : 'hotspot', 'nas_ip_address' => $record->nasipaddress,
                            'started_at' => $record->acctstarttime, 'updated_at_radius' => $record->acctupdatetime,
                            'stopped_at' => $record->acctstoptime, 'session_seconds' => $record->acctsessiontime ?? 0,
                            'input_octets' => $record->acctinputoctets ?? 0, 'output_octets' => $record->acctoutputoctets ?? 0,
                        ],
                    );
                    $count++;
                }
            });
        } finally {
            $context->clear();
        }
        $this->info("Projected {$count} accounting record(s).");

        return self::SUCCESS;
    }
}
