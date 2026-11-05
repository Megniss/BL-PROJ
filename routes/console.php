<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// uzstāda admin paroli un piešķir admin tiesības
Artisan::command('admin:password {email}', function (string $email) {
    $user = \App\Models\User::where('email', $email)->whereNull('deleted_at')->first();

    if (!$user) {
        $this->error('Lietotājs nav atrasts.');
        return 1;
    }

    $password = $this->secret('Admin parole (vismaz 12 simboli)');

    if (strlen((string) $password) < 12) {
        $this->error('Parolei jābūt vismaz 12 simbolus garai.');
        return 1;
    }

    if ($password !== $this->secret('Atkārto paroli')) {
        $this->error('Paroles nesakrīt.');
        return 1;
    }

    $user->forceFill([
        'is_admin' => true,
        'is_blocked' => false,
        'admin_password' => $password,
        'admin_password_change' => false,
    ])->save();

    $this->info("Admin parole uzstādīta: {$user->name}");
})->purpose('Uzstāda lietotājam admin paroli');
