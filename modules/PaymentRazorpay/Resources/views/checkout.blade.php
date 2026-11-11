<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Redirecting to Razorpay') }}</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body style="font-family: Inter, Arial, sans-serif; background: #f8fafc; color: #0f172a;">
    <div style="max-width: 36rem; margin: 8rem auto; padding: 2rem; text-align: center;">
        <p style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: #64748b;">{{ __('Razorpay Checkout') }}</p>
        <h1 style="margin-top: 1rem; font-size: 2rem; line-height: 1.1;">{{ __('Redirecting to secure payment') }}</h1>
        <p style="margin-top: 1rem; font-size: 1rem; line-height: 1.8; color: #64748b;">
            {{ __('If the checkout does not open automatically, use the button below.') }}
        </p>
        <button id="rzp-launch" style="margin-top: 1.5rem; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 0.75rem; background: #0765ff; color: white; font-weight: 600; padding: 0.9rem 1.5rem; cursor: pointer;">
            {{ __('Open Razorpay') }}
        </button>
    </div>

    <script>
        const options = @json($checkoutOptions);
        options.modal = {
            ondismiss: function () {
                window.location.href = @json($cancelUrl);
            }
        };

        const checkout = new Razorpay(options);
        const launchButton = document.getElementById('rzp-launch');

        launchButton.addEventListener('click', function (event) {
            event.preventDefault();
            checkout.open();
        });

        window.addEventListener('load', function () {
            launchButton.click();
        });
    </script>
</body>
</html>
