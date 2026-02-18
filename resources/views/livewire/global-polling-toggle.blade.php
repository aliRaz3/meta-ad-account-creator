<div x-data="{
    isEnabled: @entangle('isPollingEnabled'),
    init() {
        // Load state from sessionStorage on init
        const saved = sessionStorage.getItem('globalPollingEnabled');
        if (saved !== null) {
            this.isEnabled = saved === 'true';
        }

        // Listen for state changes and save to sessionStorage
        this.$watch('isEnabled', (value) => {
            sessionStorage.setItem('globalPollingEnabled', value);
            window.dispatchEvent(new CustomEvent('global-polling-changed', { detail: { enabled: value } }));
        });
    }
}">
    <button wire:click="togglePolling" type="button" title="Enable auto Refresh"
        class="fi-topbar-item flex items-center justify-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5 {{ $isPollingEnabled ? 'bg-gray-100 dark:bg-white/10' : 'bg-transparent' }}"
        style="outline: none;">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none"
            stroke="#000000" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
            <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
        </svg>

    </button>
</div>
