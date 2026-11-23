<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $customerId = $this->route('customer');
        $accountRule = Rule::unique('customers', 'account_number')->where(fn ($query) => $query->where('tenant_id', $tenantId));
        $usernameRule = Rule::unique('customers', 'pppoe_username');
        if ($customerId) {
            $accountRule->ignore($customerId);
            $usernameRule->ignore($customerId);
        }

        return [
            'account_number' => ['required', 'string', 'alpha_dash', 'max:64', $accountRule],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'regex:/^\+?[1-9][0-9]{6,14}$/'],
            'email' => ['nullable', 'email', 'max:254'],
            'address' => ['nullable', 'string', 'max:1000'],
            'plan_id' => [
                $customerId ? 'exclude' : 'required', 'integer',
                Rule::exists('plans', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'pppoe_username' => ['required', 'string', 'alpha_dash', 'max:64', $usernameRule],
            'pppoe_password' => [$customerId ? 'nullable' : 'required', 'string', 'min:8', 'max:128'],
            'status' => ['required', Rule::in(['active', 'suspended', 'disconnected'])],
            'billing_day' => [$customerId ? 'exclude' : 'required', 'integer', 'min:1', 'max:28'],
        ];
    }
}
