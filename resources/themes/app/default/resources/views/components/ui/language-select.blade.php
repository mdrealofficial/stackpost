@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'placeholder' => null,
    'preferred' => ['vi', 'en'],
    'value' => null,
    'autoLabel' => null,
    'autoValue' => 'auto',
])

@php
    $preferredCodes = collect($preferred)->map(fn ($code) => strtolower((string) $code))->filter()->values();
    $languages = collect(world_languages());
    $preferredLanguages = $languages
        ->filter(fn ($language) => $preferredCodes->contains(strtolower((string) data_get($language, 'code'))))
        ->sortBy(fn ($language) => $preferredCodes->search(strtolower((string) data_get($language, 'code'))))
        ->values();
    $otherLanguages = $languages
        ->reject(fn ($language) => $preferredCodes->contains(strtolower((string) data_get($language, 'code'))))
        ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
        ->values();
@endphp

<x-ui.select :label="$label" :help="$help" :error="$error" {{ $attributes }}>
    @if ($autoLabel)
        <option value="{{ $autoValue }}" @selected((string) $value === (string) $autoValue)>{{ $autoLabel }}</option>
    @else
        <option value="" @selected(blank($value))>{{ $placeholder ?: __('Select language') }}</option>
    @endif

    @if ($preferredLanguages->isNotEmpty())
        <optgroup label="{{ __('Popular') }}">
            @foreach ($preferredLanguages as $language)
                <option value="{{ data_get($language, 'code') }}" @selected((string) $value === (string) data_get($language, 'code'))>
                    {{ data_get($language, 'name') }} @if (data_get($language, 'native_name') && data_get($language, 'native_name') !== data_get($language, 'name')) ({{ data_get($language, 'native_name') }}) @endif
                </option>
            @endforeach
        </optgroup>
    @endif

    <optgroup label="{{ __('All languages') }}">
        @foreach ($otherLanguages as $language)
            <option value="{{ data_get($language, 'code') }}" @selected((string) $value === (string) data_get($language, 'code'))>
                {{ data_get($language, 'name') }} @if (data_get($language, 'native_name') && data_get($language, 'native_name') !== data_get($language, 'name')) ({{ data_get($language, 'native_name') }}) @endif
            </option>
        @endforeach
    </optgroup>
</x-ui.select>
