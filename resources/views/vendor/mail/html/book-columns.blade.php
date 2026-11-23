@props(['sections' => []])
{{-- sadaļas 2 blakus uz datora, telefonā viena zem otras --}}
{{-- div ar inline-block pats pāriet nākamajā rindā, kad nav vietas (strādā arī bez media query) --}}
{{-- outlook classic neprot inline-block, tam ir slēpta tabula <!--[if mso]> --}}
{{-- iekšā nedrīkst būt tukšu rindu, citādi markdown to sagriež --}}
<div class="cols" style="font-size: 0; text-align: left;">
<!--[if mso]><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td width="50%" valign="top"><![endif]-->
@foreach ($sections as $s)
@php
    $left = $loop->iteration % 2 == 1;
    $withCovers = collect($s['items'] ?? [])->contains(fn($item) => !empty($item['cover']));
@endphp
<div class="col" style="display: inline-block; width: 100%; max-width: 284px; vertical-align: top; text-align: left; font-size: 16px;">
<div class="col-pad" style="padding: {{ $left ? '0 10px 0 0' : '0 0 0 10px' }};">
<h2>{{ $s['icon'] }} {{ $s['title'] }}</h2>
@if (!empty($s['items']))
<table class="book-list" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($s['items'] as $i => $item)
<tr class="book-row">
<td class="book-rank" width="26">{{ $i + 1 }}.</td>
@if ($withCovers)
<td class="book-cover" width="48">@if (!empty($item['cover']))<img src="{{ $item['cover'] }}" width="40" height="56" alt="" style="width: 40px; height: 56px; object-fit: cover;">@endif</td>
@endif
<td>
<p class="book-title">{{ $item['title'] }}</p>
@if (!empty($item['meta']))
<p class="book-meta">{{ $item['meta'] }}</p>
@endif
@if (!empty($item['badge']))
<p class="book-badge">{{ $item['badge'] }}</p>
@endif
</td>
</tr>
@endforeach
</table>
@else
<p class="book-meta" style="font-size: 14px;">{{ $s['text'] ?? '' }}</p>
@if (!empty($s['link']))
<p style="font-size: 14px;"><a href="{{ $s['link']['url'] }}">{{ $s['link']['label'] }} →</a></p>
@endif
@endif
</div>
</div>
@if (!$loop->last)
@if ($left)
<!--[if mso]></td><td width="50%" valign="top"><![endif]-->
@else
<!--[if mso]></td></tr><tr><td width="50%" valign="top"><![endif]-->
@endif
@endif
@endforeach
<!--[if mso]></td></tr></table><![endif]-->
</div>
