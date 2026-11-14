<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function edit(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin(), 403, 'The Super Admin always has full access.');

        // Optionally pre-fill the matrix from another sub-admin's rights.
        $source = $request->filled('copy_from')
            ? User::subAdmins()->findOrFail($request->copy_from)
            : $user;

        return view('admin.users.permissions', [
            'user' => $user->load('designation'),
            'modules' => config('modules'),
            'actions' => UserPermission::ACTIONS,
            'granted' => $source->permissions()->get()->keyBy('module'),
            'copiedFrom' => $source->is($user) ? null : $source,
            'otherUsers' => User::subAdmins()->whereKeyNot($user->id)->with('designation')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin(), 403, 'The Super Admin always has full access.');

        $request->validate(['permissions' => ['nullable', 'array']]);
        $input = $request->input('permissions', []);

        DB::transaction(function () use ($user, $input) {
            foreach (array_keys(config('modules')) as $module) {
                $row = collect(UserPermission::ACTIONS)
                    ->mapWithKeys(fn ($action) => ["can_$action" => ! empty($input[$module][$action])])
                    ->all();

                // Create / edit / delete are meaningless without seeing the records.
                if ($row['can_create'] || $row['can_edit'] || $row['can_delete']) {
                    $row['can_view'] = true;
                }
                // Any right implies the module is shown; hiding a module revokes everything.
                if ($row['can_view']) {
                    $row['can_show'] = true;
                }
                if (! $row['can_show']) {
                    $row = array_map(fn () => false, $row);
                }

                $user->permissions()->updateOrCreate(['module' => $module], $row);
            }

            // Drop rows for modules that were removed from config/modules.php.
            $user->permissions()->whereNotIn('module', array_keys(config('modules')))->delete();
        });

        return redirect()->route('admin.users.permissions.edit', $user)
            ->with('success', "Access rights for \"{$user->name}\" saved.");
    }
}
