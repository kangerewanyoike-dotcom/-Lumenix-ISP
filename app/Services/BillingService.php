<?php

namespace App\Services;

use App\Domain\Tenancy\TenantContext;
use App\Models\BillingOperation;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Plan;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillingService
{
    public function generateRecurringInvoice(Customer $customer, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?string $idempotencyKey = null): Invoice
    {
        $this->assertPeriod($periodStart, $periodEnd);
        $key = $idempotencyKey ?? "recurring:{$customer->id}:{$periodStart->toDateString()}:{$periodEnd->toDateString()}";

        return DB::transaction(function () use ($customer, $periodStart, $periodEnd, $key): Invoice {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($existing = Invoice::query()->where('idempotency_key', $key)->first()) {
                $this->assertSameCustomer($existing->customer_id, $locked->id);
                $line = $existing->lines()->first();
                if ($line?->source_type !== 'plan' || $existing->period_start?->toDateString() !== $periodStart->toDateString() || $existing->period_end?->toDateString() !== $periodEnd->toDateString()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key was already used for a different invoice request.']);
                }

                return $existing->load('lines');
            }
            $plan = $locked->plan;
            if (! $plan) {
                throw ValidationException::withMessages(['plan' => 'The customer needs a plan before recurring billing.']);
            }
            $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
            if ($plan->currency !== $tenant->currency) {
                throw ValidationException::withMessages(['currency' => 'The customer plan currency must match the tenant billing currency.']);
            }

            $invoice = $this->createInvoice($locked, $key, $periodStart->startOfDay(), $periodEnd->startOfDay(), $plan->price_minor, "{$plan->name} ({$periodStart->toDateString()} – {$periodEnd->toDateString()})", $periodStart, 'plan', (string) $plan->id);
            app(AuditLogger::class)->record('invoice.recurring_issued', (int) app(TenantContext::class)->id(), Invoice::class, (string) $invoice->id);

            return $invoice->load('lines');
        }, 3);
    }

    public function createOneOffCharge(Customer $customer, string $description, int $amountMinor, CarbonImmutable $issuedAt, string $idempotencyKey): Invoice
    {
        if ($amountMinor < 1 || trim($description) === '' || mb_strlen($description) > 200) {
            throw ValidationException::withMessages(['charge' => 'Enter a description and a positive amount in minor units.']);
        }

        return DB::transaction(function () use ($customer, $description, $amountMinor, $issuedAt, $idempotencyKey): Invoice {
            Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($invoice = Invoice::query()->where('idempotency_key', $idempotencyKey)->first()) {
                $this->assertSameCustomer($invoice->customer_id, $customer->id);
                $line = $invoice->lines()->first();
                if ($line?->source_type !== 'one_off' || $line->description !== $description || $line->subtotal_minor !== $amountMinor) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key was already used for a different invoice request.']);
                }

                return $invoice->load('lines');
            }

            return $this->createInvoice($customer, $idempotencyKey, null, null, $amountMinor, $description, $issuedAt, 'one_off')->load('lines');
        }, 3);
    }

    public function grantCredit(Customer $customer, int $amountMinor, string $description, string $idempotencyKey): CreditLedgerEntry
    {
        if ($amountMinor < 1 || trim($description) === '' || mb_strlen($description) > 200) {
            throw ValidationException::withMessages(['credit' => 'Enter a description and a positive credit amount in minor units.']);
        }

        return DB::transaction(function () use ($customer, $amountMinor, $description, $idempotencyKey): CreditLedgerEntry {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($entry = CreditLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first()) {
                $this->assertSameCustomer($entry->customer_id, $locked->id);
                if ($entry->kind !== 'grant' || $entry->amount_minor !== $amountMinor || $entry->description !== $description) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key was already used for a different credit request.']);
                }

                return $entry;
            }
            $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());

            return CreditLedgerEntry::create([
                'customer_id' => $locked->id, 'amount_minor' => $amountMinor, 'currency' => $tenant->currency,
                'kind' => 'grant', 'description' => $description, 'idempotency_key' => $idempotencyKey,
                'created_by' => Auth::id(), 'created_at' => now(),
            ]);
        }, 3);
    }

    public function applyCreditToInvoice(Invoice $invoice, ?int $amountMinor, string $idempotencyKey): ?CreditLedgerEntry
    {
        return DB::transaction(function () use ($invoice, $amountMinor, $idempotencyKey): ?CreditLedgerEntry {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $customer = Customer::query()->lockForUpdate()->findOrFail($lockedInvoice->customer_id);
            if ($entry = CreditLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first()) {
                $this->assertSameCustomer($entry->customer_id, $customer->id);
                if ((int) $entry->invoice_id !== (int) $lockedInvoice->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different invoice.']);
                }
                if ($entry->kind !== 'application') {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different credit operation.']);
                }

                return $entry;
            }
            $available = (int) CreditLedgerEntry::query()->where('customer_id', $customer->id)->sum('amount_minor');
            $apply = min($amountMinor ?? $lockedInvoice->balance_minor, $lockedInvoice->balance_minor, $available);
            if ($apply <= 0) {
                return null;
            }
            $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
            $entry = CreditLedgerEntry::create([
                'customer_id' => $customer->id, 'invoice_id' => $lockedInvoice->id, 'amount_minor' => -$apply,
                'currency' => $tenant->currency, 'kind' => 'application', 'description' => 'Credit applied to invoice '.$lockedInvoice->number,
                'idempotency_key' => $idempotencyKey, 'created_by' => Auth::id(), 'created_at' => now(),
            ]);
            $lockedInvoice->balance_minor -= $apply;
            $lockedInvoice->status = $lockedInvoice->balance_minor === 0 ? 'paid' : 'partially_paid';
            $lockedInvoice->save();
            app(AutomationService::class)->restoreAfterInvoicePayment($lockedInvoice);

            return $entry;
        }, 3);
    }

    /** @return array{invoice: ?Invoice, credit: ?CreditLedgerEntry, adjustment_minor: int} */
    public function changePlan(Customer $customer, Plan $newPlan, CarbonImmutable $effectiveAt, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, string $idempotencyKey): array
    {
        $this->assertPeriod($periodStart, $periodEnd);
        if ($effectiveAt->lt($periodStart) || $effectiveAt->gt($periodEnd)) {
            throw ValidationException::withMessages(['effective_at' => 'The effective date must fall inside the supplied billing period.']);
        }
        if ($effectiveAt->toDateString() > CarbonImmutable::today(app(TenantContext::class)->current()->timezone ?? config('app.timezone'))->toDateString()) {
            throw ValidationException::withMessages(['effective_at' => 'Plan changes cannot be scheduled for a future date.']);
        }

        return DB::transaction(function () use ($customer, $newPlan, $effectiveAt, $periodStart, $periodEnd, $idempotencyKey): array {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($operation = BillingOperation::query()->where('idempotency_key', $idempotencyKey)->first()) {
                $this->assertSameCustomer($operation->customer_id, $locked->id);
                if ($operation->operation !== 'plan_change') {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key belongs to a different billing operation.']);
                }

                return $this->operationResult($operation);
            }
            $oldPlan = $locked->plan;
            $newPlan = Plan::query()->findOrFail($newPlan->id);
            if (! $oldPlan || $oldPlan->billing_period !== $newPlan->billing_period) {
                throw ValidationException::withMessages(['plan' => 'A prorated plan change must keep the same billing period.']);
            }
            $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
            if ($oldPlan->currency !== $newPlan->currency || $newPlan->currency !== $tenant->currency) {
                throw ValidationException::withMessages(['currency' => 'Both plans must use the tenant billing currency.']);
            }

            $remainingDays = $effectiveAt->startOfDay()->diffInDays($periodEnd->startOfDay()) + 1;
            $periodDays = $periodStart->startOfDay()->diffInDays($periodEnd->startOfDay()) + 1;
            $delta = $newPlan->price_minor - $oldPlan->price_minor;
            $adjustment = $this->roundedRatio($delta * $remainingDays, $periodDays);
            $operation = BillingOperation::create([
                'customer_id' => $locked->id, 'operation' => 'plan_change', 'effective_at' => $effectiveAt->toDateString(),
                'idempotency_key' => $idempotencyKey, 'created_at' => now(),
            ]);
            $invoice = null;
            $credit = null;
            if ($adjustment > 0) {
                $invoice = $this->createInvoice($locked, 'operation:'.$idempotencyKey, null, null, $adjustment, 'Prorated plan change to '.$newPlan->name, $effectiveAt, 'plan_change', (string) $newPlan->id);
            } elseif ($adjustment < 0) {
                $grossCredit = abs($adjustment + $this->roundedRatio($adjustment * (int) Tenant::query()->findOrFail(app(TenantContext::class)->id())->vat_rate_basis_points, 10000));
                $credit = $this->createCreditEntry($locked, $grossCredit, 'Prorated plan downgrade from '.$oldPlan->name, 'operation:'.$idempotencyKey, 'adjustment');
            }
            $locked->plan_id = $newPlan->id;
            $locked->save();
            $operation->result = ['invoice_id' => $invoice?->id, 'credit_id' => $credit?->id, 'adjustment_minor' => $adjustment];
            $operation->save();

            return ['invoice' => $invoice, 'credit' => $credit, 'adjustment_minor' => $adjustment];
        }, 3);
    }

    /** @return array{invoice: ?Invoice, credit: ?CreditLedgerEntry, adjustment_minor: int} */
    public function changeBillingDay(Customer $customer, int $newBillingDay, CarbonImmutable $cycleStart, CarbonImmutable $oldCycleEnd, CarbonImmutable $newCycleEnd, string $idempotencyKey): array
    {
        $this->assertPeriod($cycleStart, $oldCycleEnd);
        if ($newBillingDay < 1 || $newBillingDay > 28 || $newCycleEnd->lt($cycleStart) || $cycleStart->diffInDays($newCycleEnd) > 365 || $newCycleEnd->addDay()->day !== $newBillingDay) {
            throw ValidationException::withMessages(['billing_day' => 'Choose a billing day from 1 to 28 and a valid new cycle end.']);
        }

        return DB::transaction(function () use ($customer, $newBillingDay, $cycleStart, $oldCycleEnd, $newCycleEnd, $idempotencyKey): array {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($operation = BillingOperation::query()->where('idempotency_key', $idempotencyKey)->first()) {
                $this->assertSameCustomer($operation->customer_id, $locked->id);
                if ($operation->operation !== 'billing_day_change') {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key belongs to a different billing operation.']);
                }

                return $this->operationResult($operation);
            }
            $plan = $locked->plan;
            if (! $plan || $plan->billing_period !== 'monthly') {
                throw ValidationException::withMessages(['billing_day' => 'Billing-day adjustments require a monthly customer plan.']);
            }
            $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
            $oldDays = $cycleStart->startOfDay()->diffInDays($oldCycleEnd->startOfDay()) + 1;
            $newDays = $cycleStart->startOfDay()->diffInDays($newCycleEnd->startOfDay()) + 1;
            $adjustment = $this->roundedRatio($plan->price_minor * ($newDays - $oldDays), $oldDays);
            $operation = BillingOperation::create([
                'customer_id' => $locked->id, 'operation' => 'billing_day_change', 'effective_at' => $oldCycleEnd->addDay()->toDateString(),
                'idempotency_key' => $idempotencyKey, 'created_at' => now(),
            ]);
            $invoice = null;
            $credit = null;
            if ($adjustment > 0) {
                $issuedAt = CarbonImmutable::now($tenant->timezone ?? config('app.timezone'));
                $invoice = $this->createInvoice($locked, 'operation:'.$idempotencyKey, null, null, $adjustment, 'Prorated billing-day change', $issuedAt, 'billing_day_change');
            } elseif ($adjustment < 0) {
                $grossCredit = abs($adjustment + $this->roundedRatio($adjustment * (int) $tenant->vat_rate_basis_points, 10000));
                $credit = $this->createCreditEntry($locked, $grossCredit, 'Prorated billing-day change', 'operation:'.$idempotencyKey, 'adjustment');
            }
            $locked->billing_day = $newBillingDay;
            $locked->billing_anchor_date = $newCycleEnd->addDay()->toDateString();
            $locked->save();
            $operation->result = ['invoice_id' => $invoice?->id, 'credit_id' => $credit?->id, 'adjustment_minor' => $adjustment];
            $operation->save();

            return ['invoice' => $invoice, 'credit' => $credit, 'adjustment_minor' => $adjustment];
        }, 3);
    }

    public function runRecurringForTenantDate(CarbonImmutable $date): int
    {
        $issued = 0;
        foreach (Customer::query()->where(fn ($query) => $query->where('status', 'active')->orWhere(fn ($renewal) => $renewal->where('status', 'suspended')->whereIn('suspension_reason', ['payment_overdue', 'subscription_expired'])))->with('plan')->orderBy('id')->get() as $customer) {
            $plan = $customer->plan;
            if (! $plan) {
                continue;
            }
            $anchor = $customer->billing_anchor_date ?? CarbonImmutable::parse($customer->created_at)->startOfDay();
            if (! $this->isDue($customer, $plan, $anchor, $date)) {
                continue;
            }
            [$start, $end] = match ($plan->billing_period) {
                'daily' => [$date, $date],
                'weekly' => [$date, $date->addDays(6)],
                default => [$date, $date->addMonthNoOverflow()->subDay()],
            };
            $this->generateRecurringInvoice($customer, $start, $end);
            $issued++;
        }

        return $issued;
    }

    private function isDue(Customer $customer, Plan $plan, CarbonImmutable $anchor, CarbonImmutable $date): bool
    {
        if ($plan->billing_period === 'monthly') {
            return $date->toDateString() >= $anchor->toDateString() && (int) $date->format('j') === $customer->billing_day;
        }
        $days = $anchor->startOfDay()->diffInDays($date->startOfDay(), false);

        return $days >= 0 && ($plan->billing_period === 'daily' || $days % 7 === 0);
    }

    private function createInvoice(Customer $customer, string $idempotencyKey, ?CarbonImmutable $periodStart, ?CarbonImmutable $periodEnd, int $subtotal, string $description, CarbonImmutable $issuedAt, ?string $sourceType = null, ?string $sourceId = null): Invoice
    {
        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
        $tax = $this->roundedRatio($subtotal * (int) $tenant->vat_rate_basis_points, 10000);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'number' => $this->invoiceNumber($customer, $issuedAt, $idempotencyKey),
            'status' => 'issued', 'period_start' => $periodStart?->toDateString(), 'period_end' => $periodEnd?->toDateString(),
            'issued_at' => $issuedAt->toDateString(), 'due_at' => $issuedAt->addDays(7)->toDateString(),
            'currency' => $tenant->currency, 'subtotal_minor' => $subtotal, 'tax_minor' => $tax,
            'total_minor' => $subtotal + $tax, 'balance_minor' => $subtotal + $tax, 'idempotency_key' => $idempotencyKey,
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice->id, 'description' => mb_substr($description, 0, 240), 'quantity' => 1,
            'unit_amount_minor' => $subtotal, 'subtotal_minor' => $subtotal, 'tax_rate_basis_points' => $tenant->vat_rate_basis_points,
            'tax_minor' => $tax, 'total_minor' => $subtotal + $tax, 'source_type' => $sourceType, 'source_id' => $sourceId,
        ]);

        return $invoice;
    }

    private function createCreditEntry(Customer $customer, int $amountMinor, string $description, string $idempotencyKey, string $kind): CreditLedgerEntry
    {
        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());

        return CreditLedgerEntry::create([
            'customer_id' => $customer->id, 'amount_minor' => $amountMinor, 'currency' => $tenant->currency,
            'kind' => $kind, 'description' => $description, 'idempotency_key' => $idempotencyKey,
            'created_by' => Auth::id(), 'created_at' => now(),
        ]);
    }

    private function operationResult(BillingOperation $operation): array
    {
        $result = $operation->result ?? [];

        return [
            'invoice' => isset($result['invoice_id']) ? Invoice::query()->find($result['invoice_id']) : null,
            'credit' => isset($result['credit_id']) ? CreditLedgerEntry::query()->find($result['credit_id']) : null,
            'adjustment_minor' => (int) ($result['adjustment_minor'] ?? 0),
        ];
    }

    private function invoiceNumber(Customer $customer, CarbonImmutable $date, string $idempotencyKey): string
    {
        return 'LX-'.$customer->tenant_id.'-'.$date->format('Ymd').'-'.substr(hash('sha256', $idempotencyKey), 0, 16);
    }

    private function assertPeriod(CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($end->lt($start) || $start->diffInDays($end) > 365) {
            throw ValidationException::withMessages(['period' => 'The billing period must be valid and no longer than one year.']);
        }
    }

    private function roundedRatio(int $numerator, int $denominator): int
    {
        $sign = $numerator < 0 ? -1 : 1;
        $absolute = abs($numerator);

        return $sign * intdiv($absolute + intdiv($denominator, 2), $denominator);
    }

    private function assertSameCustomer(int|string $actualCustomerId, int|string $expectedCustomerId): void
    {
        if ((string) $actualCustomerId !== (string) $expectedCustomerId) {
            throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for another customer.']);
        }
    }
}
