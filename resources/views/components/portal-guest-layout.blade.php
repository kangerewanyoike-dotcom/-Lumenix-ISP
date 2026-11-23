<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Subscriber sign in · Lumenix</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="relative flex min-h-screen flex-col px-4 py-6 sm:px-6 sm:py-8">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-indigo-100/70 to-transparent"></div>
        <header class="relative mx-auto flex w-full max-w-6xl items-center justify-between">
            <a href="{{ route('portal.login') }}" class="flex items-center gap-3 rounded focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2" aria-label="Lumenix subscriber portal home">
                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-indigo-700 text-lg font-bold text-white shadow-sm" style="flex:0 0 40px;width:40px;height:40px;border-radius:12px;background:#4338ca;color:#fff" aria-hidden="true">L</div>
                <span class="text-lg font-semibold tracking-tight text-slate-900">Lumenix</span>
            </a>
            <span class="text-sm text-slate-600">Subscriber portal</span>
        </header>

        <div class="relative flex flex-1 items-center justify-center py-12">
            <div class="w-full max-w-md">
                @if(session('status'))<p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{{ session('status') }}</p>@endif
                @if($errors->any())<div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                {{ $slot }}
            </div>
        </div>

        <footer class="relative mx-auto w-full max-w-6xl text-center text-xs text-slate-500">Secure access to your internet account</footer>
    </main>
</body>
</html>
