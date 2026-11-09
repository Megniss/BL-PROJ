<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // enum vietā teksts, lai var arī UnderReview u.c.
            $table->string('status')->default('Available')->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->enum('status', ['Available', 'Pending', 'Swapped'])->default('Available')->change();
        });
    }
};
