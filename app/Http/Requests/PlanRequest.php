<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $planId = $this->route('plan');
        $nameRule = Rule::unique('plans', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId));
        if ($planId) {
            $nameRule->ignore($planId);
        }

        return [
            'name' => ['required', 'string', 'max:120', $nameRule],
            'price_minor' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'speed_down_kbps' => ['required', 'integer', 'min:1', 'max:1000000'],
            'speed_up_kbps' => ['required', 'integer', 'min:1', 'max:1000000'],
            'billing_period' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'data_cap_bytes' => ['nullable', 'integer', 'min:1', 'max:9223372036854775807'],
            'active' => ['required', 'boolean'],
        ];
    }
}
