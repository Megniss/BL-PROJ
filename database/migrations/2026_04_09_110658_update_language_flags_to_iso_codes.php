<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ISO valstu kodi priekš flag-icons
        DB::table('languages')->where('code', 'en')->update(['flag' => 'gb']);
        DB::table('languages')->where('code', 'lv')->update(['flag' => 'lv']);
    }

    public function down(): void
    {
        DB::table('languages')->where('code', 'en')->update(['flag' => 'EN']);
        DB::table('languages')->where('code', 'lv')->update(['flag' => 'LV']);
    }
};
