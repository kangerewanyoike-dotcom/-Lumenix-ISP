<?php

namespace App\Jobs;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\RadiusAccountingSession;
use App\Models\Router;
use App\Models\Tenant;
use App\Services\RouterConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DisconnectExpiredUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $tenantId, public readonly int $customerId) {}

    public function backoff(): array
    {
        return [10, 30, 90, 300];
    }

    public function handle(TenantContext $context, RouterConnection $connection): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $context->set($tenant);
        try {
            $customer = Customer::query()->findOrFail($this->customerId);
            $sessions = RadiusAccountingSession::query()->where('customer_id', $customer->id)->whereNull('stopped_at')->get();
            foreach ($sessions as $session) {
                $router = Router::query()->where('endpoint', $session->nas_ip_address)->where('status', 'active')->first();
                if (! $router || ! $connection->disconnect($router, $customer)) {
                    throw new \RuntimeException('Router did not confirm the subscriber disconnect; queued retry will run.');
                }
            }
        } finally {
            $context->clear();
        }
    }
}
