<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div wire:poll.300s="refreshData" class="space-y-6">

        {{-- Top Bar & Control Section --}}
        <div class="p-5 rounded-2xl bg-[#14151f] border border-slate-800/80 shadow-xl flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-400">
                    <x-heroicon-m-signal class="w-6 h-6 animate-pulse" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-white tracking-wide">
                        Verifikasi TAF Realtime
                    </h2>
                    <p class="text-xs text-slate-400">
                        Stasiun Meteorologi Maimun Saleh Sabang (WITN)
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Date Picker (Dark Mode Styling Fix) --}}
                <div class="flex items-center gap-2 bg-[#1a1b26] border border-slate-700/60 px-3.5 py-1.5 rounded-xl text-xs">
                    <span class="text-slate-400 font-medium">Tanggal:</span>
                    <input type="date"
                           wire:model.live="selectedDate"
                           class="bg-transparent border-0 p-0 text-xs font-bold text-sky-400 focus:ring-0 cursor-pointer [color-scheme:dark]">
                </div>

                {{-- Station Badge (Contrast Fix) --}}
                <div class="flex items-center gap-2 bg-[#1a1b26] border border-slate-700/60 px-3.5 py-1.5 rounded-xl text-xs">
                    <span class="text-slate-400 font-medium">Stasiun:</span>
                    <span class="font-mono font-bold text-emerald-400">WITN</span>
                </div>

                {{-- Sync Button --}}
                <button wire:click="refreshData"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-sky-600 hover:bg-sky-500 active:bg-sky-700 rounded-xl shadow-lg shadow-sky-600/20 transition duration-150">
                    <x-heroicon-m-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" />
                    <span>Sync Data</span>
                </button>
            </div>
        </div>

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

        {{-- Section Utama: Matriks 24-Jam & Pie Chart --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            {{-- 1. Matriks Ketersediaan Telemetri 24 Jam --}}
            <div class="lg:col-span-8 p-5 rounded-2xl bg-[#14151f] border border-slate-800/80 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-clock class="w-5 h-5 text-sky-400" />
                        <h3 class="text-xs font-bold text-slate-200 uppercase tracking-wider">
                            Matriks Ketersediaan Data Telemetri (24 Jam UTC)
                        </h3>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400 bg-[#1a1b26] px-2.5 py-1 rounded-lg border border-slate-700/50">HOURLY MONITORING</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-center border-collapse">
                        <thead>
                            <tr class="bg-[#1a1b26] text-slate-400 font-mono text-[11px] border-b border-slate-800/80">
                                <th class="py-2.5 px-3 text-left w-20 font-sans font-bold text-slate-300">JAM</th>
                                @for($h = 0; $h < 24; $h++)
                                    <th class="py-2.5 px-0.5 min-w-[34px] font-semibold">{{ sprintf('%02d', $h) }}</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-mono text-[10px]">
                            {{-- Row TAF --}}
                            <tr>
                                <td class="py-3 px-3 text-left font-sans font-bold text-cyan-400">TAF</td>
                                @for($h = 0; $h < 24; $h++)
                                    <td class="py-3 px-0.5">
                                        @if($hourlyStatus[$h]['taf'])
                                            <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] font-extrabold bg-cyan-500/15 text-cyan-300 border border-cyan-500/30">
                                                On
                                            </span>
                                        @else
                                            <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] text-slate-600 bg-slate-900/40">
                                                -
                                            </span>
                                        @endif
                                    </td>
                                @endfor
                            </tr>

                            {{-- Row METAR --}}
                            <tr>
                                <td class="py-3 px-3 text-left font-sans font-bold text-emerald-400">METAR</td>
                                @for($h = 0; $h < 24; $h++)
                                    <td class="py-3 px-0.5">
                                        @if($hourlyStatus[$h]['metar'])
                                            <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] font-extrabold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                                                On
                                            </span>
                                        @else
                                            <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] text-slate-600 bg-slate-900/40">
                                                -
                                            </span>
                                        @endif
                                    </td>
                                @endfor
                            </tr>

                            {{-- Row VERIF --}}
                            <tr>
                                <td class="py-3 px-3 text-left font-sans font-bold text-sky-400">VERIF</td>
                                @for($h = 0; $h < 24; $h++)
                                    @php
                                        $s00 = $hourlyStatus[$h]['score00'];
                                        $s30 = $hourlyStatus[$h]['score30'];
                                        $isFull = ($s00 === 7 && ($s30 === 7 || $s30 === null));
                                        $hasDev = ($s00 !== null && $s00 < 7) || ($s30 !== null && $s30 < 7);
                                    @endphp
                                    <td class="py-3 px-0.5">
                                        @if($isFull)
                                            <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] font-extrabold bg-sky-500/20 text-sky-300 border border-sky-500/30">
                                                ✓
                                            </span>
                                        @elseif($hasDev)
                                            <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] font-extrabold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                                !
                                            </span>
                                        @else
                                            <span class="inline-flex items-center justify-center w-7 py-0.5 rounded text-[10px] text-slate-600 bg-slate-900/40">
                                                -
                                            </span>
                                        @endif
                                    </td>
                                @endfor
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 2. Pie Chart Ringkasan Akurasi --}}
            <div class="lg:col-span-4 p-5 rounded-2xl bg-[#14151f] border border-slate-800/80 shadow-xl flex flex-col justify-between">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-chart-pie class="w-5 h-5 text-sky-400" />
                        <h3 class="text-xs font-bold text-slate-200 uppercase tracking-wider">Proporsi Validasi</h3>
                    </div>
                </div>

                <div class="flex items-center justify-center p-2" x-data="{
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
                                        borderWidth: 0,
                                        borderRadius: 4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                            labels: { color: '#9ca3af', font: { size: 11, weight: '600' }, padding: 12 }
                                        }
                                    },
                                    cutout: '74%'
                                }
                            });
                        }
                    }
                }">
                    <div class="relative w-40 h-40 my-1 flex items-center justify-center">
                        <canvas id="tafPieChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                            <span class="text-2xl font-black text-white">{{ $summary['accuracy'] }}%</span>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">AKURASI</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Stat Cards Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-5 rounded-2xl bg-[#14151f] border border-slate-800/80 shadow-xl flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Akurasi Total</span>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-sky-500/20 text-sky-300 border border-sky-500/30">
                        {{ $summary['accuracy'] >= 80 ? 'EXCELLENT' : 'WARNING' }}
                    </span>
                </div>
                <div class="my-3">
                    <span class="text-4xl font-extrabold tracking-tight text-white">
                        {{ $summary['accuracy'] }}%
                    </span>
                </div>
                <div class="text-xs text-slate-400 flex justify-between border-t border-slate-800/60 pt-2">
                    <span>Evaluasi Slot:</span>
                    <span class="font-mono font-bold text-slate-200">{{ $summary['total'] }} Slot</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-[#14151f] border border-slate-800/80 shadow-xl flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Slot Sesuai SOP</span>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">PASSED</span>
                </div>
                <div class="my-3">
                    <span class="text-4xl font-extrabold tracking-tight text-emerald-400">
                        {{ $summary['passed'] }}
                    </span>
                </div>
                <div class="text-xs text-slate-400 flex justify-between border-t border-slate-800/60 pt-2">
                    <span>Rasio Kesesuaian:</span>
                    <span class="font-mono font-bold text-emerald-400">
                        {{ $summary['total'] > 0 ? round(($summary['passed'] / $summary['total']) * 100) : 0 }}%
                    </span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-[#14151f] border border-slate-800/80 shadow-xl flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Slot Deviasi</span>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-rose-500/20 text-rose-300 border border-rose-500/30">DEVIATION</span>
                </div>
                <div class="my-3">
                    <span class="text-4xl font-extrabold tracking-tight text-rose-400">
                        {{ $summary['failed'] }}
                    </span>
                </div>
                <div class="text-xs text-slate-400 flex justify-between border-t border-slate-800/60 pt-2">
                    <span>Slot Terdegradasi:</span>
                    <span class="font-mono font-bold text-rose-400">{{ $summary['failed'] }} Slot</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-[#14151f] border border-slate-800/80 shadow-xl flex flex-col justify-between">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    7 Parameter SOP
                </div>
                <div class="space-y-1.5 overflow-y-auto max-h-20 pr-1">
                    @foreach($summary['paramStats'] as $param)
                        @php
                            $rate = $param['total'] > 0 ? round(($param['pass'] / $param['total']) * 100) : 0;
                        @endphp
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-300 truncate max-w-[100px]">{{ $param['label'] }}</span>
                            <div class="flex items-center gap-1.5">
                                <div class="w-12 bg-slate-800 rounded-full h-1 overflow-hidden">
                                    <div class="bg-sky-500 h-1 rounded-full" style="width: {{ $rate }}%"></div>
                                </div>
                                <span class="font-mono text-[11px] font-bold text-sky-400">{{ $rate }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Stream Data Log Table dengan Dropdown Detail --}}
        <div class="rounded-2xl bg-[#14151f] border border-slate-800/80 shadow-xl overflow-hidden">
            <div class="p-4 bg-[#1a1b26] border-b border-slate-800/80 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-200 uppercase tracking-wider">Data Stream Log Verifikasi Realtime (Interval 30-Menit)</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-[#1a1b26] text-slate-400 font-bold uppercase tracking-wider text-[11px] border-b border-slate-800">
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
                    <tbody class="divide-y divide-slate-800/60 font-mono">
                        @forelse($rows as $row)
                            <tbody x-data="{ open: false }" class="divide-y divide-slate-800/60">
                                <tr class="hover:bg-slate-800/40 transition cursor-pointer" @click="open = !open">
                                    <td class="py-3 px-4 font-bold text-sky-400 whitespace-nowrap">
                                        {{ $row['time'] }}
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                            {{ $row['group'] }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-4 font-sans">
                                        @if($row['scores'])
                                            <span class="inline-flex items-center gap-1.5 text-xs text-emerald-400 font-semibold">
                                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                                Sesuai SOP
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 italic">
                                                <span class="w-2 h-2 rounded-full bg-slate-600"></span>
                                                Data Belum Lengkap
                                            </span>
                                        @endif
                                    </td>

                                    @if($row['scores'])
                                        @foreach(['wind_dir', 'wind_speed', 'gust', 'visibility', 'precip', 'cloud', 'ceiling'] as $param)
                                            <td class="py-3 px-2 text-center whitespace-nowrap">
                                                @if(($row['scores'][$param] ?? 0) == 1)
                                                    <span class="inline-flex items-center justify-center w-6 py-0.5 rounded text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">1</span>
                                                @else
                                                    <span class="inline-flex items-center justify-center w-6 py-0.5 rounded text-[10px] font-extrabold bg-rose-500/20 text-rose-300 border border-rose-500/30">0</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    @else
                                        <td colspan="7" class="py-3 px-4 text-center italic text-slate-500 text-[11px]">
                                            -
                                        </td>
                                    @endif

                                    <td class="py-3 px-3 text-center">
                                        <button type="button" class="p-1 rounded-lg text-slate-400 hover:text-white">
                                            <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                        </button>
                                    </td>
                                </tr>

                                <tr x-show="open" x-transition class="bg-[#1a1b26] font-mono">
                                    <td colspan="11" class="p-4">
                                        <div class="space-y-2 border-l-2 border-sky-500 pl-4">
                                            <div class="text-xs">
                                                <span class="font-bold text-sky-400 mr-2">[TAF RAW]</span>
                                                <span class="text-slate-300">{{ $row['taf_raw'] }}</span>
                                            </div>
                                            <div class="text-xs">
                                                <span class="font-bold text-emerald-400 mr-2">[METAR RAW]</span>
                                                <span class="text-slate-300">{{ $row['metar_raw'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tr>
                                <td colspan="11" class="py-8 text-center text-slate-500 font-sans">
                                    Tidak ada data verifikasi untuk rentang waktu ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-filament-panels::page>