@props(['url'])
<tr>
<td class="header">
{{-- logo kā teksts, nevis bilde - nav jāielādē no servera un dark mode neizskatās dīvaini --}}
<a href="{{ $url }}" style="display: inline-block;">
📚 {!! $slot !!}
</a>
</td>
</tr>
