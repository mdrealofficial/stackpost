@php
    $relativeCssPath = 'build/frontend/luma/'.$stylesheet.'.css';
    $cssPath = public_path($relativeCssPath);
    $baseUrl = rtrim((string) request()->getBaseUrl(), '/');
    $documentRoot = request()->server('DOCUMENT_ROOT');
    $usesPublicRoot = is_string($documentRoot) && realpath($documentRoot) === realpath(public_path());
    $cssUrl = $baseUrl.($usesPublicRoot ? '/' : '/public/').$relativeCssPath;
@endphp
<link rel="stylesheet" href="{{ $cssUrl }}?v={{ is_file($cssPath) ? filemtime($cssPath) : '1' }}">
