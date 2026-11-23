<?php

namespace App\Services;

use App\Domain\Tenancy\TenantContext;
use App\Models\AutomatedAccessEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessagingAccount;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\Router;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AutomationService
{
    public function __construct(private readonly AfricasTalkingClient $sms, private readonly TenantContext $context) {}

    /** @return array{reminders:int,suspended:int,reactivated:int,failed:int} */
    public function runForTenantDate(Tenant $tenant, CarbonImmutable $date): array
    {
        $stats = ['reminders' => 0, 'suspended' => 0, 'reactivated' => 0, 'failed' => 0];
        if (! $tenant->automation_enabled) {
            return $stats;
        }
        $this->sendDueReminders($tenant, $date, $stats);
        $this->suspendOverdueCustomers($tenant, $date, $stats);

        return $stats;
    }

    public function restoreAfterInvoicePayment(Invoice $invoice): bool
    {
        if (! DB::transactionLevel()) {
            return DB::transaction(fn () => $this->restoreAfterInvoicePayment($invoice));
        }
        if ($invoice->balance_minor > 0) {
            return false;
        }
        $customer = Customer::query()->lockForUpdate()->findOrFail($invoice->customer_id);
        $automaticSuspension = $customer->status === 'suspended' && in_array($customer->suspension_reason, ['payment_overdue', 'payment_pending', 'subscription_expired'], true);
        if ($customer->status !== 'active' && ! $automaticSuspension) {
            return false;
        }
        $tenant = $this->context->current();
        $today = CarbonImmutable::now($tenant->timezone ?? config('app.timezone'))->startOfDay();
        $cutoff = $today->subDays((int) $tenant->grace_period_days);
        $hasOverdue = Invoice::query()->where('customer_id', $customer->id)->where('balance_minor', '>', 0)
            ->whereDate('due_at', '<', $cutoff->toDateString())->exists();
        if ($hasOverdue) {
            return false;
        }
        $updates = ['subscription_status' => 'active', 'provisioning_status' => 'pending'];
        if ($invoice->period_end) {
            $updates['current_period_ends_at'] = $invoice->period_end->toDateString();
        }
        if ($automaticSuspension) {
            $updates += ['status' => 'active', 'suspension_reason' => null, 'suspended_at' => null];
            app(AuditLogger::class)->record('customer.automatically_reactivated', (int) $tenant->id, Customer::class, (string) $customer->id, ['invoice_id' => (int) $invoice->id]);
        }
        $customer->update($updates);

        return true;
    }

    private function sendDueReminders(Tenant $tenant, CarbonImmutable $date, array &$stats): void
    {
        $account = MessagingAccount::query()->where('active', true)->first();
        if (! $account) {
            return;
        }
        $offsets = collect($tenant->reminder_days ?? [3, 1, 0])->map(fn ($day) => (int) $day)->unique()->sortDesc();
        $templates = NotificationTemplate::query()->where('channel', 'sms')->where('active', true)->get()->keyBy('event_key');
        foreach ($offsets as $daysBefore) {
            $dueDate = $date->addDays($daysBefore)->toDateString();
            $template = $templates->get('invoice_reminder');
            if (! $template) {
                continue;
            }
            Invoice::query()->with('customer')->where('balance_minor', '>', 0)->whereDate('due_at', $dueDate)
                ->whereHas('customer', fn ($query) => $query->where('status', '!=', 'disconnected'))
                ->orderBy('id')->chunkById(100, function ($invoices) use ($template, $daysBefore, &$stats): void {
                    foreach ($invoices as $invoice) {
                        $key = 'invoice-reminder:'.$invoice->id.':'.$daysBefore;
                        DB::table('notification_deliveries')->insertOrIgnore([
                            'tenant_id' => $this->context->id(), 'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id,
                            'event_key' => 'invoice_reminder', 'channel' => 'sms', 'destination' => Crypt::encryptString($invoice->customer->phone),
                            'idempotency_key' => $key, 'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $delivery = NotificationDelivery::query()->where('idempotency_key', $key)->firstOrFail();
                        if ($delivery->status === 'sent' || $delivery->next_attempt_at?->isFuture()) {
                            continue;
                        }
                        if ($invoice->balance_minor === 0) {
                            $delivery->update(['status' => 'cancelled', 'next_attempt_at' => null]);

                            continue;
                        }
                        $this->sendDelivery($delivery, $invoice, $template, $stats);
                    }
                });
        }
        $this->retryFailedDeliveries($templates, $stats);
    }

    private function retryFailedDeliveries($templates, array &$stats): void
    {
        NotificationDelivery::query()->where('attempts', '<', 5)
            ->where(fn ($query) => $query->where(fn ($pending) => $pending->where('status', 'pending')->orWhere(fn ($failed) => $failed->where('status', 'failed')->where(fn ($due) => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))))->orWhere(fn ($stale) => $stale->where('status', 'sending')->where('updated_at', '<=', now()->subMinutes(15))))
            ->orderBy('id')->limit(100)->get()->each(function (NotificationDelivery $delivery) use ($templates, &$stats): void {
                $invoice = $delivery->invoice_id ? Invoice::query()->with('customer')->find($delivery->invoice_id) : null;
                $template = $templates->get($delivery->event_key);
                if (! $invoice || $invoice->balance_minor === 0 || ! $template) {
                    $delivery->update(['status' => 'cancelled', 'next_attempt_at' => null]);

                    return;
                }
                $this->sendDelivery($delivery, $invoice, $template, $stats);
            });
    }

    private function sendDelivery(NotificationDelivery $delivery, Invoice $invoice, NotificationTemplate $template, array &$stats): void
    {
        $account = MessagingAccount::query()->where('active', true)->first();
        if (! $account) {
            return;
        }
        $claimed = DB::transaction(function () use ($delivery): ?NotificationDelivery {
            $locked = NotificationDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if ($locked->status === 'sent' || $locked->attempts >= 5 || ($locked->status === 'sending' && $locked->updated_at->gt(now()->subMinutes(15)))) {
                return null;
            }
            $locked->update(['status' => 'sending', 'attempts' => $locked->attempts + 1, 'next_attempt_at' => null]);

            return $locked->refresh();
        });
        if (! $claimed) {
            return;
        }
        try {
            $body = $this->render($template->body, [
                'brand_name' => $this->context->current()->brand_name ?: $this->context->current()->name,
                'customer_name' => $invoice->customer->name,
                'account_number' => $invoice->customer->account_number,
                'invoice_number' => $invoice->number,
                'amount' => $this->formatMinor($invoice->balance_minor),
                'due_date' => $invoice->due_at->format('Y-m-d'),
            ]);
            $result = $this->sms->send($account, $claimed->destination, $body);
            $claimed->update(['status' => 'sent', 'provider_reference' => $result['reference'], 'error_code' => null, 'next_attempt_at' => null, 'sent_at' => now()]);
            $stats['reminders']++;
        } catch (\Throwable $exception) {
            $attempts = $claimed->attempts;
            $message = $exception->getMessage();
            $errorCode = preg_match('/^provider_http_[0-9]{3}$/', $message) || in_array($message, ['provider_rejected', 'unsupported_template_variable', 'invalid_template_syntax'], true)
                ? $message
                : 'delivery_failed';
            $claimed->update(['status' => 'failed', 'error_code' => $errorCode, 'next_attempt_at' => $attempts < 5 ? now()->addMinutes(min(60, 5 * (2 ** ($attempts - 1)))) : null]);
            $stats['failed']++;
        }
    }

    private function suspendOverdueCustomers(Tenant $tenant, CarbonImmutable $date, array &$stats): void
    {
        $cutoff = $date->subDays((int) $tenant->grace_period_days)->toDateString();
        $invoiceIds = Invoice::query()->where('balance_minor', '>', 0)->whereDate('due_at', '<', $cutoff)->orderBy('due_at')->get(['id', 'customer_id']);
        foreach ($invoiceIds->unique('customer_id') as $row) {
            $customer = Customer::query()->find($row->customer_id);
            if (! $customer || ($customer->status !== 'active' && ! ($customer->status === 'suspended' && $customer->suspension_reason === 'payment_overdue'))) {
                continue;
            }
            $key = 'payment-suspend:customer:'.$customer->id.':invoice:'.$row->id;
            DB::table('automated_access_events')->insertOrIgnore([
                'tenant_id' => $this->context->id(), 'customer_id' => $customer->id, 'invoice_id' => $row->id,
                'action' => 'suspend', 'idempotency_key' => $key, 'status' => 'pending', 'attempts' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $event = DB::transaction(function () use ($key): ?AutomatedAccessEvent {
                $locked = AutomatedAccessEvent::query()->where('idempotency_key', $key)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'completed' || ($locked->status === 'running' && $locked->updated_at->gt(now()->subMinutes(15)))) {
                    return null;
                }
                $locked->update(['status' => 'running', 'attempts' => $locked->attempts + 1, 'error_code' => null]);

                return $locked->refresh();
            });
            if (! $event) {
                continue;
            }
            if ($customer->status !== 'suspended') {
                $customer->update(['status' => 'suspended', 'subscription_status' => 'suspended', 'provisioning_status' => 'pending', 'suspension_reason' => 'payment_overdue', 'suspended_at' => now()]);
                app(AuditLogger::class)->record('customer.automatically_suspended', (int) $tenant->id, Customer::class, (string) $customer->id, ['invoice_id' => (int) $row->id]);
            }
            $routers = Router::query()->where('connection_mode', 'vpn')->where('status', 'active')->get();
            try {
                foreach ($routers as $router) {
                    app(CustomerAccessService::class)->suspend($customer, $router);
                }
                $event->update(['status' => 'completed', 'completed_at' => now(), 'error_code' => $routers->isEmpty() ? 'authorization_only_no_vpn_router' : null]);
                $stats['suspended']++;
            } catch (\Throwable $exception) {
                $event->update(['status' => 'failed', 'error_code' => 'disconnect_failed']);
                $stats['failed']++;
                report($exception);
            }
        }
    }

    /** @param array<string, string> $values */
    private function render(string $template, array $values): string
    {
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $template, $matches);
        if (substr_count($template, '{{') !== substr_count($template, '}}')) {
            throw new RuntimeException('invalid_template_syntax');
        }
        foreach (array_unique(array_map('trim', $matches[1])) as $variable) {
            if (! array_key_exists($variable, $values)) {
                throw new RuntimeException('unsupported_template_variable');
            }
            $template = preg_replace('/\{\{\s*'.preg_quote($variable, '/').'\s*\}\}/', $values[$variable], $template) ?? $template;
        }

        return $template;
    }

    private function formatMinor(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
