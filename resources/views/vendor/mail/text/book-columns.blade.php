@props(['sections' => []])
@foreach ($sections as $s)
{{ $s['icon'] }} {{ $s['title'] }}

@forelse ($s['items'] ?? [] as $i => $item)
{{ $i + 1 }}. {{ $item['title'] }}@if (!empty($item['meta'])) - {{ $item['meta'] }}@endif @if (!empty($item['badge']))({{ $item['badge'] }})@endif

@empty
{{ $s['text'] ?? '' }} @if (!empty($s['link'])){{ $s['link']['url'] }}@endif

@endforelse

@endforeach
