@php
    $type = $type ?? 'qr';
    $label = $label ?? null;
    $title = $title ?? null;
    $icon = $icon ?? 'fa-light fa-qrcode';
    $qrBits = [1,1,1,0,1,1,1,1,0,1,1,0,0,1,1,1,0,1,0,1,1,0,1,1,1,0,1,0,1,0,1,0,1,1,1,1,1,0,1,0,1,0,1,0,1,1,1,1,1];
@endphp

<div class="localboost-visual localboost-visual-{{ $type }}">
    <div class="localboost-visual-grid"></div>

    @if (in_array($type, ['bio', 'retail', 'workspace'], true))
        <div class="localboost-phone">
            <span></span>
            <div class="localboost-avatar"></div>
            <div class="localboost-line w-2/3"></div>
            <div class="localboost-line w-1/2"></div>
            <div class="mt-4 space-y-2">
                @foreach ([80, 64, 72] as $width)
                    <div class="localboost-button-line" style="width: {{ $width }}%;"></div>
                @endforeach
            </div>
        </div>
        <div class="localboost-floating-qr">
            @foreach ($qrBits as $bit)
                <span class="{{ $bit ? 'is-on' : '' }}"></span>
            @endforeach
        </div>
    @elseif (in_array($type, ['analytics', 'alerts'], true))
        <div class="localboost-chart-card">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ __('Live report') }}</span>
                <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700">+18%</span>
            </div>
            <div class="mt-6 flex h-28 items-end gap-2">
                @foreach ([42, 76, 55, 88, 66, 94, 72, 84] as $bar)
                    <span class="localboost-visual-bar" style="height: {{ $bar }}%; animation-delay: {{ $loop->index * 120 }}ms;"></span>
                @endforeach
            </div>
            <svg class="absolute inset-x-6 bottom-10 h-16 w-[calc(100%-3rem)]" viewBox="0 0 360 90" fill="none" aria-hidden="true">
                <path class="localboost-visual-stroke" d="M4 68 C55 22 92 78 132 38 C176 -6 204 74 246 32 C286 -4 318 46 356 18" />
            </svg>
        </div>
    @elseif (in_array($type, ['qr', 'rules'], true))
        <div class="localboost-route-map">
            <div class="localboost-route-qr">
                @foreach ($qrBits as $bit)
                    <span class="{{ $bit ? 'is-on' : '' }}"></span>
                @endforeach
            </div>
            @foreach ([[68,18,'VN'], [76,58,'US'], [34,72,'17:00']] as $node)
                <div class="localboost-route-node" style="left: {{ $node[0] }}%; top: {{ $node[1] }}%;">{{ $node[2] }}</div>
            @endforeach
            <svg class="absolute inset-0 h-full w-full" viewBox="0 0 520 240" fill="none" aria-hidden="true">
                <path class="localboost-route-path" d="M140 120 C230 40 300 46 365 48" />
                <path class="localboost-route-path" d="M140 120 C250 120 330 135 404 150" style="animation-delay:.35s" />
                <path class="localboost-route-path" d="M140 120 C196 190 236 202 286 186" style="animation-delay:.7s" />
            </svg>
        </div>
    @elseif ($type === 'domain')
        <div class="localboost-domain-visual">
            <div class="localboost-domain-card">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                    <div class="h-3 w-36 rounded-full bg-slate-200"></div>
                </div>
                <div class="mt-4 grid gap-2">
                    <div class="rounded-xl bg-sky-50 px-3 py-2 text-xs font-extrabold text-sky-700">CNAME go.brand.com</div>
                    <div class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-extrabold text-emerald-700">SSL active</div>
                    <div class="rounded-xl bg-white px-3 py-2 text-xs font-extrabold text-slate-500">TXT verified</div>
                </div>
            </div>
            <div class="localboost-domain-node">{{ __('LIVE') }}</div>
            <svg class="absolute inset-0 h-full w-full" viewBox="0 0 520 240" fill="none" aria-hidden="true">
                <path class="localboost-route-path" d="M72 166 C180 86 286 178 430 118" />
            </svg>
        </div>
    @elseif ($type === 'utm')
        <div class="localboost-utm-visual">
            <div class="localboost-utm-card">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-orange-500">{{ __('Preset builder') }}</p>
                <div class="mt-3">
                    <span class="localboost-utm-pill">utm_source</span>
                    <span class="localboost-utm-pill">utm_campaign</span>
                    <span class="localboost-utm-pill">utm_content</span>
                    <span class="localboost-utm-pill">brand</span>
                </div>
                <div class="mt-4 h-3 w-4/5 rounded-full bg-orange-100"></div>
                <div class="mt-2 h-3 w-2/3 rounded-full bg-pink-100"></div>
            </div>
            <div class="absolute bottom-6 right-5 z-10 rounded-2xl bg-slate-950 px-4 py-3 text-xs font-extrabold text-white shadow-xl">+ auto append</div>
        </div>
    @elseif ($type === 'team')
        <div class="localboost-team-visual">
            <div class="localboost-team-card">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-violet-500">{{ __('Client workspace') }}</p>
                <div class="mt-4 space-y-2">
                    @foreach ([['Owner', 'Admin'], ['Editor', 'Campaigns'], ['Client', 'Reports']] as $member)
                        <div class="localboost-member-row">
                            <span class="localboost-member-avatar"></span>
                            <div class="min-w-0 flex-1">
                                <div class="h-2.5 w-20 rounded-full bg-slate-300"></div>
                                <div class="mt-1.5 h-2 w-14 rounded-full bg-slate-200"></div>
                            </div>
                            <span class="rounded-full bg-violet-50 px-2 py-1 text-[10px] font-extrabold text-violet-700">{{ $member[0] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="localboost-dashboard-stack">
            <div class="localboost-mini-window">
                <div class="flex gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                    <span class="h-2 w-2 rounded-full bg-amber-300"></span>
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-2">
                    @foreach ([82, 54, 70] as $value)
                        <div class="rounded-xl bg-slate-100 p-3">
                            <div class="h-2 rounded-full bg-blue-100"></div>
                            <div class="mt-3 h-8 rounded-lg bg-gradient-to-r from-blue-500 to-teal-500" style="width: {{ $value }}%;"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 space-y-2">
                    <div class="h-3 w-4/5 rounded-full bg-slate-200"></div>
                    <div class="h-3 w-2/3 rounded-full bg-slate-200"></div>
                </div>
            </div>
        </div>
    @endif

    <span class="localboost-glass-badge absolute left-4 top-4 z-10 inline-flex h-11 w-11 items-center justify-center rounded-[0.9rem] text-blue-700">
        <i class="{{ $icon }}"></i>
    </span>
    @if ($title)
        <div class="absolute bottom-4 left-4 right-4 z-10 rounded-[1rem] border bg-white/92 p-4 shadow-[0_18px_44px_-30px_rgba(15,23,42,0.48)] backdrop-blur" style="border-color: rgba(var(--theme-border-color-rgb),0.76);">
            @if ($label)
                <p class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-blue-700">{{ $label }}</p>
            @endif
            <h3 class="mt-1 text-2xl font-extrabold tracking-[-0.045em] text-slate-950">{{ $title }}</h3>
        </div>
    @elseif ($label)
        <span class="absolute bottom-4 left-4 z-10 rounded-full bg-slate-950/72 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-white backdrop-blur">{{ $label }}</span>
    @endif
</div>
