<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Book $book): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    public function restore(User $user, Book $book): bool
    {
        return false;
    }

    public function forceDelete(User $user, Book $book): bool
    {
        return false;
    }
}
