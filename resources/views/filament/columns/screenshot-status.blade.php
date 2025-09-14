@php
$hasScreenshot = $record->screenshot_path && Storage::disk('public')->exists($record->screenshot_path);
@endphp

@if($hasScreenshot)
<div class="flex items-center space-x-2">
    <x-filament::icon
        icon="heroicon-o-check-circle"
        class="h-5 w-5 text-success-500" />
    <a
        href="{{ Storage::disk('public')->url($record->screenshot_path) }}"
        target="_blank"
        class="text-primary-600 hover:text-primary-700 text-sm">
        Lihat
    </a>
</div>
@else
<div class="flex items-center space-x-2">
    <x-filament::icon
        icon="heroicon-o-x-circle"
        class="h-5 w-5 text-danger-500" />
    <span class="text-gray-500 text-sm">Tidak ada</span>
</div>
@endif