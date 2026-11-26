<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// katru reizi kad serveri palaiž, visi tiek izlogoti
// id glabājas failā, lapa to ieliek <meta>, un pārlūks salīdzina ar savu
class ServerBoot
{
    private static function file(): string
    {
        return storage_path('framework/server-boot');
    }

    public static function id(): string
    {
        return is_file(self::file()) ? trim(file_get_contents(self::file())) : '';
    }

    // izdzēš visus login tokenus un uztaisa jaunu id
    public static function start(): void
    {
        DB::table('personal_access_tokens')->delete();
        file_put_contents(self::file(), Str::random(16));
    }
}
