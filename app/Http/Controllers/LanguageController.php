<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\TranslationOverride;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    // what the navbar needs
    public function index()
    {
        return response()->json(
            Language::where('is_active', true)->orderBy('sort_order')->get(['code', 'name', 'flag'])
        );
    }

    // DB overrides for a locale (merged with file translations on the frontend)
    public function translations(string $code)
    {
        return response()->json(
            TranslationOverride::where('language_code', $code)->pluck('value', 'key')
        );
    }

    // admin view, includes inactive ones too
    public function all()
    {
        return response()->json(Language::orderBy('sort_order')->get());
    }

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

    public function deactivate(string $code)
    {
        // english is the fallback, can't remove it
        if ($code === 'en') {
            return response()->json(['message' => 'Cannot remove the default language.'], 422);
        }

        $lang = Language::findOrFail($code);
        $lang->update(['is_active' => false]);
        return response()->json(['message' => 'Language removed.']);
    }

    public function reactivate(string $code)
    {
        $lang = Language::findOrFail($code);
        $lang->update(['is_active' => true]);
        return response()->json(['message' => 'Language restored.']);
    }

    public function getTranslations(string $code)
    {
        return response()->json(
            TranslationOverride::where('language_code', $code)->pluck('value', 'key')
        );
    }

    public function saveTranslations(Request $request, string $code)
    {
        $request->validate([
            'overrides'   => ['required', 'array'],
            'overrides.*' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($request->overrides as $key => $value) {
            if ($value === null || $value === '') {
                // empty = delete the override, fall back to file
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
