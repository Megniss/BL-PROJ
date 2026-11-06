<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\User;
use App\Support\Distance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    // grāmatas, ko var dabūt apmaiņā — viena kartīte katram nosaukumam + autoram
    public function browse(Request $request)
    {
        $user = auth('sanctum')->user();
        $hasLocation = $user?->hasLocation() ?? false;

        $query = $this->swappableCopies($user);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('books.title', 'like', "%{$search}%")
                  ->orWhere('books.author', 'like', "%{$search}%")
                  ->orWhere('books.genre', 'like', "%{$search}%");
            });
        }

        if ($genres = $request->query('genres')) {
            $query->whereIn('books.genre', (array) $genres);
        }

        if ($languages = $request->query('languages')) {
            $query->whereIn('books.language', (array) $languages);
        }

        $distance = $hasLocation ? $this->ownerDistanceSql($user) : null;
        if ($distance && ($km = (int) $request->query('max_km'))) {
            $query->whereRaw("$distance <= " . Distance::limit($km));
        }

        // visu eksemplāru vērtējumi kopā
        $sameBook = 'lower(rb.title) = lower(books.title) and lower(rb.author) = lower(books.author)';

        $query->selectRaw('max(books.id) as rep_id, count(*) as copies_count, max(books.created_at) as newest')
            ->selectRaw('max(books.cover_image) as any_cover')
            ->selectRaw("(select avg(r.stars) from ratings r join books rb on rb.id = r.book_id where $sameBook) as ratings_avg_stars")
            ->selectRaw("(select count(*) from ratings r join books rb on rb.id = r.book_id where $sameBook) as ratings_count")
            ->groupByRaw('lower(books.title), lower(books.author)');

        if ($distance) {
            $query->selectRaw("min($distance) as distance_sq");
        }

        $sort = $request->query('sort', 'title_asc');
        if ($sort === 'nearest' && !$distance) {
            $sort = 'title_asc';
        }

        match ($sort) {
            'title_desc' => $query->orderByRaw('lower(books.title) desc'),
            'author_asc' => $query->orderByRaw('lower(books.author) asc'),
            'author_desc' => $query->orderByRaw('lower(books.author) desc'),
            'newest' => $query->orderByDesc('newest'),
            'popular' => $query->orderByDesc('ratings_count'),
            'top_rated' => $query->orderByDesc('ratings_avg_stars'),
            'nearest' => $query->orderByRaw('distance_sq is null')->orderBy('distance_sq'),
            default => $query->orderByRaw('lower(books.title) asc'),
        };

        $perPage = min((int) $request->query('per_page', 12), 500);
        $groups = $query->paginate($perPage);

        // katrai grupai viens eksemplārs, ko rādīt kartītē
        $reps = Book::with('user:id,name')->whereIn('id', $groups->pluck('rep_id'))->get()->keyBy('id');

        $groups->setCollection($groups->getCollection()->map(function ($g) use ($reps) {
            $book = $reps[$g->rep_id];

            return [
                ...$book->toArray(),
                'cover_image' => $book->cover_image ?? $g->any_cover,
                'copies_count' => (int) $g->copies_count,
                'ratings_avg_stars' => $g->ratings_avg_stars,
                'ratings_count' => (int) $g->ratings_count,
                'distance_km' => Distance::toKm($g->distance_sq ?? null),
            ];
        }));

        return response()->json([...$groups->toArray(), 'has_location' => $hasLocation]);
    }

    // visi pieejamie konkrētās grāmatas eksemplāri
    public function copies(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'author' => ['required', 'string'],
        ]);

        $user = auth('sanctum')->user();

        if ($user && $this->ownedKeys($user)->contains($this->key($data['title'], $data['author']))) {
            return response()->json(['i_have' => true, 'copies' => []]);
        }

        $query = $this->swappableCopies($user)
            ->with('user:id,name')
            ->withAvg('ratings', 'stars')
            ->withCount('ratings')
            ->whereRaw('lower(books.title) = ?', [mb_strtolower($data['title'])])
            ->whereRaw('lower(books.author) = ?', [mb_strtolower($data['author'])]);

        if ($user?->hasLocation()) {
            $query->selectRaw($this->ownerDistanceSql($user) . ' as distance_sq')
                ->orderByRaw('distance_sq is null')->orderBy('distance_sq');
        }

        $blocked = $user ? $user->blockedUserIds() : collect();

        $copies = $query->latest('books.created_at')->get()->map(function ($book) use ($blocked) {
            $book->distance_km = Distance::toKm($book->distance_sq);
            $book->blocked = $blocked->contains($book->user_id);
            unset($book->distance_sq);
            return $book;
        });

        return response()->json(['i_have' => false, 'copies' => $copies]);
    }

    // ieteikumi pievienojot grāmatu — jau esošie nosaukumi
    public function autocomplete(Request $request)
    {
        $q = $this->normalize((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $mine = $this->ownedKeys($request->user());

        $results = Book::where('status', '!=', 'Removed')
            ->where(fn($w) => $w->where('title', 'like', "%{$q}%")->orWhere('author', 'like', "%{$q}%"))
            ->selectRaw('min(title) as title, min(author) as author, min(genre) as genre, count(*) as copies')
            ->groupByRaw('lower(title), lower(author)')
            ->orderByDesc('copies')
            ->limit(8)
            ->get()
            ->map(fn($b) => [
                'title' => $b->title,
                'author' => $b->author,
                'genre' => $b->genre,
                'mine' => $mine->contains($this->key($b->title, $b->author)),
            ]);

        return response()->json($results);
    }

    public function authors(Request $request)
    {
        $q = $this->normalize((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Book::where('status', '!=', 'Removed')
                ->where('author', 'like', "%{$q}%")
                ->selectRaw('min(author) as author, count(*) as books')
                ->groupByRaw('lower(author)')
                ->orderByDesc('books')
                ->limit(8)
                ->pluck('author')
        );
    }

    // eksemplāri, ko lietotājs var pieprasīt — bez savām un jau esošām grāmatām
    private function swappableCopies(?User $user)
    {
        $query = Book::query()
            ->where('books.status', 'Available')
            ->whereHas('user', fn($q) => $q->where('is_blocked', false));

        if ($user) {
            $query->where('books.user_id', '!=', $user->id)
                ->whereNotExists(fn($q) => $q->selectRaw(1)
                    ->from('books as mine')
                    ->where('mine.user_id', $user->id)
                    ->where('mine.status', '!=', 'Removed')
                    ->whereRaw('lower(mine.title) = lower(books.title)')
                    ->whereRaw('lower(mine.author) = lower(books.author)'));
        }

        return $query;
    }

    private function ownerDistanceSql(User $user): string
    {
        $expr = Distance::sql($user, 'owners.latitude', 'owners.longitude');

        return "(select $expr from users as owners where owners.id = books.user_id)";
    }

    // grāmatas, kas lietotājam jau ir
    private function ownedKeys(User $user)
    {
        return $user->books()->where('status', '!=', 'Removed')->get(['title', 'author'])
            ->map(fn($b) => $this->key($b->title, $b->author));
    }

    private function key(string $title, string $author): string
    {
        return mb_strtolower($title) . '|' . mb_strtolower($author);
    }

    // tāpat kā saglabājot — bez atstarpēm
    private function normalize(string $value): string
    {
        return str_replace(' ', '', trim($value));
    }

    public function index(Request $request)
    {
        return response()->json($request->user()->books()->latest()->get());
    }

    // pirmie burti lieli, atstarpes ārā
    private function toPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(mb_strtolower($value)));
    }

    public function store(Request $request)
    {
        $request->merge([
            'title' => $this->toPascalCase($request->input('title', '')),
            'author' => $this->toPascalCase($request->input('author', '')),
        ]);

        $userId = $request->user()->id;

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255',
                'regex:/[\p{L}]/u',
                Rule::unique('books')->where(fn($q) => $q->where('user_id', $userId)->where('author', $request->input('author')))],
            'author' => ['required', 'string', 'min:2', 'max:255',
                'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'genre' => ['required', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:100'],
            'condition' => ['required', 'in:New,Good,Fair,Worn'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'title.unique' => 'You already have a book with this title and author in your library.',
            'title.min' => 'Title must be at least 2 characters.',
            'title.regex' => 'Title must contain at least one letter.',
            'author.min' => 'Author name must be at least 2 characters.',
            'author.regex' => 'Author name can only contain letters, spaces, hyphens, apostrophes, and dots.',
        ]);

        $book = $request->user()->books()->create($data);
        return response()->json($book, 201);
    }

    public function update(Request $request, Book $book)
    {
        if ($book->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->merge([
            'title' => $this->toPascalCase($request->input('title', '')),
            'author' => $this->toPascalCase($request->input('author', '')),
        ]);

        $userId = $request->user()->id;

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255',
                'regex:/[\p{L}]/u',
                Rule::unique('books')->where(fn($q) => $q->where('user_id', $userId)->where('author', $request->input('author')))->ignore($book->id)],
            'author' => ['required', 'string', 'min:2', 'max:255',
                'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'genre' => ['required', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:100'],
            'condition' => ['required', 'in:New,Good,Fair,Worn'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'title.unique' => 'You already have a book with this title and author in your library.',
            'title.min' => 'Title must be at least 2 characters.',
            'title.regex' => 'Title must contain at least one letter.',
            'author.min' => 'Author name must be at least 2 characters.',
            'author.regex' => 'Author name can only contain letters, spaces, hyphens, apostrophes, and dots.',
        ]);

        $book->update($data);
        return response()->json($book);
    }

    public function destroy(Request $request, Book $book)
    {
        if ($book->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // ja grāmata pending, nevar dzēst
        if ($book->status === 'Pending') {
            return response()->json(['message' => 'This book is part of a pending swap. Cancel the swap first.'], 422);
        }

        $book->delete();
        return response()->json(['message' => 'Book deleted.']);
    }

    public function uploadCover(Request $request, Book $book)
    {
        if ($book->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate(['cover' => ['required', 'image', 'max:2048']]);

        // dzēšam veco attēlu ja ir
        if ($book->cover_image) {
            Storage::disk('public')->delete($book->cover_image);
        }

        $path = $request->file('cover')->store('covers', 'public');
        $book->update(['cover_image' => $path]);

        return response()->json(['cover_image' => $path]);
    }

    public function removeCover(Request $request, Book $book)
    {
        if ($book->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($book->cover_image) {
            Storage::disk('public')->delete($book->cover_image);
            $book->update(['cover_image' => null]);
        }

        return response()->json(['message' => 'Cover removed.']);
    }

    // līdzīgas grāmatas — tāds pats žanrs vai valoda, katra grāmata vienreiz
    public function suggestions(Book $book)
    {
        $repIds = $this->swappableCopies(auth('sanctum')->user())
            ->where(fn($q) => $q->where('books.genre', $book->genre)->orWhere('books.language', $book->language))
            ->where(fn($q) => $q->whereRaw('lower(books.title) != ?', [mb_strtolower($book->title)])
                ->orWhereRaw('lower(books.author) != ?', [mb_strtolower($book->author)]))
            ->selectRaw('max(books.id) as rep_id')
            ->groupByRaw('lower(books.title), lower(books.author)')
            ->inRandomOrder()
            ->limit(6)
            ->pluck('rep_id');

        $similar = Book::with('user:id,name')
            ->withAvg('ratings', 'stars')
            ->withCount('ratings')
            ->whereIn('id', $repIds)
            ->get();

        return response()->json($similar);
    }

    // skaitļi priekš galvenās lapas
    public function stats()
    {
        return response()->json([
            'books' => Book::where('status', 'Available')->count(),
            'users' => User::whereNull('deleted_at')->count(),
        ]);
    }
}
