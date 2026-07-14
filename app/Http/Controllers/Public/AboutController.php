<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\OrgMember;

class AboutController extends Controller
{
    public function index()
    {
        $history = Setting::where('key', 'about_history')->first()?->value ?? '';
        $mission = Setting::where('key', 'about_mission')->first()?->value ?? '';
        $vision = Setting::where('key', 'about_vision')->first()?->value ?? '';
        $members = OrgMember::where('is_active', true)->orderBy('order')->get();

        return view('public.about', compact('history', 'mission', 'vision', 'members'));
    }
}
