<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveCustomerPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = CustomerPortalAccount::withoutGlobalScopes()->where('active', true)->find($request->session()->get('customer_portal_account_id'));
        abort_unless($account, 401);
        $tenant = Tenant::query()->find($account->tenant_id);
        abort_unless($tenant && $tenant->status === 'active', 403);
        $context = app(TenantContext::class);
        $context->set($tenant);
        try {
            $customer = Customer::query()->find($account->customer_id);
            abort_unless($customer, 403);
            $account->setRelation('customer', $customer);
            $request->attributes->set('customer_portal_account', $account);
            $request->attributes->set('portal_tenant', $tenant);

            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
