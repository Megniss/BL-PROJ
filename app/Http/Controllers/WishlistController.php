<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// vēlmju saraksts - grāmatas, ko lietotājs meklē
class WishlistController extends Controller
{
    public const MAX_ITEMS = 50;

    public function index(Request $request)
    {
        $user = $request->user();

        // ja grāmata jau ir pašam (piem. dabūja apmaiņā) - no saraksta ārā
        $owned = $user->books()->where('status', '!=', 'Removed')->get(['title', 'author'])
            ->map(fn($b) => mb_strtolower($b->title) . '|' . mb_strtolower($b->author));

        $items = $user->wishlist()->latest()->get();
        foreach ($items as $i => $item) {
            if ($owned->contains(mb_strtolower($item->title) . '|' . mb_strtolower($item->author))) {
                $item->delete();
                $items->forget($i);
            }
        }

        $blocked = $user->blockedUserIds();

        // cik eksemplāru šobrīd var pieprasīt
        $items = $items->values()->map(function ($item) use ($user, $blocked) {
            $item->available = Book::where('status', 'Available')
                ->where('user_id', '!=', $user->id)
                ->whereNotIn('user_id', $blocked)
                ->whereHas('user', fn($q) => $q->where('is_blocked', false))
                ->whereRaw('lower(title) = ?', [mb_strtolower($item->title)])
                ->whereRaw('lower(author) = ?', [mb_strtolower($item->author)])
                ->count();
            return $item;
        });

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // tāpat kā grāmatām - lieli sākuma burti, bez atstarpēm, lai sakrīt
        $request->merge([
            'title' => $this->toPascalCase($request->input('title', '')),
            'author' => $this->toPascalCase($request->input('author', '')),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255', 'regex:/[\p{L}]/u',
                Rule::unique('wishlists')->where(fn($q) => $q->where('user_id', $user->id)->where('author', $request->input('author')))],
            'author' => ['required', 'string', 'min:2', 'max:255', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
        ], [
            'title.unique' => 'This book is already in your wishlist.',
            'title.min' => 'Title must be at least 2 characters.',
            'title.regex' => 'Title must contain at least one letter.',
            'author.min' => 'Author name must be at least 2 characters.',
            'author.regex' => 'Author name can only contain letters, spaces, hyphens, apostrophes, and dots.',
        ]);

        $ownsIt = $user->books()->where('status', '!=', 'Removed')
            ->whereRaw('lower(title) = ?', [mb_strtolower($data['title'])])
            ->whereRaw('lower(author) = ?', [mb_strtolower($data['author'])])
            ->exists();
        if ($ownsIt) {
            return response()->json(['message' => 'You already have this book in your library.'], 422);
        }

        if ($user->wishlist()->count() >= self::MAX_ITEMS) {
            return response()->json(['message' => 'Your wishlist is full (max ' . self::MAX_ITEMS . ' books).'], 422);
        }

        $item = $user->wishlist()->create($data);

        return response()->json($item, 201);
    }

    public function destroy(Request $request, Wishlist $wishlist)
    {
        if ($wishlist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $wishlist->delete();
        return response()->json(['message' => 'Removed from wishlist.']);
    }

    private function toPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(mb_strtolower($value)));
    }
}
