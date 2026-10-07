<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div wire:poll.300s="refreshData" class="space-y-6 text-slate-100 font-sans">
        
        {{-- Top Bar / Filter Control Center --}}
        <div class="relative overflow-hidden rounded-2xl bg-slate-900/80 backdrop-blur-xl border border-white/10 p-5 shadow-2xl shadow-black/50">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400">
                        <x-heroicon-m-adjustments-horizontal class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white tracking-wide uppercase">Aviation Control Panel</h2>
                        <p class="text-xs text-slate-400">Monitoring & Verifikasi TAFOR WITN Realtime</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <div class="flex items-center gap-2 bg-slate-950/80 border border-slate-800 px-3 py-1.5 rounded-xl">
                        <span class="text-xs text-slate-400 font-medium">Tanggal:</span>
                        <input type="date" wire:model.live="selectedDate" class="bg-transparent border-none text-xs font-semibold text-cyan-300 focus:ring-0 p-0 cursor-pointer">
                    </div>

                    <div class="flex items-center gap-2 bg-slate-950/80 border border-slate-800 px-3 py-1.5 rounded-xl">
                        <span class="text-xs text-slate-400 font-medium">Stasiun:</span>
                        <span class="text-xs font-bold text-emerald-400 tracking-wider">WITN</span>
                    </div>

                    <button wire:click="refreshData" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 rounded-xl shadow-lg shadow-cyan-500/20 border border-cyan-400/30 transition-all duration-300 hover:scale-[1.02] active:scale-[0.98]">
                        <x-heroicon-m-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" />
                        <span>Sync Realtime</span>
                    </button>
                </div>
            </div>
        </div>

        @php
            $summary = $this->realtimeData['summary'];
            $rows = $this->realtimeData['rows'];
        @endphp

        {{-- Bento Grid Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            
            {{-- Card 1: Main Metric Overview (4 cols) --}}
            <div class="lg:col-span-4 rounded-2xl bg-gradient-to-br from-slate-900/90 via-slate-900/60 to-slate-950/90 border border-white/10 p-6 backdrop-blur-xl shadow-xl flex flex-col justify-between relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none group-hover:bg-emerald-500/20 transition-all duration-500"></div>
                
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400 tracking-wider uppercase">Tingkat Akurasi Total</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-extrabold border {{ $summary['accuracy'] >= 80 ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 shadow-[0_0_12px_rgba(16,185,129,0.2)]' : 'bg-amber-500/10 text-amber-400 border-amber-500/30' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $summary['accuracy'] >= 80 ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' }}"></span>
                            {{ $summary['accuracy'] >= 80 ? 'HIGH ACCURACY' : 'ATTENTION' }}
                        </span>
                    </div>

                    <div class="mt-4 flex items-baseline gap-3">
                        <span class="text-5xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-teal-300 to-emerald-400 drop-shadow-[0_0_20px_rgba(34,211,238,0.3)]">
                            {{ $summary['accuracy'] }}%
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-6 pt-4 border-t border-slate-800/80">
                    <div class="bg-slate-950/60 border border-emerald-500/20 p-3 rounded-xl">
                        <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider block">Sesuai SOP</span>
                        <span class="text-2xl font-black text-white mt-0.5 block">{{ $summary['passed'] }} <span class="text-xs font-normal text-slate-500">slot</span></span>
                    </div>
                    <div class="bg-slate-950/60 border border-rose-500/20 p-3 rounded-xl">
                        <span class="text-[10px] font-bold text-rose-400 uppercase tracking-wider block">Deviasi</span>
                        <span class="text-2xl font-black text-white mt-0.5 block">{{ $summary['failed'] }} <span class="text-xs font-normal text-slate-500">slot</span></span>
                    </div>
                </div>
            </div>

            {{-- Card 2: Futuristic Ring Chart (4 cols) --}}
            <div class="lg:col-span-4 rounded-2xl bg-gradient-to-br from-slate-900/90 via-slate-900/60 to-slate-950/90 border border-white/10 p-6 backdrop-blur-xl shadow-xl flex flex-col justify-between"
                 x-data="{
                    init() {
                        const ctx = document.getElementById('futuristicChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: ['Sesuai', 'Deviasi'],
                                    datasets: [{
                                        data: [{{ $summary['passed'] }}, {{ $summary['failed'] }}],
                                        backgroundColor: ['#06b6d4', '#f43f5e'],
                                        borderWidth: 0,
                                        hoverOffset: 4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    cutout: '78%'
                                }
                            });
                        }
                    }
                 }">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-400 tracking-wider uppercase">Proporsi Kesesuaian</span>
                    <span class="text-[10px] text-cyan-400 font-mono">TOTAL: {{ $summary['total'] }} SLOT</span>
                </div>

                <div class="relative h-40 flex items-center justify-center my-1">
                    <canvas id="futuristicChart"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-2xl font-black text-white drop-shadow-[0_0_10px_rgba(255,255,255,0.3)]">{{ $summary['accuracy'] }}%</span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">VALIDATED</span>
                    </div>
                </div>

                <div class="flex items-center justify-center gap-6 pt-3 border-t border-slate-800/80 text-[11px]">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-cyan-500 shadow-[0_0_8px_rgba(6,182,212,0.8)]"></span>
                        <span class="text-slate-300 font-medium">Sesuai ({{ $summary['passed'] }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.8)]"></span>
                        <span class="text-slate-300 font-medium">Deviasi ({{ $summary['failed'] }})</span>
                    </div>
                </div>
            </div>

            {{-- Card 3: SOP Parameter Progress HUD (4 cols) --}}
            <div class="lg:col-span-4 rounded-2xl bg-gradient-to-br from-slate-900/90 via-slate-900/60 to-slate-950/90 border border-white/10 p-6 backdrop-blur-xl shadow-xl flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-slate-400 tracking-wider uppercase">Akurasi 7 Parameter SOP</span>
                    <span class="text-[10px] text-emerald-400 font-mono">PASSED</span>
                </div>

                <div class="space-y-2.5 max-h-48 overflow-y-auto pr-1">
                    @foreach($summary['paramStats'] as $param)
                        @php
                            $rate = $param['total'] > 0 ? round(($param['pass'] / $param['total']) * 100) : 0;
                        @endphp
                        <div>
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="text-slate-300 font-medium">{{ $param['label'] }}</span>
                                <span class="font-mono font-bold text-cyan-400">{{ $rate }}%</span>
                            </div>
                            <div class="w-full bg-slate-950/80 rounded-full h-1.5 p-0.5 border border-slate-800">
                                <div class="bg-gradient-to-r from-cyan-500 to-emerald-400 h-1 rounded-full shadow-[0_0_8px_rgba(34,211,238,0.5)] transition-all duration-500" style="width: {{ $rate }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- Futuristic Live Data Stream Table --}}
        <div class="rounded-2xl bg-slate-900/80 border border-white/10 backdrop-blur-xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 bg-slate-950/80 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <h3 class="text-xs font-bold text-white uppercase tracking-widest">Data Stream Log Verifikasi Realtime</h3>
                </div>
                <span class="text-[10px] font-mono text-slate-400">INTERVAL: 30 MIN SLOT</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950/60 text-slate-400 font-semibold uppercase tracking-wider text-[10px] border-b border-slate-800">
                            <th class="py-3 px-4">UTC Time</th>
                            <th class="py-3 px-3">Group</th>
                            <th class="py-3 px-4 min-w-[320px]">Sandi TAF & METAR Raw</th>
                            <th class="py-3 px-2 text-center">Dir</th>
                            <th class="py-3 px-2 text-center">Spd</th>
                            <th class="py-3 px-2 text-center">Gust</th>
                            <th class="py-3 px-2 text-center">Vis</th>
                            <th class="py-3 px-2 text-center">Precip</th>
                            <th class="py-3 px-2 text-center">Cloud</th>
                            <th class="py-3 px-2 text-center">Ceiling</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-sans">
                        @forelse($rows as $row)
                            <tr class="hover:bg-slate-800/40 transition-colors duration-150 group">
                                <td class="py-3.5 px-4 font-mono font-bold text-cyan-300 whitespace-nowrap">{{ $row['time'] }}</td>
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold tracking-wider border bg-cyan-950/60 text-cyan-300 border-cyan-500/30">
                                        {{ $row['group'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 space-y-1.5 font-mono">
                                    <div class="p-2 rounded-lg bg-slate-950/80 border border-cyan-500/20 text-cyan-300/90 text-[11px] leading-relaxed shadow-inner">
                                        <span class="text-cyan-400 font-bold mr-1.5">[TAF]</span>{{ $row['taf_raw'] }}
                                    </div>
                                    <div class="p-2 rounded-lg bg-slate-950/80 border border-emerald-500/20 text-emerald-300/90 text-[11px] leading-relaxed shadow-inner">
                                        <span class="text-emerald-400 font-bold mr-1.5">[METAR]</span>{{ $row['metar_raw'] }}
                                    </div>
                                </td>

                                @if($row['scores'])
                                    @foreach(['wind_dir', 'wind_speed', 'gust', 'visibility', 'precip', 'cloud', 'ceiling'] as $param)
                                        <td class="py-3.5 px-2 text-center whitespace-nowrap">
                                            @if($row['scores'][$param] == 1)
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 font-extrabold text-xs shadow-[0_0_10px_rgba(16,185,129,0.2)]">✓</span>
                                            @else
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-rose-500/10 text-rose-400 border border-rose-500/30 font-extrabold text-xs shadow-[0_0_10px_rgba(244,63,94,0.2)]">✕</span>
                                            @endif
                                        </td>
                                    @endforeach
                                @else
                                    <td colspan="7" class="py-3.5 px-4 text-center italic text-slate-500 font-mono text-[11px]">
                                        Awaiting complete data stream...
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-500 font-mono">
                                    No telemetry or verification logs available for selected timeframe.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-filament-panels::page>