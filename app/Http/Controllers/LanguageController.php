<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Language;
use App\Models\TranslationOverride;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    // navigācijai vajag tikai aktīvās
    public function index()
    {
        return response()->json(
            Language::where('is_active', true)->orderBy('sort_order')->get(['code', 'name', 'flag'])
        );
    }

    // db override tulkojumi konkrētai valodai
    public function translations(string $code)
    {
        return response()->json(
            TranslationOverride::where('language_code', $code)->pluck('value', 'key')
        );
    }

    // admin redz visas, arī neaktīvās
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
            'code'      => strtolower($request->code),
            'name'      => $request->name,
            'flag'      => strtolower($request->flag),
            'is_active' => true,
            'sort_order' => (Language::max('sort_order') ?? 0) + 1,
        ]);

        AdminLog::create([
            'admin_id'    => $request->user()->id,
            'action'      => 'add_language',
            'target_type' => 'language',
            'target_id'   => null,
            'target_name' => $lang->name . ' (' . $lang->code . ')',
            'reason' => null,
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

        AdminLog::create([
            'admin_id'    => $request->user()->id,
            'action'      => 'edit_language',
            'target_type' => 'language',
            'target_id' => null,
            'target_name' => $lang->name . ' (' . $lang->code . ')',
            'reason' => null,
        ]);

        return response()->json($lang);
    }

    public function deactivate(Request $request, string $code)
    {
        // en ir rezerves valoda, to nedrīkst noņemt
        if ($code === 'en') {
            return response()->json(['message' => 'Cannot remove the default language.'], 422);
        }

        $lang = Language::findOrFail($code);
        $lang->update(['is_active' => false]);

        AdminLog::create([
            'admin_id'    => $request->user()->id,
            'action'      => 'remove_language',
            'target_type' => 'language',
            'target_id'   => null,
            'target_name' => $lang->name . ' (' . $lang->code . ')',
            'reason' => null,
        ]);

        return response()->json(['message' => 'Language removed.']);
    }

    public function reactivate(Request $request, string $code)
    {
        $lang = Language::findOrFail($code);
        $lang->update(['is_active' => true]);

        AdminLog::create([
            'admin_id'    => $request->user()->id,
            'action'      => 'restore_language',
            'target_type' => 'language',
            'target_id' => null,
            'target_name' => $lang->name . ' (' . $lang->code . ')',
            'reason' => null,
        ]);

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
                // tukšs = dzēš override, atgriežas pie faila
                TranslationOverride::where('language_code', $code)->where('key', $key)->delete();
            } else {
                TranslationOverride::updateOrCreate(
                    ['language_code' => $code, 'key' => $key],
                    ['value' => $value]
                );
            }
        }

        $lang = Language::find($code);
        AdminLog::create([
            'admin_id'    => $request->user()->id,
            'action'      => 'edit_translations',
            'target_type' => 'language',
            'target_id' => null,
            'target_name' => $lang ? $lang->name . ' (' . $code . ')' : $code,
            'reason' => count($request->overrides) . ' keys updated',
        ]);

        return response()->json(['message' => 'Translations saved.']);
    }
}
