<x-filament-panels::page>
    <div wire:poll.300s="refreshData" class="space-y-6">

        {{-- Header Control Section --}}
        <x-filament::section>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-lg bg-primary-500/10 text-primary-500">
                        <x-heroicon-m-signal class="w-6 h-6" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold tracking-tight text-gray-950 dark:text-white">
                            Verifikasi TAF Realtime (WITN)
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Stasiun Meteorologi Maimun Saleh Sabang
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800/60 px-3.5 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 text-xs">
                        <span class="text-gray-500 dark:text-gray-400 font-medium">Tanggal:</span>
                        <input type="date" wire:model.live="selectedDate" class="bg-transparent border-0 text-xs font-semibold text-primary-600 dark:text-primary-400 focus:ring-0 p-0 cursor-pointer">
                    </div>

                    <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800/60 px-3.5 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 text-xs">
                        <span class="text-gray-500 dark:text-gray-400 font-medium">Stasiun:</span>
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">WITN</span>
                    </div>

                    <x-filament::button wire:click="refreshData" icon="heroicon-m-arrow-path" size="sm">
                        Refresh Data
                    </x-filament::button>
                </div>
            </div>
        </x-filament-section>

        @php
            $summary = $this->realtimeData['summary'];
            $rows = $this->realtimeData['rows'];

            // Map Data 24-Jam UTC
            $hourlyStatus = [];
            for ($h = 0; $h < 24; $h++) {
                $hStr = sprintf('%02d', $h);
                $slot00 = null;
                $slot30 = null;

                foreach ($rows as $r) {
                    if (str_starts_with($r['time'], $hStr . ':00')) $slot00 = $r;
                    if (str_starts_with($r['time'], $hStr . ':30')) $slot30 = $r;
                }

                $hasTaf = ($slot00 && ($slot00['taf_raw'] ?? '') !== 'Data TAF Belum Tersedia')
                       || ($slot30 && ($slot30['taf_raw'] ?? '') !== 'Data TAF Belum Tersedia');

                $hasMetar = ($slot00 && ($slot00['metar_raw'] ?? '') !== 'Data METAR Belum Tersedia')
                         || ($slot30 && ($slot30['metar_raw'] ?? '') !== 'Data METAR Belum Tersedia');

                $score00 = ($slot00 && is_array($slot00['scores'] ?? null)) ? array_sum($slot00['scores']) : null;
                $score30 = ($slot30 && is_array($slot30['scores'] ?? null)) ? array_sum($slot30['scores']) : null;

                $hourlyStatus[$h] = [
                    'taf' => $hasTaf,
                    'metar' => $hasMetar,
                    'score00' => $score00,
                    'score30' => $score30,
                ];
            }
        @endphp

        {{-- Panel Monitoring Matriks 24-Jam --}}
        <x-filament::section icon="heroicon-m-clock" header="Panel Monitoring Ketersediaan & Verifikasi TAF Stasiun (24 Jam UTC)">
            <div class="overflow-x-auto">
                <table class="w-full text-center text-xs border-collapse divide-y divide-gray-200 dark:divide-white/10">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5 font-bold text-gray-700 dark:text-gray-300">
                            <th class="py-2.5 px-3 text-left w-24">JAM (UTC)</th>
                            @for($h = 0; $h < 24; $h++)
                                <th class="py-2.5 px-1 w-8 font-mono">{{ sprintf('%02d', $h) }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5 font-mono text-[11px]">
                        <tr>
                            <td class="py-2.5 px-3 text-left font-semibold text-gray-900 dark:text-gray-200">TAF</td>
                            @for($h = 0; $h < 24; $h++)
                                <td class="py-2.5 px-1">
                                    @if($hourlyStatus[$h]['taf'])
                                        <x-filament::badge color="success" size="sm">On</x-filament::badge>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 text-left font-semibold text-gray-900 dark:text-gray-200">METAR</td>
                            @for($h = 0; $h < 24; $h++)
                                <td class="py-2.5 px-1">
                                    @if($hourlyStatus[$h]['metar'])
                                        <x-filament::badge color="success" size="sm">On</x-filament::badge>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 text-left font-semibold text-gray-900 dark:text-gray-200">VERIFIKASI</td>
                            @for($h = 0; $h < 24; $h++)
                                @php
                                    $s00 = $hourlyStatus[$h]['score00'];
                                    $s30 = $hourlyStatus[$h]['score30'];
                                    $isFullPass = ($s00 === 7 && ($s30 === 7 || $s30 === null));
                                    $hasDev = ($s00 !== null && $s00 < 7) || ($s30 !== null && $s30 < 7);
                                @endphp
                                <td class="py-2.5 px-1">
                                    @if($isFullPass)
                                        <x-filament::badge color="info" size="sm">100%</x-filament::badge>
                                    @elseif($hasDev)
                                        <x-filament::badge color="danger" size="sm">Dev</x-filament::badge>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-600">-</span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Stat Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Akurasi Total TAF</span>
                    <x-filament::badge :color="$summary['accuracy'] >= 80 ? 'success' : 'warning'">
                        {{ $summary['accuracy'] >= 80 ? 'EXCELLENT' : 'WARNING' }}
                    </x-filament::badge>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-extrabold tracking-tight text-gray-950 dark:text-white">
                        {{ $summary['accuracy'] }}%
                    </span>
                </div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Total {{ $summary['total'] }} Slot Evaluasi
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slot Sesuai SOP</span>
                    <x-filament::badge color="success">PASSED</x-filament::badge>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-extrabold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ $summary['passed'] }}
                    </span>
                </div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ $summary['total'] > 0 ? round(($summary['passed'] / $summary['total']) * 100) : 0 }}% Kesesuaian
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slot Deviasi</span>
                    <x-filament::badge color="danger">DEVIATION</x-filament::badge>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-extrabold tracking-tight text-rose-600 dark:text-rose-400">
                        {{ $summary['failed'] }}
                    </span>
                </div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ $summary['failed'] }} Slot Terdegradasi
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Parameter SOP
                </div>
                <div class="space-y-1 overflow-y-auto max-h-20 pr-1">
                    @foreach($summary['paramStats'] as $param)
                        @php
                            $rate = $param['total'] > 0 ? round(($param['pass'] / $param['total']) * 100) : 0;
                        @endphp
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-300 truncate max-w-[110px]">{{ $param['label'] }}</span>
                            <span class="font-mono font-bold text-primary-600 dark:text-primary-400">{{ $rate }}%</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        {{-- Table Log Stream --}}
        <x-filament::section icon="heroicon-m-queue-list" header="Data Stream Log Verifikasi Realtime (Interval 30-Menit)">
            <div class="overflow-x-auto -mx-6 -mb-6">
                <table class="w-full text-left text-xs divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5 text-gray-700 dark:text-gray-300 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Jam (UTC)</th>
                            <th class="py-3 px-3">Group</th>
                            <th class="py-3 px-4 min-w-[320px]">Isi Sandi TAF & METAR Raw</th>
                            <th class="py-3 px-2 text-center">Dir</th>
                            <th class="py-3 px-2 text-center">Spd</th>
                            <th class="py-3 px-2 text-center">Gust</th>
                            <th class="py-3 px-2 text-center">Vis</th>
                            <th class="py-3 px-2 text-center">Precip</th>
                            <th class="py-3 px-2 text-center">Cloud</th>
                            <th class="py-3 px-2 text-center">Ceiling</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5 font-mono">
                        @forelse($rows as $row)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                                <td class="py-3 px-4 font-bold text-primary-600 dark:text-primary-400 whitespace-nowrap">{{ $row['time'] }}</td>
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $row['group'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="py-3 px-4 space-y-1">
                                    <div class="p-2 rounded bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-[11px] leading-relaxed">
                                        <span class="text-primary-600 dark:text-primary-400 font-bold mr-1.5">[TAF]</span>{{ $row['taf_raw'] }}
                                    </div>
                                    <div class="p-2 rounded bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-[11px] leading-relaxed">
                                        <span class="text-emerald-600 dark:text-emerald-400 font-bold mr-1.5">[METAR]</span>{{ $row['metar_raw'] }}
                                    </div>
                                </td>

                                @if($row['scores'])
                                    @foreach(['wind_dir', 'wind_speed', 'gust', 'visibility', 'precip', 'cloud', 'ceiling'] as $param)
                                        <td class="py-3 px-2 text-center whitespace-nowrap">
                                            @if(($row['scores'][$param] ?? 0) == 1)
                                                <x-filament::badge color="success" size="sm">1</x-filament::badge>
                                            @else
                                                <x-filament::badge color="danger" size="sm">0</x-filament::badge>
                                            @endif
                                        </td>
                                    @endforeach
                                @else
                                    <td colspan="7" class="py-3 px-4 text-center italic text-gray-400 dark:text-gray-500 text-[11px]">
                                        Data belum lengkap
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                    Tidak ada data verifikasi untuk rentang waktu ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>