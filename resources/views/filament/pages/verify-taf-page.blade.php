<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div wire:poll.300s="refreshData" class="space-y-6">

        {{-- Top Bar & Control Section --}}
        <x-filament::section>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-primary-500/10 text-primary-500">
                        <x-heroicon-m-signal class="w-6 h-6 animate-pulse" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-950 dark:text-white">
                            Verifikasi TAF Realtime (WITN)
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Stasiun Meteorologi Maimun Saleh Sabang
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800/80 px-3.5 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 text-xs">
                        <span class="text-gray-500 dark:text-gray-400 font-medium">Tanggal:</span>
                        <input type="date" wire:model.live="selectedDate" class="bg-transparent border-0 text-xs font-bold text-primary-600 dark:text-primary-400 focus:ring-0 p-0 cursor-pointer">
                    </div>

                    <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800/80 px-3.5 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 text-xs">
                        <span class="text-gray-500 dark:text-gray-400 font-medium">Stasiun:</span>
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">WITN</span>
                    </div>

                    <x-filament::button wire:click="refreshData" icon="heroicon-m-arrow-path" size="sm">
                        Sync Data
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

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

        {{-- Section Utama: Side-by-Side Matrix & Pie Chart --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- 1. Matriks Ketersediaan Data (8 Kolom) --}}
            <div class="lg:col-span-8">
                <x-filament::section icon="heroicon-m-clock" header="Matriks Ketersediaan Data Telemetri (24 Jam UTC)">
                    <div class="overflow-x-auto">
                        <table class="w-full text-center text-xs border-collapse">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-white/5 font-mono text-[11px] text-gray-600 dark:text-gray-300">
                                    <th class="py-2.5 px-3 text-left w-20 font-sans font-bold">JAM</th>
                                    @for($h = 0; $h < 24; $h++)
                                        <th class="py-2.5 px-0.5 min-w-[30px]">{{ sprintf('%02d', $h) }}</th>
                                    @endfor
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5 font-mono text-[10px]">
                                {{-- Row TAF (Cyan Theme) --}}
                                <tr>
                                    <td class="py-2.5 px-3 text-left font-sans font-bold text-cyan-600 dark:text-cyan-400">TAF</td>
                                    @for($h = 0; $h < 24; $h++)
                                        <td class="py-2.5 px-0.5">
                                            @if($hourlyStatus[$h]['taf'])
                                                <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] font-bold bg-cyan-500/20 text-cyan-700 dark:text-cyan-300 border border-cyan-500/30">On</span>
                                            @else
                                                <span class="inline-flex items-center justify-center w-7 py-0.5 text-gray-400 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>

                                {{-- Row METAR (Emerald Theme) --}}
                                <tr>
                                    <td class="py-2.5 px-3 text-left font-sans font-bold text-emerald-600 dark:text-emerald-400">METAR</td>
                                    @for($h = 0; $h < 24; $h++)
                                        <td class="py-2.5 px-0.5">
                                            @if($hourlyStatus[$h]['metar'])
                                                <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">On</span>
                                            @else
                                                <span class="inline-flex items-center justify-center w-7 py-0.5 text-gray-400 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>

                                {{-- Row VERIFIKASI (Indigo/Rose Theme) --}}
                                <tr>
                                    <td class="py-2.5 px-3 text-left font-sans font-bold text-indigo-600 dark:text-indigo-400">VERIF</td>
                                    @for($h = 0; $h < 24; $h++)
                                        @php
                                            $s00 = $hourlyStatus[$h]['score00'];
                                            $s30 = $hourlyStatus[$h]['score30'];
                                            $isFull = ($s00 === 7 && ($s30 === 7 || $s30 === null));
                                            $hasDev = ($s00 !== null && $s00 < 7) || ($s30 !== null && $s30 < 7);
                                        @endphp
                                        <td class="py-2.5 px-0.5">
                                            @if($isFull)
                                                <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] font-extrabold bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">✓</span>
                                            @elseif($hasDev)
                                                <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] font-extrabold bg-rose-500/20 text-rose-700 dark:text-rose-400 border border-rose-500/30">!</span>
                                            @else
                                                <span class="inline-flex items-center justify-center w-7 py-0.5 text-gray-400 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            </div>

            {{-- 2. Pie Chart Visual Ringkasan (4 Kolom) --}}
            <div class="lg:col-span-4">
                <x-filament::section icon="heroicon-m-chart-pie" header="Proporsi Akurasi TAF">
                    <div class="flex flex-col items-center justify-center p-2" x-data="{
                        init() {
                            const ctx = document.getElementById('tafPieChart');
                            if (ctx) {
                                new Chart(ctx, {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Sesuai SOP', 'Deviasi / Degradasi'],
                                        datasets: [{
                                            data: [{{ $summary['passed'] }}, {{ $summary['failed'] }}],
                                            backgroundColor: ['#10b981', '#f43f5e'],
                                            hoverBackgroundColor: ['#059669', '#e11d48'],
                                            borderWidth: 0,
                                            borderRadius: 6
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'bottom',
                                                labels: {
                                                    color: '#9ca3af',
                                                    font: { size: 11, weight: '600' },
                                                    padding: 12
                                                }
                                            }
                                        },
                                        cutout: '72%'
                                    }
                                });
                            }
                        }
                    }">
                        <div class="relative w-44 h-44 my-1 flex items-center justify-center">
                            <canvas id="tafPieChart"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                                <span class="text-2xl font-black text-gray-900 dark:text-white">{{ $summary['accuracy'] }}%</span>
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">AKURASI</span>
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            </div>

        </div>

        {{-- Stat Cards Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <x-filament::section>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Akurasi Total</span>
                    <x-filament::badge :color="$summary['accuracy'] >= 80 ? 'success' : 'warning'">
                        {{ $summary['accuracy'] >= 80 ? 'EXCELLENT' : 'WARNING' }}
                    </x-filament::badge>
                </div>
                <div class="mt-3">
                    <span class="text-4xl font-extrabold tracking-tight text-gray-950 dark:text-white">
                        {{ $summary['accuracy'] }}%
                    </span>
                </div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex justify-between border-t border-gray-100 dark:border-white/5 pt-2">
                    <span>Evaluasi Slot:</span>
                    <span class="font-mono font-bold">{{ $summary['total'] }} Slot</span>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slot Sesuai SOP</span>
                    <x-filament::badge color="success">PASSED</x-filament::badge>
                </div>
                <div class="mt-3">
                    <span class="text-4xl font-extrabold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ $summary['passed'] }}
                    </span>
                </div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex justify-between border-t border-gray-100 dark:border-white/5 pt-2">
                    <span>Rasio Kesesuaian:</span>
                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        {{ $summary['total'] > 0 ? round(($summary['passed'] / $summary['total']) * 100) : 0 }}%
                    </span>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slot Deviasi</span>
                    <x-filament::badge color="danger">DEVIATION</x-filament::badge>
                </div>
                <div class="mt-3">
                    <span class="text-4xl font-extrabold tracking-tight text-rose-600 dark:text-rose-400">
                        {{ $summary['failed'] }}
                    </span>
                </div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex justify-between border-t border-gray-100 dark:border-white/5 pt-2">
                    <span>Slot Terdegradasi:</span>
                    <span class="font-mono font-bold text-rose-600 dark:text-rose-400">{{ $summary['failed'] }} Slot</span>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                    7 Parameter SOP
                </div>
                <div class="space-y-1.5 overflow-y-auto max-h-20 pr-1">
                    @foreach($summary['paramStats'] as $param)
                        @php
                            $rate = $param['total'] > 0 ? round(($param['pass'] / $param['total']) * 100) : 0;
                        @endphp
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 dark:text-gray-300 truncate max-w-[100px]">{{ $param['label'] }}</span>
                            <div class="flex items-center gap-1.5">
                                <div class="w-12 bg-gray-200 dark:bg-gray-800 rounded-full h-1 overflow-hidden">
                                    <div class="bg-primary-500 h-1 rounded-full" style="width: {{ $rate }}%"></div>
                                </div>
                                <span class="font-mono text-[11px] font-bold text-primary-600 dark:text-primary-400">{{ $rate }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

        </div>

        {{-- Stream Data Log Table --}}
        <x-filament::section icon="heroicon-m-queue-list" header="Data Stream Log Verifikasi Realtime (30-Menit Slot)">
            <div class="overflow-x-auto -mx-6 -mb-6">
                <table class="w-full text-left text-xs divide-y divide-gray-200 dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5 text-gray-700 dark:text-gray-300 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Jam (UTC)</th>
                            <th class="py-3 px-3">Group</th>
                            <th class="py-3 px-4">Status Stream</th>
                            <th class="py-3 px-2 text-center">Dir</th>
                            <th class="py-3 px-2 text-center">Spd</th>
                            <th class="py-3 px-2 text-center">Gust</th>
                            <th class="py-3 px-2 text-center">Vis</th>
                            <th class="py-3 px-2 text-center">Cuaca</th>
                            <th class="py-3 px-2 text-center">Awan</th>
                            <th class="py-3 px-2 text-center">Ceiling</th>
                            <th class="py-3 px-3 text-center">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5 font-mono">
                        @forelse($rows as $row)
                            <tbody x-data="{ open: false }" class="divide-y divide-gray-200 dark:divide-white/5">
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition cursor-pointer" @click="open = !open">
                                    <td class="py-3 px-4 font-bold text-primary-600 dark:text-primary-400 whitespace-nowrap">
                                        {{ $row['time'] }}
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <x-filament::badge color="gray" size="sm">
                                            {{ $row['group'] }}
                                        </x-filament::badge>
                                    </td>

                                    <td class="py-3 px-4">
                                        @if($row['scores'])
                                            <span class="inline-flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-sans font-semibold">
                                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                                Sesuai SOP
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 font-sans italic">
                                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                                Data Belum Lengkap
                                            </span>
                                        @endif
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
                                            -
                                        </td>
                                    @endif

                                    <td class="py-3 px-3 text-center">
                                        <button type="button" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                            <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                        </button>
                                    </td>
                                </tr>

                                <tr x-show="open" x-transition class="bg-gray-50/80 dark:bg-gray-900/60 font-mono">
                                    <td colspan="11" class="p-4">
                                        <div class="space-y-2 border-l-2 border-primary-500 pl-4">
                                            <div class="text-xs">
                                                <span class="font-bold text-primary-600 dark:text-primary-400 mr-2">[TAF RAW]</span>
                                                <span class="text-gray-800 dark:text-gray-200">{{ $row['taf_raw'] }}</span>
                                            </div>
                                            <div class="text-xs">
                                                <span class="font-bold text-emerald-600 dark:text-emerald-400 mr-2">[METAR RAW]</span>
                                                <span class="text-gray-800 dark:text-gray-200">{{ $row['metar_raw'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tr>
                                <td colspan="11" class="py-8 text-center text-gray-500 dark:text-gray-400 font-sans">
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