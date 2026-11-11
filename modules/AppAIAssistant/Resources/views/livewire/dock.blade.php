<div
    class="ai-assistant-root"
    x-data="{
        composerActive: false,
        open: (() => { try { return localStorage.getItem('stackposts-ai-assistant-open') === '1'; } catch { return false; } })(),
        persist() { try { localStorage.setItem('stackposts-ai-assistant-open', this.open ? '1' : '0'); } catch {} },
        toggle() { this.open = !this.open; this.persist(); },
        close() { if (!this.open) return; this.open = false; this.persist(); this.$refs.launcher?.focus({ preventScroll: true }); }
    }"
    x-show="!composerActive"
    x-on:publishing-composer-scroll-lock.window="composerActive = true"
    x-on:publishing-composer-scroll-unlock.window="composerActive = false"
    x-on:livewire:navigated.window="composerActive = false"
    x-on:keydown.escape.window="close()"
    x-on:assistant-focus-question.window="$nextTick(() => { $refs.question?.focus({ preventScroll: true }); })"
>
    @if ($available)
        @php
            $remainingLabel = ($creditSummary['unlimited'] ?? false) ? __('Unlimited') : number_format((int) ($creditSummary['remaining'] ?? 0));
            $planName = data_get($workspaceSummary, 'plan.name', __('No active plan'));
            $navigationByKey = collect($navigation)->keyBy('key');
        @endphp
        <style>
            .ai-assistant-root { --ai-accent: var(--theme-accent, #2563eb); --ai-border: color-mix(in srgb, var(--theme-border-color) 72%, transparent); }
            .ai-assistant-launcher { position: fixed; z-index: 160; right: 20px; bottom: 20px; display: grid; width: 56px; height: 56px; place-items: center; padding: 0; border: 1px solid color-mix(in srgb, var(--ai-accent) 28%, var(--theme-border-color)); border-radius: 999px; background: color-mix(in srgb, var(--theme-surface-base) 94%, var(--ai-accent) 6%); color: var(--theme-header-text-color); box-shadow: 0 22px 50px -22px rgba(15,23,42,.58), 0 8px 24px -16px var(--ai-accent); backdrop-filter: blur(16px); transition: border-color .2s ease, box-shadow .2s ease, background .2s ease, transform .2s ease; isolation: isolate; }
            .ai-assistant-launcher:hover { border-color: color-mix(in srgb, var(--ai-accent) 52%, var(--theme-border-color)); background: color-mix(in srgb, var(--theme-surface-base) 90%, var(--ai-accent) 10%); box-shadow: 0 26px 56px -22px rgba(15,23,42,.62), 0 12px 30px -16px var(--ai-accent); }
            .ai-assistant-launcher:focus-visible { outline: 3px solid color-mix(in srgb, var(--ai-accent) 24%, transparent); outline-offset: 4px; }
            .ai-assistant-launcher__icon { display: grid; width: 40px; height: 40px; flex: 0 0 auto; place-items: center; border-radius: 11px; color: #fff; background:var(--ai-accent); box-shadow: 0 14px 28px -16px var(--ai-accent); }
            .ai-assistant-launcher .ai-assistant-launcher__icon { width: 44px; height: 44px; border-radius: 999px; position:relative; transition:background .15s ease; }
            .ai-assistant-launcher:hover .ai-assistant-launcher__icon { background:var(--ai-accent); }
            .ai-assistant-launcher.is-open .ai-assistant-launcher__icon { animation: none; background:var(--ai-accent); transform:none; }
            .dark .ai-assistant-launcher.is-open .ai-assistant-launcher__icon { color: #111827; background: #fff; }
            .ai-assistant-panel { position: fixed; z-index: 150; right: 20px; bottom: 92px; display: flex; width:min(440px, calc(100vw - 32px)); height:min(720px, calc(100dvh - var(--app-shell-header-height, 79px) - 104px)); flex-direction: column; overflow: hidden; border: 1px solid var(--ai-border); border-radius: 20px; background: var(--theme-surface-base); color: var(--theme-header-text-color); box-shadow:0 16px 48px -12px rgba(15,23,42,.24); transform-origin:right bottom; }
            .ai-assistant-panel__header { display: flex; align-items: center; gap: 12px; padding: 14px 15px; border-bottom: 1px solid var(--ai-border); background: linear-gradient(135deg, color-mix(in srgb, var(--ai-accent) 9%, var(--theme-surface-base)) 0%, var(--theme-surface-base) 72%); }
            .ai-assistant-panel__body { min-height: 0; flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 13px; scrollbar-width: thin; }
            .ai-assistant-panel__body.is-chat { display: flex; flex-direction: column; overflow: hidden; padding: 0; }
            .ai-assistant-chat-feed { min-height: 0; flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 13px 13px 6px; scrollbar-width: thin; }
            .ai-assistant-chat-composer { position: relative; z-index: 2; flex: 0 0 auto; border-top: 1px solid var(--ai-border); padding: 10px 13px 13px; background: color-mix(in srgb, var(--theme-surface-base) 96%, var(--ai-accent) 4%); box-shadow: 0 -14px 30px -26px rgba(15,23,42,.65); }
            .ai-assistant-chat-composer__input { min-height:80px!important;max-height:140px!important;height:80px!important; resize: none; }
            .ai-assistant-chat-suggestions { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 1px; scrollbar-width: none; }
            .ai-assistant-chat-suggestions::-webkit-scrollbar { display: none; }
            .ai-assistant-chat-suggestions .ai-assistant-prompt { flex: 0 0 auto; }
            .ai-assistant-tabs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; padding: 4px; border: 1px solid var(--ai-border); border-radius: 12px; background: color-mix(in srgb, var(--theme-surface-muted) 74%, transparent); }
            .ai-assistant-tab { min-width: 0; border-radius: 9px; padding: 8px 4px; color: var(--theme-muted-text-color); font-size:12px;font-weight:600; }
            .ai-assistant-tab.is-active { color: var(--theme-header-text-color); background: var(--theme-surface-base); box-shadow: 0 7px 18px -16px rgba(15,23,42,.6); }
            .ai-assistant-card { border: 1px solid color-mix(in srgb, var(--theme-border-color) 58%, transparent); border-radius: 13px; background: var(--theme-surface-base); }
            .ai-assistant-card--hero { border-color: color-mix(in srgb, var(--ai-accent) 15%, transparent); background: linear-gradient(135deg, color-mix(in srgb, var(--ai-accent) 6%, var(--theme-surface-base)), var(--theme-surface-base) 68%); }
            .ai-assistant-card--focus { border-color: color-mix(in srgb, var(--ai-accent) 24%, transparent); box-shadow: 0 18px 42px -36px color-mix(in srgb, var(--ai-accent) 46%, transparent); }
            .ai-assistant-grid-card { min-width: 0; padding: 11px; border: 1px solid color-mix(in srgb, var(--theme-border-color) 54%, transparent); border-radius: 12px; background: color-mix(in srgb, var(--theme-surface-base) 97%, var(--ai-accent) 3%); }
            .ai-assistant-label { display: block; margin-bottom: 6px; color: var(--theme-muted-text-color); font-size: 9px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
            .ai-assistant-input,.ai-assistant-textarea,.ai-assistant-select { width: 100%; border: 1px solid var(--ai-border); border-radius: 10px; outline: none; background: color-mix(in srgb, var(--theme-surface-base) 97%, var(--ai-accent) 3%); color: var(--theme-header-text-color); }
            .ai-assistant-input,.ai-assistant-select { height: 41px; padding: 0 11px; }
            .ai-assistant-textarea { min-height: 92px; resize: vertical; padding: 10px 11px; line-height: 1.55; }
            .ai-assistant-input:focus,.ai-assistant-textarea:focus,.ai-assistant-select:focus { border-color: color-mix(in srgb, var(--ai-accent) 54%, transparent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--ai-accent) 10%, transparent); }
            .ai-assistant-button { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: 7px; border-radius: 10px; padding: 8px 11px; font-size: 11px; font-weight: 800; }
            .ai-assistant-button:disabled { cursor: wait; opacity: .56; }
            .ai-assistant-button--primary { color: #fff; background: #111827; }
            .dark .ai-assistant-button--primary { color: #111827; background: #fff; }
            .ai-assistant-button--accent { color: #fff; background: var(--ai-accent); }
            .ai-assistant-button--outline { border: 1px solid var(--ai-border); color: var(--theme-header-text-color); background: var(--theme-surface-base); }
            .ai-assistant-chip { position: relative; display: inline-flex; cursor: pointer; }
            .ai-assistant-chip input { position: absolute; opacity: 0; pointer-events: none; }
            .ai-assistant-chip span { display: inline-flex; min-height: 30px; align-items: center; padding: 5px 9px; border: 1px solid var(--ai-border); border-radius: 9px; color: var(--theme-muted-text-color); background: var(--theme-surface-base); font-size: 10px; font-weight: 700; }
            .ai-assistant-chip input:checked + span { border-color: color-mix(in srgb, var(--ai-accent) 38%, transparent); color: var(--ai-accent); background: color-mix(in srgb, var(--ai-accent) 8%, var(--theme-surface-base)); }
            .ai-assistant-prompt { display: inline-flex; align-items: center; gap: 6px; min-height: 29px; padding: 5px 8px; border: 1px solid color-mix(in srgb, var(--theme-border-color) 58%, transparent); border-radius: 9px; color: var(--theme-muted-text-color); background: color-mix(in srgb, var(--theme-surface-base) 96%, var(--ai-accent) 4%); font-size: 9px; font-weight: 750; text-align: left; }
            .ai-assistant-prompt:hover { border-color: color-mix(in srgb, var(--ai-accent) 30%, transparent); color: var(--theme-header-text-color); }
            .ai-assistant-status { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 4px 7px; color: var(--ai-accent); background: color-mix(in srgb, var(--ai-accent) 9%, var(--theme-surface-base)); font-size: 8px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
            .ai-assistant-failure { border-left: 3px solid #f59e0b; border-radius: 0 11px 11px 0; background: color-mix(in srgb, #f59e0b 4%, var(--theme-surface-base)); }
            .ai-assistant-message { max-width: 93%; padding: 10px 11px; border: 1px solid var(--ai-border); border-radius: 14px 14px 14px 5px; background: color-mix(in srgb, var(--theme-surface-base) 96%, var(--ai-accent) 4%); }
            .ai-assistant-message.is-user { margin-left: auto; color: #fff; border-color: transparent; border-radius: 14px 14px 5px 14px; background: #111827; }
            .dark .ai-assistant-message.is-user { color: #111827; background: #fff; }
            .ai-assistant-idea { width: 100%; padding: 11px; text-align: left; }
            .ai-assistant-idea:hover { border-color: color-mix(in srgb, var(--ai-accent) 30%, var(--theme-border-color)); background: color-mix(in srgb, var(--theme-surface-base) 96%, var(--ai-accent) 4%); }
            .ai-assistant-account { display: flex; cursor: pointer; align-items: center; gap: 9px; padding: 8px; border: 1px solid var(--ai-border); border-radius: 10px; }
            .ai-assistant-account:has(input:checked) { border-color: color-mix(in srgb, var(--ai-accent) 44%, transparent); background: color-mix(in srgb, var(--ai-accent) 7%, var(--theme-surface-base)); }
            .ai-assistant-account input { width: 16px; height: 16px; accent-color: var(--ai-accent); }
            .ai-assistant-mode { position: relative; cursor: pointer; }
            .ai-assistant-mode input { position: absolute; opacity: 0; }
            .ai-assistant-mode span { display: flex; min-height: 56px; flex-direction: column; justify-content: center; gap: 2px; padding: 8px; border: 1px solid var(--ai-border); border-radius: 10px; }
            .ai-assistant-mode input:checked + span { border-color: color-mix(in srgb, var(--ai-accent) 44%, transparent); background: color-mix(in srgb, var(--ai-accent) 7%, var(--theme-surface-base)); }
            @media (max-width:639px) { .ai-assistant-launcher { right:14px; bottom:14px; width:54px; height:54px; } .ai-assistant-panel { right:12px; bottom:82px; width:min(420px,calc(100vw - 24px)); max-width:calc(100vw - 24px); height:min(680px,calc(100dvh - var(--app-shell-header-height,79px) - 94px)); border-radius:18px; } }
            @media (max-width:420px) { .ai-assistant-panel { right:8px; bottom:78px; width:calc(100vw - 16px); height:min(680px,calc(100dvh - var(--app-shell-header-height,79px) - 90px)); } }
            @media (prefers-reduced-motion:reduce) { .ai-assistant-launcher::before, .ai-assistant-launcher .ai-assistant-launcher__icon { animation:none; } .ai-assistant-launcher, .ai-assistant-launcher .ai-assistant-launcher__icon { transition:none; } }
            .ai-assistant-launcher .ai-assistant-launcher__icon>i{position:absolute;inset:0;display:grid;width:100%;height:100%;margin:0;place-items:center;font-size:20px;line-height:1;}
            .ai-helper-enter{transition:opacity .22s ease,transform .22s cubic-bezier(.22,1,.36,1)}
            .ai-helper-leave{transition:opacity .15s ease,transform .15s ease}
            .ai-helper-hidden{opacity:0;transform:translateY(12px)}
            .ai-helper-visible{opacity:1;transform:translateY(0)}
            .ai-assistant-launcher{transition:background .15s ease,border-color .15s ease;box-shadow:0 5px 18px rgba(15,23,42,.14);backdrop-filter:none}.ai-assistant-launcher:hover{box-shadow:0 5px 18px rgba(15,23,42,.14)}
            .ai-helper-title{font-size:15px;font-weight:650;line-height:1.5}.ai-helper-subtitle{font-size:12px;margin-top:2px;color:var(--theme-muted-text-color)}.ai-helper-close{display:grid;place-items:center;width:32px;height:32px;border-radius:8px;font-size:18px;flex-shrink:0}.ai-helper-close:hover{background:var(--theme-surface-muted)}
            .ai-assistant-panel__header{padding:18px;background:var(--theme-surface-base)}.ai-assistant-chat-feed{padding:18px}.ai-helper-welcome{padding:12px 5px 18px}.ai-helper-welcome-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:color-mix(in srgb,var(--ai-accent) 8%,transparent);color:var(--ai-accent);font-size:19px;margin-bottom:14px}.ai-helper-welcome h3{font-size:23px;line-height:1.3;letter-spacing:-.6px;font-weight:650}.ai-helper-welcome p{font-size:13px;line-height:1.7;color:var(--theme-muted-text-color);margin-top:8px;max-width:340px}
            .ai-helper-starters{display:grid;gap:9px}.ai-helper-starters>button{display:flex;align-items:center;gap:12px;padding:13px;border:1px solid var(--ai-border);border-radius:12px;text-align:left;background:var(--theme-surface-base)}.ai-helper-starters>button:hover{border-color:var(--ai-accent);background:color-mix(in srgb,var(--ai-accent) 3%,var(--theme-surface-base))}.ai-helper-task-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:color-mix(in srgb,var(--ai-accent) 7%,transparent);color:var(--ai-accent);flex-shrink:0}.ai-helper-starters>button>span:nth-child(2){min-width:0;flex:1}.ai-helper-starters strong{display:block;font-size:13px;font-weight:600}.ai-helper-starters small{display:block;font-size:11px;line-height:1.5;color:var(--theme-muted-text-color);margin-top:3px}.ai-helper-starters>button>i{color:var(--theme-muted-text-color);font-size:11px}
            .ai-helper-details{margin-top:18px;border-top:1px solid var(--ai-border)}.ai-helper-details>summary{display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;list-style:none;padding:14px 2px;font-size:12px;color:var(--theme-muted-text-color)}.ai-helper-details>summary::-webkit-details-marker{display:none}.ai-helper-details>summary>span{display:flex;gap:8px;align-items:center}.ai-helper-details[open]>summary>i{transform:rotate(180deg)}.ai-helper-details-body{padding-bottom:8px}
            .ai-assistant-chat-composer{padding:14px 18px 16px;background:var(--theme-surface-base);box-shadow:none}.ai-helper-composer-meta{display:flex;justify-content:space-between;gap:10px;align-items:center;font-size:11px;color:var(--theme-muted-text-color)}.ai-helper-composer-meta label{font-size:12px;font-weight:600;color:var(--theme-header-text-color)}.ai-helper-cost{font-size:10px;line-height:1.6;color:var(--theme-muted-text-color);margin-top:9px}.ai-assistant-chat-composer__input{font-size:13px}.ai-assistant-panel .ai-assistant-message{font-size:13px}.ai-assistant-panel [class~="text-[8px]"],.ai-assistant-panel [class~="text-[9px]"]{font-size:11px}.ai-assistant-panel [class~="text-[10px]"],.ai-assistant-panel [class~="text-[11px]"]{font-size:12px}.ai-assistant-label{font-size:10px;letter-spacing:.04em;text-transform:none}.ai-assistant-button{font-size:12px;font-weight:600}.ai-assistant-chip span{font-size:11px}.ai-assistant-status{font-size:9px;letter-spacing:.02em;text-transform:none}
            @media(prefers-reduced-motion:reduce){.ai-assistant-panel{transition:none!important}}@media(max-width:420px){.ai-assistant-panel__header{padding:14px}.ai-assistant-chat-feed{padding:14px}.ai-helper-welcome h3{font-size:21px}.ai-assistant-chat-composer{padding:12px 14px}.ai-assistant-tab{font-size:11px}}
            .ai-assistant-chip input:focus-visible+span,.ai-assistant-mode input:focus-visible+span{outline:2px solid var(--ai-accent);outline-offset:2px}.ai-helper-close:focus-visible,.ai-helper-starters>button:focus-visible{outline:2px solid var(--ai-accent);outline-offset:2px}
        </style>

        <button
            x-cloak
            type="button"
            class="ai-assistant-launcher"
            x-ref="launcher"
            aria-controls="workspace-ai-helper"
            x-bind:class="{ 'is-open': open }"
            x-on:click="toggle()"
            x-bind:aria-expanded="open.toString()"
            x-bind:aria-label="open ? '{{ __('Close AI Assistant') }}' : '{{ __('Open AI Assistant') }}'"
            x-bind:title="open ? '{{ __('Close AI Assistant') }}' : '{{ __('Workspace AI Assistant') }}'"
        >
            <span class="ai-assistant-launcher__icon">
                <i x-show="!open" class="fa-light fa-sparkles"></i>
                <i x-cloak x-show="open" class="fa-light fa-xmark text-lg"></i>
            </span>
        </button>

        <section
            x-cloak
            x-show="open"
            x-transition:enter="ai-helper-enter"
            x-transition:enter-start="ai-helper-hidden"
            x-transition:enter-end="ai-helper-visible"
            x-transition:leave="ai-helper-leave"
            x-transition:leave-start="ai-helper-visible"
            x-transition:leave-end="ai-helper-hidden"
            id="workspace-ai-helper"
            role="dialog"
            aria-modal="false"
            class="ai-assistant-panel"
            aria-label="{{ __('Workspace AI Assistant') }}"
        >
            <header class="ai-assistant-panel__header">
                <span class="ai-assistant-launcher__icon"><i class="fa-light fa-sparkles"></i></span>
                <div class="min-w-0 flex-1">
                    <h2 class="ai-helper-title">{{ __('AI helper') }}</h2>
                    <p class="ai-helper-subtitle">{{ __('A little help with your next post') }}</p>
                </div>
                <button type="button" class="ai-helper-close" x-on:click="close()" aria-label="{{ __('Close helper panel') }}"><i class="fa-light fa-xmark" aria-hidden="true"></i></button>
            </header>

            <div class="px-3 pt-3">
                <nav class="ai-assistant-tabs" aria-label="{{ __('AI helper sections') }}">
                    @foreach (['assistant' => ['fa-sparkles', __('Chat')], 'ideas' => ['fa-lightbulb', __('Ideas')], 'drafts' => ['fa-file-pen', __('Drafts')], 'ready' => ['fa-circle-check', __('Posts')]] as $tabKey => [$tabIcon, $tabLabel])
                        <button type="button" class="ai-assistant-tab {{ $tab === $tabKey ? 'is-active' : '' }}" aria-current="{{ $tab === $tabKey ? 'page' : 'false' }}" wire:click="setTab('{{ $tabKey }}')" wire:loading.attr="disabled" wire:target="setTab"><i class="fa-light {{ $tabIcon }} mr-1"></i>{{ $tabLabel }}</button>
                    @endforeach
                </nav>
            </div>

            <div class="ai-assistant-panel__body {{ $tab === 'assistant' ? 'is-chat' : '' }}">
                @if ($panelMessage !== '')
                    <div
                        x-data="{ noticeVisible: true }"
                        @if ($panelMessageTone === 'neutral') x-init="setTimeout(() => { noticeVisible = false; $wire.clearPanelMessage(); }, 5000)" @endif
                        x-show="noticeVisible"
                        @class([
                            'mb-3 rounded-[11px] border px-3 py-2.5 text-[10px] font-semibold leading-5',
                            'border-amber-300/50 bg-amber-50/70 text-amber-800 dark:bg-amber-950/20 dark:text-amber-200' => $panelMessageTone === 'warning',
                            'border-emerald-300/50 bg-emerald-50/70 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-200' => $panelMessageTone === 'success',
                            'border-slate-200/80 bg-slate-50/80 text-slate-600 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300' => $panelMessageTone === 'neutral',
                        ])
                    >{{ $panelMessage }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-3 rounded-[11px] border border-red-300/50 bg-red-50/70 px-3 py-2.5 text-[10px] leading-5 text-red-700 dark:bg-red-950/20 dark:text-red-200">{{ $errors->first() }}</div>
                @endif

                @if ($tab === 'assistant')
                    <div class="ai-assistant-chat-feed">
                    @if ($assistantMessages === [])
                        <div class="ai-helper-welcome">
                            <span class="ai-helper-welcome-icon"><i class="fa-light fa-sparkles" aria-hidden="true"></i></span>
                            <h3>{{ __('What would you like to do?') }}</h3>
                            <p>{{ __('Pick a starting point, or ask a question below. No special prompts needed.') }}</p>
                        </div>
                        <div class="ai-helper-starters">
                            <button type="button" wire:click="setTab('ideas')"><span class="ai-helper-task-icon"><i class="fa-light fa-pen-to-square"></i></span><span><strong>{{ __('Create a post') }}</strong><small>{{ __('Start with your business, product or idea') }}</small></span><i class="fa-light fa-angle-right"></i></button>
                            <button type="button" wire:click="useQuickPrompt('queue')"><span class="ai-helper-task-icon"><i class="fa-light fa-calendar-days"></i></span><span><strong>{{ __('Check upcoming posts') }}</strong><small>{{ __('See what is planned and what needs attention') }}</small></span><i class="fa-light fa-angle-right"></i></button>
                            <button type="button" wire:click="useQuickPrompt('failures')"><span class="ai-helper-task-icon"><i class="fa-light fa-circle-question"></i></span><span><strong>{{ __('Help with a failed post') }}</strong><small>{{ __('Understand the problem and the next step') }}</small></span><i class="fa-light fa-angle-right"></i></button>
                        </div>
                    @endif
                    @if ($assistantMessages !== [])
                        <div class="mt-3 space-y-2">
                            @foreach ($assistantMessages as $messageIndex => $message)
                                <div class="ai-assistant-message {{ ($message['role'] ?? '') === 'user' ? 'is-user' : '' }}" wire:key="assistant-message-{{ $messageIndex }}">
                                    <p class="text-[10px] font-extrabold uppercase tracking-[.1em] opacity-70">{{ ($message['role'] ?? '') === 'user' ? __('You') : __('Assistant') }}</p>
                                    <p class="mt-1.5 whitespace-pre-line text-[11px] leading-5">{{ $message['text'] ?? '' }}</p>
                                    @foreach ((array)($message['highlights'] ?? []) as $highlight)<p class="mt-2 flex gap-2 text-[10px] leading-4"><i class="fa-light fa-circle-check mt-0.5" style="color:var(--ai-accent);"></i><span>{{ $highlight }}</span></p>@endforeach
                                    @if (!empty($message['actions']))
                                        <div class="mt-2 flex flex-wrap gap-1.5">@foreach ((array)$message['actions'] as $action) @if(!empty($action['url']))<a href="{{ $action['url'] }}" class="ai-assistant-button ai-assistant-button--outline !min-h-8 !py-1.5" title="{{ $action['reason'] ?? '' }}">{{ $action['label'] }} <i class="fa-light fa-arrow-up-right-from-square"></i></a>@endif @endforeach</div>
                                    @endif
                                    @if (!empty($message['safety_note']))<p class="mt-2 border-t pt-2 text-[9px] leading-4 opacity-70" style="border-color:var(--ai-border);"><i class="fa-light fa-shield-check mr-1"></i>{{ $message['safety_note'] }}</p>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <details class="ai-helper-details" wire:key="assistant-workspace-details"><summary><span><i class="fa-light fa-grid-2" aria-hidden="true"></i>{{ __('My workspace & tools') }}</span><i class="fa-light fa-chevron-down" aria-hidden="true"></i></summary><div class="ai-helper-details-body">
                    <div class="ai-assistant-card ai-assistant-card--hero overflow-hidden">
                        <div class="flex items-start gap-3 p-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[11px]" style="color:var(--ai-accent);background:color-mix(in srgb,var(--ai-accent) 9%,transparent);"><i class="fa-light fa-location-dot"></i></span>
                            <div class="min-w-0"><p class="text-xs font-extrabold">{{ $pageGuide['title'] ?? __('Workspace overview') }}</p><p class="mt-1 text-[10px] leading-5" style="color:var(--theme-muted-text-color);">{{ $pageGuide['description'] ?? '' }}</p></div>
                        </div>
                        @if (!empty($pageGuide['tips']))
                            <div class="border-t px-3 py-2.5" style="border-color:var(--ai-border);">
                                @foreach ((array)$pageGuide['tips'] as $tip)<p class="flex gap-2 py-1 text-[10px] leading-4"><i class="fa-light fa-circle-check mt-0.5" style="color:var(--ai-accent);"></i><span>{{ $tip }}</span></p>@endforeach
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 grid grid-cols-4 gap-2">
                        @foreach ([
                            [__('Channels'), data_get($workspaceSummary,'channels.connected',0), 'fa-share-nodes'],
                            [__('Drafts'), data_get($workspaceSummary,'publishing.drafts',0), 'fa-file-pen'],
                            [__('Queue'), data_get($workspaceSummary,'publishing.queue',0), 'fa-list-check'],
                            [__('Failed'), data_get($workspaceSummary,'publishing.failed',0), 'fa-triangle-exclamation'],
                        ] as [$label,$value,$icon])
                            <div class="ai-assistant-grid-card"><i class="fa-light {{ $icon }} text-[11px]" style="color:var(--ai-accent);"></i><strong class="mt-2 block text-lg leading-none">{{ number_format((int)$value) }}</strong><span class="mt-1 block truncate text-[8px] font-bold uppercase tracking-[.08em]" style="color:var(--theme-muted-text-color);">{{ $label }}</span></div>
                        @endforeach
                    </div>

                    @if (!empty(data_get($workspaceSummary,'publishing.recent_failures')))
                        <div class="ai-assistant-card mt-3 p-3">
                            <div class="flex items-center justify-between"><div><p class="text-xs font-extrabold">{{ __('Publishing issues to review') }}</p><p class="mt-1 text-[9px]" style="color:var(--theme-muted-text-color);">{{ __('Find out what happened and how to fix it') }}</p></div><span class="ai-assistant-status">{{ count((array)data_get($workspaceSummary,'publishing.recent_failures',[])) }} {{ __('recent') }}</span></div>
                            @foreach (array_slice((array)data_get($workspaceSummary,'publishing.recent_failures',[]), 0, 3) as $failure)
                                <article class="ai-assistant-failure mt-2.5 px-3 py-2.5">
                                    <div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate text-[10px] font-extrabold">{{ $failure['title'] }}</p><p class="mt-0.5 truncate text-[9px]" style="color:var(--theme-muted-text-color);">{{ $failure['account'] }} · #{{ $failure['post_id'] }}@if($failure['code']) · {{ __('Code') }} {{ $failure['code'] }}@endif</p></div><i class="fa-light fa-triangle-exclamation mt-0.5 text-amber-500"></i></div>
                                    <p class="mt-2 text-[10px] leading-4" style="color:var(--theme-muted-text-color);">{{ $failure['summary'] }}</p>
                                    @if (!empty($failure['steps'][0]))<p class="mt-2 flex gap-2 text-[9px] leading-4"><i class="fa-light fa-arrow-right mt-0.5 text-amber-500"></i><span>{{ $failure['steps'][0] }}</span></p>@endif
                                    <div class="mt-2 flex flex-wrap gap-1.5"><button type="button" class="ai-assistant-button ai-assistant-button--outline !min-h-8 !py-1.5" wire:click="prepareFailureQuestion({{ (int)$failure['post_id'] }})"><i class="fa-light fa-sparkles"></i>{{ __('Help me fix this') }}</button>@if($failure['edit_url'])<a href="{{ $failure['edit_url'] }}" class="ai-assistant-button ai-assistant-button--outline !min-h-8 !py-1.5"><i class="fa-light fa-arrow-up-right-from-square"></i>{{ __('Open post') }}</a>@endif</div>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    <div class="ai-assistant-card mt-3 p-3">
                        <div class="flex items-center justify-between"><div><p class="text-xs font-extrabold">{{ __('Find workspace content') }}</p><p class="mt-1 text-[10px]" style="color:var(--theme-muted-text-color);">{{ __('Prompts, media, posts, and campaigns · free') }}</p></div><i class="fa-light fa-magnifying-glass" style="color:var(--ai-accent);"></i></div>
                        <div class="mt-2 grid grid-cols-[minmax(0,1fr)_42px] gap-2"><input type="search" class="ai-assistant-input" wire:model="workspaceSearch" placeholder="{{ __('Search the workspace...') }}"><button type="button" class="ai-assistant-button ai-assistant-button--primary !px-0" wire:click="searchWorkspace" wire:loading.attr="disabled" wire:target="searchWorkspace"><i class="fa-light fa-arrow-right"></i></button></div>
                        @if ($searchResults !== [])<div class="mt-2 space-y-1.5">@foreach($searchResults as $result)<a href="{{ $result['route'] ?: '#' }}" class="block rounded-[10px] border px-3 py-2" style="border-color:var(--ai-border);"><span class="flex items-center justify-between gap-2"><strong class="truncate text-[10px]">{{ $result['title'] }}</strong><small class="uppercase" style="color:var(--theme-muted-text-color);">{{ $result['type'] }}</small></span><span class="mt-1 block line-clamp-2 text-[9px] leading-4" style="color:var(--theme-muted-text-color);">{{ $result['excerpt'] }}</span></a>@endforeach</div>@endif
                    </div>

                    <div class="ai-assistant-card mt-3 p-3">
                        <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-extrabold">{{ __('More things you can do') }}</p><p class="mt-1 text-[10px] leading-5" style="color:var(--theme-muted-text-color);">{{ __('Open a tool to create and review your content.') }}</p></div><i class="fa-light fa-shield-check" style="color:var(--ai-accent);"></i></div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach ([
                                ['campaigns', __('Campaign & calendar'), __('Preview before save')],
                                ['ai_image', __('Create image'), $costs['image'].' '.__('credits')],
                                ['ai_video', __('Create video'), __('from :cost credits', ['cost' => $costs['video']])],
                                ['link_bio', __('Link Bio draft'), __('Draft only')],
                            ] as [$workflowKey, $workflowLabel, $workflowMeta])
                                @if ($workflow = $navigationByKey->get($workflowKey))
                                    <a href="{{ $workflow['url'] }}" class="ai-assistant-grid-card block"><strong class="block text-[10px]">{{ $workflowLabel }}</strong><span class="mt-1 block text-[8px]" style="color:var(--theme-muted-text-color);">{{ $workflowMeta }}</span></a>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2">@foreach($navigation as $item)<a href="{{ $item['url'] }}" class="ai-assistant-grid-card flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-[9px]" style="color:var(--ai-accent);background:color-mix(in srgb,var(--ai-accent) 8%,transparent);"><i class="fa-light {{ $item['icon'] }}"></i></span><span class="truncate text-[10px] font-extrabold">{{ $item['label'] }}</span></a>@endforeach</div>

                    @if($recentAudit->isNotEmpty())<div class="ai-assistant-card mt-3 p-3"><p class="text-xs font-extrabold">{{ __('Recent assistant activity') }}</p>@foreach($recentAudit as $log)<div class="mt-2 flex items-center justify-between gap-3 border-t pt-2 text-[9px]" style="border-color:var(--ai-border);"><span class="truncate"><strong>{{ str($log->action_key)->replace('_',' ')->headline() }}</strong> · {{ str($log->status)->headline() }}</span><span class="shrink-0" style="color:var(--theme-muted-text-color);">{{ $log->created_at?->diffForHumans() }}</span></div>@endforeach</div>@endif
                    </div></details>
                    </div>

                    <div class="ai-assistant-chat-composer">
                        <div class="ai-helper-composer-meta"><label for="ai-helper-question">{{ __('Ask me anything') }}</label><span title="{{ $planName }}">{{ $remainingLabel }} {{ __('credits left') }}</span></div>
                        <div class="mt-2 grid grid-cols-[minmax(0,1fr)_42px] items-end gap-2">
                            <textarea wire:key="assistant-question-{{ md5($question) }}" id="ai-helper-question" x-ref="question" class="ai-assistant-textarea ai-assistant-chat-composer__input" wire:model="question" placeholder="{{ __('For example: What should I post this week?') }}"></textarea>
                            <button type="button" class="ai-assistant-button ai-assistant-button--accent !min-h-[42px] !px-0" wire:click="askAssistant" wire:loading.attr="disabled" wire:target="askAssistant" aria-label="{{ __('Ask assistant') }}" title="{{ __('Ask assistant') }} · {{ $costs['assistant'] }}"><i class="fa-light fa-spinner-third fa-spin" wire:loading wire:target="askAssistant"></i><i class="fa-light fa-arrow-up" wire:loading.remove wire:target="askAssistant"></i></button>
                        </div>
                        <p class="ai-helper-cost">{{ __('Estimated cost per answer: :cost credit(s).', ['cost' => $costs['assistant']]) }} {{ __('You review before publishing.') }}</p>
                    </div>
                @else
                    @if (!$activeIdea)
                        @if ($tab === 'ideas')
                            <div class="ai-assistant-card p-3">
                                <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-extrabold">{{ __('Start with an idea') }}</p><p class="mt-1 text-[10px] leading-5" style="color:var(--theme-muted-text-color);">{{ __('Save an idea for free. AI suggestions cost :cost credit(s).', ['cost' => $costs['assistant']]) }}</p></div><span class="grid h-9 w-9 place-items-center rounded-[10px]" style="color:var(--ai-accent);background:color-mix(in srgb,var(--ai-accent) 9%,transparent);"><i class="fa-light fa-lightbulb"></i></span></div>
                                <label class="ai-assistant-label mt-3" for="assistant-title">{{ __('Idea title') }} · {{ __('optional') }}</label><input id="assistant-title" class="ai-assistant-input" wire:model="ideaTitle" placeholder="{{ __('Product launch story') }}">
                                <label class="ai-assistant-label mt-3" for="assistant-brief">{{ __('What would you like to post about?') }}</label><textarea id="assistant-brief" class="ai-assistant-textarea" wire:model="ideaBrief" placeholder="{{ __('Example: Introduce our new coffee blend to local customers.') }}"></textarea>
                                <span class="ai-assistant-label mt-3">{{ __('Where will you post?') }}</span><div class="flex flex-wrap gap-1.5">@foreach($platformOptions as $platformKey => $platformLabel)<label class="ai-assistant-chip"><input type="checkbox" value="{{ $platformKey }}" wire:model="ideaPlatforms"><span>{{ $platformLabel }}</span></label>@endforeach</div>
                                <div class="mt-3 grid grid-cols-2 gap-2"><button type="button" class="ai-assistant-button ai-assistant-button--outline" wire:click="createIdea"><i class="fa-light fa-bookmark"></i>{{ __('Save idea') }}</button><button type="button" class="ai-assistant-button ai-assistant-button--accent" wire:click="suggestIdeas" wire:loading.attr="disabled" wire:target="suggestIdeas"><i class="fa-light fa-spinner-third fa-spin" wire:loading wire:target="suggestIdeas"></i><i class="fa-light fa-sparkles" wire:loading.remove wire:target="suggestIdeas"></i>{{ __('Get 5 ideas') }}</button></div>
                            </div>
                        @endif
                        <div class="mt-3 flex items-center justify-between"><p class="ai-assistant-label !mb-0">{{ __('Your saved items') }}</p><span class="text-[9px]" style="color:var(--theme-muted-text-color);">{{ $ideas->count() }}</span></div>
                        <div class="mt-2 space-y-2">@forelse($ideas as $idea)<button type="button" class="ai-assistant-card ai-assistant-idea" wire:click="selectIdea({{ $idea->id }})" wire:key="assistant-idea-{{ $idea->id }}"><span class="flex items-start gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-[10px]" style="color:var(--ai-accent);background:color-mix(in srgb,var(--ai-accent) 8%,transparent);"><i class="fa-light {{ $idea->status === 'idea' ? 'fa-lightbulb' : ($idea->status === 'draft' ? 'fa-pen-to-square' : 'fa-circle-check') }}"></i></span><span class="min-w-0 flex-1"><span class="flex items-center justify-between gap-2"><strong class="truncate text-[11px]">{{ $idea->title }}</strong><span class="ai-assistant-status">{{ str($idea->status)->replace('_',' ')->headline() }}</span></span><span class="mt-1 block line-clamp-2 text-[10px] leading-4" style="color:var(--theme-muted-text-color);">{{ $idea->brief }}</span></span></span></button>@empty<div class="ai-assistant-card px-4 py-8 text-center"><i class="fa-light fa-inbox text-xl" style="color:var(--theme-muted-text-color);"></i><p class="mt-2 text-xs font-bold">{{ $tab === 'drafts' ? __('No drafts yet') : ($tab === 'ready' ? __('No posts yet') : __('No saved ideas yet')) }}</p><p class="mt-2 text-xs leading-5" style="color:var(--theme-muted-text-color);">{{ $tab === 'ideas' ? __('Describe your idea above to get started.') : __('Start with an idea, then create a draft and review it before posting.') }}</p>@if($tab !== 'ideas')<button type="button" class="ai-assistant-button ai-assistant-button--outline mt-3" wire:click="setTab('ideas')">{{ __('Start an idea') }}</button>@endif</div>@endforelse</div>
                    @else
                        <div class="flex items-center justify-between"><button type="button" class="ai-assistant-button ai-assistant-button--outline !min-h-8 !py-1.5" wire:click="newIdea"><i class="fa-light fa-arrow-left"></i>{{ __('All items') }}</button><span class="ai-assistant-status">{{ str($activeIdea->status)->replace('_',' ')->headline() }}</span></div>
                        <div class="ai-assistant-card mt-3 overflow-hidden">
                            <div class="border-b p-3" style="border-color:var(--ai-border);"><p class="text-xs font-extrabold">{{ $activeIdea->title }}</p><p class="mt-1 text-[10px] leading-5" style="color:var(--theme-muted-text-color);">{{ $activeIdea->brief }}</p><div class="mt-2 flex flex-wrap gap-1">@foreach((array)$activeIdea->platforms as $platform)<span class="ai-assistant-status">{{ $platformOptions[$platform] ?? str($platform)->headline() }}</span>@endforeach</div></div>
                            @if(empty($activeIdea->variants))
                                <div class="p-3 text-center"><span class="mx-auto grid h-10 w-10 place-items-center rounded-[11px]" style="color:var(--ai-accent);background:color-mix(in srgb,var(--ai-accent) 9%,transparent);"><i class="fa-light fa-file-pen"></i></span><p class="mt-2 text-xs font-extrabold">{{ __('Turn this idea into posts') }}</p><p class="mt-1 text-[10px] leading-5" style="color:var(--theme-muted-text-color);">{{ __('AI drafts cost :cost credit(s). You can review them before posting.', ['cost' => $costs['caption']]) }}</p><button type="button" class="ai-assistant-button ai-assistant-button--accent mt-3" wire:click="generatePosts" wire:loading.attr="disabled" wire:target="generatePosts"><i class="fa-light fa-spinner-third fa-spin" wire:loading wire:target="generatePosts"></i><i class="fa-light fa-sparkles" wire:loading.remove wire:target="generatePosts"></i>{{ __('Create drafts') }}</button></div>
                            @else
                                <div class="space-y-3 p-3">@foreach((array)$activeIdea->variants as $index => $variant)<div wire:key="variant-{{ $activeIdea->id }}-{{ $index }}"><label class="ai-assistant-label" for="variant-{{ $index }}">{{ $platformOptions[$variant['platform'] ?? ''] ?? str($variant['platform'] ?? 'Post')->headline() }}</label><textarea id="variant-{{ $index }}" class="ai-assistant-textarea !min-h-[128px] text-xs" wire:model="variantTexts.{{ $index }}"></textarea></div>@endforeach<div class="grid grid-cols-2 gap-2"><button type="button" class="ai-assistant-button ai-assistant-button--outline" wire:click="saveDraftChanges"><i class="fa-light fa-floppy-disk"></i>{{ __('Save edits') }}</button>@if($activeIdea->status !== 'approved')<button type="button" class="ai-assistant-button ai-assistant-button--primary" wire:click="approveIdea"><i class="fa-light fa-check"></i>{{ __('Approve') }}</button>@else<span class="ai-assistant-button border border-emerald-300/50 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-300"><i class="fa-light fa-badge-check"></i>{{ __('Approved') }}</span>@endif</div></div>
                            @endif
                        </div>

                        @if(!empty($activeIdea->variants))
                            <div class="ai-assistant-card mt-3 p-3"><div class="flex items-center justify-between"><div><p class="text-xs font-extrabold">{{ __('Review and improve') }}</p><p class="mt-1 text-[10px]" style="color:var(--theme-muted-text-color);">{{ __('Tool credit only; no assistant chat charge') }}</p></div><i class="fa-light fa-wand-magic-sparkles" style="color:var(--ai-accent);"></i></div><div class="mt-2 grid grid-cols-[minmax(0,1fr)_auto] gap-2"><select class="ai-assistant-select" wire:model="contentTool"><option value="review">{{ __('Review') }} · {{ $costs['review'] }}</option><option value="repurpose">{{ __('Repurpose') }} · {{ $costs['repurpose'] }}</option><option value="best_time">{{ __('Best time') }} · 0</option></select><button type="button" class="ai-assistant-button ai-assistant-button--accent" wire:click="runContentTool" wire:loading.attr="disabled" wire:target="runContentTool"><i class="fa-light fa-play"></i>{{ __('Run') }}</button></div>
                                @if($toolResult !== [])<div class="mt-3 rounded-[11px] border p-3 text-[10px] leading-5" style="border-color:var(--ai-border);">@if(($toolResult['type'] ?? '') === 'review')<div class="flex items-center justify-between"><strong>{{ $toolResult['verdict'] ?? __('Review complete') }}</strong><span class="ai-assistant-status">{{ (int)($toolResult['score'] ?? 0) }}/100</span></div>@foreach((array)($toolResult['fixes'] ?? []) as $fix)<p class="mt-1 flex gap-2"><i class="fa-light fa-wrench mt-1" style="color:var(--ai-accent);"></i><span>{{ $fix }}</span></p>@endforeach @elseif(($toolResult['type'] ?? '') === 'repurpose')<p class="font-bold">{{ $toolResult['strategy'] ?? '' }}</p>@foreach((array)($toolResult['items'] ?? []) as $item)<div class="mt-2 border-t pt-2" style="border-color:var(--ai-border);"><strong>{{ str($item['target'] ?? 'Post')->headline() }}</strong><p class="mt-1 whitespace-pre-line">{{ $item['caption'] ?? '' }}</p></div>@endforeach @else @foreach((array)($toolResult['items'] ?? []) as $item)<div class="mt-1 flex items-center justify-between gap-2"><strong>{{ $item['label'] ?? '' }}</strong><span>{{ (int)($item['confidence'] ?? 0) }}%</span></div>@endforeach @endif</div>@endif
                            </div>
                        @endif

                        @if(in_array($activeIdea->status,['approved','pending_approval','scheduled','published','failed'],true))
                            <div class="ai-assistant-card mt-3 p-3"><div class="flex items-start justify-between"><div><p class="text-xs font-extrabold">{{ __('Preview publishing action') }}</p><p class="mt-1 text-[10px] leading-5" style="color:var(--theme-muted-text-color);">{{ __('Confirm accounts and action. Draft is the safe default.') }}</p></div><i class="fa-light fa-shield-check" style="color:var(--ai-accent);"></i></div>
                                <span class="ai-assistant-label mt-3">{{ __('Connected accounts') }}</span><div class="space-y-1.5">@forelse($accounts as $account)<label class="ai-assistant-account"><input type="checkbox" value="{{ $account->id }}" wire:model="selectedAccountIds"><span class="grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-[9px]" style="color:var(--ai-accent);background:color-mix(in srgb,var(--ai-accent) 8%,transparent);">@if($account->avatar_url)<img src="{{ $account->avatar_url }}" alt="" class="h-full w-full object-cover">@else<i class="fa-light fa-share-nodes"></i>@endif</span><span class="min-w-0 flex-1"><strong class="block truncate text-[10px]">{{ $account->display_name ?: $account->username ?: str($account->provider_key)->headline() }}</strong><small class="block truncate text-[8px]" style="color:var(--theme-muted-text-color);">{{ str($account->provider_key)->replace('_',' ')->headline() }}</small></span></label>@empty<div class="rounded-[10px] border border-dashed px-3 py-5 text-center text-[10px]" style="border-color:var(--ai-border);color:var(--theme-muted-text-color);">{{ __('No active publishing accounts.') }}</div>@endforelse</div>
                                <span class="ai-assistant-label mt-3">{{ __('Action') }}</span><div class="grid gap-1.5" style="grid-template-columns:repeat({{ $canPublishDirectly ? 3 : 2 }},minmax(0,1fr));"><label class="ai-assistant-mode"><input type="radio" value="draft" wire:model.live="publishMode"><span><strong class="text-[9px]">{{ __('Save draft') }}</strong><small class="text-[8px]" style="color:var(--theme-muted-text-color);">{{ __('No delivery') }}</small></span></label><label class="ai-assistant-mode"><input type="radio" value="schedule" wire:model.live="publishMode"><span><strong class="text-[9px]">{{ __('Schedule') }}</strong><small class="text-[8px]" style="color:var(--theme-muted-text-color);">{{ $canPublishDirectly ? __('Calendar') : __('Needs approval') }}</small></span></label>@if($canPublishDirectly)<label class="ai-assistant-mode"><input type="radio" value="publish" wire:model.live="publishMode"><span><strong class="text-[9px]">{{ __('Publish now') }}</strong><small class="text-[8px] text-red-500">{{ __('Live action') }}</small></span></label>@endif</div>
                                @if($publishMode === 'schedule')<label class="ai-assistant-label mt-3" for="assistant-schedule">{{ __('Schedule date and time') }}</label><input id="assistant-schedule" type="datetime-local" class="ai-assistant-input" wire:model="scheduleAt">@endif
                                @if($publishMode === 'publish')<label class="mt-3 flex cursor-pointer gap-2 rounded-[10px] border border-red-300/50 bg-red-50/70 px-3 py-2.5 text-[10px] leading-5 text-red-700 dark:bg-red-950/20 dark:text-red-200"><input type="checkbox" class="mt-1" wire:model="confirmPublish"><span><strong>{{ __('Final confirmation') }}:</strong> {{ __('publish approved copy to selected live accounts now.') }}</span></label>@endif
                                <button type="button" class="ai-assistant-button ai-assistant-button--primary mt-3 w-full" wire:click="executePublishing" wire:loading.attr="disabled" wire:target="executePublishing" @disabled($accounts->isEmpty())><i class="fa-light fa-spinner-third fa-spin" wire:loading wire:target="executePublishing"></i><i class="fa-light {{ $publishMode === 'draft' ? 'fa-file-pen' : ($publishMode === 'schedule' ? 'fa-calendar-clock' : 'fa-paper-plane-top') }}" wire:loading.remove wire:target="executePublishing"></i>{{ $publishMode === 'draft' ? __('Confirm and create drafts') : ($publishMode === 'schedule' ? __('Confirm schedule action') : __('Final confirm and publish')) }}</button>
                                @if($publishingRoute)<a href="{{ $publishingRoute }}" class="mt-2 flex items-center justify-center gap-2 py-1 text-[9px] font-bold" style="color:var(--theme-muted-text-color);">{{ __('Open full Publishing workspace') }} <i class="fa-light fa-arrow-up-right-from-square"></i></a>@endif
                            </div>
                        @endif
                    @endif
                @endif
            </div>
        </section>
    @endif
</div>
