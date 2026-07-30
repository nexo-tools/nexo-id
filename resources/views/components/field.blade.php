@props([
    'name',
    'label',
    'type' => 'text',
    'value' => '',
    'autocomplete' => null,
    'required' => true,
])

@php $errorId = $name.'-error'; @endphp

<div class="mb-4">
    <label for="{{ $name }}" class="mb-1 block text-sm text-muted">{{ $label }}</label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $errorId }}" @enderror
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-control bg-bg px-3 py-2 text-ink focus:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring']) }}
    >
    @error($name)
        <p id="{{ $errorId }}" class="mt-1 text-sm text-danger">{{ $message }}</p>
    @enderror
</div>
