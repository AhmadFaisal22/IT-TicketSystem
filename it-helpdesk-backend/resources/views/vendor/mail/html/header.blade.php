@props(['url'])

@php
    $baseUrl = rtrim($url, '/');
    $logoUrl = $baseUrl.'/SEG%20Logo.png';
@endphp

<tr>
<td class="header">
<a href="{{ $baseUrl }}" target="_blank" rel="noopener">
<img src="{{ $logoUrl }}" class="logo" alt="SEG Solar">
<span class="brand-title">{{ trim($slot) }}</span>
<span class="brand-subtitle">SEG Solar Manufaktur Indonesia</span>
</a>
</td>
</tr>
