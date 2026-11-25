<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// vecajām apmaiņām (pirms closed_by) ieliek minējumu, lai admin panelī nav "nav zināms"
// tas ir tikai minējums, nevis droši dati:
// - pieņemtās gandrīz vienmēr pieņēma īpašnieks
// - noraidītas un pieprasītājs paslēpis savā sarakstā - visticamāk pats atcēla
// - pārējās noraidītās - īpašnieks
// laiks - pēdējā izmaiņa (updated_at)
return new class extends Migration
{
    public function up(): void
    {
        $old = DB::table('swap_requests')->whereNull('closed_by')->where('status', '!=', 'pending');

        (clone $old)->where('status', 'accepted')
            ->update(['closed_by' => 'owner', 'closed_at' => DB::raw('updated_at')]);

        (clone $old)->where('status', 'declined')->where('requester_dismissed', true)
            ->update(['closed_by' => 'requester', 'closed_at' => DB::raw('updated_at')]);

        (clone $old)->where('status', 'declined')
            ->update(['closed_by' => 'owner', 'closed_at' => DB::raw('updated_at')]);
    }

    // atpakaļ neko nedara - vairs nevar atšķirt minētos no īstajiem
    public function down(): void
    {
    }
};
