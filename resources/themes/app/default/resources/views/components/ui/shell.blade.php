@props([
    'title' => null,
    'shellArea' => null,
    'fullWorkspace' => false,
    'fullWorkspacePaddingBottom' => true,
    'showLoadingBackdrop' => true,
])

@php
    $isBoxedLayout = theme_setting('layout_width', 'app', 'full') === 'boxed';
    $sidebarMobileIcons = [
        'dashboard' => 'fa-light fa-house',
        'blogs' => 'fa-light fa-rss',
        'faq' => 'fa-light fa-messages-question',
        'support' => 'fa-light fa-life-ring',
        'mail' => 'fa-light fa-envelopes-bulk',
        'notification' => 'fa-light fa-bell',
        'proxy' => 'fa-light fa-hard-drive',
        'ai-report' => 'fa-light fa-chart-mixed',
        'ai-template' => 'fa-light fa-brain-circuit',
        'plans' => 'fa-light fa-box-open',
        'money' => 'fa-light fa-coins',
        'coupon' => 'fa-light fa-ticket-percent',
        'affiliate' => 'fa-light fa-handshake-angle',
        'users' => 'fa-light fa-users',
        'user-report' => 'fa-light fa-chart-user',
        'themes' => 'fa-light fa-swatchbook',
        'settings' => 'fa-light fa-sliders',
    ];
    $shellArea = in_array($shellArea, ['user', 'admin'], true)
        ? $shellArea
        : (request()->routeIs('portal.*') ? 'user' : 'admin');
    $sidebarSections = sidebar_sections($shellArea);
    $headerItemsStart = header_items($shellArea, 'start');
    $headerItemsPrimaryNav = header_items($shellArea, 'primary-nav');
    $headerItemsCenter = header_items($shellArea, 'center');
    $headerItemsEnd = header_items($shellArea, 'end');
    $dashboardSwitch = $shellArea === 'user'
        ? (auth()->user()?->canAccessAdmin() ? ['label' => 'Admin Dashboard', 'route' => route('dashboard')] : null)
        : ['label' => 'User Dashboard', 'route' => route('portal.dashboard')];
    $sidebarHomeRoute = $shellArea === 'user'
        ? route('portal.dashboard')
        : route('dashboard');
    $sidebarPlanCard = null;
    $impersonatorId = session('impersonator_id');
    $impersonatorName = session('impersonator_name');
    $isImpersonating = filled($impersonatorId);
    $headerWorkspaceTeams = collect();
    $headerCurrentWorkspaceTeam = null;

    if ($shellArea === 'user' && auth()->check()) {
        $sidebarUser = auth()->user();
        $sidebarCreditSummary = $sidebarUser?->creditSummary() ?? ['limit' => null, 'used' => 0, 'remaining' => null, 'unlimited' => true];
        $sidebarCreditLimit = is_numeric($sidebarCreditSummary['limit'] ?? null) ? max(0, (int) $sidebarCreditSummary['limit']) : null;
        $sidebarCreditsUsed = max(0, (int) ($sidebarCreditSummary['plan_used'] ?? $sidebarCreditSummary['used'] ?? 0));
        $sidebarCreditsUsedPercent = $sidebarCreditLimit && $sidebarCreditLimit > 0
            ? min(100, (int) round(($sidebarCreditsUsed / $sidebarCreditLimit) * 100))
            : null;
        $sidebarPlanCard = [
            'name' => $sidebarUser?->plan?->name ?: __('No plan assigned'),
            'badge' => $sidebarUser?->isInPlanTrial() ? __('Trial') : ($sidebarUser?->hasActivePlan() ? __('Active') : __('Inactive')),
            'badge_tone' => $sidebarUser?->hasActivePlan() ? 'success' : 'neutral',
            'expiry' => (($sidebarUser?->isInPlanTrial() ? $sidebarUser?->trialEndsAt() : $sidebarUser?->plan_expires_at)?->format('Y-m-d')) ?: __('Unlimited'),
            'unlimited' => (bool) ($sidebarCreditSummary['unlimited'] ?? false),
            'credits_used_label' => number_format($sidebarCreditsUsed),
            'credits_limit_label' => $sidebarCreditLimit !== null ? number_format($sidebarCreditLimit) : __('Unlimited'),
            'credits_percent' => $sidebarCreditsUsedPercent,
            'details_route' => route('portal.packages'),
        ];

        $headerWorkspaceTeams = \Modules\AdminUser\Models\Team::query()
            ->where(function ($query): void {
                $query->where('owner_user_id', (int) auth()->id())
                    ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('users.id', (int) auth()->id()));
            })
            ->orderByRaw('CASE WHEN owner_user_id = ? THEN 0 ELSE 1 END', [(int) auth()->id()])
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();

        $headerCurrentWorkspaceTeam = $headerWorkspaceTeams->firstWhere('id', (int) session('portal_team_id', 0))
            ?? $headerWorkspaceTeams->first();
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ current_locale_direction() }}">
    <head>
        @include(theme_view('partials.head', 'app'), ['title' => $title])
    </head>
    <body data-workspace-area="{{ $shellArea }}" class="sp-workspace app-default-body min-h-screen text-slate-900 antialiased dark:text-slate-100" style="font-family: var(--theme-font-sans);">
        <a href="#workspace-main" class="sp-skip-link">{{ __('Skip to content') }}</a>
        <div
            x-cloak
            x-data="{
                sidebarOpen: false,
                sidebarCollapsed: localStorage.getItem('app-default-sidebar-collapsed') === 'true',
                sidebarHovered: false,
                hoverCloseTimer: null,
                contentOpenTimer: null,
                sidebarContentVisible: localStorage.getItem('app-default-sidebar-collapsed') !== 'true',
                appearanceMode: 'system',
                appearanceResolved: 'light',
                supportsDarkMode: true,
                appearanceToggleAllowed: true,
                loadingCount: 0,
                loadingVisible: false,
                loadingShowTimer: null,
                loadingHideTimer: null,
                ajaxLoaderBound: false,
                formSubmitLoaderBound: false,
                loadingOptOutBound: false,
                loadingOptOutUntil: 0,
                loadingOptOutTimer: null,
                toasts: [],
                toastCounter: 0,
                toastListenerBound: false,
                demoModeEnabled: @js(config('app.demo_mode')),
                demoModeTitle: @js(__('Demo mode')),
                demoModeMessage: @js(__('Demo mode is enabled. Add, edit, and delete actions are disabled.')),
                demoModeBlockedMethodPrefixes: ['activate', 'approve', 'archive', 'attach', 'cancel', 'clear', 'clone', 'connect', 'create', 'deactivate', 'delete', 'destroy', 'detach', 'disconnect', 'duplicate', 'import', 'install', 'invite', 'mark', 'move', 'process', 'remove', 'reorder', 'reject', 'rescan', 'resend', 'reset', 'restore', 'revoke', 'run', 'save', 'send', 'set', 'store', 'submit', 'sync', 'toggle', 'unlink', 'unpublish', 'update', 'upload'],
                suppressLivewireLoading: false,
                publishingComposerLoadingGuardBound: false,
                shellHeaderResizeObserver: null,
                viewportWidth: window.innerWidth,
                navigationLoadingGuardBound: false,
                init() {
                    window.__appShellCurrent = this;
                    this.refreshAppearance();
                    window.addEventListener('theme-mode-changed', () => this.refreshAppearance());
                    window.addEventListener('resize', () => {
                        this.viewportWidth = window.innerWidth;
                        this.syncShellHeaderHeight();
                    });
                    this.bindAjaxLoader();
                    this.bindFormSubmitLoader();
                    this.bindLoadingOptOut();
                    this.bindNavigationLoadingGuard();
                    this.bindPublishingComposerLoadingGuard();
                    this.bindToastBus();
                    this.$nextTick(() => this.syncShellHeaderHeight());
                    const flashStatus = @js(session('status'));
                    const flashError = @js(session('error'));
                    const flashWarning = @js(session('warning'));

                    if (flashStatus) {
                        this.pushToast({ type: 'success', message: flashStatus });
                    }

                    if (flashError) {
                        this.pushToast({ type: 'error', message: flashError });
                    }

                    if (flashWarning) {
                        this.pushToast({ type: 'warning', title: this.demoModeTitle, message: flashWarning });
                    }
                },
                bindNavigationLoadingGuard() {
                    if (this.navigationLoadingGuardBound) {
                        return;
                    }

                    this.navigationLoadingGuardBound = true;

                    const resetSuppression = () => {
                        // Safety reset: avoid stale global suppression after page/module switches.
                        window.__appShellCurrent = this;
                        window.__appShellSuppressLoading = false;
                        this.loadingOptOutUntil = 0;
                    };

                    const normalizeState = () => {
                        resetSuppression();

                        if (this.loadingCount === 0) {
                            this.loadingVisible = false;
                        }
                    };

                    window.addEventListener('livewire:navigate', resetSuppression);
                    window.addEventListener('livewire:navigating', resetSuppression);
                    window.addEventListener('livewire:navigated', normalizeState);
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) {
                            normalizeState();
                        }
                    });
                },
                bindPublishingComposerLoadingGuard() {
                    if (this.publishingComposerLoadingGuardBound) {
                        return;
                    }

                    this.publishingComposerLoadingGuardBound = true;

                    const suppress = () => {
                        this.suppressLivewireLoading = true;
                        window.__appShellSuppressLoading = true;

                        this.loadingCount = 0;

                        if (this.loadingShowTimer) {
                            clearTimeout(this.loadingShowTimer);
                            this.loadingShowTimer = null;
                        }

                        if (this.loadingHideTimer) {
                            clearTimeout(this.loadingHideTimer);
                            this.loadingHideTimer = null;
                        }

                        this.loadingVisible = false;
                    };

                    const release = () => {
                        this.suppressLivewireLoading = false;
                        window.__appShellSuppressLoading = false;
                    };

                    window.addEventListener('publishing-composer-scroll-lock', suppress);
                    window.addEventListener('publishing-composer-scroll-unlock', release);
                    window.addEventListener('publishing-post-preview-scroll-lock', suppress);
                    window.addEventListener('publishing-post-preview-scroll-unlock', release);
                },
                syncShellHeaderHeight() {
                    const header = this.$refs.shellHeader;

                    if (!header) {
                        return;
                    }

                    const height = Math.ceil(header.getBoundingClientRect().height || 0);
                    document.documentElement.style.setProperty('--app-shell-header-height', `${Math.max(height, 0)}px`);

                    if (!this.shellHeaderResizeObserver && window.ResizeObserver) {
                        this.shellHeaderResizeObserver = new ResizeObserver(() => this.syncShellHeaderHeight());
                        this.shellHeaderResizeObserver.observe(header);
                    }
                },
                refreshAppearance() {
                    this.appearanceMode = window.themeMode?.getMode?.() || 'system';
                    this.appearanceResolved = window.themeMode?.getResolved?.() || 'light';
                    this.supportsDarkMode = window.themeMode?.supportsDark?.() ?? true;
                    this.appearanceToggleAllowed = window.themeMode?.allowToggle?.() ?? true;
                },
                showDemoModeToast() {
                    this.pushToast({
                        type: 'warning',
                        title: this.demoModeTitle,
                        message: this.demoModeMessage,
                    });
                },
                isDemoModeWorkspacePath(path) {
                    const normalizedPath = String(path || '').replace(/^\/+/, '');

                    return normalizedPath === 'dashboard'
                        || normalizedPath.startsWith('dashboard/')
                        || normalizedPath.startsWith('portal/')
                        || normalizedPath.startsWith('admin/')
                        || normalizedPath.startsWith('admin/settings/');
                },
                isDemoModeWorkspaceUrl(url) {
                    if (!url) {
                        return this.isDemoModeWorkspacePath(window.location.pathname);
                    }

                    try {
                        return this.isDemoModeWorkspacePath(new URL(url, window.location.origin).pathname);
                    } catch (error) {
                        return this.isDemoModeWorkspacePath(url);
                    }
                },
                isDemoModeBlockedMethod(method) {
                    const normalizedMethod = String(method || '').toLowerCase();

                    return normalizedMethod !== ''
                        && this.demoModeBlockedMethodPrefixes.some((prefix) => normalizedMethod.startsWith(prefix));
                },
                parseDemoModePayload(body) {
                    if (typeof body !== 'string' || body.trim() === '') {
                        return null;
                    }

                    try {
                        return JSON.parse(body);
                    } catch (error) {
                        return null;
                    }
                },
                parseDemoModeResponseBody(body) {
                    if (typeof body !== 'string' || body.trim() === '') {
                        return null;
                    }

                    try {
                        const parsed = JSON.parse(body);

                        return parsed?.demo_mode ? parsed : null;
                    } catch (error) {
                        return null;
                    }
                },
                hasBlockedLivewireCalls(payload) {
                    const components = Array.isArray(payload?.components) ? payload.components : [];

                    return components.some((component) => {
                        const calls = Array.isArray(component?.calls) ? component.calls : [];

                        return calls.some((call) => this.isDemoModeBlockedMethod(call?.method));
                    });
                },
                isLivewireEndpointUrl(url, suffix = '/update') {
                    if (typeof url !== 'string' || url === '') {
                        return false;
                    }

                    try {
                        const pathname = new URL(url, window.location.origin).pathname;

                        return /\/livewire(?:-[^/]+)?\//.test(pathname) && pathname.endsWith(suffix);
                    } catch (error) {
                        return /\/livewire(?:-[^/]+)?\//.test(url) && url.endsWith(suffix);
                    }
                },
                shouldBlockDemoModeRequest(url, method = 'GET', body = null) {
                    if (!this.demoModeEnabled) {
                        return false;
                    }

                    const normalizedMethod = String(method || 'GET').toUpperCase();

                    if (['GET', 'HEAD', 'OPTIONS'].includes(normalizedMethod)) {
                        return false;
                    }

                    const livewireRequest = this.isLivewireEndpointUrl(url, '/update');
                    const livewireUploadRequest = this.isLivewireEndpointUrl(url, '/upload-file');

                    if (livewireRequest) {
                        if (!this.isDemoModeWorkspaceUrl(window.location.href)) {
                            return false;
                        }

                        return this.hasBlockedLivewireCalls(this.parseDemoModePayload(body));
                    }

                    if (livewireUploadRequest) {
                        return true;
                    }

                    return this.isDemoModeWorkspaceUrl(url);
                },
                bindAjaxLoader() {
                    if (this.ajaxLoaderBound) {
                        return;
                    }

                    this.ajaxLoaderBound = true;
                    const begin = () => window.__appShellCurrent?.beginLoading?.();
                    const end = () => window.__appShellCurrent?.endLoading?.();
                    const isSuppressed = () => {
                        if (window.__appShellCurrent?.isLoadingSuppressed) {
                            return window.__appShellCurrent.isLoadingSuppressed();
                        }

                        return Boolean(window.__appShellSuppressLoading);
                    };
                    const resolveRequestUrl = (input) => {
                        if (typeof input === 'string') {
                            return input;
                        }

                        if (input instanceof URL) {
                            return input.toString();
                        }

                        return input?.url || '';
                    };
                    const isLivewireRequest = (url) => window.__appShellCurrent?.isLivewireEndpointUrl?.(url, '/update') === true;

                    if (! window.__appThemeFetchPatched) {
                        const originalFetch = window.fetch?.bind(window);

                        if (originalFetch) {
                            window.fetch = (input, init, ...rest) => {
                                const url = resolveRequestUrl(input);
                                const requestMethod = init?.method || (input instanceof Request ? input.method : 'GET');
                                const requestBody = init?.body ?? (input instanceof Request ? input.body : null);

                                if (window.__appShellCurrent?.shouldBlockDemoModeRequest?.(url, requestMethod, requestBody)) {
                                    window.__appShellCurrent?.showDemoModeToast?.();

                                    return Promise.reject(new Error('demo-mode-blocked'));
                                }

                                const shouldTrack = ! isLivewireRequest(url) && ! isSuppressed();

                                if (shouldTrack) {
                                    begin();
                                }

                                return originalFetch(input, init, ...rest)
                                    .finally(() => {
                                        if (shouldTrack) {
                                            end();
                                        }
                                    });
                            };
                        }

                        window.__appThemeFetchPatched = true;
                    }

                    if (! window.__appThemeXhrPatched) {
                        const originalOpen = XMLHttpRequest.prototype.open;
                        const originalSend = XMLHttpRequest.prototype.send;

                        XMLHttpRequest.prototype.open = function (...args) {
                            this.__appThemeMethod = typeof args[0] === 'string' ? args[0] : 'GET';
                            this.__appThemeUrl = typeof args[1] === 'string' ? args[1] : '';
                            this.__appThemeTrack = true;

                            return originalOpen.apply(this, args);
                        };

                        XMLHttpRequest.prototype.send = function (...args) {
                            if (window.__appShellCurrent?.shouldBlockDemoModeRequest?.(this.__appThemeUrl, this.__appThemeMethod, args[0] ?? null)) {
                                window.__appShellCurrent?.showDemoModeToast?.();
                                return;
                            }

                            const shouldTrack = this.__appThemeTrack && ! isLivewireRequest(this.__appThemeUrl) && ! isSuppressed();

                            if (shouldTrack) {
                                begin();
                                this.addEventListener('loadend', () => end(), { once: true });
                            }

                            return originalSend.apply(this, args);
                        };

                        window.__appThemeXhrPatched = true;
                    }

                    document.addEventListener('livewire:init', () => {
                        if (window.__appThemeLivewireBound || ! window.Livewire?.hook) {
                            return;
                        }

                        window.__appThemeLivewireBound = true;
                        const localLoadingMethods = [
                            'generateComposerCaption',
                            'generateComposerImage',
                            'repurposeComposer',
                            'reviewComposer',
                            'suggestComposerBestTimes',
                        ];

                        window.Livewire.hook('request', ({ fail }) => {
                            fail(({ status, content, preventDefault }) => {
                                const currentShell = window.__appShellCurrent;
                                const demoModeResponse = status === 403
                                    ? currentShell?.parseDemoModeResponseBody?.(content)
                                    : null;

                                if (!demoModeResponse) {
                                    return;
                                }

                                preventDefault();
                                currentShell?.pushToast?.({
                                    type: 'warning',
                                    title: currentShell.demoModeTitle,
                                    message: demoModeResponse.message || currentShell.demoModeMessage,
                                });
                            });
                        });

                        window.Livewire.hook('commit', ({ commit, succeed, fail }) => {
                            const currentShell = window.__appShellCurrent;

                            if ((currentShell?.suppressLivewireLoading ?? false) || isSuppressed()) {
                                return;
                            }

                            const calls = Array.isArray(commit?.calls) ? commit.calls : [];
                            const hasActionCalls = calls.some((call) => Boolean(call?.method));
                            const isSilentPoll = calls.length > 0 && calls.every((call) => call?.method === 'refreshConversation');
                            const usesLocalLoadingUi = calls.length > 0 && calls.every((call) => localLoadingMethods.includes(call?.method));

                            if (hasActionCalls && !isSilentPoll && !usesLocalLoadingUi) {
                                begin();
                            }

                            const finish = () => {
                                if (hasActionCalls && !isSilentPoll && !usesLocalLoadingUi) {
                                    end();
                                }
                            };
                            succeed(finish);
                            fail(({ status, content, preventDefault }) => {
                                const demoModeResponse = status === 403
                                    ? currentShell?.parseDemoModeResponseBody?.(content)
                                    : null;

                                if (demoModeResponse) {
                                    preventDefault();
                                    currentShell?.pushToast?.({
                                        type: 'warning',
                                        title: currentShell.demoModeTitle,
                                        message: demoModeResponse.message || currentShell.demoModeMessage,
                                    });
                                }

                                finish();
                            });
                        });
                    }, { once: true });
                },
                bindFormSubmitLoader() {
                    if (this.formSubmitLoaderBound) {
                        return;
                    }

                    this.formSubmitLoaderBound = true;

                    document.addEventListener('submit', (event) => {
                        const form = event.target;

                        if (!(form instanceof HTMLFormElement)) {
                            return;
                        }

                        if (event.defaultPrevented || this.hasLoadingOptOut(form)) {
                            return;
                        }

                        const method = String(form.getAttribute('method') || 'GET').toUpperCase();

                        if (['GET', 'DIALOG'].includes(method)) {
                            return;
                        }

                        this.beginLoading();
                    }, true);
                },
                bindLoadingOptOut() {
                    if (this.loadingOptOutBound) {
                        return;
                    }

                    this.loadingOptOutBound = true;

                    const registerOptOut = (event) => {
                        if (this.hasLoadingOptOut(event.target)) {
                            this.suppressLoadingFor();
                        }
                    };

                    document.addEventListener('click', registerOptOut, true);
                    document.addEventListener('submit', registerOptOut, true);
                    document.addEventListener('input', registerOptOut, true);
                    document.addEventListener('change', registerOptOut, true);
                },
                hasLoadingOptOut(target) {
                    if (!target || typeof target.closest !== 'function') {
                        return false;
                    }

                    return Boolean(target.closest('.no-loading, [data-no-loading]'));
                },
                suppressLoadingFor(duration = 1200) {
                    this.loadingOptOutUntil = Date.now() + duration;
                    window.__appShellLoadingOptOutUntil = this.loadingOptOutUntil;

                    if (this.loadingOptOutTimer) {
                        clearTimeout(this.loadingOptOutTimer);
                    }

                    this.loadingOptOutTimer = setTimeout(() => {
                        this.loadingOptOutUntil = 0;
                        window.__appShellLoadingOptOutUntil = 0;
                        this.loadingOptOutTimer = null;
                    }, duration + 40);
                },
                isLoadingSuppressed() {
                    const globalOptOutUntil = Number(window.__appShellLoadingOptOutUntil || 0);

                    return Boolean(window.__appShellSuppressLoading)
                        || this.loadingOptOutUntil > Date.now()
                        || globalOptOutUntil > Date.now();
                },
                beginLoading() {
                    this.loadingCount++;

                    if (this.loadingHideTimer) {
                        clearTimeout(this.loadingHideTimer);
                        this.loadingHideTimer = null;
                    }

                    if (this.loadingVisible || this.loadingShowTimer) {
                        return;
                    }

                    this.loadingShowTimer = setTimeout(() => {
                        this.loadingVisible = this.loadingCount > 0;
                        this.loadingShowTimer = null;
                    }, 120);
                },
                endLoading() {
                    this.loadingCount = Math.max(0, this.loadingCount - 1);

                    if (this.loadingCount > 0) {
                        return;
                    }

                    if (this.loadingShowTimer) {
                        clearTimeout(this.loadingShowTimer);
                        this.loadingShowTimer = null;
                    }

                    if (this.loadingHideTimer) {
                        clearTimeout(this.loadingHideTimer);
                    }

                    this.loadingHideTimer = setTimeout(() => {
                        this.loadingVisible = false;
                        this.loadingHideTimer = null;
                    }, 140);
                },
                bindToastBus() {
                    if (this.toastListenerBound) {
                        return;
                    }

                    this.toastListenerBound = true;

                    window.addEventListener('app-toast', (event) => {
                        this.pushToast(event.detail || {});
                    });
                },
                pushToast(detail = {}) {
                    const id = ++this.toastCounter;
                    const type = detail.type === 'error' ? 'error' : (detail.type === 'warning' ? 'warning' : 'success');
                    const toast = {
                        id,
                        type,
                        title: detail.title || (type === 'error' ? 'Action failed' : (type === 'warning' ? 'Notice' : 'Success')),
                        message: detail.message || '',
                    };

                    this.toasts = [...this.toasts, toast];

                    const timeout = window.setTimeout(() => {
                        this.dismissToast(id);
                    }, type === 'error' ? 5200 : 3600);

                    toast.timeout = timeout;
                },
                dismissToast(id) {
                    const toast = this.toasts.find((item) => item.id === id);

                    if (toast?.timeout) {
                        clearTimeout(toast.timeout);
                    }

                    this.toasts = this.toasts.filter((item) => item.id !== id);
                },
                setAppearance(mode) {
                    window.themeMode?.setMode?.(mode);
                    this.refreshAppearance();
                },
                get sidebarPanelExpanded() {
                    return !this.sidebarCollapsed || this.sidebarHovered;
                },
                notifyLayoutChange() {
                    window.dispatchEvent(new CustomEvent('app-shell:layout-change', {
                        detail: {
                            sidebarCollapsed: this.sidebarCollapsed,
                            sidebarHovered: this.sidebarHovered,
                            sidebarExpanded: this.sidebarPanelExpanded,
                        },
                    }));
                },
                toggleSidebar() {
                    const nextCollapsed = !this.sidebarCollapsed;
                    if (this.contentOpenTimer) {
                        clearTimeout(this.contentOpenTimer);
                        this.contentOpenTimer = null;
                    }

                    if (nextCollapsed) {
                        this.sidebarContentVisible = false;
                    }

                    this.sidebarCollapsed = nextCollapsed;
                    localStorage.setItem('app-default-sidebar-collapsed', this.sidebarCollapsed ? 'true' : 'false');
                    this.sidebarHovered = false;

                    if (!nextCollapsed) {
                        this.contentOpenTimer = setTimeout(() => {
                            this.sidebarContentVisible = true;
                            this.contentOpenTimer = null;
                        }, 0);
                    }

                    if (this.hoverCloseTimer) {
                        clearTimeout(this.hoverCloseTimer);
                        this.hoverCloseTimer = null;
                    }

                    this.notifyLayoutChange();
                    setTimeout(() => this.notifyLayoutChange(), 180);
                    setTimeout(() => this.notifyLayoutChange(), 440);
                },
                startSidebarHover() {
                    if (!this.sidebarCollapsed) return;
                    if (this.hoverCloseTimer) {
                        clearTimeout(this.hoverCloseTimer);
                        this.hoverCloseTimer = null;
                    }
                    this.sidebarHovered = true;
                    if (this.contentOpenTimer) {
                        clearTimeout(this.contentOpenTimer);
                    }
                    this.contentOpenTimer = setTimeout(() => {
                        this.sidebarContentVisible = true;
                        this.contentOpenTimer = null;
                        this.notifyLayoutChange();
                    }, 0);
                },
                endSidebarHover() {
                    if (!this.sidebarCollapsed) return;
                    this.sidebarContentVisible = false;
                    if (this.contentOpenTimer) {
                        clearTimeout(this.contentOpenTimer);
                        this.contentOpenTimer = null;
                    }
                    if (this.hoverCloseTimer) {
                        clearTimeout(this.hoverCloseTimer);
                    }
                    this.hoverCloseTimer = setTimeout(() => {
                        this.sidebarHovered = false;
                        this.hoverCloseTimer = null;
                        this.notifyLayoutChange();
                    }, 90);
                },
            }"
            class="app-default-shell min-h-screen"
        >
            <div
                x-cloak
                x-show="loadingVisible && !window.__appShellSuppressLoading"
                x-transition.opacity.duration.150ms
                class="pointer-events-none fixed inset-x-0 top-0 z-[180]"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="h-[3px] overflow-hidden bg-transparent shadow-[0_8px_22px_-14px_rgba(var(--theme-accent-rgb),0.85)]">
                    <div class="h-full w-full animate-pulse bg-[linear-gradient(90deg,transparent_0%,var(--theme-accent)_18%,var(--theme-link-hover)_52%,var(--theme-accent)_84%,transparent_100%)] opacity-95"></div>
                </div>
            </div>

            <div class="pointer-events-none fixed inset-x-0 top-2 z-[190] flex max-w-full flex-col gap-3 px-3 sm:left-auto sm:right-6 sm:top-6 sm:w-full sm:max-w-sm sm:px-0">
                <template x-for="toast in toasts" :key="toast.id">
                    <div
                        x-cloak
                        x-transition:enter="transform transition ease-out duration-200"
                        x-transition:enter-start="translate-y-2 opacity-0"
                        x-transition:enter-end="translate-y-0 opacity-100"
                        x-transition:leave="transform transition ease-in duration-150"
                        x-transition:leave-start="translate-y-0 opacity-100"
                        x-transition:leave-end="translate-y-1 opacity-0"
                        class="pointer-events-auto w-full overflow-hidden rounded-[1rem] border shadow-[0_24px_60px_-24px_rgba(15,23,42,0.35)] backdrop-blur-xl"
                        :style="toast.type === 'error'
                            ? 'border-color: rgba(248,113,113,0.28); background: color-mix(in srgb, #fff1f2 88%, transparent);'
                            : toast.type === 'warning'
                                ? 'border-color: rgba(251,191,36,0.28); background: color-mix(in srgb, #fffbeb 88%, transparent);'
                                : 'border-color: rgba(16,185,129,0.24); background: color-mix(in srgb, #ecfdf5 90%, transparent);'"
                    >
                        <div class="flex items-start gap-3 px-4 py-3.5 dark:bg-slate-950/80">
                            <span
                                class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[0.8rem]"
                                :style="toast.type === 'error'
                                    ? 'background: rgba(239,68,68,0.14); color: rgb(220,38,38);'
                                    : toast.type === 'warning'
                                        ? 'background: rgba(245,158,11,0.14); color: rgb(217,119,6);'
                                        : 'background: rgba(16,185,129,0.14); color: rgb(5,150,105);'"
                            >
                                <i class="fa-light" :class="toast.type === 'error' ? 'fa-circle-exclamation' : (toast.type === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check')"></i>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="toast.title"></p>
                                <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);" x-text="toast.message"></p>
                            </div>

                            <button
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-[0.7rem] text-slate-400 transition hover:bg-slate-900/5 hover:text-slate-700 dark:hover:bg-white/5 dark:hover:text-slate-200"
                                x-on:click="dismissToast(toast.id)"
                            >
                                <i class="fa-light fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <x-layout.sidebar :sections="$sidebarSections" :home-href="$sidebarHomeRoute" :area="$shellArea" :workspace-name="$headerCurrentWorkspaceTeam?->name">
                <x-slot:footer>
                    <div class="space-y-3">
                        @if ($dashboardSwitch)
                            <x-ui.button
                                :href="$dashboardSwitch['route']"
                                variant="outline"
                                wire:navigate
                                x-bind:class="sidebarContentVisible ? 'w-full justify-center' : 'h-10 w-10 justify-center px-0'"
                                title="{{ __($dashboardSwitch['label']) }}"
                            >
                                <i class="fa-light fa-arrow-right-arrow-left"></i>
                                <span
                                    x-cloak
                                    x-show="sidebarContentVisible"
                                    x-transition:enter="transition ease-out duration-140"
                                    x-transition:enter-start="opacity-0 -translate-x-1"
                                    x-transition:enter-end="opacity-100 translate-x-0"
                                >
                                    {{ __($dashboardSwitch['label']) }}
                                </span>
                            </x-ui.button>
                        @endif

                        @if ($sidebarPlanCard)
                            <div class="sp-sidebar-plan" x-cloak x-show="sidebarContentVisible">
                                <div><strong>{{ $sidebarPlanCard['name'] }}</strong><span>{{ $sidebarPlanCard['badge'] }}</span></div>
                                <p>{{ __('Expires') }} {{ $sidebarPlanCard['expiry'] }}</p>
                                <div class="sp-sidebar-credit"><span>{{ __('Plan credits used') }}</span><strong>{{ $sidebarPlanCard['credits_used_label'] }} / {{ $sidebarPlanCard['credits_limit_label'] }}</strong></div>
                                <a href="{{ $sidebarPlanCard['details_route'] }}" wire:navigate>{{ __('Manage plan') }} <i class="fa-light fa-arrow-up-right" aria-hidden="true"></i></a>
                            </div>
                        @endif
                    </div>
                </x-slot:footer>
            </x-layout.sidebar>

            <div class="transition-[padding] duration-[420ms] ease-[cubic-bezier(0.16,1,0.3,1)] lg:pl-[16rem]" x-bind:class="sidebarCollapsed ? 'lg:pl-[70px]' : 'lg:pl-[16rem]'">
                <div
                    x-ref="shellHeader"
                    class="fixed top-0 right-0 z-[120] transition-[left] duration-[420ms] ease-[cubic-bezier(0.16,1,0.3,1)]"
                    x-bind:style="viewportWidth >= 1024
                        ? `left: ${sidebarCollapsed ? '70px' : '16rem'}`
                        : 'left: 0px'"
                >
                    <x-layout.header
                        :search-placeholder="__('Search workspace')"
                        :header-items-start="$headerItemsStart"
                        :header-items-primary-nav="$headerItemsPrimaryNav"
                        :header-items-center="$headerItemsCenter"
                        :header-items-end="$headerItemsEnd"
                        :boxed-layout="$isBoxedLayout" :page-title="$title" :area="$shellArea" :home-href="$sidebarHomeRoute"
                    >
                        <x-slot:start>
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-[0.75rem] border border-slate-200 text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 lg:hidden dark:border-slate-800 dark:text-slate-100 dark:hover:border-slate-700 dark:hover:bg-slate-900"
                                style="background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);"
                                aria-label="{{ __('Open main menu') }}" x-on:click="sidebarOpen = true"
                            >
                                <i class="fa-light fa-bars text-base"></i>
                            </button>
                        </x-slot:start>

                        @if ($shellArea === 'user' && Route::has('portal.teams.switch'))
                            <x-slot:workspace>
                                <x-layout.team-switcher :teams="$headerWorkspaceTeams" :current-team="$headerCurrentWorkspaceTeam" />
                            </x-slot:workspace>
                        @endif

                        <x-slot:end>
                            <div class="relative" x-data="{ open: false }" x-show="appearanceToggleAllowed && supportsDarkMode" x-cloak x-on:keydown.escape.window="open = false">
                            <button
                                type="button"
                                class="flex h-11 w-11 items-center justify-center rounded-[0.75rem] border border-slate-200/90 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:hover:border-slate-700 dark:hover:bg-slate-900"
                                style="background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);"
                                x-bind:class="open ? 'border-slate-300 bg-slate-50 dark:border-slate-600 dark:bg-slate-900' : ''"
                                x-on:click="open = !open"
                                x-bind:aria-label="appearanceResolved === 'dark' ? 'Dark mode' : 'Light mode'"
                            >
                                <i class="fa-light text-[16px] text-slate-600 dark:text-slate-200" :class="appearanceResolved === 'dark' ? 'fa-moon-stars' : 'fa-sun-bright'"></i>
                            </button>

                            <div
                                x-cloak
                                x-show="open"
                                x-transition:enter="transition ease-out duration-160"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-120"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 translate-y-1"
                                x-on:click.outside="open = false"
                                class="absolute left-0 top-[calc(100%+0.75rem)] z-50 w-56 overflow-hidden rounded-[1rem] border p-2 shadow-[0_18px_44px_-30px_rgba(15,23,42,0.45)] md:left-auto md:right-0 md:translate-x-0"
                                style="border-color: rgba(var(--theme-border-color-rgb),0.72); background-color: color-mix(in srgb, var(--theme-surface-overlay) 92%, var(--theme-surface-base) 8%);"
                            >
                                <div class="px-3 pb-2 pt-1">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Appearance') }}</p>
                                </div>
                                @foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $mode => $label)
                                    <button
                                        type="button"
                                        class="grid w-full grid-cols-[2rem_minmax(0,1fr)_1rem] items-center gap-3 rounded-[0.75rem] px-3 py-2.5 text-sm font-medium transition"
                                        x-bind:class="appearanceMode === '{{ $mode }}'
                                            ? 'text-white'
                                            : 'text-slate-600 hover:bg-slate-950/5 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-white/7 dark:hover:text-white'"
                                        x-bind:style="appearanceMode === '{{ $mode }}' ? 'background-color: var(--theme-accent);' : ''"
                                        x-on:click="setAppearance('{{ $mode }}'); open = false"
                                    >
                                        <span class="flex h-8 w-8 items-center justify-center rounded-[0.65rem]"
                                            :class="appearanceMode === '{{ $mode }}' ? 'bg-white/16 text-white' : 'bg-slate-100 text-slate-500 dark:bg-white/7 dark:text-slate-300'">
                                            <i class="fa-light text-[13px]"
                                               :class="'{{ $mode === 'light' ? 'fa-sun-bright' : ($mode === 'dark' ? 'fa-moon-stars' : 'fa-circle-half-stroke') }}'"></i>
                                        </span>
                                        <span class="min-w-0 truncate text-left">{{ __($label) }}</span>
                                        <i class="fa-light fa-check justify-self-end text-xs" x-show="appearanceMode === '{{ $mode }}'"></i>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @php($languages = available_languages())
                        @if ($languages->isNotEmpty())
                            <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                                <button
                                    type="button"
                                    class="flex h-11 w-11 items-center justify-center rounded-[0.75rem] border border-slate-200/90 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:hover:border-slate-700 dark:hover:bg-slate-900"
                                    style="background-color: rgba(var(--theme-header-surface-rgb), 0.9); border-color: var(--theme-shell-border-color);"
                                    x-bind:class="open ? 'border-slate-300 bg-slate-50 dark:border-slate-600 dark:bg-slate-900' : ''"
                                    x-on:click="open = !open"
                                >
                                    <span class="{{ language_flag_class(current_language() ?? app()->getLocale()) }} rounded-sm text-[18px]"></span>
                                </button>

                                <div
                                    x-cloak
                                    x-show="open"
                                    x-transition:enter="transition ease-out duration-160"
                                    x-transition:enter-start="opacity-0 translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-120"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 translate-y-1"
                                x-on:click.outside="open = false"
                                class="absolute left-0 top-[calc(100%+0.75rem)] z-50 w-56 overflow-hidden rounded-[1rem] border p-2 shadow-[0_18px_44px_-30px_rgba(15,23,42,0.45)] md:left-auto md:right-0 md:translate-x-0"
                                style="border-color: rgba(var(--theme-border-color-rgb),0.72); background-color: color-mix(in srgb, var(--theme-surface-overlay) 92%, var(--theme-surface-base) 8%);"
                            >
                                <div class="px-3 pb-2 pt-1">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ __('Language') }}</p>
                                </div>
                                @foreach ($languages as $language)
                                    <a
                                        href="{{ route('language.switch', $language->code) }}"
                                        class="grid grid-cols-[2rem_minmax(0,1fr)_1rem] items-center gap-3 rounded-[0.75rem] px-3 py-2.5 text-sm font-medium transition {{ app()->getLocale() === $language->code ? 'text-white' : 'text-slate-600 hover:bg-slate-950/5 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-white/7 dark:hover:text-white' }}"
                                        style="{{ app()->getLocale() === $language->code ? 'background-color: var(--theme-accent);' : '' }}"
                                        x-on:click="open = false"
                                    >
                                        <span class="flex h-8 w-8 items-center justify-center rounded-[0.65rem]"
                                            @class([
                                                'bg-white/16' => app()->getLocale() === $language->code,
                                                'bg-slate-100 dark:bg-white/7' => app()->getLocale() !== $language->code,
                                            ])>
                                            <span class="{{ language_flag_class($language) }} rounded-sm text-[18px]"></span>
                                        </span>
                                        <span class="min-w-0 text-left leading-none">
                                            <span class="block truncate">{{ $language->name }}</span>
                                            <span class="mt-1 block text-[11px] font-semibold uppercase tracking-[0.16em] opacity-70">{{ strtoupper($language->code) }}</span>
                                        </span>
                                        @if (app()->getLocale() === $language->code)
                                            <i class="fa-light fa-check justify-self-end text-xs"></i>
                                        @endif
                                    </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        </x-slot:end>
                    </x-layout.header>
                </div>

                <main
                    id="workspace-main" tabindex="-1" class="app-theme-content {{ $fullWorkspace ? ('w-full max-w-none px-0 pt-0 '.($fullWorkspacePaddingBottom ? 'pb-4 sm:pb-4 xl:pb-5' : 'pb-0')) : 'mt-[var(--app-shell-header-height,64px)] mx-auto w-full '.($isBoxedLayout ? 'max-w-[1440px]' : 'app-theme-shell').' px-4 py-6 sm:px-6 xl:px-8' }}"
                    @if ($fullWorkspace)
                        style="height: 100dvh; min-height: 100dvh; padding-top: var(--app-shell-header-height, 64px);"
                    @endif
                >
                    @if ($isImpersonating)
                        <div class="mb-6 flex flex-col gap-4 rounded-[1.15rem] border px-5 py-4 shadow-none lg:flex-row lg:items-center lg:justify-between" style="border-color: rgba(var(--theme-warning-color-rgb), 0.28); background-color: rgba(var(--theme-warning-color-rgb), 0.08);">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[0.95rem]" style="background: rgba(var(--theme-warning-color-rgb), 0.16); color: var(--theme-warning-color);">
                                    <i class="fa-light fa-user-secret"></i>
                                </span>
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('View as user') }}</p>
                                    <p class="mt-1 text-sm font-semibold" style="color: var(--theme-header-text-color);">
                                        {{ __('You are currently browsing as :user.', ['user' => auth()->user()?->name]) }}
                                    </p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">
                                        {{ __('Admin origin') }}: {{ $impersonatorName ?? __('Administrator') }}
                                    </p>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('impersonation.leave') }}">
                                @csrf
                                <x-ui.button type="submit" variant="outline">
                                    {{ __('Return to admin') }}
                                </x-ui.button>
                            </form>
                        </div>
                    @endif

                    {{ $slot }}
                </main>

                @if ($shellArea === 'user' && auth()->check() && class_exists(\Modules\AppAIAssistant\Livewire\AssistantDock::class))
                    @livewire(
                        \Modules\AppAIAssistant\Livewire\AssistantDock::class,
                        [
                            'pageRouteName' => request()->route()?->getName() ?? '',
                            'pagePath' => request()->path(),
                            'pageTitle' => (string) ($title ?? ''),
                        ],
                        key('workspace-ai-assistant-'.auth()->id().'-'.((int) session('portal_team_id', 0)))
                    )
                @endif
            </div>

            <x-layout.mobile-sidebar
                :sections="$sidebarSections"
                :dashboard-switch="$dashboardSwitch"
                :plan-card="$sidebarPlanCard"
                :home-href="$sidebarHomeRoute"
            />
        </div>
        @include(theme_view('partials.embed-code-body', 'app'))
        @livewireScripts
    </body>
</html>
