<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return view('dashboard', ['modules' => $user->visibleModules()]);
        }

        return view('dashboard', [
            'modules' => config('modules'),
            'stats' => [
                'sub_admins' => User::subAdmins()->count(),
                'active' => User::subAdmins()->where('is_active', true)->count(),
                'inactive' => User::subAdmins()->where('is_active', false)->count(),
                'designations' => Designation::count(),
            ],
            'recentUsers' => User::subAdmins()->with('designation')->latest()->take(5)->get(),
        ]);
    }
}
