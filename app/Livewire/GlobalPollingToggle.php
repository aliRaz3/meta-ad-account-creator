<?php

namespace App\Livewire;

use Livewire\Component;

class GlobalPollingToggle extends Component
{
    public bool $isPollingEnabled = false;

    public function mount(): void
    {
        // Load the state from session if available
        $this->isPollingEnabled = session('global_polling_enabled', false);
    }

    public function togglePolling(): void
    {
        $this->isPollingEnabled = !$this->isPollingEnabled;
        
        // Save to session
        session(['global_polling_enabled' => $this->isPollingEnabled]);
        
        // Dispatch browser event to notify all components
        $this->dispatch('polling-toggled', enabled: $this->isPollingEnabled);
    }

    public function render()
    {
        return view('livewire.global-polling-toggle');
    }
}
