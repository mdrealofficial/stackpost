<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Loading PayTR Checkout...') }}</title>
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; background: #f7f8fc; color: #0f172a; }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: min(860px, 100%); border: 1px solid #d9e2f1; border-radius: 16px; background: #fff; padding: 28px; box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08); }
        .title { margin: 0 0 8px; font-size: 22px; }
        .muted { color: #64748b; line-height: 1.6; }
        .frame { margin-top: 20px; min-height: 720px; border: 1px solid #d9e2f1; border-radius: 14px; overflow: hidden; background: #fff; }
        .frame iframe { width: 100%; border: 0; min-height: 720px; display: block; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1 class="title">{{ __('Loading PayTR Checkout...') }}</h1>
            <p class="muted">{{ __('We are preparing your PayTR session. If the iframe does not appear automatically, it will render below.') }}</p>

            @if (filled($checkoutUrl))
                <div class="frame">
                    <iframe src="{{ $checkoutUrl }}" id="paytriframe" frameborder="0" scrolling="no" title="{{ __('PayTR checkout') }}"></iframe>
                </div>
                <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
                <script>window.addEventListener('load', function () { if (window.iFrameResize) { iFrameResize({}, '#paytriframe'); } });</script>
            @else
                <p class="muted">{{ __('Payment form is not available right now.') }}</p>
            @endif
        </div>
    </div>
</body>
</html>
