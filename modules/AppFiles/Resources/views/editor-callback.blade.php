<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Image updated') }}</title>
</head>
<body style="margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;font-family:Inter,system-ui,sans-serif;background:#f8fafc;color:#0f172a;">
    @php
        $returnTarget = session('appfiles.image_editor_return_url');
        $safeReturnTarget = is_string($returnTarget) && str_starts_with($returnTarget, '/') ? $returnTarget : route('portal.files.index');
    @endphp
    <div style="text-align:center;padding:24px;">
        <div style="font-size:18px;font-weight:600;">{{ __('Image updated') }}</div>
        <div style="margin-top:8px;color:#64748b;">{{ __('Closing editor...') }}</div>
    </div>
    <script>
        (() => {
            const payload = {
                type: 'appfiles:image-editor-saved',
                updatedAt: new URLSearchParams(window.location.search).get('editor_updated') || String(Date.now()),
                returnUrl: @js($safeReturnTarget),
            };

            try {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage(payload, window.location.origin);
                }

                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage(payload, window.location.origin);
                }
            } catch (error) {
                console.error('[AppFilesEditorCallback] postMessage failed', error);
            }

            window.setTimeout(() => {
                try {
                    window.close();
                } catch (error) {
                    console.error('[AppFilesEditorCallback] close failed', error);
                }

                if (!window.closed) {
                    window.location.replace(`${payload.returnUrl}${payload.returnUrl.includes('?') ? '&' : '?'}editor_updated=${payload.updatedAt}`);
                }
            }, 300);
        })();
    </script>
</body>
</html>
