@props([
    'name',
    'label',
    'type' => 'text',
    'value' => '',
    'autocomplete' => null,
    'required' => true,
])

<div class="mb-4">
    <label for="{{ $name }}" class="mb-1 block text-sm text-muted">{{ $label }}</label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-line bg-bg px-3 py-2 text-ink outline-none focus:border-primary']) }}
    >
    @error($name)
        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>
