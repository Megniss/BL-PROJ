<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// vēlmju saraksts - grāmatas, ko lietotājs meklē
// notified_at - kad pēdējo reizi sūtījām e-pastu (lai nesūta 10 reizes dienā)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('author');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'title', 'author']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
