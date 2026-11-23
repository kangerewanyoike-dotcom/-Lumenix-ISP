<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\NotificationDelivery;
use App\Models\Payment;
use App\Models\PaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CustomerPrivacyService
{
    public function anonymizeAndArchive(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $customer->refresh();
            $oldUsername = $customer->pppoe_username;
            $anonymousUsername = 'deleted-'.$customer->id;

            // Keep financial records and aggregate usage for accounting, while removing direct customer identifiers.
            $customer->update([
                'name' => 'Deleted customer '.$customer->id,
                'account_number' => 'DELETED-'.$customer->id,
                'phone' => '+254000000000',
                'email' => null,
                'address' => null,
                'pppoe_username' => $anonymousUsername,
                'pppoe_password' => Str::random(64),
                'status' => 'disconnected',
                'suspension_reason' => null,
                'suspended_at' => null,
            ]);

            CustomerPortalAccount::query()->where('customer_id', $customer->id)->first()?->update([
                'active' => false,
                'email' => 'deleted-'.$customer->id.'@invalid.local',
                'password' => Str::random(64),
                'last_login_at' => null,
            ]);
            Payment::query()->where('customer_id', $customer->id)->update(['phone' => null, 'account_reference' => null]);
            PaymentRequest::query()->where('customer_id', $customer->id)->update(['phone' => 'REDACTED']);
            foreach (NotificationDelivery::query()->where('customer_id', $customer->id)->get() as $delivery) {
                $delivery->update(['destination' => 'REDACTED']);
            }

            DB::table('radacct')->where('username', $oldUsername)->update([
                'username' => $anonymousUsername,
                'callingstationid' => null,
                'framedipaddress' => null,
                'framedipv6address' => null,
                'framedipv6prefix' => null,
                'delegatedipv6prefix' => null,
                'framedinterfaceid' => null,
            ]);
            DB::table('radpostauth')->where('username', $oldUsername)->delete();

            $customer->delete();
            app(AuditLogger::class)->record('customer.anonymized_and_archived', (int) $customer->tenant_id, Customer::class, (string) $customer->id);
        });
    }
}
