@props(['items' => [], 'placeholder' => '', 'link' => ''])
@foreach ($items as $i => $item)
{{ $i + 1 }}. {{ $item['title'] }}@if (!empty($item['meta'])) - {{ $item['meta'] }}@endif @if (!empty($item['badge']))({{ $item['badge'] }})@endif

@if (!empty($item['url']))
   {{ $item['url'] }}

@endif
@endforeach
