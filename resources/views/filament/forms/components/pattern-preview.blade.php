@php
    $pattern = $pattern ?? '';
    $starting = (int) ($starting ?? 1);
    $total = (int) ($total ?? 1);

    $names = [];
    $maxPreview = 5;

    if (!empty($pattern)) {
        for ($i = 0; $i < min($total, $maxPreview); $i++) {
            $number = $starting + $i;
            $name = str_replace('{number}', $number, $pattern);
            $names[] = $name;
        }
    }

    $remaining = max(0, $total - $maxPreview);
@endphp

@if(count($names) > 0)
    <div class="space-y-1">
        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Preview ({{ $total }} account{{ $total > 1 ? 's' : '' }} will be created):
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($names as $name)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800 dark:bg-primary-800 dark:text-primary-100">
                    {{ $name }}
                </span>
            @endforeach

            @if($remaining > 0)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100">
                    +{{ $remaining }} more
                </span>
            @endif
        </div>
    </div>
@endif
