<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\TranslationOverride;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    // public: active languages for the switcher
    public function index()
    {
        return response()->json(
            Language::where('is_active', true)->orderBy('sort_order')->get(['code', 'name', 'flag'])
        );
    }

    // public: DB overrides for a locale
    public function translations(string $code)
    {
        return response()->json(
            TranslationOverride::where('language_code', $code)->pluck('value', 'key')
        );
    }

    // admin: all languages (including inactive)
    public function all()
    {
        return response()->json(Language::orderBy('sort_order')->get());
    }

    // admin: add a new language
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:languages,code', 'regex:/^[a-z]{2,10}$/'],
            'name' => ['required', 'string', 'max:100'],
            'flag' => ['required', 'string', 'max:20'],
        ]);

        $lang = Language::create([
            'code'       => strtolower($request->code),
            'name'       => $request->name,
            'flag'       => strtolower($request->flag),
            'is_active'  => true,
            'sort_order' => (Language::max('sort_order') ?? 0) + 1,
        ]);

        return response()->json($lang, 201);
    }

    // admin: update name/flag of an existing language
    public function update(Request $request, string $code)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'flag' => ['required', 'string', 'max:20'],
        ]);

        $lang = Language::findOrFail($code);
        $lang->update(['name' => $request->name, 'flag' => strtolower($request->flag)]);
        return response()->json($lang);
    }

    // admin: deactivate a language (frontend stops using it; data stays)
    public function deactivate(string $code)
    {
        if ($code === 'en') {
            return response()->json(['message' => 'Cannot remove the default language.'], 422);
        }

        $lang = Language::findOrFail($code);
        $lang->update(['is_active' => false]);
        return response()->json(['message' => 'Language removed.']);
    }

    // admin: reactivate a language
    public function reactivate(string $code)
    {
        $lang = Language::findOrFail($code);
        $lang->update(['is_active' => true]);
        return response()->json(['message' => 'Language restored.']);
    }

    // admin: get current overrides for editing
    public function getTranslations(string $code)
    {
        return response()->json(
            TranslationOverride::where('language_code', $code)->pluck('value', 'key')
        );
    }

    // admin: save batch overrides
    public function saveTranslations(Request $request, string $code)
    {
        $request->validate([
            'overrides'   => ['required', 'array'],
            'overrides.*' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($request->overrides as $key => $value) {
            if ($value === null || $value === '') {
                TranslationOverride::where('language_code', $code)->where('key', $key)->delete();
            } else {
                TranslationOverride::updateOrCreate(
                    ['language_code' => $code, 'key' => $key],
                    ['value' => $value]
                );
            }
        }

        return response()->json(['message' => 'Translations saved.']);
    }
}
