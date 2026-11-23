<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantContext;
use App\Models\MessagingAccount;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AutomationController extends Controller
{
    public function index(TenantContext $context): View
    {
        $this->authorizeAdmin(request());

        return view('admin.automation.index', [
            'tenant' => $context->current(),
            'account' => MessagingAccount::query()->first(),
            'templates' => NotificationTemplate::query()->orderBy('event_key')->get(),
            'deliveries' => NotificationDelivery::query()->latest()->limit(30)->get(),
            'defaultTemplate' => 'Hi {{customer_name}}, invoice {{invoice_number}} for {{amount}} KES is due {{due_date}}. Account: {{account_number}}.',
        ]);
    }

    public function settings(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'automation_enabled' => ['nullable', 'boolean'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:30'],
            'reminder_days' => ['nullable', 'array', 'max:8'],
            'reminder_days.*' => ['integer', 'min:0', 'max:30'],
        ]);
        $tenant = $context->current();
        $tenant->update([
            'automation_enabled' => (bool) ($data['automation_enabled'] ?? false),
            'grace_period_days' => (int) $data['grace_period_days'],
            'reminder_days' => array_values(array_unique(array_map('intval', $data['reminder_days'] ?? []))),
        ]);
        $audit->record('automation.policy_updated', (int) $tenant->id, Tenant::class, (string) $tenant->id, ['automation_enabled' => $tenant->automation_enabled, 'grace_period_days' => $tenant->grace_period_days]);

        return back()->with('status', 'Automation policy saved.');
    }

    public function messagingAccount(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'username' => ['required', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'sender_id' => ['nullable', 'string', 'max:32'],
            'active' => ['nullable', 'boolean'],
        ]);
        $account = MessagingAccount::query()->first();
        if (! $account && blank($data['api_key'] ?? null)) {
            return back()->withErrors(['api_key' => 'Enter the Africa’s Talking API key to configure the account.']);
        }
        $values = [
            'environment' => $data['environment'], 'username' => $data['username'],
            'sender_id' => $data['sender_id'] ?: null, 'active' => (bool) ($data['active'] ?? false),
        ];
        if (filled($data['api_key'] ?? null)) {
            $values['api_key'] = $data['api_key'];
        }
        if ($account) {
            $account->update($values);
        } else {
            MessagingAccount::create($values);
        }
        $audit->record('automation.messaging_account_updated', (int) $context->id(), MessagingAccount::class, $account ? (string) $account->id : null, ['environment' => $values['environment'], 'active' => $values['active']]);

        return back()->with('status', 'SMS account saved.');
    }

    public function template(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:500', function (string $attribute, string $value, \Closure $fail): void {
                preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $value, $matches);
                if (substr_count($value, '{{') !== substr_count($value, '}}') || array_diff(array_map('trim', $matches[1]), ['brand_name', 'customer_name', 'account_number', 'invoice_number', 'amount', 'due_date'])) {
                    $fail('Use only the supported template variables shown below.');
                }
            }],
        ]);
        NotificationTemplate::query()->updateOrCreate(['event_key' => 'invoice_reminder', 'channel' => 'sms'], ['body' => $data['body'], 'active' => true]);
        $audit->record('automation.template_updated', (int) $context->id(), NotificationTemplate::class, null, ['event_key' => 'invoice_reminder', 'channel' => 'sms']);

        return back()->with('status', 'Invoice reminder template saved.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->attributes->get('tenant_membership')?->role, ['owner', 'admin'], true), 403);
    }
}
