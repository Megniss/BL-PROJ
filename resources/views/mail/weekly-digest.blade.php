<x-mail::message>
{{-- vārds html tagā, nevis markdown, lai vārdā ielikts [saite](...) nekļūst par īstu saiti --}}
<h1>Hi {{ $name }}! 👋</h1>

Here is what has been happening on BookLoop this week.

<x-mail::stats :items="$stats" />

<x-mail::book-columns :sections="$sections" />

<x-mail::button :url="url('/browse')">
Browse books
</x-mail::button>

Happy reading,<br>
the BookLoop team

<x-slot:footerNote>
You get this email because you turned on the weekly roundup. [Unsubscribe]({{ $unsubscribeUrl }})
</x-slot:footerNote>
</x-mail::message>
