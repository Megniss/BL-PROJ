<?php

namespace App\Http\Controllers;

use App\Support\Chat;
use Illuminate\Http\Request;

// "raksta..." burbuļi abiem čatiem
// chat = "user:{id}" parastai sarunai vai "support:{id}" atbalsta pieteikumam
class TypingController extends Controller
{
    // es rakstu
    public function store(Request $request)
    {
        $request->validate(['chat' => ['required', 'string', 'max:50']]);

        if (!Chat::startTyping($request->user(), $request->chat)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(null, 204);
    }

    // vai otrs raksta
    public function show(Request $request)
    {
        $request->validate(['chat' => ['required', 'string', 'max:50']]);

        return response()->json(['typing' => Chat::isOtherTyping($request->user(), $request->chat)]);
    }
}
