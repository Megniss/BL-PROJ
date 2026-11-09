<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('swap_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('requester_id');
        });

        // esošajiem pieprasījumiem īpašnieks ir wanted_book īpašnieks
        DB::statement("UPDATE swap_requests SET owner_id = (SELECT user_id FROM books WHERE books.id = swap_requests.wanted_book_id)");

        // pieņemtajās apmaiņās grāmatas jau samainītas, tāpēc ņem offered_book
        DB::statement("UPDATE swap_requests SET owner_id = (SELECT user_id FROM books WHERE books.id = swap_requests.offered_book_id) WHERE status = 'accepted'");

        Schema::table('swap_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable(false)->change();
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('swap_requests', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
