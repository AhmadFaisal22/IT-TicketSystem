@props(['url'])

@php
    $baseUrl = rtrim($url, '/');
    $logoUrl = config('mail.logo_url');
@endphp

<tr>
<td class="header">
<a href="{{ $baseUrl }}" target="_blank" rel="noopener">
@if ($logoUrl)
<img src="{{ $logoUrl }}" class="logo" alt="SEG Solar">
@else
<span class="logo-fallback">SEG</span>
@endif
<span class="brand-title">{{ trim($slot) }}</span>
<span class="brand-subtitle">SEG Solar Manufaktur Indonesia</span>
</a>
</td>
</tr>
