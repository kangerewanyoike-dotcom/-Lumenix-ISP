<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1728">
    <meta name="description" content="Run subscriber billing, M-Pesa collections, MikroTik and FreeRADIUS operations in one ISP workspace with Lumenix.">
    <title>Lumenix — Run your ISP with clarity</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f5f7fa] font-sans text-slate-900 antialiased">
    <header class="sticky top-0 z-50 border-b border-white/10 bg-[#0b1728]/95 text-white shadow-lg shadow-slate-950/5 backdrop-blur-xl">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3.5 sm:px-8" aria-label="Main navigation">
            <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="Lumenix home">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-teal-200 to-cyan-400 text-lg font-black text-[#0b1728] shadow-lg shadow-cyan-950/20">L</span>
                <span><span class="block text-lg font-bold tracking-tight">Lumenix</span><span class="block text-[9px] font-semibold uppercase tracking-[.2em] text-slate-400">ISP operations</span></span>
            </a>
            <div class="hidden items-center gap-7 text-[13px] font-medium text-slate-300 lg:flex">
                <a class="transition hover:text-white" href="#platform">Platform</a>
                <a class="transition hover:text-white" href="#billing">Two billing systems</a>
                <a class="transition hover:text-white" href="#how-it-works">How it works</a>
                <a class="transition hover:text-white" href="#pricing">Pricing</a>
                <a class="transition hover:text-white" href="#contact">Contact</a>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <a class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10 hover:text-white sm:inline-flex" href="{{ route('login') }}">Sign in</a>
                <a class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-[#0b1728] shadow-sm transition hover:bg-teal-50" href="{{ route('register') }}">Get started <span aria-hidden="true">↗</span></a>
            </div>
        </nav>
    </header>

    <main>
        <section class="relative isolate overflow-hidden bg-[#0b1728] text-white">
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
                <div class="absolute -right-40 -top-56 h-[39rem] w-[39rem] rounded-full bg-blue-500/20 blur-3xl"></div>
                <div class="absolute -bottom-80 left-[24%] h-[40rem] w-[40rem] rounded-full bg-teal-400/10 blur-3xl"></div>
                <div class="absolute inset-0 opacity-[.065]" style="background-image:radial-gradient(#dbeafe 1px,transparent 1px);background-size:25px 25px"></div>
            </div>
            <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 pb-20 pt-14 sm:px-8 sm:pb-24 sm:pt-20 lg:grid-cols-[.92fr_1.08fr] lg:gap-12 lg:py-24">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2.5 rounded-full border border-teal-200/20 bg-teal-100/[.07] px-3.5 py-2 text-[11px] font-semibold uppercase tracking-[.12em] text-teal-100"><span class="h-1.5 w-1.5 rounded-full bg-teal-300 shadow-[0_0_12px_#5eead4]"></span>Billing and network operations for ISPs</div>
                    <h1 class="mt-7 text-[2.65rem] font-semibold leading-[1.04] tracking-[-.055em] sm:text-5xl lg:text-[4rem]">Your network keeps people connected.<br><span class="bg-gradient-to-r from-teal-200 via-cyan-200 to-blue-300 bg-clip-text text-transparent">Your software should keep you in control.</span></h1>
                    <p class="mt-6 max-w-xl text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">Bring subscriber billing, M-Pesa collections, MikroTik access and service visibility into one workspace designed around ISP operations.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-300 px-5 py-3.5 text-sm font-bold text-[#0b1728] shadow-lg shadow-teal-950/25 transition hover:-translate-y-0.5 hover:bg-teal-200">Create your ISP workspace <span aria-hidden="true">→</span></a>
                        <a href="#contact" class="inline-flex items-center justify-center rounded-xl border border-white/15 px-5 py-3.5 text-sm font-semibold text-white transition hover:border-white/25 hover:bg-white/[.07]">Request a product walkthrough</a>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-x-5 gap-y-3 text-xs font-medium text-slate-400">
                        <span class="inline-flex items-center gap-2"><span class="grid h-4 w-4 place-items-center rounded-full bg-teal-300/15 text-[10px] text-teal-200">✓</span> Separate ISP and SaaS billing</span>
                        <span class="inline-flex items-center gap-2"><span class="grid h-4 w-4 place-items-center rounded-full bg-teal-300/15 text-[10px] text-teal-200">✓</span> Built for MikroTik and RADIUS workflows</span>
                    </div>
                    <p class="mt-8 text-[10px] font-semibold uppercase tracking-[.18em] text-slate-500">Built for the work behind every connection</p>
                    <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-bold text-slate-300"><span>MIKROTIK</span><span class="text-slate-600">/</span><span>FREERADIUS</span><span class="text-slate-600">/</span><span>M-PESA</span><span class="text-slate-600">/</span><span>PPPoE</span><span class="text-slate-600">/</span><span>HOTSPOT</span></div>
                </div>

                <div class="relative mx-auto w-full max-w-2xl lg:ml-auto">
                    <div class="absolute -inset-6 rounded-[2rem] bg-gradient-to-br from-teal-300/15 via-blue-500/10 to-violet-500/10 blur-2xl" aria-hidden="true"></div>
                    <div class="relative rotate-[.35deg] overflow-hidden rounded-[1.5rem] border border-white/15 bg-[#f9fbff] p-2 shadow-2xl shadow-black/40">
                        <div class="overflow-hidden rounded-[1.05rem] border border-slate-200 bg-white">
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3.5 sm:px-5">
                                <div class="flex items-center gap-2.5"><span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-50 text-sm font-black text-teal-800">N</span><span><span class="block text-xs font-bold text-slate-800">Northstar Fibre</span><span class="block text-[10px] text-slate-400">ISP workspace · illustrative preview</span></span></div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-bold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Workspace overview</span>
                            </div>
                            <div class="grid min-h-[390px] sm:grid-cols-[122px_1fr]">
                                <aside class="hidden border-r border-slate-100 bg-slate-50/70 p-3 sm:block" aria-label="Illustrative workspace navigation"><div class="mb-5 flex items-center gap-1.5 px-2"><span class="h-1.5 w-1.5 rounded-full bg-rose-300"></span><span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span></div><p class="px-2 text-[8px] font-bold uppercase tracking-widest text-slate-400">Workspace</p><div class="mt-2 space-y-1 text-[9px] font-medium"><div class="rounded-md bg-teal-50 px-2 py-2 text-teal-800">Overview</div><div class="px-2 py-2 text-slate-500">Customers</div><div class="px-2 py-2 text-slate-500">Packages</div><div class="px-2 py-2 text-slate-500">Payments</div><div class="px-2 py-2 text-slate-500">Network</div></div><div class="mt-8 rounded-lg border border-slate-200 bg-white p-2"><span class="block text-[8px] font-semibold text-slate-500">Network status</span><span class="mt-1.5 flex items-center gap-1.5 text-[8px] font-bold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Reporting data</span></div></aside>
                                <div class="min-w-0 p-4 sm:p-5">
                                    <div class="flex items-start justify-between gap-3"><div><p class="text-[9px] font-bold uppercase tracking-[.13em] text-teal-700">Workspace overview</p><h2 class="mt-1 text-base font-bold tracking-tight text-slate-900">Good morning, Alex</h2><p class="mt-1 text-[9px] text-slate-400">A clear view of subscribers, collections and network activity.</p></div><span class="rounded-lg border border-slate-200 px-2 py-1 text-[8px] font-semibold text-slate-500">This month⌄</span></div>
                                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        @foreach([['CUSTOMERS','1,284','+8.2%'],['ONLINE NOW','936','PPPoE + hotspot'],['COLLECTED','KES 482k','This month'],['ROUTERS','12','11 reporting']] as [$label,$value,$detail])<div class="rounded-lg border border-slate-100 bg-white p-2.5 shadow-[0_2px_8px_rgba(15,23,42,.025)]"><span class="block text-[7px] font-bold tracking-wide text-slate-400">{{ $label }}</span><strong class="mt-1.5 block text-base font-bold tracking-tight text-slate-900 sm:text-lg">{{ $value }}</strong><span class="mt-1 block truncate text-[8px] {{ $loop->first ? 'text-emerald-700' : 'text-slate-400' }}">{{ $detail }}</span></div>@endforeach
                                    </div>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-[1.35fr_.85fr]">
                                        <div class="rounded-lg border border-slate-100 p-3"><div class="flex items-start justify-between"><div><p class="text-[9px] font-bold text-slate-700">Collections</p><p class="mt-0.5 text-[8px] text-slate-400">Illustrative monthly activity</p></div><span class="rounded bg-teal-50 px-1.5 py-1 text-[7px] font-bold text-teal-800">6 MONTHS</span></div><div class="mt-4 flex h-20 items-end gap-1.5" aria-label="Illustrative collections chart">@foreach([34,52,43,67,56,82,62,92,74,100,78,88] as $height)<div class="flex h-full flex-1 items-end"><span class="w-full rounded-t-sm bg-gradient-to-t from-teal-700 to-cyan-400" style="height: {{ $height }}%"></span></div>@endforeach</div><div class="mt-1.5 flex justify-between text-[7px] text-slate-400"><span>Oct</span><span>Nov</span><span>Dec</span><span>Jan</span><span>Feb</span><span>Mar</span></div></div>
                                        <div class="rounded-lg border border-slate-100 p-3"><p class="text-[9px] font-bold text-slate-700">Service snapshot</p><p class="mt-0.5 text-[8px] text-slate-400">Payments and access</p><div class="mt-3 space-y-2"><div class="flex items-center justify-between"><span class="text-[8px] text-slate-500">Invoices paid</span><span class="text-[8px] font-bold text-slate-800">Updated</span></div><div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full w-[72%] rounded-full bg-teal-500"></div></div><div class="flex items-center justify-between"><span class="text-[8px] text-slate-500">Active sessions</span><span class="text-[8px] font-bold text-slate-800">Reported</span></div><div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full w-[61%] rounded-full bg-blue-500"></div></div><div class="mt-2 rounded-md bg-slate-50 p-2 text-[8px] leading-4 text-slate-500">Payment status and live connection shown separately.</div></div></div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between gap-2 rounded-lg bg-[#0b1728] px-3 py-2.5 text-white"><span class="flex items-center gap-2 text-[8px] font-medium"><span class="grid h-6 w-6 place-items-center rounded-md bg-white/10 text-teal-200">↗</span> Subscriber operations at a glance</span><span class="text-[7px] font-semibold text-teal-200">PAYMENT · SERVICE · NETWORK</span></div>
                                    <p class="mt-2 text-right text-[7px] text-slate-400">Illustrative interface · sample figures</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -bottom-5 -left-5 hidden items-center gap-3 rounded-xl border border-white/20 bg-white p-3.5 shadow-xl sm:flex"><span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-50 text-emerald-700">✓</span><span><strong class="block text-[11px] text-slate-800">Payment verified</strong><span class="mt-0.5 block text-[9px] text-slate-400">Service state tracked separately</span></span></div>
                </div>
            </div>
        </section>

        <section class="border-b border-slate-200 bg-white px-5 py-5 sm:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-[10px] font-bold uppercase tracking-[.16em] text-slate-400">Purpose-built for ISP workflows</p><div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] font-extrabold tracking-[.1em] text-slate-500 sm:gap-x-7"><span>MIKROTIK</span><span>FREERADIUS</span><span>M-PESA</span><span>PPPoE</span><span>HOTSPOT</span><span>MULTI-TENANT</span></div></div>
        </section>

        <section id="platform" class="scroll-mt-24 px-5 py-20 sm:px-8 sm:py-24">
            <div class="mx-auto max-w-7xl">
                <div class="mx-auto max-w-2xl text-center"><p class="text-xs font-bold uppercase tracking-[.17em] text-teal-700">One connected operation</p><h2 class="mt-3 text-3xl font-semibold leading-tight tracking-tight text-[#102338] sm:text-4xl">Less chasing. More control of the customer experience.</h2><p class="mt-4 text-base leading-7 text-slate-600">Turn day-to-day ISP administration into a workflow your whole team can follow—from the first invoice to a confirmed online session.</p></div>
                <div class="mt-11 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach([
                        ['Subscriber billing', 'Packages, customer accounts, invoices and balances stay together. Track the full customer billing history in one workspace.', '◫', 'bg-blue-50 text-blue-700'],
                        ['M-Pesa collections', 'Initiate STK payments, receive provider callbacks and keep unmatched payments visible until they are allocated.', '↗', 'bg-emerald-50 text-emerald-700'],
                        ['MikroTik + FreeRADIUS', 'Manage router records and connect PPPoE and hotspot operations to RADIUS authentication and accounting.', '⌁', 'bg-cyan-50 text-cyan-800'],
                        ['Network visibility', 'Review reported PPPoE and hotspot sessions, router health, traffic and disconnection activity.', '⌘', 'bg-violet-50 text-violet-700'],
                        ['An ISP-branded workspace', 'Set your ISP identity, country and currency. Give your staff a shared place to manage service.', '◈', 'bg-amber-50 text-amber-700'],
                        ['Team and audit controls', 'Use tenant-specific roles and keep a record of key platform and workspace actions.', '◎', 'bg-rose-50 text-rose-700'],
                    ] as [$title, $description, $icon, $tone])
                        <article class="group rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_5px_24px_rgba(15,23,42,.035)] transition duration-200 hover:-translate-y-1 hover:border-teal-200 hover:shadow-[0_18px_42px_rgba(15,23,42,.09)]"><div class="flex items-center justify-between"><span class="grid h-11 w-11 place-items-center rounded-xl {{ $tone }} text-lg font-semibold">{{ $icon }}</span><span class="h-px w-12 bg-slate-200 transition group-hover:w-20 group-hover:bg-teal-300"></span></div><h3 class="mt-5 text-lg font-semibold tracking-tight text-[#102338]">{{ $title }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p></article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="billing" class="scroll-mt-24 overflow-hidden bg-white px-5 py-20 sm:px-8 sm:py-24">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-12 lg:grid-cols-[.85fr_1.15fr] lg:items-center">
                    <div><p class="text-xs font-bold uppercase tracking-[.17em] text-blue-700">Two ledgers. Clear ownership.</p><h2 class="mt-3 text-3xl font-semibold leading-tight tracking-tight text-[#102338] sm:text-4xl">Your subscriber revenue and your software bill are different things.</h2><p class="mt-5 text-base leading-7 text-slate-600">Lumenix keeps your ISP’s customer billing separate from the SaaS fees your ISP owes the platform. Your team can reconcile each on its own terms.</p><a href="#pricing" class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-teal-800 underline decoration-teal-300 underline-offset-4">See platform plans <span aria-hidden="true">→</span></a></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <article class="relative overflow-hidden rounded-2xl border border-teal-100 bg-gradient-to-br from-teal-50 via-white to-white p-6 shadow-sm"><div class="absolute -right-10 -top-10 h-36 w-36 rounded-full bg-teal-200/30 blur-2xl" aria-hidden="true"></div><div class="relative"><span class="inline-flex rounded-full bg-teal-100 px-2.5 py-1 text-[9px] font-bold uppercase tracking-[.1em] text-teal-900">Your ISP → subscriber</span><h3 class="mt-5 text-xl font-semibold tracking-tight text-[#102338]">ISP customer billing</h3><p class="mt-2 text-sm leading-6 text-slate-600">Your packages, invoices, M-Pesa collections and customer balances.</p><div class="mt-6 space-y-2 border-t border-teal-100 pt-4 text-xs"><div class="flex items-center justify-between"><span class="text-slate-500">Invoice</span><span class="font-semibold text-slate-800">Subscriber account</span></div><div class="flex items-center justify-between"><span class="text-slate-500">Payment</span><span class="font-semibold text-slate-800">Your M-Pesa account</span></div><div class="flex items-center justify-between"><span class="text-slate-500">Access</span><span class="font-semibold text-slate-800">Your network policy</span></div></div></div></article>
                        <article class="relative overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 via-white to-white p-6 shadow-sm"><div class="absolute -right-10 -top-10 h-36 w-36 rounded-full bg-blue-200/30 blur-2xl" aria-hidden="true"></div><div class="relative"><span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-[9px] font-bold uppercase tracking-[.1em] text-blue-900">Lumenix → your ISP</span><h3 class="mt-5 text-xl font-semibold tracking-tight text-[#102338]">Platform SaaS billing</h3><p class="mt-2 text-sm leading-6 text-slate-600">Your Lumenix plan, platform invoices and subscription payments.</p><div class="mt-6 space-y-2 border-t border-blue-100 pt-4 text-xs"><div class="flex items-center justify-between"><span class="text-slate-500">Invoice</span><span class="font-semibold text-slate-800">ISP subscription</span></div><div class="flex items-center justify-between"><span class="text-slate-500">Plan</span><span class="font-semibold text-slate-800">Configured platform tier</span></div><div class="flex items-center justify-between"><span class="text-slate-500">Ledger</span><span class="font-semibold text-slate-800">Separate from ISP revenue</span></div></div></div></article>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-5 py-20 sm:px-8 sm:py-24">
            <div class="mx-auto max-w-7xl overflow-hidden rounded-[1.75rem] bg-[#0b1728] text-white shadow-2xl shadow-slate-900/10">
                <div class="grid lg:grid-cols-[.8fr_1.2fr]">
                    <div class="p-7 sm:p-10 lg:p-12"><p class="text-xs font-bold uppercase tracking-[.16em] text-teal-200">Payments are not the same as access</p><h2 class="mt-3 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">Know what happened after a customer paid.</h2><p class="mt-4 text-sm leading-6 text-slate-300">A successful transaction should not be mistaken for a live connection. Lumenix tracks the billing and service states separately, so your staff can see where follow-up is needed.</p></div>
                    <div class="relative flex items-center p-6 sm:p-10 lg:p-12"><div class="absolute inset-y-0 left-0 hidden w-px bg-white/10 lg:block"></div><div class="w-full space-y-3">
                        @foreach([['01','Payment','Verified by provider','bg-emerald-300'],['02','Invoice','Paid in subscriber ledger','bg-emerald-300'],['03','Subscription','Active for the billing period','bg-cyan-300'],['04','Provisioning','Pending router confirmation','bg-amber-300'],['05','Connection','Not yet confirmed online','bg-slate-500']] as [$number,$label,$description,$dot])<div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[.045] p-3.5 sm:gap-4 sm:p-4"><span class="text-[9px] font-bold tracking-widest text-slate-500">{{ $number }}</span><span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span><span class="min-w-0 flex-1"><strong class="block text-xs font-semibold text-white">{{ $label }}</strong><span class="mt-0.5 block text-[10px] text-slate-400">{{ $description }}</span></span><span class="hidden rounded-full bg-white/[.07] px-2 py-1 text-[8px] font-semibold text-slate-300 sm:inline-flex">TRACKED</span></div>@endforeach
                    </div></div>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="scroll-mt-24 border-y border-slate-200 bg-white px-5 py-20 sm:px-8 sm:py-24">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[.17em] text-teal-700">Get your operation set up</p><h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#102338] sm:text-4xl">Start with your ISP. Add the detail as you grow.</h2><p class="mt-4 text-sm leading-7 text-slate-600">Build around the customers and network you already have. Keep the billing, access and reporting in a consistent workflow.</p></div>
                <div class="relative mt-10 grid gap-4 md:grid-cols-3">
                    <div class="absolute left-[16%] right-[16%] top-6 hidden h-px bg-gradient-to-r from-teal-200 via-slate-200 to-blue-200 md:block" aria-hidden="true"></div>
                    @foreach([['01','Create your workspace','Set your ISP name, country, currency and owner account.'],['02','Configure packages and access','Add internet plans, register a router and set up PPPoE or hotspot workflows.'],['03','Manage billing and service','Issue invoices, follow payment status and review reported sessions.']] as [$number,$title,$description])<article class="relative rounded-2xl border border-slate-200 bg-[#f8fafc] p-6"><span class="relative z-10 grid h-12 w-12 place-items-center rounded-full border-4 border-[#f8fafc] bg-[#102338] text-xs font-bold text-teal-200 shadow-sm">{{ $number }}</span><h3 class="mt-5 text-base font-semibold text-[#102338]">{{ $title }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p></article>@endforeach
                </div>
                <div class="mt-7 rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-xs leading-5 text-amber-950"><strong>ISP approval:</strong> new public registrations are reviewed before the workspace can be used. Platform-admin-created tenants are activated directly.</div>
            </div>
        </section>

        <section id="pricing" class="scroll-mt-24 px-5 py-20 sm:px-8 sm:py-24">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="text-xs font-bold uppercase tracking-[.17em] text-teal-700">Simple SaaS plans</p><h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#102338] sm:text-4xl">Find the right fit for your ISP.</h2><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Plan prices and limits below are the current platform configuration. These fees are separate from subscriber invoices and ISP collections.</p></div><a href="#contact" class="inline-flex items-center gap-2 text-sm font-bold text-teal-800 underline decoration-teal-300 underline-offset-4">Ask about a plan <span aria-hidden="true">↗</span></a></div>
                @if($plans->isNotEmpty())
                    <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach($plans as $plan)
                            @php($featured = strtolower($plan->name) === 'pro')
                            <article @class(['relative flex flex-col rounded-2xl border p-6 shadow-sm transition hover:-translate-y-1', 'border-[#102338] bg-[#102338] text-white shadow-xl shadow-slate-900/10' => $featured, 'border-slate-200 bg-white text-slate-900 hover:border-teal-200 hover:shadow-lg' => ! $featured])>
                                @if($featured)<span class="absolute -top-3 right-5 rounded-full bg-teal-300 px-3 py-1 text-[9px] font-extrabold uppercase tracking-[.12em] text-[#102338]">Popular</span>@endif
                                <h3 @class(['text-lg font-semibold', 'text-white' => $featured, 'text-[#102338]' => ! $featured])>{{ $plan->name }}</h3>
                                <p @class(['mt-2 min-h-10 text-xs leading-5', 'text-slate-300' => $featured, 'text-slate-500' => ! $featured])>{{ $plan->included_subscribers ? number_format($plan->included_subscribers).' included subscribers' : 'For your ISP operations' }}</p>
                                <p class="mt-5"><strong class="text-[1.7rem] font-semibold tracking-tight">{{ $plan->monthly_price_minor === 0 ? 'No base fee' : $plan->currency.' '.number_format($plan->monthly_price_minor / 100, 2) }}</strong>@if($plan->monthly_price_minor > 0)<span @class(['text-xs', 'text-slate-300' => $featured, 'text-slate-500' => ! $featured])> / month</span>@endif</p>
                                <p @class(['mt-2 text-[11px]', 'text-slate-300' => $featured, 'text-slate-500' => ! $featured])>Up to {{ number_format($plan->max_routers) }} {{ Illuminate\Support\Str::plural('router', $plan->max_routers) }}</p>
                                <p @class(['mt-2 min-h-8 text-[10px] leading-4', 'text-slate-300' => $featured, 'text-slate-500' => ! $featured])>@if($plan->included_subscribers > 0){{ number_format($plan->included_subscribers) }} active subscribers included; extra active subscribers are billed at the per-ISP rate shown in your subscription.@else Active subscriber overage is billed at the per-ISP rate shown in your subscription.@endif</p>
                                <ul class="mt-5 flex-1 space-y-2 border-t {{ $featured ? 'border-white/15' : 'border-slate-100' }} pt-5">
                                    @forelse($plan->features ?? [] as $feature)<li @class(['flex gap-2 text-xs leading-5', 'text-slate-200' => $featured, 'text-slate-600' => ! $featured])><span class="font-bold text-teal-400">✓</span>{{ $feature }}</li>@empty<li @class(['text-xs', 'text-slate-300' => $featured, 'text-slate-500' => ! $featured])>Core ISP billing workspace</li>@endforelse
                                </ul>
                                <a href="{{ route('register') }}" @class(['mt-6 inline-flex justify-center rounded-xl px-4 py-3 text-sm font-bold transition', 'bg-teal-300 text-[#102338] hover:bg-teal-200' => $featured, 'border border-slate-200 text-[#102338] hover:bg-slate-50' => ! $featured])>Get started <span class="ml-2" aria-hidden="true">→</span></a>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="mt-9 rounded-2xl border border-slate-200 bg-white p-8 text-center"><p class="text-sm text-slate-600">Plan details are being prepared. Register your ISP workspace to get started.</p><a class="mt-4 inline-flex rounded-xl bg-[#102338] px-5 py-3 text-sm font-semibold text-white" href="{{ route('register') }}">Create workspace</a></div>
                @endif
                <p class="mt-5 text-[11px] leading-5 text-slate-500">The monthly base price, included active-subscriber allowance and router limit come from the displayed plan. When the allowance is exceeded, the additional monthly amount is the number of active subscribers above the allowance multiplied by the per-subscriber rate configured for your ISP. Your final currency and rate are shown in the platform subscription details. Contact us for enterprise arrangements.</p>
            </div>
        </section>

        <section class="border-y border-slate-200 bg-white px-5 py-16 sm:px-8 sm:py-20">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[.17em] text-blue-700">Operational confidence</p><h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#102338] sm:text-4xl">Designed for clear boundaries and accountable operations.</h2><p class="mt-4 text-sm leading-6 text-slate-600">See what the platform does with access, payments and network state—without confusing one for another.</p></div>
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([['Tenant-scoped roles','Staff permissions and workspace data are scoped to each ISP.','◎'],['Auditable actions','Key platform and workspace changes create audit records.','⌕'],['Protected router secrets','Router connection and RADIUS credentials are encrypted in storage.','◇'],['Verified service state','Payment, invoice, provisioning and reported connection remain distinct.','↗']] as [$title,$copy,$icon])<article class="rounded-xl border border-slate-200 bg-[#f8fafc] p-5"><span class="grid h-9 w-9 place-items-center rounded-lg bg-white text-base font-semibold text-teal-800 shadow-sm">{{ $icon }}</span><h3 class="mt-4 text-sm font-semibold text-[#102338]">{{ $title }}</h3><p class="mt-2 text-xs leading-5 text-slate-600">{{ $copy }}</p></article>@endforeach
                </div>
            </div>
        </section>

        <section id="contact" class="scroll-mt-24 px-5 py-20 sm:px-8 sm:py-24">
            <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.8fr_1.2fr]">
                <div class="pt-2"><p class="text-xs font-bold uppercase tracking-[.17em] text-teal-700">Talk through your setup</p><h2 class="mt-3 text-3xl font-semibold leading-tight tracking-tight text-[#102338] sm:text-4xl">See how Lumenix could fit your ISP.</h2><p class="mt-4 text-sm leading-6 text-slate-600">Request a product walkthrough, ask about a platform plan or tell us about your router and subscriber setup. The Lumenix team will follow up using the details you provide.</p>
                    <div class="mt-7 space-y-4">
                        <div class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-teal-50 text-sm font-bold text-teal-800">01</span><p class="pt-1 text-xs leading-5 text-slate-600"><strong class="text-[#102338]">Share your context.</strong> Tell us about your ISP and what you want to improve.</p></div>
                        <div class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-blue-50 text-sm font-bold text-blue-800">02</span><p class="pt-1 text-xs leading-5 text-slate-600"><strong class="text-[#102338]">We’ll follow up.</strong> Your request goes to the Lumenix platform support queue for response.</p></div>
                    </div>
                    @if(filled($supportEmail))<p class="mt-7 text-xs text-slate-500">Prefer email? <a class="font-semibold text-teal-800 underline" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>@else<p class="mt-7 text-xs leading-5 text-slate-500">Contact details are managed by the platform team. Use this request form and the team will reply to the email you provide.</p>@endif
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_16px_48px_rgba(15,23,42,.07)] sm:p-7">
                    @if(session('contact_status'))<div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-900">{{ session('contact_status') }}</div>@endif
                    @if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><p class="font-semibold">Please check the form:</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <form method="POST" action="{{ route('marketing.contact') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                        <label class="block text-xs font-semibold text-slate-700">Your name<input class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="160" required></label>
                        <label class="block text-xs font-semibold text-slate-700">ISP / company<input class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm" name="company" value="{{ old('company') }}" autocomplete="organization" maxlength="160" required></label>
                        <label class="block text-xs font-semibold text-slate-700">Work email<input class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm" type="email" name="email" value="{{ old('email') }}" autocomplete="email" maxlength="254" required></label>
                        <label class="block text-xs font-semibold text-slate-700">Phone <span class="font-normal text-slate-400">optional</span><input class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" maxlength="40"></label>
                        <label class="block text-xs font-semibold text-slate-700 sm:col-span-2">What would you like to discuss?<select class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm" name="interest" required><option value="product_walkthrough" @selected(old('interest','product_walkthrough')==='product_walkthrough')>Product walkthrough</option><option value="pricing" @selected(old('interest')==='pricing')>Plans and pricing</option><option value="network_setup" @selected(old('interest')==='network_setup')>MikroTik / RADIUS setup</option><option value="other" @selected(old('interest')==='other')>Something else</option></select></label>
                        <label class="block text-xs font-semibold text-slate-700 sm:col-span-2">A little about your ISP and needs<textarea class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm" name="message" rows="4" maxlength="5000" required>{{ old('message') }}</textarea></label>
                        <label class="sr-only" aria-hidden="true">Leave this field empty<input tabindex="-1" autocomplete="off" name="website"></label>
                        <label class="flex items-start gap-2.5 text-[11px] leading-5 text-slate-600 sm:col-span-2"><input class="mt-0.5 rounded border-slate-300 text-teal-700 focus:ring-teal-600" type="checkbox" name="consent" value="1" @checked(old('consent')) required><span>I agree that Lumenix may use these details to respond to this request. See the <a class="font-semibold text-teal-800 underline" href="{{ route('marketing.privacy') }}">Privacy notice</a>.</span></label>
                        <div class="sm:col-span-2"><button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#102338] px-5 py-3.5 text-sm font-bold text-white transition hover:bg-[#203b63] sm:w-auto">Request a walkthrough <span aria-hidden="true">→</span></button><p class="mt-3 text-[10px] text-slate-400">This submits a request for follow-up; it does not reserve a meeting time.</p></div>
                    </form>
                </div>
            </div>
        </section>

        <section class="border-y border-slate-200 bg-white px-5 py-16 sm:px-8 sm:py-20">
            <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.75fr_1.25fr]">
                <div><p class="text-xs font-bold uppercase tracking-[.17em] text-blue-700">Common questions</p><h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#102338]">Know what you are signing up for.</h2></div>
                <div class="divide-y divide-slate-200">
                    @foreach([['Does Lumenix collect my subscribers’ payments?', 'Your ISP remains responsible for its subscriber billing and collections. Lumenix tracks that customer ledger separately from platform subscription billing. Payment-provider credentials and mappings are configured for your workspace.'],['Can payment activate internet access automatically?', 'A verified payment can settle an invoice and update subscription state. Provisioning and a confirmed network session are tracked separately, so the system does not claim a subscriber is online until network accounting reports it.'],['What network workflows does Lumenix support?', 'The workspace includes MikroTik router records, FreeRADIUS-backed PPPoE and hotspot account workflows, and views for RADIUS accounting and reported router telemetry.'],['How do I start?', 'Create an ISP workspace with your business name, country and owner account. Public registrations are reviewed before workspace access is enabled.'] ] as [$question,$answer])<details class="group py-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-5 text-sm font-semibold text-[#102338]"><span>{{ $question }}</span><span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-45 group-open:bg-teal-50 group-open:text-teal-800">+</span></summary><p class="mt-3 max-w-3xl pr-10 text-sm leading-6 text-slate-600">{{ $answer }}</p></details>@endforeach
                </div>
            </div>
        </section>

        <section class="px-5 py-16 sm:px-8 sm:py-20">
            <div class="relative mx-auto max-w-7xl overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#0b1728] via-[#12304a] to-[#14515a] p-8 text-white shadow-2xl shadow-slate-900/10 sm:p-11 lg:p-14">
                <div class="absolute -right-20 -top-36 h-96 w-96 rounded-full bg-teal-300/10 blur-3xl" aria-hidden="true"></div>
                <div class="relative flex flex-col items-start justify-between gap-7 lg:flex-row lg:items-center"><div><p class="text-xs font-bold uppercase tracking-[.17em] text-teal-200">Bring your operation into focus</p><h2 class="mt-3 max-w-3xl text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">Spend less time piecing the business together.</h2><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Create a branded ISP workspace for billing, payments, subscriber access and network visibility.</p></div><a href="{{ route('register') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-teal-300 px-5 py-3.5 text-sm font-bold text-[#0b1728] transition hover:bg-teal-200">Start your ISP workspace <span aria-hidden="true">→</span></a></div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white px-5 py-8 sm:px-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5"><span class="grid h-8 w-8 place-items-center rounded-lg bg-[#0b1728] text-sm font-black text-teal-200">L</span><span class="text-sm font-bold tracking-tight text-[#102338]">Lumenix</span></a>
            <p class="text-xs text-slate-500">Billing and network operations for internet providers.</p>
            <div class="flex flex-wrap gap-5 text-xs font-medium text-slate-600"><a class="hover:text-teal-700" href="#platform">Platform</a><a class="hover:text-teal-700" href="#pricing">Pricing</a><a class="hover:text-teal-700" href="#contact">Contact</a><a class="hover:text-teal-700" href="{{ route('marketing.privacy') }}">Privacy</a><a class="hover:text-teal-700" href="{{ route('marketing.terms') }}">Terms</a><a class="hover:text-teal-700" href="{{ route('login') }}">Sign in</a></div>
        </div>
        <p class="mx-auto mt-6 max-w-7xl text-[10px] text-slate-400">© {{ now()->year }} Lumenix. All rights reserved.</p>
    </footer>
</body>
</html>
