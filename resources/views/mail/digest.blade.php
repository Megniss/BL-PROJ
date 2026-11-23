<x-mail::message>
{{-- vārds html tagā, lai markdown to neapstrādā (sk. DigestTest) --}}
<h1>{{ $copy['icon'] }} {{ $copy['title'] }}</h1>
<p>Hi {{ $name }}! {{ $copy['intro'] }}</p>

@if ($stats)
<x-mail::stats :items="$stats" />

@endif
<x-mail::book-grid :items="$items" :placeholder="$copy['placeholder']" />

<x-mail::button :url="$buttonUrl">
{{ $copy['button'] }}
</x-mail::button>

Happy reading,<br>
the BookLoop team

<x-slot:footerNote>
You get this email because you turned on "{{ $copy['title'] }}" emails. [Unsubscribe from these]({{ $unsubscribeUrl }})
</x-slot:footerNote>
</x-mail::message>
