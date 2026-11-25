<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailChangeController extends Controller
{
    // saite no apstiprinājuma e-pasta, strādā bez ielogošanās jo ir parakstīta
    public function confirm(Request $request, User $user)
    {
        $email = (string) $request->query('email');

        // saite veca (jau apstiprināts vai pa vidu prasīts cits e-pasts)
        if ($user->deleted_at || !$user->pending_email || mb_strtolower($user->pending_email) !== mb_strtolower($email)) {
            return view('email-confirmed', ['ok' => false, 'reason' => 'old']);
        }

        // kamēr gaidīja, kāds cits ar šo e-pastu piereģistrējās
        if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
            $user->forceFill(['pending_email' => null])->save();

            return view('email-confirmed', ['ok' => false, 'reason' => 'taken']);
        }

        $oldEmail = $user->email;
        $user->forceFill(['email' => $user->pending_email, 'pending_email' => null])->save();

        // vecā e-pasta reset saite vairs neder
        DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();

        return view('email-confirmed', ['ok' => true, 'email' => $user->email]);
    }
}
