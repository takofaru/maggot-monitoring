<?php

use Livewire\Component;
use App\Models\ActivityLog;
use App\Models\EnvironmentLog;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

new class extends Component
{
    public int $unreadCount = 0;
    public ?int $lastKnownId = null;

    public function mount()
    {
        $this->lastKnownId = ActivityLog::max('id') ?? 0;
    }

    public function markAllAsRead()
    {
        ActivityLog::where('is_read', false)->update(['is_read' => true]);
        $this->unreadCount = 0;
    }

    public function markAsRead(int $id)
    {
        ActivityLog::where('id', $id)->update(['is_read' => true]);
    }

    public function with(): array
    {
        // 1. Evaluasi Status Koneksi IoT Terkini (Liveness Check)
        $latestEnv = EnvironmentLog::latest('timestamp')->latest('id')->first();
        $cachedLastSeenStr = Cache::get('device_last_seen');
        $cachedLastSeen = $cachedLastSeenStr ? Carbon::parse($cachedLastSeenStr) : null;
        $dbLastSeen = $latestEnv ? Carbon::parse($latestEnv->timestamp ?? $latestEnv->created_at) : null;

        $lastSeen = ($cachedLastSeen && $dbLastSeen)
            ? ($cachedLastSeen->greaterThan($dbLastSeen) ? $cachedLastSeen : $dbLastSeen)
            : ($cachedLastSeen ?? $dbLastSeen);

        $diffInSeconds = $lastSeen ? (int) abs(now()->diffInSeconds($lastSeen, false)) : null;
        $isDeviceOnline = ($diffInSeconds !== null && $diffInSeconds <= 20);

        // Catat transisi status perangkat jika ada perubahan
        NotificationService::evaluateDeviceStatus($isDeviceOnline);

        // 2. Query Log Aktivitas & Notifikasi Terbaru
        $notifications = ActivityLog::latest('id')->take(20)->get();
        $unreadCount = ActivityLog::where('is_read', false)->count();

        // 3. Deteksi Notifikasi Baru untuk Pop-up Toast
        $latestMaxId = $notifications->first()?->id ?? 0;
        $newToasts = [];

        if ($this->lastKnownId !== null && $latestMaxId > $this->lastKnownId) {
            $newItems = ActivityLog::where('id', '>', $this->lastKnownId)->orderBy('id', 'asc')->get();
            foreach ($newItems as $item) {
                $newToasts[] = [
                    'id'          => $item->id,
                    'type'        => $item->type,
                    'title'       => $item->title,
                    'description' => $item->description,
                    'time'        => $item->created_at ? $item->created_at->diffForHumans() : 'Baru saja',
                ];
            }
            $this->lastKnownId = $latestMaxId;
            if (!empty($newToasts)) {
                $this->dispatch('show-toast-notifications', toasts: $newToasts);
            }
        } elseif ($this->lastKnownId === null) {
            $this->lastKnownId = $latestMaxId;
        }

        return [
            'notifications'  => $notifications,
            'unreadCount'    => $unreadCount,
            'isDeviceOnline' => $isDeviceOnline,
        ];
    }
};
?>

<div 
    wire:poll.4s
    x-data="{
        open: false,
        toasts: [],
        addToast(toast) {
            const id = toast.id || Date.now() + Math.random();
            const newToast = { ...toast, internalId: id };
            this.toasts.push(newToast);
            setTimeout(() => this.removeToast(id), 6000);
        },
        removeToast(id) {
            this.toasts = this.toasts.filter(t => t.internalId !== id);
        }
    }"
    x-on:show-toast-notifications.window="
        let list = $event.detail.toasts || [$event.detail];
        if (Array.isArray(list)) {
            list.forEach(item => addToast(item));
        }
    "
    class="relative"
