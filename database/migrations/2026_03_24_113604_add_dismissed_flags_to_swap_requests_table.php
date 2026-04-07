<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('swap_requests', function (Blueprint $table) {
            $table->boolean('requester_dismissed')->default(false)->after('status');
            $table->boolean('owner_dismissed')->default(false)->after('requester_dismissed');
        });
    }

    public function down(): void
    {
        Schema::table('swap_requests', function (Blueprint $table) {
            $table->dropColumn(['requester_dismissed', 'owner_dismissed']);
        });
    }
};
