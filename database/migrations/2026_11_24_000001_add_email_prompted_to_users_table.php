<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// vai lietotājam jau parādīts logs "vari ieslēgt e-pastus iestatījumos"
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_prompted')->default(false)->after('two_factor_prompted');
        });

        // kam jau ir ieslēgts kāds e-pasts, tiem logu nerādām
        $ids = DB::table('email_subscriptions')->distinct()->pluck('user_id');
        DB::table('users')->whereIn('id', $ids)->update(['email_prompted' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_prompted');
        });
    }
};
