<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\TenantContext;
use App\Models\Router;
use App\Rules\PrivateRouterEndpoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $routerId = $this->route('router');
        $router = $routerId ? Router::query()->find($routerId) : null;
        $radiusSecretRequired = ! $router || blank($router->radius_secret);
        $nameRule = Rule::unique('routers', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId));
        if ($routerId) {
            $nameRule->ignore($routerId);
        }

        return [
            'name' => ['required', 'string', 'max:120', $nameRule],
            'connection_mode' => ['required', Rule::in(['vpn'])],
            'endpoint' => ['required', new PrivateRouterEndpoint],
            'radius_secret' => [Rule::requiredIf($radiusSecretRequired), 'nullable', 'string', 'min:16', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
