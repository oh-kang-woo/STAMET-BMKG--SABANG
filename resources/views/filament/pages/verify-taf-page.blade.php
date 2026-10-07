<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div wire:poll.300s="refreshData" class="space-y-5 text-slate-200 font-sans -mt-2">
        
        {{-- Header Bar & Quick Controls --}}
        <div class="p-5 rounded-xl bg-[#1a1c23] border border-slate-800/80 shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-medium mb-1">
                    <span>Verifikasi TAF</span>
                    <span>&rsaquo;</span>
                    <span class="text-blue-400">Monitoring Realtime</span>
                </div>
                <h2 class="text-lg font-bold text-white tracking-wide">VERIFIKASI TAF REALTIME (WITN)</h2>
            </div>

            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="flex items-center gap-2 bg-[#121319] border border-slate-800 px-3.5 py-2 rounded-lg text-xs">
                    <span class="text-slate-400 font-medium">Tanggal:</span>
                    <input type="date" wire:model.live="selectedDate" class="bg-transparent border-0 text-xs font-bold text-blue-400 focus:ring-0 p-0 cursor-pointer">
                </div>

                <div class="flex items-center gap-2 bg-[#121319] border border-slate-800 px-3.5 py-2 rounded-lg text-xs">
                    <span class="text-slate-400 font-medium">Stasiun:</span>
                    <span class="font-mono font-bold text-emerald-400">WITN</span>
                </div>

                <button wire:click="refreshData" class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-lg shadow transition duration-150">
                    <x-heroicon-m-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" />
                    <span>Refresh</span>
                </button>
            </div>
        </div>

        @php
            $summary = $this->realtimeData['summary'];
            $rows = $this->realtimeData['rows'];

            // Map data per jam (00-23 UTC) untuk Panel Matriks Monitoring
            $hourlyStatus = [];
            for ($h = 0; $h < 24; $h++) {
                $hStr = sprintf('%02d', $h);
                $slot00 = null;
                $slot30 = null;
                foreach ($rows as $r) {
                    if (str_starts_with($r['time'], $hStr . ':00')) $slot00 = $r;
                    if (str_starts_with($r['time'], $hStr . ':30')) $slot30 = $r;
                }
                
                $hasTaf = ($slot00 && $slot00['taf_raw'] !== 'Data TAF Belum Tersedia') || ($slot30 && $slot30['taf_raw'] !== 'Data TAF Belum Tersedia');
                $hasMetar = ($slot00 && $slot00['metar_raw'] !== 'Data METAR Belum Tersedia') || ($slot30 && $slot30['metar_raw'] !== 'Data METAR Belum Tersedia');
                
                $score00 = $slot00['scores'] ? array_sum($slot00['scores']) : null;
                $score30 = $slot30['scores'] ? array_sum($slot30['scores']) : null;
                
                $hourlyStatus[$h] = [
                    'taf' => $hasTaf,
                    'metar' => $hasMetar,
                    'score00' => $score00,
                    'score30' => $score30,
                ];
            }
        @endphp

        {{-- PANEL MONITORING MATRIKS 24-JAM (Model GTS Messages) --}}
        <div class="p-5 rounded-xl bg-[#1a1c23] border border-slate-800/80 shadow-lg space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">PANEL MONITORING KETERSEDIAAN & VERIFIKASI TAF STASIUN</h3>
                </div>
                <span class="text-[10px] font-mono text-slate-400">WAKTU DALAM UTC</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-center border-collapse text-xs">
                    <thead>
                        <tr class="text-slate-400 font-bold text-[11px] border-b border-slate-800/60">
                            <th class="py-2 px-3 text-left w-24">JAM (UTC)</th>
                            @for($h = 0; $h < 24; $h++)
                                <th class="py-2 px-1 w-8 font-mono text-slate-300">{{ sprintf('%02d', $h) }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/40 font-mono text-[10px]">
                        {{-- Row TAF Availability --}}
                        <tr>
                            <td class="py-2.5 px-3 text-left font-bold text-slate-300">TAF</td>
                            @for($h = 0; $h < 24; $h++)
                                <td class="py-2.5 px-1">
                                    @if($hourlyStatus[$h]['taf'])
                                        <span class="inline-block w-6 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-bold">On</span>
                                    @else
                                        <span class="inline-block w-6 py-0.5 rounded bg-slate-800/60 text-slate-600">-</span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                        {{-- Row METAR Availability --}}
                        <tr>
                            <td class="py-2.5 px-3 text-left font-bold text-slate-300">METAR</td>
                            @for($h = 0; $h < 24; $h++)
                                <td class="py-2.5 px-1">
                                    @if($hourlyStatus[$h]['metar'])
                                        <span class="inline-block w-6 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-bold">On</span>
                                    @else
                                        <span class="inline-block w-6 py-0.5 rounded bg-slate-800/60 text-slate-600">-</span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                        {{-- Row Status Verifikasi --}}
                        <tr>
                            <td class="py-2.5 px-3 text-left font-bold text-slate-300">VERIFIKASI</td>
                            @for($h = 0; $h < 24; $h++)
                                @php
                                    $s00 = $hourlyStatus[$h]['score00'];
                                    $s30 = $hourlyStatus[$h]['score30'];
                                    $isFullPass = ($s00 === 7 && ($s30 === 7 || $s30 === null));
                                    $hasDev = ($s00 !== null && $s00 < 7) || ($s30 !== null && $s30 < 7);
                                @endphp
                                <td class="py-2.5 px-1">
                                    @if($isFullPass)
                                        <span class="inline-block w-6 py-0.5 rounded bg-blue-500/20 text-blue-400 border border-blue-500/30 font-bold">100%</span>
                                    @elseif($hasDev)
                                        <span class="inline-block w-6 py-0.5 rounded bg-rose-500/20 text-rose-400 border border-rose-500/30 font-bold">Dev</span>
                                    @else
                                        <span class="inline-block w-6 py-0.5 rounded bg-slate-800/60 text-slate-600">-</span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 4-Card Summary Metrics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            {{-- Card 1: Akurasi Total --}}
            <div class="p-5 rounded-xl bg-[#1a1c23] border border-slate-800/80 shadow-md flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Akurasi Total TAF</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                        {{ $summary['accuracy'] >= 80 ? 'EXCELLENT' : 'WARNING' }}
                    </span>
                </div>
                <div class="my-3">
                    <span class="text-4xl font-extrabold text-white tracking-tight">
                        {{ $summary['accuracy'] }}%
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center justify-between border-t border-slate-800/60 pt-2.5">
                    <span>Total Evaluasi</span>
                    <span class="font-mono font-bold text-slate-200">{{ $summary['total'] }} Slot</span>
                </div>
            </div>

            {{-- Card 2: Slot Sesuai --}}
            <div class="p-5 rounded-xl bg-[#1a1c23] border border-slate-800/80 shadow-md flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Slot Sesuai SOP</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">PASSED</span>
                </div>
                <div class="my-3">
                    <span class="text-4xl font-extrabold text-emerald-400 tracking-tight">
                        {{ $summary['passed'] }}
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center justify-between border-t border-slate-800/60 pt-2.5">
                    <span>Kesesuaian</span>
                    <span class="font-mono font-bold text-emerald-400">
                        {{ $summary['total'] > 0 ? round(($summary['passed'] / $summary['total']) * 100) : 0 }}%
                    </span>
                </div>
            </div>

            {{-- Card 3: Slot Deviasi --}}
            <div class="p-5 rounded-xl bg-[#1a1c23] border border-slate-800/80 shadow-md flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Slot Deviasi</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">DEVIATION</span>
                </div>
                <div class="my-3">
                    <span class="text-4xl font-extrabold text-rose-400 tracking-tight">
                        {{ $summary['failed'] }}
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center justify-between border-t border-slate-800/60 pt-2.5">
                    <span>Terdegradasi</span>
                    <span class="font-mono font-bold text-rose-400">{{ $summary['failed'] }} Slot</span>
                </div>
            </div>

            {{-- Card 4: Progress Bar Parameter Summary --}}
            <div class="p-5 rounded-xl bg-[#1a1c23] border border-slate-800/80 shadow-md flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Metrik Parameter</span>
                    <span class="text-[10px] font-mono text-blue-400">7 PARAMETER</span>
                </div>
                <div class="space-y-1.5 overflow-y-auto max-h-24 pr-1">
                    @foreach($summary['paramStats'] as $param)
                        @php
                            $rate = $param['total'] > 0 ? round(($param['pass'] / $param['total']) * 100) : 0;
                        @endphp
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-400 truncate max-w-[100px]">{{ $param['label'] }}</span>
                            <div class="flex items-center gap-2">
                                <div class="w-16 bg-slate-800 rounded-full h-1 overflow-hidden">
                                    <div class="bg-blue-500 h-1 rounded-full" style="width: {{ $rate }}%"></div>
                                </div>
                                <span class="font-mono text-xs font-bold text-slate-200">{{ $rate }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- Stream Data Table Log (Model GTS Table) --}}
        <div class="rounded-xl bg-[#1a1c23] border border-slate-800/80 shadow-lg overflow-hidden">
            <div class="p-4 bg-[#14151b] border-b border-slate-800/80 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">DATA STREAM LOG VERIFIKASI REALTIME</h3>
                <span class="text-[10px] text-slate-400 font-mono">INTERVAL 30-MENIT</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="text-slate-400 font-semibold text-[11px] bg-[#121319] border-b border-slate-800/80">
                            <th class="py-3 px-4">JAM (UTC)</th>
                            <th class="py-3 px-3">GROUP</th>
                            <th class="py-3 px-4 min-w-[340px]">ISI SANDI TAF & METAR RAW</th>
                            <th class="py-3 px-2 text-center">DIR</th>
                            <th class="py-3 px-2 text-center">SPD</th>
                            <th class="py-3 px-2 text-center">GUST</th>
                            <th class="py-3 px-2 text-center">VIS</th>
                            <th class="py-3 px-2 text-center">PRECIP</th>
                            <th class="py-3 px-2 text-center">CLOUD</th>
                            <th class="py-3 px-2 text-center">CEILING</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/40 font-mono">
                        @forelse($rows as $row)
                            <tr class="hover:bg-slate-800/20 transition duration-100">
                                <td class="py-3 px-4 font-bold text-blue-400 whitespace-nowrap">{{ $row['time'] }}</td>
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                        {{ $row['group'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 space-y-1">
                                    <div class="p-2 rounded bg-[#121319] text-slate-300 text-[11px] leading-relaxed border border-slate-800/60">
                                        <span class="text-blue-400 font-bold mr-1.5">[TAF]</span>{{ $row['taf_raw'] }}
                                    </div>
                                    <div class="p-2 rounded bg-[#121319] text-slate-300 text-[11px] leading-relaxed border border-slate-800/60">
                                        <span class="text-emerald-400 font-bold mr-1.5">[METAR]</span>{{ $row['metar_raw'] }}
                                    </div>
                                </td>

                                @if($row['scores'])
                                    @foreach(['wind_dir', 'wind_speed', 'gust', 'visibility', 'precip', 'cloud', 'ceiling'] as $param)
                                        <td class="py-3 px-2 text-center whitespace-nowrap">
                                            @if($row['scores'][$param] == 1)
                                                <span class="inline-block w-6 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-bold text-[10px]">1</span>
                                            @else
                                                <span class="inline-block w-6 py-0.5 rounded bg-rose-500/20 text-rose-400 border border-rose-500/30 font-bold text-[10px]">0</span>
                                            @endif
                                        </td>
                                    @endforeach
                                @else
                                    <td colspan="7" class="py-3 px-4 text-center italic text-slate-500 text-[11px]">
                                        Data belum lengkap
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-500">
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