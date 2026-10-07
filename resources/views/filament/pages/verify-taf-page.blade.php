<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div wire:poll.300s="refreshData" class="space-y-6 text-slate-100 font-sans -mt-2">
        
        {{-- Header Control Bar --}}
        <div class="flex flex-col md:flex-row items-center justify-between gap-4 p-5 rounded-2xl bg-slate-900/90 backdrop-blur-md border border-slate-800/80 shadow-2xl">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-indigo-500 via-purple-500 to-pink-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                    <x-heroicon-m-signal class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-white tracking-wide">Aviation Control Center</h2>
                    <p class="text-xs text-slate-400">Verifikasi Realtime TAFOR • Stasiun WITN Sabang</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2.5 bg-slate-950/80 border border-slate-800 px-4 py-2 rounded-xl text-xs">
                    <span class="text-slate-400 font-medium">Tanggal:</span>
                    <input type="date" wire:model.live="selectedDate" class="bg-transparent border-0 text-xs font-bold text-indigo-400 focus:ring-0 p-0 cursor-pointer">
                </div>

                <div class="flex items-center gap-2 bg-slate-950/80 border border-slate-800 px-3.5 py-2 rounded-xl text-xs">
                    <span class="text-slate-400 font-medium">Stasiun:</span>
                    <span class="font-mono font-bold text-cyan-400">WITN</span>
                </div>

                <button wire:click="refreshData" class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 rounded-xl shadow-lg shadow-indigo-500/20 transition-all duration-200 active:scale-95">
                    <x-heroicon-m-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" />
                    <span>Sync Realtime</span>
                </button>
            </div>
        </div>

        @php
            $summary = $this->realtimeData['summary'];
            $rows = $this->realtimeData['rows'];
        @endphp

        {{-- Bento Grid Metrics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            
            {{-- Card 1: Akurasi Total --}}
            <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800/80 shadow-xl shadow-black/40 flex flex-col justify-between relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Akurasi Total</span>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold border {{ $summary['accuracy'] >= 80 ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20' }}">
                        {{ $summary['accuracy'] >= 80 ? 'EXCELLENT' : 'WARNING' }}
                    </span>
                </div>
                <div class="my-4">
                    <span class="text-5xl font-black text-white tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white to-slate-300">
                        {{ $summary['accuracy'] }}%
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center justify-between border-t border-slate-800/80 pt-3">
                    <span>Total Slot Dievaluasi</span>
                    <span class="font-mono font-bold text-slate-200">{{ $summary['total'] }} Slot</span>
                </div>
            </div>

            {{-- Card 2: Sesuai SOP --}}
            <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800/80 shadow-xl shadow-black/40 flex flex-col justify-between relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sesuai SOP</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                        <x-heroicon-m-check-circle class="w-5 h-5" />
                    </div>
                </div>
                <div class="my-4">
                    <span class="text-5xl font-black text-emerald-400 tracking-tight">
                        {{ $summary['passed'] }}
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center justify-between border-t border-slate-800/80 pt-3">
                    <span>Rasio Kesesuaian</span>
                    <span class="font-mono font-bold text-emerald-400">
                        {{ $summary['total'] > 0 ? round(($summary['passed'] / $summary['total']) * 100) : 0 }}%
                    </span>
                </div>
            </div>

            {{-- Card 3: Deviasi --}}
            <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800/80 shadow-xl shadow-black/40 flex flex-col justify-between relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-rose-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Slot Deviasi</span>
                    <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center">
                        <x-heroicon-m-x-circle class="w-5 h-5" />
                    </div>
                </div>
                <div class="my-4">
                    <span class="text-5xl font-black text-rose-400 tracking-tight">
                        {{ $summary['failed'] }}
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center justify-between border-t border-slate-800/80 pt-3">
                    <span>Jumlah Terdegradasi</span>
                    <span class="font-mono font-bold text-rose-400">{{ $summary['failed'] }} Slot</span>
                </div>
            </div>

            {{-- Card 4: Ring Gauge Chart --}}
            <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800/80 shadow-xl shadow-black/40 flex items-center justify-between"
                 x-data="{
                    init() {
                        const ctx = document.getElementById('miniRingChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    datasets: [{
                                        data: [{{ $summary['passed'] }}, {{ $summary['failed'] }}],
                                        backgroundColor: ['#10b981', '#f43f5e'],
                                        borderWidth: 0
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    cutout: '80%'
                                }
                            });
                        }
                    }
                 }">
                <div class="space-y-1">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Proporsi</span>
                    <div class="text-3xl font-black text-white">{{ $summary['accuracy'] }}%</div>
                    <p class="text-[10px] text-indigo-400 font-semibold uppercase tracking-wider">Validasi SOP</p>
                </div>
                <div class="relative w-20 h-20 flex items-center justify-center">
                    <canvas id="miniRingChart"></canvas>
                    <div class="absolute inset-0 flex items-center justify-center text-[10px] font-mono font-bold text-slate-300">
                        {{ $summary['passed'] }}/{{ $summary['total'] }}
                    </div>
                </div>
            </div>

        </div>

        {{-- SOP Parameter Progress Bars --}}
        <div class="p-6 rounded-2xl bg-slate-900/90 border border-slate-800/80 shadow-xl shadow-black/40 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Akurasi per Parameter SOP</h3>
                <span class="text-[10px] text-indigo-400 font-mono font-bold">7 PARAMETERS</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($summary['paramStats'] as $param)
                    @php
                        $rate = $param['total'] > 0 ? round(($param['pass'] / $param['total']) * 100) : 0;
                    @endphp
                    <div class="p-4 rounded-xl bg-slate-950/70 border border-slate-800/60">
                        <div class="flex justify-between items-center text-xs mb-2">
                            <span class="text-slate-300 font-medium">{{ $param['label'] }}</span>
                            <span class="font-mono font-bold text-indigo-400">{{ $rate }}%</span>
                        </div>
                        <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-gradient-to-r from-indigo-500 to-cyan-400 h-1.5 rounded-full transition-all duration-500" style="width: {{ $rate }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Live Stream Table --}}
        <div class="rounded-2xl bg-slate-900/90 border border-slate-800/80 shadow-2xl overflow-hidden">
            <div class="p-5 bg-slate-950/80 flex items-center justify-between border-b border-slate-800/80">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider">Data Stream Log Verifikasi Realtime</h3>
                </div>
                <span class="text-[10px] text-slate-400 font-mono">INTERVAL 30-MENIT</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="text-slate-400 font-semibold text-[11px] bg-slate-950/50 uppercase tracking-wider border-b border-slate-800/80">
                            <th class="py-3.5 px-4">Jam (UTC)</th>
                            <th class="py-3.5 px-3">Group</th>
                            <th class="py-3.5 px-4 min-w-[340px]">Sandi TAF & METAR Raw</th>
                            <th class="py-3.5 px-2 text-center">Dir</th>
                            <th class="py-3.5 px-2 text-center">Spd</th>
                            <th class="py-3.5 px-2 text-center">Gust</th>
                            <th class="py-3.5 px-2 text-center">Vis</th>
                            <th class="py-3.5 px-2 text-center">Cuaca</th>
                            <th class="py-3.5 px-2 text-center">Awan</th>
                            <th class="py-3.5 px-2 text-center">Ceiling</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        @forelse($rows as $row)
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4 font-mono font-bold text-cyan-400 whitespace-nowrap">{{ $row['time'] }}</td>
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                                        {{ $row['group'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 space-y-1.5 font-mono">
                                    <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 text-slate-300 text-[11px] leading-relaxed">
                                        <span class="text-cyan-400 font-bold mr-1.5">[TAF]</span>{{ $row['taf_raw'] }}
                                    </div>
                                    <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 text-slate-300 text-[11px] leading-relaxed">
                                        <span class="text-emerald-400 font-bold mr-1.5">[METAR]</span>{{ $row['metar_raw'] }}
                                    </div>
                                </td>

                                @if($row['scores'])
                                    @foreach(['wind_dir', 'wind_speed', 'gust', 'visibility', 'precip', 'cloud', 'ceiling'] as $param)
                                        <td class="py-3.5 px-2 text-center whitespace-nowrap">
                                            @if($row['scores'][$param] == 1)
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-emerald-500/10 text-emerald-400 font-bold text-xs border border-emerald-500/20">✓</span>
                                            @else
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-rose-500/10 text-rose-400 font-bold text-xs border border-rose-500/20">✕</span>
                                            @endif
                                        </td>
                                    @endforeach
                                @else
                                    <td colspan="7" class="py-3.5 px-4 text-center italic text-slate-500 font-mono text-[11px]">
                                        Data belum lengkap
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-500 font-mono">
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