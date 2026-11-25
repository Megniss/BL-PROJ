<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// kurš un kad apmaiņu pieņēma/noraidīja, lai admins redz kas notika
// closed_by: owner, requester (atcēla), admin, system (grāmata aizgāja citā apmaiņā), deleted (dzēsts konts)
// vecajiem ierakstiem paliek null - nav zināms
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('swap_requests', function (Blueprint $table) {
            $table->string('closed_by', 20)->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('swap_requests', function (Blueprint $table) {
            $table->dropColumn(['closed_by', 'closed_at']);
        });
    }
};
