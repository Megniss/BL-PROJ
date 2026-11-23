<?php

namespace App\Http\Controllers;

use App\Mail\DigestEmail;
use App\Models\User;
use App\Support\Digest;
use Illuminate\Http\Request;

class DigestController extends Controller
{
    // saite no e-pasta apakšas - strādā bez ielogošanās, jo saite ir parakstīta
    // atrakstās tikai no šī veida, pārējie e-pasti paliek kā bija
    public function unsubscribe(User $user, string $type)
    {
        $user->emailSubscriptions()->where('type', $type)->delete();

        return view('digest-unsubscribed', ['title' => DigestEmail::COPY[$type]['title']]);
    }

    // gmail/outlook "unsubscribe" poga sūta POST (RFC 8058)
    public function unsubscribeOneClick(User $user, string $type)
    {
        $user->emailSubscriptions()->where('type', $type)->delete();

        return response()->noContent();
    }

    // tikai lokāli: apskatīt e-pastu pārlūkā, neko nesūtot
    // /dev/mail/digest/popular, ?user=ID konkrētam lietotājam
    public function preview(Request $request, string $type = 'popular')
    {
        $user = $request->query('user')
            ? User::findOrFail($request->query('user'))
            : (User::whereNotNull('latitude')->whereNull('deleted_at')->first() ?? User::firstOrFail());

        $global = Digest::global();

        return new DigestEmail($user, $type, Digest::items($type, $user, $global), $global['week']);
    }
}
