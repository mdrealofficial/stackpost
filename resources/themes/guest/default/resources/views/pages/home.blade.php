@extends(theme_view('layouts.marketing', 'guest'))

@section('content')
    @php
        $signupEnabled = auth_signup_enabled();
    @endphp
    <section class="guest-marketing-shell mx-auto px-5 pb-14 pt-10 lg:px-8 lg:pb-20 lg:pt-14">
        <div class="mx-auto max-w-5xl text-center">
            <span data-reveal class="nova-pulse-glow inline-flex items-center rounded-full border border-violet-400/14 bg-violet-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-violet-200 shadow-[0_0_24px_rgba(139,92,246,0.12)]">
                <i class="fa-light fa-sparkles mr-2"></i>
                {{ __('Now with AI publishing workflows') }}
            </span>

            <h1 data-reveal class="nova-stagger-1 mt-8 text-5xl font-semibold leading-[0.92] tracking-[-0.06em] text-white sm:text-6xl lg:text-8xl">
                {{ __('Build Smarter.') }}
                <span class="block bg-[linear-gradient(90deg,#a855f7_0%,#8b5cf6_24%,#6366f1_48%,#3b82f6_72%,#22d3ee_100%)] bg-clip-text text-transparent">
                    {{ __('Automate Everything.') }}
                </span>
            </h1>

            <p data-reveal class="nova-stagger-2 mx-auto mt-7 max-w-2xl text-lg leading-8 text-white/58">
                {{ __('Plan, create, and publish faster with AI, media tools, scheduling, and team workflow in one place.') }}
            </p>

            <div data-reveal class="nova-stagger-3 mt-10 flex flex-wrap justify-center gap-4">
                @if ($signupEnabled)
                    <a href="{{ route('register') }}" class="nova-sheen nova-pulse-glow inline-flex items-center justify-center rounded-full px-7 py-4 text-sm font-semibold text-white shadow-[0_0_32px_rgba(99,102,241,0.34),0_22px_48px_-26px_rgba(34,211,238,0.45)] transition hover:scale-[1.01]" style="background: linear-gradient(135deg, #8b5cf6 0%, #4f46e5 48%, #22d3ee 100%);">
                        {{ __('Start Free Trial') }}
                        <i class="fa-light fa-arrow-right ml-2"></i>
                    </a>
                @endif
                <a href="#capabilities" class="nova-sheen inline-flex items-center justify-center rounded-full border border-white/10 bg-white/[0.03] px-7 py-4 text-sm font-semibold text-white/76 transition hover:bg-white/8 hover:text-white">
                    <i class="fa-light fa-circle-play mr-2"></i>
                    {{ __('Explore Features') }}
                </a>
            </div>

            <div data-reveal class="nova-stagger-4 mt-12 flex flex-wrap items-center justify-center gap-10">
                <div>
                    <p class="text-3xl font-semibold tracking-[-0.05em] text-white">50K+</p>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('Active users') }}</p>
                </div>
                <div>
                    <p class="text-3xl font-semibold tracking-[-0.05em] text-white">99.9%</p>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('Uptime') }}</p>
                </div>
                <div>
                    <p class="text-3xl font-semibold tracking-[-0.05em] text-white">4.9★</p>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('User rating') }}</p>
                </div>
            </div>
        </div>

        <div class="relative mx-auto mt-16 max-w-[76rem] px-1 lg:px-6">
            <div data-reveal class="nova-orbit-chip nova-orbit-chip--violet nova-float nova-inverse-surface nova-inverse hidden rounded-[1.35rem] px-4 py-3 text-left lg:block" style="left: 0.25rem; top: 5.75rem;">
                <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-white/34">{{ __('Posts queued') }}</p>
                <p class="mt-2 text-2xl font-semibold tracking-[-0.05em] text-white">148</p>
            </div>

            <div data-reveal class="nova-orbit-chip nova-orbit-chip--cyan nova-float nova-orbit-chip--delay-1 nova-inverse-surface nova-inverse hidden rounded-[1.35rem] px-4 py-3 text-left lg:block" style="right: 1.25rem; top: 9.5rem;">
                <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-white/34">{{ __('AI ready') }}</p>
                <p class="mt-2 text-sm font-medium text-cyan-100">{{ __('12 caption drafts') }}</p>
            </div>

            <div data-reveal class="nova-orbit-chip nova-orbit-chip--amber nova-float-soft nova-orbit-chip--delay-2 nova-inverse-surface nova-inverse hidden rounded-[999px] px-4 py-2 lg:block" style="left: 3.75rem; bottom: 2.5rem;">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-amber-300 shadow-[0_0_14px_rgba(252,211,77,0.6)]"></span>
                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/56">{{ __('+28 scheduled today') }}</span>
                </div>
            </div>

            <div data-reveal class="nova-orbit-chip nova-orbit-chip--cyan nova-float-soft nova-inverse-surface nova-inverse hidden rounded-[999px] px-4 py-2 lg:block" style="right: 4.25rem; bottom: 1.5rem;">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-300 shadow-[0_0_14px_rgba(110,231,183,0.65)]"></span>
                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/56">{{ __('Live queue synced') }}</span>
                </div>
            </div>

        <div data-reveal class="nova-float nova-grid-glow nova-stagger-5 nova-inverse-surface nova-inverse mx-auto max-w-5xl overflow-hidden rounded-[2.2rem] border border-white/8 bg-[linear-gradient(180deg,rgba(14,18,34,0.98)_0%,rgba(10,12,24,0.96)_100%)] p-4 shadow-[0_40px_120px_-60px_rgba(0,0,0,0.95)] backdrop-blur-xl lg:p-6">
            <div class="nova-inverse-soft flex items-center justify-between rounded-[1.4rem] border border-white/6 bg-[linear-gradient(180deg,rgba(255,255,255,0.04)_0%,rgba(255,255,255,0.02)_100%)] px-4 py-3">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span>
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                </div>
                <span class="rounded-full border border-white/8 bg-black/20 px-4 py-1.5 text-xs font-semibold tracking-[0.18em] text-white/50">{{ __('Publishing Dashboard') }}</span>
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">{{ __('Live system') }}</span>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[0.23fr_0.77fr]">
                <div class="nova-inverse-soft rounded-[1.6rem] border border-white/6 bg-[linear-gradient(180deg,rgba(255,255,255,0.035)_0%,rgba(255,255,255,0.02)_100%)] p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/30">{{ __('Queue') }}</p>
                        <span class="rounded-full border border-white/8 bg-white/[0.03] px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/40">{{ __('04 live') }}</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        @foreach ([
                            ['label' => 'RSS feed sync', 'time' => '10:00'],
                            ['label' => 'Bulk publish', 'time' => '12:30'],
                            ['label' => 'Caption batch', 'time' => '14:00'],
                            ['label' => 'Team review', 'time' => '16:15'],
                        ] as $row)
                            <div class="nova-float-soft nova-tilt-hover nova-inverse-deep rounded-[1.25rem] border border-white/6 bg-black/18 px-3.5 py-3.5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-white/78">{{ $row['label'] }}</p>
                                        <p class="mt-1 text-[11px] uppercase tracking-[0.18em] text-white/28">{{ $row['time'] }}</p>
                                    </div>
                                    <span class="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-300 shadow-[0_0_18px_rgba(110,231,183,0.45)]"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="nova-inverse-soft rounded-[1.6rem] border border-white/6 bg-[linear-gradient(180deg,rgba(255,255,255,0.035)_0%,rgba(255,255,255,0.02)_100%)] p-5">
                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="nova-tilt-hover nova-inverse-deep rounded-[1.4rem] border border-white/6 bg-black/18 px-4 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/30">{{ __('Active connections') }}</p>
                            <p class="mt-3 text-[2.55rem] font-semibold tracking-[-0.06em] text-white">{{ number_format(($marketingStats['plans'] ?? 0) * 302 + 1208) }}</p>
                        </div>
                        <div class="nova-tilt-hover rounded-[1.4rem] border border-violet-400/14 bg-[linear-gradient(180deg,rgba(139,92,246,0.16)_0%,rgba(139,92,246,0.08)_100%)] px-4 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-violet-200/70">{{ __('Events / sec') }}</p>
                            <p class="mt-3 text-[2.55rem] font-semibold tracking-[-0.06em] text-violet-100">868</p>
                        </div>
                        <div class="nova-tilt-hover rounded-[1.4rem] border border-cyan-400/14 bg-[linear-gradient(180deg,rgba(34,211,238,0.16)_0%,rgba(34,211,238,0.08)_100%)] px-4 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-cyan-100/70">{{ __('Automations') }}</p>
                            <p class="mt-3 text-[2.55rem] font-semibold tracking-[-0.06em] text-cyan-100">{{ max(24, (int) ($marketingStats['faqs'] ?? 0) + 24) }}</p>
                        </div>
                    </div>

                    <div class="nova-inverse-deep relative mt-5 overflow-hidden rounded-[1.7rem] border border-white/6 bg-[linear-gradient(180deg,rgba(6,8,18,0.98)_0%,rgba(9,12,24,0.98)_100%)] p-6">
                        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom,rgba(34,211,238,0.08),transparent_34%),radial-gradient(circle_at_top,rgba(168,85,247,0.10),transparent_28%)]"></div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-white/72">{{ __('Publishing performance') }}</p>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/28">{{ __('Last 7 days') }}</p>
                        </div>
                        <div class="relative mt-8 h-52">
                            <div class="absolute inset-x-0 bottom-0 top-0 flex flex-col justify-between">
                                @foreach ([1, 2, 3, 4] as $line)
                                    <span class="block border-t border-dashed border-white/6"></span>
                                @endforeach
                            </div>
                            <div class="relative flex h-full items-end gap-3">
                                @foreach ([32, 28, 36, 52, 47, 61, 58, 44, 42] as $point)
                                    <div class="flex-1">
                                        <div class="nova-pulse-glow w-full rounded-t-[1.4rem] bg-[linear-gradient(180deg,rgba(168,85,247,0.12)_0%,rgba(118,86,255,0.56)_34%,rgba(236,72,153,0.92)_100%)] shadow-[0_20px_45px_-22px_rgba(168,85,247,0.52)]" style="height: {{ $point * 2.35 }}px;"></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </section>

    <section id="capabilities" class="guest-marketing-shell guest-section-space mx-auto scroll-mt-32 px-5 pt-0 lg:px-8 lg:scroll-mt-36">
        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ([
                ['icon' => 'fa-light fa-satellite-dish', 'title' => __('Operational visibility'), 'body' => __('Track queues, schedules, channel health, and delivery performance from one live dashboard.')],
                ['icon' => 'fa-light fa-wand-magic-sparkles', 'title' => __('AI production layer'), 'body' => __('Generate drafts, captions, post variants, and supporting content without leaving the workflow.')],
                ['icon' => 'fa-light fa-people-group', 'title' => __('Team-ready controls'), 'body' => __('Coordinate approvals, media files, groups, plans, and workspace operations in one system.')],
            ] as $card)
                <article data-reveal class="nova-tilt-hover nova-card-surface rounded-[2rem] border border-white/8 bg-[linear-gradient(180deg,rgba(14,17,31,0.92)_0%,rgba(10,13,25,0.82)_100%)] p-8 shadow-[0_20px_60px_-42px_rgba(0,0,0,0.62)]">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-3xl bg-violet-500/10 text-xl text-violet-200 shadow-[0_0_26px_rgba(139,92,246,0.12)]">
                        <i class="{{ $card['icon'] }}"></i>
                    </span>
                    <h2 class="mt-6 text-2xl font-semibold tracking-tight text-white">{{ $card['title'] }}</h2>
                    <p class="mt-4 text-sm leading-8 text-white/58">{{ $card['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="guest-marketing-shell guest-section-space mx-auto px-5 pt-0 lg:px-8">
        <div data-reveal class="mb-10 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="max-w-3xl">
                <span class="inline-flex items-center rounded-full border border-violet-400/14 bg-violet-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-violet-200">
                    {{ __('Feature matrix') }}
                </span>
                <h2 class="mt-5 text-4xl font-semibold tracking-[-0.05em] text-white md:text-5xl">{{ __('Everything your team needs to run content operations.') }}</h2>
            </div>
            <p class="max-w-xl text-sm leading-7 text-white/52">{{ __('From drafting and scheduling to assets, approvals, and reporting, each module supports daily work.') }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <article data-reveal class="nova-tilt-hover nova-accent-surface--violet relative overflow-hidden rounded-[2.4rem] border border-violet-400/12 bg-[linear-gradient(135deg,rgba(38,20,64,0.74)_0%,rgba(18,18,34,0.94)_48%,rgba(10,12,24,0.98)_100%)] p-8 shadow-[0_28px_80px_-48px_rgba(0,0,0,0.85)] lg:col-span-7">
                <div class="pointer-events-none absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_top,rgba(168,85,247,0.22),transparent_58%)]"></div>
                <div class="relative flex flex-col gap-8">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                        <div class="max-w-xl">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.26em] text-violet-200/46">{{ __('Content system') }}</p>
                            <div class="mt-5 flex items-center gap-4">
                                <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-violet-500/16 text-violet-100">
                                    <i class="fa-light fa-fw fa-wand-magic-sparkles text-[1.35rem] leading-none"></i>
                                </span>
                                <div>
                                    <h3 class="text-3xl font-semibold tracking-[-0.05em] text-white">{{ __('Create, refine, and prep content faster.') }}</h3>
                                    <p class="mt-2 text-sm leading-7 text-white/54">{{ __('Turn ideas into ready-to-publish content with AI help, reusable media, and a cleaner drafting flow.') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 sm:w-[12.5rem]">
                            <div class="nova-soft-surface flex min-h-[4.5rem] items-center gap-3 rounded-[1.25rem] border border-white/8 bg-white/[0.04] px-4 py-3">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-500/12 text-sm font-semibold uppercase tracking-[0.02em] text-white">AI</span>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('Caption assist') }}</p>
                                    <p class="mt-1 text-sm font-medium text-white/62">{{ __('Rewrite faster') }}</p>
                                </div>
                            </div>
                            <div class="nova-soft-surface flex min-h-[4.5rem] items-center gap-3 rounded-[1.25rem] border border-white/8 bg-white/[0.04] px-4 py-3">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-500/12 text-sm font-semibold tracking-[-0.03em] text-white">B</span>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/34">{{ __('Bulk prep') }}</p>
                                    <p class="mt-1 text-sm font-medium text-white/62">{{ __('Queue ready') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([__('AI captions and rewrites'), __('Post drafts and review flow'), __('Media search and attachment'), __('Bulk post preparation')] as $item)
                            <div class="nova-soft-surface group flex items-center gap-3 rounded-[1.35rem] border border-white/8 bg-black/16 px-4 py-4 transition duration-300 hover:border-violet-300/18 hover:bg-white/[0.05]">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-500/12 text-violet-100">
                                    <i class="fa-light fa-fw fa-arrow-up-right-from-square text-[0.7rem] leading-none"></i>
                                </span>
                                <p class="text-sm leading-6 text-white/68 transition group-hover:text-white/86">{{ $item }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article data-reveal class="nova-tilt-hover nova-accent-surface--cyan relative overflow-hidden rounded-[2.4rem] border border-cyan-400/12 bg-[linear-gradient(135deg,rgba(10,52,74,0.46)_0%,rgba(12,20,36,0.96)_55%,rgba(9,12,24,0.98)_100%)] p-8 shadow-[0_28px_80px_-48px_rgba(0,0,0,0.85)] lg:col-span-5">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-36 bg-[radial-gradient(circle_at_top,rgba(34,211,238,0.16),transparent_60%)]"></div>
                <div class="relative">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.26em] text-cyan-100/40">{{ __('Always-on publishing') }}</p>
                            <div class="mt-4 flex items-center gap-4">
                                <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-cyan-400/14 text-cyan-100">
                                    <i class="fa-light fa-fw fa-calendar-days text-[1.1rem] leading-none"></i>
                                </span>
                                <h3 class="text-3xl font-semibold tracking-[-0.05em] text-white">{{ __('Scheduling and automation') }}</h3>
                            </div>
                        </div>
                        <div class="nova-soft-surface rounded-[1.4rem] border border-white/8 bg-white/[0.04] px-4 py-4 text-right">
                            <p class="text-2xl font-semibold tracking-[-0.05em] text-white">24/7</p>
                            <p class="mt-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-white/34">{{ __('queue active') }}</p>
                        </div>
                    </div>

                    <p class="mt-6 text-sm leading-7 text-white/56">{{ __('Move approved content into queues, schedules, and RSS automations while keeping execution clean across connected channels.') }}</p>

                    <div class="mt-7 space-y-3">
                        @foreach ([__('Calendar scheduling and queue control'), __('RSS automation and evergreen loops'), __('Channel-based publish rules')] as $item)
                            <div class="nova-soft-surface rounded-[1.25rem] border border-white/7 bg-white/[0.03] px-4 py-3.5 text-sm text-white/66">{{ $item }}</div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article data-reveal class="nova-tilt-hover nova-accent-surface--emerald relative overflow-hidden rounded-[2.4rem] border border-emerald-400/12 bg-[linear-gradient(135deg,rgba(8,64,58,0.42)_0%,rgba(11,20,28,0.96)_56%,rgba(10,12,24,0.98)_100%)] p-8 shadow-[0_28px_80px_-48px_rgba(0,0,0,0.85)] lg:col-span-4">
                <div class="relative">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.26em] text-emerald-100/36">{{ __('Workspace layer') }}</p>
                    <div class="mt-4 flex items-center gap-4">
                        <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-emerald-400/14 text-emerald-100">
                            <i class="fa-light fa-fw fa-folder-open text-[1.05rem] leading-none"></i>
                        </span>
                        <h3 class="text-[2rem] font-semibold tracking-[-0.05em] text-white">{{ __('Assets and team control') }}</h3>
                    </div>

                    <p class="mt-5 text-sm leading-7 text-white/56">{{ __('Keep media, accounts, groups, permissions, and client workspaces organized from one shared system.') }}</p>

                    <div class="mt-8 grid gap-3">
                        @foreach ([__('Shared files and media library'), __('Groups, workspaces, and permissions'), __('Connected accounts and proxies')] as $item)
                            <div class="nova-soft-surface flex items-center gap-3 rounded-[1.2rem] border border-white/7 bg-white/[0.03] px-4 py-3.5">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-400/12 text-emerald-100">
                                    <i class="fa-light fa-fw fa-check text-[0.7rem] leading-none"></i>
                                </span>
                                <p class="text-sm text-white/66">{{ $item }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article data-reveal class="nova-tilt-hover nova-accent-surface--amber relative overflow-hidden rounded-[2.4rem] border border-amber-400/12 bg-[linear-gradient(135deg,rgba(84,50,12,0.34)_0%,rgba(28,18,17,0.96)_52%,rgba(10,12,24,0.98)_100%)] p-8 shadow-[0_28px_80px_-48px_rgba(0,0,0,0.85)] lg:col-span-8">
                <div class="pointer-events-none absolute right-0 top-0 h-full w-1/3 bg-[radial-gradient(circle_at_center,rgba(251,191,36,0.10),transparent_60%)]"></div>
                <div class="relative grid gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-end">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.26em] text-amber-100/38">{{ __('Reporting and visibility') }}</p>
                        <div class="mt-4 flex items-center gap-4">
                            <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-amber-400/14 text-amber-100">
                                <i class="fa-light fa-fw fa-chart-line text-[1.05rem] leading-none"></i>
                            </span>
                            <h3 class="text-3xl font-semibold tracking-[-0.05em] text-white">{{ __('Insights after publish') }}</h3>
                        </div>

                        <p class="mt-5 text-sm leading-7 text-white/56">{{ __('See what is going out, which queues stay active, and how the team can improve output across accounts and campaigns.') }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            ['label' => __('Publishing activity overview'), 'value' => 'Live'],
                            ['label' => __('Workflow and queue visibility'), 'value' => '24/7'],
                            ['label' => __('Account performance signals'), 'value' => 'Data'],
                            ['label' => __('Reporting for smarter planning'), 'value' => 'Ready'],
                        ] as $item)
                            <div class="nova-soft-surface rounded-[1.35rem] border border-white/8 bg-white/[0.035] px-4 py-4">
                                <p class="text-lg font-semibold text-white">{{ $item['value'] }}</p>
                                <p class="mt-2 text-sm leading-6 text-white/58">{{ $item['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="guest-marketing-shell guest-section-space mx-auto px-5 pt-0 lg:px-8">
        <div data-reveal class="mx-auto max-w-4xl text-center">
            <span class="inline-flex items-center rounded-full border border-emerald-400/14 bg-emerald-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-100/90">
                {{ __('Product modules') }}
            </span>
            <h2 class="mx-auto mt-6 max-w-4xl text-4xl font-semibold tracking-[-0.05em] text-white md:text-6xl">
                {{ __('Built around the work publishing teams do every day.') }}
            </h2>
            <p class="mx-auto mt-6 max-w-3xl text-base leading-8 text-white/54 md:text-lg">
                {{ __('Each module covers a clear job: planning, writing, media, scheduling, account control, and reporting.') }}
            </p>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                [
                    'icon' => 'fa-light fa-sparkles',
                    'title' => __('AI Captions'),
                    'body' => __('Generate captions, rewrites, hooks, and post variations faster so teams can publish more without losing brand consistency.'),
                    'accent' => 'text-violet-100 bg-violet-500/14',
                ],
                [
                    'icon' => 'fa-light fa-calendar-clock',
                    'title' => __('Smart Scheduling'),
                    'body' => __('Queue, plan, and schedule posts across connected social accounts with better timing and cleaner publishing control.'),
                    'accent' => 'text-cyan-100 bg-cyan-400/14',
                ],
                [
                    'icon' => 'fa-light fa-rectangle-history-circle-plus',
                    'title' => __('Bulk Publishing'),
                    'body' => __('Launch high-volume posting workflows, reuse content at scale, and keep campaigns moving across multiple channels.'),
                    'accent' => 'text-fuchsia-100 bg-fuchsia-500/14',
                ],
                [
                    'icon' => 'fa-light fa-rss',
                    'title' => __('RSS Automation'),
                    'body' => __('Turn feeds into scheduled social content automatically and keep evergreen publishing loops active with minimal work.'),
                    'accent' => 'text-emerald-100 bg-emerald-400/14',
                ],
                [
                    'icon' => 'fa-light fa-images',
                    'title' => __('Media Library'),
                    'body' => __('Manage images, videos, and reusable creative assets from one shared library built for faster campaign assembly.'),
                    'accent' => 'text-sky-100 bg-sky-400/14',
                ],
                [
                    'icon' => 'fa-light fa-people-group',
                    'title' => __('Teams and Workspaces'),
                    'body' => __('Separate clients, brands, and internal teams with workspace structure, permissions, and cleaner approval flow.'),
                    'accent' => 'text-amber-100 bg-amber-400/14',
                ],
                [
                    'icon' => 'fa-light fa-user-gear',
                    'title' => __('Account Management'),
                    'body' => __('Control connected accounts, proxies, groups, and operational settings from one place without losing visibility.'),
                    'accent' => 'text-rose-100 bg-rose-400/14',
                ],
                [
                    'icon' => 'fa-light fa-chart-column',
                    'title' => __('Analytics Reports'),
                    'body' => __('Track publishing output, account activity, and workflow performance with reporting that helps teams optimize faster.'),
                    'accent' => 'text-teal-100 bg-teal-400/14',
                ],
            ] as $module)
                <article data-reveal class="nova-tilt-hover nova-card-surface rounded-[2rem] border border-white/8 bg-[linear-gradient(180deg,rgba(14,17,31,0.94)_0%,rgba(10,13,25,0.88)_100%)] p-8 shadow-[0_24px_60px_-42px_rgba(0,0,0,0.65)]">
                    <span class="inline-flex h-16 w-16 items-center justify-center rounded-[1.4rem] {{ $module['accent'] }}">
                        <i class="{{ $module['icon'] }} text-[1.35rem]"></i>
                    </span>

                    <h3 class="mt-8 text-[1.42rem] leading-[1.18] font-semibold tracking-[-0.03em] text-white md:text-[1.5rem]">
                        {{ $module['title'] }}
                    </h3>

                    <p class="mt-5 text-base leading-8 text-white/56">
                        {{ $module['body'] }}
                    </p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="guest-marketing-shell guest-section-space mx-auto px-5 pt-0 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
            <article data-reveal class="nova-tilt-hover nova-card-surface rounded-[2rem] border border-white/8 bg-[linear-gradient(180deg,rgba(14,17,31,0.92)_0%,rgba(10,13,25,0.82)_100%)] p-8 shadow-[0_20px_60px_-42px_rgba(0,0,0,0.62)]">
                <span class="inline-flex items-center rounded-full border border-cyan-400/14 bg-cyan-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-cyan-100/80">
                    {{ __('How it flows') }}
                </span>
                <h2 class="mt-5 text-4xl font-semibold tracking-[-0.05em] text-white">{{ __('A workflow visitors can understand in under a minute.') }}</h2>
                <p class="mt-5 text-sm leading-8 text-white/56">{{ __('Show how raw ideas move into scheduled campaigns, reusable assets, and measurable results instead of stopping at a generic feature list.') }}</p>

                <div class="mt-8 space-y-4">
                    @foreach ([
                        ['step' => '01', 'title' => __('Collect inputs'), 'body' => __('Bring in drafts, RSS feeds, channel targets, prompts, and media assets.')],
                        ['step' => '02', 'title' => __('Generate and refine'), 'body' => __('Use AI to produce options, captions, and post variants, then review with the team.')],
                        ['step' => '03', 'title' => __('Schedule and publish'), 'body' => __('Push content into queues, calendars, and automation rules across channels.')],
                        ['step' => '04', 'title' => __('Track and iterate'), 'body' => __('Watch output, stabilize operations, and improve the workflow without leaving the system.')],
                    ] as $flow)
                        <div class="nova-tilt-hover nova-soft-surface flex gap-4 rounded-[1.6rem] border border-white/6 bg-white/[0.03] px-5 py-5">
                            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/[0.04] text-sm font-semibold tracking-[0.18em] text-white/72">{{ $flow['step'] }}</span>
                            <div>
                                <h3 class="text-lg font-semibold text-white">{{ $flow['title'] }}</h3>
                                <p class="mt-2 text-sm leading-7 text-white/56">{{ $flow['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>

            <div class="grid gap-6">
                <article data-reveal class="nova-tilt-hover nova-card-surface rounded-[2rem] border border-white/8 bg-[linear-gradient(180deg,rgba(14,17,31,0.92)_0%,rgba(10,13,25,0.82)_100%)] p-8 shadow-[0_20px_60px_-42px_rgba(0,0,0,0.62)]">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/28">{{ __('Integrations and destinations') }}</p>
                            <h3 class="mt-4 text-2xl font-semibold tracking-tight text-white">{{ __('Built to connect with the channels your team already uses.') }}</h3>
                        </div>
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-200">
                            <i class="fa-light fa-plug-circle-bolt text-xl"></i>
                        </span>
                    </div>

                    <div class="mt-8 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            ['label' => __('Facebook'), 'icon' => 'fa-brands fa-facebook-f', 'tint' => 'text-sky-200 bg-sky-400/10 border-sky-400/14'],
                            ['label' => __('Instagram'), 'icon' => 'fa-brands fa-instagram', 'tint' => 'text-pink-200 bg-pink-400/10 border-pink-400/14'],
                            ['label' => __('LinkedIn'), 'icon' => 'fa-brands fa-linkedin-in', 'tint' => 'text-cyan-100 bg-cyan-400/10 border-cyan-400/14'],
                            ['label' => __('X'), 'icon' => 'fa-brands fa-x-twitter', 'tint' => 'text-white bg-white/[0.06] border-white/10'],
                            ['label' => __('TikTok'), 'icon' => 'fa-brands fa-tiktok', 'tint' => 'text-fuchsia-100 bg-fuchsia-500/10 border-fuchsia-400/14'],
                            ['label' => __('RSS feeds'), 'icon' => 'fa-light fa-rss', 'tint' => 'text-amber-100 bg-amber-400/10 border-amber-400/14'],
                            ['label' => __('And more'), 'icon' => 'fa-light fa-plus', 'tint' => 'text-emerald-100 bg-emerald-400/10 border-emerald-400/14'],
                        ] as $integration)
                            <div class="nova-tilt-hover nova-soft-surface flex items-center gap-3 rounded-2xl border border-white/6 bg-white/[0.03] px-4 py-4 text-sm font-medium text-white/72">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.9rem] border {{ $integration['tint'] }}">
                                    <i class="{{ $integration['icon'] }} text-base"></i>
                                </span>
                                <span>{{ $integration['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article data-reveal class="nova-tilt-hover nova-card-surface rounded-[2rem] border border-white/8 bg-[linear-gradient(180deg,rgba(24,18,45,0.94)_0%,rgba(12,14,26,0.86)_100%)] p-8 shadow-[0_20px_60px_-42px_rgba(0,0,0,0.72)]">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/28">{{ __('Social proof') }}</p>
                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        @foreach ([
                            ['value' => '12h', 'label' => __('Average weekly hours saved')],
                            ['value' => '3.4x', 'label' => __('Faster campaign setup')],
                            ['value' => '91%', 'label' => __('Teams reporting cleaner ops')],
                        ] as $proof)
                            <div class="nova-tilt-hover nova-soft-surface rounded-[1.5rem] border border-white/6 bg-white/[0.03] px-5 py-5">
                                <p class="text-3xl font-semibold tracking-[-0.05em] text-white">{{ $proof['value'] }}</p>
                                <p class="mt-2 text-sm leading-7 text-white/52">{{ $proof['label'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="nova-deep-surface mt-6 rounded-[1.6rem] border border-white/6 bg-black/20 px-6 py-6">
                        <p class="text-base leading-8 text-white/70">
                            {{ __('“The landing page now explains the product like a real operating system for content teams, not just another generic SaaS tool.”') }}
                        </p>
                        <p class="mt-4 text-sm font-semibold text-violet-200">{{ __('Marketing team lead') }}</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    @php
        $homePricing = $homePricing ?? \Pricing::plansWithFeatures();
        $homePlanTypes = $homePlanTypes ?? collect(\Plan::getTypes())->filter(fn ($label, $key) => ! empty($homePricing[$key] ?? []));
        $homeDefaultType = $homeDefaultType ?? (int) ($homePlanTypes->keys()->first() ?? 1);
    @endphp

    @if ($homePlanTypes->isNotEmpty())
        <section x-data="{ type: {{ $homeDefaultType }} }" class="guest-marketing-shell guest-section-space mx-auto px-5 pb-[28rem] pt-0 lg:px-8 lg:pb-[22rem]">
            <div class="mx-auto max-w-4xl text-center">
                <span data-reveal class="inline-flex items-center rounded-full border border-cyan-400/14 bg-cyan-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-cyan-100/80">{{ __('Pricing') }}</span>
                <h2 data-reveal class="nova-stagger-1 mx-auto mt-5 max-w-4xl text-4xl font-semibold tracking-[-0.05em] text-white md:text-6xl">{{ __('Pick the plan that matches your publishing volume and team size.') }}</h2>
                <p data-reveal class="nova-stagger-2 mx-auto mt-5 max-w-3xl text-base leading-8 text-white/56 md:text-lg">{{ __('Clean pricing, scalable automation, and enough operational headroom to run support, sales, and campaign workflows from one place.') }}</p>

                <div data-reveal class="nova-stagger-3 mt-10 flex justify-center">
                    <div class="inline-flex items-center gap-1 rounded-full border border-white/10 bg-white/[0.03] p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)]">
                        @foreach ($homePlanTypes as $typeKey => $typeLabel)
                            <button
                                type="button"
                                x-on:click="type = {{ $typeKey }}"
                                :class="type === {{ $typeKey }} ? 'bg-emerald-500 text-white shadow-[0_12px_24px_-18px_rgba(16,185,129,0.6)]' : 'text-white/58 hover:bg-white/[0.05] hover:text-white'"
                                class="rounded-full px-5 py-2.5 text-sm font-semibold transition"
                            >
                                {{ $typeLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-14 xl:relative xl:min-h-[84rem] 2xl:min-h-[74rem]">
                @foreach ($homePlanTypes as $typeKey => $typeLabel)
                    @php
                        $plansForType = collect($homePricing[$typeKey] ?? []);
                    @endphp
                    <div x-cloak x-show="type === {{ $typeKey }}" x-transition.opacity.duration.200ms class="xl:absolute xl:inset-0" style="display: none;">
                        <div class="grid gap-6 xl:grid-cols-3">
                            @foreach ($plansForType as $plan)
                                <article data-reveal class="nova-tilt-hover flex h-full flex-col rounded-[2.2rem] border p-8 {{ $plan['featured'] ? 'nova-accent-surface--emerald border-emerald-400/24 bg-[linear-gradient(180deg,rgba(18,38,34,0.96)_0%,rgba(12,14,26,0.92)_100%)] shadow-[0_0_0_1px_rgba(16,185,129,0.08),0_40px_90px_-52px_rgba(16,185,129,0.45)]' : 'nova-card-surface border-white/8 bg-[linear-gradient(180deg,rgba(14,17,31,0.92)_0%,rgba(10,13,25,0.82)_100%)] shadow-[0_24px_60px_-42px_rgba(0,0,0,0.7)]' }}">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full {{ $plan['featured'] ? 'bg-emerald-400/12 text-emerald-100' : 'bg-white/[0.05] text-white/72' }} px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]">
                                                {{ $plan['name'] }}
                                            </span>
                                            @if (!($plan['free_plan'] ?? false) && (int) ($plan['trial_day'] ?? 0) > 0)
                                                <span class="inline-flex items-center rounded-full border border-amber-300/18 bg-amber-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-100">
                                                    {{ __(':days-day trial', ['days' => (int) $plan['trial_day']]) }}
                                                </span>
                                            @endif
                                        </div>
                                        @if ($plan['featured'])
                                            <span class="inline-flex items-center rounded-full border border-emerald-300/14 bg-emerald-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-100">
                                                {{ __('Most popular') }}
                                            </span>
                                        @endif
                                    </div>

                                    <p class="mt-7 text-base leading-8 text-white/56">{{ $plan['desc'] ?: __('A practical plan for daily publishing, media organization, team coordination, and automation.') }}</p>

                                    <div class="mt-8">
                                        <p class="text-6xl font-semibold tracking-[-0.08em] text-white">
                                            {{ $plan['free_plan'] ? ($plan['currency_symbol'] ?: '$').'0' : ($plan['currency_symbol'] ?: '$').rtrim(rtrim(number_format((float) $plan['price'], 2), '0'), '.') }}
                                            <span class="text-xl font-semibold tracking-normal text-white/42">/{{ strtolower($typeLabel) }}</span>
                                        </p>
                                        <p class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/38">{{ $plan['currency'] }} · {{ $plan['currency_name'] }}</p>
                                        <p class="mt-3 text-sm font-medium text-white/46">{{ match((int) $plan['type']) {2 => __('Billed yearly'), 3 => __('Pay once, use forever'), default => __('Billed monthly')} }}</p>
                                    </div>

                                    <div class="mt-8">
                                        @php
                                            $planTarget = $plan['model']->slug ?? $plan['id'];
                                        @endphp
                                        <a href="{{ $plan['free_plan'] ? (auth()->check() ? route('portal.dashboard') : ($signupEnabled ? route('register') : route('login'))) : route('payment.index', $planTarget) }}" class="inline-flex w-full items-center justify-center rounded-[1rem] {{ $plan['free_plan'] ? 'border border-emerald-400/24 bg-transparent text-emerald-100 hover:bg-emerald-400/8' : 'bg-emerald-500 text-white hover:bg-emerald-400' }} px-6 py-4 text-sm font-semibold transition">
                                            {{ $plan['free_plan'] ? __('Start for Free') : __('Choose Plan') }}
                                        </a>
                                    </div>

                                    <div class="nova-soft-surface mt-8 rounded-[1.5rem] border border-white/7 bg-white/[0.03] p-4">
                                        <p class="text-sm font-semibold text-white">{{ __('Included in this plan') }}</p>
                                        <p class="mt-2 text-xs leading-6 text-white/46">{{ __('Channels, limits, and automation controls included in this plan.') }}</p>
                                    </div>

                                    <div class="mt-7 flex-1 space-y-3">
                                        @foreach (($plan['features'] ?? []) as $feature)
                                            @continue(is_string($feature['label'] ?? null) && (stripos($feature['label'], 'approval') !== false || stripos($feature['label'], 'approvals') !== false))
                                            <div class="nova-soft-surface flex items-center justify-between gap-4 rounded-[1.15rem] border border-white/7 bg-white/[0.025] px-4 py-3.5">
                                                <div class="flex min-w-0 items-center gap-3">
                                                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $feature['check'] ? 'bg-emerald-400/12 text-emerald-100' : 'bg-white/[0.05] text-white/36' }}">
                                                        <i class="fa-light {{ $feature['check'] ? 'fa-check' : 'fa-minus' }} text-[11px]"></i>
                                                    </span>
                                                    <span class="truncate text-sm font-medium text-white/78">{{ $feature['label'] }}</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    @if(($feature['display'] ?? null) !== null && ($feature['display'] ?? '') !== '')
                                                        <span class="inline-flex items-center rounded-full bg-white/[0.05] px-2.5 py-1 text-[11px] font-semibold text-white/52">
                                                            {{ $feature['display'] }}
                                                        </span>
                                                    @endif
                                                    @if (!empty($feature['subfeature']))
                                                        <div x-data="{ open: false }" class="relative">
                                                            <button type="button" x-on:mouseenter="open = true" x-on:mouseleave="open = false" class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-white/[0.05] text-white/54 transition hover:bg-white/[0.09] hover:text-white">
                                                                <i class="fa-light fa-info text-[11px]"></i>
                                                            </button>
                                                            <div x-show="open" x-transition.opacity.duration.150ms x-on:mouseenter="open = true" x-on:mouseleave="open = false" class="nova-scrollbar nova-popover-surface absolute right-0 top-full z-[120] mt-3 max-h-[22rem] w-[20rem] overflow-y-auto overflow-x-hidden rounded-[1.25rem] border border-white/10 bg-[#0d1120] p-4 pr-3 shadow-[0_30px_80px_-40px_rgba(0,0,0,0.9)]" style="display: none; scrollbar-gutter: stable;">
                                                            @foreach (($feature['subfeature'] ?? []) as $tabGroup)
                                                                    <div class="mb-4 last:mb-0">
                                                                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-white/42">{{ $tabGroup['tab_name'] }}</p>
                                                                        <div class="space-y-2">
                                                                            @foreach ($tabGroup['items'] as $sub)
                                                                                <div class="nova-popover-soft flex items-center justify-between gap-3 rounded-[0.95rem] border border-white/7 bg-white/[0.03] px-3 py-2.5">
                                                                                    <div class="flex min-w-0 items-center gap-2">
                                                                                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $sub['check'] ? 'bg-emerald-400/12 text-emerald-100' : 'bg-white/[0.05] text-white/36' }}">
                                                                                            <i class="fa-light {{ $sub['check'] ? 'fa-check' : 'fa-minus' }} text-[10px]"></i>
                                                                                        </span>
                                                                                        <span class="truncate text-xs font-medium text-white/78">{{ $sub['label'] }}</span>
                                                                                    </div>
                                                                                    @if(($sub['display'] ?? null) !== null && ($sub['display'] ?? '') !== '')
                                                                                        <span class="inline-flex items-center rounded-full bg-white/[0.05] px-2 py-1 text-[10px] font-semibold text-white/52">{{ $sub['display'] }}</span>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($featuredFaqs->isNotEmpty())
        <section class="guest-marketing-shell guest-section-space mx-auto px-5 pt-0 pb-0 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[0.86fr_1.14fr] lg:items-start">
                <div data-reveal class="space-y-6">
                    <div>
                        <span class="inline-flex items-center rounded-full border border-emerald-400/14 bg-emerald-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-100/90">
                            {{ __('Frequently asked questions') }}
                        </span>
                        <h2 class="mt-5 max-w-xl text-[2rem] font-semibold leading-[1.1] tracking-[-0.03em] text-white md:text-[2.55rem]">
                            {{ __('Clear answers before you get started') }}
                        </h2>
                        <p class="mt-5 max-w-xl text-base leading-8 text-white/56">
                            {{ __('Find quick answers about setup, scheduling, accounts, workflow, and support.') }}
                        </p>
                    </div>

                    <div class="nova-card-surface max-w-xl rounded-[2rem] border border-white/8 p-6">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-400/12 text-emerald-100">
                                <i class="fa-light fa-headset text-lg"></i>
                            </span>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-100/70">{{ __('Need more help?') }}</p>
                                <h3 class="mt-2 text-2xl font-semibold text-white">{{ __('Talk to the team') }}</h3>
                                <p class="mt-3 text-sm leading-7 text-white/56">
                                    {{ __('If your flow is more complex than a quick FAQ can solve, contact us and we will help you map the right setup.') }}
                                </p>
                                <a href="{{ route('guest.contact') }}" class="mt-5 inline-flex items-center rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500">
                                    {{ __('Contact sales') }}
                                    <i class="fa-light fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-data="{ openFaq: 0 }" class="space-y-4">
                    @foreach ($featuredFaqs->take(5)->values() as $index => $faq)
                        <div data-reveal class="group nova-card-surface rounded-[1.9rem] border border-white/8 p-6 transition duration-300" x-bind:class="openFaq === {{ $index }} ? 'border-emerald-400/20 shadow-[0_20px_60px_-40px_rgba(16,185,129,0.22)]' : ''">
                            <button type="button" x-on:click="openFaq = openFaq === {{ $index }} ? -1 : {{ $index }}" class="flex w-full items-start justify-between gap-4 text-left">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-100/72">
                                        {{ __('FAQ') }} {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
                                    </p>
                                    <h3 class="mt-2 text-[1.16rem] font-semibold leading-[1.18] tracking-[-0.015em] text-white md:text-[1.28rem]">
                                        {{ $faq->titleForLocale() }}
                                    </h3>
                                </div>
                                <span class="nova-soft-surface inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 text-emerald-100 transition" x-bind:class="openFaq === {{ $index }} ? 'rotate-45' : ''">
                                    <i class="fa-light fa-plus text-base"></i>
                                </span>
                            </button>

                            <div
                                class="grid overflow-hidden transition-all duration-300 ease-out"
                                style="grid-template-rows: 0fr; opacity: 0;"
                                x-bind:style="openFaq === {{ $index }} ? 'grid-template-rows: 1fr; opacity: 1; margin-top: 1.25rem;' : 'grid-template-rows: 0fr; opacity: 0; margin-top: 0;'"
                            >
                                <div class="min-h-0 overflow-hidden">
                                    <div class="nova-soft-surface rounded-[1.5rem] border border-white/8 px-5 py-5">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-100/66">{{ __('Answer') }}</p>
                                        <div class="mt-3 max-w-3xl text-sm leading-8 text-white/58 whitespace-pre-line">
                                            {{ $faq->contentPreview(240) !== '' ? $faq->contentPreview(240) : $faq->contentForLocale() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($latestBlogs->isNotEmpty())
        <section class="guest-marketing-shell guest-section-space mx-auto px-5 pt-0 lg:px-8">
            <div data-reveal class="mb-10 flex items-end justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/28">{{ __('Insights') }}</p>
                    <h2 class="mt-4 text-4xl font-semibold tracking-[-0.05em] text-white">{{ __('Guides and product updates') }}</h2>
                </div>
                <a href="{{ route('guest.blogs') }}" class="hidden rounded-full border border-white/10 px-5 py-3 text-sm font-semibold text-white/72 transition hover:bg-white/8 hover:text-white md:inline-flex">{{ __('View all posts') }}</a>
            </div>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($latestBlogs as $blog)
                    @include(theme_view('partials.blog-card', 'guest'), ['blog' => $blog, 'compact' => true])
                @endforeach
            </div>
        </section>
    @endif
@endsection
