<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('Edit image') }}</title>
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/brands.css') }}">
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/light.css') }}">
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/fontawesome/css/solid.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style type="text/tailwindcss">
        @theme {
            --color-accent: var(--theme-accent);
            --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
        }

        @layer base {
            [x-cloak] {
                display: none !important;
            }
        }
    </style>
    <style>
        :root {
            --theme-accent: {{ request()->routeIs('portal.files.edit-image') ? theme_setting('accent_color', 'app', '#4f46e5') : '#4f46e5' }};
            --theme-accent-rgb: {{ request()->routeIs('portal.files.edit-image') ? theme_color_rgb('accent_color', 'app', '#4f46e5') : '79, 70, 229' }};
            --theme-button-radius: {{ request()->routeIs('portal.files.edit-image') ? (int) theme_setting('button_radius', 'app', 12) : 12 }}px;
            --theme-font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
        }

        html, body {
            margin: 0;
            min-height: 100%;
            font-family: var(--theme-font-sans);
        }

        body[data-editor-theme="dark"] {
            --editor-page-bg: #131920;
            --editor-topbar: rgba(26, 33, 42, 0.98);
            --editor-sidebar: #1a212a;
            --editor-stage: #10151c;
            --editor-canvas-wrap: #0c1117;
            --editor-card: #1d2630;
            --editor-input: #131b24;
            --editor-border: rgba(255, 255, 255, 0.08);
            --editor-border-strong: rgba(255, 255, 255, 0.12);
            --editor-text: #ffffff;
            --editor-text-soft: #cbd5e1;
            --editor-text-muted: #94a3b8;
            --editor-button-soft: rgba(255, 255, 255, 0.05);
            --editor-button-soft-hover: rgba(255, 255, 255, 0.1);
        }

        body[data-editor-theme="light"] {
            --editor-page-bg: #ffffff;
            --editor-topbar: rgba(255, 255, 255, 0.98);
            --editor-sidebar: #ffffff;
            --editor-stage: #111827;
            --editor-canvas-wrap: #0f172a;
            --editor-card: #ffffff;
            --editor-input: #ffffff;
            --editor-border: rgba(15, 23, 42, 0.08);
            --editor-border-strong: rgba(15, 23, 42, 0.12);
            --editor-text: #0f172a;
            --editor-text-soft: #334155;
            --editor-text-muted: #64748b;
            --editor-button-soft: #ffffff;
            --editor-button-soft-hover: #f8fafc;
        }

        body {
            background: var(--editor-page-bg);
            color: var(--editor-text);
        }

        [data-image-editor] * {
            scrollbar-width: thin;
            scrollbar-color: rgba(100, 116, 139, 0.7) transparent;
        }

        [data-image-editor] *::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        [data-image-editor] *::-webkit-scrollbar-track {
            background: transparent;
        }

        [data-image-editor] *::-webkit-scrollbar-thumb {
            border: 3px solid transparent;
            border-radius: 9999px;
            background-clip: padding-box;
            background-color: rgba(100, 116, 139, 0.6);
        }

        [data-image-editor] *::-webkit-scrollbar-thumb:hover {
            background-color: rgba(100, 116, 139, 0.82);
        }

        [data-image-editor] *::-webkit-scrollbar-corner {
            background: transparent;
        }

        body[data-editor-theme="light"] [data-image-editor] * {
            scrollbar-color: rgba(148, 163, 184, 0.92) rgba(226, 232, 240, 0.42);
        }

        body[data-editor-theme="light"] [data-image-editor] *::-webkit-scrollbar-track {
            background: rgba(226, 232, 240, 0.46);
            border-radius: 9999px;
        }

        body[data-editor-theme="light"] [data-image-editor] *::-webkit-scrollbar-thumb {
            background-color: rgba(148, 163, 184, 0.92);
        }

        body[data-editor-theme="dark"] [data-image-editor] * {
            scrollbar-color: rgba(71, 85, 105, 0.95) rgba(15, 23, 42, 0.32);
        }

        body[data-editor-theme="dark"] [data-image-editor] *::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.34);
            border-radius: 9999px;
        }

        body[data-editor-theme="dark"] [data-image-editor] *::-webkit-scrollbar-thumb {
            background-color: rgba(71, 85, 105, 0.95);
        }

        [data-image-editor] {
            background: var(--editor-page-bg) !important;
            color: var(--editor-text) !important;
        }

        [data-image-editor] header,
        [data-image-editor] [data-editor-stage-bar] {
            background: var(--editor-topbar) !important;
            border-color: var(--editor-border) !important;
        }

        [data-image-editor] aside {
            background: var(--editor-sidebar) !important;
            border-color: var(--editor-border) !important;
        }

        [data-image-editor] aside > section,
        [data-image-editor] aside > div {
            background: var(--editor-card) !important;
            border-color: var(--editor-border) !important;
            box-shadow: 0 10px 30px -24px rgba(15, 23, 42, 0.18);
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-adjust-panel] {
            box-shadow: 0 18px 40px -30px rgba(15, 23, 42, 0.16);
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-adjust-panel] [data-editor-slider-group] + [data-editor-slider-group] {
            margin-top: 0.9rem;
        }

        [data-image-editor] [data-editor-preset-card] {
            border-color: rgba(148, 163, 184, 0.14);
            background: rgba(255, 255, 255, 0.04);
        }

        [data-image-editor] [data-editor-preset-card][data-active="true"] {
            border-color: rgba(var(--theme-accent-rgb), 0.72);
            background: rgba(var(--theme-accent-rgb), 0.12);
            box-shadow: 0 0 0 1px rgba(var(--theme-accent-rgb), 0.18), 0 14px 24px -18px rgba(var(--theme-accent-rgb), 0.45);
        }

        [data-image-editor] [data-editor-preset-card][data-active="true"] [data-editor-preset-label] {
            color: #ffffff;
        }

        [data-image-editor] [data-editor-preset-overlay] {
            opacity: 0.42;
            mix-blend-mode: normal;
        }

        [data-image-editor] [data-editor-preset-card]:hover [data-editor-preset-overlay] {
            opacity: 0.54;
        }

        [data-image-editor] [data-editor-preset-card][data-active="true"] [data-editor-preset-overlay] {
            opacity: 0.68;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card] [data-editor-preset-preview] {
            height: 3.2rem;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card] [data-editor-preset-label] {
            min-height: 2.35rem;
            padding-top: 0.55rem;
            padding-bottom: 0.55rem;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card] {
            border-color: rgba(148, 163, 184, 0.28);
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card]:hover {
            border-color: rgba(99, 102, 241, 0.28);
            background: #f8fafc;
            box-shadow: 0 10px 22px -20px rgba(15, 23, 42, 0.16);
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card][data-active="true"] {
            border-color: rgba(var(--theme-accent-rgb), 0.68);
            background: linear-gradient(180deg, rgba(var(--theme-accent-rgb), 0.12), rgba(var(--theme-accent-rgb), 0.06));
            box-shadow: 0 0 0 1px rgba(var(--theme-accent-rgb), 0.2), 0 16px 32px -24px rgba(var(--theme-accent-rgb), 0.35);
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card][data-active="true"] [data-editor-preset-label] {
            color: #0f172a;
            font-weight: 700;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card][data-active="true"] [data-editor-preset-preview] {
            box-shadow: inset 0 0 0 1px rgba(var(--theme-accent-rgb), 0.18);
        }

        [data-image-editor] [data-editor-preset-preview-canvas] {
            display: block;
            width: 100%;
            height: 100%;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-overlay] {
            opacity: 0.28;
            mix-blend-mode: normal;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card]:hover [data-editor-preset-overlay] {
            opacity: 0.38;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card][data-active="true"] [data-editor-preset-overlay] {
            opacity: 0.48;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card][data-active="true"] .group-data-\[active\=true\]\:inline-flex,
        body[data-editor-theme="light"] [data-image-editor] [data-editor-preset-card][data-active="true"] [data-editor-preset-badge] {
            border-color: rgba(var(--theme-accent-rgb), 0.24) !important;
            background: rgba(var(--theme-accent-rgb), 0.18) !important;
            color: rgb(var(--theme-accent-rgb)) !important;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-adjust-panel] [data-editor-adjust-body] {
            position: relative;
            padding-bottom: 1.1rem;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-adjust-panel] [data-editor-adjust-body]::after {
            content: "";
            position: absolute;
            left: 1rem;
            right: 1rem;
            bottom: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(148, 163, 184, 0.32), transparent);
        }

        [data-image-editor] [data-editor-stage] {
            background: var(--editor-stage) !important;
        }

        [data-image-editor] [data-editor-canvas-wrap] {
            background-color: var(--editor-canvas-wrap) !important;
            border-color: var(--editor-border) !important;
        }

        body[data-editor-theme="dark"] [data-image-editor] [data-editor-canvas-wrap] {
            border-radius: 1rem !important;
            background-image:
                linear-gradient(180deg, rgba(255,255,255,0.015), rgba(255,255,255,0.005)),
                radial-gradient(circle at top, rgba(255,255,255,0.025), transparent 38%) !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.03);
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-canvas-wrap] {
            background-color: #ffffff !important;
            background-image:
                radial-gradient(circle at top, rgba(96, 165, 250, 0.06), transparent 42%),
                linear-gradient(180deg, rgba(248,250,252,0.95), rgba(255,255,255,0.98)) !important;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-stage] {
            background: #f8fafc !important;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-stage-bar] {
            background: #ffffff !important;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-stage-body] {
            background: #f8fafc !important;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-canvas-wrap] {
            border-color: rgba(15, 23, 42, 0.08) !important;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.02);
        }

        body[data-editor-theme="light"] [data-image-editor] [data-editor-canvas] {
            background:
                linear-gradient(45deg, #eef2f7 25%, #e5ebf3 25%, #e5ebf3 50%, #eef2f7 50%, #eef2f7 75%, #e5ebf3 75%, #e5ebf3 100%) !important;
            background-size: 32px 32px !important;
            box-shadow: 0 30px 70px -44px rgba(15, 23, 42, 0.24) !important;
        }

        [data-image-editor] p,
        [data-image-editor] span,
        [data-image-editor] label,
        [data-image-editor] h1 {
            border-color: var(--editor-border);
        }

        [data-image-editor] .text-white {
            color: var(--editor-text) !important;
        }

        [data-image-editor] .text-slate-400,
        [data-image-editor] .text-slate-500 {
            color: var(--editor-text-muted) !important;
        }

        [data-image-editor] .text-slate-300,
        [data-image-editor] .text-slate-200,
        [data-image-editor] .text-slate-100 {
            color: var(--editor-text-soft) !important;
        }

        [data-image-editor] input[type="text"],
        [data-image-editor] input[type="number"],
        [data-image-editor] textarea,
        [data-image-editor] select {
            background: var(--editor-input) !important;
            border-color: var(--editor-border-strong) !important;
            color: var(--editor-text) !important;
        }

        [data-image-editor] input[type="range"] {
            accent-color: var(--theme-accent);
        }

        [data-image-editor] input[type="range"]::-webkit-slider-runnable-track {
            height: 0.42rem;
            border-radius: 9999px;
            background: rgba(148, 163, 184, 0.24);
        }

        [data-image-editor] input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            margin-top: -5px;
            width: 1rem;
            height: 1rem;
            border-radius: 9999px;
            border: 2px solid #ffffff;
            background: var(--theme-accent);
            box-shadow: 0 4px 14px -6px rgba(var(--theme-accent-rgb), 0.7);
        }

        [data-image-editor] input[type="range"]::-moz-range-track {
            height: 0.42rem;
            border-radius: 9999px;
            background: rgba(148, 163, 184, 0.24);
        }

        [data-image-editor] input[type="range"]::-moz-range-thumb {
            width: 1rem;
            height: 1rem;
            border-radius: 9999px;
            border: 2px solid #ffffff;
            background: var(--theme-accent);
            box-shadow: 0 4px 14px -6px rgba(var(--theme-accent-rgb), 0.7);
        }

        body[data-editor-theme="light"] [data-image-editor] input[type="range"]::-webkit-slider-runnable-track {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.18), rgba(148, 163, 184, 0.34));
        }

        body[data-editor-theme="light"] [data-image-editor] input[type="range"]::-moz-range-track {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.18), rgba(148, 163, 184, 0.34));
        }

        [data-image-editor] a[data-editor-close],
        [data-image-editor] button[data-editor-zoom],
        [data-image-editor] button[data-editor-preset],
        [data-image-editor] button[data-editor-action]:not([data-editor-action="apply-crop-selection"]) {
            background: var(--editor-button-soft) !important;
            border-color: var(--editor-border-strong) !important;
            color: var(--editor-text-soft) !important;
        }

        [data-image-editor] a[data-editor-close]:hover,
        [data-image-editor] button[data-editor-zoom]:hover,
        [data-image-editor] button[data-editor-preset]:hover,
        [data-image-editor] button[data-editor-action]:not([data-editor-action="apply-crop-selection"]):hover {
            background: var(--editor-button-soft-hover) !important;
        }

        [data-image-editor] button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        [data-image-editor] [data-editor-ui-button] {
            min-height: 2.5rem;
            border-radius: 0.95rem;
            border: 1px solid var(--editor-border-strong);
            background: var(--editor-button-soft);
            color: var(--editor-text-soft);
            font-size: 0.875rem;
            font-weight: 600;
            transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        [data-image-editor] [data-editor-ui-button]:hover {
            background: var(--editor-button-soft-hover);
        }

        [data-image-editor] [data-editor-ui-icon-button] {
            display: inline-flex;
            height: 2.75rem;
            width: 2.75rem;
            align-items: center;
            justify-content: center;
            border-radius: var(--theme-input-radius, 0.75rem);
            border: 1px solid var(--editor-border-strong);
            background: var(--editor-input);
            color: var(--editor-text-soft);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        [data-image-editor] [data-editor-ui-icon-button]:hover {
            background: var(--editor-button-soft-hover);
        }

        [data-image-editor] [data-editor-ui-select-wrap] {
            position: relative;
        }

        [data-image-editor] [data-editor-ui-select-wrap]::after {
            content: "\f078";
            position: absolute;
            right: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            font-family: "Font Awesome 6 Pro";
            font-size: 0.7rem;
            font-weight: 300;
            color: var(--editor-text-muted);
            pointer-events: none;
        }

        [data-image-editor] [data-editor-ui-select] {
            min-height: 2.5rem;
            width: 100%;
            appearance: none;
            border-radius: 0.95rem;
            border: 1px solid var(--editor-border-strong);
            background: var(--editor-input);
            color: var(--editor-text);
            padding: 0 2.5rem 0 0.9rem;
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        [data-image-editor] [data-editor-ui-select]:focus {
            border-color: var(--theme-accent);
            box-shadow: 0 0 0 4px rgba(var(--theme-accent-rgb), 0.1);
        }

        [data-image-editor] [data-editor-ui-group-item] {
            min-height: 2.5rem;
            border: 0;
            background: transparent;
            color: inherit;
            outline: none;
        }

        [data-image-editor] [data-editor-ui-group-item] + [data-editor-ui-group-item] {
            border-left: 1px solid var(--editor-border-strong);
        }

        [data-image-editor] [data-ui-button-group="attached"] {
            border-radius: var(--ui-button-group-radius, 1rem) !important;
            border-color: var(--editor-border-strong) !important;
            background: var(--editor-card) !important;
            box-shadow: none !important;
        }

        [data-image-editor] [data-ui-button-group="attached"] > button,
        [data-image-editor] [data-ui-button-group="attached"] > a,
        [data-image-editor] [data-ui-button-group="attached"] > span > button,
        [data-image-editor] [data-ui-button-group="attached"] > span > a {
            min-height: var(--ui-button-group-item-height, 2.5rem);
            border: 0;
            border-right: 1px solid var(--editor-border-strong);
            background: transparent;
            color: var(--editor-text-soft);
            padding: 0 var(--ui-button-group-item-padding-x, 0.95rem);
            font-size: var(--ui-button-group-item-font-size, 0.875rem);
            font-weight: 600;
            transition: background 0.18s ease, color 0.18s ease;
            box-shadow: none !important;
        }

        [data-image-editor] [data-ui-button-group="attached"] > :last-child,
        [data-image-editor] [data-ui-button-group="attached"] > span:last-child > button,
        [data-image-editor] [data-ui-button-group="attached"] > span:last-child > a {
            border-right: 0;
        }

        [data-image-editor] [data-ui-button-group="attached"] > button:hover,
        [data-image-editor] [data-ui-button-group="attached"] > a:hover,
        [data-image-editor] [data-ui-button-group="attached"] > span > button:hover,
        [data-image-editor] [data-ui-button-group="attached"] > span > a:hover {
            background: var(--editor-button-soft-hover);
        }

        body[data-editor-theme="light"] [data-image-editor] [data-ui-button-group="attached"] {
            background: #ffffff !important;
            border-color: rgba(148, 163, 184, 0.28) !important;
        }

        body[data-editor-theme="light"] [data-image-editor] [data-ui-button-group="attached"] > button,
        body[data-editor-theme="light"] [data-image-editor] [data-ui-button-group="attached"] > a,
        body[data-editor-theme="light"] [data-image-editor] [data-ui-button-group="attached"] > span > button,
        body[data-editor-theme="light"] [data-image-editor] [data-ui-button-group="attached"] > span > a {
            color: #0f172a;
            border-right-color: rgba(148, 163, 184, 0.24);
        }

        body[data-editor-theme="light"] [data-image-editor] [data-ui-button-group="attached"] > button:hover,
        body[data-editor-theme="light"] [data-image-editor] [data-ui-button-group="attached"] > a:hover,
        body[data-editor-theme="light"] [data-image-editor] [data-ui-button-group="attached"] > span > button:hover,
        body[data-editor-theme="light"] [data-image-editor] [data-ui-button-group="attached"] > span > a:hover {
            background: #f8fafc;
        }

        body[data-editor-theme="light"] [data-image-editor] a[data-editor-close],
        body[data-editor-theme="light"] [data-image-editor] button[data-editor-zoom],
        body[data-editor-theme="light"] [data-image-editor] button[data-editor-preset],
        body[data-editor-theme="light"] [data-image-editor] button[data-editor-action]:not([data-editor-action="apply-crop-selection"]) {
            background: #ffffff !important;
            border-color: rgba(148, 163, 184, 0.32) !important;
            color: #0f172a !important;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        body[data-editor-theme="light"] [data-image-editor] a[data-editor-close]:hover,
        body[data-editor-theme="light"] [data-image-editor] button[data-editor-zoom]:hover,
        body[data-editor-theme="light"] [data-image-editor] button[data-editor-preset]:hover,
        body[data-editor-theme="light"] [data-image-editor] button[data-editor-action]:not([data-editor-action="apply-crop-selection"]):hover {
            background: #f8fafc !important;
            border-color: rgba(99, 102, 241, 0.28) !important;
        }

        body[data-editor-theme="light"] [data-image-editor] button[data-editor-action="apply-crop-selection"] {
            border-color: rgba(16, 185, 129, 0.45) !important;
            background: rgba(16, 185, 129, 0.14) !important;
            color: #047857 !important;
            font-weight: 600;
        }

        body[data-editor-theme="light"] [data-image-editor] button[data-editor-action="apply-crop-selection"]:hover {
            background: rgba(16, 185, 129, 0.2) !important;
            border-color: rgba(5, 150, 105, 0.5) !important;
            color: #065f46 !important;
        }

        [data-image-editor] [data-editor-text-style][data-active="true"] {
            background: color-mix(in srgb, var(--theme-accent) 14%, transparent) !important;
            border-color: rgba(var(--theme-accent-rgb), 0.4) !important;
            color: var(--theme-accent) !important;
        }
    </style>
    <script>
        (() => {
            const storageKey = 'appearance';
            const media = window.matchMedia('(prefers-color-scheme: dark)');
            const supportsDark = @js((string) theme_setting('supports_dark_mode', 'app', '1')) !== '0';
            const allowToggle = @js((string) theme_setting('allow_user_appearance_toggle', 'app', '1')) !== '0';
            const defaultMode = @js(theme_setting('default_appearance', 'app', theme_setting('appearance', 'app', 'system')));

            const normalizeMode = (mode) => {
                const allowedModes = supportsDark ? ['light', 'dark', 'system'] : ['light'];
                const fallback = supportsDark ? (defaultMode || 'system') : 'light';

                return allowedModes.includes(mode) ? mode : fallback;
            };

            const getMode = () => {
                if (!allowToggle) {
                    return normalizeMode(defaultMode || 'system');
                }

                return normalizeMode(window.localStorage?.getItem(storageKey) || defaultMode || 'system');
            };

            const resolveMode = (mode) => {
                const normalized = normalizeMode(mode);

                if (!supportsDark) {
                    return 'light';
                }

                return normalized === 'dark' || (normalized === 'system' && media.matches) ? 'dark' : 'light';
            };

            const applyTheme = (mode = getMode()) => {
                const resolved = resolveMode(mode);
                document.body.dataset.editorTheme = resolved;
                document.documentElement.dataset.editorTheme = resolved;
            };

            document.addEventListener('DOMContentLoaded', () => {
                applyTheme();
            });

            media.addEventListener?.('change', () => {
                if (getMode() === 'system') {
                    applyTheme('system');
                }
            });

            window.addEventListener('storage', (event) => {
                if (event.key === storageKey) {
                    applyTheme();
                }
            });
        })();
    </script>
</head>
<body>
<div
    data-image-editor
    data-preview-url="{{ $editorSourceUrl ?? $previewUrl }}"
    data-save-url="{{ $saveUrl }}"
    data-back-url="{{ $backUrl }}"
    data-file-name="{{ $file->name }}"
    data-file-extension="{{ strtolower((string) $file->extension) }}"
    data-file-mime="{{ $file->mime_type ?: 'image/png' }}"
    data-original-width="{{ (int) ($file->width ?: 0) }}"
    data-original-height="{{ (int) ($file->height ?: 0) }}"
    data-csrf="{{ csrf_token() }}"
    class="fixed inset-0 z-[9999] h-screen w-screen overflow-hidden bg-[#131920] text-white"
>
        <img
            data-editor-source-image
            src="{{ $editorSourceUrl ?? $previewUrl }}"
            alt=""
            decoding="async"
            class="hidden"
        >
        <div class="flex h-full w-full flex-col">
            <header class="shrink-0 border-b border-white/8 bg-[#1a212a]/98 px-5 py-4 shadow-[0_24px_80px_-48px_rgba(0,0,0,0.55)] backdrop-blur-sm">
                <div class="flex items-start justify-center gap-3 sm:justify-between">
                    <div class="hidden min-w-0 flex-1 sm:block">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-400">{{ __('Shared image editor') }}</p>
                        <h1 class="mt-1 max-w-full truncate text-[1.02rem] font-semibold tracking-[-0.035em] text-white sm:text-[1.08rem]">{{ $file->name }}</h1>
                    </div>

                    <div class="shrink-0 flex items-center gap-2 rounded-[1rem] border border-white/8 bg-white/[0.03] p-1.5 sm:ml-auto sm:flex-wrap sm:rounded-none sm:border-0 sm:bg-transparent sm:p-0">
                        <button type="button" data-editor-undo aria-label="{{ __('Undo') }}" title="{{ __('Undo') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.9rem] border border-white/12 bg-transparent text-sm font-semibold text-white transition hover:bg-white/5">
                            <i class="fa-light fa-arrow-rotate-left text-sm"></i>
                        </button>
                        <button type="button" data-editor-redo aria-label="{{ __('Redo') }}" title="{{ __('Redo') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.9rem] border border-white/12 bg-transparent text-sm font-semibold text-white transition hover:bg-white/5">
                            <i class="fa-light fa-arrow-rotate-right text-sm"></i>
                        </button>
                        <button type="button" data-editor-download aria-label="{{ __('Download') }}" title="{{ __('Download') }}" class="inline-flex h-10 w-10 items-center justify-center gap-2 rounded-[0.9rem] border border-sky-500 bg-sky-500 px-0 text-sm font-semibold text-white shadow-[0_14px_28px_-18px_rgba(14,165,233,0.42)] transition hover:bg-sky-400 sm:w-auto sm:px-4">
                            <i class="fa-light fa-download text-xs"></i>
                            <span class="hidden sm:inline">{{ __('Download') }}</span>
                        </button>
                        <button type="button" data-editor-save aria-label="{{ __('Save') }}" title="{{ __('Save') }}" class="inline-flex h-10 w-10 items-center justify-center gap-2 rounded-[0.9rem] border border-emerald-500 bg-emerald-500 px-0 text-sm font-semibold text-white shadow-[0_14px_28px_-18px_rgba(16,185,129,0.42)] transition hover:bg-emerald-400 sm:w-auto sm:px-4">
                            <i class="fa-light fa-floppy-disk text-xs"></i>
                            <span class="hidden sm:inline">{{ __('Save') }}</span>
                        </button>
                        <a
                            href="{{ $backUrl }}"
                            data-editor-close
                            aria-label="{{ __('Close editor') }}"
                            title="{{ __('Close editor') }}"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-[0.9rem] border border-white/12 bg-transparent text-sm font-semibold text-white transition hover:bg-white/5"
                        >
                            <i class="fa-light fa-xmark text-sm"></i>
                        </a>
                    </div>
                </div>

                <div class="hidden gap-3 sm:grid lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1">{{ __('Original') }}: {{ number_format((int) $file->width) }} x {{ number_format((int) $file->height) }}</span>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1">{{ strtoupper((string) $file->extension) }}</span>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1">{{ $file->humanSize() }}</span>
                    </div>
                    <p data-editor-status class="text-sm text-slate-300 lg:text-right">{{ __('Ready to edit.') }}</p>
                </div>
            </header>

            <section class="grid min-h-0 flex-1 xl:grid-cols-[360px_minmax(0,1fr)]">
                <aside
                    x-data="{ open: 'filters', toggle(key) { this.open = this.open === key ? '' : key; } }"
                    class="min-h-0 space-y-3 overflow-y-auto border-r border-white/8 bg-[#1a212a] p-4"
                >
                    <section data-editor-adjust-panel class="overflow-hidden rounded-[1.2rem] border border-white/8 bg-[#1d2630]">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-4 py-4 text-left transition hover:bg-white/[0.03]"
                            x-on:click="toggle('adjust')"
                            x-bind:aria-expanded="open === 'adjust'"
                        >
                            <div>
                                <p class="text-sm font-semibold text-white">{{ __('Adjust') }}</p>
                                <p class="mt-1 text-xs leading-6 text-slate-400">{{ __('Fine-tune the visible look of the image.') }}</p>
                            </div>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-400 transition" x-bind:class="open === 'adjust' ? 'rotate-180 text-white' : ''">
                                <i class="fa-light fa-chevron-down text-xs"></i>
                            </span>
                        </button>
                        <div x-cloak x-show="open === 'adjust'" x-collapse class="border-t border-white/8 px-4 py-4">
                            <div data-editor-adjust-body class="space-y-4">
                                @foreach ([
                                    ['key' => 'brightness', 'label' => __('Brightness'), 'min' => 0, 'max' => 200, 'value' => 100],
                                    ['key' => 'contrast', 'label' => __('Contrast'), 'min' => 0, 'max' => 200, 'value' => 100],
                                    ['key' => 'saturation', 'label' => __('Saturation'), 'min' => 0, 'max' => 200, 'value' => 100],
                                    ['key' => 'grayscale', 'label' => __('Grayscale'), 'min' => 0, 'max' => 100, 'value' => 0],
                                    ['key' => 'sepia', 'label' => __('Sepia'), 'min' => 0, 'max' => 100, 'value' => 0],
                                    ['key' => 'blur', 'label' => __('Blur'), 'min' => 0, 'max' => 20, 'value' => 0],
                                ] as $slider)
                                    <label data-editor-slider-group class="block">
                                        <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                                            <span class="font-medium text-slate-300">{{ $slider['label'] }}</span>
                                            <span class="text-slate-500" data-editor-value="{{ $slider['key'] }}">{{ $slider['value'] }}</span>
                                        </div>
                                        <input
                                            type="range"
                                            min="{{ $slider['min'] }}"
                                            max="{{ $slider['max'] }}"
                                            value="{{ $slider['value'] }}"
                                            data-editor-slider="{{ $slider['key'] }}"
                                            class="h-2 w-full cursor-pointer appearance-none rounded-full bg-white/10 accent-[var(--theme-accent)]"
                                        >
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-[1.2rem] border border-white/8 bg-[#1d2630]">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-4 py-4 text-left transition hover:bg-white/[0.03]"
                            x-on:click="toggle('filters')"
                            x-bind:aria-expanded="open === 'filters'"
                        >
                            <div>
                                <p class="text-sm font-semibold text-white">{{ __('Filters') }}</p>
                                <p class="mt-1 text-xs leading-6 text-slate-400">{{ __('Apply a preset look or save your own reusable filter combinations.') }}</p>
                            </div>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-400 transition" x-bind:class="open === 'filters' ? 'rotate-180 text-white' : ''">
                                <i class="fa-light fa-chevron-down text-xs"></i>
                            </span>
                        </button>
                        <div x-cloak x-show="open === 'filters'" x-collapse class="border-t border-white/8 px-4 py-4">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Presets') }}</p>
                                    <button type="button" data-editor-action="clear-preset" class="text-xs font-medium text-slate-500 transition hover:text-slate-300">{{ __('Clear') }}</button>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ([
                                        ['key' => 'cross-process', 'label' => __('Cross Process'), 'surface' => 'linear-gradient(135deg, rgba(34,197,94,0.16), rgba(59,130,246,0.14))', 'overlay' => 'linear-gradient(135deg, rgba(20,184,166,0.28), rgba(37,99,235,0.18))'],
                                        ['key' => 'glow-sun', 'label' => __('Glow Sun'), 'surface' => 'linear-gradient(135deg, rgba(254,240,138,0.18), rgba(251,146,60,0.12))', 'overlay' => 'linear-gradient(135deg, rgba(250,204,21,0.30), rgba(249,115,22,0.18))'],
                                        ['key' => 'jarques', 'label' => __('Jarques'), 'surface' => 'linear-gradient(135deg, rgba(244,114,182,0.14), rgba(192,132,252,0.12))', 'overlay' => 'linear-gradient(135deg, rgba(236,72,153,0.24), rgba(139,92,246,0.18))'],
                                        ['key' => 'love', 'label' => __('Love'), 'surface' => 'linear-gradient(135deg, rgba(251,113,133,0.14), rgba(244,114,182,0.12))', 'overlay' => 'linear-gradient(135deg, rgba(244,63,94,0.22), rgba(236,72,153,0.18))'],
                                        ['key' => 'old-boot', 'label' => __('Old Boot'), 'surface' => 'linear-gradient(135deg, rgba(180,83,9,0.14), rgba(120,53,15,0.14))', 'overlay' => 'linear-gradient(135deg, rgba(146,64,14,0.30), rgba(120,53,15,0.20))'],
                                        ['key' => 'orange-peel', 'label' => __('Orange Peel'), 'surface' => 'linear-gradient(135deg, rgba(253,186,116,0.18), rgba(249,115,22,0.12))', 'overlay' => 'linear-gradient(135deg, rgba(251,146,60,0.28), rgba(234,88,12,0.18))'],
                                        ['key' => 'pin-hole', 'label' => __('Pin Hole'), 'surface' => 'linear-gradient(135deg, rgba(51,65,85,0.18), rgba(15,23,42,0.16))', 'overlay' => 'radial-gradient(circle at center, rgba(15,23,42,0.02) 18%, rgba(15,23,42,0.32) 100%)'],
                                        ['key' => 'sepia', 'label' => __('Sepia'), 'surface' => 'linear-gradient(135deg, rgba(217,119,6,0.14), rgba(120,53,15,0.14))', 'overlay' => 'linear-gradient(135deg, rgba(180,83,9,0.24), rgba(120,53,15,0.20))'],
                                        ['key' => 'sun-rise', 'label' => __('Sun Rise'), 'surface' => 'linear-gradient(135deg, rgba(253,224,71,0.16), rgba(248,113,113,0.10))', 'overlay' => 'linear-gradient(135deg, rgba(250,204,21,0.26), rgba(239,68,68,0.14))'],
                                        ['key' => 'vintage', 'label' => __('Vintage'), 'surface' => 'linear-gradient(135deg, rgba(192,132,252,0.12), rgba(251,191,36,0.12))', 'overlay' => 'linear-gradient(135deg, rgba(168,85,247,0.18), rgba(217,119,6,0.18))'],
                                    ] as $preset)
                                        <button
                                            type="button"
                                            data-editor-preset-card="{{ $preset['key'] }}"
                                            class="group overflow-hidden rounded-[1rem] border border-white/10 bg-white/5 text-left transition hover:-translate-y-0.5 hover:border-sky-400/40 hover:bg-white/8 data-[active=true]:border-sky-400/70 data-[active=true]:bg-sky-500/10"
                                        >
                                            <span class="relative block">
                                                <span
                                                    class="block h-16 w-full overflow-hidden rounded-t-[0.95rem] transition duration-200 group-data-[active=true]:scale-[1.02]"
                                                    data-editor-preset-preview
                                                    data-editor-preset-preview="{{ $preset['key'] }}"
                                                    style="background: {{ $preset['surface'] }};"
                                                >
                                                    <svg viewBox="0 0 160 96" class="h-full w-full" aria-hidden="true" focusable="false">
                                                        <defs>
                                                            <linearGradient id="editor-preset-sky" x1="0%" x2="100%" y1="0%" y2="100%">
                                                                <stop offset="0%" stop-color="#e0f2fe" />
                                                                <stop offset="100%" stop-color="#bae6fd" />
                                                            </linearGradient>
                                                            <linearGradient id="editor-preset-ground" x1="0%" x2="100%" y1="0%" y2="0%">
                                                                <stop offset="0%" stop-color="#99f6e4" />
                                                                <stop offset="100%" stop-color="#2dd4bf" />
                                                            </linearGradient>
                                                            <linearGradient id="editor-preset-card" x1="0%" x2="100%" y1="0%" y2="100%">
                                                                <stop offset="0%" stop-color="#ffffff" stop-opacity="0.98" />
                                                                <stop offset="100%" stop-color="#dbeafe" stop-opacity="0.94" />
                                                            </linearGradient>
                                                        </defs>
                                                        <rect width="160" height="96" rx="14" fill="url(#editor-preset-sky)" />
                                                        <circle cx="126" cy="22" r="11" fill="#fef08a" />
                                                        <path d="M0 71L22 54L40 64L64 38L85 56L106 33L130 52L160 36V96H0Z" fill="#93c5fd" fill-opacity="0.9" />
                                                        <path d="M0 80L28 67L58 78L91 52L118 66L160 56V96H0Z" fill="url(#editor-preset-ground)" />
                                                        <rect x="18" y="24" width="66" height="42" rx="10" fill="url(#editor-preset-card)" />
                                                        <rect x="26" y="32" width="50" height="5" rx="2.5" fill="#94a3b8" fill-opacity="0.9" />
                                                        <rect x="26" y="42" width="26" height="16" rx="4" fill="#38bdf8" fill-opacity="0.9" />
                                                        <rect x="56" y="42" width="20" height="7" rx="3.5" fill="#cbd5e1" />
                                                        <rect x="56" y="51" width="14" height="7" rx="3.5" fill="#e2e8f0" />
                                                        <path d="M106 26C114 18 127 18 135 26C143 34 143 48 135 56L120 71L105 56C97 48 97 34 105 26Z" fill="#2563eb" fill-opacity="0.9" />
                                                        <path d="M111 42L118 49L131 36" fill="none" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="5" />
                                                    </svg>
                                                </span>
                                                <span
                                                    class="pointer-events-none absolute inset-0 opacity-0 transition duration-200"
                                                    data-editor-preset-overlay
                                                    data-editor-preset-overlay="{{ $preset['key'] }}"
                                                    style="background: {{ $preset['overlay'] }};"
                                                ></span>
                                                <span data-editor-preset-badge class="pointer-events-none absolute right-2 top-2 hidden rounded-full border border-sky-300/50 bg-sky-500/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-sky-100 backdrop-blur-sm group-data-[active=true]:inline-flex">
                                                    {{ __('On') }}
                                                </span>
                                            </span>
                                            <span data-editor-preset-label class="block min-h-[3.25rem] px-3 py-2 text-left text-xs font-medium leading-5 text-slate-100">
                                                {{ $preset['label'] }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                                <div class="rounded-[1rem] border border-white/8 bg-white/[0.03] p-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Your presets') }}</p>
                                    <div class="mt-3 flex gap-2">
                                        <x-ui.input type="text" data-editor-custom-preset-name class="min-w-0 flex-1" placeholder="{{ __('Preset name') }}" />
                                        <button type="button" data-editor-action="save-custom-preset" class="rounded-[0.95rem] border border-white/10 bg-white/5 px-3 py-2 text-xs font-medium text-slate-200 transition hover:bg-white/10">{{ __('Save') }}</button>
                                    </div>
                                    <div data-editor-custom-presets class="mt-3 space-y-2"></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-[1.2rem] border border-white/8 bg-[#1d2630]">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-4 py-4 text-left transition hover:bg-white/[0.03]"
                            x-on:click="toggle('transform')"
                            x-bind:aria-expanded="open === 'transform'"
                        >
                            <div>
                                <p class="text-sm font-semibold text-white">{{ __('Transform') }}</p>
                                <p class="mt-1 text-xs leading-6 text-slate-400">{{ __('Rotate, flip, crop, and resize the current image.') }}</p>
                            </div>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-400 transition" x-bind:class="open === 'transform' ? 'rotate-180 text-white' : ''">
                                <i class="fa-light fa-chevron-down text-xs"></i>
                            </span>
                        </button>
                        <div x-cloak x-show="open === 'transform'" x-collapse class="border-t border-white/8 px-4 py-4">
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" data-editor-action="rotate-left" class="rounded-[1rem] border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10">{{ __('Rotate -90°') }}</button>
                                <button type="button" data-editor-action="rotate-right" class="rounded-[1rem] border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10">{{ __('Rotate +90°') }}</button>
                                <button type="button" data-editor-action="flip-x" class="rounded-[1rem] border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10">{{ __('Flip X') }}</button>
                                <button type="button" data-editor-action="flip-y" class="rounded-[1rem] border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10">{{ __('Flip Y') }}</button>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Crop') }}</p>
                                    <div class="flex items-center gap-3">
                                        <button type="button" data-editor-action="toggle-crop-mode" class="text-xs font-medium text-sky-300 transition hover:text-sky-200">{{ __('Manual crop') }}</button>
                                        <button type="button" data-editor-action="reset-crop" class="text-xs font-medium text-slate-500 transition hover:text-slate-300">{{ __('Reset') }}</button>
                                    </div>
                                </div>
                                <p class="text-xs leading-6 text-slate-500">{{ __('Enable manual crop, then drag on the canvas to select the new crop area.') }}</p>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <button type="button" data-editor-action="apply-crop-selection" class="rounded-[0.95rem] border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-emerald-200 transition hover:bg-emerald-500/18">{{ __('Apply crop') }}</button>
                                    <button type="button" data-editor-action="cancel-crop-mode" class="rounded-[0.95rem] border border-white/10 bg-white/5 px-3 py-2 text-slate-300 transition hover:bg-white/10">{{ __('Cancel crop') }}</button>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <x-ui.input type="number" label="X" min="0" step="1" data-editor-input="crop-x" />
                                    <x-ui.input type="number" label="Y" min="0" step="1" data-editor-input="crop-y" />
                                    <x-ui.input type="number" :label="__('Width')" min="1" step="1" data-editor-input="crop-width" />
                                    <x-ui.input type="number" :label="__('Height')" min="1" step="1" data-editor-input="crop-height" />
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <button type="button" data-editor-preset="free" class="rounded-[0.95rem] border border-white/10 bg-white/5 px-3 py-2 text-slate-300 transition hover:bg-white/10">{{ __('Full frame') }}</button>
                                    <button type="button" data-editor-preset="square" class="rounded-[0.95rem] border border-white/10 bg-white/5 px-3 py-2 text-slate-300 transition hover:bg-white/10">{{ __('1:1 square') }}</button>
                                    <button type="button" data-editor-preset="portrait" class="rounded-[0.95rem] border border-white/10 bg-white/5 px-3 py-2 text-slate-300 transition hover:bg-white/10">{{ __('4:5 portrait') }}</button>
                                    <button type="button" data-editor-preset="landscape" class="rounded-[0.95rem] border border-white/10 bg-white/5 px-3 py-2 text-slate-300 transition hover:bg-white/10">{{ __('16:9 landscape') }}</button>
                                </div>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Resize') }}</p>
                                    <x-ui.checkbox :checked="true" :label="__('Lock ratio')" data-editor-lock />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <x-ui.input type="number" :label="__('Width')" min="1" step="1" data-editor-input="output-width" />
                                    <x-ui.input type="number" :label="__('Height')" min="1" step="1" data-editor-input="output-height" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-[1.2rem] border border-white/8 bg-[#1d2630]">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-4 py-4 text-left transition hover:bg-white/[0.03]"
                            x-on:click="toggle('text')"
                            x-bind:aria-expanded="open === 'text'"
                        >
                            <div>
                                <p class="text-sm font-semibold text-white">{{ __('Text overlay') }}</p>
                                <p class="mt-1 text-xs leading-6 text-slate-400">{{ __('Add simple watermark or callout text before saving.') }}</p>
                            </div>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-400 transition" x-bind:class="open === 'text' ? 'rotate-180 text-white' : ''">
                                <i class="fa-light fa-chevron-down text-xs"></i>
                            </span>
                        </button>
                        <div x-cloak x-show="open === 'text'" x-collapse class="border-t border-white/8 px-4 py-4">
                            <div class="space-y-3">
                                <x-ui.checkbox :label="__('Enable text overlay')" data-editor-text-enabled />
                                <p class="text-xs leading-6 text-slate-500">{{ __('Tip: drag the text directly on the canvas to reposition it.') }}</p>
                                <div class="rounded-[1rem] border border-white/8 bg-white/[0.03] p-3">
                                    <div class="grid grid-cols-[minmax(0,1fr)_auto_auto_auto] gap-2">
                                        <x-ui.select data-editor-text-layer-select class="min-w-0"></x-ui.select>
                                        <button type="button" data-editor-action="text-add" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.9rem] border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10"><i class="fa-light fa-plus text-xs"></i></button>
                                        <button type="button" data-editor-action="text-duplicate" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.9rem] border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10"><i class="fa-light fa-copy text-xs"></i></button>
                                        <button type="button" data-editor-action="text-delete" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.9rem] border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10"><i class="fa-light fa-trash text-xs"></i></button>
                                    </div>
                                    <x-ui.button-group class="mt-2 w-full">
                                        <button type="button" data-editor-action="layer-backward" class="flex-1">{{ __('Send back') }}</button>
                                        <button type="button" data-editor-action="layer-forward" class="flex-1">{{ __('Bring front') }}</button>
                                    </x-ui.button-group>
                                </div>
                                <x-ui.textarea :label="__('Text')" data-editor-text="value" rows="3" placeholder="{{ __('Your brand, note, or watermark') }}"></x-ui.textarea>
                                <div class="grid grid-cols-2 gap-3">
                                    <x-ui.select :label="__('Font family')" data-editor-text="fontFamily">
                                            <option value="Arial">Arial</option>
                                            <option value="Georgia">Georgia</option>
                                            <option value="Impact">Impact</option>
                                            <option value="Tahoma">Tahoma</option>
                                            <option value="Times New Roman">Times New Roman</option>
                                            <option value="Verdana">Verdana</option>
                                    </x-ui.select>
                                    <x-ui.input type="number" :label="__('Size')" min="8" step="1" value="48" data-editor-text="size" />
                                </div>
                                <div class="grid grid-cols-5 gap-2">
                                    <button type="button" data-editor-text-style="bold" class="rounded-[0.9rem] border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/10">{{ __('Bold') }}</button>
                                    <button type="button" data-editor-text-style="italic" class="rounded-[0.9rem] border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/10">{{ __('Italic') }}</button>
                                    <button type="button" data-editor-text-style="align-left" class="rounded-[0.9rem] border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/10"><i class="fa-light fa-align-left"></i></button>
                                    <button type="button" data-editor-text-style="align-center" class="rounded-[0.9rem] border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/10"><i class="fa-light fa-align-center"></i></button>
                                    <button type="button" data-editor-text-style="align-right" class="rounded-[0.9rem] border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/10"><i class="fa-light fa-align-right"></i></button>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="mb-2 block text-xs text-slate-400">{{ __('Fill color') }}</span>
                                        <x-ui.group-input class="space-y-0">
                                            <span data-editor-ui-group-item class="relative inline-flex w-full items-center gap-2 px-2">
                                                <span class="inline-flex h-6 w-6 shrink-0 rounded-[0.45rem] border border-white/10 bg-white" data-editor-color-preview></span>
                                                <span class="text-sm text-slate-300">#FFFFFF</span>
                                                <input type="color" value="#ffffff" data-editor-text="color" class="absolute inset-0 cursor-pointer opacity-0">
                                            </span>
                                        </x-ui.group-input>
                                    </label>
                                    <label class="block">
                                        <span class="mb-2 block text-xs text-slate-400">{{ __('Opacity') }}</span>
                                        <div class="space-y-2">
                                            <input type="range" min="0" max="100" step="1" value="100" data-editor-text="opacity" class="h-2 w-full cursor-pointer appearance-none rounded-full bg-white/10 accent-[var(--theme-accent)]">
                                            <div class="text-right text-xs text-slate-500" data-editor-value="text-opacity">100</div>
                                        </div>
                                    </label>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="mb-2 block text-xs text-slate-400">{{ __('Stroke color') }}</span>
                                        <x-ui.group-input class="space-y-0">
                                            <span data-editor-ui-group-item class="relative inline-flex w-full items-center gap-2 px-2">
                                                <span class="inline-flex h-6 w-6 shrink-0 rounded-[0.45rem] border border-white/10 bg-black" data-editor-stroke-preview></span>
                                                <span class="text-sm text-slate-300">#000000</span>
                                                <input type="color" value="#000000" data-editor-text="strokeColor" class="absolute inset-0 cursor-pointer opacity-0">
                                            </span>
                                        </x-ui.group-input>
                                    </label>
                                    <x-ui.input type="number" :label="__('Stroke width')" min="0" step="1" value="0" data-editor-text="strokeWidth" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="mb-2 block text-xs text-slate-400">{{ __('Shadow color') }}</span>
                                        <x-ui.group-input class="space-y-0">
                                            <span data-editor-ui-group-item class="relative inline-flex w-full items-center gap-2 px-2">
                                                <span class="inline-flex h-6 w-6 shrink-0 rounded-[0.45rem] border border-white/10 bg-slate-950" data-editor-shadow-preview></span>
                                                <span class="text-sm text-slate-300">#0F172A</span>
                                                <input type="color" value="#0f172a" data-editor-text="shadowColor" class="absolute inset-0 cursor-pointer opacity-0">
                                            </span>
                                        </x-ui.group-input>
                                    </label>
                                    <x-ui.input type="number" :label="__('Shadow blur')" min="0" step="1" value="12" data-editor-text="shadowBlur" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <x-ui.input type="number" :label="__('Letter spacing')" min="-10" max="40" step="1" value="0" data-editor-text="letterSpacing" />
                                    <x-ui.input type="number" :label="__('Line height %')" min="80" max="220" step="1" value="116" data-editor-text="lineHeight" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <x-ui.input type="number" :label="__('X (%)')" min="0" max="100" step="1" value="50" data-editor-text="x" />
                                    <x-ui.input type="number" :label="__('Y (%)')" min="0" max="100" step="1" value="88" data-editor-text="y" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-[1.2rem] border border-white/8 bg-[#1d2630]">
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-3 px-4 py-4 text-left transition hover:bg-white/[0.03]"
                            x-on:click="toggle('watermark')"
                            x-bind:aria-expanded="open === 'watermark'"
                        >
                            <div>
                                <p class="text-sm font-semibold text-white">{{ __('Watermark image') }}</p>
                                <p class="mt-1 text-xs leading-6 text-slate-400">{{ __('Upload a PNG or logo image, then drag it on the canvas.') }}</p>
                            </div>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-400 transition" x-bind:class="open === 'watermark' ? 'rotate-180 text-white' : ''">
                                <i class="fa-light fa-chevron-down text-xs"></i>
                            </span>
                        </button>
                        <div x-cloak x-show="open === 'watermark'" x-collapse class="border-t border-white/8 px-4 py-4">
                            <div class="space-y-3">
                                <input type="file" accept="image/*" data-editor-watermark-file class="hidden">
                                <button
                                    type="button"
                                    data-editor-action="watermark-upload"
                                    data-editor-ui-button
                                    class="w-full justify-center"
                                    onclick="this.previousElementSibling.click()"
                                >
                                    {{ __('Upload image') }}
                                </button>
                                <x-ui.button-group class="w-full" size="sm">
                                    <button type="button" data-editor-action="watermark-duplicate" class="flex-1 whitespace-nowrap">{{ __('Duplicate') }}</button>
                                    <button type="button" data-editor-action="watermark-remove" class="flex-1 whitespace-nowrap">{{ __('Remove') }}</button>
                                </x-ui.button-group>
                                <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-2">
                                    <x-ui.select data-editor-image-layer-select class="min-w-0"></x-ui.select>
                                    <button type="button" data-editor-action="layer-backward" data-editor-ui-icon-button><i class="fa-light fa-layer-minus text-xs"></i></button>
                                    <button type="button" data-editor-action="layer-forward" data-editor-ui-icon-button><i class="fa-light fa-layer-plus text-xs"></i></button>
                                </div>
                                <x-ui.checkbox :label="__('Enable overlay')" data-editor-watermark-enabled />
                                <p data-editor-watermark-name class="text-xs leading-6 text-slate-500">{{ __('No image overlay selected.') }}</p>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="mb-2 block text-xs text-slate-400">{{ __('Opacity') }}</span>
                                        <input type="range" min="0" max="100" step="1" value="72" data-editor-watermark="opacity" class="h-2 w-full cursor-pointer appearance-none rounded-full bg-white/10 accent-[var(--theme-accent)]">
                                    </label>
                                    <x-ui.input type="number" :label="__('Scale %')" min="5" max="90" step="1" value="24" data-editor-watermark="scale" />
                                    <x-ui.input type="number" :label="__('X (%)')" min="0" max="100" step="1" value="78" data-editor-watermark="x" />
                                    <x-ui.input type="number" :label="__('Y (%)')" min="0" max="100" step="1" value="78" data-editor-watermark="y" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="flex items-center justify-between gap-3 rounded-[1.2rem] border border-white/8 bg-[#1d2630] p-4">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ __('Reset all changes') }}</p>
                            <p class="mt-1 text-xs leading-6 text-slate-400">{{ __('Return to the original image and clear every tool state.') }}</p>
                        </div>
                        <button type="button" data-editor-action="reset-all" class="rounded-[1rem] border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10">{{ __('Reset') }}</button>
                    </div>

                    <div class="rounded-[1.2rem] border border-white/8 bg-[#1d2630] p-4">
                        <p class="text-sm font-semibold text-white">{{ __('Export') }}</p>
                        <div class="mt-3 space-y-3">
                            <x-ui.input type="text" :label="__('File name')" data-editor-export-name />
                            <div class="grid grid-cols-2 gap-3">
                                <x-ui.select :label="__('Format')" data-editor-export-format>
                                        <option value="jpg">JPG</option>
                                        <option value="png">PNG</option>
                                        <option value="webp">WEBP</option>
                                </x-ui.select>
                                <label class="block">
                                    <span class="mb-2 block text-xs text-slate-400">{{ __('Quality') }}</span>
                                    <div class="space-y-2">
                                        <input type="range" min="10" max="100" step="1" value="92" data-editor-export-quality class="h-2 w-full cursor-pointer appearance-none rounded-full bg-white/10 accent-[var(--theme-accent)]">
                                        <div class="text-right text-xs text-slate-500" data-editor-value="export-quality">92</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.2rem] border border-white/8 bg-[#1d2630] p-4">
                        <p class="text-sm font-semibold text-white">{{ __('Shortcuts') }}</p>
                        <p data-editor-shortcuts class="mt-1 text-xs leading-6 text-slate-400"></p>
                    </div>
                </aside>

                <section data-editor-stage class="flex min-h-0 flex-col overflow-hidden bg-[#161c24]">
                    <div data-editor-stage-bar class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-white/8 bg-[#1a212a] px-5 py-3">
                        <div class="flex items-center gap-2 text-xs text-slate-400">
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1" data-editor-dimensions>{{ __('Output') }}: {{ number_format((int) $file->width) }} x {{ number_format((int) $file->height) }}</span>
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1" data-editor-rotation>{{ __('Rotation') }}: 0°</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" data-editor-compare class="rounded-[0.95rem] border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10">{{ __('Before / After') }}</button>
                            <button type="button" data-editor-zoom="out" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.95rem] border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10"><i class="fa-light fa-magnifying-glass-minus text-xs"></i></button>
                            <button type="button" data-editor-zoom="reset" class="rounded-[0.95rem] border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10">{{ __('Fit') }}</button>
                            <button type="button" data-editor-zoom="in" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.95rem] border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10"><i class="fa-light fa-magnifying-glass-plus text-xs"></i></button>
                        </div>
                    </div>

                    <div data-editor-stage-body class="flex min-h-0 flex-1 items-center justify-center bg-[#121820] p-5 lg:p-8">
                        <div data-editor-canvas-wrap class="flex h-full w-full items-center justify-center overflow-auto rounded-[1.8rem] border border-dashed border-white/8 bg-[radial-gradient(circle_at_top,rgba(59,130,246,0.08),transparent_44%),linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01))] p-5 lg:p-8">
                            <canvas data-editor-canvas class="max-w-none rounded-[1.1rem] bg-[linear-gradient(45deg,#202933_25%,#1b232d_25%,#1b232d_50%,#202933_50%,#202933_75%,#1b232d_75%,#1b232d_100%)] shadow-[0_38px_90px_-48px_rgba(0,0,0,0.78)] [background-size:32px_32px]"></canvas>
                        </div>
                    </div>
                </section>
            </section>
        </div>
</div>

@include('appfiles::partials.editor-script')
</body>
</html>
