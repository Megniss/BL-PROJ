@props(['items' => []])
@php
    // ja vismaz vienai grāmatai ir vāks, visām rindām atstājam vietu, lai kolonnas nenobīdās
    $withCovers = collect($items)->contains(fn($item) => !empty($item['cover']));
@endphp
{{-- grāmatu saraksts ar tabulām, lai outlook classic nesabojā. bez tukšām rindām, citādi markdown to sagriež --}}
<table class="book-list" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($items as $i => $item)
<tr class="book-row">
<td class="book-rank" width="32">{{ $i + 1 }}.</td>
@if ($withCovers)
<td class="book-cover" width="52">
@if (!empty($item['cover']))
<img src="{{ $item['cover'] }}" width="40" height="56" alt="" style="width: 40px; height: 56px; object-fit: cover;">
@endif
</td>
@endif
<td>
<p class="book-title">{{ $item['title'] }}</p>
@if (!empty($item['meta']))
<p class="book-meta">{{ $item['meta'] }}</p>
@endif
</td>
<td class="book-badge" width="80" align="right">{{ $item['badge'] ?? '' }}</td>
</tr>
@endforeach
</table>
