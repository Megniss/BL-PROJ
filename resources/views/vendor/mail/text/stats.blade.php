@props(['items' => []])
@foreach ($items as $item)
{{ $item['value'] }} {{ $item['label'] }}@if (!$loop->last) · @endif
@endforeach