>
    <!-- Tombol Lonceng Notifikasi -->
    <button 
        @click="open = !open" 
        type="button"
        class="relative p-2.5 bg-white border border-(--outline-colour) rounded-full shadow-xs hover:bg-gray-50 focus:outline-none transition-all duration-200 flex items-center justify-center cursor-pointer"
        aria-label="Lihat Notifikasi"
    >
        <x-lucide-bell class="w-5 h-5 text-(--prime-colour)"/>
        
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 flex h-5 min-w-[20px] px-1 items-center justify-center">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex items-center justify-center rounded-full h-4 min-w-[16px] px-1 bg-red-600 text-[10px] font-bold text-white leading-none">
                    {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                </span>
            </span>
        @endif
    </button>

    <!-- Popover Dropdown Notifikasi -->
    <div 
        x-show="open" 
        @click.outside="open = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        style="display: none;"
        class="absolute right-0 mt-3 w-84 sm:w-96 bg-white border border-gray-200 rounded-2xl shadow-2xl z-50 overflow-hidden"
    >
        <!-- Header Popover -->
        <div class="px-4 py-3.5 bg-gray-50/80 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-gray-900">Aktivitas & Notifikasi</span>
                @if($unreadCount > 0)
                    <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-semibold rounded-full">
                        {{ $unreadCount }} baru
                    </span>
                @endif
            </div>
            @if($unreadCount > 0)
                <button 
                    wire:click="markAllAsRead" 
                    type="button" 
                    class="text-xs text-emerald-700 hover:text-emerald-900 font-semibold cursor-pointer transition"
                >
                    Tandai dibaca
                </button>
            @endif
        </div>

        <!-- Daftar Log Notifikasi -->
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
            @forelse($notifications as $item)
                <div 
                    wire:key="notif-{{ $item->id }}"
                    @class([
                        'p-3.5 flex items-start gap-3 hover:bg-gray-50/80 transition-colors',
                        'bg-emerald-50/30' => !$item->is_read,
                    ])
                >
                    <!-- Ikon Indikator sesuai Tipe -->
                    <div class="shrink-0 mt-0.5">
                        @if($item->type === 'observation')
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center">
                                <x-lucide-notebook-pen class="w-4 h-4"/>
                            </div>
                        @elseif($item->type === 'temp_alert')
                            <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center">
                                <x-lucide-thermometer class="w-4 h-4"/>
                            </div>
                        @elseif($item->type === 'humid_alert')
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-800 flex items-center justify-center">
                                <x-lucide-droplets class="w-4 h-4"/>
                            </div>
                        @elseif($item->type === 'device_status')
                            <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-800 flex items-center justify-center">
                                <x-lucide-cpu class="w-4 h-4"/>
                            </div>
                        @else
                            <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-800 flex items-center justify-center">
                                <x-lucide-bell class="w-4 h-4"/>
                            </div>
                        @endif
                    </div>

                    <!-- Konten Notifikasi -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1">
                            <p class="text-xs font-bold text-gray-900 truncate">
                                {{ $item->title }}
                            </p>
                            @if(!$item->is_read)
                                <span class="w-2 h-2 rounded-full bg-emerald-600 shrink-0"></span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-600 mt-0.5 leading-relaxed">
                            {{ $item->description }}
                        </p>
                        <p class="text-[10px] text-gray-400 mt-1 font-medium">
                            {{ $item->created_at ? $item->created_at->diffForHumans() : 'Baru saja' }}
                        </p>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-400">
                    <x-lucide-bell-off class="w-8 h-8 mx-auto mb-2 opacity-40"/>
                    <p class="text-xs font-medium">Belum ada riwayat notifikasi.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Container Floating Toast Pop-up Notifications (Muncul di Pojok Kanan Atas Layar) -->
    <div 
        class="fixed top-6 right-6 z-50 flex flex-col gap-3 pointer-events-none max-w-sm w-full"
        style="min-width: 320px;"
    >
        <template x-for="toast in toasts" :key="toast.internalId">
            <div 
                x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 translate-x-8 scale-95"
                x-transition:enter-end="opacity-100 translate-x-0 scale-100"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="opacity-100 translate-x-0 scale-100"
                x-transition:leave-end="opacity-0 translate-x-8 scale-95"
                class="pointer-events-auto bg-white/95 backdrop-blur-md border border-gray-200/90 shadow-2xl rounded-2xl p-4 flex items-start gap-3.5 transition"
            >
                <!-- Ikon Toast -->
                <div class="shrink-0 mt-0.5">
                    <template x-if="toast.type === 'observation'">
                        <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center shadow-xs">
                            <x-lucide-notebook-pen class="w-4 h-4"/>
                        </div>
                    </template>
                    <template x-if="toast.type === 'temp_alert'">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shadow-xs">
                            <x-lucide-thermometer class="w-4 h-4"/>
                        </div>
                    </template>
                    <template x-if="toast.type === 'humid_alert'">
                        <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center shadow-xs">
                            <x-lucide-droplets class="w-4 h-4"/>
                        </div>
                    </template>
                    <template x-if="toast.type === 'device_status'">
                        <div class="w-8 h-8 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center shadow-xs">
                            <x-lucide-cpu class="w-4 h-4"/>
                        </div>
                    </template>
                    <template x-if="toast.type !== 'observation' && toast.type !== 'temp_alert' && toast.type !== 'humid_alert' && toast.type !== 'device_status'">
                        <div class="w-8 h-8 rounded-xl bg-gray-100 text-gray-800 flex items-center justify-center shadow-xs">
                            <x-lucide-bell class="w-4 h-4"/>
                        </div>
                    </template>
                </div>

                <!-- Teks Notifikasi -->
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-gray-900" x-text="toast.title"></p>
                    <p class="text-xs text-gray-600 mt-0.5 leading-relaxed" x-text="toast.description"></p>
                    <p class="text-[10px] text-gray-400 mt-1 font-medium" x-text="toast.time || 'Baru saja'"></p>
                </div>

                <!-- Tombol Tutup Toast -->
                <button 
                    @click="removeToast(toast.internalId)" 
                    type="button"
                    class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition shrink-0 cursor-pointer"
                >
                    <x-lucide-x class="w-3.5 h-3.5"/>
                </button>
            </div>
        </template>
    </div>
</div>
