<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Http\Requests\RouterRequest;
use App\Models\PlatformSubscription;
use App\Models\Router;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class RouterController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Router::class);

        return view('admin.routers.index', ['routers' => Router::query()->orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        $this->authorize('create', Router::class);

        return view('admin.routers.create');
    }

    public function store(RouterRequest $request, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Router::class);
        $subscription = PlatformSubscription::query()->with('plan')->where('tenant_id', $context->id())->where('status', 'active')->first();
        if ($subscription?->plan && Router::query()->count() >= $subscription->plan->max_routers) {
            throw ValidationException::withMessages(['plan' => 'Your '.$subscription->plan->name.' plan allows up to '.$subscription->plan->max_routers.' routers. Upgrade the SaaS plan to register another router.']);
        }
        $data = $request->validated();
        if ($data['connection_mode'] === 'agent') {
            $data['endpoint'] = null;
            $data['api_username'] = null;
            $data['api_password'] = null;
            $data['radius_secret'] = null;
        }
        $router = Router::create($data);
        $audit->record('router.created', (int) $context->id(), Router::class, (string) $router->id, ['connection_mode' => $router->connection_mode]);

        return redirect()->route('admin.routers.index')->with('status', 'Router registered. Credentials are encrypted and will not be displayed.');
    }

    public function edit(string $router): View
    {
        $router = Router::query()->findOrFail($router);
        $this->authorize('update', $router);

        return view('admin.routers.edit', ['router' => $router]);
    }

    public function update(RouterRequest $request, string $router, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $router = Router::query()->findOrFail($router);
        $this->authorize('update', $router);
        $data = $request->validated();
        if ($data['connection_mode'] === 'agent') {
            $data['endpoint'] = null;
            $data['api_username'] = null;
            $data['api_password'] = null;
            $data['radius_secret'] = null;
        } else {
            if (blank($data['radius_secret'] ?? null)) {
                unset($data['radius_secret']);
            }
            if (blank($data['api_username'] ?? null)) {
                unset($data['api_username']);
            }
            if (blank($data['api_password'] ?? null)) {
                unset($data['api_password']);
            }
            $data['agent_id'] = null;
        }
        $router->update($data);
        $audit->record('router.updated', (int) $context->id(), Router::class, (string) $router->id, ['connection_mode' => $router->connection_mode]);

        return redirect()->route('admin.routers.index')->with('status', 'Router updated.');
    }

    public function destroy(string $router, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $router = Router::query()->findOrFail($router);
        $this->authorize('delete', $router);
        $audit->record('router.deleted', (int) $context->id(), Router::class, (string) $router->id);
        $router->delete();

        return redirect()->route('admin.routers.index')->with('status', 'Router removed.');
    }
}
