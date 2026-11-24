@props(['items' => [], 'placeholder' => '📖', 'link' => 'View book', 'message' => null])
{{-- ja ir $message, vāku pieliek pašam e-pastam (cid), citādi rāda ar saiti --}}
{{-- grāmatu kartītes: datorā 3 blakus, telefonā viena zem otras --}}
{{-- div ar inline-block pats pāriet nākamajā rindā, kad nav vietas (strādā arī bez media query) --}}
{{-- outlook classic neprot inline-block, tam ir slēpta tabula <!--[if mso]> --}}
{{-- vāks, nosaukums un "View book" ved uz lapu, kur atveras tieši šī grāmata (vai autora grāmatas) --}}
{{-- iekšā nedrīkst būt tukšu rindu, citādi markdown to sagriež --}}
<div class="cards" style="font-size: 0; text-align: center;">
<!--[if mso]><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td width="33%" valign="top"><![endif]-->
@foreach ($items as $i => $item)
@php
    $url = $item['url'] ?? null;
    $src = $item['cover'] ?? null;
    if ($src && $message && !empty($item['cover_file'])) {
        $src = $message->embed($item['cover_file']);
    }
@endphp
<div class="card" style="display: inline-block; width: 100%; max-width: 190px; vertical-align: top; text-align: center; font-size: 16px;">
<div class="card-pad" style="padding: 0 6px 22px;">
<div class="card-cover" style="text-align: center;">
@if ($url)<a href="{{ $url }}" style="text-decoration: none;">@endif
@if (!empty($item['cover']))
<img class="card-img" src="{{ $src }}" width="72" height="104" alt="{{ $item['title'] }}" style="display: inline-block; width: 72px; height: 104px; object-fit: cover; border: 0;">
@else
<div class="card-ph" style="display: inline-block; width: 72px; height: 104px; line-height: 104px; font-size: 28px; text-align: center;">{{ $placeholder }}</div>
@endif
@if ($url)</a>@endif
</div>
<div class="card-body">
<p class="card-rank">#{{ $i + 1 }}</p>
@if ($url)
<p class="card-title"><a class="card-title-link" href="{{ $url }}">{{ $item['title'] }}</a></p>
@else
<p class="card-title">{{ $item['title'] }}</p>
@endif
@if (!empty($item['meta']))
<p class="card-meta">{{ $item['meta'] }}</p>
@endif
@if (!empty($item['badge']))
<p class="card-badge">{{ $item['badge'] }}</p>
@endif
@if ($url)
<p class="card-link"><a href="{{ $url }}">{{ $link }} →</a></p>
@endif
</div>
</div>
</div>
@if (!$loop->last)
@if ($loop->iteration % 3 == 0)
<!--[if mso]></td></tr><tr><td width="33%" valign="top"><![endif]-->
@else
<!--[if mso]></td><td width="33%" valign="top"><![endif]-->
@endif
@endif
@endforeach
<!--[if mso]></td></tr></table><![endif]-->
</div>
