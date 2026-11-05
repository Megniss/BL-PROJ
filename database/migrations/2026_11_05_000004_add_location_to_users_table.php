<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // noapaļots līdz ~1 km, precīzu adresi neglabā
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('latitude', 5, 2)->nullable()->after('show_swap_history');
            $table->decimal('longitude', 5, 2)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
