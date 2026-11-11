<style>
    .lm-error-page { --error-ink:#101010; --error-muted:#6f7178; --error-line:#e8e8eb; --error-paper:#fff; min-height:100vh; display:grid; place-items:center; padding:32px; overflow:hidden; background:radial-gradient(circle at 50% 20%,rgba(100,132,255,.11),transparent 28%),#fff; color:var(--error-ink); font-family:Inter,ui-sans-serif,system-ui,sans-serif; }
    .lm-error-card { width:min(100%,760px); padding:clamp(34px,7vw,72px); border:1px solid var(--error-line); border-radius:22px; background:rgba(255,255,255,.9); text-align:center; box-shadow:0 36px 90px -64px rgba(0,0,0,.5); animation:lmErrorIn .8s cubic-bezier(.2,.72,.2,1) both; }
    .lm-error-code { display:inline-flex; align-items:center; gap:12px; color:var(--error-muted); font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
    .lm-error-code::before,.lm-error-code::after { content:""; width:38px; height:1px; background:var(--error-line); }
    .lm-error-card h1 { margin:26px 0 0; font-size:clamp(48px,9vw,82px); font-weight:830; line-height:.98; letter-spacing:-.065em; }
    .lm-error-card p { max-width:540px; margin:22px auto 0; color:var(--error-muted); font-size:15px; line-height:1.75; }
    .lm-error-button { display:inline-flex; align-items:center; justify-content:center; gap:9px; min-height:48px; margin-top:30px; padding:0 21px; border:1px solid #111; border-radius:13px; background:#111; color:#fff!important; font-size:13px; font-weight:780; text-decoration:none; transition:transform .2s ease,background .2s ease; }
    .lm-error-button:hover { transform:translateY(-2px); background:#292929; }
    @keyframes lmErrorIn { from{opacity:0;transform:translateY(22px) scale(.985)} to{opacity:1;transform:none} }
    @media(prefers-reduced-motion:reduce){.lm-error-card{animation:none}}
</style>
<section class="lm-error-page">
    <div class="lm-error-card">
        <span class="lm-error-code">{{ __('Error 404') }}</span>
        <h1>{{ __('This page is out of the workflow.') }}</h1>
        <p>{{ __('The address may have changed or the page is no longer available. Return to the homepage to continue exploring the workspace.') }}</p>
        <a href="{{ route('home') }}" class="lm-error-button"><i class="fa-light fa-arrow-left"></i>{{ __('Back to homepage') }}</a>
    </div>
</section>
