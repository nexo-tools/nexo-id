@props([
    'name',
    'label',
    'type' => 'text',
    'value' => '',
    'autocomplete' => null,
    'required' => true,
])

<div class="mb-4">
    <label for="{{ $name }}" class="mb-1 block text-sm text-neutral-300">{{ $label }}</label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-white/10 bg-neutral-950 px-3 py-2 text-neutral-100 outline-none focus:border-emerald-500']) }}
    >
    @error($name)
        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
    @enderror
</div>
