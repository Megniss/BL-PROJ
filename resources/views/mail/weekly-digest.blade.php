<x-mail::message>
{{-- vārds html tagā, nevis markdown, lai vārdā ielikts [saite](...) nekļūst par īstu saiti --}}
<h1>Hi {{ $name }}! 👋</h1>

Here is what has been happening on BookLoop.

<x-mail::panel>
**This week:** {{ $week['books'] }} new {{ $week['books'] == 1 ? 'book' : 'books' }} listed and {{ $week['swaps'] }} {{ $week['swaps'] == 1 ? 'swap' : 'swaps' }} completed.
</x-mail::panel>

@if ($nearby !== [])
## 📍 New near you

<x-mail::book-list :items="$nearby" />

@elseif (!$hasLocation)
## 📍 Books near you

Add your location in [settings]({{ url('/settings') }}) and we will show you new books within {{ \App\Support\Digest::NEARBY_KM }} km.

@endif
@if ($popular !== [])
## 🔁 Most swapped books

<x-mail::book-list :items="$popular" />

@endif
@if ($authors !== [])
## ✍️ Most popular authors

<x-mail::book-list :items="$authors" />

@endif
@if ($rated !== [])
## ⭐ Top rated

<x-mail::book-list :items="$rated" />

@endif
<x-mail::button :url="url('/browse')">
Browse books
</x-mail::button>

Happy reading,<br>
the BookLoop team

<x-slot:footerNote>
You get this email because you turned on the weekly roundup. [Unsubscribe]({{ $unsubscribeUrl }})
</x-slot:footerNote>
</x-mail::message>
