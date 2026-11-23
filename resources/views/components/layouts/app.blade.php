<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ config('app.name', 'Lumenix') }} · {{ \Illuminate\Support\Str::of(request()->route()?->getName() ?? 'workspace')->afterLast('.')->replace('-', ' ')->title() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell min-h-screen bg-slate-50 text-slate-900 antialiased" data-theme="{{ request()->attributes->has('tenant_membership') ? 'tenant' : (auth()->check() && auth()->user()->platformAdmin()->exists() ? 'platform' : 'default') }}">
    @php
        $membership = request()->attributes->get('tenant_membership');
        $isManager = in_array($membership?->role, ['owner', 'admin'], true);
        $isPlatformAdmin = auth()->check() && auth()->user()->platformAdmin()->exists();
        $platformBrandName = 'Lumenix';
        if ($isPlatformAdmin && \Illuminate\Support\Facades\Schema::hasTable('platform_settings')) {
            $platformBrandName = \Illuminate\Support\Facades\DB::table('platform_settings')->where('key', 'brand_name')->value('value') ?: 'Lumenix';
        }
        $hasTenant = (bool) $membership;
        $tenantLinks = [
            ['route' => 'tenant.dashboard', 'label' => 'Overview', 'icon' => 'grid'],
            ['route' => 'admin.customers.index', 'label' => 'Customer management', 'icon' => 'users'],
            ['route' => 'admin.plans.index', 'label' => 'Internet packages', 'icon' => 'layers'],
            ['route' => 'admin.routers.index', 'label' => 'MikroTik management', 'icon' => 'radio'],
            ['route' => 'admin.hotspot.index', 'label' => 'Hotspot management', 'icon' => 'radio'],
            ['route' => 'admin.pppoe.index', 'label' => 'PPPoE management', 'icon' => 'radio'],
            ['route' => 'admin.billing.index', 'label' => 'Billing', 'icon' => 'wallet'],
            ['route' => 'admin.payments.index', 'label' => 'Payments', 'icon' => 'wallet'],
            ['route' => 'admin.subscriptions.index', 'label' => 'Subscriptions', 'icon' => 'layers'],
            ['route' => 'admin.network.index', 'label' => 'Network monitoring', 'icon' => 'chart'],
        ];
        $managementLinks = [
            ['route' => 'admin.reports.index', 'label' => 'Reports', 'icon' => 'chart'],
            ['route' => 'admin.automation.index', 'label' => 'Notifications', 'icon' => 'spark'],
            ['route' => 'admin.support.index', 'label' => 'Support', 'icon' => 'users'],
            ['route' => 'admin.team.index', 'label' => 'Team', 'icon' => 'users'],
            ['route' => 'admin.branding.index', 'label' => 'Branding', 'icon' => 'palette'],
        ];
        $platformLinks = [
            ['route' => 'platform.dashboard', 'label' => 'Dashboard', 'icon' => 'grid'],
            ['route' => 'platform.tenants.index', 'label' => 'Tenants', 'icon' => 'building'],
            ['route' => 'platform.plans.index', 'label' => 'SaaS plans', 'icon' => 'layers'],
            ['route' => 'platform.billing.index', 'label' => 'Platform billing', 'icon' => 'wallet'],
            ['route' => 'platform.payments.index', 'label' => 'Payments', 'icon' => 'wallet'],
            ['route' => 'platform.support.index', 'label' => 'Support', 'icon' => 'users'],
            ['route' => 'platform.monitoring', 'label' => 'System health', 'icon' => 'chart'],
            ['route' => 'platform.settings', 'label' => 'Settings', 'icon' => 'palette'],
            ['route' => 'platform.audit.index', 'label' => 'Audit log', 'icon' => 'radio'],
        ];
        $currentName = request()->route()?->getName();
        $currentLabel = collect([...$tenantLinks, ...$managementLinks, ...$platformLinks])->first(fn ($link) => $currentName === $link['route'])['label'] ?? \Illuminate\Support\Str::of($currentName ?? 'Workspace')->afterLast('.')->replace('-', ' ')->title();
    @endphp

    <div class="app-frame {{ auth()->check() ? '' : 'guest-frame' }}" data-app-frame data-theme="{{ $hasTenant ? 'tenant' : ($isPlatformAdmin ? 'platform' : 'default') }}">
        @auth
        <div class="sidebar-scrim" data-sidebar-scrim></div>
        <aside class="app-sidebar" data-sidebar aria-label="Main navigation">
            <a href="{{ $hasTenant ? route('tenant.dashboard') : ($isPlatformAdmin ? route('platform.dashboard') : route('login')) }}" class="brand-lockup" aria-label="Lumenix home">
                <span class="brand-symbol">{{ \Illuminate\Support\Str::substr($platformBrandName, 0, 1) }}</span>
                <span class="brand-copy"><span class="brand-name">{{ $platformBrandName }}</span><span class="brand-caption">ISP OPERATIONS</span></span>
            </a>

            @if($hasTenant)
                <div class="workspace-switcher"><span class="workspace-avatar">{{ \Illuminate\Support\Str::substr($membership->tenant->name, 0, 1) }}</span><span class="workspace-copy"><span class="workspace-name">{{ $membership->tenant->name }}</span><span class="workspace-role">{{ ucfirst($membership->role) }} workspace</span></span><span class="workspace-more" aria-hidden="true">···</span></div>
                <nav class="sidebar-nav" aria-label="Workspace">
                    <p class="nav-section-label">WORKSPACE</p>
                    @foreach($tenantLinks as $link)
                        <a href="{{ route($link['route']) }}" title="{{ $link['label'] }}" @class(['nav-link', 'is-active' => $currentName === $link['route'] || ($link['route'] === 'admin.billing.index' && str_starts_with($currentName ?? '', 'admin.billing.'))]) @if($currentName === $link['route']) aria-current="page" @endif>
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                @if($link['icon'] === 'grid')<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>
                                @elseif($link['icon'] === 'users')<path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M17 11a3.5 3.5 0 1 0-1-6.85M17 14.5h.5a4 4 0 0 1 4 4V20"/>
                                @elseif($link['icon'] === 'layers')<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>
                                @elseif($link['icon'] === 'radio')<circle cx="12" cy="12" r="2"/><path d="M5 5a10 10 0 0 0 0 14M19 5a10 10 0 0 1 0 14M8 8a5.7 5.7 0 0 0 0 8M16 8a5.7 5.7 0 0 1 0 8"/>
                                @elseif($link['icon'] === 'wallet')<rect x="3" y="5" width="18" height="15" rx="2.5"/><path d="M3 9h18M16 14h2"/><path d="M6 5V3.8A1.8 1.8 0 0 1 8 2h10"/>
                                @elseif($link['icon'] === 'chart')<path d="M4 19.5h16M6.5 16V11M12 16V5M17.5 16v-8"/>
                                @elseif($link['icon'] === 'spark')<path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"/><path d="m19 15 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/>
                                @elseif($link['icon'] === 'palette')<path d="M12 3a9 9 0 1 0 0 18h1.2a2 2 0 0 0 1.4-3.4 1.7 1.7 0 0 1 1.2-2.9H18a3 3 0 0 0 3-3A9 9 0 0 0 12 3Z"/><circle cx="7.5" cy="11" r=".8"/><circle cx="10" cy="7.5" r=".8"/><circle cx="15" cy="7.5" r=".8"/>
                                @elseif($link['icon'] === 'building')<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 21v-4h6v4M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01"/>
                                @endif
                            </svg>
                            <span class="nav-label">{{ $link['label'] }}</span>
                            @if($currentName === $link['route'])<span class="nav-current-dot" aria-hidden="true"></span>@endif
                        </a>
                    @endforeach
                    @if($isManager)
                        <p class="nav-section-label nav-section-spaced">MANAGE</p>
                        @foreach($managementLinks as $link)
                            <a href="{{ route($link['route']) }}" title="{{ $link['label'] }}" @class(['nav-link', 'is-active' => $currentName === $link['route']]) @if($currentName === $link['route']) aria-current="page" @endif>
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    @if($link['icon'] === 'chart')<path d="M4 19.5h16M6.5 16V11M12 16V5M17.5 16v-8"/>
                                    @elseif($link['icon'] === 'spark')<path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"/><path d="m19 15 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/>
                                    @elseif($link['icon'] === 'users')<path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M17 11a3.5 3.5 0 1 0-1-6.85M17 14.5h.5a4 4 0 0 1 4 4V20"/>
                                @elseif($link['icon'] === 'spark')<path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"/><path d="m19 15 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/>
                                @else<path d="M12 3a9 9 0 1 0 0 18h1.2a2 2 0 0 0 1.4-3.4 1.7 1.7 0 0 1 1.2-2.9H18a3 3 0 0 0 3-3A9 9 0 0 0 12 3Z"/><circle cx="7.5" cy="11" r=".8"/><circle cx="10" cy="7.5" r=".8"/><circle cx="15" cy="7.5" r=".8"/>
                                    @endif
                                </svg><span class="nav-label">{{ $link['label'] }}</span>@if($currentName === $link['route'])<span class="nav-current-dot" aria-hidden="true"></span>@endif
                            </a>
                        @endforeach
                    @endif
                </nav>
            @elseif($isPlatformAdmin)
                <div class="workspace-switcher"><span class="workspace-avatar platform-avatar">{{ \Illuminate\Support\Str::substr($platformBrandName, 0, 1) }}</span><span class="workspace-copy"><span class="workspace-name">{{ $platformBrandName }}</span><span class="workspace-role">Platform console</span></span></div>
                <nav class="sidebar-nav" aria-label="Platform">
                    <p class="nav-section-label">PLATFORM</p>
                    @foreach($platformLinks as $link)
                        <a href="{{ route($link['route']) }}" title="{{ $link['label'] }}" @class(['nav-link', 'is-active' => $currentName === $link['route']]) @if($currentName === $link['route']) aria-current="page" @endif>
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                @if($link['icon'] === 'building')<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 21v-4h6v4M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01"/>
                                @elseif($link['icon'] === 'grid')<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>
                                @elseif($link['icon'] === 'layers')<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>
                                @elseif($link['icon'] === 'chart')<path d="M4 19.5h16M6.5 16V11M12 16V5M17.5 16v-8"/>
                                @elseif($link['icon'] === 'radio')<circle cx="12" cy="12" r="2"/><path d="M5 5a10 10 0 0 0 0 14M19 5a10 10 0 0 1 0 14M8 8a5.7 5.7 0 0 0 0 8M16 8a5.7 5.7 0 0 1 0 8"/>
                                @elseif($link['icon'] === 'users')<path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M17 11a3.5 3.5 0 1 0-1-6.85M17 14.5h.5a4 4 0 0 1 4 4V20"/>
                                @elseif($link['icon'] === 'palette')<path d="M12 3a9 9 0 1 0 0 18h1.2a2 2 0 0 0 1.4-3.4 1.7 1.7 0 0 1 1.2-2.9H18a3 3 0 0 0 3-3A9 9 0 0 0 12 3Z"/><circle cx="7.5" cy="11" r=".8"/>
                                @else<rect x="3" y="5" width="18" height="15" rx="2.5"/><path d="M3 9h18M16 14h2M6 5V3.8A1.8 1.8 0 0 1 8 2h10"/>
                                @endif
                            </svg><span class="nav-label">{{ $link['label'] }}</span>@if($currentName === $link['route'])<span class="nav-current-dot" aria-hidden="true"></span>@endif
                        </a>
                    @endforeach
                </nav>
            @endif

            <div class="sidebar-bottom">
                <div class="sidebar-help"><span class="help-orb">?</span><span class="sidebar-help-copy"><strong>Need a hand?</strong><span>We’re here to help.</span></span><span class="help-arrow">↗</span></div>
                <div class="sidebar-profile">
                    <span class="profile-avatar">{{ \Illuminate\Support\Str::substr(auth()->user()?->name ?? 'L', 0, 1) }}</span>
                    <span class="profile-copy"><strong>{{ auth()->user()?->name ?? 'Guest' }}</strong><span>{{ $membership?->role ? ucfirst($membership->role) : ($isPlatformAdmin ? 'Platform admin' : 'Account') }}</span></span>
                    @auth
                        <form method="POST" action="{{ route('logout') }}" class="profile-logout">@csrf<button type="submit" aria-label="Sign out" title="Sign out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M21 4v16"/></svg></button></form>
                    @endauth
                </div>
            </div>
            <button type="button" class="sidebar-collapse" data-sidebar-toggle aria-label="Collapse sidebar" aria-expanded="true" title="Collapse sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/><path d="M20 4v16"/></svg></button>
        </aside>
        @endauth

        <main class="app-main">
            <header class="topbar">
                <div class="topbar-leading">
                    @auth
                    <button type="button" class="mobile-menu-toggle" data-mobile-menu aria-label="Open navigation" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                    @endauth
                    <div class="topbar-heading"><div class="breadcrumb"><span>{{ $hasTenant ? $membership->tenant->name : ($isPlatformAdmin ? 'Platform' : 'Welcome') }}</span><span class="breadcrumb-separator">/</span><strong>{{ $currentLabel }}</strong></div><h1>{{ $currentLabel }}</h1></div>
                </div>
                <div class="topbar-actions">
                    @if($hasTenant)<span class="topbar-status"><span></span>Workspace active</span>@elseif($isPlatformAdmin)<span class="topbar-status platform-status"><span></span>Platform console</span>@endif
                    <span class="topbar-date">{{ now()->format('D, M j') }}</span>
                    <span class="topbar-user-avatar" title="{{ auth()->user()?->name }}">{{ \Illuminate\Support\Str::substr(auth()->user()?->name ?? 'L', 0, 1) }}</span>
                </div>
            </header>

            <div class="page-content">
                @if(session('status'))<div role="status" class="flash-message flash-success"><span class="flash-mark">✓</span><span>{{ session('status') }}</span></div>@endif
                @if(session('warning'))<div role="status" class="flash-message flash-warning"><span class="flash-mark">!</span><span>{{ session('warning') }}</span></div>@endif
                @if(session('recovery_codes'))
                    <section class="flash-message flash-warning"><div><strong>Save these recovery codes now.</strong><p>Each code works once and will not be shown again.</p><ul class="recovery-code-list">@foreach(session('recovery_codes') as $code)<li>{{ $code }}</li>@endforeach</ul></div></section>
                @endif
                @if($errors->any())<div role="alert" class="flash-message flash-error"><span class="flash-mark">!</span><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                {{ $slot }}
                <footer class="app-footer"><span>© {{ now()->year }} Lumenix</span><span>Built for reliable internet operations</span></footer>
            </div>
        </main>
    </div>
</body>
</html>
