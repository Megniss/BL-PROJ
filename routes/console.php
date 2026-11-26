<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

// katrs e-pasts savā dienā, lai nesanāk 4 vienā rītā (vajag php artisan schedule:work vai cron)
// 1 = pirmdiena, 3 = trešdiena, 5 = piektdiena, 6 = sestdiena
$emailDays = ['nearby' => 1, 'popular' => 3, 'authors' => 5, 'rated' => 6];
foreach ($emailDays as $type => $day) {
    Schedule::command("bookloop:emails $type")
        ->weeklyOn($day, '9:00')
        ->timezone('Europe/Riga')
        ->withoutOverlapping();
}

// izlogo visus (docker to palaiž katru reizi kad konteiners startē)
Artisan::command('auth:logout-all', function () {
    \App\Support\ServerBoot::start();
    $this->info('Visi lietotāji izlogoti.');
})->purpose('Izdzēš visus login tokenus');
