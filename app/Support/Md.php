<?php

namespace App\Support;

// lietotāja teksts e-pastos iet caur markdown
// bez šī vārds "[Spied šeit](http://...)" kļūst par īstu saiti e-pastā
// < un > neaiztiekam, tos jau escapo blade
class Md
{
    public static function escape(?string $text): string
    {
        return preg_replace('/([\\\\`*_\[\]])/', '\\\\$1', (string) $text);
    }
}
