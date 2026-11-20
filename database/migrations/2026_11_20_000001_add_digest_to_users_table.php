<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// iknedēļas apkopojums - tikai ja lietotājs pats ieslēdz (gdpr)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('digest_opt_in')->default(false)->after('show_on_map');
            $table->timestamp('digest_sent_at')->nullable()->after('digest_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['digest_opt_in', 'digest_sent_at']);
        });
    }
};
