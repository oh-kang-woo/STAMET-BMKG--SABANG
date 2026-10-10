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
                            Verifikasi TAF Realtime
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Stasiun Meteorologi Maimun Saleh Sabang (WITN)
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800/80 px-3.5 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 text-xs">
                        <span class="text-gray-500 dark:text-gray-400 font-medium">Tanggal:</span>
                        <input type="date" 
                               wire:model.live="selectedDate" 
                               class="bg-transparent border-0 p-0 text-xs font-bold text-primary-600 dark:text-primary-400 focus:ring-0 cursor-pointer [color-scheme:dark]">
                    </div>

                    <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800/80 px-3.5 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 text-xs">
                        <span class="text-gray-500 dark:text-gray-400 font-medium">Stasiun:</span>
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">WITN</span>
                    </div>

                    {{-- Tombol Refresh / Sync --}}
                    <x-filament::button wire:click="refreshData" icon="heroicon-m-arrow-path" size="sm">
                        Sync Data
                    </x-filament::button>

                    {{-- Tombol Export Excel Membuka Pop-Up Modal --}}
                    {{ $this->exportExcelAction }}
                </div>
            </div>
        </x-filament::section>

        {{-- TAMBAHAN SECTION QC TAFOR --}}
        <x-filament::section icon="heroicon-o-shield-check" header="Quality Control Sandi TAFOR Sebelum Kirim">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Kiri: Form Input -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">INPUT SANDI TAFOR</label>
                        <textarea wire:model.defer="rawTaf" rows="5" class="w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm font-mono" placeholder="Contoh: FTID40 WITN 290500 CCA TAF COR..."></textarea>
                    </div>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="checkQualityControl" color="primary" size="sm">
                            Verifikasi TAFOR
                        </x-filament::button>
                        <x-filament::button wire:click="clearQualityControl" color="gray" size="sm">
                            Bersihkan
                        </x-filament::button>
                    </div>
                </div>

                <!-- Kanan: Hasil QC -->
                <div>
                    @if(!empty($qcResult))
                        <div class="mb-3 p-3 rounded-lg text-sm {{ $qcResult['is_valid'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' }}">
                            @if($qcResult['is_valid'])
                                <span class="font-bold">✓ Sandi TAF Valid</span> — Tidak ada temuan.
                            @else
                                <span class="font-bold">✗ Sandi TAF perlu diperbaiki</span> — {{ $qcResult['errors'] }} temuan.
                            @endif
                        </div>
                        
                        <div class="overflow-x-auto border rounded-lg dark:border-white/10">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-gray-50 dark:bg-white/5 border-b dark:border-white/10">
                                    <tr>
                                        <th class="p-2 border-r dark:border-white/10 font-bold">Elemen</th>
                                        <th class="p-2 border-r dark:border-white/10 font-bold">Aturan QC</th>
                                        <th class="p-2 font-bold">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach($qcResult['results'] as $res)
                                        <tr class="{{ $res['is_valid'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400 font-bold bg-rose-50/50 dark:bg-rose-900/10' }}">
                                            <td class="p-2 border-r dark:border-white/10 font-mono">{{ $res['element'] }}</td>
                                            <td class="p-2 border-r dark:border-white/10">{{ $res['rule'] }}</td>
                                            <td class="p-2">{{ $res['remark'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="h-full flex items-center justify-center text-sm text-gray-400 italic border-2 border-dashed rounded-lg p-6 dark:border-white/10">
                            Silakan masukkan sandi TAFOR dan klik Verifikasi untuk melihat hasil QC.
                        </div>
                    @endif
                </div>

            </div>
        </x-filament::section>
        {{-- AKHIR SECTION QC TAFOR --}}

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
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- Matriks Ketersediaan Data Telemetri --}}
            <div class="lg:col-span-8">
                <x-filament::section icon="heroicon-m-clock" header="Matriks Ketersediaan Data Telemetri (24 Jam UTC)">
                    <div class="overflow-x-auto">
                        <table class="w-full text-center text-xs border-collapse">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-white/5 font-mono text-[11px] text-gray-600 dark:text-gray-400 border-b dark:border-white/10">
                                    <th class="p-2 text-left font-semibold">Param</th>
                                    @for($i=0; $i<24; $i++)
                                        <th class="p-2 border-l dark:border-white/10 font-semibold">{{ sprintf('%02d', $i) }}</th>
                                    @endfor
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10 text-[11px]">
                                <!-- Row TAF -->
                                <tr>
                                    <td class="p-2 text-left font-bold text-gray-700 dark:text-gray-300">TAF</td>
                                    @for($i=0; $i<24; $i++)
                                        <td class="p-2 border-l dark:border-white/10">
                                            @if($hourlyStatus[$i]['taf'])
                                                <div class="w-3 h-3 mx-auto rounded-full bg-emerald-500"></div>
                                            @else
                                                <div class="w-3 h-3 mx-auto rounded-full bg-gray-200 dark:bg-gray-700"></div>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                                <!-- Row METAR -->
                                <tr>
                                    <td class="p-2 text-left font-bold text-gray-700 dark:text-gray-300">METAR</td>
                                    @for($i=0; $i<24; $i++)
                                        <td class="p-2 border-l dark:border-white/10">
                                            @if($hourlyStatus[$i]['metar'])
                                                <div class="w-3 h-3 mx-auto rounded-full bg-blue-500"></div>
                                            @else
                                                <div class="w-3 h-3 mx-auto rounded-full bg-gray-200 dark:bg-gray-700"></div>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                                <!-- Row Score 00 -->
                                <tr>
                                    <td class="p-2 text-left font-bold text-gray-700 dark:text-gray-300">Skor 00</td>
                                    @for($i=0; $i<24; $i++)
                                        <td class="p-2 border-l dark:border-white/10 font-mono font-bold {{ $hourlyStatus[$i]['score00'] === 7 ? 'text-emerald-600' : ($hourlyStatus[$i]['score00'] !== null ? 'text-rose-600' : 'text-gray-400') }}">
                                            {{ $hourlyStatus[$i]['score00'] ?? '-' }}
                                        </td>
                                    @endfor
                                </tr>
                                <!-- Row Score 30 -->
                                <tr>
                                    <td class="p-2 text-left font-bold text-gray-700 dark:text-gray-300">Skor 30</td>
                                    @for($i=0; $i<24; $i++)
                                        <td class="p-2 border-l dark:border-white/10 font-mono font-bold {{ $hourlyStatus[$i]['score30'] === 7 ? 'text-emerald-600' : ($hourlyStatus[$i]['score30'] !== null ? 'text-rose-600' : 'text-gray-400') }}">
                                            {{ $hourlyStatus[$i]['score30'] ?? '-' }}
                                        </td>
                                    @endfor
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            </div>

            {{-- Ringkasan Akurasi (Chart) --}}
            <div class="lg:col-span-4">
                <x-filament::section icon="heroicon-m-chart-pie" header="Ringkasan Akurasi Harian">
                    <div class="flex flex-col items-center justify-center p-4">
                        <div class="relative w-40 h-40">
                            <canvas id="accuracyChart"></canvas>
                            <div class="absolute inset-0 flex items-center justify-center flex-col">
                                <span class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $summary['accuracy'] }}%</span>
                            </div>
                        </div>
                        <div class="mt-6 w-full grid grid-cols-2 gap-4 text-center">
                            <div class="bg-emerald-50 dark:bg-emerald-500/10 p-3 rounded-lg border border-emerald-100 dark:border-emerald-500/20">
                                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $summary['passed'] }}</div>
                                <div class="text-[10px] font-bold text-emerald-600/70 dark:text-emerald-400/70 uppercase">Tepat</div>
                            </div>
                            <div class="bg-rose-50 dark:bg-rose-500/10 p-3 rounded-lg border border-rose-100 dark:border-rose-500/20">
                                <div class="text-xl font-bold text-rose-600 dark:text-rose-400">{{ $summary['failed'] }}</div>
                                <div class="text-[10px] font-bold text-rose-600/70 dark:text-rose-400/70 uppercase">Meleset</div>
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            const initChart = () => {
                const ctx = document.getElementById('accuracyChart');
                if(ctx) {
                    if(window.tafChart) { window.tafChart.destroy(); }
                    window.tafChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Tepat', 'Meleset'],
                            datasets: [{
                                data: [{{ $summary['passed'] }}, {{ $summary['failed'] }}],
                                backgroundColor: ['#10b981', '#f43f5e'],
                                borderWidth: 0,
                                cutout: '75%'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { enabled: true } }
                        }
                    });
                }
            };
            initChart();
            Livewire.hook('morph.updated', () => { initChart(); });
        });
    </script>
</x-filament-panels::page>