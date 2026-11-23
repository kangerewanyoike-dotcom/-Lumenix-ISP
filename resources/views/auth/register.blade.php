<x-layouts.app>
    <section class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow-sm sm:p-9">
        <div class="mb-8">
            <p class="mb-2 text-xs font-bold uppercase tracking-[.16em] text-indigo-700">Get started</p>
            <h2 class="text-3xl font-semibold">Create your ISP workspace</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Set up your business profile and owner account. Your workspace will be branded with your ISP’s name.</p>
        </div>

        <form method="POST" action="{{ route('register.store') }}" class="space-y-7">
            @csrf
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Business profile</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium sm:col-span-2">ISP or business name
                        <input class="mt-1.5 w-full rounded border p-3" name="tenant_name" value="{{ old('tenant_name') }}" maxlength="160" autocomplete="organization" placeholder="e.g. Northstar Fibre" required autofocus>
                    </label>
                    <label class="block text-sm font-medium">Country
                        <select class="mt-1.5 w-full rounded border p-3" name="country_code" required>
                            @foreach($countries as $code => $defaults)
                                <option value="{{ $code }}" @selected(old('country_code', 'KE') === $code)>{{ ['KE' => 'Kenya', 'UG' => 'Uganda', 'TZ' => 'Tanzania', 'RW' => 'Rwanda', 'NG' => 'Nigeria', 'GH' => 'Ghana', 'ZA' => 'South Africa', 'GB' => 'United Kingdom', 'US' => 'United States', 'CA' => 'Canada', 'AU' => 'Australia', 'IN' => 'India', 'AE' => 'United Arab Emirates', 'DE' => 'Germany', 'FR' => 'France'][$code] }} · {{ $defaults['currency'] }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs font-normal text-slate-500">Sets your workspace currency and local timezone.</span>
                    </label>
                    <label class="block text-sm font-medium">Brand colour
                        <span class="mt-1.5 flex items-center gap-3 rounded-xl border border-slate-200 p-2.5"><input class="h-10 w-14 cursor-pointer rounded border-0 p-0" type="color" name="brand_primary_color" value="{{ old('brand_primary_color', '#4338CA') }}" required><span class="text-xs font-normal text-slate-500">Used on your customer portal</span></span>
                    </label>
                </div>
            </div>

            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Workspace owner</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium">Your name
                        <input class="mt-1.5 w-full rounded border p-3" name="owner_name" value="{{ old('owner_name') }}" maxlength="160" autocomplete="name" required>
                    </label>
                    <label class="block text-sm font-medium">Work email
                        <input class="mt-1.5 w-full rounded border p-3" type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>
                    </label>
                    <label class="block text-sm font-medium">Password
                        <input class="mt-1.5 w-full rounded border p-3" type="password" name="password" autocomplete="new-password" minlength="12" required>
                        <span class="mt-1 block text-xs font-normal text-slate-500">Use at least 12 characters.</span>
                    </label>
                    <label class="block text-sm font-medium">Confirm password
                        <input class="mt-1.5 w-full rounded border p-3" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required>
                    </label>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-4 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">Already have a workspace? <a class="font-semibold text-indigo-700 underline" href="{{ route('login') }}">Sign in</a></p>
                <button class="rounded-xl bg-indigo-700 px-5 py-3 text-sm font-semibold text-white">Create workspace</button>
            </div>
        </form>
    </section>
</x-layouts.app>
