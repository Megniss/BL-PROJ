@props(['items' => [], 'placeholder' => '📖'])
{{-- grāmatu kartītes: datorā 3 blakus, telefonā viena zem otras --}}
{{-- div ar inline-block pats pāriet nākamajā rindā, kad nav vietas (strādā arī bez media query) --}}
{{-- outlook classic neprot inline-block, tam ir slēpta tabula <!--[if mso]> --}}
{{-- iekšā nedrīkst būt tukšu rindu, citādi markdown to sagriež --}}
<div class="cards" style="font-size: 0; text-align: center;">
<!--[if mso]><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td width="33%" valign="top"><![endif]-->
@foreach ($items as $i => $item)
<div class="card" style="display: inline-block; width: 100%; max-width: 190px; vertical-align: top; text-align: center; font-size: 16px;">
<div class="card-pad" style="padding: 0 6px 22px;">
<div class="card-cover" style="text-align: center;">
@if (!empty($item['cover']))
<img class="card-img" src="{{ $item['cover'] }}" width="72" height="104" alt="" style="display: inline-block; width: 72px; height: 104px; object-fit: cover;">
@else
<div class="card-ph" style="display: inline-block; width: 72px; height: 104px; line-height: 104px; font-size: 28px; text-align: center;">{{ $placeholder }}</div>
@endif
</div>
<div class="card-body">
<p class="card-rank">#{{ $i + 1 }}</p>
<p class="card-title">{{ $item['title'] }}</p>
@if (!empty($item['meta']))
<p class="card-meta">{{ $item['meta'] }}</p>
@endif
@if (!empty($item['badge']))
<p class="card-badge">{{ $item['badge'] }}</p>
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
