@props([
    'label' => null,
    'align' => 'left',
])

@php
    $wireModel = $attributes->wire('model')->value();
@endphp

<div
    x-data="{
        open: false,
        value: @entangle($attributes->wire('model')),
        viewYear: new Date().getFullYear(),
        viewMonth: new Date().getMonth(),
        monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        monthsFull: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
        daysOfWeek: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],

        init() {
            this.syncView();
            this.$watch('value', () => this.syncView());
        },

        syncView() {
            if (this.value) {
                const parts = this.value.split('-');
                if (parts.length === 3) {
                    this.viewYear = parseInt(parts[0], 10);
                    this.viewMonth = parseInt(parts[1], 10) - 1;
                }
            }
        },

        get formattedDisplay() {
            if (!this.value) return 'Pilih Tanggal';
            const parts = this.value.split('-');
            if (parts.length !== 3) return this.value;
            const y = parts[0];
            const mIdx = parseInt(parts[1], 10) - 1;
            const d = String(parseInt(parts[2], 10)).padStart(2, '0');
            const mName = this.monthsShort[mIdx] || parts[1];
            return `${d} ${mName} ${y}`;
        },

        get currentMonthLabel() {
            return this.monthsFull[this.viewMonth] + ' ' + this.viewYear;
        },

        prevMonth() {
            if (this.viewMonth === 0) {
                this.viewMonth = 11;
                this.viewYear--;
            } else {
                this.viewMonth--;
            }
        },

        nextMonth() {
            if (this.viewMonth === 11) {
                this.viewMonth = 0;
                this.viewYear++;
            } else {
                this.viewMonth++;
            }
        },

        selectDate(y, m, d) {
            const mStr = String(m + 1).padStart(2, '0');
            const dStr = String(d).padStart(2, '0');
            this.value = `${y}-${mStr}-${dStr}`;
            this.open = false;
        },

        selectToday() {
            const today = new Date();
            this.selectDate(today.getFullYear(), today.getMonth(), today.getDate());
        },

        isSelected(y, m, d) {
            if (!this.value) return false;
            const mStr = String(m + 1).padStart(2, '0');
            const dStr = String(d).padStart(2, '0');
            return this.value === `${y}-${mStr}-${dStr}`;
        },

        isToday(y, m, d) {
            const today = new Date();
            return today.getFullYear() === y && today.getMonth() === m && today.getDate() === d;
        },

        get calendarDays() {
            const days = [];
            const firstDayIndex = new Date(this.viewYear, this.viewMonth, 1).getDay();
            const startOffset = (firstDayIndex + 6) % 7; // Mulai hari Senin = 0

            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
            const daysInPrevMonth = new Date(this.viewYear, this.viewMonth, 0).getDate();

            // Hari bulan sebelumnya
            for (let i = startOffset - 1; i >= 0; i--) {
                const prevM = this.viewMonth === 0 ? 11 : this.viewMonth - 1;
                const prevY = this.viewMonth === 0 ? this.viewYear - 1 : this.viewYear;
                days.push({
                    day: daysInPrevMonth - i,
                    month: prevM,
                    year: prevY,
                    isCurrent: false
                });
            }

            // Hari bulan ini
            for (let i = 1; i <= daysInMonth; i++) {
                days.push({
                    day: i,
                    month: this.viewMonth,
                    year: this.viewYear,
                    isCurrent: true
                });
            }

            // Hari bulan berikutnya
            const remaining = (7 - (days.length % 7)) % 7;
            for (let i = 1; i <= remaining; i++) {
                const nextM = this.viewMonth === 11 ? 0 : this.viewMonth + 1;
                const nextY = this.viewMonth === 11 ? this.viewYear + 1 : this.viewYear;
                days.push({
                    day: i,
                    month: nextM,
                    year: nextY,
                    isCurrent: false
                });
            }

            return days;
        }
    }"
    class="relative inline-block"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <!-- Trigger Button Input (Format dd Mon yyyy, Harmonized dengan style input-text) -->
    <div class="flex items-center gap-(--size-10)">
        @if($label)
            <span class="text-(length:--size-16) font-normal text-(--text-colour) whitespace-nowrap">{{ $label }}:</span>
        @endif
        <button
            type="button"
            @click="open = !open"
            class="rounded-(--size-16) inline-flex justify-between items-center gap-(--size-10) input-text text-(--size-16) hover:bg-(--bg2-colour) cursor-pointer whitespace-nowrap shrink-0 transition-all focus:outline-none focus:ring-1 focus:ring-(--prime-colour)"
            x-bind:class="open ? 'border-(--prime-colour) ring-1 ring-(--prime-colour)' : ''"
        >
            <span class="font-bold text-[#163428]" x-text="formattedDisplay"></span>
            <x-lucide-chevron-down class="w-(--size-16) text-gray-500 transition-transform duration-200" x-bind:class="open ? 'rotate-180' : ''"/>
        </button>
    </div>

    <!-- Kalender Dropdown Popover -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="absolute {{ $align === 'right' ? 'right-0' : 'left-0' }} top-full mt-(--size-10) w-72 bg-(--fg-colour) border-[1.5px] border-(--outline-colour) rounded-(--size-16) shadow-xl z-50 p-4 select-none"
        x-cloak
    >
        <!-- Header Navigasi Bulan & Tahun -->
        <div class="flex items-center justify-between pb-3 mb-2 border-b border-gray-100">
            <button
                type="button"
                @click="prevMonth()"
                class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-600 transition cursor-pointer"
                title="Bulan Sebelumnya"
            >
                <x-lucide-chevron-left class="w-4 h-4"/>
            </button>

            <span class="font-bold text-sm text-(--prime-colour)" x-text="currentMonthLabel"></span>

            <button
                type="button"
                @click="nextMonth()"
                class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-600 transition cursor-pointer"
                title="Bulan Berikutnya"
            >
                <x-lucide-chevron-right class="w-4 h-4"/>
            </button>
        </div>

        <!-- Nama Hari dalam Seminggu (Sen - Min) -->
        <div class="grid grid-cols-7 gap-1 text-center mb-1">
            <template x-for="dayName in daysOfWeek" :key="dayName">
                <span class="text-[11px] font-bold text-gray-400 py-1" x-text="dayName"></span>
            </template>
        </div>

        <!-- Grid Tanggal -->
        <div class="grid grid-cols-7 gap-1 text-center">
            <template x-for="(cell, index) in calendarDays" :key="index">
                <button
                    type="button"
                    @click="selectDate(cell.year, cell.month, cell.day)"
                    class="h-8 w-8 mx-auto rounded-lg text-xs font-semibold flex items-center justify-center transition cursor-pointer"
                    x-bind:class="{
                        'bg-(--prime-colour) text-(--fg-colour) font-bold shadow-xs': isSelected(cell.year, cell.month, cell.day),
                        'hover:bg-emerald-50 text-gray-800': cell.isCurrent && !isSelected(cell.year, cell.month, cell.day),
                        'text-gray-300 hover:text-gray-500': !cell.isCurrent && !isSelected(cell.year, cell.month, cell.day),
                        'ring-1 ring-(--prime-colour)': isToday(cell.year, cell.month, cell.day) && !isSelected(cell.year, cell.month, cell.day)
                    }"
                    x-text="cell.day"
                ></button>
            </template>
        </div>

        <!-- Footer: Tombol Pintas Hari Ini & Tutup -->
        <div class="flex items-center justify-between pt-3 mt-2 border-t border-gray-100 text-xs">
            <button
                type="button"
                @click="selectToday()"
                class="text-(--prime-colour) font-bold hover:underline cursor-pointer flex items-center gap-1"
            >
                <x-lucide-clock class="w-3.5 h-3.5"/>
                <span>Hari Ini</span>
            </button>

            <button
                type="button"
                @click="open = false"
                class="text-gray-400 hover:text-gray-600 cursor-pointer"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
