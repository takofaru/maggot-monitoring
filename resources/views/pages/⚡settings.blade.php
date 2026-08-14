<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div class="space-y-(--size-26)">
    <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold">
        Pengaturan Perangkat
    </h1>
    <form wire:submit="changePhaseSettings" id="changePhaseSettingsForm" class="space-y-(--size-26)">
        <div class="inline-grid grid-rows-2 grid-cols-2 grid-flow-row gap-(--size-26) w-full">
            <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) min-w-[624px] w-full">
                <div class="flex flex-row gap-(--size-16) items-center">
                    <x-lucide-egg class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16)"/>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Bertelur</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container">
                        <label for="tempLimit">Batas Suhu</label>
                        <div id="tempLimit" class="flex flex-row gap-(--size-10) items-center">
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="eggTempMin"
                                    id="eggTempMin"
                                    type="number"
                                    min="0"
                                    max="999.99"
                                    placeholder="Masukkan Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                &deg;C
                            </div>
                            sampai
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="eggTempMax"
                                    id="eggTempMax"
                                    type="number"
                                    min="0"
                                    max="999.99"
                                    placeholder="Masukkan Nilai Maximum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                &deg;C
                            </div>
                        </div>
                    </div>
                    <div class="input-container">
                        <label for="humidLimit">Batas Kelembapan</label>
                        <div id="humidLimit" class="flex flex-row gap-(--size-10) items-center">
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="eggHumidMin"
                                    id="eggHumidMin"
                                    type="number"
                                    min="0"
                                    max="100.00"
                                    placeholder="Masukkan Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                %
                            </div>
                            sampai
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="eggHumidMax"
                                    id="eggHumidMax"
                                    type="number"
                                    min="0"
                                    max="100.00"
                                    placeholder="Masukkan Nilai Maximum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                %
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) min-w-[624px] w-full">
                <div class="flex flex-row gap-(--size-16) items-center">
                    <x-lucide-worm class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16)"/>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Larva</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container">
                        <label for="tempLimit">Batas Suhu</label>
                        <div id="tempLimit" class="flex flex-row gap-(--size-10) items-center">
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="larvaTempMin"
                                    id="larvaTempMin"
                                    type="number"
                                    min="0"
                                    max="999.99"
                                    placeholder="Masukkan Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                &deg;C
                            </div>
                            sampai
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="larvaTempMax"
                                    id="larvaTempMax"
                                    type="number"
                                    min="0"
                                    max="999.99"
                                    placeholder="Masukkan Nilai Maximum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                &deg;C
                            </div>
                        </div>
                    </div>
                    <div class="input-container">
                        <label for="humidLimit">Batas Kelembapan</label>
                        <div id="humidLimit" class="flex flex-row gap-(--size-10) items-center">
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="larvaHumidMin"
                                    id="larvaHumidMin"
                                    type="number"
                                    min="0"
                                    max="100.00"
                                    placeholder="Masukkan Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                %
                            </div>
                            sampai
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="larvaHumidMax"
                                    id="larvaHumidMax"
                                    type="number"
                                    min="0"
                                    max="100.00"
                                    placeholder="Masukkan Nilai Maximum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                %
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) min-w-[624px] w-full">
                <div class="flex flex-row gap-(--size-16) items-center">
                    <x-lucide-heart class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16)"/>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Pupa</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container">
                        <label for="tempLimit">Batas Suhu</label>
                        <div id="tempLimit" class="flex flex-row gap-(--size-10) items-center">
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="pupaTempMin"
                                    id="pupaTempMin"
                                    type="number"
                                    min="0"
                                    max="999.99"
                                    placeholder="Masukkan Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                &deg;C
                            </div>
                            sampai
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="pupaTempMax"
                                    id="pupaTempMax"
                                    type="number"
                                    min="0"
                                    max="999.99"
                                    placeholder="Masukkan Nilai Maximum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                &deg;C
                            </div>
                        </div>
                    </div>
                    <div class="input-container">
                        <label for="humidLimit">Batas Kelembapan</label>
                        <div id="humidLimit" class="flex flex-row gap-(--size-10) items-center">
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="pupaHumidMin"
                                    id="pupaHumidMin"
                                    type="number"
                                    min="0"
                                    max="100.00"
                                    placeholder="Masukkan Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                %
                            </div>
                            sampai
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="pupaHumidMax"
                                    id="pupaHumidMax"
                                    type="number"
                                    min="0"
                                    max="100.00"
                                    placeholder="Masukkan Nilai Maximum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                %
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42) bg-(--fg-colour) border-(--outline-colour) border-[1.5px] rounded-(--size-16) min-w-[624px] w-full">
                <div class="flex flex-row gap-(--size-16) items-center">
                    <x-lucide-bug class="w-[46px] text-(--fg-colour) p-(--size-10) bg-(--prime-colour) rounded-(--size-16)"/>
                    <span class="text-(--prime-colour) text-(length:--size-26) font-bold">Fase Dewasa</span>
                </div>
                <div class="flex flex-col gap-(--size-16)">
                    <div class="input-container">
                        <label for="tempLimit">Batas Suhu</label>
                        <div id="tempLimit" class="flex flex-row gap-(--size-10) items-center">
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="adultTempMin"
                                    id="adultsTempMin"
                                    type="number"
                                    min="0"
                                    max="999.99"
                                    placeholder="Masukkan Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                &deg;C
                            </div>
                            sampai
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="adultTempMax"
                                    id="adultTempMax"
                                    type="number"
                                    min="0"
                                    max="999.99"
                                    placeholder="Masukkan Nilai Maximum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                &deg;C
                            </div>
                        </div>
                    </div>
                    <div class="input-container">
                        <label for="humidLimit">Batas Kelembapan</label>
                        <div id="humidLimit" class="flex flex-row gap-(--size-10) items-center">
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="adultHumidMin"
                                    id="adultHumidMin"
                                    type="number"
                                    min="0"
                                    max="100.00"
                                    placeholder="Masukkan Nilai Minimum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                %
                            </div>
                            sampai
                            <div class="flex flex-row items-center justify-between input-text w-full">
                                <input
                                    wire:model="adultHumidMax"
                                    id="adultHumidMax"
                                    type="number"
                                    min="0"
                                    max="100.00"
                                    placeholder="Masukkan Nilai Maximum"
                                    class="w-full bg-transparent focus:outline-none"
                                />
                                %
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <button type="submit" class="input-button w-full">
            <x-lucide-save class="w-(--size-26)"/>
            Simpan Pengaturan
        </button>
    </form>
</div>
