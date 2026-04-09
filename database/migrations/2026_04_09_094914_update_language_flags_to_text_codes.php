<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Country flag emoji sequences don't render on Windows — switch to text codes
        DB::table('languages')->where('code', 'en')->update(['flag' => 'EN']);
        DB::table('languages')->where('code', 'lv')->update(['flag' => 'LV']);
    }

    public function down(): void
    {
        DB::table('languages')->where('code', 'en')->update(['flag' => '🇬🇧']);
        DB::table('languages')->where('code', 'lv')->update(['flag' => '🇱🇻']);
    }
};
