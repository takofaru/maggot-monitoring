<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div
    x-data="{
        show: false,
        title: '',
        message: '',
        confirmText: 'Konfirmasi',
        cancelText: 'Batal',
        variant: 'danger',
        icon: 'trash',
        onConfirmCallback: null,
        onCancelCallback: null,

        init() {
            window.$confirm = (options) => {
                this.openModal(options);
            };
        },

        openModal(detail) {
            this.title = detail.title || 'Konfirmasi Tindakan';
            this.message = detail.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
            this.confirmText = detail.confirmText || (detail.variant === 'danger' ? 'Hapus' : 'Konfirmasi');
            this.cancelText = detail.cancelText || 'Batal';
            this.variant = detail.variant || 'danger';
            this.icon = detail.icon || (this.variant === 'danger' ? 'trash' : (this.variant === 'primary' ? 'chevrons-right' : 'alert-triangle'));
            this.onConfirmCallback = typeof detail.onConfirm === 'function' ? detail.onConfirm : null;
            this.onCancelCallback = typeof detail.onCancel === 'function' ? detail.onCancel : null;
            this.show = true;
        },

        handleConfirm() {
            if (this.onConfirmCallback) {
                this.onConfirmCallback();
            }
            this.show = false;
        },

        handleCancel() {
            if (this.onCancelCallback) {
                this.onCancelCallback();
            }
            this.show = false;
        }
    }"
    x-on:show-confirm-modal.window="openModal($event.detail)"
    x-on:keydown.escape.window="if (show) handleCancel()"
    class="relative"
>
    <!-- Modal Backdrop & Dialog Container -->
    <div
        x-show="show"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <!-- Backdrop Backdrop Blur -->
        <div
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="handleCancel()"
            class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity"
        ></div>

        <!-- Dialog Box -->
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div
                x-show="show"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-(--size-26) bg-white border border-(--outline-colour)/60 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md p-6"
            >
                <div class="flex items-start gap-4">
                    <!-- Icon Visual Indicator -->
                    <div class="shrink-0">
                        <template x-if="variant === 'danger'">
                            <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center border border-red-200">
                                <x-lucide-trash-2 class="w-6 h-6"/>
                            </div>
                        </template>
                        <template x-if="variant === 'primary'">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-[#163428] flex items-center justify-center border border-emerald-300">
                                <x-lucide-chevrons-right class="w-6 h-6"/>
                            </div>
                        </template>
                        <template x-if="variant === 'warning'">
                            <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center border border-amber-300">
                                <x-lucide-alert-triangle class="w-6 h-6"/>
                            </div>
                        </template>
                        <template x-if="variant !== 'danger' && variant !== 'primary' && variant !== 'warning'">
                            <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-700 flex items-center justify-center border border-gray-300">
                                <x-lucide-help-circle class="w-6 h-6"/>
                            </div>
                        </template>
                    </div>

                    <!-- Title and Message -->
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base font-bold text-gray-900 leading-snug" id="modal-title" x-text="title"></h3>
                        <p class="mt-1.5 text-xs text-gray-600 leading-relaxed whitespace-pre-line" x-text="message"></p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                        type="button"
                        @click="handleCancel()"
                        class="px-4 py-2.5 rounded-xl border border-gray-300 bg-white font-semibold text-xs text-gray-700 hover:bg-gray-50 focus:outline-none transition cursor-pointer shadow-2xs"
                        x-text="cancelText"
                    >
                    </button>

                    <button
                        type="button"
                        @click="handleConfirm()"
                        class="px-5 py-2.5 rounded-xl font-bold text-xs text-white focus:outline-none transition cursor-pointer shadow-xs"
                        :class="{
                            'bg-red-600 hover:bg-red-700 focus:ring-2 focus:ring-red-500': variant === 'danger',
                            'bg-[#163428] hover:bg-[#1e4435] focus:ring-2 focus:ring-emerald-600': variant === 'primary',
                            'bg-amber-600 hover:bg-amber-700 focus:ring-2 focus:ring-amber-500': variant === 'warning',
                            'bg-gray-800 hover:bg-gray-900': variant !== 'danger' && variant !== 'primary' && variant !== 'warning'
                        }"
                        x-text="confirmText"
                    >
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
