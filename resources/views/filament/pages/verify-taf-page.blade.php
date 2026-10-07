<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div wire:poll.300s="refreshData" class="space-y-6 text-slate-100 font-sans -mt-2">
        
        {{-- Header Control Bar --}}
        <div class="p-6 rounded-2xl bg-[#13141f] shadow-2xl shadow-black/60 border border-white/[0.05] flex flex-col md:flex-row items-center justify-between gap-5">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-cyan-500 via-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-cyan-500/25">
                    <x-heroicon-m-signal class="w-6 h-6 animate-pulse" />
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-white tracking-wider uppercase">Aviation Realtime Command</h2>
                    <p class="text-xs text-slate-400 font-medium">Sistem Monitoring & Verifikasi TAFOR • Stasiun WITN Sabang</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 bg-[#0b0c13] px-4 py-2.5 rounded-xl border border-white/[0.05]">
                    <span class="text-xs text-slate-400 font-medium">Tanggal:</span>
                    <input type="date" wire:model.live="selectedDate" class="bg-transparent border-0 text-xs font-bold text-cyan-400 focus:ring-0 p-0 cursor-pointer">
                </div>

                <div class="flex items-center gap-2 bg-[#0b0c13] px-4 py-2.5 rounded-xl border border-white/[0.05]">
                    <span class="text-xs text-slate-400 font-medium">Stasiun:</span>
                    <span class="font-mono font-black text-emerald-400 tracking-wider">WITN</span>
                </div>

                <button wire:click="refreshData" class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 rounded-xl shadow-lg shadow-cyan-500/20 transition-all duration-300 hover:scale-[1.02] active:scale-95">
                    <x-heroicon-m-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" />
                    <span>Sync Telemetry</span>
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

        {{-- Panel Matrix Telemetry 24-Jam (Futuristic Timeline HUD) --}}
        <div class="p-6 rounded-2xl bg-[#13141f] shadow-2xl shadow-black/60 border border-white/[0.05] space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-white/[0.05]">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 shadow-[0_0_10px_#22d3ee]"></span>
                    <h3 class="text-xs font-extrabold text-slate-200 uppercase tracking-widest">Matriks Ketersediaan & Status Verifikasi (24 Jam UTC)</h3>
                </div>
                <span class="text-[10px] font-mono font-semibold text-slate-400 bg-[#0b0c13] px-2.5 py-1 rounded-lg border border-white/[0.05]">HOURLY STREAM</span>
            </div>

            <div class="overflow-x-auto pb-2">
                <div class="min-w-[850px] grid grid-cols-25 gap-1.5 text-center font-mono">
                    {{-- Row Label Headers --}}
                    <div class="text-left text-[11px] font-bold text-slate-400 self-center">JAM</div>
                    @for($h = 0; $h < 24; $h++)
                        <div class="text-[10px] font-bold text-slate-400 bg-[#0b0c13] py-1 rounded-md border border-white/[0.03]">
                            {{ sprintf('%02d', $h) }}
                        </div>
                    @endfor

                    {{-- Row TAF Status --}}
                    <div class="text-left text-[11px] font-bold text-slate-300 self-center">TAF</div>
                    @for($h = 0; $h < 24; $h++)
                        <div class="py-1 rounded-md text-[10px] font-extrabold transition-all {{ $hourlyStatus[$h]['taf'] ? 'bg-cyan-500/15 text-cyan-400 border border-cyan-500/30' : 'bg-[#0b0c13] text-slate-600' }}">
                            {{ $hourlyStatus[$h]['taf'] ? 'ON' : '-' }}
                        </div>
                    @endfor

                    {{-- Row METAR Status --}}
                    <div class="text-left text-[11px] font-bold text-slate-300 self-center">METAR</div>
                    @for($h = 0; $h < 24; $h++)
                        <div class="py-1 rounded-md text-[10px] font-extrabold transition-all {{ $hourlyStatus[$h]['metar'] ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-[#0b0c13] text-slate-600' }}">
                            {{ $hourlyStatus[$h]['metar'] ? 'ON' : '-' }}
                        </div>
                    @endfor

                    {{-- Row Verification Score --}}
                    <div class="text-left text-[11px] font-bold text-slate-300 self-center">VERIF</div>
                    @for($h = 0; $h < 24; $h++)
                        @php
                            $s00 = $hourlyStatus[$h]['score00'];
                            $s30 = $hourlyStatus[$h]['score30'];
                            $isFull = ($s00 === 7 && ($s30 === 7 || $s30 === null));
                            $hasDev = ($s00 !== null && $s00 < 7) || ($s30 !== null && $s30 < 7);
                        @endphp
                        <div class="py-1 rounded-md text-[10px] font-black transition-all {{ $isFull ? 'bg-blue-500/20 text-cyan-300 border border-blue-500/30' : ($hasDev ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-[#0b0c13] text-slate-600') }}">
                            {{ $isFull ? '100%' : ($hasDev ? 'DEV' : '-') }}
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        {{-- 4-Card Bento Grid Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            
            {{-- Card 1: Akurasi Total --}}
            <div class="p-6 rounded-2xl bg-[#13141f] shadow-2xl shadow-black/60 border border-white/[0.05] flex flex-col justify-between relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-cyan-500/10 rounded-full blur-xl pointer-events-none group-hover:bg-cyan-500/20 transition-all duration-500"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Akurasi Total TAF</span>
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-black tracking-wider uppercase border {{ $summary['accuracy'] >= 80 ? 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30' : 'bg-amber-500/10 text-amber-400 border-amber-500/30' }}">
                        {{ $summary['accuracy'] >= 80 ? 'HIGH ACCURACY' : 'ATTENTION' }}
                    </span>
                </div>
                <div class="my-5">
                    <span class="text-5xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-teal-300 to-emerald-400">
                        {{ $summary['accuracy'] }}%
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center justify-between pt-3 border-t border-white/[0.05]">
                    <span>Total Evaluasi Slot</span>
                    <span class="font-mono font-bold text-slate-200">{{ $summary['total'] }} Slot</span>
                </div>
            </div>

            {{-- Card 2: Slot Sesuai --}}
            <div class="p-6 rounded-2xl bg-[#13141f] shadow-2xl shadow-black/60 border border-white/[0.05] flex flex-col justify-between relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl pointer-events-none group-hover:bg-emerald-500/20 transition-all duration-500"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Slot Sesuai SOP</span>
                    <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <x-heroicon-m-check-circle class="w-5 h-5" />
                    </div>
                </div>
                <div class="my-5">
                    <span class="text-5xl font-black text-emerald-400 tracking-tight">
                        {{ $summary['passed'] }}
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center justify-between pt-3 border-t border-white/[0.05]">
                    <span>Rasio Kesesuaian</span>
                    <span class="font-mono font-bold text-emerald-400">
                        {{ $summary['total'] > 0 ? round(($summary['passed'] / $summary['total']) * 100) : 0 }}%
                    </span>
                </div>
            </div>

            {{-- Card 3: Deviasi --}}
            <div class="p-6 rounded-2xl bg-[#13141f] shadow-2xl shadow-black/60 border border-white/[0.05] flex flex-col justify-between relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-rose-500/10 rounded-full blur-xl pointer-events-none group-hover:bg-rose-500/20 transition-all duration-500"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Slot Deviasi</span>
                    <div class="p-2 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20">
                        <x-heroicon-m-x-circle class="w-5 h-5" />
                    </div>
                </div>
                <div class="my-5">
                    <span class="text-5xl font-black text-rose-400 tracking-tight">
                        {{ $summary['failed'] }}
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center justify-between pt-3 border-t border-white/[0.05]">
                    <span>Jumlah Terdegradasi</span>
                    <span class="font-mono font-bold text-rose-400">{{ $summary['failed'] }} Slot</span>
                </div>
            </div>

            {{-- Card 4: Donut Gauge --}}
            <div class="p-6 rounded-2xl bg-[#13141f] shadow-2xl shadow-black/60 border border-white/[0.05] flex items-center justify-between"
                 x-data="{
                    init() {
                        const ctx = document.getElementById('miniRingGauge');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    datasets: [{
                                        data: [{{ $summary['passed'] }}, {{ $summary['failed'] }}],
                                        backgroundColor: ['#06b6d4', '#f43f5e'],
                                        borderWidth: 0
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    cutout: '82%'
                                }
                            });
                        }
                    }
                 }">
                <div class="space-y-1">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Proporsi Validasi</span>
                    <div class="text-3xl font-black text-white">{{ $summary['accuracy'] }}%</div>
                    <p class="text-[10px] text-cyan-400 font-bold uppercase tracking-wider">Automated HUD</p>
                </div>
                <div class="relative w-20 h-20 flex items-center justify-center">
                    <canvas id="miniRingGauge"></canvas>
                    <div class="absolute inset-0 flex items-center justify-center text-[10px] font-mono font-extrabold text-slate-200">
                        {{ $summary['passed'] }}/{{ $summary['total'] }}
                    </div>
                </div>
            </div>

        </div>

        {{-- SOP Parameter Accuracy Bars --}}
        <div class="p-6 rounded-2xl bg-[#13141f] shadow-2xl shadow-black/60 border border-white/[0.05] space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-white/[0.05]">
                <h3 class="text-xs font-extrabold text-slate-200 uppercase tracking-widest">Akurasi per 7 Parameter SOP Meteorologi</h3>
                <span class="text-[10px] font-mono font-extrabold text-cyan-400 bg-[#0b0c13] px-2.5 py-1 rounded-lg border border-white/[0.05]">COMPLIANCE RATE</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($summary['paramStats'] as $param)
                    @php
                        $rate = $param['total'] > 0 ? round(($param['pass'] / $param['total']) * 100) : 0;
                    @endphp
                    <div class="p-4 rounded-xl bg-[#0b0c13] border border-white/[0.03] space-y-2.5">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-300 font-medium">{{ $param['label'] }}</span>
                            <span class="font-mono font-black text-cyan-400">{{ $rate }}%</span>
                        </div>
                        <div class="w-full bg-slate-800/80 rounded-full h-2 overflow-hidden p-0.5 border border-white/[0.03]">
                            <div class="bg-gradient-to-r from-cyan-500 to-emerald-400 h-1.5 rounded-full transition-all duration-500 shadow-[0_0_8px_#22d3ee]" style="width: {{ $rate }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Live Stream Data Log Table --}}
        <div class="rounded-2xl bg-[#13141f] shadow-2xl shadow-black/60 border border-white/[0.05] overflow-hidden">
            <div class="p-5 bg-[#0b0c13] flex items-center justify-between border-b border-white/[0.05]">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    <h3 class="text-xs font-extrabold text-white uppercase tracking-widest">Live Telemetry Stream Log (30-Min Intervals)</h3>
                </div>
                <span class="text-[10px] text-slate-400 font-mono bg-[#13141f] px-2.5 py-1 rounded-lg border border-white/[0.05]">REALTIME DATA</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="text-slate-400 font-bold text-[10px] bg-[#0d0e17] uppercase tracking-wider border-b border-white/[0.05]">
                            <th class="py-4 px-4">Jam (UTC)</th>
                            <th class="py-4 px-3">Group</th>
                            <th class="py-4 px-4 min-w-[360px]">Sandi TAF & METAR Raw</th>
                            <th class="py-4 px-2 text-center">Dir</th>
                            <th class="py-4 px-2 text-center">Spd</th>
                            <th class="py-4 px-2 text-center">Gust</th>
                            <th class="py-4 px-2 text-center">Vis</th>
                            <th class="py-4 px-2 text-center">Precip</th>
                            <th class="py-4 px-2 text-center">Cloud</th>
                            <th class="py-4 px-2 text-center">Ceiling</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.03] font-mono">
                        @forelse($rows as $row)
                            <tr class="hover:bg-slate-800/30 transition duration-150">
                                <td class="py-4 px-4 font-black text-cyan-400 whitespace-nowrap">{{ $row['time'] }}</td>
                                <td class="py-4 px-3 whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-blue-500/10 text-cyan-300 border border-blue-500/20">
                                        {{ $row['group'] }}
                                    </span>
                                </td>
                                <td class="py-4 px-4 space-y-1.5">
                                    <div class="p-2.5 rounded-xl bg-[#0b0c13] text-slate-200 text-[11px] leading-relaxed border border-white/[0.03]">
                                        <span class="text-cyan-400 font-black mr-1.5">[TAF]</span>{{ $row['taf_raw'] }}
                                    </div>
                                    <div class="p-2.5 rounded-xl bg-[#0b0c13] text-slate-200 text-[11px] leading-relaxed border border-white/[0.03]">
                                        <span class="text-emerald-400 font-black mr-1.5">[METAR]</span>{{ $row['metar_raw'] }}
                                    </div>
                                </td>

                                @if($row['scores'])
                                    @foreach(['wind_dir', 'wind_speed', 'gust', 'visibility', 'precip', 'cloud', 'ceiling'] as $param)
                                        <td class="py-4 px-2 text-center whitespace-nowrap">
                                            @if(($row['scores'][$param] ?? 0) == 1)
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 font-black text-xs shadow-[0_0_8px_rgba(16,185,129,0.15)]">1</span>
                                            @else
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-rose-500/15 text-rose-400 border border-rose-500/30 font-black text-xs shadow-[0_0_8px_rgba(244,63,94,0.15)]">0</span>
                                            @endif
                                        </td>
                                    @endforeach
                                @else
                                    <td colspan="7" class="py-4 px-4 text-center italic text-slate-500 text-[11px]">
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