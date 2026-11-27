<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// vai citi redz manu vēlmju sarakstu profilā (tāpat kā show_on_map - pēc noklusējuma jā)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_wishlist')->default(true)->after('show_on_map');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_wishlist');
        });
    }
};
