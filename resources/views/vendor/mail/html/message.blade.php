<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
BookLoop
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
@isset($footerNote)
{!! $footerNote !!}

@endisset
© {{ date('Y') }} BookLoop · [Email settings]({{ url('/settings') }})
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
