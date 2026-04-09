<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Store ISO 3166-1 alpha-2 country codes (lowercase) for flag-icons CSS library
        DB::table('languages')->where('code', 'en')->update(['flag' => 'gb']);
        DB::table('languages')->where('code', 'lv')->update(['flag' => 'lv']);
    }

    public function down(): void
    {
        DB::table('languages')->where('code', 'en')->update(['flag' => 'EN']);
        DB::table('languages')->where('code', 'lv')->update(['flag' => 'LV']);
    }
};
