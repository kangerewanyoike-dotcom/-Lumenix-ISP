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

    protected function prepareForValidation(): void
    {
        $this->merge(['use_tls' => $this->boolean('use_tls')]);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $routerId = $this->route('router');
        $router = $routerId ? Router::query()->find($routerId) : null;
        $mode = $this->input('connection_mode');
        $credentialsRequired = $mode === 'vpn'
            && (! $router || blank($router->api_username) || blank($router->api_password));
        $radiusSecretRequired = $mode === 'vpn' && (! $router || blank($router->radius_secret));
        $nameRule = Rule::unique('routers', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId));
        $agentRule = Rule::unique('routers', 'agent_id')->where(fn ($query) => $query->where('tenant_id', $tenantId));
        if ($routerId) {
            $nameRule->ignore($routerId);
            $agentRule->ignore($routerId);
        }

        return [
            'name' => ['required', 'string', 'max:120', $nameRule],
            'connection_mode' => ['required', Rule::in(['vpn', 'agent'])],
            'endpoint' => ['exclude_if:connection_mode,agent', 'required', 'nullable', new PrivateRouterEndpoint],
            'agent_id' => ['exclude_unless:connection_mode,agent', 'required', 'nullable', 'string', 'alpha_dash', 'max:120', $agentRule],
            'api_username' => ['exclude_if:connection_mode,agent', Rule::requiredIf($credentialsRequired), 'nullable', 'string', 'max:128'],
            'api_password' => ['exclude_if:connection_mode,agent', Rule::requiredIf($credentialsRequired), 'nullable', 'string', 'min:8', 'max:255'],
            'radius_secret' => ['exclude_if:connection_mode,agent', Rule::requiredIf($radiusSecretRequired), 'nullable', 'string', 'min:16', 'max:255'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'use_tls' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
