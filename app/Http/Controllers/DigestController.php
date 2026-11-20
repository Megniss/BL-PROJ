<?php

namespace App\Http\Controllers;

use App\Mail\WeeklyDigest;
use App\Models\User;
use App\Support\Digest;
use Illuminate\Http\Request;

class DigestController extends Controller
{
    // saite no e-pasta apakšas - strādā bez ielogošanās, jo saite ir parakstīta
    public function unsubscribe(User $user)
    {
        $user->forceFill(['digest_opt_in' => false])->save();

        return view('digest-unsubscribed');
    }

    // gmail/outlook "unsubscribe" poga sūta POST (RFC 8058)
    public function unsubscribeOneClick(User $user)
    {
        $user->forceFill(['digest_opt_in' => false])->save();

        return response()->noContent();
    }

    // tikai lokāli: apskatīt e-pastu pārlūkā, neko nesūtot
    public function preview(Request $request)
    {
        $user = $request->query('user')
            ? User::findOrFail($request->query('user'))
            : (User::whereNotNull('latitude')->whereNull('deleted_at')->first() ?? User::firstOrFail());

        return new WeeklyDigest($user, Digest::global(), Digest::nearby($user));
    }
}
