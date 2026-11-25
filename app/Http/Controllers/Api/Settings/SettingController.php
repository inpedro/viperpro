<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Locales this casino offers, with native names and text direction (RTL support).
     */
    public function locales()
    {
        $enabled = array_map(fn (string $code) => [
            'code'      => $code,
            'name'      => \App\Core\Localization\Locales::meta($code)['name'],
            'native'    => \App\Core\Localization\Locales::meta($code)['native'],
            'dir'       => \App\Core\Localization\Locales::direction($code),
            'html_lang' => \App\Core\Localization\Locales::htmlLang($code),
        ], \App\Core\Localization\Locales::enabled());

        return response()->json([
            'current'  => app()->getLocale(),
            'default'  => \App\Core\Localization\Locales::tenantDefault(),
            'timezone' => \App\Core\Localization\Locales::timezone(),
            'enabled'  => $enabled,
        ]);
    }

    /** Which registration fields this casino asks for (phone / national ID). */
    public function registration()
    {
        return response()->json(\App\Core\Localization\RegistrationPolicy::config());
    }

    public function index()
    {
        $setting = \Helper::getSetting();

        return response()->json(['setting' => $setting ? $setting->only(\App\Models\Setting::PUBLIC_FIELDS) : null], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
