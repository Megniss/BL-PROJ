<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// pieteikuma iemesls no saraksta (sk. Complaint::CATEGORIES)
// vecajiem pieteikumiem paliek null - tiem rāda tikai tematu
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('subject');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
