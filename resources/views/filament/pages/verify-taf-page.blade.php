<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div wire:poll.300s="refreshData" class="space-y-6">
        
        {{-- Filter Top Bar --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-4 w-full sm:w-auto">
                <div class="flex-1 sm:flex-initial">
                    <label class="block text-xs text-gray-500 dark:text-gray-400 font-semibold mb-1">Tanggal Verifikasi</label>
                    <input type="date" wire:model.live="selectedDate" class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-white focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 font-semibold mb-1">Stasiun</label>
                    <input type="text" wire:model="icao" readonly class="w-24 text-sm rounded-lg border-gray-300 bg-gray-100 font-bold dark:bg-gray-800 dark:border-gray-700 dark:text-primary-400 text-center">
                </div>
            </div>

            <x-filament::button wire:click="refreshData" icon="heroicon-m-arrow-path" color="primary">
                Perbarui Data Realtime
            </x-filament::button>
        </div>

        @php
            $summary = $this->realtimeData['summary'];
            $rows = $this->realtimeData['rows'];
        @endphp

        {{-- Section Visual Metrics & Pie Chart --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {{-- Kartu Ringkasan Status --}}
            <div class="lg:col-span-1 space-y-4">
                <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tingkat Akurasi Total TAF</span>
                    <div class="flex items-baseline justify-between mt-2">
                        <span class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $summary['accuracy'] }}%</span>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $summary['accuracy'] >= 80 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400' }}">
                            {{ $summary['accuracy'] >= 80 ? 'Sangat Baik' : 'Perlu Perhatian' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <x-heroicon-m-check-circle class="w-4 h-4" /> Slot Sesuai (100%)
                        </span>
                        <p class="text-2xl font-bold mt-1 text-gray-900 dark:text-white">{{ $summary['passed'] }}</p>
                    </div>

                    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
                        <span class="text-xs font-medium text-rose-600 dark:text-rose-400 flex items-center gap-1">
                            <x-heroicon-m-x-circle class="w-4 h-4" /> Slot Ada Deviasi
                        </span>
                        <p class="text-2xl font-bold mt-1 text-gray-900 dark:text-white">{{ $summary['failed'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Pie / Donut Chart Matriks Visual --}}
            <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col justify-between" x-data="{
                init() {
                    const ctx = document.getElementById('tafAccuracyChart');
                    if(ctx) {
                        new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: ['Sesuai Sempurna', 'Ada Deviasi'],
                                datasets: [{
                                    data: [{{ $summary['passed'] }}, {{ $summary['failed'] }}],
                                    backgroundColor: ['#10b981', '#f43f5e'],
                                    borderWidth: 0
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'bottom', labels: { color: '#9ca3af', font: { size: 11 } } }
                                },
                                cutout: '70%'
                            }
                        });
                    }
                }
            }">
                <h3 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Matriks Proporsi Kesesuaian</h3>
                <div class="relative h-44 my-2">
                    <canvas id="tafAccuracyChart"></canvas>
                </div>
            </div>

            {{-- Progress Bar Akurasi 7 Parameter SOP --}}
            <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-3">
                <h3 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Akurasi per Parameter SOP</h3>
                <div class="space-y-2.5 max-h-48 overflow-y-auto pr-1">
                    @foreach($summary['paramStats'] as $param)
                        @php
                            $rate = $param['total'] > 0 ? round(($param['pass'] / $param['total']) * 100) : 0;
                        @endphp
                        <div>
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="font-medium text-gray-600 dark:text-gray-300">{{ $param['label'] }}</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ $rate }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-800 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-primary-500 h-1.5 rounded-full" style="width: {{ $rate }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- Tabel Verifikasi Realtime High-Contrast --}}
        <div class="overflow-x-auto bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">
                        <th class="p-3">Jam (UTC)</th>
                        <th class="p-3">Group</th>
                        <th class="p-3 min-w-[280px]">Sandi TAF & METAR Raw</th>
                        <th class="p-2 text-center">Angin</th>
                        <th class="p-2 text-center">Spd</th>
                        <th class="p-2 text-center">Gust</th>
                        <th class="p-2 text-center">Vis</th>
                        <th class="p-2 text-center">Cuaca</th>
                        <th class="p-2 text-center">Awan</th>
                        <th class="p-2 text-center">Ceiling</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($rows as $row)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                            <td class="p-3 font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ $row['time'] }}</td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-[10px] font-extrabold dark:bg-blue-950 dark:text-blue-300 bg-blue-100 text-blue-800">
                                    {{ $row['group'] }}
                                </span>
                            </td>
                            <td class="p-3 space-y-1.5 font-mono">
                                <div class="p-1.5 rounded bg-blue-50/60 dark:bg-blue-950/30 border border-blue-200/50 dark:border-blue-800/40 text-blue-700 dark:text-blue-300 text-[11px] leading-relaxed">
                                    <span class="font-bold text-blue-900 dark:text-blue-200 mr-1">TAF:</span> {{ $row['taf_raw'] }}
                                </div>
                                <div class="p-1.5 rounded bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/50 dark:border-emerald-800/40 text-emerald-700 dark:text-emerald-300 text-[11px] leading-relaxed">
                                    <span class="font-bold text-emerald-900 dark:text-emerald-200 mr-1">METAR:</span> {{ $row['metar_raw'] }}
                                </div>
                            </td>

                            @if($row['scores'])
                                @foreach(['wind_dir', 'wind_speed', 'gust', 'visibility', 'precip', 'cloud', 'ceiling'] as $param)
                                    <td class="p-2 text-center">
                                        @if($row['scores'][$param] == 1)
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-bold text-xs">✓</span>
                                        @else
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 font-bold text-xs">✕</span>
                                        @endif
                                    </td>
                                @endforeach
                            @else
                                <td colspan="7" class="p-3 text-center italic text-gray-400 dark:text-gray-500">
                                    Data belum lengkap
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-6 text-center text-gray-500 dark:text-gray-400">
                                Tidak ada data verifikasi pada tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-filament-panels::page>