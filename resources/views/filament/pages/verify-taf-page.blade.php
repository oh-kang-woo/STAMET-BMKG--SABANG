<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit">
                Proses Verifikasi
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>