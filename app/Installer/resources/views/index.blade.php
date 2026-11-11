<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application Installer</title>
    <style>
        :root {
            --bg: #f3f4f6;
            --bg-soft: #fafafa;
            --panel: rgba(255, 255, 255, 0.96);
            --panel-soft: #f8f8f8;
            --border: rgba(17, 24, 39, 0.1);
            --text: #111827;
            --muted: #6b7280;
            --accent: #111111;
            --accent-2: #3f3f46;
            --danger: #dc2626;
            --warning: #f59e0b;
            --shadow: 0 18px 40px -30px rgba(15, 23, 42, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
        }
        .shell { width: min(1120px, calc(100% - 32px)); margin: 32px auto 48px; }
        .hero, .panel {
            border: 1px solid var(--border);
            background: var(--panel);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }
        .hero {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 36px 34px;
            margin-bottom: 24px;
            background: var(--panel);
        }
        .hero::after {
            content: '';
            position: absolute;
            inset: auto -40px -55px auto;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(17, 24, 39, 0.04), transparent 68%);
            pointer-events: none;
        }
        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(17, 24, 39, 0.08);
            background: #ffffff;
            color: #374151;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        .hero-kicker::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #111111;
        }
        .hero h1 { margin: 0 0 12px; font-size: clamp(2.2rem, 4.6vw, 3.6rem); line-height: 0.98; max-width: 700px; }
        .hero p { margin: 0; max-width: 720px; color: var(--muted); font-size: 1.02rem; line-height: 1.85; }
        .stepper {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .step-card {
            border: 1px solid var(--border);
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.88);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .step-card.active {
            border-color: rgba(17, 24, 39, 0.18);
            box-shadow: inset 0 0 0 1px rgba(17, 24, 39, 0.05);
        }
        .step-card.locked { opacity: 0.68; }
        .step-card.done {
            border-color: rgba(17, 24, 39, 0.12);
            background: #ffffff;
        }
        .step-number {
            width: 40px;
            height: 40px;
            min-width: 40px;
            min-height: 40px;
            flex: 0 0 40px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            color: #111827;
            font-weight: 800;
        }
        .step-card.locked .step-number {
            background: rgba(148, 163, 184, 0.14);
            color: #64748b;
        }
        .step-card.done .step-number {
            background: rgba(15, 118, 110, 0.12);
            color: #0f766e;
        }
        .step-copy strong {
            display: block;
            font-size: 0.98rem;
            margin-bottom: 4px;
        }
        .step-copy span {
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.5;
        }
        .panel { border-radius: 24px; padding: 24px; }
        .panel {
            position: relative;
            overflow: hidden;
            background: #ffffff;
        }
        .panel::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: linear-gradient(90deg, #111111 0%, #52525b 100%);
            opacity: 0.88;
        }
        .panel h2 { margin: 0 0 8px; font-size: 1.12rem; letter-spacing: 0.02em; }
        .panel-subtitle {
            margin: 0 0 18px;
            color: var(--muted);
            line-height: 1.6;
        }
        .panel-header {
            display: grid;
            gap: 8px;
            padding-bottom: 18px;
            margin-bottom: 18px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
        }
        .stack { display: grid; gap: 16px; }
        .requirement {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            align-items: center;
            border: 1px solid var(--border);
            background: #ffffff;
            border-radius: 18px;
            padding: 18px 18px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }
        .requirement-main {
            display: grid;
            gap: 8px;
        }
        .requirement strong {
            display: block;
            margin-bottom: 0;
            font-size: 1rem;
            letter-spacing: -0.01em;
        }
        .requirement-meta {
            display: grid;
            gap: 4px;
        }
        .requirement small {
            display: block;
            color: var(--muted);
            line-height: 1.55;
            font-size: 0.9rem;
        }
        .badge {
            display: inline-flex; align-items: center; justify-content: center; min-width: 92px;
            height: 34px; padding: 0 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 700;
        }
        .badge.ok {
            color: #166534;
            background: linear-gradient(180deg, rgba(220, 252, 231, 0.96) 0%, rgba(240, 253, 244, 0.98) 100%);
            border: 1px solid rgba(34, 197, 94, 0.24);
        }
        .badge.fail {
            color: #b91c1c;
            background: linear-gradient(180deg, rgba(254, 226, 226, 0.96) 0%, rgba(254, 242, 242, 0.98) 100%);
            border: 1px solid rgba(248, 113, 113, 0.24);
        }
        .summary {
            border: 1px solid rgba(245, 158, 11, 0.26);
            background: rgba(254, 243, 199, 0.72);
            color: #92400e;
            border-radius: 16px;
            padding: 14px 16px;
            line-height: 1.6;
        }
        .summary.success {
            border-color: rgba(34, 197, 94, 0.26);
            background: rgba(220, 252, 231, 0.82);
            color: #166534;
        }
        form { display: grid; gap: 20px; }
        .section { border: 1px solid var(--border); background: var(--panel-soft); border-radius: 18px; padding: 18px; }
        .section h3 { margin: 0 0 14px; font-size: 0.98rem; }
        .fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .field.full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 8px; font-size: 0.86rem; font-weight: 600; color: #213247; }
        input, textarea, select {
            width: 100%; border: 1px solid rgba(148, 163, 184, 0.26); background: #fff;
            color: var(--text); border-radius: 14px; padding: 13px 14px; font: inherit;
        }
        .input-error {
            border-color: rgba(220, 38, 38, 0.6) !important;
            background: #fff5f5 !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08);
        }
        textarea { min-height: 108px; resize: vertical; }
        input::placeholder, textarea::placeholder { color: #8b9ab0; }
        input:focus, textarea:focus, select:focus { outline: 2px solid rgba(37, 99, 235, 0.12); border-color: rgba(37, 99, 235, 0.45); }
        .hint { margin-top: 8px; color: var(--muted); font-size: 0.82rem; line-height: 1.5; }
        .alert {
            border-radius: 16px; padding: 14px 16px; border: 1px solid rgba(248, 113, 113, 0.28);
            background: rgba(254, 226, 226, 0.86); color: #b91c1c;
        }
        .alert.success { border-color: rgba(34, 197, 94, 0.28); background: rgba(220, 252, 231, 0.86); color: #166534; }
        .errors { margin: 0; padding-left: 18px; display: grid; gap: 6px; }
        .field-error {
            margin-top: 10px;
            color: #b91c1c;
            font-size: 0.82rem;
            font-weight: 600;
            border: 1px solid rgba(220, 38, 38, 0.18);
            background: rgba(254, 226, 226, 0.88);
            border-radius: 12px;
            padding: 10px 12px;
            line-height: 1.45;
        }
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .actions-note {
            width: 100%;
        }
        .button, .button-secondary {
            display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
            border-radius: 999px; padding: 14px 22px; min-width: 220px; font-weight: 700; font-size: 0.95rem;
        }
        .button {
            border: 0; cursor: pointer; color: #fff;
            background: #111111;
            box-shadow: 0 16px 32px -24px rgba(15, 23, 42, 0.24);
        }
        .button-secondary {
            border: 1px solid rgba(17, 24, 39, 0.12);
            color: #111827;
            background: rgba(255, 255, 255, 0.96);
        }
        .finish-card {
            display: grid;
            gap: 20px;
            padding: 40px 32px 36px;
            text-align: center;
            background: #ffffff;
            box-shadow: 0 24px 60px -42px rgba(15, 23, 42, 0.12);
        }
        .finish-icon {
            width: 82px;
            height: 82px;
            margin: 0 auto;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 800;
            color: #111827;
            background: #f3f4f6;
            border: 1px solid rgba(17, 24, 39, 0.1);
            box-shadow: 0 18px 36px -24px rgba(15, 23, 42, 0.12);
        }
        .finish-eyebrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 0 auto;
            padding: 8px 12px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid rgba(17, 24, 39, 0.08);
            color: #374151;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .finish-eyebrow::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #111111;
        }
        .finish-title {
            margin: 0;
            font-size: 2rem;
            letter-spacing: -0.02em;
        }
        .finish-copy {
            margin: 0 auto;
            max-width: 680px;
            color: var(--muted);
            line-height: 1.9;
            font-size: 1rem;
        }
        .finish-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .finish-actions .button,
        .finish-actions .button-secondary {
            min-width: 206px;
            padding: 15px 24px;
        }
        .finish-actions .button-secondary {
            background: rgba(255, 255, 255, 0.96);
        }
        .status-line { color: var(--muted); font-size: 0.84rem; line-height: 1.6; max-width: 620px; margin: 0 0 14px; }
        @media (max-width: 960px) {
            .stepper { grid-template-columns: 1fr; }
        }
        @media (max-width: 720px) {
            .shell { width: min(100% - 20px, 1120px); margin-top: 16px; }
            .hero, .panel { border-radius: 22px; }
            .hero, .panel, .section { padding: 18px; }
            .fields { grid-template-columns: 1fr; }
            .requirement { flex-direction: column; }
            .requirement { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @php($errorBag = $validationErrors ?? new \Illuminate\Support\MessageBag())
    @php($formData = $formData ?? [])
    <div class="shell">
        <section class="hero">
            <div class="hero-kicker">Setup Wizard</div>
            <h1>Application Installer</h1>
            <p>Complete the initial setup in two steps: first validate the environment, then configure website details, database access, and the first administrator account.</p>
        </section>

        <div class="stepper">
            <div class="step-card {{ $currentStep === 'requirements' ? 'active' : '' }} {{ in_array($currentStep, ['setup', 'finish'], true) ? 'done' : '' }}">
                <span class="step-number">1</span>
                <div class="step-copy">
                    <strong>Requirements</strong>
                    <span>Check PHP, server software, extensions, writable paths, and purchase verification connectivity.</span>
                </div>
            </div>
            <div class="step-card {{ $currentStep === 'setup' ? 'active' : '' }} {{ $canProceed ? '' : 'locked' }} {{ $currentStep === 'finish' ? 'done' : '' }}">
                <span class="step-number">2</span>
                <div class="step-copy">
                    <strong>Setup</strong>
                    <span>{{ $canProceed ? 'Environment is ready. You can continue with installation details.' : 'Locked until every requirement passes.' }}</span>
                </div>
            </div>
            <div class="step-card {{ $currentStep === 'finish' ? 'active' : '' }} {{ $currentStep === 'finish' ? 'done' : 'locked' }}">
                <span class="step-number">3</span>
                <div class="step-copy">
                    <strong>Finish</strong>
                    <span>{{ $currentStep === 'finish' ? 'Installation completed. You can continue to the application home page.' : 'Displayed after installation succeeds.' }}</span>
                </div>
            </div>
        </div>

        @if ($currentStep === 'requirements')
            <section class="panel">
                <div class="panel-header">
                    <h2>Step 1: Environment Checks</h2>
                    <p class="panel-subtitle">Review every required item below. The next step only becomes available after all checks pass.</p>
                </div>

                <div class="stack">
                    @foreach ($requirements as $check)
                        <div class="requirement">
                            <div class="requirement-main">
                                <strong>{{ $check['label'] }}</strong>
                                <div class="requirement-meta">
                                    <small>Current: {{ $check['current'] }}</small>
                                    <small>Expected: {{ $check['expected'] }}</small>
                                </div>
                            </div>
                            <span class="badge {{ $check['ok'] ? 'ok' : 'fail' }}">{{ $check['ok'] ? 'Ready' : 'Fix required' }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="actions" style="margin-top: 20px;">
                    @if ($canProceed)
                        <div class="summary success">All requirements passed. You can continue to the setup step.</div>
                        <a class="button" href="{{ route('installer.index', ['step' => 'setup']) }}">Next Step</a>
                    @else
                        <div class="summary">One or more requirements are failing. Fix them first, then refresh this page. The next step stays hidden until all checks pass.</div>
                    @endif
                </div>
            </section>
        @endif

        @if ($currentStep === 'setup')
            <section class="panel">
                <div class="panel-header">
                    <h2>Step 2: Setup Details</h2>
                    <p class="panel-subtitle">Provide licensing, website, database, and administrator information to complete installation.</p>
                </div>

                @if (session('status'))
                    <div class="alert success" style="margin-bottom: 16px;">{{ session('status') }}</div>
                @endif

                @if ($errorBag->has('installer'))
                    <div class="alert" style="margin-bottom: 16px;">{{ $errorBag->first('installer') }}</div>
                @endif

                <form method="POST" action="{{ route('installer.store', ['step' => 'setup']) }}">
                    @csrf

                    @if ($purchaseRequired)
                        <div class="section">
                            <h3>License Verification</h3>
                            <div class="fields">
                                <div class="field full">
                                    <label for="purchase_code">Purchase code *</label>
                                    <input id="purchase_code" name="purchase_code" type="text" value="{{ $formData['purchase_code'] ?? '' }}" placeholder="Enter the purchase code used for this product" class="{{ $errorBag->has('purchase_code') ? 'input-error' : '' }}">
                                    <div class="hint">The installer verifies the purchase code against the configured licensing service before finishing setup.</div>
                                    @if ($errorBag->has('purchase_code')) <div class="field-error">{{ $errorBag->first('purchase_code') }}</div> @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="section">
                        <h3>Website Settings</h3>
                        <div class="fields">
                            <div class="field full">
                                <label for="website_title">Website title *</label>
                                <input id="website_title" name="website_title" type="text" value="{{ $formData['website_title'] ?? '' }}" placeholder="Your application name" class="{{ $errorBag->has('website_title') ? 'input-error' : '' }}">
                                @if ($errorBag->has('website_title')) <div class="field-error">{{ $errorBag->first('website_title') }}</div> @endif
                            </div>
                            <div class="field full">
                                <label for="website_description">Website description</label>
                                <textarea id="website_description" name="website_description" placeholder="Short description used for meta tags" class="{{ $errorBag->has('website_description') ? 'input-error' : '' }}">{{ $formData['website_description'] ?? '' }}</textarea>
                                @if ($errorBag->has('website_description')) <div class="field-error">{{ $errorBag->first('website_description') }}</div> @endif
                            </div>
                            <div class="field full">
                                <label for="website_keywords">Website keywords</label>
                                <textarea id="website_keywords" name="website_keywords" placeholder="keyword one, keyword two, keyword three" class="{{ $errorBag->has('website_keywords') ? 'input-error' : '' }}">{{ $formData['website_keywords'] ?? '' }}</textarea>
                                @if ($errorBag->has('website_keywords')) <div class="field-error">{{ $errorBag->first('website_keywords') }}</div> @endif
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h3>Database Connection</h3>
                        <div class="fields">
                            <div class="field">
                                <label for="db_host">Host</label>
                                <input id="db_host" name="db_host" type="text" value="{{ $formData['db_host'] ?? 'localhost' }}" placeholder="localhost" class="{{ $errorBag->has('db_host') ? 'input-error' : '' }}">
                                @if ($errorBag->has('db_host')) <div class="field-error">{{ $errorBag->first('db_host') }}</div> @endif
                            </div>
                            <div class="field">
                                <label for="db_port">Port</label>
                                <input id="db_port" name="db_port" type="text" value="{{ $formData['db_port'] ?? '3306' }}" placeholder="3306" class="{{ $errorBag->has('db_port') ? 'input-error' : '' }}">
                                @if ($errorBag->has('db_port')) <div class="field-error">{{ $errorBag->first('db_port') }}</div> @endif
                            </div>
                            <div class="field">
                                <label for="db_database">Database name</label>
                                <input id="db_database" name="db_database" type="text" value="{{ $formData['db_database'] ?? '' }}" placeholder="application_db" class="{{ $errorBag->has('db_database') ? 'input-error' : '' }}">
                                @if ($errorBag->has('db_database')) <div class="field-error">{{ $errorBag->first('db_database') }}</div> @endif
                            </div>
                            <div class="field">
                                <label for="db_username">Username</label>
                                <input id="db_username" name="db_username" type="text" value="{{ $formData['db_username'] ?? '' }}" placeholder="root" class="{{ $errorBag->has('db_username') ? 'input-error' : '' }}">
                                @if ($errorBag->has('db_username')) <div class="field-error">{{ $errorBag->first('db_username') }}</div> @endif
                            </div>
                            <div class="field">
                                <label for="db_password">Password</label>
                                <input id="db_password" name="db_password" type="text" value="{{ $formData['db_password'] ?? '' }}" placeholder="Database password" class="{{ $errorBag->has('db_password') ? 'input-error' : '' }}">
                                @if ($errorBag->has('db_password')) <div class="field-error">{{ $errorBag->first('db_password') }}</div> @endif
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h3>Administrator Account</h3>
                        <div class="fields">
                            <div class="field">
                                <label for="admin_name">Full name *</label>
                                <input id="admin_name" name="admin_name" type="text" value="{{ $formData['admin_name'] ?? '' }}" placeholder="Administrator name" class="{{ $errorBag->has('admin_name') ? 'input-error' : '' }}">
                                @if ($errorBag->has('admin_name')) <div class="field-error">{{ $errorBag->first('admin_name') }}</div> @endif
                            </div>
                            <div class="field">
                                <label for="admin_username">Username *</label>
                                <input id="admin_username" name="admin_username" type="text" value="{{ $formData['admin_username'] ?? '' }}" placeholder="admin" class="{{ $errorBag->has('admin_username') ? 'input-error' : '' }}">
                                @if ($errorBag->has('admin_username')) <div class="field-error">{{ $errorBag->first('admin_username') }}</div> @endif
                            </div>
                            <div class="field">
                                <label for="admin_email">Email *</label>
                                <input id="admin_email" name="admin_email" type="email" value="{{ $formData['admin_email'] ?? '' }}" placeholder="admin@example.com" class="{{ $errorBag->has('admin_email') ? 'input-error' : '' }}">
                                @if ($errorBag->has('admin_email')) <div class="field-error">{{ $errorBag->first('admin_email') }}</div> @endif
                            </div>
                            <div class="field">
                                <label for="admin_timezone">Timezone *</label>
                                <select id="admin_timezone" name="admin_timezone" class="{{ $errorBag->has('admin_timezone') ? 'input-error' : '' }}">
                                    @foreach ($timezoneOptions as $timezone)
                                        <option value="{{ $timezone['value'] }}" @selected(($formData['admin_timezone'] ?? config('app.timezone', 'UTC')) === $timezone['value'])>{{ $timezone['label'] }}</option>
                                    @endforeach
                                </select>
                                @if ($errorBag->has('admin_timezone')) <div class="field-error">{{ $errorBag->first('admin_timezone') }}</div> @endif
                            </div>
                            <div class="field">
                                <label for="admin_password">Password *</label>
                                <input id="admin_password" name="admin_password" type="text" value="{{ $formData['admin_password'] ?? '' }}" placeholder="At least 8 characters" class="{{ $errorBag->has('admin_password') ? 'input-error' : '' }}">
                                @if ($errorBag->has('admin_password')) <div class="field-error">{{ $errorBag->first('admin_password') }}</div> @endif
                            </div>
                            <div class="field">
                                <label for="admin_password_confirmation">Confirm password *</label>
                                <input id="admin_password_confirmation" name="admin_password_confirmation" type="text" value="{{ $formData['admin_password_confirmation'] ?? '' }}" placeholder="Repeat the password">
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <div class="actions-note">
                            <div class="status-line">The installer will update the environment file, run migrations, save website metadata, and create the first super administrator.</div>
                        </div>
                        <a class="button-secondary" href="{{ route('installer.index') }}">Back To Requirements</a>
                        <button class="button" type="submit">Run Installation</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($currentStep === 'finish')
            <section class="panel finish-card">
                <div class="finish-eyebrow">Setup Completed</div>
                <div class="finish-icon">✓</div>
                <div>
                    <h2 class="finish-title">Installation Completed</h2>
                    <p class="finish-copy">The application is ready. Environment variables were saved, the database was migrated, website metadata was stored, and the administrator account was created successfully.</p>
                </div>
                <div class="finish-actions">
                    <a class="button" href="{{ route('home') }}">Go To Home</a>
                    <a class="button-secondary" href="{{ route('login') }}">Go To Login</a>
                </div>
            </section>
        @endif
    </div>
</body>
</html>
