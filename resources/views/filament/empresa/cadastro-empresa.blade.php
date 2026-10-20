<x-filament-panels::page>
    {{ $this->form }}

    @foreach ($this->abas() as $aba)
        @livewire($aba, ['ownerRecord' => $this->empresa(), 'pageClass' => $this->paginaDasAbas()], key($aba))
    @endforeach
</x-filament-panels::page>
