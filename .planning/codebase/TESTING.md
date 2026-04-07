# Testing

How tests are written and run in the BookLoop project.

---

## Running tests

```bash
# Run the full test suite
composer run test

# Run a single test class or method
php artisan test --filter AuthTest
php artisan test --filter test_user_can_register
```

Tests use an in-memory SQLite database (configured in `phpunit.xml`) so they do not touch the development database.

---

## Structure

```
tests/
  Feature/
    AuthTest.php          -- registration, login, logout, /me endpoint
    BookTest.php          -- CRUD for books, browse/search
    MessageTest.php       -- conversations, threads, unread count
    ProfileTest.php       -- profile read/update, password change, notifications, swap history
    SwapRequestTest.php   -- creating, accepting, declining, cancelling swaps
  Unit/
    ExampleTest.php       -- placeholder
  TestCase.php            -- base class
```

All current tests are Feature tests that hit the HTTP layer via `postJson`, `getJson`, `patchJson`, etc.

---

## Conventions

### Every test class
- Extends `Tests\TestCase`
- Uses the `RefreshDatabase` trait — the database is wiped and re-migrated before each test
- Lives in `namespace Tests\Feature`

### Creating test data
Use factories:
```php
$user = User::factory()->create();
$book = Book::factory()->create(['user_id' => $user->id, 'status' => 'Available']);
```

Override only the fields that matter for the test. Let the factory supply the rest.

When a test needs the same base data repeated, extract a private helper method:
```php
private function bookData(array $overrides = []): array
{
    return array_merge([...defaults...], $overrides);
}
```

### Authentication
Use `actingAs($user)` to make authenticated requests — do not manually set headers in most tests.

For token-based tests (e.g. testing that a token is revoked on logout):
```php
$token = $user->createToken('test')->plainTextToken;
$this->withHeader('Authorization', "Bearer {$token}")->postJson(...);
```

### Assertions — what to check
- HTTP status: `assertStatus(201)`, `assertOk()`, `assertUnauthorized()`, `assertForbidden()`, `assertStatus(422)`
- Response shape: `assertJsonStructure([...])`, `assertJsonFragment([...])`, `assertJsonPath('key', $value)`
- Database state: `assertDatabaseHas('table', [...])`, `assertDatabaseMissing('table', [...])`
- Collection size: `assertJsonCount(N)` or `$this->assertCount(N, $response->json())`
- Model refresh: `$model->fresh()->field` to re-fetch from the DB after a request

### What to test

For each API endpoint, cover at minimum:
1. Happy path (correct data, correct user)
2. Unauthenticated request returns 401 (where the route requires auth)
3. Authorization — a user cannot act on another user's resource (403)
4. Validation — missing or invalid input returns 422

For business logic (e.g. swap requests):
- Test side effects: after accepting a swap, check that book ownership actually changed in the database
- Test state guards: a book with status `Pending` cannot be deleted; a duplicate swap request is rejected

### Test method naming
Use `snake_case` with the `test_` prefix and a plain English description:
```
test_user_can_add_a_book
test_user_cannot_edit_another_users_book
test_owner_can_accept_swap
test_fetching_thread_marks_incoming_messages_as_read
```

---

## Factories reference

| Factory | Key overridable fields |
|---|---|
| `User::factory()` | `name`, `email`, `password` |
| `Book::factory()` | `user_id`, `title`, `author`, `genre`, `language`, `condition`, `status`, `description` |

There is no `SwapRequest` factory — create records directly with `SwapRequest::create([...])` or via the API endpoint.

There is no `Message` factory — create records directly with `Message::create([...])`.
