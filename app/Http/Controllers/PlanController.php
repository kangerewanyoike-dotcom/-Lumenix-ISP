<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PlanController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Plan::class);

        return view('admin.plans.index', ['plans' => Plan::query()->orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        $this->authorize('create', Plan::class);

        return view('admin.plans.create');
    }

    public function store(PlanRequest $request, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Plan::class);
        $plan = Plan::create([...$request->validated(), 'currency' => $context->current()->currency]);
        $audit->record('plan.created', (int) $context->id(), Plan::class, (string) $plan->id);

        return redirect()->route('admin.plans.index')->with('status', 'Plan created.');
    }

    public function edit(string $plan): View
    {
        $plan = Plan::query()->findOrFail($plan);
        $this->authorize('update', $plan);

        return view('admin.plans.edit', ['plan' => $plan]);
    }

    public function update(PlanRequest $request, string $plan, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $plan = Plan::query()->findOrFail($plan);
        $this->authorize('update', $plan);
        $plan->update([...$request->validated(), 'currency' => $context->current()->currency]);
        $audit->record('plan.updated', (int) $context->id(), Plan::class, (string) $plan->id);

        return redirect()->route('admin.plans.index')->with('status', 'Plan updated.');
    }

    public function destroy(string $plan, AuditLogger $audit, TenantContext $context): RedirectResponse
    {
        $plan = Plan::query()->findOrFail($plan);
        $this->authorize('delete', $plan);
        if ($plan->customers()->withTrashed()->exists()) {
            return back()->withErrors(['plan' => 'A plan with customer history cannot be deleted. Deactivate it instead.']);
        }

        $audit->record('plan.deleted', (int) $context->id(), Plan::class, (string) $plan->id);
        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', 'Plan deleted.');
    }
}
