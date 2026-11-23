@props(['items' => []])
{{-- 3 skaitļi blakus, parasta tabula, lai paliek vienā rindā arī telefonā --}}
<table class="stats" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
@foreach ($items as $item)
<td class="stat" width="{{ floor(100 / max(count($items), 1)) }}%" align="center" valign="top">
<p class="stat-value">{{ $item['value'] }}</p>
<p class="stat-label">{{ $item['label'] }}</p>
</td>
@if (!$loop->last)
<td class="stat-gap" width="10" style="font-size: 0; line-height: 0;">&nbsp;</td>
@endif
@endforeach
</tr>
</table>
