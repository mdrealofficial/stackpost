@php
    $embedOptions = app(\Modules\AdminSettings\Support\OptionStore::class);
    $embedEnabled = (string) $embedOptions->get('embed_code_status', '0') === '1';
    $embedCode = trim((string) $embedOptions->get('embed_code_app_body_end', ''));
@endphp

@if ($embedEnabled && $embedCode !== '')
    {!! $embedCode !!}
@endif
