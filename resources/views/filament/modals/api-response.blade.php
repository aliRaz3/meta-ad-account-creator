{{-- <x-filament::modal width="3xl"> --}}
    <x-slot name="heading">
        API Response
    </x-slot>

    <div class="space-y-4">
        @if($response)
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                <pre class="text-xs overflow-auto max-h-96"><code>{{ json_encode(json_decode($response), JSON_PRETTY_PRINT) }}</code></pre>
            </div>
        @else
            <div class="text-sm text-gray-500 dark:text-gray-400">
                No API response available.
            </div>
        @endif
    </div>
{{-- </x-filament::modal> --}}
