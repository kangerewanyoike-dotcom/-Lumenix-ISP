@props(['tenant'])
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $tenant->brand_name ?: $tenant->name }} · Customer portal</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-50 text-slate-900"><main class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-10">
<header class="mb-8 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-white p-5 shadow-sm"><a class="text-xl font-bold" style="color: {{ $tenant->brand_primary_color }}" href="{{ route('portal.dashboard') }}">{{ $tenant->brand_name ?: $tenant->name }}</a><nav class="flex items-center gap-4"><a class="text-sm underline" href="{{ route('portal.dashboard') }}">Overview</a><form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="text-sm underline">Sign out</button></form></nav></header>
@if(session('status'))<p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="mb-4 rounded border border-red-300 bg-red-50 p-3"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
{{ $slot }}
@if($tenant->powered_by_enabled)<footer class="mt-10 text-center text-xs text-slate-500">Powered by Lumenix</footer>@endif
</main></body></html>
