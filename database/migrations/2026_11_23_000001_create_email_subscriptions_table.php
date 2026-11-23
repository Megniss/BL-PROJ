<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// katram e-pasta veidam sava piekrišana (nearby, popular, authors, rated)
// ieraksts ir = lietotājs to grib saņemt, nav = nesūtām
return new class extends Migration
{
    private array $types = ['nearby', 'popular', 'authors', 'rated'];

    public function up(): void
    {
        Schema::create('email_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });

        // kas bija ieslēdzis veco apkopojumu, tam ieslēdzam visus veidus
        $ids = DB::table('users')->where('digest_opt_in', true)->pluck('id');
        foreach ($ids as $id) {
            foreach ($this->types as $type) {
                DB::table('email_subscriptions')->insert([
                    'user_id' => $id,
                    'type' => $type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['digest_opt_in', 'digest_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('digest_opt_in')->default(false)->after('show_on_map');
            $table->timestamp('digest_sent_at')->nullable()->after('digest_opt_in');
        });

        $ids = DB::table('email_subscriptions')->distinct()->pluck('user_id');
        DB::table('users')->whereIn('id', $ids)->update(['digest_opt_in' => true]);

        Schema::dropIfExists('email_subscriptions');
    }
};
