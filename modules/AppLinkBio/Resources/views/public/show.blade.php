<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }}</title>
    <meta name="description" content="{{ $page->headline ?: $page->description }}">
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/brands.css') }}">
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/solid.css') }}">
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/regular.css') }}">
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/light.css') }}">
    <style>
        body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:{{ data_get($theme, 'theme.page_bg', '#0f172a') }};min-height:100vh;padding:32px 18px}
    </style>
</head>
<body>
    @php
        $linkBioAccess = app(\Modules\AppLinkBio\Support\LinkBioAccess::class);
        $brandingText = $linkBioAccess->canCustomizeBranding($page->owner)
            ? $page->brandingText()
            : $linkBioAccess->defaultBrandingText();
    @endphp

    @include('applinkbio::partials.renderer', [
        'preview' => false,
        'theme' => $theme,
        'title' => $page->title,
        'headline' => $page->headline,
        'description' => $page->description,
        'brandingText' => $brandingText,
        'accentColor' => $page->accent_color ?: '#2563eb',
        'avatarUrl' => $page->avatar_url,
        'coverUrl' => $page->cover_url,
        'backgroundUrl' => $page->backgroundImage(),
        'backgroundOverlay' => $page->backgroundOverlay(),
        'backgroundPosition' => $page->backgroundPosition(),
        'backgroundFit' => $page->backgroundFit(),
        'avatarStyle' => $page->avatarStyle(),
        'buttonStyle' => $page->buttonStyle(),
        'contentAlign' => $page->contentAlign(),
        'blocks' => $page->blocks ?? [],
        'templateKey' => $page->template_key,
        'pageSlug' => $page->slug,
    ])
</body>
</html>
