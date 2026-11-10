<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

// dati Playwright testiem — visiem parole Password1!
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $anna = $this->user('Anna Ozola', 'anna@e2e.test', [56.95, 24.11]);       // Rīga
        $janis = $this->user('Jānis Bērziņš', 'janis@e2e.test', [56.65, 23.72]);  // Jelgava
        $liga = $this->user('Līga Kalniņa', 'liga@e2e.test', [57.54, 25.43]);      // Valmiera
        $peteris = $this->user('Pēteris Liepa', 'peteris@e2e.test');

        $this->user('Ilze Kļava', 'twofa@e2e.test', extra: ['two_factor_enabled' => true]);
        $this->user('Kārlis Jauns', 'new@e2e.test', extra: ['two_factor_prompted' => false]);
        $this->user('Admin Admins', 'admin@e2e.test', extra: [
            'is_admin' => true,
            'admin_password' => 'admin12345678',
        ]);

        // viena grāmata trīs lietotājiem
        foreach ([$janis, $liga, $peteris] as $owner) {
            $this->book($owner, 'HarryPotter', 'JkRowling', 'Fantāzija');
        }

        $this->book($anna, 'Dune', 'FrankHerbert', 'Fantāzija');
        $this->book($anna, 'MazaisPrincis', 'SentEkziperi', 'Bērnu grāmatas');
        $this->book($janis, 'Hobits', 'Tolkien', 'Fantāzija');
        $this->book($liga, 'Bille', 'VizmaBelsevica', 'Romāns');
    }

    private function user(string $name, string $email, ?array $place = null, array $extra = []): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'Password1!']);

        $user->forceFill($extra + [
            'two_factor_prompted' => true,
            'latitude' => $place[0] ?? null,
            'longitude' => $place[1] ?? null,
        ])->save();

        return $user;
    }

    private function book(User $owner, string $title, string $author, string $genre): void
    {
        Book::create([
            'user_id' => $owner->id,
            'title' => $title,
            'author' => $author,
            'genre' => $genre,
            'language' => 'Latviešu',
            'condition' => 'Good',
            'status' => 'Available',
        ]);
    }
}
