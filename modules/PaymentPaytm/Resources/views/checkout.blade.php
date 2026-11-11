<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Redirecting to Paytm...') }}</title>
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; background: #f7f8fc; color: #0f172a; }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: min(560px, 100%); border: 1px solid #d9e2f1; border-radius: 16px; background: #fff; padding: 28px; box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08); }
        .title { margin: 0 0 8px; font-size: 22px; }
        .muted { color: #64748b; line-height: 1.6; }
        .btn { display: inline-flex; align-items: center; justify-content: center; margin-top: 18px; padding: 12px 18px; border-radius: 10px; background: #0f69ff; color: #fff; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1 class="title">{{ __('Redirecting to Paytm...') }}</h1>
            <p class="muted">{{ __('We are preparing your Paytm transaction token.') }}</p>
            <p class="muted" style="margin-top: 0;">{{ $checkout->gateway ?? 'Paytm' }}</p>
            <a class="btn" href="{{ $checkoutUrl }}">{{ __('Continue to Paytm') }}</a>
        </div>
    </div>
    <script>window.location.replace(@json($checkoutUrl));</script>
</body>
</html>
