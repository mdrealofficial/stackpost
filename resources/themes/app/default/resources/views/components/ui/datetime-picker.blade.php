@props([
    'name' => null,
    'label' => null,
    'value' => null,
    'error' => null,
    'placeholder' => null,
    'pickerAlign' => 'left',
    'pickerPosition' => 'bottom',
])

@php
    $inputAttributes = $attributes->except('class');
@endphp

<div {{ $attributes->only('class')->class('space-y-2.5') }}>
    @if ($label)
        <x-ui.label>{{ $label }}</x-ui.label>
    @endif

    <div
        class="relative"
        x-modelable="value"
        x-data="{
            open: false,
            value: @js($value),
            pickerAlign: @js($pickerAlign),
            pickerPosition: @js($pickerPosition),
            resolvedAlign: @js($pickerAlign === 'right' ? 'right' : 'left'),
            resolvedPosition: @js($pickerPosition === 'top' ? 'top' : 'bottom'),
            viewDate: null,
            selectedDate: null,
            hour: '09',
            minute: '00',
            meridiem: 'AM',
            dayNames: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
            monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            hours: Array.from({ length: 12 }, (_, index) => String(index + 1).padStart(2, '0')),
            minutes: Array.from({ length: 60 }, (_, index) => String(index).padStart(2, '0')),
            init() {
                this.hydrateFromValue(this.value);
            },
            toggleOpen() {
                this.open = !this.open;

                if (this.open) {
                    this.$nextTick(() => this.updatePlacement());
                }
            },
            updatePlacement() {
                const trigger = this.$refs.trigger;
                const panel = this.$refs.panel;

                if (!trigger || !panel) {
                    return;
                }

                const gap = 12;
                const triggerRect = trigger.getBoundingClientRect();
                const panelWidth = panel.offsetWidth || 560;
                const panelHeight = panel.offsetHeight || 420;
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;

                if (this.pickerPosition === 'auto') {
                    const spaceBelow = viewportHeight - triggerRect.bottom;
                    const spaceAbove = triggerRect.top;
                    this.resolvedPosition = spaceBelow >= (panelHeight + gap) || spaceBelow >= spaceAbove ? 'bottom' : 'top';
                } else {
                    this.resolvedPosition = this.pickerPosition === 'top' ? 'top' : 'bottom';
                }

                if (this.pickerAlign === 'auto') {
                    const spaceRight = viewportWidth - triggerRect.left;
                    const spaceLeft = triggerRect.right;
                    this.resolvedAlign = spaceRight >= panelWidth || spaceRight >= spaceLeft ? 'left' : 'right';
                } else {
                    this.resolvedAlign = this.pickerAlign === 'right' ? 'right' : 'left';
                }
            },
            today() {
                const now = new Date();
                return new Date(now.getFullYear(), now.getMonth(), now.getDate());
            },
            parseDateTime(value) {
                if (!value) {
                    return null;
                }

                const [datePart, timePart = '00:00'] = String(value).split('T');
                const dateSegments = datePart.split('-').map(Number);
                const timeSegments = timePart.split(':').map(Number);

                if (dateSegments.length !== 3 || dateSegments.some(Number.isNaN)) {
                    return null;
                }

                const [year, month, day] = dateSegments;
                const hour = Number.isNaN(timeSegments[0]) ? 0 : timeSegments[0];
                const minute = Number.isNaN(timeSegments[1]) ? 0 : timeSegments[1];

                return new Date(year, month - 1, day, hour, minute, 0, 0);
            },
            hydrateFromValue(value) {
                const parsed = this.parseDateTime(value);
                const fallback = new Date();
                fallback.setMinutes(0, 0, 0);
                fallback.setHours(fallback.getHours() + 1);

                const date = parsed || fallback;
                this.selectedDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                this.viewDate = new Date(date.getFullYear(), date.getMonth(), 1);

                let hours = date.getHours();
                this.meridiem = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12 || 12;
                this.hour = String(hours).padStart(2, '0');
                this.minute = String(date.getMinutes()).padStart(2, '0');

                if (!parsed && !this.value) {
                    this.value = this.formatValue();
                    this.syncInput();
                }
            },
            formatValue() {
                if (!this.selectedDate) {
                    return '';
                }

                let hours = Number(this.hour);
                const minutes = Number(this.minute);

                if (this.meridiem === 'PM' && hours < 12) {
                    hours += 12;
                }

                if (this.meridiem === 'AM' && hours === 12) {
                    hours = 0;
                }

                const year = this.selectedDate.getFullYear();
                const month = String(this.selectedDate.getMonth() + 1).padStart(2, '0');
                const day = String(this.selectedDate.getDate()).padStart(2, '0');
                const hour = String(hours).padStart(2, '0');
                const minute = String(minutes).padStart(2, '0');

                return `${year}-${month}-${day}T${hour}:${minute}`;
            },
            formatDisplay(value) {
                const date = this.parseDateTime(value);

                if (!date) {
                    return @js($placeholder ?: __('Select date & time'));
                }

                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                let hours = date.getHours();
                const minutes = String(date.getMinutes()).padStart(2, '0');
                const meridiem = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12 || 12;

                return `${day}/${month}/${date.getFullYear()} ${String(hours).padStart(2, '0')}:${minutes} ${meridiem}`;
            },
            monthLabel() {
                return `${this.monthNames[this.viewDate.getMonth()]} ${this.viewDate.getFullYear()}`;
            },
            previousMonth() {
                this.viewDate = new Date(this.viewDate.getFullYear(), this.viewDate.getMonth() - 1, 1);
            },
            nextMonth() {
                this.viewDate = new Date(this.viewDate.getFullYear(), this.viewDate.getMonth() + 1, 1);
            },
            selectDate(date) {
                this.selectedDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                this.viewDate = new Date(date.getFullYear(), date.getMonth(), 1);
                this.commit();
            },
            clear() {
                this.value = '';
                this.selectedDate = null;
                this.open = false;
            },
            pickNow() {
                const now = new Date();
                this.selectedDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());

                let hours = now.getHours();
                this.meridiem = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12 || 12;
                this.hour = String(hours).padStart(2, '0');
                this.minute = String(now.getMinutes() - (now.getMinutes() % 5)).padStart(2, '0');
                this.viewDate = new Date(now.getFullYear(), now.getMonth(), 1);
                this.commit();
            },
            isSelected(day) {
                return day.currentMonth
                    && this.selectedDate
                    && day.date.getFullYear() === this.selectedDate.getFullYear()
                    && day.date.getMonth() === this.selectedDate.getMonth()
                    && day.date.getDate() === this.selectedDate.getDate();
            },
            isToday(day) {
                const today = this.today();

                return day.date.getFullYear() === today.getFullYear()
                    && day.date.getMonth() === today.getMonth()
                    && day.date.getDate() === today.getDate();
            },
            days() {
                const year = this.viewDate.getFullYear();
                const month = this.viewDate.getMonth();
                const firstDay = new Date(year, month, 1);
                const startOffset = firstDay.getDay();
                const gridStart = new Date(year, month, 1 - startOffset);
                const days = [];

                for (let index = 0; index < 42; index += 1) {
                    const date = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + index);

                    days.push({
                        key: `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`,
                        date,
                        number: date.getDate(),
                        currentMonth: date.getMonth() === month,
                    });
                }

                return days;
            },
            commit(close = false) {
                this.value = this.formatValue();
                this.syncInput();

                if (close) {
                    this.open = false;
                }
            },
            syncInput() {
                if (!this.$refs.input) {
                    return;
                }

                this.$refs.input.value = this.value || '';
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            },
        }"
        x-on:resize.window="if (open) updatePlacement()"
        x-on:scroll.window="if (open) updatePlacement()"
        x-on:keydown.escape.window="open = false"
    >
        <input x-ref="input" type="hidden" @if($name) name="{{ $name }}" @endif x-bind:value="value" {{ $inputAttributes }}>

        <button
            x-ref="trigger"
            type="button"
            class="flex h-11 w-full items-center justify-between gap-3 border px-4 text-left text-sm font-medium shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
            style="border-radius: var(--theme-input-radius, 0.75rem); border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
            x-on:click="toggleOpen()"
        >
            <span :class="value ? '' : 'text-[var(--theme-input-placeholder)]'" x-text="formatDisplay(value)"></span>
            <i class="fa-light fa-calendar-days text-sm" style="color: var(--theme-muted-text-color);"></i>
        </button>

        <div
            x-ref="panel"
            x-show="open"
            x-transition.opacity.scale
            x-on:click.outside="open = false"
            style="display: none; background-color: var(--theme-surface-overlay); border-color: var(--theme-border-color);"
            x-bind:class="{
                'left-0': resolvedAlign === 'left',
                'right-0': resolvedAlign === 'right',
                'top-[calc(100%+0.65rem)] origin-top': resolvedPosition === 'bottom',
                'bottom-[calc(100%+0.65rem)] origin-bottom': resolvedPosition === 'top',
            }"
            class="absolute z-40 w-[20rem] max-w-[min(20rem,calc(100vw-1rem))] overflow-hidden rounded-[1rem] border shadow-[0_28px_70px_-28px_rgba(15,23,42,0.35)]"
        >
            <div class="p-4">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="monthLabel()"></p>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-[0.8rem] transition hover:bg-slate-50 dark:hover:bg-slate-800"
                            style="color: var(--theme-header-text-color);"
                            x-on:click="previousMonth()"
                            aria-label="{{ __('Previous month') }}"
                        >
                            <i class="fa-light fa-arrow-up text-sm"></i>
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-[0.8rem] transition hover:bg-slate-50 dark:hover:bg-slate-800"
                            style="color: var(--theme-header-text-color);"
                            x-on:click="nextMonth()"
                            aria-label="{{ __('Next month') }}"
                        >
                            <i class="fa-light fa-arrow-down text-sm"></i>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-7 gap-0.5 text-center">
                    <template x-for="dayName in dayNames" :key="dayName">
                        <div class="pb-2 text-[11px] font-semibold" style="color: var(--theme-header-text-color);" x-text="dayName"></div>
                    </template>

                    <template x-for="day in days()" :key="day.key">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-[0.75rem] text-sm font-medium transition"
                            :class="{
                                'text-slate-400 dark:text-slate-500': !day.currentMonth && !isSelected(day),
                                'text-[var(--theme-header-text-color)]': day.currentMonth && !isSelected(day),
                                'bg-[var(--theme-accent)] text-white shadow-[0_14px_24px_-16px_rgba(var(--theme-accent-rgb),0.8)]': isSelected(day),
                                'ring-1 ring-[color:rgba(var(--theme-accent-rgb),0.28)]': isToday(day) && !isSelected(day),
                            }"
                            x-on:click="selectDate(day.date)"
                        >
                            <span x-text="day.number"></span>
                        </button>
                    </template>
                </div>

                <div class="mt-4 border-t pt-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Hour') }}</p>
                            <select
                                x-model="hour"
                                x-on:change="commit()"
                                class="flex h-11 w-full rounded-[0.75rem] border px-3 text-sm font-semibold shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                                style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                            >
                                <template x-for="option in hours" :key="'hour-option-'+option">
                                    <option x-bind:value="option" x-text="option"></option>
                                </template>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Min') }}</p>
                            <select
                                x-model="minute"
                                x-on:change="commit()"
                                class="flex h-11 w-full rounded-[0.75rem] border px-3 text-sm font-semibold shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                                style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                            >
                                <template x-for="option in minutes" :key="'minute-option-'+option">
                                    <option x-bind:value="option" x-text="option"></option>
                                </template>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('AM/PM') }}</p>
                            <select
                                x-model="meridiem"
                                x-on:change="commit()"
                                class="flex h-11 w-full rounded-[0.75rem] border px-3 text-sm font-semibold shadow-[0_1px_2px_rgba(15,23,42,0.04)] outline-none transition duration-200 focus:border-[var(--theme-accent)] focus:ring-4 focus:ring-[color:rgba(var(--theme-accent-rgb),0.10)]"
                                style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);"
                            >
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t pt-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <button
                        type="button"
                        class="text-sm font-semibold transition hover:opacity-80"
                        style="color: var(--theme-accent);"
                        x-on:click="clear()"
                    >
                        {{ __('Clear') }}
                    </button>

                    <button
                        type="button"
                        class="text-sm font-semibold transition hover:opacity-80"
                        style="color: var(--theme-accent);"
                        x-on:click="pickNow()"
                    >
                        {{ __('Now') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($error)
        <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $error }}</p>
    @endif
</div>
