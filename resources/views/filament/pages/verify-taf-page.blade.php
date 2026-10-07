<x-filament-panels::page>
    <div wire:poll.300s="refreshData" class="space-y-6">
        
        {{-- Header Controls --}}
        <div class="flex flex-col md:flex-row items-center justify-between gap-4 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
                <div>
                    <label class="text-xs text-gray-500 font-medium">Tanggal Verifikasi</label>
                    <input type="date" wire:model.live="selectedDate" class="block w-full text-sm rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600">
                </div>
                <div>
                    <label class="text-xs text-gray-500 font-medium">Stasiun</label>
                    <input type="text" wire:model="icao" readonly class="block w-24 text-sm rounded-lg border-gray-300 bg-gray-100 font-bold dark:bg-gray-700">
                </div>
            </div>

            <x-filament::button wire:click="refreshData" icon="heroicon-m-arrow-path" color="primary">
                Perbarui Data Realtime
            </x-filament::button>
        </div>

        {{-- Tabel Verifikasi Realtime --}}
        <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-700 dark:text-gray-200 font-semibold uppercase">
                    <tr>
                        <th class="p-3">Jam (UTC)</th>
                        <th class="p-3">Group</th>
                        <th class="p-3">TAF & METAR Raw</th>
                        <th class="p-3 text-center">Angin Dir</th>
                        <th class="p-3 text-center">Angin Spd</th>
                        <th class="p-3 text-center">Gust</th>
                        <th class="p-3 text-center">Vis</th>
                        <th class="p-3 text-center">Cuaca</th>
                        <th class="p-3 text-center">Awan</th>
                        <th class="p-3 text-center">Ceiling</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($this->realtimeVerification as $row)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30">
                            <td class="p-3 font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ $row['time'] }}</td>
                            <td class="p-3"><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-bold text-[10px]">{{ $row['group'] }}</span></td>
                            <td class="p-3 space-y-1">
                                <div class="text-[11px] font-mono text-blue-600 dark:text-blue-400">TAF: {{ $row['taf_raw'] }}</div>
                                <div class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400">METAR: {{ $row['metar_raw'] }}</div>
                            </td>

                            @if($row['scores'])
                                @foreach(['wind_dir', 'wind_speed', 'gust', 'visibility', 'precip', 'cloud', 'ceiling'] as $param)
                                    <td class="p-3 text-center">
                                        @if($row['scores'][$param] == 1)
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-700 font-bold rounded-full">✓</span>
                                        @else
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-rose-100 text-rose-700 font-bold rounded-full">✕</span>
                                        @endif
                                    </td>
                                @endforeach
                            @else
                                <td colspan="7" class="p-3 text-center italic text-gray-400">Data belum lengkap</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-4 text-center text-gray-500">Tidak ada data verifikasi untuk tanggal ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-filament-panels::page>