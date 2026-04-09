<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function browse(Request $request)
    {
        $query = \App\Models\Book::with('user:id,name')
            ->withAvg('ratings', 'stars')
            ->withCount('ratings')
            ->whereIn('status', ['Available', 'Pending'])
            ->whereHas('user', fn($q) => $q->where('is_blocked', false));

        // savējās grāmatas nerāda
        $user = auth('sanctum')->user();
        if ($user) {
            $query->where('user_id', '!=', $user->id);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('genre', 'like', "%{$search}%");
            });
        }

        if ($genres = $request->query('genres')) {
            $query->whereIn('genre', (array) $genres);
        }

        if ($languages = $request->query('languages')) {
            $query->whereIn('language', (array) $languages);
        }

        match ($request->query('sort', 'title_asc')) {
            'title_asc' => $query->orderBy('title', 'asc'),
            'title_desc' => $query->orderBy('title', 'desc'),
            'author_asc' => $query->orderBy('author', 'asc'),
            'author_desc' => $query->orderBy('author', 'desc'),
            'newest' => $query->orderBy('created_at', 'desc'),
            'popular' => $query->orderBy('ratings_count', 'desc'),
            'top_rated' => $query->orderByDesc('ratings_avg_stars'),
            default => $query->orderBy('title', 'asc'),
        };

        $perPage = min((int) $request->query('per_page', 12), 500);
        return response()->json($query->paginate($perPage));
    }

    public function index(Request $request)
    {
        return response()->json($request->user()->books()->latest()->get());
    }

    private function toPascalCase(string $value): string
    {
        // capitalize each word then strip spaces: "harry potter" -> "HarryPotter"
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

    // just for the landing page counters
    public function stats()
    {
        return response()->json([
            'books' => Book::where('status', 'Available')->count(),
            'users' => User::count(),
        ]);
    }
}
