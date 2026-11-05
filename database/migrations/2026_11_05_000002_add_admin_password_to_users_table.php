<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('admin_password')->nullable()->after('is_admin');
            $table->boolean('admin_password_change')->default(false)->after('admin_password');
        });

        // līdz kuram laikam šim tokenam atvērts admin panelis
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->timestamp('admin_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['admin_password', 'admin_password_change']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('admin_until');
        });
    }
};
