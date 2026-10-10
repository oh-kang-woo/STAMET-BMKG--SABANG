<x-filament-panels::page>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div wire:poll.300s="refreshData" class="space-y-6">

        {{-- Top Bar & Control Section --}}
        <x-filament::section class="shadow-sm border-t-4 border-t-primary-500">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-xl bg-primary-500/10 text-primary-500">
                        <div style="width: 28px; height: 28px;">
                            <x-heroicon-m-signal class="animate-pulse" />
                        </div>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold tracking-tight">
                            Verifikasi TAF Realtime
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                            Stasiun Meteorologi Maimun Saleh Sabang (WITN)
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900 px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-sm">
                        <div style="width: 16px; height: 16px;">
                            <x-heroicon-o-calendar class="text-gray-400" />
                        </div>
                        <span class="text-gray-500 dark:text-gray-400 font-semibold">Tanggal:</span>
                        <input type="date" 
                               wire:model.live="selectedDate" 
                               class="bg-transparent border-0 p-0 text-sm font-bold text-primary-600 dark:text-primary-400 focus:ring-0 cursor-pointer [color-scheme:dark]">
                    </div>

                    <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900 px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-sm">
                        <div style="width: 16px; height: 16px;">
                            <x-heroicon-o-map-pin class="text-gray-400" />
                        </div>
                        <span class="text-gray-500 dark:text-gray-400 font-semibold">Stasiun:</span>
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">WITN</span>
                    </div>

                    <div class="h-8 w-px bg-gray-200 dark:bg-gray-700 hidden sm:block"></div>

                    <x-filament::button wire:click="refreshData" icon="heroicon-m-arrow-path" size="sm">
                        Sync Data
                    </x-filament::button>

                    {{ $this->exportExcelAction }}
                </div>
            </div>
        </x-filament::section>

        {{-- SECTION QC TAFOR --}}
        <x-filament::section icon="heroicon-o-shield-check" header="Quality Control Sandi TAFOR Sebelum Kirim">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Kiri: Form Input -->
                <div class="space-y-4">
                    <div>
                        <label class="flex items-center gap-2 text-sm font-bold mb-2">
                            <div style="width: 16px; height: 16px;">
                                <x-heroicon-m-pencil-square class="text-primary-500"/>
                            </div>
                            INPUT SANDI TAFOR
                        </label>
                        <textarea wire:model.defer="rawTaf" rows="6" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm font-mono leading-relaxed" placeholder="Ketik atau paste sandi TAFOR di sini...&#10;Contoh: FTID40 WITN 290500 CCA TAF COR..."></textarea>
                    </div>
                    <div class="flex gap-3">
                        <x-filament::button wire:click="checkQualityControl" color="primary" size="sm">
                            Verifikasi TAFOR
                        </x-filament::button>
                        <x-filament::button wire:click="clearQualityControl" color="gray" size="sm">
                            Bersihkan
                        </x-filament::button>
                    </div>
                </div>

                <!-- Kanan: Hasil QC -->
                <div class="flex flex-col h-full">
                    @if(!empty($qcResult))
                        <div class="mb-4 p-4 rounded-lg text-sm flex items-start gap-3 border {{ $qcResult['is_valid'] ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300' : 'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300' }}">
                            @if($qcResult['is_valid'])
                                <div style="width: 24px; height: 24px; flex-shrink: 0;">
                                    <x-heroicon-m-check-circle class="text-emerald-500"/>
                                </div>
                                <div>
                                    <span class="font-bold block text-base text-emerald-900 dark:text-emerald-400">Sandi TAF Valid</span>
                                    <span class="opacity-90">Tidak ditemukan kesalahan penulisan format standar.</span>
                                </div>
                            @else
                                <div style="width: 24px; height: 24px; flex-shrink: 0;">
                                    <x-heroicon-m-x-circle class="text-rose-500"/>
                                </div>
                                <div>
                                    <span class="font-bold block text-base text-rose-900 dark:text-rose-400">Sandi TAF perlu diperbaiki</span>
                                    <span class="opacity-90">Ditemukan <strong class="font-extrabold">{{ $qcResult['errors'] }}</strong> elemen yang tidak sesuai standar pengiriman.</span>
                                </div>
                            @endif
                        </div>
                        
                        <div class="overflow-x-auto border rounded-lg dark:border-gray-700 flex-1">
                            <table class="w-full text-sm text-left">
                                <thead class="border-b dark:border-gray-700">
                                    <tr>
                                        <th class="p-3 border-r dark:border-gray-700 font-bold text-gray-900 dark:text-gray-200">Elemen</th>
                                        <th class="p-3 border-r dark:border-gray-700 font-bold text-gray-900 dark:text-gray-200">Aturan QC</th>
                                        <th class="p-3 font-bold text-gray-900 dark:text-gray-200">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/50 bg-white dark:bg-transparent">
                                    @foreach($qcResult['results'] as $res)
                                        <tr class="{{ $res['is_valid'] ? 'text-gray-800 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors' : 'bg-rose-50/50 dark:bg-rose-900/20 text-rose-800 dark:text-rose-300 hover:bg-rose-100/50 dark:hover:bg-rose-900/30 transition-colors' }}">
                                            <td class="p-3 border-r dark:border-gray-700/50 font-mono font-bold text-primary-600 dark:text-primary-400">{{ $res['element'] }}</td>
                                            <td class="p-3 border-r dark:border-gray-700/50 font-medium">{{ $res['rule'] }}</td>
                                            <td class="p-3 flex items-center gap-2.5 font-medium">
                                                @if($res['is_valid'])
                                                    <div class="flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex-shrink-0">
                                                        <x-heroicon-m-check style="width: 12px; height: 12px;"/>
                                                    </div>
                                                @else
                                                    <div class="flex items-center justify-center w-5 h-5 rounded-full bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 flex-shrink-0">
                                                        <x-heroicon-m-x-mark style="width: 12px; height: 12px;"/>
                                                    </div>
                                                @endif
                                                {{ $res['remark'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="h-full min-h-[200px] flex flex-col items-center justify-center text-sm text-gray-500 dark:text-gray-400 italic border-2 border-dashed rounded-lg p-8 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                            <div style="width: 48px; height: 48px; margin-bottom: 12px;">
                                <x-heroicon-o-document-magnifying-glass class="text-gray-400 dark:text-gray-500"/>
                            </div>
                            <p class="text-center">Silakan masukkan sandi TAFOR di kolom kiri<br>lalu klik <strong class="font-semibold text-primary-500">Verifikasi TAFOR</strong>.</p>
                        </div>
                    @endif
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

                $hourlyStatus[$h] = [
                    'taf' => ($slot00 && ($slot00['taf_raw'] ?? '') !== 'Data TAF Belum Tersedia') || ($slot30 && ($slot30['taf_raw'] ?? '') !== 'Data TAF Belum Tersedia'),
                    'metar' => ($slot00 && ($slot00['metar_raw'] ?? '') !== 'Data METAR Belum Tersedia') || ($slot30 && ($slot30['metar_raw'] ?? '') !== 'Data METAR Belum Tersedia'),
                    'score00' => ($slot00 && is_array($slot00['scores'] ?? null)) ? array_sum($slot00['scores']) : null,
                    'score30' => ($slot30 && is_array($slot30['scores'] ?? null)) ? array_sum($slot30['scores']) : null,
                ];
            }
        @endphp

        {{-- Section Utama: Matriks 24-Jam & Pie Chart --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- Matriks Ketersediaan Data Telemetri --}}
            <div class="lg:col-span-8">
                <x-filament::section icon="heroicon-m-table-cells" header="Matriks Ketersediaan & Akurasi (24 Jam UTC)">
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-center text-xs border-collapse">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-transparent font-mono text-[11px] text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                    <th class="p-3 text-left font-extrabold uppercase tracking-wider">Param</th>
                                    @for($i=0; $i<24; $i++)
                                        <th class="p-2 border-l border-gray-200 dark:border-gray-700 font-bold">{{ sprintf('%02d', $i) }}</th>
                                    @endfor
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-[11px] bg-white dark:bg-gray-900/30">
                                <!-- Row TAF -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="p-3 text-left font-bold text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-emerald-500"></div> TAF
                                        </div>
                                    </td>
                                    @for($i=0; $i<24; $i++)
                                        <td class="p-2 border-l border-gray-100 dark:border-gray-800">
                                            @if($hourlyStatus[$i]['taf'])
                                                <div class="w-3 h-3 mx-auto rounded-full bg-emerald-500"></div>
                                            @else
                                                <div class="w-2 h-2 mx-auto rounded-full bg-gray-200 dark:bg-gray-800"></div>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                                <!-- Row METAR -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="p-3 text-left font-bold text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full bg-blue-500"></div> METAR
                                        </div>
                                    </td>
                                    @for($i=0; $i<24; $i++)
                                        <td class="p-2 border-l border-gray-100 dark:border-gray-800">
                                            @if($hourlyStatus[$i]['metar'])
                                                <div class="w-3 h-3 mx-auto rounded-full bg-blue-500"></div>
                                            @else
                                                <div class="w-2 h-2 mx-auto rounded-full bg-gray-200 dark:bg-gray-800"></div>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                                <!-- Row Score 00 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="p-3 text-left font-bold text-gray-700 dark:text-gray-300 whitespace-nowrap">Skor 00</td>
                                    @for($i=0; $i<24; $i++)
                                        <td class="p-2 border-l border-gray-100 dark:border-gray-800">
                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-md font-mono font-bold text-xs
                                                {{ $hourlyStatus[$i]['score00'] === 7 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : ($hourlyStatus[$i]['score00'] !== null ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400') }}">
                                                {{ $hourlyStatus[$i]['score00'] ?? '-' }}
                                            </span>
                                        </td>
                                    @endfor
                                </tr>
                                <!-- Row Score 30 -->
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="p-3 text-left font-bold text-gray-700 dark:text-gray-300 whitespace-nowrap">Skor 30</td>
                                    @for($i=0; $i<24; $i++)
                                        <td class="p-2 border-l border-gray-100 dark:border-gray-800">
                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-md font-mono font-bold text-xs
                                                {{ $hourlyStatus[$i]['score30'] === 7 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : ($hourlyStatus[$i]['score30'] !== null ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400') }}">
                                                {{ $hourlyStatus[$i]['score30'] ?? '-' }}
                                            </span>
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
                <x-filament::section icon="heroicon-m-chart-pie" header="Ringkasan Akurasi" class="h-full flex flex-col">
                    <div class="flex-1 flex flex-col items-center justify-center p-6 bg-gray-50 dark:bg-gray-900/30 rounded-lg">
                        
                        <!-- Area Chart -->
                        <div class="relative w-48 h-48 mb-6">
                            <canvas id="accuracyChart"></canvas>
                            <div class="absolute inset-0 flex items-center justify-center flex-col pointer-events-none">
                                <span class="text-4xl font-extrabold tracking-tighter">{{ $summary['accuracy'] }}%</span>
                                <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mt-1">Akurasi</span>
                            </div>
                        </div>
                        
                        <!-- Kotak Statistik -->
                        <div class="w-full grid grid-cols-2 gap-4 text-center mt-auto">
                            <div class="bg-white dark:bg-gray-800/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-center gap-1.5 mb-1">
                                    <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tepat</div>
                                </div>
                                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $summary['passed'] }}</div>
                            </div>
                            <div class="bg-white dark:bg-gray-800/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-center gap-1.5 mb-1">
                                    <div class="w-2 h-2 rounded-full bg-rose-500"></div>
                                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Meleset</div>
                                </div>
                                <div class="text-2xl font-black text-rose-600 dark:text-rose-400">{{ $summary['failed'] }}</div>
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
                                hoverBackgroundColor: ['#059669', '#e11d48'],
                                borderWidth: 0,
                                cutout: '75%',
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { animateScale: true, animateRotate: true },
                            plugins: { 
                                legend: { display: false }, 
                                tooltip: { 
                                    enabled: true,
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleFont: { size: 14, family: 'sans-serif' },
                                    bodyFont: { size: 14, weight: 'bold' },
                                    displayColors: true,
                                    cornerRadius: 8,
                                } 
                            }
                        }
                    });
                }
            };
            initChart();
            Livewire.hook('morph.updated', () => { initChart(); });
        });
    </script>
</x-filament-panels::page>