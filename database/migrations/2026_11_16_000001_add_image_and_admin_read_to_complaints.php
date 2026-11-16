<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // bildes atbalsta čatā, tāpat kā parastajās ziņās
        Schema::table('complaint_messages', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('body');
            $table->boolean('image_removed')->default(false)->after('image_path');
        });

        // null = admins vēl nav redzējis jaunāko lietotāja ziņu
        Schema::table('complaints', function (Blueprint $table) {
            $table->timestamp('admin_read_at')->nullable()->after('status');
        });

        // vecie pieteikumi skaitās jau redzēti
        DB::table('complaints')->update(['admin_read_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('complaint_messages', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_removed']);
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('admin_read_at');
        });
    }
};
