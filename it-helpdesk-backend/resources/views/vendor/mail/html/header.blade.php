@props(['url'])

@php
    $baseUrl = trim((string) $url);
    $brandMarkup = '<span class="brand-mark">SEG</span>'
        . '<span class="brand-title">' . e(trim($slot)) . '</span>'
        . '<span class="brand-subtitle">SEG Solar Manufactor</span>';
@endphp

<tr>
<td class="header">
@if ($baseUrl !== '')
<a href="{{ rtrim($baseUrl, '/') }}" target="_blank" rel="noopener">
{!! $brandMarkup !!}
</a>
@else
<div class="header-panel">
{!! $brandMarkup !!}
</div>
@endif
</td>
</tr>
