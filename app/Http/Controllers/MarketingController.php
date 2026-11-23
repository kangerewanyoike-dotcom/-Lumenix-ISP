<?php

namespace App\Http\Controllers;

use App\Models\PlatformPlan;
use App\Models\PlatformSupportTicket;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class MarketingController extends Controller
{
    public function index(): View
    {
        return view('marketing.home', [
            'plans' => PlatformPlan::query()->where('active', true)->orderBy('monthly_price_minor')->get(),
            'supportEmail' => DB::table('platform_settings')->where('key', 'support_email')->value('value'),
        ]);
    }

    public function contact(Request $request, AuditLogger $audit): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return back()->with('contact_status', 'Thanks. Your request has been received.');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:160'],
            'company' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'interest' => ['required', 'in:product_walkthrough,pricing,network_setup,other'],
            'message' => ['required', 'string', 'max:5000'],
            'consent' => ['accepted'],
        ]);
        if ($validator->fails()) {
            return redirect()->to(route('home').'#contact')->withErrors($validator)->withInput();
        }
        $data = $validator->validated();

        $ticket = PlatformSupportTicket::query()->create([
            'tenant_id' => null,
            'subject' => 'Website enquiry: '.$data['company'],
            'description' => implode("\n", [
                'Website contact request',
                'Name: '.$data['name'],
                'Company / ISP: '.$data['company'],
                'Email: '.$data['email'],
                'Phone: '.($data['phone'] ?: 'Not provided'),
                'Interest: '.str_replace('_', ' ', $data['interest']),
                '',
                'Message:',
                $data['message'],
            ]),
            'priority' => 'normal',
            'status' => 'open',
            'created_by' => null,
        ]);

        $audit->record('marketing.contact_submitted', null, PlatformSupportTicket::class, (string) $ticket->id, ['source' => 'public_homepage']);

        return redirect()->to(route('home').'#contact')->with('contact_status', 'Your request is in the Lumenix team queue. We will follow up using the contact details you provided.');
    }

    public function privacy(): View
    {
        return view('marketing.privacy', ['supportEmail' => DB::table('platform_settings')->where('key', 'support_email')->value('value')]);
    }

    public function terms(): View
    {
        return view('marketing.terms', ['supportEmail' => DB::table('platform_settings')->where('key', 'support_email')->value('value')]);
    }
}
