<x-layouts.app>
<h1 class="mb-2 text-2xl font-semibold">Customer portal branding</h1><p class="mb-6 max-w-2xl text-sm text-slate-600">These settings appear in the subscriber portal and invoice view. “Powered by Lumenix” is shown by default.</p>
<form method="POST" action="{{ route('admin.branding.update') }}" class="max-w-xl space-y-4 rounded-xl bg-white p-6 shadow-sm">@csrf @method('PUT')
<label class="block">ISP display name<input class="mt-1 w-full rounded border p-2" name="brand_name" maxlength="160" value="{{ old('brand_name', $tenant->brand_name) }}" placeholder="{{ $tenant->name }}"><span class="text-xs text-slate-500">Leave empty to use {{ $tenant->name }}.</span></label>
<label class="block">Primary color<input class="mt-1 h-11 w-full rounded border p-1" type="color" name="brand_primary_color" value="{{ old('brand_primary_color', $tenant->brand_primary_color) }}" required></label>
<label class="flex items-center gap-2"><input type="checkbox" name="powered_by_enabled" value="1" @checked(old('powered_by_enabled', $tenant->powered_by_enabled))> Show “Powered by Lumenix”</label>
<button class="rounded bg-indigo-700 px-4 py-2 text-white">Save branding</button></form>
</x-layouts.app>
