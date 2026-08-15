@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex flex-col sm:flex-row items-center justify-between gap-3 w-full py-2">
            <!-- Info Jumlah Data -->
            <div class="text-xs sm:text-sm text-gray-500 font-medium text-center sm:text-left">
                Menampilkan <span class="font-bold text-gray-800">{{ $paginator->firstItem() ?? 0 }}</span> &ndash; <span class="font-bold text-gray-800">{{ $paginator->lastItem() ?? 0 }}</span> dari <span class="font-bold text-gray-800">{{ $paginator->total() }}</span> data
            </div>

            <!-- Kontrol Tombol Pagination Sesuai pagination.png -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <!-- Tombol Sebelumnya (<) -->
                @if ($paginator->onFirstPage())
                    <span class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center text-gray-300 cursor-not-allowed">
                        <x-lucide-chevron-left class="w-5 h-5"/>
                    </span>
                @else
                    <button
                        type="button"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center text-(--text-colour) hover:text-(--prime-colour) hover:bg-gray-100 rounded-xl transition cursor-pointer"
                        aria-label="{{ __('pagination.previous') }}"
                    >
                        <x-lucide-chevron-left class="w-5 h-5"/>
                    </button>
                @endif

                <!-- Angka Halaman -->
                @foreach ($elements as $element)
                    {{-- Separator Titik Tiga (...) --}}
                    @if (is_string($element))
                        <span class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center bg-(--fg-colour) border border-(--outline-colour) rounded-xl text-xs sm:text-sm font-semibold text-gray-600 shadow-2xs">
                            {{ $element }}
                        </span>
                    @endif

                    {{-- Link Halaman --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                @if ($page == $paginator->currentPage())
                                    <span class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center bg-(--prime-colour) text-(--fg-colour) rounded-xl text-xs sm:text-sm font-bold shadow-2xs">
                                        {{ $page }}
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                        class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center bg-(--fg-colour) border border-(--outline-colour) hover:border-(--prime-colour) hover:bg-emerald-50/50 rounded-xl text-xs sm:text-sm font-semibold text-(--text-colour) shadow-2xs transition cursor-pointer"
                                        aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                    >
                                        {{ $page }}
                                    </button>
                                @endif
                            </span>
                        @endforeach
                    @endif
                @endforeach

                <!-- Tombol Berikutnya (>) -->
                @if ($paginator->hasMorePages())
                    <button
                        type="button"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center text-(--text-colour) hover:text-(--prime-colour) hover:bg-gray-100 rounded-xl transition cursor-pointer"
                        aria-label="{{ __('pagination.next') }}"
                    >
                        <x-lucide-chevron-right class="w-5 h-5"/>
                    </button>
                @else
                    <span class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center text-gray-300 cursor-not-allowed">
                        <x-lucide-chevron-right class="w-5 h-5"/>
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
